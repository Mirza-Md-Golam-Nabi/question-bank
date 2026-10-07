<x-filament-panels::page>
    @php
        $gradients = [
            'from-fuchsia-400 to-purple-600',
            'from-rose-400 to-pink-600',
            'from-lime-400 to-green-600',
            'from-amber-400 to-orange-500',
            'from-sky-400 to-blue-600',
            'from-emerald-400 to-teal-600',
        ];
    @endphp

    <div>
        <a
            href="{{ $this->getResource()::getUrl('subjects', ['class' => $this->class->id]) }}"
            wire:navigate
            class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-gray-500 hover:text-primary-600 dark:text-gray-400 lg:mb-4 lg:text-sm"
        >
            <x-filament::icon icon="heroicon-m-arrow-left" class="h-3.5 w-3.5 lg:h-4 lg:w-4" />
            {{ __('Back to :name', ['name' => $this->class->name]) }}
        </a>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5 lg:gap-4">
            @forelse ($this->chapters() as $index => $chapter)
                @php $gradient = $gradients[$index % count($gradients)]; @endphp

                <div class="group relative flex flex-col rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl dark:border-white/10 dark:bg-gray-900">
                    <a
                        href="{{ $this->getResource()::getUrl('list', ['chapter' => $chapter->id]) }}"
                        wire:navigate
                        class="absolute inset-0 z-0"
                        aria-label="{{ $chapter->name }}"
                    ></a>

                    <div class="pointer-events-none relative z-10">
                        <div class="h-12 rounded-t-2xl bg-gradient-to-br {{ $gradient }} lg:h-16"></div>

                        <div class="flex flex-col gap-2 p-3 lg:gap-3 lg:p-4">
                            <div class="-mt-9 flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br {{ $gradient }} text-white shadow-lg ring-4 ring-white dark:ring-gray-900 lg:-mt-12 lg:h-14 lg:w-14 lg:rounded-2xl">
                                <x-filament::icon icon="heroicon-o-bookmark-square" class="h-5 w-5 lg:h-7 lg:w-7" />
                            </div>

                            <div>
                                <h3 class="truncate text-xs font-semibold text-gray-950 dark:text-white lg:text-sm">{{ $chapter->name }}</h3>
                                <p class="mt-1 inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 lg:text-sm">
                                    <x-filament::icon icon="heroicon-o-question-mark-circle" class="h-3.5 w-3.5 shrink-0 lg:h-4 lg:w-4" />
                                    {{ trans_choice(':count Question|:count Questions', $chapter->questions_count) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if ($this->canManageContent())
                        <div class="absolute right-1.5 top-1.5 z-20 lg:right-2 lg:top-2">
                            <x-filament::dropdown placement="bottom-end">
                                <x-slot name="trigger">
                                    <button
                                        type="button"
                                        class="flex h-7 w-7 items-center justify-center rounded-full bg-white/80 text-gray-600 opacity-100 backdrop-blur transition hover:bg-white md:opacity-0 md:group-hover:opacity-100 dark:bg-gray-900/80 dark:text-gray-300 dark:hover:bg-gray-900 lg:h-8 lg:w-8"
                                    >
                                        <x-filament::icon icon="heroicon-m-ellipsis-vertical" class="h-4 w-4" />
                                    </button>
                                </x-slot>

                                <x-filament::dropdown.list>
                                    <x-filament::dropdown.list.item
                                        icon="heroicon-o-tag"
                                        href="{{ $this->getResource()::getUrl('topics', ['class' => $this->class->id, 'classSubject' => $this->classSubject->id, 'chapter' => $chapter->id]) }}"
                                        tag="a"
                                        wire:navigate
                                    >
                                        {{ __('Topics') }}
                                    </x-filament::dropdown.list.item>
                                    <x-filament::dropdown.list.item
                                        icon="heroicon-o-pencil-square"
                                        wire:click="mountAction('editChapter', { chapter: {{ $chapter->id }} })"
                                    >
                                        {{ __('Edit') }}
                                    </x-filament::dropdown.list.item>
                                    <x-filament::dropdown.list.item
                                        icon="heroicon-o-trash"
                                        color="danger"
                                        wire:click="mountAction('deleteChapter', { chapter: {{ $chapter->id }} })"
                                    >
                                        {{ __('Delete') }}
                                    </x-filament::dropdown.list.item>
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>
                        </div>
                    @endif
                </div>
            @empty
                <div class="col-span-full">
                    <x-filament::section>
                        <p class="text-center text-xs text-gray-500 dark:text-gray-400 lg:text-sm">
                            @if ($this->canManageContent())
                                {{ __('No chapters yet. Add one to get started.') }}
                            @else
                                {{ __('No chapters yet.') }}
                            @endif
                        </p>
                    </x-filament::section>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
