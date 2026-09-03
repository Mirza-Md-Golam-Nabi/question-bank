<?php

namespace App\Models;

use App\Enums\QuestionApprovalAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['question_id', 'action', 'performed_by', 'reason'])]
class QuestionApprovalLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'action' => QuestionApprovalAction::class,
            'created_at' => 'datetime',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
