<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The installable app (config/pwa.php): one app for every role. Its manifest,
 * and /app — where it opens: a signed-in user's own panel, or for anyone
 * else "who are you?" with the offers on now.
 */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $icon = fn (string $key, string $size, string $purpose = 'any'): array => [
            'src' => (string) config("pwa.icons.{$key}"),
            'sizes' => $size,
            'type' => 'image/png',
            'purpose' => $purpose,
        ];

        return response()->json([
            'id' => '/app',
            'name' => config('pwa.name'),
            'short_name' => config('pwa.short_name'),
            'description' => config('pwa.description'),
            'lang' => 'bn',
            'dir' => 'ltr',
            'start_url' => '/app',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'theme_color' => config('pwa.theme_color'),
            'background_color' => config('pwa.background_color'),
            'icons' => [
                $icon('192', '192x192'),
                $icon('512', '512x512'),
                $icon('maskable', '512x512', 'maskable'),
            ],
        ], options: JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Type', 'application/manifest+json');
    }

    public function start(Request $request): RedirectResponse|View
    {
        if ($user = $request->user()) {
            return redirect()->route('filament.'.$user->role->panelId().'.pages.dashboard');
        }

        return view('pwa.start', [
            'offers' => Offer::query()->live()->latest('id')->get(),
        ]);
    }
}
