@php
    /**
     * The "you still have unanswered questions" dialog an exam page shows
     * when it is submitted before time runs out (guest and Student panel
     * alike). The page's script opens it and fills in the count through the
     * data-qb-* hooks; this is only the markup.
     */
@endphp

<div class="qb-exam-dialog" data-qb-submit-warning hidden role="dialog" aria-modal="true" aria-labelledby="qb-submit-warning-title">
    <div class="qb-exam-dialog-box">
        <h2 id="qb-submit-warning-title" class="qb-exam-dialog-title">{{ __('Some questions are not answered') }}</h2>

        <p class="qb-exam-dialog-text">
            {!! __('You have not answered :count of the questions yet. Do you still want to submit the exam?', ['count' => '<strong data-qb-unanswered-count></strong>']) !!}
        </p>

        <div class="qb-exam-dialog-actions">
            <button type="button" class="qb-exam-dialog-button" data-qb-submit-cancel>{{ __('Go back and answer') }}</button>
            <button type="button" class="qb-exam-dialog-button qb-exam-dialog-button--danger" data-qb-submit-confirm>{{ __('Submit anyway') }}</button>
        </div>
    </div>
</div>
