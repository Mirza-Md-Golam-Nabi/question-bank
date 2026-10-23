<?php

namespace App\Services;

use App\Models\SchoolProfile;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * A School's classes and their sections, as its school management software
 * lists them — what the entry form of its exams offers. Read from the
 * software at most every few minutes (a whole school opens the same link at
 * once), and the last list it gave is kept, so the form still works while
 * the software is briefly unreachable.
 */
class SchoolRoster
{
    /**
     * How long a fetched list is used before asking again — a technical
     * limit, not a business number.
     */
    private const FRESH_SECONDS = 600;

    public function __construct(private readonly SchoolInstallationClient $client) {}

    /**
     * @return list<array{id: int, name: string, name_bn: ?string, sections: list<array{id: int, name: string}>}>
     *
     * @throws RuntimeException when the list was never fetched and the software can't give it now
     */
    public function classes(SchoolProfile $profile): array
    {
        return Cache::remember($this->freshKey($profile), self::FRESH_SECONDS, function () use ($profile): array {
            try {
                $classes = array_values((array) ($this->client->get($profile, '/api/question-bank/classes')['classes'] ?? []));
            } catch (RuntimeException $exception) {
                return Cache::get($this->lastKey($profile)) ?? throw $exception;
            }

            Cache::forever($this->lastKey($profile), $classes);

            return $classes;
        });
    }

    /**
     * The class and section a student picked, checked against the list —
     * with their names, to keep on the attempt.
     * Null when either isn't the school's, or a class with sections has
     * none picked.
     *
     * @return array{class: array{id: int, name: string}, section: array{id: int, name: string}|null}|null
     */
    public function pick(SchoolProfile $profile, mixed $classId, mixed $sectionId): ?array
    {
        $class = collect($this->classes($profile))->firstWhere('id', (int) $classId);

        if (! $class) {
            return null;
        }

        $sections = collect($class['sections'] ?? []);
        $section = $sections->firstWhere('id', (int) $sectionId);

        if ($sections->isNotEmpty() && ! $section) {
            return null;
        }

        return [
            // The school's own name for the class, the same whatever language
            // the student filled the form in.
            'class' => ['id' => (int) $class['id'], 'name' => (string) $class['name']],
            'section' => $section ? ['id' => (int) $section['id'], 'name' => (string) $section['name']] : null,
        ];
    }

    /**
     * A class's name in the visitor's language.
     *
     * @param  array{name: string, name_bn?: ?string}  $class
     */
    public static function className(array $class): string
    {
        return app()->getLocale() === 'bn' && filled($class['name_bn'] ?? null) ? $class['name_bn'] : $class['name'];
    }

    private function freshKey(SchoolProfile $profile): string
    {
        return "school-roster:{$profile->id}";
    }

    private function lastKey(SchoolProfile $profile): string
    {
        return "school-roster-last:{$profile->id}";
    }
}
