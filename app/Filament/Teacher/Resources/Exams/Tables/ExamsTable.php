<?php

namespace App\Filament\Teacher\Resources\Exams\Tables;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\Locale;
use App\Enums\QuestionType;
use App\Filament\School\Pages\SendToSchoolResults;
use App\Filament\Support\CurrentPanel;
use App\Filament\Support\FutureDateTimePicker;
use App\Filament\Support\SubjectColumn;
use App\Filament\Support\TableActions;
use App\Filament\Teacher\Pages\SelectQuestions;
use App\Filament\Teacher\Resources\Exams\Pages\ExamResults;
use App\Filament\Teacher\Resources\Exams\Pages\ListExams;
use App\Models\Exam;
use App\Services\ExamRepeater;
use App\Services\TeacherExamBuilder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Js;

class ExamsTable
{
    /**
     * Whether there are answers still to unlock: the exam is being taken
     * online and students can't see its answers yet.
     */
    private static function canReleaseAnswers(Exam $exam): bool
    {
        return $exam->status !== ExamStatus::Draft
            && $exam->delivery_mode->includesOnline()
            && ! $exam->showsAnswersToStudents();
    }

    /**
     * Whether any student has started the exam — which freezes its paper
     * (TeacherExamBuilder::canBeEdited()). Read off the table's own query
     * when it is there.
     */
    private static function hasAttempts(Exam $exam): bool
    {
        return (bool) ($exam->attempts_exists ?? $exam->attempts()->exists());
    }

    /**
     * The trophy button to an exam's results: who sat it and how they
     * ranked. There is something to show as soon as the exam is out for
     * students to take online. Shared with the dashboard's "Recent exams".
     */
    public static function resultsAction(): Action
    {
        return Action::make('results')
            ->label(__('Results'))
            ->tooltip(__('Results'))
            ->icon('heroicon-o-trophy')
            ->iconButton()
            ->color('warning')
            ->visible(fn (Exam $record) => $record->status !== ExamStatus::Draft && $record->delivery_mode->includesOnline())
            ->url(fn (Exam $record): string => ExamResults::getUrl(['record' => $record]));
    }

    /**
     * The eye button: the exam's questions to read, with their answers and
     * explanations — whatever kind of exam it is (online exams have no
     * printed paper to look at). Shared with the dashboard's "Recent exams".
     */
    public static function viewQuestionsAction(): Action
    {
        return Action::make('viewQuestions')
            ->label(__('View questions'))
            ->tooltip(__('View questions'))
            ->icon('heroicon-o-eye')
            ->iconButton()
            ->color('gray')
            ->modalHeading(fn (Exam $record): string => $record->title)
            ->modalDescription(fn (Exam $record): ?string => $record->heading() !== $record->title ? $record->heading() : null)
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Close'))
            ->modalContent(function (Exam $record): View {
                $questions = $record->printedItems();

                return view('filament.support.questions.question-list', [
                    'mcqQuestions' => $questions->filter(fn (Model $question): bool => $question->question_type === QuestionType::Mcq)->values(),
                    'cqQuestions' => $questions->filter(fn (Model $question): bool => $question->question_type === QuestionType::Cq)->values(),
                ]);
            });
    }

    /**
     * One click copies the exam's share link — an icon instead of a column
     * spelling out the whole URL. Only there once the exam is published and
     * so actually has a link. Shared with the dashboard's "Recent exams".
     */
    public static function copyShareLinkAction(): Action
    {
        return Action::make('copyShareLink')
            ->label(__('Copy share link'))
            ->tooltip(__('Copy share link'))
            ->icon('heroicon-o-link')
            ->iconButton()
            ->color('primary')
            ->visible(fn (Exam $record) => filled($record->share_token))
            // Copied in the browser itself; nothing to ask the server.
            ->alpineClickHandler(fn (Exam $record): string => sprintf(
                'window.navigator.clipboard.writeText(%s); $tooltip(%s, { theme: $store.theme, timeout: 2000 })',
                Js::from($record->shareUrl()),
                Js::from(__('Link copied')),
            ));
    }

