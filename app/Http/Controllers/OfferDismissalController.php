<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferDismissal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A user closing an offer in their dashboard banner — it isn't shown to
 * them again.
 */
class OfferDismissalController extends Controller
{
    public function __invoke(Request $request, Offer $offer): JsonResponse
    {
        OfferDismissal::query()->firstOrCreate(['offer_id' => $offer->id, 'user_id' => $request->user()->id]);

        return response()->json(['dismissed' => true]);
    }
}
