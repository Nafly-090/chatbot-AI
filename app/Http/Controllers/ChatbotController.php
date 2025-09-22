<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;  
use Illuminate\Support\Facades\Session;


class ChatbotController extends Controller
{
    public function index()
    {
        $user_input = session('user_input', '');
        $response = session('response', '');
        $error = session('error', '');
        $conversation = session('conversation', []);

        return view('index', [
            'user_input' => $user_input,
            'response' => $response,
            'error' => $error,
            'conversation' => $conversation
        ]);
    }

    public function process(Request $request)
    {
        $conversation = session('conversation', []) ?? [];        // Enhanced validation with custom rule

        $validator = Validator::make($request->all(), [
            'user_input' => [
                'required',
                'string',
                'max:1000',
                'min:10',  // Minimum 10 characters
                function ($attribute, $value, $fail) use ($conversation) {
                    $word_count = str_word_count($value);
                    $tokens = explode(' ', strtolower($value));
                    $stop_words = ['a', 'an', 'the', 'i', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
                    $meaningful_words = array_diff($tokens, $stop_words);
                    
                    // Fail if less than 3 meaningful words or too generic (e.g., just numbers/symbols)
                    if ($word_count < 3 || count($meaningful_words) < 2 || ctype_digit(trim($value))) {
                        $fail('Please provide more details about your project (at least 3 words describing what you need). For example: "I need a student exam system to manage details."');
                    }

                    // Check if this is a follow-up question
                    $is_followup = false;
                    $followup_keywords = ['more', 'details', 'how', 'what', 'when', 'budget', 'timeline', 'features', 'technology', 'integration'];
                    foreach ($followup_keywords as $keyword) {
                        if (stripos($value, $keyword) !== false) {
                            $is_followup = true;
                            break;
                        }
                    }
                    
                    if (!$is_followup && count($conversation) > 0) {
                        // Suggest continuing conversation
                        $context = end($conversation)['project_type'] ?? 'project';
                        $fail("Great! Regarding your {$context}, what specific aspect would you like to discuss? (budget, features, timeline, etc.)");
                    }
                },
            ],
        ], [
            'user_input.required' => 'I need something to work with! Please describe your project.',
            'user_input.min' => 'That\'s a bit too short—tell me more about your project (at least 10 characters) so I can give you great suggestions!',
            'user_input.max' => 'Whoa, that\'s a novel! Keep it under 1000 characters for now.',
            'user_input.string' => 'Please enter text only—no files or links yet.'
        ]);

        if ($validator->fails()) {
            // Return error without processing
            return redirect()->route('home')
                ->withErrors($validator)
                ->withInput()
                ->with('error', $validator->errors()->first('user_input'));
        }

        $user_input = $request->input('user_input');
        $response = $this->generateResponse($user_input, $conversation);
        
        // Update conversation history
        $project_type = $this->detectProjectType($user_input);
        $conversation[] = [
            'user_message' => $user_input,
            'ai_response' => $response,
            'timestamp' => now(),
            'project_type' => $project_type
        ];

        // Keep only last 10 exchanges
        if (count($conversation) > 10) {
            $conversation = array_slice($conversation, -10);
        }
        session(['conversation' => $conversation]);
        return redirect()->route('home')
            ->with('response', $response)
            ->with('user_input', $user_input)
            ->with('conversation', $conversation)
            ->with('success', 'Thanks for sharing! Here\'s my analysis and recommendations.');
    }

        private function detectProjectType($user_input)
            {
                $tokens = explode(' ', strtolower($user_input));
                $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'i', 'you', 'it', 'he', 'she', 'we', 'they'];
                $tokens = array_diff($tokens, $stop_words);
                $tokens = array_filter($tokens, fn($token) => strlen($token) > 2);

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


    private function generateResponse($user_input,$conversation)
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
                "Looking to dive deeper into your exam system? Tell me about specific features like question types, grading algorithms, or integration needs!",
                "Great follow-up! For your exam system, are you most interested in the technical implementation, user experience design, or deployment strategy?"
            ],
            "inventory_system" => [
                "Excellent! Regarding your inventory solution, would you like to explore barcode integration, multi-warehouse setup, or reporting requirements?",
                "Perfect! For inventory management, should we discuss real-time tracking capabilities, supplier integration, or mobile access needs?"
            ],
            "ecommerce_system" => [
                "Wonderful! For your online store, are you thinking about payment gateway options, shipping integrations, or marketing automation features?",
                "Nice! Regarding e-commerce, would you like to explore product catalog management, customer experience optimization, or SEO strategies?"
            ],
            "general_project" => [
                "Happy to continue! What specific aspect of your project would you like to explore next? Technology choices, timeline planning, or budget considerations?",
                "Great to keep the conversation going! Should we dive into architecture decisions, team requirements, or scalability planning?"
            ]
        ];

        $responses = $followup_responses[$project_type] ?? $followup_responses['general_project'];
        $response = $responses[array_rand($responses)];
        
        return "<div class='followup-response'>
            <h3>💬 Continuing Our Conversation</h3>
            <p><strong>You:</strong> " . htmlspecialchars($user_input) . "</p>
            <p><em>{$response}</em></p>
            <div style='margin-top: 16px; padding: 12px; background: #f0f9ff; border-radius: 8px; border-left: 3px solid #0ea5e9;'>
                <strong>💡 Quick Options:</strong><br>
                • 'Tell me more about the budget breakdown'<br>
                • 'What technologies do you recommend?'<br>
                • 'How long will development take?'<br>
                • 'What about mobile app development?'
            </div>
        </div>";
    }

    private function getVagueInputResponse($user_input)
    {
        // AI-like response for vague/short inputs
        $examples = [
            "I need a student exam system to manage details and results.",
            "Build an inventory system for tracking stock and orders.",
            "Create an e-commerce store with payment integration.",
            "Develop a blog platform for content management."
        ];

        $html = "<div class='vague-response'>";
        $html .= "<h3>🤔 Let's Make This More Specific!</h3>";
        $html .= "<p>Hi there! Your input ('" . htmlspecialchars($user_input) . "') is a bit too brief for me to provide detailed recommendations. I need more context to give you accurate advice on features, technologies, and estimates.</p>";
        $html .= "<p><strong>Why more details help:</strong> It lets me tailor suggestions to your exact needs—like whether it's for web, mobile, or a specific industry.</p>";
        $html .= "<h4>💡 Quick Tips for Better Input:</h4>";
        $html .= "<ul>";
        $html .= "<li>Describe the main goal (e.g., 'manage student exams').</li>";
        $html .= "<li>Mention key features (e.g., 'track results, send notifications').</li>";
        $html .= "<li>Specify type (e.g., 'web app' or 'mobile').</li>";
        $html .= "</ul>";
        $html .= "<h4>📝 Try These Examples:</h4>";
        $html .= "<ul>";
        foreach ($examples as $example) {
            $html .= "<li><strong>" . htmlspecialchars($example) . "</strong></li>";
        }
        $html .= "</ul>";
        $html .= "<p>Ready to try again? Paste a more detailed description above and hit submit—I'll analyze it right away! 🚀</p>";
        $html .= "</div>";

        // Add inline CSS for this response
        $css = "<style>
            .vague-response { 
                background: #fff3cd; 
                border-left: 5px solid #ffc107; 
                padding: 25px; 
                border-radius: 8px; 
                margin-top: 20px; 
            } 
            .vague-response h3 { 
                color: #856404; 
                margin-bottom: 15px; 
            } 
            .vague-response h4 { 
                color: #856404; 
                margin-top: 15px; 
                margin-bottom: 10px; 
            }
            .vague-response ul { 
                margin: 10px 0 15px 20px; 
            }
            .vague-response li { 
                margin: 5px 0; 
            }
        </style>";

        return $css . $html;
    }

    private function getProjectResponse($project_type, $user_input,$conversation = [])
    {
        $base_response = $this->getBaseProjectResponse($project_type);
        if (!empty($conversation)) {
            $last_project = end($conversation)['project_type'] ?? null;
            if ($last_project && $last_project !== $project_type) {
                $base_response['advice'] = "Building on our previous discussion about your {$last_project}, I see you're now exploring {$project_type}. This is a great way to compare different solutions!";
            }
        }
        
        return $base_response;

    }

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

                default:
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


    private function formatResponse($data)
    {
        $html = "<div class='response-content'>";
        
        // Advice Section
        $html .= "<div class='advice-section'>";
        $html .= "<h3>💡 Project Recommendations</h3>";
        $html .= "<p>" . $data['advice'] . "</p>";
        $html .= "</div>";

        

        // Duration Section
        $html .= "<div class='duration-section'>";
        $html .= "<h3>⏱️ Development Timeline</h3>";
        $html .= "<p>" . $data['duration'] . "</p>";
        $html .= "</div>";

        // Features Section
        $html .= "<div class='features-section'>";
        $html .= "<h3>✨ Recommended Features</h3>";
        $html .= "<ul>";
        foreach ($data['features'] as $feature) {
            $html .= "<li>" . $feature . "</li>";
        }
        $html .= "</ul>";
        $html .= "</div>";

        // Technologies Section
        $html .= "<div class='tech-section'>";
        $html .= "<h3>🔧 Technology Stack</h3>";
        $html .= "<ul>";
        foreach ($data['technologies'] as $tech) {
            $html .= "<li>" . $tech . "</li>";
        }
        $html .= "</ul>";
        $html .= "</div>";

        // Budget Section
        $html .= "<div class='budget-section'>";
        $html .= "<h3>💰 Investment Overview</h3>";
        $html .= "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #007cba;'>";
        $html .= $data['budget'];
        $html .= "</div>";
        $html .= "</div>";

        // Terms Section
        $html .= "<div class='terms-section'>";
        $html .= "<h3>📋 Project Terms</h3>";
        $html .= "<ul>";
        foreach ($data['terms'] as $term) {
            $html .= "<li>" . $term . "</li>";
        }
        $html .= "</ul>";
        $html .= "</div>";

        // Summary Section
        $html .= "<div class='summary-section' style='background: #e7f3ff; padding: 20px; border-radius: 8px; border-left: 5px solid #007cba;'>";
        $html .= "<h3>📋 Executive Summary</h3>";
        $html .= "<p><strong>" . $data['summary'] . "</strong></p>";
        $html .= "</div>";

        // Questions Section
        $html .= "<div class='questions-section'>";
        $html .= "<h3>❓ Next Steps</h3>";
        $html .= "<p>Consider these important questions for your project planning:</p>";
        $html .= "<ul>";
        foreach ($data['questions'] as $question) {
            $html .= "<li>" . $question . "</li>";
        }
        $html .= "</ul>";
        $html .= "</div>";

        // Add some conversational touches
        $html = "<div class='ai-response'>";
        $html .= "<div style='margin-bottom: 16px; padding: 12px; background: #f0f9ff; border-radius: 8px; font-size: 14px; color: #0f766e;'>
                    <strong>🎯 Quick Summary:</strong> Based on your description, I recommend a {$data['project_type']} with {$data['recommended_features']} key features.
                 </div>";

        $html .= "</div>";

        return $html;
    }
}