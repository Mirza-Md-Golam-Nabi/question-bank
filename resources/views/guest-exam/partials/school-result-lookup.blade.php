@php
    /**
     * "See my result" for a School's exam — on its share-link page, open or
     * closed: the class, section and roll the student sat it with.
     *
     * @var \App\Models\Exam $exam
     * @var list<array<string, mixed>> $classes
     */
@endphp

<div class="mt-8 border-t pt-6">
    <h2 class="text-lg font-semibold">{{ __('Already took this exam?') }}</h2>
    <p class="mt-1 text-sm text-gray-500">{{ __('Choose your class and section and enter your roll, to see your result.') }}</p>

    <form method="POST" action="{{ route('guest-exam.school-result', $exam->share_token) }}" class="mt-4 space-y-4">
        @csrf

        <x-school-student-fields :classes="$classes" id-prefix="result" :with-name="false" :refill="$errors->has('result')" />

        @error('result')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <button type="submit" class="qb-btn qb-btn--info">{{ __('See my result') }}</button>
    </form>
</div>
