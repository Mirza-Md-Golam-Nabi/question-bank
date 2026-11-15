/*
 * Reads a photo of an OMR sheet (App\Support\OmrSheet; CLAUDE.md rule
 * 10.11) in the browser, with no library: finds the four solid corner
 * squares, maps the photo onto the sheet's own millimetres (a homography, so
 * a tilted or skewed photo reads the same as a straight scan), checks the
 * row of code cells against the exam's pages — which also tells which way
 * up the sheet is — and then measures how dark every circle is.
 *
 * Everything about where things sit comes from the layout the server gives
 * (OmrSheet::scannerLayout()); nothing here knows the sheet's geometry.
 */

const DEFAULT_MAX_SIDE = 1600;

// A pixel is ink when it is this much darker than the paper around it.
const INK = 0.45;

// How many code cells may be misread and the sheet still be recognised.
const CODE_TOLERANCE = 3;

/**
 * Draws a camera frame, photo or bitmap onto a canvas no bigger than
 * `maxSide` and returns its pixels: colour (for previews) and grey — the
 * red channel alone, in which the sheet's red circles, letters and pink
 * stripes are as light as the paper, and only the pen (black or blue) and
 * the black squares show. A sheet printed in black and white still reads:
 * its circles are then the same grey everywhere, which the threshold
 * allows for (readSheet()).
 */
export function imageFrom(source, sourceWidth, sourceHeight, maxSide = DEFAULT_MAX_SIDE) {
    const scale = Math.min(1, maxSide / Math.max(sourceWidth, sourceHeight));
    const width = Math.max(1, Math.round(sourceWidth * scale));
    const height = Math.max(1, Math.round(sourceHeight * scale));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d', { willReadFrequently: true });
    context.drawImage(source, 0, 0, width, height);

    const rgba = context.getImageData(0, 0, width, height).data;
    const gray = new Uint8ClampedArray(width * height);
    for (let i = 0, p = 0; i < gray.length; i++, p += 4) {
        gray[i] = rgba[p];
    }

    return { width, height, rgba, gray };
}

/**
 * How much darker than its surroundings each pixel is (0 = as light as the
 * paper around it, 1 = black) — so shadows and uneven light across a photo
 * don't make one part of the sheet read darker than another.
 */
function darknessOf(image) {
    const { width, height, gray } = image;
    const stride = width + 1;
    const integral = new Float64Array(stride * (height + 1));

    for (let y = 0; y < height; y++) {
        let rowSum = 0;
        for (let x = 0; x < width; x++) {
            rowSum += gray[y * width + x];
            integral[(y + 1) * stride + x + 1] = integral[y * stride + x + 1] + rowSum;
        }
    }

    // Wide enough that a corner square never fills it (it would then look
    // like its own background), narrow enough to follow a shadow.
    const radius = Math.max(16, Math.round(Math.max(width, height) / 8));
    const darkness = new Float32Array(width * height);

    for (let y = 0; y < height; y++) {
        const top = Math.max(0, y - radius);
        const bottom = Math.min(height, y + radius + 1);
        for (let x = 0; x < width; x++) {
            const left = Math.max(0, x - radius);
            const right = Math.min(width, x + radius + 1);
            const sum = integral[bottom * stride + right] - integral[top * stride + right]
                - integral[bottom * stride + left] + integral[top * stride + left];
            const mean = sum / ((bottom - top) * (right - left));
            const value = 1 - gray[y * width + x] / Math.max(mean, 1);
            darkness[y * width + x] = value < 0 ? 0 : value > 1 ? 1 : value;
        }
    }

    return darkness;
}

/**
 * The solid, roughly square blobs of ink — the corner squares are among
 * them. Measured by their moments, so a square turned at any angle (or
 * foreshortened a little) still counts, while letters, lines and rings
 * don't.
 */
