<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChatbotController extends Controller
{
  public function index()
    {
        return view('index', [
            'response' => session('response', ''), 
            'user_input' => session('user_input', '')
        ]);
    }

    public function process(Request $request)
    {
        $request->validate([
            'user_input' => 'required|string|max:1000'
        ]);

        $user_input = $request->input('user_input');
        $response = $this->generateResponse($user_input);
        
        return redirect()->route('home')
            ->with('response', $response)
            ->with('user_input', $user_input)
            ->with('success', 'Analysis complete! Here are your professional recommendations.');
    }

    private function generateResponse($user_input)
    {
        if (trim($user_input) === '') {
            return "<div class='error-message'><strong>⚠️ Please enter your project requirements!</strong></div>";
        }

        // Simple tokenization: split into words (lowercase)
        $tokens = explode(' ', strtolower($user_input));

        // Remove common stop words for better matching
        $stop_words = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'i', 'you', 'it', 'he', 'she', 'we', 'they', 'is', 'are', 'was', 'were', 'have', 'has', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'must'];
        $tokens = array_diff($tokens, $stop_words);
        $tokens = array_filter($tokens, function($token) {
            return strlen($token) > 2; // Ignore very short words
        });

        // Detect project type based on keywords
        $project_type = "general";
        if ((in_array('student', $tokens) || in_array('students', $tokens)) && 
            (in_array('exam', $tokens) || in_array('test', $tokens) || in_array('quiz', $tokens))) {
            $project_type = "student_exam_system";
        } elseif (in_array('inventory', $tokens) || in_array('stock', $tokens) || in_array('warehouse', $tokens)) {
            $project_type = "inventory_system";
        } elseif (in_array('ecommerce', $tokens) || in_array('shop', $tokens) || in_array('store', $tokens) || in_array('online', $tokens) && in_array('sell', $tokens)) {
            $project_type = "ecommerce_system";
        } elseif (in_array('blog', $tokens) || in_array('content', $tokens) && in_array('management', $tokens)) {
            $project_type = "blog_system";
        }

        // Generate response based on project type
        $response_data = $this->getProjectResponse($project_type, $user_input);
        
        // Format with HTML and CSS classes
        return $this->formatResponse($response_data);
    }

    private function getProjectResponse($project_type, $user_input)
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

        $html .= "</div>";

        return $html;
    }
}
