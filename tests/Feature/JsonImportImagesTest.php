<?php

use App\Enums\Difficulty;
use App\Enums\PaperSourceType;
use App\Enums\QuestionType;
use App\Filament\Support\QuestionJsonImportAction;
use App\Filament\Teacher\Resources\Questions\Pages\ListQuestions as TeacherListQuestions;
use App\Models\BillingSetting;
use App\Models\BoardQuestionPaper;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\QuestionStimulus;
use App\Models\User;
use App\Services\ImportedImages;
use App\Services\ImportedQuestions;
use App\Services\PastPaperJsonImporter;
use App\Services\QuestionJsonImporter;
use App\Services\QuestionJsonReader;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake(ImportedImages::DISK);
    $this->teacher = User::factory()->teacher()->create();
    $this->actingAs($this->teacher);
    $this->chapter = Chapter::factory()->create();
    $this->importer = app(QuestionJsonImporter::class);

    $this->png = fn (string $name, int $kilobytes = 0): UploadedFile => tap(UploadedFile::fake()->image($name, 40, 40), fn (UploadedFile $file) => $kilobytes ? $file->size($kilobytes) : null);

    /** A ZIP of these images (and whatever junk a computer adds). */
    $this->zip = function (array $entries): UploadedFile {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return new UploadedFile($path, 'figures.zip', 'application/zip', null, true);
    };

    $this->pngBytes = function (): string {
        $image = UploadedFile::fake()->image('x.png', 40, 40);

        return file_get_contents($image->getRealPath());
    };

    $this->import = fn (array $entries, array $files): array => $this->importer->import(
        json_encode($entries), $this->chapter, null, Difficulty::Easy, images: ImportedImages::fromUploads($files),
    );

    $this->problems = function (array $entries, array $files): array {
        try {
            ($this->import)($entries, $files);
        } catch (ValidationException $exception) {
            return $exception->errors()[ImportedQuestions::IMAGES_KEY] ?? $exception->errors();
        }

        return [];
    };
});

it('gives every question, option, sub-question and stimulus the image it names, kept with the question bank\'s images', function () {
    ($this->import)([
        ['question' => 'চিত্রের বর্তনীর তুল্যরোধ কত?', 'image' => 'q1.png', 'options' => ['$2$', ['text' => '', 'image' => 'q1-b.png']], 'answer' => 1],
        ['type' => 'cq', 'question' => 'একটি উদ্দীপক।', 'parts' => [
            ['text' => 'ক?', 'marks' => 1], ['text' => 'খ?', 'marks' => 2, 'image' => 'q2-part2.png'], ['text' => 'গ?', 'marks' => 3], ['text' => 'ঘ?', 'marks' => 4],
        ]],
        ['stimulus' => 'চিত্রটি দেখো।', 'image' => 's3.png', 'questions' => [
            ['question' => 'প্রথম?', 'options' => ['ক', 'খ'], 'answer' => 1],
            ['question' => 'দ্বিতীয়?', 'options' => ['ক', 'খ'], 'answer' => 2],
        ]],
    ], [($this->png)('q1.png'), ($this->png)('Q1-B.PNG'), ($this->png)('q2-part2.png'), ($this->png)('s3.png')]);

    $mcq = Question::where('question_type', QuestionType::Mcq)->whereNull('stimulus_id')->sole();
    $cq = Question::where('question_type', QuestionType::Cq)->sole();
    $stimulus = QuestionStimulus::sole();
    $paths = [$mcq->question_image, $mcq->options[1]['image'], $cq->cqParts[1]->part_image, $stimulus->image];

    expect($mcq->options[1]['option'])->toBe('')
        ->and($mcq->options[0]['image'])->toBeNull()
        ->and($cq->cqParts[0]->part_image)->toBeNull();

    foreach ($paths as $path) {
        expect($path)->toStartWith(QuestionJsonImporter::IMAGE_DIRECTORY.'/');
        Storage::disk(ImportedImages::DISK)->assertExists($path);
    }

    // The images were kept aside only while the import was looked over.
    expect(Storage::disk(ImportedImages::DISK)->allFiles(ImportedImages::TEMPORARY_DIRECTORY))->toBe([]);
});

it('finds the images inside a ZIP by their names, whatever folder they are in', function () {
    $zip = ($this->zip)(['figures/q1.png' => ($this->pngBytes)(), '__MACOSX/figures/._q1.png' => 'junk', '.DS_Store' => 'junk']);

    ($this->import)([['question' => 'চিত্রে কী?', 'image' => 'q1.png', 'options' => ['ক', 'খ'], 'answer' => 1]], [$zip]);

    Storage::disk(ImportedImages::DISK)->assertExists(Question::sole()->question_image);
});

