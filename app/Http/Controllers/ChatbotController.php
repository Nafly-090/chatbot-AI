<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class ChatbotController extends Controller
{
    /**
     * Display the main chat interface
     */
    public function index()
    {
        $user_input = session('user_input', '');
        $response = session('response', '');
        $error = session('error', '');
        $conversation = session('conversation', []);

        // Format timestamps for view
        $formattedConversation = collect($conversation)->map(function($exchange) {
            $exchange['formatted_time'] = Carbon::parse($exchange['timestamp'])->format('H:i');
            return $exchange;
        })->toArray();

        return view('index', [
            'user_input' => $user_input,
            'response' => $response,
            'error' => $error,
            'conversation' => $formattedConversation
        ]);
    }

    /**
     * Process user input and generate AI response
     * Supports both AJAX (real-time) and traditional form submissions
     */
    public function process(Request $request)
    {
        // Check if it's an AJAX request
        $isAjax = $request->ajax() || $request->wantsJson();
        
        // Get conversation history from session
        $conversation = session('conversation', []) ?? [];

        // Enhanced validation with custom rules
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
                    
                    // Fail if too generic or just numbers/symbols
                    if ($word_count < 3 || count($meaningful_words) < 2 || ctype_digit(trim($value))) {
                        $fail('Please provide more details about your project (at least 3 meaningful words). For example: "I need a student exam system to manage details."');
                    }

                    // Check if this is a follow-up question
                    $is_followup = false;
                    $followup_keywords = ['more', 'details', 'how', 'what', 'when', 'budget', 'timeline', 'features', 'technology', 'integration', 'cost', 'time', 'mobile', 'web'];
                    foreach ($followup_keywords as $keyword) {
                        if (stripos($value, $keyword) !== false) {
                            $is_followup = true;
                            break;
                        }
                    }
                    
                    // If not a follow-up and conversation exists, guide user
                    if (!$is_followup && count($conversation) > 0) {
                        $context = end($conversation)['project_type'] ?? 'project';
                        $fail("Great! Regarding your {$context}, what specific aspect would you like to discuss? (budget, features, timeline, mobile app, etc.)");
                    }
                },
            ],
        ], [
            'user_input.required' => 'I need something to work with! Please describe your project.',
            'user_input.min' => "That's a bit too short—tell me more about your project (at least 10 characters) so I can give you great suggestions!",
            'user_input.max' => 'Whoa, that\'s quite detailed! Keep it under 1000 characters for now.',
            'user_input.string' => 'Please enter text only—no files or links yet.'
        ]);

        if ($validator->fails()) {
            if ($isAjax) {
                // Return JSON error for AJAX
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first('user_input')
                ], 422);
            }
            
            // Fallback for traditional form submission
            return redirect()->route('home')
                ->withErrors($validator)
                ->withInput()
                ->with('error', $validator->errors()->first('user_input'));
        }

        $user_input = $request->input('user_input');
        $response = $this->generateResponse($user_input, $conversation);
        
        // Detect project type for conversation tracking
        $project_type = $this->detectProjectType($user_input);
        
        // Update conversation history
        $conversation[] = [
            'user_message' => $user_input,
            'ai_response' => $response,
            'timestamp' => now(),
            'project_type' => $project_type
        ];

        // Keep only last 10 exchanges to prevent session bloat
        if (count($conversation) > 10) {
            $conversation = array_slice($conversation, -10);
        }
        
        // Store updated conversation in session
        session(['conversation' => $conversation]);

        if ($isAjax) {
            // Return JSON response for real-time chat
            return response()->json([
                'success' => true,
                'response' => $response,
                'project_type' => $project_type,
                'timestamp' => now()->format('H:i'),
                'conversation_count' => count($conversation)
            ]);
        }

        // Fallback for traditional form submission
        return redirect()->route('home')
            ->with('response', $response)
            ->with('user_input', $user_input)
            ->with('conversation', $conversation)
            ->with('success', 'Thanks for sharing! Here\'s my analysis and recommendations.');
    }

    /**
     * Detect the type of project based on user input
     */
    private function detectProjectType($user_input)
    {
        $tokens = explode(' ', strtolower($user_input));
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'i', 'you', 'it', 'he', 'she', 'we', 'they', 'is', 'are', 'was', 'were'];
        $tokens = array_diff($tokens, $stop_words);
        $tokens = array_filter($tokens, function($token) {
            return strlen($token) > 2;
        });

        // Student Exam System
        if ((in_array('student', $tokens) || in_array('students', $tokens)) && 
            (in_array('exam', $tokens) || in_array('test', $tokens) || in_array('quiz', $tokens) || in_array('assessment', $tokens))) {
            return "student_exam_system";
        }
        
        // Inventory Management
        elseif ((in_array('inventory', $tokens) || in_array('stock', $tokens) || in_array('warehouse', $tokens) || in_array('supply', $tokens)) && 
                (in_array('manage', $tokens) || in_array('track', $tokens) || in_array('management', $tokens))) {
            return "inventory_system";
        }
        
        // E-commerce
        elseif ((in_array('ecommerce', $tokens) || in_array('e-commerce', $tokens) || in_array('shop', $tokens) || in_array('store', $tokens) || in_array('online', $tokens)) && 
                (in_array('sell', $tokens) || in_array('buy', $tokens) || in_array('cart', $tokens) || in_array('payment', $tokens))) {
            return "ecommerce_system";
        }
        
        // Blog/Content Management
        elseif ((in_array('blog', $tokens) || in_array('website', $tokens) || in_array('content', $tokens)) && 
                (in_array('management', $tokens) || in_array('cms', $tokens) || in_array('publish', $tokens))) {
            return "blog_system";
        }
        
        // Default general project
        return "general_project";
    }

    /**
     * Generate appropriate AI response based on input and conversation context
     */
    private function generateResponse($user_input, $conversation)
    {
        $word_count = str_word_count($user_input);
        
        // If very short and we have conversation history, provide follow-up response
        if ($word_count < 5 && count($conversation) > 0) {
            return $this->getFollowupResponse($user_input, $conversation);
        }

        // Detect project type and generate full response
        $project_type = $this->detectProjectType($user_input);
        $response_data = $this->getProjectResponse($project_type, $user_input, $conversation);
        
        return $this->formatResponse($response_data);
    }

    /**
     * Generate contextual follow-up response for ongoing conversations
     */
    private function getFollowupResponse($user_input, $conversation)
    {
        $last_exchange = end($conversation);
        $project_type = $last_exchange['project_type'] ?? 'project';
        
        $followup_responses = [
            "student_exam_system" => [
                "Looking to dive deeper into your exam system? Tell me about specific features like question types, grading algorithms, or integration needs!",
                "Great follow-up! For your exam system, are you most interested in the technical implementation, user experience design, or deployment strategy?",
                "Perfect! Regarding the student exam platform, would you like to discuss mobile app development, admin dashboard features, or security requirements?"
            ],
            "inventory_system" => [
                "Excellent! Regarding your inventory solution, would you like to explore barcode integration, multi-warehouse setup, or reporting requirements?",
                "Perfect! For inventory management, should we discuss real-time tracking capabilities, supplier integration, or mobile access needs?",
                "Nice! For your inventory system, are you thinking about IoT integration, predictive analytics, or custom reporting dashboards?"
            ],
            "ecommerce_system" => [
                "Wonderful! For your online store, are you thinking about payment gateway options, shipping integrations, or marketing automation features?",
                "Nice! Regarding e-commerce, would you like to explore product catalog management, customer experience optimization, or SEO strategies?",
                "Great! For your e-commerce platform, should we discuss subscription models, international shipping, or advanced analytics?"
            ],
            "blog_system" => [
                "Excellent! For your content platform, would you like to explore SEO optimization, social media integration, or monetization strategies?",
                "Perfect! Regarding your blog system, are you interested in multi-author workflows, newsletter automation, or performance analytics?",
                "Nice follow-up! For your content management system, should we discuss custom themes, membership features, or API integrations?"
            ],
            "general_project" => [
                "Happy to continue! What specific aspect of your project would you like to explore next? Technology choices, timeline planning, or budget considerations?",
                "Great to keep the conversation going! Should we dive into architecture decisions, team requirements, or scalability planning?",
                "Perfect! Regarding your software project, would you like to discuss development methodology, deployment strategy, or maintenance planning?"
            ]
        ];

        $responses = $followup_responses[$project_type] ?? $followup_responses['general_project'];
        $response = $responses[array_rand($responses)];
        
        $quick_options = [
            'Tell me more about the budget breakdown',
            'What technologies do you recommend for this?',
            'How long will the development process take?',
            'Should I consider mobile app development too?',
            'What about integration with existing systems?',
            'Can you explain the technical architecture?'
        ];

        return "<div class='followup-response'>
            <div style='margin-bottom: 12px; padding: 8px 12px; background: #f0f9ff; border-radius: 6px; border-left: 3px solid #0ea5e9; font-size: 14px;'>
                <strong>💬 Continuing our discussion about your {$project_type}...</strong>
            </div>
            <p style='margin-bottom: 8px;'><strong>You:</strong> " . htmlspecialchars($user_input) . "</p>
            <p style='margin-bottom: 16px; font-style: italic; color: #374151;'><em>" . $response . "</em></p>
            <div style='margin-top: 16px; padding: 12px; background: #f8fafc; border-radius: 8px; border-left: 3px solid #3b82f6;'>
                <strong>💡 Quick follow-up questions you might ask:</strong><br>
                " . implode('<br>', array_map(fn($option) => "• '$option'", $quick_options)) . "
            </div>
        </div>";
    }

    /**
     * Get project-specific response data
     */
    private function getProjectResponse($project_type, $user_input, $conversation = [])
    {
        $base_response = $this->getBaseProjectResponse($project_type);
        
        // Add conversation context if available
        if (!empty($conversation)) {
            $last_project = end($conversation)['project_type'] ?? null;
            if ($last_project && $last_project !== $project_type) {
                $base_response['advice'] = "Building on our previous discussion about your {$last_project}, I see you're now exploring {$project_type}. This is a great way to compare different solutions! Here's my analysis for your current request:";
            }
        }
        
        // Add user input context to advice
        $base_response['advice'] .= " Based on your description: <em>'" . htmlspecialchars(substr($user_input, 0, 100)) . (strlen($user_input) > 100 ? '...' : '') . "'</em>";
        
        return $base_response;
    }

    /**
     * Get base project response templates
     */
    private function getBaseProjectResponse($project_type)
    {
        switch ($project_type) {
            case "student_exam_system":
                return [
                    'advice' => "Excellent choice! A student exam system is crucial for modern educational institutions. Our recommendation is to prioritize data security, user experience, and scalability from the start.",
                    'duration' => "📅 Development Timeline: 4-8 weeks (including testing and deployment)",
                    'features' => [
                        "🔐 Secure user authentication (students, teachers, administrators)",
                        "📚 Comprehensive student profile management",
                        "📋 Advanced exam scheduling with automated reminders",
                        "🧠 Intelligent question bank with categorization",
                        "⚡ Real-time auto-grading and result calculation",
                        "📊 Interactive dashboards and performance analytics",
                        "📱 Mobile-responsive design for all devices",
                        "💾 Automated backups and data export capabilities",
                        "🔍 Advanced search and filtering system",
                        "📧 Integration with email/SMS notification systems"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x with PHP 8.3+",
                        "Frontend: React.js with Tailwind CSS (via Laravel Breeze)",
                        "Database: MySQL 8.0 with Redis caching",
                        "Mobile: Flutter 3.x for native iOS/Android apps",
                        "Deployment: Laravel Forge with AWS or DigitalOcean",
                        "Testing: PHPUnit with Laravel Dusk for E2E testing"
                    ],
                    'budget' => "💰 Investment Range: $1,200 - $3,500<br><small>(Based on features and team size - includes 3 months support)</small>",
                    'terms' => [
                        "💳 Payment: 40% upfront, 30% at milestone delivery, 30% on launch",
                        "🔄 Revisions: 3 rounds of revisions included",
                        "⏰ Timeline: Fixed delivery date with progress tracking",
                        "📜 Ownership: Full source code ownership upon final payment",
                        "🔒 Security: GDPR compliant with data encryption",
                        "📞 Support: 90 days free technical support post-launch"
                    ],
                    'summary' => "A comprehensive student exam system will revolutionize your institution's assessment process. Our Laravel + React solution ensures scalability, security, and exceptional user experience.",
                    'questions' => [
                        "🎯 What specific assessment types do you need to support?",
                        "📊 Do you require integration with existing student information systems?",
                        "💻 Would you prefer a web-only solution or mobile apps as well?",
                        "🕒 What is your target launch date?",
                        "💼 Do you need ongoing maintenance and support services?"
                    ]
                ];

            case "inventory_system":
                return [
                    'advice' => "Inventory management is the backbone of efficient operations. We recommend implementing real-time tracking and automated alerts to minimize stock discrepancies.",
                    'duration' => "📅 Development Timeline: 6-10 weeks (including warehouse integration)",
                    'features' => [
                        "📦 Complete product catalog with multi-media support",
                        "📊 Real-time stock level monitoring with critical alerts",
                        "📋 Automated purchase order generation",
                        "💳 Integrated sales and POS system",
                        "📱 Barcode and QR code scanning capabilities",
                        "📈 Advanced analytics and forecasting tools",
                        "🏢 Multi-warehouse and location management",
                        "🔐 Role-based access control for different users",
                        "📊 Customizable reporting and export functions",
                        "⚡ API integration for third-party systems"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x with Queue system for background jobs",
                        "Frontend: Vue.js 3.x with Composition API",
                        "Database: PostgreSQL with TimescaleDB extension",
                        "Mobile: React Native with offline-first architecture",
                        "Hardware: Barcode scanner SDK integration",
                        "Real-time: Laravel Echo with Pusher WebSockets"
                    ],
                    'budget' => "💰 Investment Range: $2,000 - $5,000<br><small>(Includes hardware integration and 6 months support)</small>",
                    'terms' => [
                        "💳 Payment: 30% upfront, 40% at beta, 30% on production",
                        "🔄 Revisions: Unlimited minor changes during development",
                        "⏰ Timeline: Agile development with bi-weekly sprints",
                        "📜 Ownership: Complete codebase transfer on completion",
                        "🔒 Security: PCI DSS compliant for payment processing",
                        "📞 Support: 6 months priority support included"
                    ],
                    'summary' => "Transform your inventory operations with our comprehensive management system. Real-time tracking and intelligent automation will optimize your supply chain efficiency.",
                    'questions' => [
                        "📦 What types of products do you need to track?",
                        "🏢 Do you operate multiple warehouse locations?",
                        "📱 Do you need mobile scanning capabilities for staff?",
                        "💻 Are there existing systems that need integration?",
                        "📊 What key performance indicators matter most to you?"
                    ]
                ];

            case "ecommerce_system":
                return [
                    'advice' => "E-commerce success depends on conversion rates and customer experience. We recommend focusing on mobile-first design and seamless checkout flows.",
                    'duration' => "📅 Development Timeline: 8-14 weeks (including payment gateway setup)",
                    'features' => [
                        "🛍️ Advanced product catalog with variant support",
                        "🛒 Intelligent shopping cart with abandoned cart recovery",
                        "💳 Multiple payment gateway integrations (Stripe, PayPal, etc.)",
                        "🚚 Real-time shipping calculation and tracking",
                        "⭐ Customer review and rating system",
                        "🔍 Advanced search with autocomplete and filters",
                        "📧 Marketing automation and email campaigns",
                        "📊 Complete analytics and conversion tracking",
                        "🔐 Enterprise-grade security and SSL encryption",
                        "📱 Progressive Web App (PWA) capabilities"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x with Laravel Cashier",
                        "Frontend: Next.js 14 with App Router",
                        "Database: MySQL with Elasticsearch for search",
                        "Payments: Stripe Connect with fraud detection",
                        "CDN: Cloudflare for global content delivery",
                        "Analytics: Google Analytics 4 integration"
                    ],
                    'budget' => "💰 Investment Range: $3,500 - $8,000<br><small>(Scalable pricing based on expected monthly revenue)</small>",
                    'terms' => [
                        "💳 Payment: 25% upfront, 50% at soft launch, 25% final",
                        "🔄 Revisions: A/B testing included for key pages",
                        "⏰ Timeline: Phased rollout with MVP in 6 weeks",
                        "📜 Ownership: Full ownership with source code escrow",
                        "🔒 Security: SOC 2 Type II compliant architecture",
                        "📞 Support: 12 months dedicated account management"
                    ],
                    'summary' => "Launch a high-converting e-commerce platform that scales with your business. Our solution combines cutting-edge technology with proven UX principles for maximum ROI.",
                    'questions' => [
                        "🛍️ What is your target average order value?",
                        "🌍 Do you plan international expansion?",
                        "💳 Which payment methods are essential for your customers?",
                        "📱 Do you need a mobile app in addition to web?",
                        "📈 What are your key performance metrics for success?"
                    ]
                ];

            case "blog_system":
                return [
                    'advice' => "A blog system needs to balance content management with reader engagement. Focus on SEO optimization and social sharing features from the start.",
                    'duration' => "📅 Development Timeline: 3-6 weeks (content-focused solution)",
                    'features' => [
                        "✍️ Rich text editor with media embedding",
                        "📂 Category and tag management system",
                        "👥 Multi-author support with user profiles",
                        "📧 Newsletter subscription and email integration",
                        "🔍 Advanced search with full-text indexing",
                        "📊 Content analytics and performance tracking",
                        "🔗 SEO optimization with meta tags and sitemaps",
                        "📱 Mobile-optimized responsive design",
                        "💬 Comment system with moderation tools",
                        "📱 Social media sharing and embed capabilities"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x with Eloquent ORM",
                        "Frontend: Livewire for dynamic content without JavaScript",
                        "Database: MySQL with full-text search indexes",
                        "Content: Laravel Filament for admin panel",
                        "SEO: Laravel SEO package integration",
                        "Email: Laravel Mail with Mailgun/SendGrid"
                    ],
                    'budget' => "💰 Investment Range: $800 - $2,500<br><small>(Scales with content volume and advanced features)</small>",
                    'terms' => [
                        "💳 Payment: 50% upfront, 50% on content migration completion",
                        "🔄 Revisions: Content structure changes included",
                        "⏰ Timeline: Rapid development with content import planning",
                        "📜 Ownership: Full CMS ownership with training included",
                        "🔒 Security: Content security and spam protection",
                        "📞 Support: 60 days content management training"
                    ],
                    'summary' => "Create an engaging blog platform that grows your audience. Our Laravel-based solution provides robust content management with excellent SEO performance.",
                    'questions' => [
                        "✍️ How many authors will contribute to the blog?",
                        "📊 Do you need advanced analytics for content performance?",
                        "🔗 Are there existing articles that need migration?",
                        "📧 Do you plan email newsletter campaigns?",
                        "💰 What is your content monetization strategy?"
                    ]
                ];

            default: // general_project
                return [
                    'advice' => "Based on your description, this appears to be a custom software solution. To provide more specific recommendations, please include details about your industry, target users, and key objectives.",
                    'duration' => "📅 Development Timeline: 4-12 weeks (custom assessment required)",
                    'features' => [
                        "👥 User authentication and role management",
                        "📊 Data management with full CRUD operations",
                        "📱 Fully responsive design across all devices",
                        "📈 Built-in analytics and reporting tools",
                        "🔔 Real-time notifications and alerts",
                        "🔍 Advanced search and filtering capabilities",
                        "🔐 Enterprise-grade security implementation",
                        "📱 Mobile-first responsive architecture",
                        "⚡ Performance optimization and caching",
                        "🔌 API-ready architecture for future integrations"
                    ],
                    'technologies' => [
                        "Backend: Laravel 12.x (PHP 8.3+)",
                        "Frontend: Modern JavaScript framework (React/Vue)",
                        "Database: MySQL/PostgreSQL with Redis caching",
                        "API: RESTful APIs with Laravel Sanctum",
                        "Deployment: Docker containers with CI/CD pipeline",
                        "Testing: Comprehensive unit and integration tests"
                    ],
                    'budget' => "💰 Investment Range: $1,000 - $6,000<br><small>(Preliminary estimate - detailed quote after requirements analysis)</small>",
                    'terms' => [
                        "💳 Payment: Flexible terms based on project scope",
                        "🔄 Revisions: Iterative development with client feedback",
                        "⏰ Timeline: Agile methodology with sprint planning",
                        "📜 Ownership: Full transfer upon project completion",
                        "🔒 Security: Industry-standard security practices",
                        "📞 Support: 60 days free support post-launch"
                    ],
                    'summary' => "We're excited about your project vision! Our team specializes in custom software solutions that deliver measurable business value. Let's schedule a detailed requirements discussion.",
                    'questions' => [
                        "🎯 What specific business problem are you trying to solve?",
                        "👥 Who are your primary end users and stakeholders?",
                        "📊 What key metrics will define project success?",
                        "💻 Do you have existing systems that need integration?",
                        "🕒 What is your ideal timeline for project completion?"
                    ]
                ];
        }
    }

    /**
     * Format the AI response with HTML structure
     */
    private function formatResponse($data)
    {
        $html = "<div class='response-content'>";
        
        // Quick summary at the top
        $html .= "<div style='margin-bottom: 16px; padding: 12px; background: #f0f9ff; border-radius: 8px; font-size: 14px; color: #0f766e;'>";
        $html .= "<strong>🎯 Quick Summary:</strong> Based on your description, I recommend a comprehensive solution with " . count($data['features']) . " key features and " . count($data['technologies']) . " technology components.";
        $html .= "</div>";

        // Advice Section
        $html .= "<div class='advice-section' style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 8px;'>💡 Project Recommendations</h3>";
        $html .= "<p style='line-height: 1.6; color: #4b5563;'>" . $data['advice'] . "</p>";
        $html .= "</div>";

        // Duration Section
        $html .= "<div class='duration-section' style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 8px;'>⏱️ Development Timeline</h3>";
        $html .= "<p style='font-size: 16px; font-weight: 500; color: #374151;'>" . $data['duration'] . "</p>";
        $html .= "</div>";

        // Features Section
        $html .= "<div class='features-section' style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>✨ Recommended Features</h3>";
        $html .= "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px;'>";
        foreach ($data['features'] as $feature) {
            $html .= "<div style='background: #f8fafc; padding: 12px; border-radius: 8px; border-left: 3px solid #10b981;'>";
            $html .= "<div style='font-weight: 500; color: #047857; margin-bottom: 4px;'>" . $feature . "</div>";
            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        // Technologies Section
        $html .= "<div class='tech-section' style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>🔧 Technology Stack</h3>";
        $html .= "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px;'>";
        foreach ($data['technologies'] as $tech) {
            $html .= "<div style='background: #f1f5f9; padding: 10px; border-radius: 6px; border-left: 3px solid #3b82f6;'>";
            $html .= "<div style='font-weight: 500; color: #1e40af;'>" . $tech . "</div>";
            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        // Budget Section
        $html .= "<div class='budget-section' style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>💰 Investment Overview</h3>";
        $html .= "<div style='background: #f8fafc; padding: 16px; border-radius: 8px; border-left: 4px solid #f59e0b; font-size: 16px;'>";
        $html .= $data['budget'];
        $html .= "</div>";
        $html .= "</div>";

        // Terms Section
        $html .= "<div class='terms-section' style='margin-bottom: 20px;'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>📋 Project Terms & Conditions</h3>";
        $html .= "<ul style='list-style: none; padding: 0;'>";
        foreach ($data['terms'] as $term) {
            $html .= "<li style='margin-bottom: 8px; padding: 8px; background: #f1f5f9; border-radius: 6px; border-left: 3px solid #6b7280;'>";
            $html .= "<span style='font-weight: 500; color: #374151;'>" . $term . "</span>";
            $html .= "</li>";
        }
        $html .= "</ul>";
        $html .= "</div>";

        // Summary Section
        $html .= "<div class='summary-section' style='margin-bottom: 20px; background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 5px solid #007cba;'>";
        $html .= "<h3 style='color: #1e40af; margin-bottom: 12px;'>📋 Executive Summary</h3>";
        $html .= "<p style='font-size: 16px; font-weight: 600; color: #1e40af; line-height: 1.6;'>" . $data['summary'] . "</p>";
        $html .= "</div>";

        // Questions Section
        $html .= "<div class='questions-section'>";
        $html .= "<h3 style='color: #1f2937; margin-bottom: 12px;'>❓ Next Steps & Questions</h3>";
        $html .= "<p style='color: #6b7280; margin-bottom: 12px;'>Consider these important questions for your project planning:</p>";
        $html .= "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px;'>";
        foreach ($data['questions'] as $question) {
            $html .= "<div style='background: #f8fafc; padding: 12px; border-radius: 8px; border-left: 3px solid #8b5cf6; cursor: pointer; transition: all 0.2s ease;' onclick='navigator.clipboard.writeText(\"" . addslashes($question) . "\"); this.style.background=\"#e879f9\"; setTimeout(() => this.style.background=\"#f8fafc\", 1000);'>";
            $html .= "<div style='font-weight: 500; color: #7c3aed; margin-bottom: 4px;'>" . $question . "</div>";
            $html .= "<div style='font-size: 12px; color: #9ca3af;'>Click to copy</div>";
            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        $html .= "</div>"; // Close response-content

        return $html;
    }
}