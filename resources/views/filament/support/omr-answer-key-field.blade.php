@php
    /**
     * Typing in an OMR check's answer key (CLAUDE.md rule 10.12;
     * OmrCheckForm): a row per question with a circle per option — a click
     * picks the right one — or the whole key typed at once ("কখগঘ", "ABCD"
     * or "1234", spaces and commas ignored). The picks live in the browser
     * (Alpine) until the form is saved; OmrCheckService checks them again.
     *
     * @var int $questionCount
     * @var int $optionCount
     */
    use App\Enums\Locale;
    use App\Filament\Support\QuestionDisplay;

    $letters = array_map(fn (int $option): string => QuestionDisplay::letter($option), range(0, $optionCount - 1));

    // Whatever a key may be typed as — Bangla or English letters, digits from 1 — to the option it means.
    $typed = [];
    foreach (range(0, $optionCount - 1) as $option) {
        $typed[QuestionDisplay::letter($option, 'bn')] = $option;
        $typed[QuestionDisplay::letter($option, 'en')] = $option;
        $typed[mb_strtolower(QuestionDisplay::letter($option, 'en'))] = $option;
        $typed[(string) ($option + 1)] = $option;
        $typed[Locale::Bn->writeDigits((string) ($option + 1))] = $option;
    }
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        wire:key="omr-answer-key-{{ $questionCount }}-{{ $optionCount }}"
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            typed: '',
            typedError: false,
            pick(question, option) {
                const answers = Array.isArray(this.state) ? [...this.state] : Object.values(this.state ?? {});
                answers[question] = option;
                this.state = Array.from({ length: Math.max(answers.length, question + 1) }, (_, index) => answers[index] ?? null);
            },
            picked(question, option) {
                return Array.isArray(this.state) && this.state[question] === option;
            },
            missing(question) {
                return ! Array.isArray(this.state) || this.state[question] === null || this.state[question] === undefined;
            },
            digits(value) {
                return @js(app()->getLocale() === 'bn') ? String(value).replace(/[0-9]/g, (digit) => '০১২৩৪৫৬৭৮৯'[digit]) : String(value);
            },
            get done() {
                return Array.from({ length: {{ $questionCount }} }, (_, index) => index).filter((index) => ! this.missing(index)).length;
            },
            fill() {
                const map = @js($typed);
                const marks = [...this.typed].filter((character) => ! /[\s,;.\-\/|]/.test(character));
                this.typedError = marks.some((character) => ! (character in map));
                if (this.typedError) {
                    return;
                }
                this.state = marks.slice(0, {{ $questionCount }}).map((character) => map[character]);
            },
        }"
        class="flex flex-col gap-3"
    >
        @if ($questionCount === 0)
            <p class="qb-answer-key-note">{{ __('Give the number of questions first.') }}</p>
        @else
            <div class="flex flex-wrap items-end gap-2">
                <label class="flex min-w-0 flex-1 flex-col gap-1 text-sm">
                    <span class="font-medium">{{ __('Type the whole key at once (optional)') }}</span>
                    <input type="text" x-model="typed" @keydown.enter.prevent="fill()"
                           class="fi-input block w-full rounded-lg border border-gray-300 px-3 py-1.5 dark:border-white/10 dark:bg-white/5"
                           placeholder="{{ implode('', array_slice(array_merge($letters, $letters, $letters), 0, 8)) }}…">
                </label>
                <x-filament::button type="button" color="gray" size="sm" x-on:click="fill()">{{ __('Fill in') }}</x-filament::button>
            </div>
            <p class="qb-answer-key-note" x-show="typedError" x-cloak style="color: #dc2626">{{ __('Use only the option letters (or their numbers from 1).') }}</p>

            <p class="qb-answer-key-note">
                {{ __('Answers given') }}: <span x-text="digits(done)"></span> / {{ Locale::current()->writeDigits((string) $questionCount) }}
            </p>

            <div class="qb-answer-key">
                @for ($question = 0; $question < $questionCount; $question++)
                    <div class="qb-answer-key-row" :class="{ 'qb-answer-key-row--missing': missing({{ $question }}) }">
                        <span class="qb-answer-key-number">{{ Locale::current()->writeDigits((string) ($question + 1)) }}</span>
                        @foreach ($letters as $option => $letter)
                            <button type="button" class="qb-answer-key-bubble"
                                    :class="{ 'qb-answer-key-bubble--picked': picked({{ $question }}, {{ $option }}) }"
                                    :aria-pressed="picked({{ $question }}, {{ $option }}) ? 'true' : 'false'"
                                    aria-label="{{ __('Question :number', ['number' => $question + 1]) }}: {{ $letter }}"
                                    x-on:click="pick({{ $question }}, {{ $option }})">{{ $letter }}</button>
                        @endforeach
                    </div>
                @endfor
            </div>
        @endif
    </div>
</x-dynamic-component>
