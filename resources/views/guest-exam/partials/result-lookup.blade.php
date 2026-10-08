@php
    /**
     * "See my result" form for a guest — on the exam's share-link page,
     * whether the link is still open or already closed. A guest has no
     * account, so they are recognised by the same name and phone/email they
     * started the exam with.
     *
     * @var \App\Models\Exam $exam
     */
    $inputClasses = 'w-full rounded-lg border border-gray-300 bg-white px-3 py-2 placeholder:text-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-200';
@endphp

<div class="mt-8 border-t pt-6">
    <h2 class="text-lg font-semibold">{{ __('Already took this exam as a guest?') }}</h2>
    <p class="mt-1 text-sm text-gray-500">{{ __('Enter the same name and phone/email you used, to see your result.') }}</p>

    <form method="POST" action="{{ route('guest-exam.result', $exam->share_token) }}" class="mt-4 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium mb-1" for="result-guest-name">{{ __('Your name') }}</label>
            <input id="result-guest-name" type="text" name="guest_name" value="{{ $errors->has('result') ? old('guest_name') : '' }}" required class="{{ $inputClasses }}">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1" for="result-guest-contact">{{ __('Phone or email') }}</label>
            <input id="result-guest-contact" type="text" name="guest_contact" value="{{ $errors->has('result') ? old('guest_contact') : '' }}" required class="{{ $inputClasses }}">
        </div>

        @error('result')
            <p class="text-sm text-red-600">{{ $message }}</p>
        @enderror

        <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 font-medium hover:bg-gray-50">
            {{ __('See my result') }}
        </button>
    </form>
</div>