it('adds nothing, and names each, when an image a question asks for is missing or could not be taken', function () {
    BillingSetting::current()->fill(['question_import_image_max_kb' => 100])->save();

    $problems = ($this->problems)([
        ['question' => 'প্রথম?', 'image' => 'q1.png', 'options' => ['ক', ['text' => 'খ', 'image' => 'q1-b.png']], 'answer' => 1],
        ['question' => 'দ্বিতীয়?', 'image' => 'q2.png', 'options' => ['ক', 'খ'], 'answer' => 1],
    ], [
        ($this->png)('q1.png', kilobytes: 300),
        UploadedFile::fake()->createWithContent('q2.png', 'not an image at all'),
    ]);

    expect($problems)->toBe([
        'q1.png (question 1): too large (300.0 KB; at most 100 KB)',
        'q1-b.png (option 2 of question 1): not among the images given',
        'q2.png (question 2): not a readable image',
    ])->and(Question::count())->toBe(0);
});

it('says which images were given but not taken, and adds the questions', function () {
    $images = ImportedImages::fromUploads([($this->png)('q1.png'), ($this->png)('spare.png'), UploadedFile::fake()->create('notes.txt', 1)]);

    $this->importer->import(json_encode([['question' => 'প্রথম?', 'image' => 'q1.png', 'options' => ['ক', 'খ'], 'answer' => 1]]), $this->chapter, null, Difficulty::Easy, images: $images);

    expect($images->notTaken())->toBe([
        'notes.txt' => 'not a PNG, JPG or WebP image',
        'spare.png' => 'no question uses it',
    ])->and(Question::count())->toBe(1);
});

it('refuses more images altogether than the admin allows', function () {
    BillingSetting::current()->fill(['question_import_images_max_mb' => 1])->save();

    expect(($this->problems)(
        [['question' => 'প্রথম?', 'image' => 'q1.png', 'options' => ['ক', 'খ'], 'answer' => 1]],
        [($this->png)('q1.png', kilobytes: 700), ($this->png)('q2.png', kilobytes: 700)],
    ))->toContain('The images add up to more than 1 MB — give fewer at once.');
});

it('only takes a plain file name as an image, and an option with an image may have no text', function (mixed $image) {
    $problems = [];

    try {
        ($this->import)([['question' => 'প্রথম?', 'image' => $image, 'options' => ['ক', 'খ'], 'answer' => 1]], []);
    } catch (ValidationException $exception) {
        $problems = $exception->errors()[QuestionJsonReader::ERROR_KEY];
    }

    expect($problems[0] ?? '')->toContain('"image" of the question must be the file name of an image');
})->with(['in a folder' => 'figures/q1.png', 'a web address' => 'https://example.com/q1.png', 'another kind of file' => 'q1.gif', 'not text' => 12]);

it('wants an option to have its text or an image', function () {
    try {
        ($this->import)([['question' => 'প্রথম?', 'options' => ['ক', ['text' => '']], 'answer' => 1]], []);
    } catch (ValidationException $exception) {
        expect($exception->errors()[QuestionJsonReader::ERROR_KEY][0])->toContain('option 2 needs its "text", or an "image"');

        return;
    }

    $this->fail('The option without text or image was taken.');
});

it('adds images to a past paper\'s questions the same way', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $paper = BoardQuestionPaper::factory()->admission(PaperSourceType::Medical)->create();

    app(PastPaperJsonImporter::class)->import(
        json_encode([['question' => 'চিত্রে কী?', 'image' => 'q1.png', 'options' => ['ক', 'খ'], 'answer' => 1]]),
        $paper,
        QuestionType::Mcq,
        images: ImportedImages::fromUploads([($this->png)('q1.png')]),
    );

    $path = $paper->mcqQuestions()->sole()->question_image;

    expect($path)->toStartWith(PastPaperJsonImporter::IMAGE_DIRECTORY.'/');
    Storage::disk(ImportedImages::DISK)->assertExists($path);
});

it('takes the images in the "Add from JSON" window, and lists there the ones a question misses', function () {
    Filament::setCurrentPanel(Filament::getPanel('teacher'));
    $json = json_encode([['question' => 'চিত্রে কী?', 'image' => 'q1.png', 'options' => ['ক', 'খ'], 'answer' => 1]]);

    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->callAction('importQuestionsFromJson', data: ['json' => $json, ImportedQuestions::IMAGES_KEY => [($this->png)('other.png')]])
        ->assertHasFormErrors(['json']);

    expect(Question::count())->toBe(0);

    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->mountAction('importQuestionsFromJson')
        ->fillForm(['json' => $json, ImportedQuestions::IMAGES_KEY => [($this->png)('q1.png'), ($this->png)('spare.png')]])
        ->assertMountedActionModalSee(['spare.png', 'no question uses it']);

    livewire(TeacherListQuestions::class, ['chapter' => $this->chapter->id])
        ->callAction('importQuestionsFromJson', data: ['json' => $json, ImportedQuestions::IMAGES_KEY => [($this->png)('q1.png')]])
        ->assertHasNoFormErrors();

    Storage::disk(ImportedImages::DISK)->assertExists(Question::sole()->question_image);
    expect(QuestionJsonImportAction::ACCEPTED_FIELD)->toBe('accepted_duplicates');
});
