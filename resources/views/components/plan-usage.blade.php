{{--
    The signed-in user's plans, each with what it reaches and how much of
    its allowance is used — what ShowsPlanSummary::planSummary() returns.
    Shared by the "My Subscription" page and the teacher dashboard.
--}}
@props(['summary'])

@if ($summary === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __("You don't have an active subscription yet.") }}</p>
@else
    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($summary as ['grant' => $grant, 'today' => $today, 'month' => $month, 'past_papers' => $pastPapers, 'past_papers_again_at' => $pastPapersAgainAt])
            <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="font-semibold text-gray-950 dark:text-white">{{ $grant->name }}</p>

                    @if ($grant->isFree())
                        <x-filament::badge color="gray">{{ __('Free') }}</x-filament::badge>
                    @else
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ collect([
                                $grant->sharedBy ? __('Shared by :name', ['name' => $grant->sharedBy]) : null,
                                $grant->subscription?->ends_at ? __('Until :date', ['date' => $grant->subscription->ends_at->format('M j, Y')]) : null,
                            ])->filter()->join(' · ') }}
                        </span>
                    @endif
                </div>

                <ul class="mt-2 list-inside list-disc space-y-0.5 text-sm text-gray-600 dark:text-gray-300">
                    @foreach ($grant->descriptions() as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>

                @if ($grant->dailyExamLimit !== null || $grant->monthlyExamLimit !== null)
                    <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        @if ($grant->dailyExamLimit !== null)
                            <span @class(['font-medium', 'text-danger-600 dark:text-danger-400' => $today >= $grant->dailyExamLimit])>
                                {{ __('Used today') }}: {{ $today }} / {{ $grant->dailyExamLimit }}
                            </span>
                        @endif

                        @if ($grant->monthlyExamLimit !== null)
                            <span @class(['font-medium', 'text-danger-600 dark:text-danger-400' => $month >= $grant->monthlyExamLimit])>
                                {{ __('Used this month') }}: {{ $month }} / {{ $grant->monthlyExamLimit }}
                            </span>
                        @endif
                    </div>
                @endif

                {{-- Past papers are counted over the plan's own rolling period. --}}
                @if ($grant->pastPapers->hasLimit())
                    <p @class(['mt-2 text-sm font-medium', 'text-danger-600 dark:text-danger-400' => $pastPapers >= $grant->pastPapers->limit])>
                        {{ trans_choice('Past papers in the last :count day|Past papers in the last :count days', $grant->pastPapers->limitDays) }}: {{ $pastPapers }} / {{ $grant->pastPapers->limit }}

                        @if ($pastPapers >= $grant->pastPapers->limit && $pastPapersAgainAt)
                            <span class="font-normal">· {{ __('Next from :date', ['date' => $pastPapersAgainAt->translatedFormat('j M, g:i A')]) }}</span>
                        @endif
                    </p>
                @endif
            </div>
        @endforeach
    </div>
@endif
