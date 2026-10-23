@props([
    'classes',
    'idPrefix',
    'withName' => true,
    'refill' => false,
])

@php
    /**
     * The fields that identify a School's student — class, section (only for
     * a class that has them), roll, and name — asked to start a School's
     * exam, and (without the name) to look the result up. The classes and
     * sections are the school management software's (SchoolRoster).
     *
     * @var list<array{id: int, name: string, name_bn: ?string, sections: list<array{id: int, name: string}>}> $classes
     * @var string $idPrefix  Keeps the field ids unique when both forms are on one page.
     * @var bool $withName
     * @var bool $refill      Put back what was typed after a failed submit of this form.
     */
    use App\Services\SchoolRoster;

    $oldClass = $refill ? old('class_id') : null;
    $oldSection = $refill ? old('section_id') : null;
    $sectionsOf = collect($classes)->mapWithKeys(fn (array $class): array => [$class['id'] => $class['sections'] ?? []]);
@endphp

<div data-school-student-fields data-sections='@json($sectionsOf)' class="space-y-4">
    <div>
        <label class="qb-label" for="{{ $idPrefix }}-class">{{ __('Class') }}</label>
        <select id="{{ $idPrefix }}-class" name="class_id" required class="qb-input" data-class-select>
            <option value="">{{ __('Choose your class') }}</option>
            @foreach ($classes as $class)
                <option value="{{ $class['id'] }}" @selected((string) $oldClass === (string) $class['id'])>{{ SchoolRoster::className($class) }}</option>
            @endforeach
        </select>
    </div>

    <div data-section-field @class(['hidden' => blank($oldClass) || ($sectionsOf[$oldClass] ?? []) === []])>
        <label class="qb-label" for="{{ $idPrefix }}-section">{{ __('Section') }}</label>
        <select id="{{ $idPrefix }}-section" name="section_id" class="qb-input" data-section-select data-selected="{{ $oldSection }}">
            <option value="">{{ __('Choose your section') }}</option>
        </select>
    </div>

    <div>
        <label class="qb-label" for="{{ $idPrefix }}-roll">{{ __('Roll') }}</label>
        <input id="{{ $idPrefix }}-roll" type="text" inputmode="numeric" name="roll" value="{{ $refill ? old('roll') : '' }}" required maxlength="20" class="qb-input">
    </div>

    @if ($withName)
        <div>
            <label class="qb-label" for="{{ $idPrefix }}-name">{{ __('Your name') }}</label>
            <input id="{{ $idPrefix }}-name" type="text" name="name" value="{{ $refill ? old('name') : '' }}" required maxlength="255" class="qb-input">
        </div>
    @endif
</div>

@once
    <script>
        // A class's sections, offered (and required) only when it has them.
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-school-student-fields]').forEach((fields) => {
                const sectionsOf = JSON.parse(fields.dataset.sections || '{}');
                const classSelect = fields.querySelector('[data-class-select]');
                const sectionField = fields.querySelector('[data-section-field]');
                const sectionSelect = fields.querySelector('[data-section-select]');
                const placeholder = sectionSelect.options[0].cloneNode(true);

                const fill = () => {
                    const sections = sectionsOf[classSelect.value] || [];
                    const selected = sectionSelect.dataset.selected;

                    sectionSelect.replaceChildren(placeholder.cloneNode(true));
                    sections.forEach((section) => {
                        const option = new Option(section.name, section.id, false, String(section.id) === String(selected));
                        sectionSelect.add(option);
                    });

                    sectionField.classList.toggle('hidden', sections.length === 0);
                    sectionSelect.required = sections.length > 0;
                    sectionSelect.dataset.selected = '';
                };

                classSelect.addEventListener('change', fill);
                fill();
            });
        });
    </script>
@endonce
