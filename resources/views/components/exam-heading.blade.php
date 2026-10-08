@props(['exam'])

@php
    /**
     * The centred heading of an exam paper: subject (in the active
     * language), then the exam's title, then the class.
     *
     * The class is only known for exams built with the question picker
     * (`exams.class_subject_id`); older exams simply have no class line.
     *
     * @var \App\Models\Exam $exam
     */
    $className = $exam->classSubject?->academicClass?->name;
@endphp

<header {{ $attributes->class(['text-center']) }}>
    {{-- Smaller on a phone, so a long subject name stays on one line there. --}}
    <h1 class="text-lg font-bold leading-tight sm:text-2xl">{{ $exam->subject->display_name }}</h1>
    <h2 class="text-xs font-semibold sm:text-sm">{{ $exam->title }}</h2>

    @if ($className)
        <p class="text-gray-600">{{ $className }}</p>
    @endif
</header>
