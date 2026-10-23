<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\SchoolProfile;
use App\Support\SchoolConnectionSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request from a school's management software through only when it
 * names a School (X-School-Code) whose account an Admin has approved and
 * is active, and is signed with that School's secret. The School's profile
 * is then on the request, for the controller.
 */
class AuthenticateSchoolInstallation
{
    public const PROFILE_ATTRIBUTE = 'schoolProfile';

    public function handle(Request $request, Closure $next): Response
    {
        $profile = SchoolProfile::query()
            ->with('user')
            ->where('connection_code', (string) $request->header(SchoolConnectionSignature::CODE_HEADER))
            ->first();

        $isAuthentic = $profile
            && SchoolConnectionSignature::verify(
                $profile->connection_secret,
                $request->header(SchoolConnectionSignature::TIMESTAMP_HEADER),
                $request->getContent(),
                $request->header(SchoolConnectionSignature::SIGNATURE_HEADER),
            );

        if (! $isAuthentic) {
            return response()->json(['message' => 'The connection code or secret is wrong.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($profile->user?->role !== UserRole::School || $profile->user->status !== UserStatus::Active) {
            return response()->json(['message' => 'This school account is not approved or is suspended.'], Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set(self::PROFILE_ATTRIBUTE, $profile);

        return $next($request);
    }
}
