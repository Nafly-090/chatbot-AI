<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class ChatbotController extends Controller
{
    public function index()
    {
        // Get current conversation
        $current_conversation = session('current_conversation', []);
        
        // Get all saved conversations from local storage (via session for demo)
        $saved_conversations = session('saved_conversations', []);
        
        // Format timestamps
        $formattedConversation = collect($current_conversation)->map(function($exchange) {
            $exchange['formatted_time'] = Carbon::parse($exchange['timestamp'])->format('H:i');
            return $exchange;
        })->toArray();

        return view('index', [
            'current_conversation' => $formattedConversation,
            'saved_conversations' => $saved_conversations,
            'has_conversation' => !empty($current_conversation)
        ]);
    }

    public function process(Request $request)
    {
        $isAjax = $request->ajax() || $request->wantsJson();
        
        // Get current conversation
        $conversation = session('current_conversation', []) ?? [];
        
        // Validation
        $validator = Validator::make($request->all(), [
            'user_input' => [
                'required',
                'string',
                'max:1000',
                'min:10',
                function ($attribute, $value, $fail) use ($conversation) {
                    $word_count = str_word_count($value);
                    $tokens = explode(' ', strtolower($value));
                    $stop_words = ['a', 'an', 'the', 'i', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
                    $meaningful_words = array_diff($tokens, $stop_words);
                    
                    if ($word_count < 3 || count($meaningful_words) < 2 || ctype_digit(trim($value))) {
                        $fail('Please provide more details about your project (at least 3 meaningful words).');
                    }

                    $is_followup = false;
                    $followup_keywords = ['more', 'details', 'how', 'what', 'when', 'budget', 'timeline', 'features', 'technology'];
                    foreach ($followup_keywords as $keyword) {
                        if (stripos($value, $keyword) !== false) {
                            $is_followup = true;
                            break;
                        }
                    }
                    
                    if (!$is_followup && count($conversation) > 0) {
                        $context = end($conversation)['project_type'] ?? 'project';
                        $fail("Great! Regarding your {$context}, what specific aspect would you like to discuss?");
                    }
                },
            ],
        ], [
            'user_input.required' => 'Please describe your project.',
            'user_input.min' => 'Tell me more about your project.',
            'user_input.max' => 'Keep it under 1000 characters.',
        ]);

        if ($validator->fails()) {
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first('user_input')
                ], 422);
            }
            return redirect()->route('home')
                ->withErrors($validator)
                ->withInput()
                ->with('error', $validator->errors()->first('user_input'));
        }

        $user_input = $request->input('user_input');
        $response = $this->generateResponse($user_input, $conversation);
        $project_type = $this->detectProjectType($user_input);
        
        // Add to current conversation
        $conversation[] = [
            'user_message' => $user_input,
            'ai_response' => $response,
            'timestamp' => now(),
            'project_type' => $project_type
        ];

        // Limit to 20 messages
        if (count($conversation) > 20) {
            $conversation = array_slice($conversation, -20);
        }
        
        // Save to session
        session(['current_conversation' => $conversation]);

        if ($isAjax) {
            return response()->json([
                'success' => true,
                'response' => $response,
                'project_type' => $project_type,
                'timestamp' => now()->format('H:i'),
                'conversation_count' => count($conversation)
            ]);
        }

        return redirect()->route('home')
            ->with('success', 'Message sent! Here\'s my response.');
    }

    /**
     * Start new conversation
     */
    public function newChat(Request $request)
    {
        if ($request->ajax()) {
            // Save current conversation to history
            $current = session('current_conversation', []);
            $saved = session('saved_conversations', []);
            
            if (!empty($current)) {
                $chatTitle = $this->generateChatTitle($current);
                $saved[] = [
                    'id' => uniqid(),
                    'title' => $chatTitle,
                    'preview' => $current[0]['user_message'] ?? 'New conversation',
                    'timestamp' => now(),
                    'messages' => $current,
                    'project_type' => end($current)['project_type'] ?? 'general'
                ];
                
                // Keep only last 10 conversations
                if (count($saved) > 10) {
                    $saved = array_slice($saved, -10);
                }
                
                session(['saved_conversations' => $saved]);
            }
            
            // Clear current conversation
            session(['current_conversation' => []]);
            
            return response()->json([
                'success' => true,
                'message' => 'New chat started!',
                'saved_count' => count($saved)
            ]);
        }
    }

    /**
     * Load saved conversation
     */
    public function loadChat(Request $request)
    {
        $chatId = $request->input('chat_id');
        $saved = session('saved_conversations', []);
        
        foreach ($saved as $chat) {
            if ($chat['id'] === $chatId) {
                session(['current_conversation' => $chat['messages']]);
                return response()->json([
                    'success' => true,
                    'title' => $chat['title'],
                    'messages' => $chat['messages']
                ]);
            }
        }
        
        return response()->json(['success' => false, 'error' => 'Chat not found'], 404);
    }

    /**
     * Delete conversation
     */
    public function deleteChat(Request $request)
    {
        $chatId = $request->input('chat_id');
        $saved = session('saved_conversations', []);
        
        $saved = array_filter($saved, function($chat) use ($chatId) {
            return $chat['id'] !== $chatId;
        });
        
        session(['saved_conversations' => array_values($saved)]);
        
        return response()->json([
            'success' => true,
            'message' => 'Chat deleted',
            'remaining' => count($saved)
        ]);
    }

    /**
     * Generate title for saved conversations
     */
    private function generateChatTitle($conversation)
    {
        $firstMessage = $conversation[0]['user_message'] ?? '';
        $projectType = end($conversation)['project_type'] ?? 'project';
        
        // Extract keywords from first message
        $tokens = explode(' ', strtolower($firstMessage));
        $keywords = array_filter($tokens, function($token) {
            return strlen($token) > 3 && !in_array($token, ['need', 'want', 'create', 'build', 'make']);
        });
        
        $title = implode(' ', array_slice($keywords, 0, 4));
        
        // Fallback based on project type
        $typeTitles = [
            'student_exam_system' => 'Student Exam System',
            'inventory_system' => 'Inventory Management', 
            'ecommerce_system' => 'E-commerce Store',
            'blog_system' => 'Blog Platform',
            'general_project' => 'New Project'
        ];
        
        return $typeTitles[$projectType] ?? ucwords($title) ?: 'New Conversation';
    }

    // ... keep all your existing methods (detectProjectType, generateResponse, etc.)
    private function detectProjectType($user_input)
    {
        $tokens = explode(' ', strtolower($user_input));
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'i', 'you', 'it', 'he', 'she', 'we', 'they'];
        $tokens = array_diff($tokens, $stop_words);
        $tokens = array_filter($tokens, function($token) {
            return strlen($token) > 2;
        });

        if ((in_array('student', $tokens) || in_array('students', $tokens)) && 
            (in_array('exam', $tokens) || in_array('test', $tokens) || in_array('quiz', $tokens))) {
            return "student_exam_system";
        } elseif (in_array('inventory', $tokens) || in_array('stock', $tokens) || in_array('warehouse', $tokens)) {
            return "inventory_system";
        } elseif (in_array('ecommerce', $tokens) || in_array('shop', $tokens) || in_array('store', $tokens)) {
            return "ecommerce_system";
        } elseif (in_array('blog', $tokens) || (in_array('content', $tokens) && in_array('management', $tokens))) {
            return "blog_system";
        }
        
        return "general_project";
    }

    private function generateResponse($user_input, $conversation)
    {
        $word_count = str_word_count($user_input);
        if ($word_count < 5 && count($conversation) > 0) {
            return $this->getFollowupResponse($user_input, $conversation);
        }

        $project_type = $this->detectProjectType($user_input);
        $response_data = $this->getProjectResponse($project_type, $user_input, $conversation);
        
        return $this->formatResponse($response_data);
    }

    private function getFollowupResponse($user_input, $conversation)
    {
        $last_exchange = end($conversation);
        $project_type = $last_exchange['project_type'] ?? 'project';
        
        $followup_responses = [
            "student_exam_system" => [
                "Looking to dive deeper into your exam system? Tell me about specific features like question types or integration needs!",
                "Great follow-up! For your exam system, are you most interested in technical implementation or user experience?"
            ],
            "inventory_system" => [
                "Excellent! Regarding your inventory solution, would you like to explore barcode integration or reporting requirements?",
                "Perfect! For inventory management, should we discuss real-time tracking or mobile access needs?"
            ],
            "ecommerce_system" => [
                "Wonderful! For your online store, are you thinking about payment options or marketing features?",
                "Nice! Regarding e-commerce, would you like to explore product management or SEO strategies?"
            ],
            "general_project" => [
                "Happy to continue! What specific aspect would you like to explore next? Technology or timeline?",
                "Great to keep the conversation going! Should we dive into architecture or scalability planning?"
            ]
        ];

        $responses = $followup_responses[$project_type] ?? $followup_responses['general_project'];
        $response = $responses[array_rand($responses)];
        
        return "<div class='followup-response'>
            <h3>💬 Continuing Our Conversation</h3>
            <p><strong>You:</strong> " . htmlspecialchars($user_input) . "</p>
            <p><em>{$response}</em></p>
        </div>";
    }

    private function getProjectResponse($project_type, $user_input, $conversation = [])
    {
        $base_response = $this->getBaseProjectResponse($project_type);
        
        if (!empty($conversation)) {
            $last_project = end($conversation)['project_type'] ?? null;
            if ($last_project && $last_project !== $project_type) {
                $base_response['advice'] = "Building on our previous discussion about your {$last_project}, I see you're now exploring {$project_type}. Here's my analysis:";
            }
        }
        
        return $base_response;
    }

    private function getBaseProjectResponse($project_type)
    {
        switch ($project_type) {
            case "student_exam_system":
                return [
                    'advice' => "Excellent choice! A student exam system is crucial for modern educational institutions. Our recommendation is to prioritize data security, user experience, and scalability.",
                    'duration' => "📅 Development Timeline: 4-8 weeks",
                    'features' => [
                        "🔐 Secure user authentication",
                        "📚 Student profile management", 
                        "📋 Exam scheduling & reminders",
                        "🧠 Question bank & auto-grading",
                        "📊 Performance analytics",
                        "📱 Mobile-responsive design",
                        "💾 Data backup & export",
                        "🔍 Advanced search system",
                        "📧 Email/SMS notifications"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x",
                        "Frontend: React.js + Tailwind",
                        "Database: MySQL + Redis",
                        "Mobile: Flutter (optional)",
                        "Deployment: Laravel Forge/AWS"
                    ],
                    'budget' => "💰 Investment: $1,200 - $3,500<br><small>Includes 3 months support</small>",
                    'terms' => [
                        "💳 Payment: 40% upfront, 30% milestone, 30% launch",
                        "🔄 Revisions: 3 rounds included",
                        "⏰ Timeline: Fixed delivery date",
                        "📜 Full code ownership",
                        "🔒 GDPR compliant security"
                    ],
                    'summary' => "A comprehensive exam system that streamlines assessments with modern technology.",
                    'questions' => [
                        "What assessment types do you need?",
                        "Need integration with existing systems?",
                        "Web-only or mobile apps too?",
                        "What's your target launch date?"
                    ]
                ];

            case "inventory_system":
                return [
                    'advice' => "Inventory management is the backbone of efficient operations. Real-time tracking is key.",
                    'duration' => "📅 Development Timeline: 6-10 weeks",
                    'features' => [
                        "📦 Product catalog management",
                        "📊 Real-time stock monitoring",
                        "📋 Automated purchase orders",
                        "💳 Integrated POS system",
                        "📱 Barcode/QR scanning",
                        "📈 Analytics & forecasting",
                        "🏢 Multi-warehouse support",
                        "🔐 Role-based access control"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x",
                        "Frontend: Vue.js 3.x",
                        "Database: PostgreSQL",
                        "Mobile: React Native",
                        "Real-time: Laravel Echo"
                    ],
                    'budget' => "💰 Investment: $2,000 - $5,000<br><small>Includes hardware integration</small>",
                    'terms' => [
                        "💳 Payment: 30% upfront, 40% beta, 30% production",
                        "🔄 Unlimited minor revisions",
                        "⏰ Agile sprints",
                        "📜 Complete codebase transfer",
                        "🔒 PCI DSS compliant"
                    ],
                    'summary' => "Transform your inventory operations with real-time tracking and automation.",
                    'questions' => [
                        "What product types to track?",
                        "Multiple warehouse locations?",
                        "Need mobile scanning?",
                        "Existing system integrations?"
                    ]
                ];

            case "ecommerce_system":
                return [
                    'advice' => "E-commerce success depends on conversion rates and customer experience. Mobile-first is essential.",
                    'duration' => "📅 Development Timeline: 8-14 weeks",
                    'features' => [
                        "🛍️ Advanced product catalog",
                        "🛒 Smart shopping cart",
                        "💳 Multiple payment gateways",
                        "🚚 Real-time shipping",
                        "⭐ Reviews & ratings",
                        "🔍 Advanced search",
                        "📧 Marketing automation",
                        "📊 Conversion analytics"
                    ],
                    'technologies' => [
                        "Backend: Laravel + Cashier",
                        "Frontend: Next.js 14",
                        "Database: MySQL + Elasticsearch",
                        "Payments: Stripe Connect",
                        "CDN: Cloudflare"
                    ],
                    'budget' => "💰 Investment: $3,500 - $8,000<br><small>Scales with revenue potential</small>",
                    'terms' => [
                        "💳 Payment: 25% upfront, 50% soft launch, 25% final",
                        "🔄 A/B testing included",
                        "⏰ MVP in 6 weeks",
                        "📜 Source code escrow",
                        "🔒 SOC 2 Type II compliant"
                    ],
                    'summary' => "Launch a high-converting e-commerce platform that scales with your business.",
                    'questions' => [
                        "Target average order value?",
                        "International expansion plans?",
                        "Essential payment methods?",
                        "Mobile app requirements?"
                    ]
                ];

            case "blog_system":
                return [
                    'advice' => "A blog system needs to balance content management with reader engagement. SEO is crucial.",
                    'duration' => "📅 Development Timeline: 3-6 weeks",
                    'features' => [
                        "✍️ Rich text editor",
                        "📂 Category/tag management",
                        "👥 Multi-author support",
                        "📧 Newsletter integration",
                        "🔍 Full-text search",
                        "📊 Content analytics",
                        "🔗 SEO optimization",
                        "💬 Comment moderation"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x",
                        "Frontend: Livewire",
                        "Database: MySQL",
                        "Admin: Laravel Filament",
                        "SEO: Laravel SEO package"
                    ],
                    'budget' => "💰 Investment: $800 - $2,500<br><small>Scales with content volume</small>",
                    'terms' => [
                        "💳 Payment: 50% upfront, 50% completion",
                        "🔄 Content revisions included",
                        "⏰ Rapid development",
                        "📜 Full CMS ownership",
                        "🔒 Spam protection included"
                    ],
                    'summary' => "Create an engaging blog platform that grows your audience with excellent SEO.",
                    'questions' => [
                        "Number of contributing authors?",
                        "Advanced analytics needed?",
                        "Existing content migration?",
                        "Newsletter campaigns planned?"
                    ]
                ];

            default:
                return [
                    'advice' => "Based on your description, this appears to be a custom software solution. Let's create something tailored to your needs.",
                    'duration' => "📅 Development Timeline: 4-12 weeks",
                    'features' => [
                        "👥 User authentication & roles",
                        "📊 Full CRUD operations",
                        "📱 Responsive design",
                        "📈 Analytics & reporting",
                        "🔔 Real-time notifications",
                        "🔍 Advanced search",
                        "🔐 Enterprise security",
                        "📱 Mobile-first approach"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x",
                        "Frontend: React/Vue.js",
                        "Database: MySQL/PostgreSQL",
                        "API: Laravel Sanctum",
                        "Deployment: Docker/CI-CD"
                    ],
                    'budget' => "💰 Investment: $1,000 - $6,000<br><small>Preliminary estimate</small>",
                    'terms' => [
                        "💳 Flexible payment terms",
                        "🔄 Iterative development",
                        "⏰ Agile methodology",
                        "📜 Full ownership transfer",
                        "🔒 Industry-standard security"
                    ],
                    'summary' => "We're excited about your project! Our team specializes in custom solutions that deliver real business value.",
                    'questions' => [
                        "Specific business problem to solve?",
                        "Primary end users & stakeholders?",
                        "Key success metrics?",
                        "Existing system integrations?"
                    ]
                ];
        }
    }

    private function formatResponse($data)
    {
        $html = "<div class='response-content'>";
        
        // Quick summary
        $html .= "<div style='margin-bottom: 16px; padding: 12px; background: #f0f9ff; border-radius: 8px; font-size: 14px; color: #0f766e;'>";
        $html .= "<strong>🎯 Quick Summary:</strong> " . count($data['features']) . " key features with " . count($data['technologies']) . " tech components.";
        $html .= "</div>";

        // Advice
        $html .= "<div style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 8px;'>💡 Project Recommendations</h3>";
        $html .= "<p style='line-height: 1.6; color: #4b5563;'>" . $data['advice'] . "</p>";
        $html .= "</div>";

        // Timeline
        $html .= "<div style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 8px;'>⏱️ Development Timeline</h3>";
        $html .= "<p style='font-size: 16px; font-weight: 500; color: #374151;'>" . $data['duration'] . "</p>";
        $html .= "</div>";

        // Features
        $html .= "<div style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>✨ Recommended Features</h3>";
        $html .= "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px;'>";
        foreach ($data['features'] as $feature) {
            $html .= "<div style='background: #f8fafc; padding: 12px; border-radius: 8px; border-left: 3px solid #10b981;'>";
            $html .= "<div style='font-weight: 500; color: #047857;'>" . $feature . "</div>";
            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        // Technologies
        $html .= "<div style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>🔧 Technology Stack</h3>";
        $html .= "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;'>";
        foreach ($data['technologies'] as $tech) {
            $html .= "<div style='background: #f1f5f9; padding: 10px; border-radius: 6px; border-left: 3px solid #3b82f6;'>";
            $html .= "<div style='font-weight: 500; color: #1e40af;'>" . $tech . "</div>";
            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        // Budget
        $html .= "<div style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>💰 Investment Overview</h3>";
        $html .= "<div style='background: #f8fafc; padding: 16px; border-radius: 8px; border-left: 4px solid #f59e0b; font-size: 16px;'>";
        $html .= $data['budget'];
        $html .= "</div>";
        $html .= "</div>";

        // Terms
        $html .= "<div style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>📋 Project Terms</h3>";
        $html .= "<ul style='list-style: none; padding: 0;'>";
        foreach ($data['terms'] as $term) {
            $html .= "<li style='margin-bottom: 8px; padding: 8px; background: #f1f5f9; border-radius: 6px; border-left: 3px solid #6b7280;'>";
            $html .= "<span style='font-weight: 500; color: #374151;'>" . $term . "</span>";
            $html .= "</li>";
        }
        $html .= "</ul>";
        $html .= "</div>";

        // Summary
        $html .= "<div style='margin-bottom: 20px; background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 5px solid #007cba;'>";
        $html .= "<h3 style='color: #1e40af; margin-bottom: 12px;'>📋 Executive Summary</h3>";
        $html .= "<p style='font-size: 16px; font-weight: 600; color: #1e40af; line-height: 1.6;'>" . $data['summary'] . "</p>";
        $html .= "</div>";

        // Questions
        $html .= "<div>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>❓ Next Steps</h3>";
        $html .= "<p style='color: #6b7280; margin-bottom: 12px;'>Consider these important questions:</p>";
        $html .= "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px;'>";
        foreach ($data['questions'] as $question) {
            $html .= "<div style='background: #f8fafc; padding: 12px; border-radius: 8px; border-left: 3px solid #8b5cf6; cursor: pointer;' onclick='navigator.clipboard.writeText(\"" . addslashes($question) . "\");this.style.background=\"#e879f9\";setTimeout(()=>this.style.background=\"#f8fafc\",1000);'>";
            $html .= "<div style='font-weight: 500; color: #7c3aed; margin-bottom: 4px;'>" . $question . "</div>";
            $html .= "<div style='font-size: 12px; color: #9ca3af;'>Click to copy</div>";
            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        $html .= "</div>";
        return $html;
    }
}