<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user closed an offer in their dashboard banner — it isn't shown to them
 * again (Offer::scopeInBannerOf()).
 */
#[Fillable(['offer_id', 'user_id'])]
class OfferDismissal extends Model
{
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
