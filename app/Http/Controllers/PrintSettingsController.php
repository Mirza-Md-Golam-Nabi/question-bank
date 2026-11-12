<?php

namespace App\Http\Controllers;

use App\Support\PrintSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Saves the print settings a Teacher or School changes on the printable
 * question paper, as they change them — so the next paper, on any device,
 * looks the same. Registered inside the Teacher and School panels'
 * authenticated routes.
 */
class PrintSettingsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->role->setsExams(), 403);

        PrintSettings::save($request->user(), $request->all());

        return response()->noContent();
    }
}
