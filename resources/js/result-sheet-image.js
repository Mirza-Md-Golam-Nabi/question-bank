/**
 * "Download as image" for the printable result sheet
 * (teacher/exam-results-print.blade.php).
 *
 * The sheet is redrawn onto a <canvas> from the text already on the page —
 * its heading lines, summary line and table cells — and saved as a PNG. The
 * browser lays the text out itself, so Bangla renders exactly as on screen,
 * and nothing has to be installed or sent to the server.
 *
 * A canvas can only be so tall, so a long sheet is split into several
 * images of ROWS_PER_IMAGE rows each, every one carrying the heading.
 */
const ROWS_PER_IMAGE = 300;
const WIDTH = 1000;
const PADDING = 40;
const ROW_HEIGHT = 34;
const SCALE = 2; // drawn at double size so the image stays sharp when zoomed

const FONT_FAMILY = getComputedStyle(document.body).fontFamily || 'sans-serif';
const font = (size, weight = 400) => `${weight} ${size}px ${FONT_FAMILY}`;

function textOf(root, selector) {
    return [...root.querySelectorAll(selector)].map((el) => el.textContent.trim().replace(/\s+/g, ' ')).filter(Boolean);
}

/** Shortens text with an ellipsis until it fits the column. */
function fit(context, text, maxWidth) {
    if (context.measureText(text).width <= maxWidth) {
        return text;
    }

    let shortened = text;

    while (shortened.length > 1 && context.measureText(`${shortened}…`).width > maxWidth) {
        shortened = shortened.slice(0, -1);
    }

    return `${shortened}…`;
}

function drawSheet({ headingLines, summary, headers, rows, centered, pageLabel }) {
    const tableWidth = WIDTH - PADDING * 2;
    // Position | Name | Phone/Email | Marks | Time taken
    const shares = [0.11, 0.3, 0.33, 0.11, 0.15];
    const columns = shares.map((share) => share * tableWidth);

    const headingHeight = headingLines.length * 34 + 16;
    const summaryHeight = summary ? 44 : 0;
    const height = PADDING + headingHeight + summaryHeight + ROW_HEIGHT * (rows.length + 1) + (pageLabel ? 36 : 0) + PADDING;

    const canvas = document.createElement('canvas');
    canvas.width = WIDTH * SCALE;
    canvas.height = height * SCALE;

    const context = canvas.getContext('2d');
    context.scale(SCALE, SCALE);
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, WIDTH, height);
    context.textBaseline = 'middle';

    let y = PADDING;

    // Heading: subject (largest), then title, then class.
    context.textAlign = 'center';
    headingLines.forEach((line, index) => {
        context.fillStyle = index === headingLines.length - 1 && headingLines.length > 2 ? '#4b5563' : '#111827';
        context.font = index === 0 ? font(26, 700) : font(18, index === 1 ? 600 : 400);
        context.fillText(fit(context, line, tableWidth), WIDTH / 2, y + 17);
        y += 34;
    });
    y += 16;

    if (summary) {
        context.strokeStyle = '#d1d5db';
        context.beginPath();
        context.moveTo(PADDING, y);
        context.lineTo(WIDTH - PADDING, y);
        context.moveTo(PADDING, y + 36);
        context.lineTo(WIDTH - PADDING, y + 36);
        context.stroke();

        context.fillStyle = '#111827';
        context.font = font(16, 500);
        context.textAlign = 'left';
        context.fillText(summary[0] ?? '', PADDING, y + 18);
        context.textAlign = 'right';
        context.fillText(summary[1] ?? '', WIDTH - PADDING, y + 18);
        y += summaryHeight;
    }

    const drawRow = (cells, { header = false, striped = false } = {}) => {
        if (header || striped) {
            context.fillStyle = header ? '#f3f4f6' : '#f9fafb';
            context.fillRect(PADDING, y, tableWidth, ROW_HEIGHT);
        }

        context.fillStyle = '#111827';
        context.font = font(15, header ? 700 : 400);

        let x = PADDING;

        cells.forEach((cell, index) => {
            const width = columns[index];
            // Position, marks and time are centred in their column, as on the page.
            const isCentered = centered[index];

            context.textAlign = isCentered ? 'center' : 'left';
            context.fillText(fit(context, cell, width - 20), isCentered ? x + width / 2 : x + 10, y + ROW_HEIGHT / 2);
            x += width;
        });

        context.strokeStyle = '#e5e7eb';
        context.beginPath();
        context.moveTo(PADDING, y + ROW_HEIGHT);
        context.lineTo(WIDTH - PADDING, y + ROW_HEIGHT);
        context.stroke();

        y += ROW_HEIGHT;
    };

    drawRow(headers, { header: true });
    rows.forEach((row, index) => drawRow(row, { striped: index % 2 === 1 }));

    if (pageLabel) {
        context.fillStyle = '#6b7280';
        context.font = font(13);
        context.textAlign = 'right';
        context.fillText(pageLabel, WIDTH - PADDING, y + 22);
    }

    return canvas;
}

function download(canvas, fileName) {
    const link = document.createElement('a');

    link.download = fileName;
    link.href = canvas.toDataURL('image/png');
    document.body.appendChild(link);
    link.click();
    link.remove();
}

async function downloadResultSheetImages(root) {
    const table = root.querySelector('[data-qb-result-sheet]');

    if (! table) {
        return;
    }

    // Draw with the page's own web font, not a fallback still loading.
    await document.fonts?.ready;

    const headerCells = [...table.querySelectorAll('thead th')];
    const headers = headerCells.map((cell) => cell.textContent.trim());
    const centered = headerCells.map((cell) => cell.classList.contains('qb-result-sheet-num'));
    const rows = [...table.querySelectorAll('tbody tr')].map((row) => [...row.children].map((cell) => cell.textContent.trim()));

    const headingLines = textOf(root, '[data-qb-result-sheet-heading] > *');
    const summary = textOf(root, '[data-qb-result-sheet-summary] > *');
    const baseName = root.dataset.fileName || 'results';
    const pages = Math.max(1, Math.ceil(rows.length / ROWS_PER_IMAGE));

    for (let page = 0; page < pages; page++) {
        const canvas = drawSheet({
            headingLines,
            summary,
            headers,
            centered,
            rows: rows.slice(page * ROWS_PER_IMAGE, (page + 1) * ROWS_PER_IMAGE),
            pageLabel: pages > 1 ? `${page + 1} / ${pages}` : null,
        });

        download(canvas, pages > 1 ? `${baseName}-${page + 1}.png` : `${baseName}.png`);

        // Browsers drop downloads fired in the same instant.
        await new Promise((resolve) => setTimeout(resolve, 400));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-qb-result-sheet-root]');
    const button = document.querySelector('[data-qb-download-image]');

    if (! root || ! button) {
        return;
    }

    button.disabled = ! root.querySelector('[data-qb-result-sheet]');
    button.addEventListener('click', () => downloadResultSheetImages(root));
});
