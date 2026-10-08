@php
    /**
     * "See my result" form for a guest — on the exam's share-link page,
     * whether the link is still open or already closed. A guest has no
     * account, so they are recognised by the same name and phone/email they
     * started the exam with.
     *
     * @var \App\Models\Exam $exam
     */
@endphp

<div class="mt-8 border-t pt-6">
    <h2 class="text-lg font-semibold">{{ __('Already took this exam as a guest?') }}</h2>
    <p class="mt-1 text-sm text-gray-500">{{ __('Enter the same name and phone/email you used, to see your result.') }}</p>

    <form method="POST" action="{{ route('guest-exam.result', $exam->share_token) }}" class="mt-4 space-y-4">
        @csrf

        <x-guest-identity-fields id-prefix="result" :refill="$errors->has('result')" />

        @error('result')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <button type="submit" class="qb-btn qb-btn--outline">{{ __('See my result') }}</button>
    </form>
</div>
