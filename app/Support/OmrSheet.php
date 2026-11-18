<?php

namespace App\Support;

use App\Enums\Locale;
use App\Enums\OmrIdentity;
use App\Enums\QuestionType;
use App\Filament\Support\QuestionBlocks;
use App\Models\AttemptAnswer;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The machine-readable OMR answer sheet of an exam's printed MCQ (CLAUDE.md
 * rule 10.11): where every mark on it sits, in millimetres from the top-left
 * corner of the sheet's frame. The one place that geometry lives — the
 * printed sheet (teacher.partials.exam-omr-sheet) draws it, the scanner
 * (resources/js/omr-reader.js) reads by it (scannerLayout()).
 *
 * The frame fills an A4 page (WIDTH × HEIGHT); on A5 the very same frame is
 * printed smaller (A5_SCALE), so nothing on it moves relative to the rest.
 * Four solid squares at its corners let a photo of it — tilted, skewed,
 * shrunk — be mapped back onto these coordinates, and a row of black/white
 * cells between the top two says which exam and which page it is
 * (pageCode()), so a sheet of another exam, or one printed before the
 * exam's questions changed, is never read as this one.
 *
 * Below the heading the questions stand on the left, always in two columns
 * (the first half on the left, the rest on the right; numbered as on the
 * paper, QuestionBlocks; more than fit go on to the next sheet) — as many
 * rows as the teacher chose (questionCount(), `exams.omr_questions`; rows
 * after the paper's MCQ are left blank and never read, and with fewer rows
 * than MCQ the last ones are not on the sheet) — and on the right the
 * number grids (grids() — only the one the exam tells students apart by is
 * read: the roll or the registration number, `exams.omr_identity`), then
 * boxes to write in (boxes()). An OMR check's "MCQ" are its answer key's
 * (Exam::answerKeyQuestions()). Circles and their letters are printed
 * red: the reader looks at the red channel only, where red ink is as light
 * as the paper and only the student's pen shows.
 */
final class OmrSheet
{
    public const WIDTH = 190.0;

    public const HEIGHT = 272.0;

    /** How much smaller the frame is printed on A5 than on A4. */
    public const A5_SCALE = 0.72;

    /** The most rows a teacher can ask the sheet for (questionCount()). */
    public const MAX_QUESTIONS = 200;

    public const MARKER_SIZE = 7.0;

    public const CODE_BITS = 32;

    public const CODE_CELL = 3.2;

    public const CODE_PITCH = 4.4;

    /** The heading (institution, exam, the sheet's name, the warnings). */
    public const HEADING_TOP = 8.5;

    public const HEADING_HEIGHT = 41.0;

    /** The question columns: their area, and the head row of every column. */
    public const QUESTIONS_LEFT = 5.0;

    public const QUESTIONS_WIDTH = 106.0;

    public const BODY_TOP = 52.0;

    public const COLUMN_HEAD = 8.0;

    public const ROWS_BOTTOM = 252.0;

    /** The answer list is always in two columns, MAX_ROWS at most each — so a sheet holds twice that. */
    public const COLUMNS = 2;

    public const MAX_ROWS = 30;

    public const MAX_ROW_PITCH = 9.0;

    public const NUMBER_WIDTH = 8.0;

    public const MIN_BUBBLE = 4.6;

    public const MAX_BUBBLE = 5.8;

    /** The white between two circles of a question. */
    public const BUBBLE_GAP = 1.4;

    /** The widest a question's circles stand apart — a column never stretches further. */
    public const MAX_OPTION_PITCH = 9.0;

    public const COLUMN_PADDING = 2.0;

    /** The least white between the two question columns: what is left of the area is shared out around them (pages()). */
    public const COLUMN_GAP = 2.0;

    /** The right-hand side — number grids, then the boxes — just past the question area. */
    public const GRIDS_LEFT = 113.0;

    public const GRIDS_WIDTH = 73.0;

    /** The number grids on the right: a title row, a row of boxes to write the digits in, then the circles. */
    public const GRID_TITLE = 6.0;