function solidBlobs(darkness, width, height) {
    const labels = new Int32Array(width * height);
    const stack = new Int32Array(width * height);
    const minSide = Math.max(5, Math.round(Math.max(width, height) / 160));
    const blobs = [];
    let label = 0;

    for (let start = 0; start < labels.length; start++) {
        if (labels[start] !== 0 || darkness[start] < INK) {
            continue;
        }

        label++;
        let top = 0;
        stack[top++] = start;
        labels[start] = label;
        let count = 0;
        let sumX = 0;
        let sumY = 0;
        let sumXX = 0;
        let sumYY = 0;
        let sumXY = 0;
        let minX = width;
        let maxX = 0;
        let minY = height;
        let maxY = 0;

        while (top > 0) {
            const index = stack[--top];
            const x = index % width;
            const y = (index - x) / width;
            count++;
            sumX += x;
            sumY += y;
            sumXX += x * x;
            sumYY += y * y;
            sumXY += x * y;
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;

            if (x > 0 && labels[index - 1] === 0 && darkness[index - 1] >= INK) {
                labels[index - 1] = label;
                stack[top++] = index - 1;
            }
            if (x < width - 1 && labels[index + 1] === 0 && darkness[index + 1] >= INK) {
                labels[index + 1] = label;
                stack[top++] = index + 1;
            }
            if (y > 0 && labels[index - width] === 0 && darkness[index - width] >= INK) {
                labels[index - width] = label;
                stack[top++] = index - width;
            }
            if (y < height - 1 && labels[index + width] === 0 && darkness[index + width] >= INK) {
                labels[index + width] = label;
                stack[top++] = index + width;
            }
        }

        if (maxX - minX + 1 < minSide || maxY - minY + 1 < minSide) {
            continue;
        }

        const cx = sumX / count;
        const cy = sumY / count;
        const varX = sumXX / count - cx * cx;
        const varY = sumYY / count - cy * cy;
        const covXY = sumXY / count - cx * cy;
        const det = varX * varY - covXY * covXY;
        if (det <= 0) {
            continue;
        }

        // A filled square of any turn has both variances side²/12: its area
        // is then 12·√det. A ring, a letter or a line has far more spread.
        const solidity = count / (12 * Math.sqrt(det));
        const spread = Math.sqrt((varX - varY) ** 2 + 4 * covXY * covXY);
        const elongation = (varX + varY + spread) / Math.max(varX + varY - spread, 1e-6);

        if (solidity > 0.8 && solidity < 1.2 && elongation < 3) {
            blobs.push({ x: cx, y: cy, area: count });
        }
    }

    return blobs.sort((a, b) => b.area - a.area).slice(0, 12);
}

/**
 * The homography taking the sheet's millimetres (`from`) to the photo's
 * pixels (`to`), from four pairs of points.
 */
function homography(from, to) {
    const rows = [];
    for (let i = 0; i < 4; i++) {
        const [x, y] = from[i];
        const [u, v] = to[i];
        rows.push([x, y, 1, 0, 0, 0, -u * x, -u * y, u]);
        rows.push([0, 0, 0, x, y, 1, -v * x, -v * y, v]);
    }

    // Gaussian elimination with partial pivoting on the 8 × 9 system.
    for (let column = 0; column < 8; column++) {
        let pivot = column;
        for (let row = column + 1; row < 8; row++) {
            if (Math.abs(rows[row][column]) > Math.abs(rows[pivot][column])) {
                pivot = row;
            }
        }
        if (Math.abs(rows[pivot][column]) < 1e-12) {
            return null;
        }
        [rows[column], rows[pivot]] = [rows[pivot], rows[column]];

        for (let row = 0; row < 8; row++) {
            if (row === column) {
                continue;
            }
            const factor = rows[row][column] / rows[column][column];
            for (let k = column; k < 9; k++) {
                rows[row][k] -= factor * rows[column][k];
            }
        }
    }

    return rows.map((row, i) => row[8] / row[i]);
}

export function project(h, x, y) {
    const w = h[6] * x + h[7] * y + 1;

    return [(h[0] * x + h[1] * y + h[2]) / w, (h[3] * x + h[4] * y + h[5]) / w];
}

