<?php

namespace App\Filament\Staff\Widgets;

use App\Models\QuestionRate;
use App\Models\Subject;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class StaffQuestionRatesWidget extends Widget
{
    protected string $view = 'filament.staff.widgets.question-rates';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return Collection<int, array{subject: string, rate: float}>
     */
    public function rates(): Collection
    {
        $subjects = Subject::orderBy('name')->get(['id', 'name']);

        // Every subject's rate from one query, not one query per subject.
        $rates = QuestionRate::ratesFor($subjects->modelKeys());

        return $subjects->map(fn (Subject $subject) => [
            'subject' => $subject->name,
            'rate' => $rates[$subject->id],
        ]);
    }
}
