<?php

namespace App\Http\Controllers;

use App\Services\OnboardingChecklist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A user closing the dashboard's "Get started" list for good.
 */
class OnboardingDismissalController extends Controller
{
    public function __invoke(Request $request, OnboardingChecklist $checklist): JsonResponse
    {
        $checklist->dismiss($request->user());

        return response()->json(['dismissed' => true]);
    }
}
