<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Services\ChatbotService; 

class ChatbotController extends Controller
{
    protected $chatbotService;

    // Inject the service into our controller
    public function __construct(ChatbotService $chatbotService)
    {
        $this->chatbotService = $chatbotService;
    }

    /**
     * Display the main chat interface
     */
    public function index()
    {
        $conversation = session('conversation', []);

        // Format timestamps for view
        $formattedConversation = collect($conversation)->map(function($exchange) {
            $exchange['formatted_time'] = Carbon::parse($exchange['timestamp'])->format('H:i');
            return $exchange;
        })->toArray();

        return view('index', [
            'conversation' => $formattedConversation
        ]);
    }

    /**
     * Process user input and generate AI response via Gemini
     */
    public function process(Request $request)
    {
        // Simple validation for the input
        $validator = Validator::make($request->all(), [
            'user_input' => ['required', 'string', 'max:2000', 'min:2'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first('user_input')
            ], 422);
        }

        $user_input = $request->input('user_input');
        $conversation = session('conversation', []);

        // Call the service to get the live response from Gemini
        $responseHtml = $this->chatbotService->generateGeminiResponse($user_input, $conversation);

        // Add the new exchange to our session history
        $conversation[] = [
            'user_message' => $user_input,
            'ai_response' => $responseHtml, // This is the formatted HTML for the view
            'ai_response_raw' => strip_tags($responseHtml), // A raw text version for the next API call
            'timestamp' => now(),
        ];

        // Keep only the last 10 exchanges to prevent the session from getting too large
        session(['conversation' => array_slice($conversation, -10)]);

        // Return the successful JSON response to the frontend
        return response()->json([
            'success' => true,
            'response' => $responseHtml,
            'timestamp' => now()->format('H:i'),
        ]);
    }
}