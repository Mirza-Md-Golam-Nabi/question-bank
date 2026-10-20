<?php

namespace App\Services;

use App\Models\BillingSetting;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * The images given with a JSON import — one by one, or in a ZIP, as an AI
 * tool hands them over — found by the file names the JSON gives them
 * ("image": "q12.png"; the case of a name does not matter). The one place
 * an import's images are opened, checked and stored:
 *
 *  - each acceptable image is kept in a temporary folder of the public disk
 *    while the import is looked over, so the preview can show it;
 *  - one that is not taken (not an image, too large) is kept by its name
 *    with the reason, to be listed;
 *  - publish() copies the ones a saved question uses to where that kind
 *    of image lives, and the temporary folder goes when the import is done
 *    (or after a day, when it never is).
 *
 * How large an image, and all of them together, may be is the Admin's
 * (BillingSetting::question_import_image_max_kb, question_import_images_max_mb).
 */
final class ImportedImages
{
    public const DISK = 'public';

    public const TEMPORARY_DIRECTORY = 'question-imports';

    /**
     * The largest single upload Livewire is let take, in KB — a technical
     * ceiling above anything the Admin can set, not a limit of its own.
     */
    public const UPLOAD_CEILING_KB = 51200;

    /**
     * @var list<string>
     */
    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    private const IMAGE_TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP];

    private const MANIFEST = 'manifest.json';

    /**
     * The names a question asked for, lower-cased.
     *
     * @var array<string, true>
     */
    private array $asked = [];

    /**
     * Temporary path => where it was published.
     *
     * @var array<string, string>
     */
    private array $published = [];

    /**
     * @param  array<string, array{name: string, path: string}>  $images  The acceptable ones, by lower-cased name.
     * @param  array<string, array{name: string, problem: string}>  $rejected  The ones not taken, by lower-cased name.
     * @param  list<string>  $problems  What is wrong with the upload as a whole.
     */
    private function __construct(
        private readonly ?string $directory = null,
        private array $images = [],
        private array $rejected = [],
        private array $problems = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }

    /**
     * @param  iterable<mixed>|null  $uploads  The upload field's files — images and ZIPs (anything else is passed over).
     */
    public static function fromUploads(?iterable $uploads): self
    {
        $files = collect($uploads ?? [])->filter(fn (mixed $file): bool => $file instanceof UploadedFile)->values();

        if ($files->isEmpty()) {
            return self::none();
        }

        // The same uploads are opened once, however often the form asks.
        $directory = self::TEMPORARY_DIRECTORY.'/'.md5(Auth::id().'|'.$files->map(fn (UploadedFile $file): string => $file->getFilename().':'.$file->getSize())->sort()->implode('|'));
        $disk = Storage::disk(self::DISK);

        if ($disk->exists("{$directory}/".self::MANIFEST)) {
            $manifest = json_decode((string) $disk->get("{$directory}/".self::MANIFEST), associative: true);

            return new self($directory, $manifest['images'], $manifest['rejected'], $manifest['problems']);
        }

        self::forgetAbandoned();

        $images = new self($directory);
        $images->take($files);

        $disk->put("{$directory}/".self::MANIFEST, json_encode([
            'images' => $images->images,
            'rejected' => $images->rejected,
            'problems' => $images->problems,
        ]));

        return $images;
    }

    /**
     * What tells these uploads apart from others ('' for none).
     */
    public function key(): string
    {
        return $this->directory ?? '';
    }

    /**
     * Where the image of this name is kept for now — null when there is
     * none to take. Asking for it marks it as used.
     */
    public function pathOf(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $this->asked[mb_strtolower($name)] = true;

        return $this->images[mb_strtolower($name)]['path'] ?? null;
    }

    /**
     * Why the image of this name can't be used: not taken, or not given.
     */
    public function problemWith(string $name): ?string
    {
        $key = mb_strtolower($name);

        return match (true) {
            isset($this->images[$key]) => null,
            isset($this->rejected[$key]) => $this->rejected[$key]['problem'],
            default => __('not among the images given'),
        };
    }

    /**
     * What is wrong with the upload as a whole (too much altogether, a ZIP
     * that won't open).
     *
     * @return list<string>
     */
    public function problems(): array
    {
        return $this->problems;
    }

    /**
     * The images given but not taken in — the ones no question asked for,
     * and the ones that could not be used — with why, by file name.
     *
     * @return array<string, string>
     */
    public function notTaken(): array
    {
        return collect($this->rejected)
            ->mapWithKeys(fn (array $image): array => [$image['name'] => $image['problem']])
            ->merge(collect($this->images)
                ->reject(fn (array $image, string $key): bool => isset($this->asked[$key]))
                ->mapWithKeys(fn (array $image): array => [$image['name'] => __('no question uses it')]))
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();
    }

    /**
     * Copies the temporary images a question (or stimulus) is about to be
     * saved with to `$directory`, and points the question at the copies —
     * its own image, its options' and its sub-questions'.
     */
    public function publish(Model $model, string $directory): void
    {
        foreach (['question_image', 'image'] as $attribute) {
            if ($model->getAttribute($attribute) !== null) {
                $model->setAttribute($attribute, $this->publishPath($model->getAttribute($attribute), $directory));
            }
        }

        if (is_array($model->getAttribute('options'))) {
            $model->setAttribute('options', array_map(
                fn (array $option): array => [...$option, 'image' => $this->publishPath($option['image'] ?? null, $directory)],
                $model->getAttribute('options'),
            ));
        }

        if ($model->relationLoaded('cqParts')) {
            $model->cqParts->each(fn (Model $part) => $part->setAttribute('part_image', $this->publishPath($part->getAttribute('part_image'), $directory)));
        }
    }

    /**
     * The temporary folder goes, once the import is done.
     */
    public function discard(): void
    {
        if ($this->directory) {
            Storage::disk(self::DISK)->deleteDirectory($this->directory);
        }
    }

    /**
     * @param  Collection<int, UploadedFile>  $files
     */
    private function take(Collection $files): void
    {
        $settings = BillingSetting::current();
        $remaining = $settings->question_import_images_max_mb * 1024 * 1024;

        foreach ($files as $file) {
            if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
                $remaining -= (int) $file->getSize();

                if ($remaining < 0) {
                    $this->problems[] = $this->tooMuch($settings);

                    return;
                }

                $this->add($file->getClientOriginalName(), (int) $file->getSize(), fn (): string => (string) file_get_contents($file->getRealPath()));

                continue;
            }

            $zip = new ZipArchive;

            if ($zip->open($file->getRealPath()) !== true) {
                $this->problems[] = __(':name could not be opened as a ZIP.', ['name' => $file->getClientOriginalName()]);

                continue;
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = $zip->statIndex($index);
                $name = basename($entry['name']);

                // Folders, and what an OS adds on its own (__MACOSX, .DS_Store).
                if (str_ends_with($entry['name'], '/') || str_contains($entry['name'], '__MACOSX') || str_starts_with($name, '.')) {
                    continue;
                }

                // Counted by what is inside, so a small ZIP can't unpack into
                // more than the Admin allows.
                $remaining -= (int) $entry['size'];

                if ($remaining < 0) {
                    $this->problems[] = $this->tooMuch($settings);
                    $zip->close();

                    return;
                }

                $this->add($name, (int) $entry['size'], fn (): string => (string) $zip->getFromIndex($index));
            }

            $zip->close();
        }
    }

    /**
     * Keeps one image, or why it is not taken. A name given twice keeps
     * the first.
     *
     * @param  Closure(): string  $contents
     */
    private function add(string $name, int $size, Closure $contents): void
    {
        $key = mb_strtolower($name);

        if (isset($this->images[$key]) || isset($this->rejected[$key])) {
            return;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $largest = BillingSetting::current()->question_import_image_max_kb * 1024;

        $problem = match (true) {
            ! in_array($extension, self::EXTENSIONS, strict: true) => __('not a PNG, JPG or WebP image'),
            $size > $largest => __('too large (:size; at most :limit)', ['size' => Number::fileSize($size, 1), 'limit' => Number::fileSize($largest)]),
            default => null,
        };

        if ($problem === null) {
            $bytes = $contents();
            $image = @getimagesizefromstring($bytes);

            if ($image === false || ! in_array($image[2], self::IMAGE_TYPES, strict: true)) {
                $problem = __('not a readable image');
            } else {
                $path = "{$this->directory}/".Str::random(20).'.'.$extension;
                Storage::disk(self::DISK)->put($path, $bytes);
                $this->images[$key] = ['name' => $name, 'path' => $path];

                return;
            }
        }

        $this->rejected[$key] = ['name' => $name, 'problem' => $problem];
    }

    private function publishPath(?string $path, string $directory): ?string
    {
        if ($path === null || ! str_starts_with($path, self::TEMPORARY_DIRECTORY.'/')) {
            return $path;
        }

        return $this->published[$path] ??= tap(
            "{$directory}/".Str::random(40).'.'.pathinfo($path, PATHINFO_EXTENSION),
            fn (string $target) => Storage::disk(self::DISK)->copy($path, $target),
        );
    }

    private function tooMuch(BillingSetting $settings): string
    {
        return __('The images add up to more than :limit MB — give fewer at once.', ['limit' => $settings->question_import_images_max_mb]);
    }

    /**
     * Imports looked over but never finished leave their folder; it goes
     * after a day.
     */
    private static function forgetAbandoned(): void
    {
        $disk = Storage::disk(self::DISK);

        foreach ($disk->directories(self::TEMPORARY_DIRECTORY) as $folder) {
            $manifest = "{$folder}/".self::MANIFEST;

            // One without its manifest is still being unpacked.
            if ($disk->exists($manifest) && $disk->lastModified($manifest) < now()->subDay()->getTimestamp()) {
                $disk->deleteDirectory($folder);
            }
        }
    }
}