/**
 * How dark a round (or square) patch of the sheet is on average —
 * `radius` mm around (x, y), sampled every quarter millimetre.
 */
function patchDarkness(darkness, width, height, h, x, y, radius, square = false) {
    let total = 0;
    let samples = 0;

    for (let dy = -radius; dy <= radius + 1e-9; dy += 0.25) {
        for (let dx = -radius; dx <= radius + 1e-9; dx += 0.25) {
            if (!square && dx * dx + dy * dy > radius * radius) {
                continue;
            }
            const [u, v] = project(h, x + dx, y + dy);
            const px = Math.round(u);
            const py = Math.round(v);
            if (px < 0 || py < 0 || px >= width || py >= height) {
                return null;
            }
            total += darkness[py * width + px];
            samples++;
        }
    }

    return samples === 0 ? 0 : total / samples;
}

function codeOf(darkness, width, height, h, layout) {
    let bits = '';
    for (const [x, y] of layout.codeCells) {
        const value = patchDarkness(darkness, width, height, h, x, y, layout.codeCell * 0.3, true);
        if (value === null) {
            return null;
        }
        bits += value > 0.35 ? '1' : '0';
    }

    return bits;
}

function hamming(a, b) {
    let difference = 0;
    for (let i = 0; i < a.length; i++) {
        if (a[i] !== b[i]) {
            difference++;
        }
    }

    return difference;
}

/**
 * The corner squares among the blobs, in the sheet's order (top-left,
 * top-right, bottom-right, bottom-left), and the page whose code the cells
 * between them spell — trying each set of four blobs, biggest first, and
 * each of the four ways up.
 */
function locate(darkness, image, layout) {
    const { width, height } = image;
    const blobs = solidBlobs(darkness, width, height);
    const minQuadArea = width * height * 0.04;

    const sets = [];
    for (let a = 0; a < blobs.length; a++) {
        for (let b = a + 1; b < blobs.length; b++) {
            for (let c = b + 1; c < blobs.length; c++) {
                for (let d = c + 1; d < blobs.length; d++) {
                    const four = [blobs[a], blobs[b], blobs[c], blobs[d]];
                    const areas = four.map((blob) => blob.area);
                    if (Math.max(...areas) > 3 * Math.min(...areas)) {
                        continue;
                    }
                    sets.push({ four, size: areas.reduce((sum, area) => sum + area, 0) });
                }
            }
        }
    }
    sets.sort((first, second) => second.size - first.size);

    for (const { four } of sets) {
        const cx = four.reduce((sum, blob) => sum + blob.x, 0) / 4;
        const cy = four.reduce((sum, blob) => sum + blob.y, 0) / 4;
        const corners = [...four].sort((p, q) => Math.atan2(p.y - cy, p.x - cx) - Math.atan2(q.y - cy, q.x - cx));

        // Shoelace: a sheet covers a fair part of the photo.
        let area = 0;
        for (let i = 0; i < 4; i++) {
            const p = corners[i];
            const q = corners[(i + 1) % 4];
            area += p.x * q.y - q.x * p.y;
        }
        if (Math.abs(area) / 2 < minQuadArea) {
            continue;
        }

        for (let turn = 0; turn < 4; turn++) {
            const points = [0, 1, 2, 3].map((i) => [corners[(i + turn) % 4].x, corners[(i + turn) % 4].y]);
            const h = homography(layout.markers, points);
            if (!h) {
                continue;
            }

            const code = codeOf(darkness, width, height, h, layout);
            if (code === null) {
                continue;
            }

            let best = null;
            for (const page of layout.pages) {
                const distance = hamming(code, page.code);
                if (distance <= CODE_TOLERANCE && (!best || distance < best.distance)) {
                    best = { page, distance };
                }
            }

            if (best) {
                return { h, page: best.page, corners: points };
            }
        }
    }

    return null;
}

