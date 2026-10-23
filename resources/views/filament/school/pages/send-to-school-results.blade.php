@php
    use App\Services\SchoolResultMatcher;

    $statusLabel = [
        SchoolResultMatcher::MATCHED => [__('Matched'), 'success'],
        SchoolResultMatcher::NAME_MISMATCH => [__('Name does not match'), 'warning'],
        SchoolResultMatcher::DUPLICATE => [__('Roll used more than once'), 'danger'],
        SchoolResultMatcher::ABSENT => [__('Absent'), 'gray'],
    ];
    $push = $this->push;
@endphp

<x-filament-panels::page>
    <x-filament::section :heading="__('Where the marks go')" :description="__('The class the papers came from, then the exam and subject in the school management software.')">
        <div class="grid gap-4 md:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">{{ __('Class') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="classId">
                        <option value="">{{ __('Choose a class') }}</option>
                        @foreach ($this->classes as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                @error('classId') <span class="mt-1 block text-sm text-danger-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">{{ __('School exam') }}</span>
                <x-filament::input.wrapper :disabled="! $classId">
                    <x-filament::input.select wire:model.live="schoolExamId" :disabled="! $classId">
                        <option value="">{{ __('Choose an exam') }}</option>
                        @foreach ($this->schoolExams as $schoolExam)
                            <option value="{{ $schoolExam['id'] }}" @disabled($schoolExam['is_published'])>
                                {{ $schoolExam['name'] }}{{ $schoolExam['is_published'] ? ' — '.__('result published') : '' }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">{{ __('Subject (MCQ)') }}</span>
                <x-filament::input.wrapper :disabled="! $schoolExamId">
                    <x-filament::input.select wire:model.live="subjectId" :disabled="! $schoolExamId">
                        <option value="">{{ __('Choose a subject') }}</option>
                        @foreach ($this->schoolExam['subjects'] ?? [] as $subject)
                            <option value="{{ $subject['id'] }}">
                                {{ app()->getLocale() === 'bn' && filled($subject['name_bn']) ? $subject['name_bn'] : $subject['name'] }} — {{ __('MCQ :marks', ['marks' => (float) $subject['mcq_total']]) }}
                            </option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>

        <div class="mt-4">
            <x-filament::button wire:click="loadSheet" icon="heroicon-o-arrow-down-tray" :disabled="! $subjectId">
                {{ __('Load the student list') }}
            </x-filament::button>
        </div>
    </x-filament::section>

    @if ($this->sheet)
        <x-filament::section>
            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                <p>
                    {{ __('This exam: :ours marks · the school MCQ: :theirs marks.', ['ours' => (float) $this->exam->total_marks, 'theirs' => (float) $this->sheet['mcq_total']]) }}
                    @if ($this->fullMarksDiffer() && $this->inProportion)
                        <x-filament::badge color="info" class="ms-1 inline-flex">{{ __('In proportion') }}</x-filament::badge>
                    @endif
                </p>

                <label class="flex items-center gap-2">
                    <x-filament::input.checkbox wire:model.live="overwrite" />
                    <span>{{ __('Replace MCQ marks already in the school result') }}</span>
                </label>
                <p class="text-xs text-gray-500">{{ __('Without this, a student who already has MCQ marks (or is marked absent) is left as they are. Written and practical marks are never touched.') }}</p>
            </div>
        </x-filament::section>

        <x-filament::section :heading="__('Students on the school list')">
            <div class="overflow-x-auto">
                <table class="qb-data-table w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Roll') }}</th>
                            <th>{{ __('Name in school') }}</th>
                            <th>{{ __('Paper') }}</th>
                            <th>{{ __('Marks to send') }}</th>
                            <th>{{ __('In the result now') }}</th>
                            <th>{{ __('Send?') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->rows as $row)
                            @php
                                $student = $row['student'];
                                $id = $student['id'];
                                [$label, $color] = $statusLabel[$row['status']];
                            @endphp
                            <tr wire:key="row-{{ $id }}">
                                <td>{{ $student['roll'] }}@if ($student['section_name']) <span class="text-gray-500">· {{ $student['section_name'] }}</span>@endif</td>
                                <td>{{ $student['name'] }}</td>
                                <td>
                                    <x-filament::badge :color="$color">{{ $label }}</x-filament::badge>
                                    @if ($row['status'] === SchoolResultMatcher::DUPLICATE)
                                        <div class="mt-2 space-y-1">
                                            @foreach ($row['attempts'] as $paper)
                                                <label class="flex items-center gap-2" wire:key="pick-{{ $id }}-{{ $paper->id }}">
                                                    <input type="radio" class="fi-radio-input" value="{{ $paper->id }}" wire:model.live="choices.{{ $id }}.attempt">
                                                    <span>{{ $paper->guest_name }} — {{ (float) $paper->total_score }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif ($row['status'] !== SchoolResultMatcher::ABSENT)
                                        <div class="mt-1 text-xs text-gray-500">{{ __('Wrote: :name', ['name' => $row['attempts']->first()->guest_name]) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['status'] === SchoolResultMatcher::ABSENT)
                                        {{ __('Absent') }}
                                    @else
                                        {{ $this->marksOf($choices[$id]['attempt'] ?? null) ?? '—' }}
                                    @endif
                                </td>
                                <td>
                                    @if ($student['is_absent'])
                                        {{ __('Absent') }}
                                    @else
                                        {{ $student['mcq_marks'] ?? '—' }}
                                    @endif
                                    @if ($student['has_other_marks'])
                                        <div class="text-xs text-gray-500">{{ __('has written/practical') }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($row['status'] !== SchoolResultMatcher::DUPLICATE)
                                        <label class="flex items-center gap-2">
                                            <x-filament::input.checkbox wire:model.live="choices.{{ $id }}.send" />
                                            <span class="text-xs">{{ $row['status'] === SchoolResultMatcher::NAME_MISMATCH ? __('Approve') : __('Send') }}</span>
                                        </label>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->match['unmatched']->isNotEmpty())
                <div class="mt-4 rounded-lg bg-warning-50 p-3 text-sm text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
                    <p class="font-medium">{{ __('Papers whose roll is not on the school list (not sent):') }}</p>
                    <ul class="mt-1 list-disc ps-5">
                        @foreach ($this->match['unmatched'] as $paper)
                            <li>{{ $paper->participantContact() }} — {{ $paper->guest_name }} ({{ (float) $paper->total_score }})</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-3">
                {{ $this->sendAction }}
                <span class="text-sm text-gray-500">{{ __(':count students will be sent.', ['count' => count($this->marksToSend())]) }}</span>
            </div>
        </x-filament::section>
    @endif

    @if ($push)
        <x-filament::section icon="heroicon-o-check-circle" icon-color="success" :heading="__(':count marks saved in the school results', ['count' => $push->saved_count])">
            @if ($push->skipped)
                <p class="text-sm font-medium">{{ __('Left as they were in the school result:') }}</p>
                <ul class="mt-1 list-disc ps-5 text-sm">
                    @foreach ($push->skipped as $skip)
                        @php($student = $this->studentOnList((int) $skip['student_id']))
                        <li>{{ $student ? __('Roll :roll', ['roll' => $student['roll']]).' — '.$student['name'] : '#'.$skip['student_id'] }}: {{ \App\Filament\School\Pages\SendToSchoolResults::skipReason($skip['reason']) }}</li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
