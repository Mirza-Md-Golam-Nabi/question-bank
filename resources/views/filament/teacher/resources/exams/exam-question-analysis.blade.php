@php
    $stats = $this->stats;
    $participants = $stats->first()['participants'] ?? 0;
@endphp

<x-filament-panels::page>
    @if ($participants === 0)
        <x-filament::empty-state
            :heading="__('Nobody has submitted this exam yet')"
            :description="__('Results appear here as soon as a student submits.')"
            icon="heroicon-o-users"
        />
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('Out of :count students. The question most often answered wrongly comes first.', ['count' => $participants]) }}
        </p>

        <div class="space-y-4">
            @foreach ($stats as $index => $stat)
                <x-filament::section wire:key="question-stat-{{ $stat['question']->id }}">
                    <div class="flex gap-3">
                        <span class="shrink-0 font-semibold text-gray-950 dark:text-white">{{ $index + 1 }}.</span>

                        <div class="min-w-0 flex-1">
                            @include('filament.support.questions.question-body', ['question' => $stat['question'], 'showAnswers' => true])

                            {{-- One bar split by outcome, so the share of wrong answers reads at a glance. --}}
                            <div class="qb-answer-bar" role="img" aria-label="{{ __('Correct') }}: {{ $stat['correct'] }}, {{ __('Incorrect') }}: {{ $stat['wrong'] }}, {{ __('Not answered') }}: {{ $stat['unanswered'] }}">
                                <span class="qb-answer-bar-part qb-answer-bar-part--wrong" style="flex-grow: {{ $stat['wrong'] }}"></span>
                                <span class="qb-answer-bar-part qb-answer-bar-part--correct" style="flex-grow: {{ $stat['correct'] }}"></span>
                                <span class="qb-answer-bar-part qb-answer-bar-part--unanswered" style="flex-grow: {{ $stat['unanswered'] }}"></span>
                            </div>

                            <dl class="mt-2 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                <div class="flex items-center gap-1.5">
                                    <span class="qb-answer-dot qb-answer-bar-part--wrong"></span>
                                    <dt class="text-gray-600 dark:text-gray-300">{{ __('Incorrect') }}:</dt>
                                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $stat['wrong'] }}</dd>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="qb-answer-dot qb-answer-bar-part--correct"></span>
                                    <dt class="text-gray-600 dark:text-gray-300">{{ __('Correct') }}:</dt>
                                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $stat['correct'] }}</dd>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="qb-answer-dot qb-answer-bar-part--unanswered"></span>
                                    <dt class="text-gray-600 dark:text-gray-300">{{ __('Not answered') }}:</dt>
                                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $stat['unanswered'] }}</dd>
                                </div>
                            </dl>

                            @if ($stat['wrong'] > 0 || $stat['unanswered'] > 0)
                                <div class="mt-3 flex flex-wrap gap-x-6 gap-y-2">
                                    @if ($stat['wrong'] > 0)
                                        <x-filament::link
                                            tag="button"
                                            icon="heroicon-m-users"
                                            wire:click="mountAction('wrongStudents', { question: {{ $stat['question']->id }} })"
                                        >
                                            {{ __('See who answered wrongly') }}
                                        </x-filament::link>
                                    @endif

                                    @if ($stat['unanswered'] > 0)
                                        <x-filament::link
                                            tag="button"
                                            color="gray"
                                            icon="heroicon-m-users"
                                            wire:click="mountAction('unansweredStudents', { question: {{ $stat['question']->id }} })"
                                        >
                                            {{ __('See who did not answer') }}
                                        </x-filament::link>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