    public const GRID_WRITE_ROW = 6.0;

    public const GRID_ROW_PITCH = 4.7;

    public const GRID_BUBBLE = 3.9;

    public const ROLL_DIGITS = 6;

    /**
     * What is kept as the answer to a question with more than one circle
     * filled in — never one of its options, so it is marked wrong.
     */
    public const SEVERAL_FILLED = '*';

    /**
     * @param  Collection<int, Model>  $mcqQuestions  The printed paper's MCQs, in paper order.
     */
    public function __construct(private readonly Exam $exam, private readonly Collection $mcqQuestions) {}

    public static function for(Exam $exam): self
    {
        return new self($exam, $exam->printedItems()->where('question_type', QuestionType::Mcq)->values());
    }

    /**
     * Every MCQ with its number on the paper, its answer key
     * (AttemptAnswer::keyFor()) and how many options it has.
     *
     * @return Collection<int, array{number: int, key: string, question: Model, options: int}>
     */
    public function entries(): Collection
    {
        return QuestionBlocks::blocks($this->mcqQuestions)
            ->flatMap(fn (array $block): array => $block['questions'])
            ->map(fn (array $entry): array => [
                'number' => $entry['number'],
                'key' => AttemptAnswer::keyFor($entry['question']),
                'question' => $entry['question'],
                'options' => count($entry['question']->options ?? []),
            ])
            ->values();
    }

    /**
     * How many questions the sheet has rows for: the teacher's choice
     * (`exams.omr_questions`, 1 to MAX_QUESTIONS), or else the paper's MCQ.
     * Fewer than the paper's MCQ leaves the last ones off the sheet.
     */
    public function questionCount(): int
    {
        $chosen = (int) $this->exam->omr_questions;
        $mcqCount = $this->entries()->count();

        return $chosen > 0 ? min(max(self::MAX_QUESTIONS, $mcqCount), $chosen) : $mcqCount;
    }

    /**
     * Saves how many questions the exam's sheet has rows for — 1 to
     * MAX_QUESTIONS (or the paper's MCQ, if more); just the MCQ is kept as
     * null, so the sheet follows the paper if its questions change. A sheet
     * printed before reads no more (its code changes, pageCode()).
     *
     * @throws ValidationException
     */
    public static function saveQuestionCount(Exam $exam, mixed $count): void
    {
        $mcqCount = self::for($exam)->entries()->count();

        $most = max($mcqCount, self::MAX_QUESTIONS);

        $validated = Validator::make(['questions' => $count], [
            'questions' => ['required', 'integer', 'between:1,'.$most],
        ], [
            'questions.*' => __('Give a number from :min to :max.', [
                'min' => Locale::current()->writeDigits('1'),
                'max' => Locale::current()->writeDigits((string) $most),
            ]),
        ])->validate();

        $exam->forceFill(['omr_questions' => (int) $validated['questions'] !== $mcqCount ? (int) $validated['questions'] : null])->save();
    }

    /**
     * Saves which number tells the exam's students apart on its sheets
     * (identity()) — only until a sheet is saved, since the papers already
     * saved go by the other one. A sheet printed before reads no more (its
     * code changes, pageCode()).
     *
     * @throws ValidationException
     */
    public static function saveIdentity(Exam $exam, mixed $identity): void
    {
        $validated = Validator::make(['identity' => $identity], [
            'identity' => ['required', Rule::enum(OmrIdentity::class)],
        ])->validate();

        if ($exam->attempts()->where('is_omr', true)->exists()) {
            throw ValidationException::withMessages([
                'identity' => __('Sheets have already been saved by the :number, so it can no longer be changed.', ['number' => mb_strtolower((string) $exam->omr_identity->getLabel())]),
            ]);
        }

        $exam->forceFill(['omr_identity' => OmrIdentity::from($validated['identity'])])->save();
    }

