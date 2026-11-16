<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaperSourceType;
use App\Http\Controllers\Controller;
use App\Services\QuestionSyncSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The live server's side of bringing questions to the test server — what
 * the test server's fetch page asks (AuthenticateQuestionSyncRequest lets
 * it through). What is shared is QuestionSyncSource's.
 */
class QuestionSyncController extends Controller
{
    public function __construct(private readonly QuestionSyncSource $source) {}

    public function status(): JsonResponse
    {
        return response()->json(['open_until' => QuestionSyncSource::openUntil()?->toIso8601String()]);
    }

    public function classes(): JsonResponse
    {
        return response()->json($this->source->classes());
    }

    public function subjects(int $class): JsonResponse
    {
        return response()->json($this->source->subjects($class));
    }

    public function chapters(int $classSubject): JsonResponse
    {
        return response()->json($this->source->chapters($classSubject));
    }

    public function questions(Request $request, int $chapter): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['single', 'group'])],
            'after' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json($this->source->units($chapter, $data['kind'], (int) ($data['after'] ?? 0)));
    }

    public function paperSources(string $type): JsonResponse
    {
        $type = PaperSourceType::tryFrom($type) ?? abort(Response::HTTP_NOT_FOUND);

        return response()->json($this->source->paperSources($type));
    }

    public function papers(int $board): JsonResponse
    {
        return response()->json($this->source->papers($board));
    }

    public function paper(int $paper): JsonResponse
    {
        return response()->json($this->source->paper($paper) ?? abort(Response::HTTP_NOT_FOUND));
    }

    public function image(Request $request): StreamedResponse
    {
        $path = $this->source->imagePath($request->query('path')) ?? abort(Response::HTTP_NOT_FOUND);

        return Storage::disk('public')->response($path);
    }
}
