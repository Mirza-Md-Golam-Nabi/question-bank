@php
    /**
     * The running summary of the teacher's selection — a sticky right-hand
     * panel on wide screens, a bar pinned to the bottom of the screen on a
     * phone (tap it to open the chapter breakdown). Rendered entirely from
     * the browser-side selection, so it never waits on the server.
     */
    use App\Filament\Teacher\Pages\SelectQuestions;

    $isReview = $this->step === SelectQuestions::STEP_REVIEW;
@endphp

<aside
    class="fixed inset-x-0 bottom-0 z-20 max-h-[75vh] overflow-y-auto border-t border-gray-200 bg-white p-4 shadow-lg lg:sticky lg:inset-auto lg:top-20 lg:z-auto lg:max-h-none lg:self-start lg:rounded-xl lg:border-0 lg:shadow-sm lg:ring-1 lg:ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:lg:ring-white/10"
>
    <div class="flex items-center justify-between gap-3">
        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 text-start lg:pointer-events-none" x-on:click="panelOpen = ! panelOpen">
            <span class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Selected questions') }}</span>
            <x-filament::badge>
                <span x-text="count()"></span>
            </x-filament::badge>
            <x-filament::icon icon="heroicon-m-chevron-up" class="size-5 text-gray-400 transition lg:hidden" x-bind:class="panelOpen && 'rotate-180'" />
        </button>

        <div class="lg:hidden">
            @if ($isReview)
                <x-filament::button size="sm" x-on:click="saveExam()" x-bind:disabled="! canReview()">
                    {{ __('Save as exam') }}
                </x-filament::button>
            @else
                <x-filament::button size="sm" x-on:click="review()" x-bind:disabled="! canReview()">
                    {{ __('Final view') }}
                </x-filament::button>
            @endif
        </div>
    </div>

    <div class="mt-4 hidden space-y-4 lg:block" x-bind:class="panelOpen && '!block'">
        <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-gray-600 dark:text-gray-300">{{ __('MCQ') }}</dt>
                <dd class="font-semibold" x-bind:class="isOverTarget('mcq') ? 'text-danger-600' : 'text-gray-950 dark:text-white'">
                    <span x-text="countType('mcq')"></span> / <span x-text="target('mcq')"></span>
                </dd>
            </div>

            <div class="flex items-center justify-between gap-3" x-show="allowsCq()">
                <dt class="text-gray-600 dark:text-gray-300">{{ __('CQ') }}</dt>
                <dd class="font-semibold" x-bind:class="isOverTarget('cq') ? 'text-danger-600' : 'text-gray-950 dark:text-white'">
                    <span x-text="countType('cq')"></span> / <span x-text="target('cq')"></span>
                </dd>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-gray-200 pt-2 dark:border-white/10">
                <dt class="text-gray-600 dark:text-gray-300">{{ __('Total marks') }}</dt>
                <dd class="font-semibold text-gray-950 dark:text-white" x-text="totalMarks()"></dd>
            </div>
        </dl>

        <p
            x-show="isOverTarget('mcq') || isOverTarget('cq')"
            x-cloak
            class="rounded-lg bg-danger-50 px-3 py-2 text-xs text-danger-700 dark:bg-danger-500/10 dark:text-danger-400"
        >
            {{ __('You have selected more questions than the number you asked for. Remove some, or raise the number.') }}
        </p>

        <div>
            <h3 class="mb-2 text-sm font-semibold text-gray-950 dark:text-white">{{ __('By chapter') }}</h3>

            <p x-show="count() === 0" class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('Nothing selected yet.') }}
            </p>

            <ul class="space-y-2">
                <template x-for="chapterId in chapterIds()" x-bind:key="chapterId">
                    <li class="flex items-start justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-gray-950 dark:text-white" x-text="chapters[chapterId]"></p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <span x-show="chapterCount(chapterId, 'mcq') > 0">
                                    {{ __('MCQ') }}: <span x-text="chapterCount(chapterId, 'mcq')"></span>
                                </span>
                                <span x-show="chapterCount(chapterId, 'cq') > 0">
                                    {{ __('CQ') }}: <span x-text="chapterCount(chapterId, 'cq')"></span>
                                </span>
                            </p>
                        </div>

                        <button
                            type="button"
                            class="shrink-0 rounded p-1 text-gray-400 hover:text-danger-600"
                            title="{{ __('Remove the questions of this chapter') }}"
                            aria-label="{{ __('Remove the questions of this chapter') }}"
                            x-on:click="removeChapter(chapterId)"
                        >
                            <x-filament::icon icon="heroicon-m-x-mark" class="size-4" />
                        </button>
                    </li>
                </template>
            </ul>
        </div>

        <div class="flex flex-col gap-2">
            @if ($isReview)
                <x-filament::button x-on:click="saveExam()" x-bind:disabled="! canReview()" icon="heroicon-o-check">
                    {{ __('Save as exam') }}
                </x-filament::button>

                <x-filament::button color="gray" wire:click="backToSelection" icon="heroicon-o-arrow-left">
                    {{ __('Select more questions') }}
                </x-filament::button>
            @else
                <x-filament::button x-on:click="review()" x-bind:disabled="! canReview()" icon="heroicon-o-eye">
                    {{ __('Final view') }}
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    x-show="count() > 0"
                    x-on:click="pending = { kind: 'clear' }"
                    icon="heroicon-o-trash"
                >
                    {{ __('Clear selection') }}
                </x-filament::button>
            @endif
        </div>
    </div>
</aside>
