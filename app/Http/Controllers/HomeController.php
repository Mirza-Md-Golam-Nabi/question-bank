<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\View\View;

/**
 * The home page — with the offers and news on now, for everyone.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('welcome', [
            'offers' => Offer::query()->live()->latest('id')->get(),
        ]);
    }
}