    /**
     * The sheet's rows: the MCQ (entries()) up to questionCount() — the
     * rest are not on the sheet, so never read — then blank rows numbered
     * on up to questionCount() — no key, never read — with as many circles
     * as the paper's most options.
     *
     * @return Collection<int, array{number: int, key: string|null, question: Model|null, options: int}>
     */
    private function rows(int $optionCount): Collection
    {
        $entries = $this->entries()->take($this->questionCount());
        $last = (int) $entries->max('number');
        $blank = $this->questionCount() - $entries->count();

        return $entries->concat(array_map(
            fn (int $extra): array => ['number' => $last + $extra, 'key' => null, 'question' => null, 'options' => $optionCount],
            $blank > 0 ? range(1, $blank) : [],
        ))->values();
    }

    /**
     * The sheets, each with its questions where they sit — as many sheets
     * as the questions need. Always two columns: the first half of a
     * sheet's questions on the left, the rest on the right (21 → 11 and
     * 10). A short paper gets fewer, roomier rows and bigger circles; a long
     * one up to MAX_ROWS a column. The two columns sit like flex items with
     * "justify-content: space-around": each as wide as its share (its
     * circles at most MAX_OPTION_PITCH apart), the white left over the same
     * on both sides of each — so twice that between them.
     *
     * @return list<array{index: int, code: string, bubble: float, rowPitch: float, columns: list<array{x: float, width: float, rows: int}>, questions: list<array{number: int, key: string|null, question: Model|null, x: float, y: float, options: list<array{0: float, 1: float}>}>}>
     */
    public function pages(): array
    {
        $optionCount = max(4, (int) $this->entries()->max('options'));

        return $this->rows($optionCount)->chunk(self::COLUMNS * self::MAX_ROWS)->values()->map(function (Collection $onPage, int $index) use ($optionCount): array {
            $count = $onPage->count();
            $rows = (int) ceil($count / self::COLUMNS);

            $rowPitch = min(self::MAX_ROW_PITCH, (self::ROWS_BOTTOM - self::BODY_TOP - self::COLUMN_HEAD) / $rows);
            $roomPerOption = ((self::QUESTIONS_WIDTH + self::COLUMN_GAP) / self::COLUMNS - self::COLUMN_GAP - self::NUMBER_WIDTH - self::COLUMN_PADDING) / $optionCount;
            $bubble = round(max(self::MIN_BUBBLE, min(self::MAX_BUBBLE, $rowPitch * 0.75, $roomPerOption - self::BUBBLE_GAP)), 2);
            $optionPitch = max($bubble + self::BUBBLE_GAP, min(self::MAX_OPTION_PITCH, $roomPerOption));
            $columnWidth = self::NUMBER_WIDTH + $optionCount * $optionPitch + self::COLUMN_PADDING;
            $around = max(0.0, (self::QUESTIONS_WIDTH - self::COLUMNS * $columnWidth) / (2 * self::COLUMNS));
            $columnX = fn (int $column): float => self::QUESTIONS_LEFT + $around + $column * ($columnWidth + 2 * $around);

            $questions = $onPage->values()->map(function (array $entry, int $position) use ($rows, $rowPitch, $optionPitch, $columnX): array {
                $x = $columnX(intdiv($position, $rows));
                $y = self::BODY_TOP + self::COLUMN_HEAD + ($position % $rows + 0.5) * $rowPitch;

                return [
                    ...$entry,
                    'x' => round($x, 2),
                    'y' => round($y, 2),
                    'options' => array_map(
                        fn (int $option): array => [round($x + self::NUMBER_WIDTH + self::COLUMN_PADDING / 2 + ($option + 0.5) * $optionPitch, 2), round($y, 2)],
                        range(0, $entry['options'] - 1),
                    ),
                ];
            })->all();

            return [
                'index' => $index,
                'code' => $this->pageCode($index),
                'bubble' => $bubble,
                'rowPitch' => round($rowPitch, 3),
                // A sheet of a single question has nothing in its right column.
                'columns' => array_map(fn (int $column): array => [
                    'x' => round($columnX($column), 2),
                    'width' => round($columnWidth, 2),
                    'rows' => min($rows, $count - $column * $rows),
                ], range(0, (int) ceil($count / $rows) - 1)),
                'questions' => $questions,
            ];
        })->all();
    }