/**
 * Sorts a row of circles' darkness into what was meant: one filled, none,
 * more than one, or something in between the teacher has to look at.
 */
function judge(scores, filledAt, faintAt) {
    const filled = [];
    const faint = [];
    scores.forEach((score, index) => {
        if (score >= filledAt) {
            filled.push(index);
        } else if (score >= faintAt) {
            faint.push(index);
        }
    });

    if (filled.length > 1) {
        return { state: 'several', mark: 'several' };
    }

    if (filled.length === 1) {
        const strongestFaint = Math.max(0, ...faint.map((index) => scores[index]));
        return faint.length === 0 || scores[filled[0]] - strongestFaint >= 0.25
            ? { state: 'ok', mark: filled[0] }
            : { state: 'unclear', mark: filled[0] };
    }

    if (faint.length > 0) {
        const strongest = faint.reduce((best, index) => (scores[index] > scores[best] ? index : best), faint[0]);
        return { state: 'unclear', mark: strongest };
    }

    return { state: 'blank', mark: null };
}

/**
 * Reads one sheet off a photo: null when no sheet of this exam is in it.
 *
 * @returns {{
 *   page: number, h: number[], corners: number[][],
 *   roll: { value: string, unclear: boolean },
 *   answers: { key: string, number: number, page: number, mark: number|'several'|null, state: 'ok'|'blank'|'several'|'unclear', scores: number[] }[],
 * } | null}
 */
export function readSheet(image, layout) {
    const darkness = darknessOf(image);
    const found = locate(darkness, image, layout);

    if (!found) {
        return null;
    }

    const { width, height } = image;
    const { h, page } = found;
    const inner = (diameter) => diameter * 0.3;
    const darknessAt = ([x, y], diameter) => patchDarkness(darkness, width, height, h, x, y, inner(diameter)) ?? 0;

    const questionScores = page.questions.map((question) => question.options.map((option) => darknessAt(option, page.bubble)));

    // What an empty circle reads as on this sheet (its light letter, the
    // paper, the printer): most circles are empty, so a low percentile.
    const all = questionScores.flat().sort((a, b) => a - b);
    const empty = all.length ? all[Math.floor(all.length * 0.3)] : 0;
    const filledAt = Math.max(0.3, empty + 0.22);
    const faintAt = Math.max(0.15, empty + 0.1);

    const answers = page.questions.map((question, index) => ({
        key: question.key,
        number: question.number,
        page: page.index,
        scores: questionScores[index],
        ...judge(questionScores[index], filledAt, faintAt),
    }));

    let roll = '';
    let rollUnclear = false;
    for (const column of layout.roll) {
        const verdict = judge(column.map((circle) => darknessAt(circle, layout.rollBubble)), filledAt, faintAt);
        if (verdict.state === 'blank') {
            continue;
        }
        if (verdict.state !== 'ok') {
            rollUnclear = true;
        }
        roll += verdict.mark === 'several' ? '?' : String(verdict.mark);
    }

    return { page: page.index, h, corners: found.corners, roll: { value: roll, unclear: rollUnclear }, answers };
}

/**
 * The sheet straightened out — its frame at `pixelsPerMm` — for the
 * teacher to check what was read against.
 */
export function straightened(image, h, layout, pixelsPerMm = 3) {
    const width = Math.round(layout.width * pixelsPerMm);
    const height = Math.round(layout.height * pixelsPerMm);
    const output = new ImageData(width, height);

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            const [u, v] = project(h, x / pixelsPerMm, y / pixelsPerMm);
            const px = Math.round(u);
            const py = Math.round(v);
            const target = (y * width + x) * 4;
            if (px >= 0 && py >= 0 && px < image.width && py < image.height) {
                const source = (py * image.width + px) * 4;
                output.data[target] = image.rgba[source];
                output.data[target + 1] = image.rgba[source + 1];
                output.data[target + 2] = image.rgba[source + 2];
            }
            output.data[target + 3] = 255;
        }
    }

    return output;
}
