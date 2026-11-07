{{-- The student's mistakes: the practice form, then the chosen section's mistakes with their answers (PractiseMistakes). --}}
<x-filament-panels::page>
    @if ($this->hasClass() && ! $this->hasMistakes())
        <x-filament::empty-state
            :heading="__('No mistakes to practise')"
            :description="__('Questions you get wrong in any exam (by a teacher, your own practice or a past paper) will appear here, with the correct answer.')"
            icon="heroicon-o-check-badge"
        />
    @else
        @include('filament.student.pages.partials.self-practice-form')

        @if ($mistakes = $this->mistakes)
            <div class="space-y-4">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ __('My mistakes (:count)', ['count' => \App\Enums\Locale::current()->writeDigits((string) $mistakes->total())]) }}
                </h2>

                <div class="qb-result-list">
                    @foreach ($mistakes as $mistake)
                        @continue (! $mistake['question'])

                        <div class="qb-result-item space-y-3" wire:key="mistake-{{ $mistakes->firstItem() + $loop->index }}">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ collect([
                                    $mistake['exam_title'],
                                    $mistake['last_wrong_at'] ? \App\Enums\Locale::current()->writeDigits(\Illuminate\Support\Carbon::parse($mistake['last_wrong_at'])->translatedFormat('j M Y')) : null,
                                    __('Wrong :count times', ['count' => \App\Enums\Locale::current()->writeDigits((string) $mistake['wrong_count'])]),
                                ])->filter()->join(' · ') }}
                            </p>

                            @if ($stimulus = \App\Filament\Support\QuestionBlocks::stimulusOf($mistake['question']))
                                <x-question-stimulus :stimulus="$stimulus" />
                            @endif

                            <x-exam-result-question
                                :question="$mistake['question']"
                                :answer="new \App\Models\AttemptAnswer(['student_answer' => $mistake['last_answer'], 'is_correct' => false])"
                                :number="$mistakes->firstItem() + $loop->index"
                                :verdict="false"
                            />

                            @if (blank($mistake['last_answer']))
                                <p class="text-sm font-medium text-danger-600 dark:text-danger-400">{{ __('You left it unanswered.') }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <x-filament::pagination :paginator="$mistakes" />
            </div>
        @endif
    @endif
</x-filament-panels::page>