    /**
     * The sheet's 32-bit code as "0"s and "1"s, the first cell first: a
     * checksum of the exam, the page, every question on the paper with its
     * number of options, the sheet's number of rows and which number grid
     * is read — so it changes whenever the paper or the sheet does.
     */
    public function pageCode(int $page): string
    {
        $paper = $this->entries()->map(fn (array $entry): string => "{$entry['key']}:{$entry['options']}")->join(',');

        return str_pad(decbin(crc32("omr|5|{$this->exam->id}|{$page}|{$paper}|{$this->questionCount()}|{$this->identity()->value}")), self::CODE_BITS, '0', STR_PAD_LEFT);
    }

    /**
     * The corner squares' centres: top-left, top-right, bottom-right,
     * bottom-left.
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function markers(): array
    {
        $near = self::MARKER_SIZE / 2;

        return [[$near, $near], [self::WIDTH - $near, $near], [self::WIDTH - $near, self::HEIGHT - $near], [$near, self::HEIGHT - $near]];
    }

    /**
     * The code cells' centres, between the top two corner squares.
     *
     * @return list<array{0: float, 1: float}>
     */
    public static function codeCells(): array
    {
        $first = self::WIDTH / 2 - (self::CODE_BITS - 1) * self::CODE_PITCH / 2;

        return array_map(
            fn (int $bit): array => [round($first + $bit * self::CODE_PITCH, 2), self::MARKER_SIZE / 2],
            range(0, self::CODE_BITS - 1),
        );
    }

    /**
     * The number grids on the right, each a column of circles per digit:
     * roll number, set code and subject code side by side, the registration
     * number below. Only one of the roll and the registration number is read
     * — the one the exam tells students apart by (identityColumns()); the
     * others are there for the look of a familiar sheet. `labels` =
     * 'digits' (0–9) or 'letters' (the option letters, as many as `rows`).
     *
     * @return array<string, array{title: string, x: float, y: float, columns: int, pitch: float, rows: int, labels: string}>
     */
    public static function grids(): array
    {
        return [
            'roll' => ['title' => 'Roll number', 'x' => self::GRIDS_LEFT, 'y' => self::BODY_TOP, 'columns' => self::ROLL_DIGITS, 'pitch' => 6.0, 'rows' => 10, 'labels' => 'digits'],
            'set' => ['title' => 'Set code', 'x' => 153.5, 'y' => self::BODY_TOP, 'columns' => 1, 'pitch' => 7.0, 'rows' => 4, 'labels' => 'letters'],
            'subject' => ['title' => 'Subject code', 'x' => 165.0, 'y' => self::BODY_TOP, 'columns' => 3, 'pitch' => 7.0, 'rows' => 10, 'labels' => 'digits'],
            'registration' => ['title' => 'Registration number', 'x' => self::GRIDS_LEFT, 'y' => 115.0, 'columns' => 10, 'pitch' => 7.3, 'rows' => 10, 'labels' => 'digits'],
        ];
    }

    /**
     * A grid's circles: a column per digit, each with its circles' centres.
     *
     * @param  array{x: float, y: float, columns: int, pitch: float, rows: int}  $grid
     * @return list<list<array{0: float, 1: float}>>
     */
    public static function gridColumns(array $grid): array
    {
        $firstRow = $grid['y'] + self::GRID_TITLE + self::GRID_WRITE_ROW + 0.6 + self::GRID_ROW_PITCH / 2;

        return array_map(
            fn (int $column): array => array_map(
                fn (int $row): array => [round($grid['x'] + ($column + 0.5) * $grid['pitch'], 2), round($firstRow + $row * self::GRID_ROW_PITCH, 2)],
                range(0, $grid['rows'] - 1),
            ),
            range(0, $grid['columns'] - 1),
        );
    }

