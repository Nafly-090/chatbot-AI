<?php
// app/Services/ChatbotService.php

namespace App\Services;

use Gemini\Client;            
use Gemini\Data\Content;
use Gemini\Enums\Role;
use Illuminate\Support\Facades\Log;
use GrahamCampbell\Markdown\Facades\Markdown;
use Gemini;                     
class ChatbotService
{
    /**
     * @var Client
     */
    protected $gemini; 

    // ADD THIS CONSTRUCTOR
    public function __construct()
    {
        // This is the key change: We are creating the Gemini client manually.
        $apiKey = config('services.gemini.api_key');

        if (empty($apiKey)) {
            Log::error('GEMINI_API_KEY is not set in your .env file.');
            // You might want to throw an exception here in a real app
        }

        $this->gemini = Gemini::client($apiKey);
    }

    // In app/Services/ChatbotService.php

public function generateGeminiResponse(string $userInput, array $conversationHistory): string
{
    try {
        $systemPrompt = "You are an expert AI Project Consultant named 'CodeCraft AI'. Your personality is professional, encouraging, and highly knowledgeable about software development, specifically with Laravel, Vue.js, React, and mobile technologies. Your primary goal is to help potential clients explore and define their software project ideas. You are the first point of contact for a development agency. Follow these rules strictly: 1. **Introduce Yourself:** In your very first message, and only the first message, briefly introduce yourself as \"CodeCraft AI, your AI Project Consultant.\" 2. **Stay On-Topic:** Always steer the conversation back to software projects. If the user asks about the weather, briefly answer and then ask, \"Now, returning to your project idea, what specific features were you thinking about?\" 3. **Ask Clarifying Questions:** Never assume. Always ask probing questions to better understand the user's needs. Ask about target users, key features, budget ideas, and desired timelines. 4. **Provide Structured Advice:** When giving recommendations, use Markdown for clarity. Use bolding for key terms (`**Laravel**`), numbered lists for steps, and bullet points for features. 5. **Give Estimates with Disclaimers:** You can provide *rough* estimates for budget and timelines, but you MUST ALWAYS include a disclaimer like, \"Please note that this is a preliminary estimate. A detailed quote will be provided after a formal discovery call.\" 6. **Maintain Your Persona:** Do NOT mention that you are a large language model or a Google product. You are 'CodeCraft AI'. 7. **End with an Engaging Question:** Always end your responses with a question to keep the conversation moving forward.";

        $history = $this->formatHistory($conversationHistory);

        if (empty($history)) {
            $history[] = Content::parse(part: $systemPrompt, role: Role::USER);
            $history[] = Content::parse(part: 'OK, I am ready to act as CodeCraft AI.', role: Role::MODEL);
        }
        
        // --- THIS IS THE CORRECTED BLOCK ---
        // Replace it with this
        $chat = $this->gemini->generativeModel('gemini-2.5-flash')->startChat(history: $history);        // --- END CORRECTION ---

        $response = $chat->sendMessage($userInput);

        $geminiText = $response->text();
        
        return $this->formatResponse($geminiText);

    } catch (\Exception $e) {
        Log::error('Gemini API Error: ' . $e->getMessage() . ' on line ' . $e->getLine() . ' in ' . $e->getFile());
        return $this->formatResponse("😥 Sorry, I'm having trouble connecting to my brain right now. Please try again in a moment. (Error: " . $e->getCode() . ")");
    }
}

    private function formatHistory(array $conversationHistory): array
    {
        $history = [];
        foreach ($conversationHistory as $message) {
            $history[] = Content::parse(
                part: $message['user_message'],
                role: Role::USER
            );
            $history[] = Content::parse(
                part: $message['ai_response_raw'] ?? 'OK.',
                role: Role::MODEL
            );
        }
        return $history;
    }
    
    private function formatResponse(string $text): string
    {
        return Markdown::convert($text)->getContent();
    }
}