    /**
     * The same paper as a new exam for another group of students — its own
     * link and results, counted against the plan again (ExamRepeater).
     */
    public static function takeAgainAction(): Action
    {
        return Action::make('takeAgain')
            ->label(__('Take again'))
            ->tooltip(__('Take this exam again with other students'))
            ->icon('heroicon-o-document-duplicate')
            ->iconButton()
            ->color('gray')
            ->visible(fn (Exam $record): bool => app(ExamRepeater::class)->canRepeat($record, Auth::user()))
            ->modalHeading(__('Take this exam again'))
            ->modalDescription(function (Exam $record): string {
                $leftOut = app(ExamRepeater::class)->preview($record, Auth::user())['leftOut'];

                return __('A new exam with the same questions and its own link. This exam, its link and its results stay as they are. It counts as a new exam on your plan.')
                    .($leftOut > 0 ? ' '.trans_choice(':count question is left out: it has been rejected, or edited and not approved again.|:count questions are left out: they have been rejected, or edited and not approved again.', $leftOut) : '');
            })
            ->fillForm(fn (Exam $record): array => [
                'title' => $record->title.' ('.Locale::current()->writeDigits('2').')',
                'duration_minutes' => $record->duration_minutes,
            ])
            ->schema(fn (Exam $record): array => [
                TextInput::make('title')->label(__('Exam title'))->required()->maxLength(255),
                TextInput::make('duration_minutes')->label(__('Duration (minutes)'))->integer()->minValue(1)->maxValue(600)->required(),
                FutureDateTimePicker::make('link_expires_at')
                    ->label(__('Exam ends at'))
                    ->visible($record->delivery_mode->includesOnline()),
            ])
            ->modalSubmitActionLabel(__('Create the new exam'))
            ->authorize('update')
            ->action(fn (Exam $record, array $data, ListExams $livewire, Action $action) => $livewire->takeAgain($record, $data, $action));
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // One query for the whole page tells every row whether students
            // have started it, instead of each row asking separately.
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withExists('attempts')
                // A past paper's questions are counted on the paper.
                ->with(['boardQuestionPaper' => fn ($paper) => $paper->withCount(['mcqQuestions', 'cqQuestions'])]))
            ->columns([
                // Only exams built with the question picker know their class.
                TextColumn::make('classSubject.academicClass.name')
                    ->label(__('Class'))
                    ->alignCenter()
                    ->placeholder('—'),
                SubjectColumn::make()->alignCenter(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('delivery_mode')->label(__('Exam mode'))->badge()->color('gray')->alignCenter(),
                TextColumn::make('status')->badge()->alignCenter(),
                TextColumn::make('total_marks')->alignCenter(),
                TextColumn::make('questions_count')
                    ->label(__('Questions'))
                    ->counts('questions')
                    ->state(fn (Exam $record): int => $record->isPastPaper()
                        ? $record->boardQuestionPaper->mcq_questions_count + ($record->delivery_mode->includesOffline() ? $record->boardQuestionPaper->cq_questions_count : 0)
                        : (int) $record->questions_count)
                    ->alignCenter(),
                // Until when the exam can be started; red once that has passed.
                TextColumn::make('link_expires_at')
                    ->label(__('Ends at'))
                    ->alignCenter()
                    ->badge()
                    ->state(fn (Exam $record): ?string => $record->link_expires_at?->translatedFormat('j M, g:i A'))
                    ->color(fn (Exam $record): string => $record->link_expires_at?->isPast() ? 'danger' : 'gray')
                    ->tooltip(fn (Exam $record): ?string => $record->link_expires_at?->isPast() ? __('This exam has ended') : null)
                    ->placeholder('—'),
                // Where the answers stand for students: locked, unlocking at
                // a set time, or already visible.
                TextColumn::make('answers_release_at')
                    ->label(__('Answers'))
                    ->alignCenter()
                    ->badge()
                    ->state(fn (Exam $record): ?string => match (true) {
                        ! $record->delivery_mode->includesOnline() => null,
                        $record->showsAnswersToStudents() => __('Released'),
                        $record->hasPendingAnswerRelease() => $record->answers_release_at->translatedFormat('j M, g:i A'),
                        default => __('Hidden'),
                    })
                    ->color(fn (Exam $record): string => match (true) {
                        $record->showsAnswersToStudents() => 'success',
                        $record->hasPendingAnswerRelease() => 'info',
                        default => 'gray',
                    })
                    ->icon(fn (Exam $record): ?string => $record->hasPendingAnswerRelease() ? 'heroicon-m-clock' : null)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ExamStatus::class),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label(__('Publish'))
                    ->color('success')
                    ->icon('heroicon-o-globe-alt')
                    ->visible(fn (Exam $record) => $record->status === ExamStatus::Draft && $record->delivery_mode->includesOnline())
                    ->requiresConfirmation()
                    ->authorize('publish')
                    ->action(function (Exam $record) {
                        $record->recalculateTotalMarks();
                        $record->publish();

                        Notification::make()->title(__('Exam published'))->success()->send();
                    }),
                self::resultsAction(),
                // A School's exam: its online MCQ marks into the school
                // management software's results (CLAUDE.md rule 12).
                Action::make('sendToSchoolResults')
                    ->label(__('Send to school results'))
                    ->tooltip(__('Send to school results'))
                    ->icon('heroicon-o-paper-airplane')
                    ->iconButton()
                    ->color('success')
                    ->visible(fn (Exam $record): bool => CurrentPanel::id() === 'school'
                        && $record->status !== ExamStatus::Draft
                        && $record->delivery_mode->includesOnline())
                    ->url(fn (Exam $record): string => SendToSchoolResults::getUrl(['exam' => $record->id])),
                self::copyShareLinkAction(),
                // The last moment a student can start the exam; after it the
                // share link stops taking new attempts on its own.
                Action::make('setEndTime')
                    ->label(fn (Exam $record) => $record->link_expires_at ? __('Change end time') : __('Set end time'))
                    ->color('gray')
                    ->icon('heroicon-o-calendar-days')
                    ->visible(fn (Exam $record) => $record->delivery_mode->includesOnline())
                    ->modalHeading(__('Exam end time'))
                    ->modalDescription(__('After this time the exam link stops working for anyone who has not started yet. Students already taking the exam can finish it. Leave it empty for no end time.'))
                    ->modalSubmitActionLabel(__('Save'))
                    ->fillForm(fn (Exam $record): array => ['link_expires_at' => $record->link_expires_at])
                    ->schema([
                        FutureDateTimePicker::make('link_expires_at')->label(__('Exam ends at')),
                    ])
                    ->authorize('update')
                    ->action(function (Exam $record, array $data) {
                        $endsAt = FutureDateTimePicker::parse($data['link_expires_at'] ?? null);

                        $record->closeLinkAt($endsAt);

                        Notification::make()
                            ->title($endsAt
                                ? __('The exam will close on :time', ['time' => $endsAt->translatedFormat('j M Y, g:i A')])
                                : __('Exam end time removed'))
                            ->success()
                            ->send();
                    }),
                // Students only see their score until this is pressed —
                // otherwise a blank paper would reveal the answer key to
                // anyone who has yet to sit the exam.
                Action::make('releaseAnswers')
                    ->label(__('Release answers'))
                    ->color('info')
                    ->icon('heroicon-o-lock-open')
                    ->visible(fn (Exam $record) => self::canReleaseAnswers($record))
                    ->requiresConfirmation()
                    ->modalDescription(__('Students will be able to see every question, the correct answers and their own answers. Do this only after everyone has finished the exam.'))
                    ->authorize('update')
                    ->action(function (Exam $record) {
                        $record->releaseAnswers();

                        Notification::make()->title(__('Answers released to students'))->success()->send();
                    }),
                // The same unlock, but left to happen on its own at a time
                // the teacher picks — e.g. when the exam window closes.
                Action::make('scheduleAnswers')
                    ->label(fn (Exam $record) => $record->hasPendingAnswerRelease() ? __('Change release time') : __('Schedule answers'))
                    ->color('info')
                    ->icon('heroicon-o-clock')
                    ->visible(fn (Exam $record) => self::canReleaseAnswers($record))
                    ->modalHeading(__('Release answers automatically'))
                    ->modalDescription(__('At this time students will be able to see every question, the correct answers and their own answers — without you doing anything. Leave it empty to cancel the schedule.'))
                    ->modalSubmitActionLabel(__('Save'))
                    ->fillForm(fn (Exam $record): array => ['answers_release_at' => $record->answers_release_at])
                    ->schema([
                        FutureDateTimePicker::make('answers_release_at')->label(__('Release answers at')),
                    ])
                    ->authorize('update')
                    ->action(function (Exam $record, array $data) {
                        $releaseAt = FutureDateTimePicker::parse($data['answers_release_at'] ?? null);

                        $record->scheduleAnswerRelease($releaseAt);

                        Notification::make()
                            ->title($releaseAt
                                ? __('Answers will be released on :time', ['time' => $releaseAt->translatedFormat('j M Y, g:i A')])
                                : __('Answer release schedule cancelled'))
                            ->success()
                            ->send();
                    }),
                Action::make('hideAnswers')
                    ->label(__('Hide answers'))
                    ->color('gray')
                    ->icon('heroicon-o-lock-closed')
                    // Released by hand or by the schedule having come due.
                    ->visible(fn (Exam $record) => $record->exam_type === ExamType::TeacherExam && $record->showsAnswersToStudents())
                    ->requiresConfirmation()
                    ->authorize('update')
                    ->action(function (Exam $record) {
                        $record->hideAnswers();

                        Notification::make()->title(__('Answers hidden from students'))->success()->send();
                    }),
                self::viewQuestionsAction(),
                Action::make('print')
                    ->label(__('Print / PDF'))
                    ->color('gray')
                    ->icon('heroicon-o-printer')
                    ->visible(fn (Exam $record) => $record->delivery_mode->includesOffline())
                    ->url(fn (Exam $record): string => CurrentPanel::route('exams.print', $record))
                    ->openUrlInNewTab(),
                // Editing works like creating: the exam opens in the question
                // picker with its questions already selected. Gone once a
                // student has started it — the paper they sat must not change
                // — and for a past paper, whose questions are the paper's.
                Action::make('editQuestions')
                    ->label(__('Edit'))
                    ->tooltip(__('Edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->iconButton()
                    ->visible(fn (Exam $record) => ! self::hasAttempts($record) && ! $record->isPastPaper())
                    ->authorize('update')
                    ->url(fn (Exam $record): string => SelectQuestions::getUrl(['exam' => $record->id])),
                self::takeAgainAction(),
                // The way back to an editable paper once students have started:
                // call the exam off, which deletes everything they did on it.
                Action::make('cancelExam')
                    ->label(__('Cancel exam'))
                    ->tooltip(__('Cancel exam'))
                    ->icon('heroicon-o-x-circle')
                    ->iconButton()
                    ->color('danger')
                    ->visible(fn (Exam $record) => self::hasAttempts($record))
                    ->requiresConfirmation()
                    ->modalHeading(__('Cancel this exam?'))
                    ->modalDescription(fn (Exam $record): string => trans_choice(
                        'This permanently deletes the result of :count student who started this exam — marks, answers, positions and the question analysis. It cannot be undone. The exam goes back to a draft so you can edit its questions and publish it again with the same link.|This permanently deletes the results of all :count students who started this exam — marks, answers, positions and the question analysis. It cannot be undone. The exam goes back to a draft so you can edit its questions and publish it again with the same link.',
                        $record->attempts()->count(),
                    ))
                    ->modalSubmitActionLabel(__('Yes, cancel and delete results'))
                    ->authorize('update')
                    ->action(function (Exam $record) {
                        $deleted = $record->cancel();

                        Notification::make()
                            ->title(__('Exam cancelled'))
                            ->body(trans_choice(':count result was deleted. You can now edit the exam.|:count results were deleted. You can now edit the exam.', $deleted))
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions(TableActions::bulkDelete());
    }
}
