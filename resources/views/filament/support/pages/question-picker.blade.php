@php
    /**
     * The question picker of every panel (QuestionPickerPage): the Teacher's
     * "Select questions" and the Student's own practice exam. What differs
     * between them comes from the page — which questions, whether CQ and
     * printing exist, and what the final button does.
     */
    use App\Enums\ExamDeliveryMode;
    use App\Filament\Support\Pages\QuestionPickerPage;

    $questions = $this->step === QuestionPickerPage::STEP_SELECT ? $this->questions : null;
    $deliveryMode = $this->deliveryMode();
    $blockedNotice = $this->blockedNotice();
@endphp

<x-filament-panels::page>
    @if ($blockedNotice)
        <x-filament::section icon="heroicon-o-lock-closed" icon-color="warning" :heading="$blockedNotice['heading']">
            <p class="text-sm">{{ $blockedNotice['message'] }}</p>

            <x-filament::button tag="a" :href="$blockedNotice['url']" class="mt-3">
                {{ $blockedNotice['button'] }}
            </x-filament::button>
        </x-filament::section>
    @endif

    {{--
        The selection lives in the browser only (CLAUDE.md rule 10): this
        Alpine component keeps the ticked question ids in localStorage and
        does all the counting itself, so ticking a question never calls the
        server. Livewire is only asked for a page of questions, the review
        of the ids held here, and the final step.
    --}}
    <div
        x-data="qbQuestionSelection({
            storageKey: @js($this->selectionStorageKey()),
            initial: @js($this->initialSelection()),
        })"
        x-on:qb-selection-validated.window="keepOnly($event.detail.ids)"
        x-on:qb-selection-saved.window="clearSelection()"
        class="qb-selection pb-28 lg:pb-0"
    >
        @if ($this->step === QuestionPickerPage::STEP_SELECT)
            <div class="space-y-6">
                <x-filament::section>
                    {{ $this->form }}

                    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div x-show="showsType('mcq')" x-cloak>
                            <label class="fi-fo-field-label-content mb-2 block text-sm font-medium text-gray-950 dark:text-white" for="qb-target-mcq">
                                {{ __('How many MCQ questions?') }}
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    id="qb-target-mcq"
                                    type="number"
                                    min="1"
                                    inputmode="numeric"
                                    x-model.number="targets.mcq"
                                    x-on:input="save()"
                                />
                            </x-filament::input.wrapper>
                        </div>

                        <div x-show="showsType('cq')" x-cloak>
                            <label class="fi-fo-field-label-content mb-2 block text-sm font-medium text-gray-950 dark:text-white" for="qb-target-cq">
                                {{ __('How many CQ questions?') }}
                            </label>
                            <x-filament::input.wrapper>
                                <x-filament::input
                                    id="qb-target-cq"
                                    type="number"
                                    min="1"
                                    inputmode="numeric"
                                    x-model.number="targets.cq"
                                    x-on:input="save()"
                                />
                            </x-filament::input.wrapper>
                        </div>
                    </div>
                </x-filament::section>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="space-y-4 lg:col-span-2">
                        @if (! $questions)
                            <x-filament::empty-state
                                :heading="$this->showsClassFilter() ? __('Choose a class, subject and chapter') : __('Choose a subject and chapter')"
                                :description="__('The approved questions of that chapter will be listed here.')"
                                icon="heroicon-o-funnel"
                            />
                        @elseif ($questions->isEmpty())
                            <x-filament::empty-state
                                :heading="__('No approved questions found')"
                                :description="__('Try another chapter, topic or question type.')"
                                icon="heroicon-o-inbox"
                            />
                        @else
                            @php
                                // What the browser keeps of each question on this page when
                                // it is ticked — one at a time, or all of them at once.
                                $pageItems = $questions->getCollection()->map(fn ($question): array => [
                                    'id' => $question->id,
                                    'chapter' => $question->chapter_id,
                                    'chapterName' => $this->chapter->name,
                                    'type' => $question->question_type->value,
                                    'marks' => (float) $question->marks,
                                ])->values();
                            @endphp

                            <div id="qb-question-list" class="flex scroll-mt-24 flex-wrap items-center justify-between gap-3">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ trans_choice(':count Question|:count Questions', $questions->total()) }}
                                    &middot; {{ $this->chapter->name }}
                                </p>

                                <div class="flex flex-wrap items-center gap-2">
                                    {{-- The list by ID, the oldest or the latest first. --}}
                                    {{ $this->oldestAction }}
                                    {{ $this->latestAction }}

                                    {{-- Picks from every page of the list (one request), up to
                                         the number of questions still asked for. --}}
                                    <x-filament::button
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-m-sparkles"
                                        x-bind:disabled="picking || ! canPickAtRandom()"
                                        x-on:click="pickAtRandom()"
                                    >
                                        {{ __('Select at random') }}
                                    </x-filament::button>

                                    {{-- Works on the questions of this page only, in the browser —
                                         and stops at the number of questions asked for. --}}
                                    <div x-data="{ page: @js($pageItems) }" wire:key="select-all-{{ $questions->currentPage() }}-{{ $pageItems->pluck('id')->implode('-') }}">
                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-m-check-circle"
                                            x-show="! allSelected(page)"
                                            x-bind:disabled="! canSelectAny(page)"
                                            x-on:click="selectAll(page)"
                                        >
                                            {{ __('Select all on this page') }}
                                        </x-filament::button>

                                        <x-filament::button
                                            size="sm"
                                            color="gray"
                                            icon="heroicon-m-x-circle"
                                            x-cloak
                                            x-show="allSelected(page)"
                                            x-on:click="unselectAll(page)"
                                        >
                                            {{ __('Unselect all on this page') }}
                                        </x-filament::button>
                                    </div>
                                </div>
                            </div>

                            <p
                                x-show="pickedNone"
                                x-cloak
                                x-transition.opacity
                                class="rounded-lg bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-400"
                            >
                                {{ __('No more questions here to pick at random.') }}
                            </p>

                            <p
                                x-show="needsTarget()"
                                x-cloak
                                class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400"
                            >
                                {{ __('Enter how many questions you want above — then you can start selecting.') }}
                            </p>

                            @foreach ($questions as $index => $question)
                                @php
                                    $item = $pageItems[$index];
                                    $type = $item['type'];
                                @endphp

                                <label
                                    wire:key="question-{{ $question->id }}"
                                    class="flex cursor-pointer gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 transition dark:bg-gray-900 dark:ring-white/10"
                                    x-bind:class="{
                                        'ring-2 !ring-primary-500': has({{ $question->id }}),
                                        'cursor-not-allowed opacity-60': ! canSelect({{ $question->id }}, @js($type)),
                                    }"
                                >
                                    <input
                                        type="checkbox"
                                        class="fi-checkbox-input mt-1 size-5 shrink-0 rounded border-gray-300 text-primary-600"
                                        x-bind:checked="has({{ $question->id }})"
                                        x-bind:disabled="! canSelect({{ $question->id }}, @js($type))"
                                        x-on:change="toggle(@js($item), $event)"
                                    >

                                    @include('filament.support.pages.question-picker.question-card', ['question' => $question, 'showAnswers' => $this->showsAnswersWhilePicking()])
                                </label>
                            @endforeach

                            {{-- Another page opens at the top of its questions, not
                                 where the last one was scrolled to. --}}
                            <div x-on:click="$event.target.closest('button, a') && document.getElementById('qb-question-list')?.scrollIntoView({ block: 'start' })">
                                <x-filament::pagination :paginator="$questions" />
                            </div>
                        @endif
                    </div>

                    @include('filament.support.pages.question-picker.selection-summary')
                </div>
            </div>
        @elseif ($this->step === QuestionPickerPage::STEP_REVIEW)
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <x-filament::section>
                        <x-slot name="heading">
                            {{ $this->classSubject?->label() }}
                        </x-slot>

                        <x-slot name="description">
                            {{ $deliveryMode->getLabel() }}
                            @if ($deliveryMode === ExamDeliveryMode::Both)
                                &mdash; {{ __('CQ questions appear on the printed paper only.') }}
                            @endif
                        </x-slot>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->reviewIntro() }}
                        </p>
                    </x-filament::section>

                    @foreach ($this->reviewChapters as $chapterQuestions)
                        @php
                            $chapter = $chapterQuestions->first()->chapter;
                        @endphp

                        <section
                            wire:key="review-chapter-{{ $chapter->id }}"
                            x-show="chapterCount({{ $chapter->id }}) > 0"
                            class="space-y-3"
                        >
                            <h3 class="flex items-center gap-2 text-base font-semibold text-gray-950 dark:text-white">
                                {{ $chapter->name }}
                                <x-filament::badge color="gray">
                                    <span x-text="chapterCount({{ $chapter->id }})"></span>
                                </x-filament::badge>
                            </h3>

                            @foreach ($chapterQuestions as $question)
                                <div
                                    wire:key="review-question-{{ $question->id }}"
                                    x-show="has({{ $question->id }})"
                                    class="flex gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                                >
                                    @include('filament.support.pages.question-picker.question-card', ['question' => $question, 'showAnswers' => $this->showsAnswersWhilePicking()])

                                    <x-filament::icon-button
                                        icon="heroicon-o-trash"
                                        color="danger"
                                        :label="__('Remove')"
                                        x-on:click="remove({{ $question->id }})"
                                    />
                                </div>
                            @endforeach
                        </section>
                    @endforeach
                </div>

                @include('filament.support.pages.question-picker.selection-summary')
            </div>
        @elseif ($this->finishedView())
            @include($this->finishedView())
        @endif

        {{-- Asks before a destructive change discards part of the selection. --}}
        <div
            x-show="pending"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4"
            x-on:keydown.escape.window="pending && cancelPending()"
            role="dialog"
            aria-modal="true"
        >
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900" x-on:click.outside="cancelPending()">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                    <span x-show="pending?.kind === 'subject'">{{ __('Change subject?') }}</span>
                    <span x-show="pending?.kind === 'mode'">{{ __('Switch to an online exam?') }}</span>
                    <span x-show="pending?.kind === 'clear'">{{ __('Clear selection?') }}</span>
                </h2>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    <span x-show="pending?.kind === 'subject'">
                        {!! __('An exam can only have questions from one class and subject. Changing it will delete all :count questions you have selected so far.', ['count' => '<strong x-text="count()"></strong>']) !!}
                    </span>
                    <span x-show="pending?.kind === 'mode'">
                        {!! __('CQ questions cannot be taken online. Switching will remove the :count CQ questions you have selected.', ['count' => '<strong x-text="countType(`cq`)"></strong>']) !!}
                    </span>
                    <span x-show="pending?.kind === 'clear'">
                        {!! __('This will delete all :count questions you have selected so far.', ['count' => '<strong x-text="count()"></strong>']) !!}
                    </span>
                </p>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <x-filament::button color="gray" x-on:click="cancelPending()">
                        {{ __('No, keep my selection') }}
                    </x-filament::button>

                    <x-filament::button color="danger" x-on:click="confirmPending()">
                        {{ __('Yes, delete them') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    </div>

    @script
        <script>
            Alpine.data('qbQuestionSelection', ({ storageKey, initial }) => ({
                // { [questionId]: { c: chapterId, t: 'mcq' | 'cq', m: marks } }
                items: {},
                // { [chapterId]: chapterName } — only for chapters with a selection
                chapters: {},
                targets: { mcq: null, cq: null },
                classSubjectId: null,
                mode: null,
                // A change waiting for the teacher's permission: { kind, value }
                pending: null,
                panelOpen: false,
                // "Select at random" is waiting for the server / found nothing left.
                picking: false,
                pickedNone: false,

                init() {
                    this.load();

                    // Editing an exam: with nothing kept from an earlier visit,
                    // start from the questions the exam already has.
                    if (initial && this.count() === 0) {
                        this.items = initial.items;
                        this.chapters = initial.chapters;
                        this.targets = { ...initial.targets };
                        this.classSubjectId = initial.classSubjectId;
                        this.mode = initial.mode;
                        this.save();
                    } else if (initial && ! this.matches(initial.items)) {
                        // Editing, with unsaved changes kept from an earlier
                        // visit: the page opened on the exam's saved questions,
                        // so ask for the final view of what is selected now.
                        this.$wire.review(this.ids());
                    }

                    if (this.count() > 0 && this.classSubjectId) {
                        // Bring the server-side filters back to the subject
                        // this selection belongs to.
                        this.$wire.restoreFilters({ class_subject_id: this.classSubjectId, exam_mode: this.mode });
                    } else {
                        this.items = {};
                        this.chapters = {};
                        this.classSubjectId = this.$wire.data.class_subject_id ?? null;
                        this.mode = this.$wire.data.exam_mode ?? null;
                        this.save();
                    }

                    this.$watch('$wire.data.class_subject_id', (value) => this.subjectChanged(value));
                    this.$watch('$wire.data.exam_mode', (value) => this.modeChanged(value));
                },

                load() {
                    try {
                        const stored = JSON.parse(localStorage.getItem(storageKey) ?? 'null');

                        if (stored && typeof stored === 'object') {
                            this.items = stored.items ?? {};
                            this.chapters = stored.chapters ?? {};
                            this.targets = { mcq: stored.targets?.mcq ?? null, cq: stored.targets?.cq ?? null };
                            this.classSubjectId = stored.classSubjectId ?? null;
                            this.mode = stored.mode ?? null;
                        }
                    } catch {
                        // Unreadable or blocked storage: start with an empty selection.
                    }
                },

                save() {
                    try {
                        localStorage.setItem(storageKey, JSON.stringify({
                            items: this.items,
                            chapters: this.chapters,
                            targets: this.targets,
                            classSubjectId: this.classSubjectId,
                            mode: this.mode,
                        }));
                    } catch {
                        // Private mode / full storage: the selection still works for this visit.
                    }
                },

                ids() {
                    return Object.keys(this.items).map(Number);
                },

                // Whether the selection is exactly the given set of questions.
                matches(items) {
                    const ids = Object.keys(items ?? {});

                    return ids.length === this.count() && ids.every((id) => this.has(id));
                },

                // Read the entry itself rather than asking hasOwnProperty():
                // Alpine only re-renders what was read through a tracked
                // property access, and a hasOwnProperty() call isn't one —
                // so a tick-box would stay ticked after its question was
                // removed from the summary panel.
                has(id) {
                    return this.items[id] !== undefined;
                },

                count() {
                    return Object.keys(this.items).length;
                },

                countType(type) {
                    return Object.values(this.items).filter((item) => item.t === type).length;
                },

                target(type) {
                    const value = Number(this.targets[type]);

                    return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
                },

                remaining(type) {
                    return Math.max(this.target(type) - this.countType(type), 0);
                },

                isOverTarget(type) {
                    return this.countType(type) > this.target(type);
                },

                canSelect(id, type) {
                    return this.has(id) || this.countType(type) < this.target(type);
                },

                // A page without a choice of exam mode is online only.
                allowsCq() {
                    return (this.$wire.data.exam_mode ?? 'online') !== 'online';
                },

                // Which target fields the chosen question type calls for (MCQ
                // only, on a page without the type filter).
                showsType(type) {
                    const filter = this.$wire.data.question_type ?? 'mcq';

                    if (type === 'cq' && ! this.allowsCq()) {
                        return false;
                    }

                    return filter === 'both' || filter === type;
                },

                needsTarget() {
                    return ['mcq', 'cq'].some((type) => this.showsType(type) && this.target(type) === 0);
                },

                totalMarks() {
                    return Object.values(this.items).reduce((sum, item) => sum + (Number(item.m) || 0), 0);
                },

                chapterIds() {
                    return Object.keys(this.chapters);
                },

                chapterCount(chapterId, type = null) {
                    return Object.values(this.items).filter(
                        (item) => String(item.c) === String(chapterId) && (type === null || item.t === type),
                    ).length;
                },

                toggle(question, event) {
                    if (this.has(question.id)) {
                        this.remove(question.id);
                    } else if (this.canSelect(question.id, question.type)) {
                        this.add(question);
                        this.save();
                    }

                    // The tick-box always ends up showing the real selection,
                    // whatever the browser did to it on click.
                    event.target.checked = this.has(question.id);
                },

                add(question) {
                    this.items[question.id] = { c: question.chapter, t: question.type, m: question.marks };
                    this.chapters[question.chapter] = question.chapterName;
                },

                // Ticks every question of the page that still fits: each one
                // goes through the same check as a single tick, so the
                // number of questions asked for is never exceeded.
                selectAll(questions) {
                    questions
                        .filter((question) => ! this.has(question.id))
                        .forEach((question) => {
                            if (this.canSelect(question.id, question.type)) {
                                this.add(question);
                            }
                        });

                    this.save();
                },

                unselectAll(questions) {
                    questions.filter((question) => this.has(question.id)).forEach((question) => this.remove(question.id));
                },

                allSelected(questions) {
                    return questions.length > 0 && questions.every((question) => this.has(question.id));
                },

                canSelectAny(questions) {
                    return questions.some((question) => ! this.has(question.id) && this.canSelect(question.id, question.type));
                },

                // How many more of each listed type the numbers asked for allow.
                stillWanted() {
                    return Object.fromEntries(['mcq', 'cq'].map((type) => [type, this.showsType(type) ? this.remaining(type) : 0]));
                },

                canPickAtRandom() {
                    return Object.values(this.stillWanted()).some((count) => count > 0);
                },

                // The server picks at random from every page of the list; the
                // picks are then ticked like "select all", within the targets.
                async pickAtRandom() {
                    this.picking = true;
                    this.pickedNone = false;

                    try {
                        const questions = await this.$wire.randomQuestions(this.ids(), this.stillWanted());

                        this.selectAll(questions);

                        // A short note, so it never outlives the list it was about.
                        if (questions.length === 0) {
                            this.pickedNone = true;
                            setTimeout(() => (this.pickedNone = false), 4000);
                        }
                    } finally {
                        this.picking = false;
                    }
                },

                remove(id) {
                    const chapterId = this.items[id]?.c;

                    delete this.items[id];
                    this.forgetEmptyChapter(chapterId);
                    this.save();
                },

                removeChapter(chapterId) {
                    Object.keys(this.items)
                        .filter((id) => String(this.items[id].c) === String(chapterId))
                        .forEach((id) => delete this.items[id]);

                    this.forgetEmptyChapter(chapterId);
                    this.save();
                },

                forgetEmptyChapter(chapterId) {
                    if (chapterId !== undefined && this.chapterCount(chapterId) === 0) {
                        delete this.chapters[chapterId];
                    }
                },

                clearSelection() {
                    this.items = {};
                    this.chapters = {};
                    this.save();
                },

                // The server's verdict on which ticked ids still exist in the approved pool.
                keepOnly(validIds) {
                    const valid = new Set((validIds ?? []).map(String));

                    Object.keys(this.items)
                        .filter((id) => ! valid.has(String(id)))
                        .forEach((id) => this.remove(id));
                },

                subjectChanged(value) {
                    if (! value) {
                        return;
                    }

                    if (this.count() > 0 && String(value) !== String(this.classSubjectId)) {
                        this.pending = { kind: 'subject', value };

                        return;
                    }

                    this.classSubjectId = value;
                    this.save();
                },

                modeChanged(value) {
                    if (value === 'online' && this.countType('cq') > 0) {
                        this.pending = { kind: 'mode', value };

                        return;
                    }

                    this.mode = value;
                    this.save();
                },

                confirmPending() {
                    if (this.pending.kind === 'clear') {
                        this.items = {};
                        this.chapters = {};
                    } else if (this.pending.kind === 'subject') {
                        this.items = {};
                        this.chapters = {};
                        this.classSubjectId = this.pending.value;
                    } else {
                        Object.keys(this.items)
                            .filter((id) => this.items[id].t === 'cq')
                            .forEach((id) => this.remove(id));

                        this.mode = this.pending.value;
                    }

                    this.pending = null;
                    this.save();
                },

                cancelPending() {
                    const kind = this.pending?.kind;

                    this.pending = null;

                    // Put the filters back on the subject / mode the selection belongs to.
                    if (kind !== 'clear') {
                        this.$wire.restoreFilters({ class_subject_id: this.classSubjectId, exam_mode: this.mode });
                    }
                },

                canReview() {
                    return this.count() > 0 && ! this.isOverTarget('mcq') && ! this.isOverTarget('cq');
                },

                review() {
                    this.$wire.review(this.ids());
                },

                finish() {
                    this.$wire.finish(this.ids());
                },
            }));
        </script>
    @endscript
</x-filament-panels::page>
