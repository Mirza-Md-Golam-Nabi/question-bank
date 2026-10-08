@php
    use App\Filament\Teacher\Pages\SelectQuestions;

    $questions = $this->step === SelectQuestions::STEP_SELECT ? $this->questions : null;
    $deliveryMode = $this->deliveryMode();
@endphp

<x-filament-panels::page>
    {{--
        The selection lives in the browser only (CLAUDE.md rule 10): this
        Alpine component keeps the ticked question ids in localStorage and
        does all the counting itself, so ticking a question never calls the
        server. Livewire is only asked for a page of questions, the review
        of the ids held here, and the final save.
    --}}
    <div
        x-data="qbQuestionSelection({
            storageKey: @js('qb:teacher-selection:' . auth()->id()),
        })"
        x-on:qb-selection-validated.window="keepOnly($event.detail.ids)"
        x-on:qb-selection-saved.window="clearSelection()"
        class="qb-selection pb-28 lg:pb-0"
    >
        @if ($this->step === SelectQuestions::STEP_SELECT)
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
                                :heading="__('Choose a class, subject and chapter')"
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
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ trans_choice(':count Question|:count Questions', $questions->total()) }}
                                &middot; {{ $this->chapter->name }}
                            </p>

                            <p
                                x-show="needsTarget()"
                                x-cloak
                                class="rounded-lg bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:bg-warning-500/10 dark:text-warning-400"
                            >
                                {{ __('Enter how many questions you want above — then you can start selecting.') }}
                            </p>

                            @foreach ($questions as $question)
                                @php
                                    $type = $question->question_type->value;
                                    $item = [
                                        'id' => $question->id,
                                        'chapter' => $question->chapter_id,
                                        'chapterName' => $this->chapter->name,
                                        'type' => $type,
                                        'marks' => (float) $question->marks,
                                    ];
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

                                    @include('filament.teacher.pages.partials.question-card-content', ['question' => $question])
                                </label>
                            @endforeach

                            <x-filament::pagination :paginator="$questions" />
                        @endif
                    </div>

                    @include('filament.teacher.pages.partials.selection-summary')
                </div>
            </div>
        @elseif ($this->step === SelectQuestions::STEP_REVIEW)
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <x-filament::section>
                        <x-slot name="heading">
                            {{ $this->classSubject?->academicClass->name }} &middot; {{ $this->classSubject?->subject->name }}
                        </x-slot>

                        <x-slot name="description">
                            {{ $deliveryMode->getLabel() }}
                            @if ($deliveryMode === \App\Enums\ExamDeliveryMode::Both)
                                &mdash; {{ __('CQ questions appear on the printed paper only.') }}
                            @endif
                        </x-slot>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('This is the final view of everything you selected, chapter by chapter. Remove anything you do not want, then save it as an exam.') }}
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
                                    @include('filament.teacher.pages.partials.question-card-content', ['question' => $question])

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

                @include('filament.teacher.pages.partials.selection-summary')
            </div>
        @else
            @php
                $savedExam = $this->savedExam;
            @endphp

            <x-filament::section>
                <x-slot name="heading">{{ __('Exam saved') }}</x-slot>

                @if ($savedExam)
                    <x-slot name="description">
                        {{ $savedExam->title }} &middot;
                        {{ trans_choice(':count Question|:count Questions', $savedExam->questions_count) }}
                    </x-slot>

                    <div class="flex flex-wrap gap-3">
                        @if ($savedExam->delivery_mode->includesOffline())
                            <x-filament::button
                                tag="a"
                                :href="route('filament.teacher.exams.print', $savedExam)"
                                target="_blank"
                                icon="heroicon-o-printer"
                            >
                                {{ __('Print / PDF') }}
                            </x-filament::button>
                        @endif

                        @if ($savedExam->delivery_mode->includesOnline())
                            <x-filament::button tag="a" :href="$this->examsUrl()" icon="heroicon-o-globe-alt" color="success">
                                {{ __('Go to exams to publish it online') }}
                            </x-filament::button>
                        @endif

                        <x-filament::button color="gray" wire:click="startNewSelection" icon="heroicon-o-plus">
                            {{ __('Select questions for another exam') }}
                        </x-filament::button>
                    </div>
                @endif
            </x-filament::section>
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
            Alpine.data('qbQuestionSelection', ({ storageKey }) => ({
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

                init() {
                    this.load();

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

                allowsCq() {
                    return this.$wire.data.exam_mode !== 'online';
                },

                // Which target fields the chosen question type calls for.
                showsType(type) {
                    const filter = this.$wire.data.question_type;

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
                        this.items[question.id] = { c: question.chapter, t: question.type, m: question.marks };
                        this.chapters[question.chapter] = question.chapterName;
                        this.save();
                    }

                    // The tick-box always ends up showing the real selection,
                    // whatever the browser did to it on click.
                    event.target.checked = this.has(question.id);
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

                saveExam() {
                    this.$wire.mountAction('saveExam', { ids: this.ids() });
                },
            }));
        </script>
    @endscript
</x-filament-panels::page>
