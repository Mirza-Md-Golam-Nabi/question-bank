@props(['question'])

@php
    /**
     * Where a past paper's question came from — "ঢাবি 2023 · ক ইউনিট" — on
     * the exam, its result and the print. Shown only when the question
     * already knows its paper (Exam::onlineItems() and printedItems() set
     * it), so this never queries per question; a page that shows a whole
     * paper under its own heading leaves it unset. Nothing for a
     * question-bank question.
     *
     * @var \App\Models\Question|\App\Models\BoardMcqQuestion|\App\Models\BoardCqQuestion $question
     */
    $paper = $question->relationLoaded('boardQuestionPaper') ? $question->boardQuestionPaper : null;
@endphp

@if ($paper)
    <span {{ $attributes->class('qb-question-source') }}>{{ $paper->label() }}</span>
@endif
