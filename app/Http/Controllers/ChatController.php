<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
            'image' => 'nullable|string',
            'image_mime' => 'nullable|string',
            'history' => 'nullable|array',
        ]);

        $result = $this->gemini->sendMessage(
            $request->input('message'),
            $request->input('image'),
            $request->input('image_mime'),
            $request->input('history', [])
        );

        return response()->json($result);
    }

    public function usage()
    {
        return response()->json([
            'dailyRemaining' => $this->gemini->getDailyRemaining(),
            'dailyLimit' => $this->gemini->getDailyLimit(),
            'resetTime' => $this->gemini->getResetTime(),
        ]);
    }
}
