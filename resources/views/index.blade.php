<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Software Help Chatbot</title>
    
    @vite(['resources/css/chatbot.css', 'resources/js/app.js'])
</head>
<body>
    <div class="container">
        <!-- Header Section -->
        <div class="header">
            <h1>🤖 Software Project Help Assistant</h1>
            <p>Describe your project requirements and get professional recommendations instantly!</p>
        </div>

        <!-- Form Section -->
        <div class="form-section">
            <h2>Tell us about your project</h2>
            
            @if (session('success'))
                <div class="success-message">
                    {{ session('success') }}
                </div>
            @endif
            
            <form method="POST" action="{{ route('home.process') }}" id="chatbot-form">
                @csrf
                <label for="user_input">Project Requirements:</label>
                <textarea 
                    name="user_input" 
                    id="user_input" 
                    placeholder="Example: I need to make a student exam system to manage student details, exam information, scheduling, and results..."
                    required
                >{{ old('user_input', $user_input) }}</textarea>
                <br><br>
                <button type="submit" class="btn" id="submit-btn">
                    <span id="btn-text">🚀 Get Professional Suggestions</span>
                    <span id="loading-spinner" class="loading" style="display: none;"></span>
                </button>
            </form>
            
            @error('user_input')
                <div style="color: #dc3545; margin-top: 10px; font-size: 14px;">
                    {{ $message }}
                </div>
            @enderror
        </div>

        <!-- Response Section -->
        @if($response)
            <div class="response-section">
                <h2>📊 Professional Project Analysis</h2>
                <div id="response">
                    {!! $response !!}
                </div>
                
                <!-- Action Buttons -->
                <div style="margin-top: 30px; text-align: center;">
                    <a href="{{ route('home') }}" class="btn" style="margin-right: 10px; text-decoration: none;">
                        🔄 New Project
                    </a>
                    <button onclick="printResponse()" class="btn" style="background: #28a745;">
                        🖨️ Print Report
                    </button>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        // Form submission with loading state
        document.getElementById('chatbot-form').addEventListener('submit', function() {
            const btn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const spinner = document.getElementById('loading-spinner');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'inline-block';
            btnText.textContent = 'Processing your request...';
        });

        // Print function
        function printResponse() {
            const responseContent = document.getElementById('response').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Project Analysis Report</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; line-height: 1.6; }
                            h1, h2, h3 { color: #333; }
                            ul { margin: 10px 0; padding-left: 20px; }
                            li { margin: 5px 0; }
                            strong { color: #007cba; }
                        </style>
                    </head>
                    <body>
                        <h1>Project Analysis Report</h1>
                        <div>${responseContent}</div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Auto-resize textarea
        document.getElementById('user_input').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    </script>
    @endpush
</body>
</html>