    /**
     * How tall a grid is, from its title to below its last circle.
     *
     * @param  array{rows: int}  $grid
     */
    public static function gridHeight(array $grid): float
    {
        return self::GRID_TITLE + self::GRID_WRITE_ROW + $grid['rows'] * self::GRID_ROW_PITCH + 1.2;
    }

    /**
     * Which number tells this exam's students apart on the sheet.
     */
    public function identity(): OmrIdentity
    {
        return $this->exam->omr_identity ?? OmrIdentity::Roll;
    }

    /**
     * That number's circles — what the scanner reads it from.
     *
     * @return list<list<array{0: float, 1: float}>>
     */
    public function identityColumns(): array
    {
        return self::gridColumns(self::grids()[$this->identity()->grid()]);
    }

    /**
     * The boxes written in by hand: on the right under the registration
     * number, each with its label below; the marks box at the bottom left,
     * its label before it.
     *
     * @return list<array{label: string, x: float, y: float, width: float, height: float, labelBefore: bool}>
     */
    public static function boxes(): array
    {
        return [
            ['label' => 'Signature of the room invigilator', 'x' => self::GRIDS_LEFT, 'y' => 178.0, 'width' => self::GRIDS_WIDTH, 'height' => 13.0, 'labelBefore' => false],
            ['label' => 'Date', 'x' => self::GRIDS_LEFT, 'y' => 199.0, 'width' => self::GRIDS_WIDTH, 'height' => 11.0, 'labelBefore' => false],
            ['label' => 'Name of the student', 'x' => self::GRIDS_LEFT, 'y' => 219.0, 'width' => self::GRIDS_WIDTH, 'height' => 13.0, 'labelBefore' => false],
            ['label' => 'Marks obtained', 'x' => 34.0, 'y' => 254.5, 'width' => 52.0, 'height' => 9.5, 'labelBefore' => true],
        ];
    }

    /**
     * Everything the scanner needs to read a photo of one of these sheets.
     *
     * @return array<string, mixed>
     */
    public function scannerLayout(): array
    {
        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'markerSize' => self::MARKER_SIZE,
            'markers' => self::markers(),
            'codeCells' => self::codeCells(),
            'codeCell' => self::CODE_CELL,
            // The number the students are told apart by — the roll's grid or
            // the registration number's (identity()); "roll" to the scanner.
            'rollBubble' => self::GRID_BUBBLE,
            'roll' => $this->identityColumns(),
            // The blank rows after the paper's MCQ are not read — nor a sheet of only those.
            'pages' => collect($this->pages())
                ->map(fn (array $page): array => [
                    'index' => $page['index'],
                    'code' => $page['code'],
                    'bubble' => $page['bubble'],
                    'questions' => collect($page['questions'])
                        ->whereNotNull('key')
                        ->map(fn (array $question): array => [
                            'number' => $question['number'],
                            'key' => $question['key'],
                            'options' => $question['options'],
                        ])
                        ->values()
                        ->all(),
                ])
                ->filter(fn (array $page): bool => $page['questions'] !== [])
                ->values()
                ->all(),
        ];
    }

    /**
     * The answers to keep for what was read off a sheet: each question's
     * filled-in option (0 = the first) as that option's answer value, or
     * SEVERAL_FILLED; a question left blank, or not on the paper or the
     * sheet (questionCount()), is left out.
     *
     * @param  array<string, int|string|null>  $marks  question key => option index, "several" or null
     * @return array<string, string>
     */
    public function answersFrom(array $marks): array
    {
        $answers = [];

        foreach ($this->entries()->take($this->questionCount()) as $entry) {
            $mark = $marks[$entry['key']] ?? null;
            $options = array_values($entry['question']->options ?? []);

            if ($mark === 'several') {
                $answers[$entry['key']] = self::SEVERAL_FILLED;
            } elseif (is_int($mark) && isset($options[$mark])) {
                $answers[$entry['key']] = $entry['question']::answerValueOf($options[$mark]);
            }
        }

        return $answers;
    }
}
