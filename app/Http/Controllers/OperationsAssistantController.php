<?php

namespace App\Http\Controllers;

use App\Services\Assistant\OperationsAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsAssistantController extends Controller
{
    public function message(Request $request, OperationsAssistantService $assistant): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        return response()->json($assistant->handle(
            $request->user(),
            trim($data['message']),
            $data['conversation_id'] ?? null,
        ));
    }

    public function conversation(Request $request, OperationsAssistantService $assistant): JsonResponse
    {
        return response()->json($assistant->startConversation($request->user()));
    }

    public function history(Request $request, OperationsAssistantService $assistant): JsonResponse
    {
        return response()->json($assistant->history($request->user()));
    }
}
