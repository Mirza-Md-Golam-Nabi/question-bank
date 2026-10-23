<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateSchoolInstallation;
use App\Models\SchoolProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * "Save and connect" in a school's management software: it proves it holds
 * the School's secret (AuthenticateSchoolInstallation) and says where it
 * answers, so this platform can ask it for its classes and send it marks.
 */
class SchoolConnectionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'installation_url' => ['required', 'url:http,https', 'max:255'],
        ]);

        /** @var SchoolProfile $profile */
        $profile = $request->attributes->get(AuthenticateSchoolInstallation::PROFILE_ATTRIBUTE);
        $profile->markConnected($data['installation_url']);

        Log::info('School management software connected.', ['school_id' => $profile->user_id, 'installation_url' => $profile->installation_url]);

        return response()->json([
            'school_name' => $profile->name ?? $profile->user->name,
            'connected_at' => $profile->connected_at->toIso8601String(),
        ]);
    }
}
