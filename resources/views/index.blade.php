<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Project Consultant - {{ config('app.name', 'Laravel') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/chatbot.css', 'resources/js/app.js'])
</head>
<body>
    <div class="chat-container">
        <!-- Chat Header -->
        <div class="chat-header">
            <div class="header-avatar">
                🤖
            </div>
            <div class="header-content">
                <h1>AI Project Consultant</h1>
                <p>Your professional software advisor is ready to help</p>
            </div>
        </div>

        <!-- Messages Container -->
        <div class="messages-container" id="messages-container">
            @if(empty($conversation))
                <!-- Welcome Screen -->
                <div class="welcome-message">
                    <h2>Welcome! I'm your AI Project Consultant 🤖</h2>
                    <p>Tell me about your software project and I'll provide detailed recommendations on features, technology stack, timeline, budget, and next steps. The more details you share, the better I can help!</p>
                    
                    <div class="examples">
                        <button class="example-btn" data-example="I need to build a student exam system to manage student details, exam scheduling, and results">
                            Student Exam System
                        </button>
                        <button class="example-btn" data-example="I want an inventory management system for tracking stock, orders, and suppliers">
                            Inventory Management
                        </button>
                        <button class="example-btn" data-example="Create an e-commerce store with product catalog, shopping cart, and payment integration">
                            E-commerce Store
                        </button>
                        <button class="example-btn" data-example="Develop a blog platform with content management, user authentication, and SEO features">
                            Blog Platform
                        </button>
                    </div>
                </div>
            @else
                <!-- Load Conversation History -->
                @foreach($conversation as $exchange)
                    <!-- User Message -->
                    <div class="message user">
                        <div class="message-content">
                            <div>{{ $exchange['user_message'] }}</div>
                            <div class="message-meta">
                                <span class="message-time">{{ \Carbon\Carbon::parse($exchange['timestamp'])->format('H:i') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- AI Response -->
                    <div class="message ai">
                        <div class="message-content">
                            {!! $exchange['ai_response'] !!}
                            <div class="message-meta">
                                <span class="message-time">{{ \Carbon\Carbon::parse($exchange['timestamp'])->format('H:i') }}</span>
                                <span style="font-size: 11px; opacity: 0.7;">AI Assistant</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>

        <!-- Input Container -->
        <div class="input-container">
            <div class="input-wrapper">
                <div class="input-icons">
                    <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                </div>
                <textarea 
                    id="message-input" 
                    placeholder="Describe your project requirements or ask follow-up questions..."
                    rows="1"
                ></textarea>
            </div>
            <button id="send-button">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                </svg>
            </button>
        </div>
    </div>

    <!-- CSRF Token for AJAX -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @push('scripts')
    <script>

        // Global setExample function for onclick handlers
        function setExample(exampleText) {
            const chatInterface = window.chatInterface;
            if (chatInterface && chatInterface.setExampleInput) {
                chatInterface.setExampleInput(exampleText);
            }
        }

        // Chat functionality with AJAX
        class ChatInterface {
            constructor() {
                this.messagesContainer = document.getElementById('messages-container');
                this.messageInput = document.getElementById('message-input');
                this.sendButton = document.getElementById('send-button');
                this.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                window.chatInterface = this;
                this.init();
            }

            init() {
                // Auto-resize textarea
                this.messageInput.addEventListener('input', (e) => {
                    this.autoResize(e.target);
                    this.toggleSendButton();
                });

                // Send message on Enter (without Shift)
                this.messageInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });

                // Send button click
                this.sendButton.addEventListener('click', () => {
                    this.sendMessage();
                });

                // Example button handlers (updated to use data attributes)
                document.querySelectorAll('.example-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const exampleText = btn.getAttribute('data-example');
                        if (exampleText) {
                            this.setExampleInput(exampleText);
                        }
                    });
                });

                // Scroll to bottom on load
                this.scrollToBottom();
                
                // Focus input if conversation exists
                if (!this.messagesContainer.querySelector('.welcome-message')) {
                    this.messageInput.focus();
                }
            }

            autoResize(textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            }

            toggleSendButton() {
                const isEmpty = this.messageInput.value.trim().length === 0;
                this.sendButton.disabled = isEmpty;
                this.sendButton.style.opacity = isEmpty ? '0.5' : '1';
            }

            setExampleInput(text) {
                this.messageInput.value = `I need to make a ${text} to manage details`;
                this.messageInput.focus();
                this.autoResize(this.messageInput);
                this.toggleSendButton();

                // setTimeout(() => this.sendMessage(), 1000);
            }

            async sendMessage() {
                const message = this.messageInput.value.trim();
                if (!message || this.sendButton.disabled) return;

                // Disable input during processing
                this.messageInput.disabled = true;
                this.sendButton.disabled = true;

                // Add user message
                this.addMessage(message, 'user');

                // Clear input
                this.messageInput.value = '';
                this.autoResize(this.messageInput);

                // Show typing indicator
                const typingIndicator = this.addTypingIndicator();

                // Send AJAX request
                try {
                    const response = await fetch('{{ route("home.process") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            user_input: message
                        })
                    });

                    const data = await response.json();

                    // Remove typing indicator
                    if (typingIndicator && typingIndicator.parentNode) {
                        typingIndicator.remove();
                    }

                    // Re-enable input
                    this.messageInput.disabled = false;
                    this.toggleSendButton();
                    this.messageInput.focus();

                    if (response.ok && data.success) {
                        // Add AI message
                        this.addMessage(data.response, 'ai');
                    } else {
                        // Handle validation errors
                        this.addErrorMessage(data.error || 'Sorry, something went wrong. Please try again.');
                    }
                } catch (error) {
                    console.error('Chat error:', error);
                    
                    // Remove typing indicator
                    if (typingIndicator && typingIndicator.parentNode) {
                        typingIndicator.remove();
                    }

                    // Re-enable input
                    this.messageInput.disabled = false;
                    this.toggleSendButton();
                    this.messageInput.focus();

                    this.addErrorMessage('Connection error. Please check your internet and try again.');
                }
            }

            addMessage(content, sender) {
                // Remove welcome message if present
                const welcomeMessage = this.messagesContainer.querySelector('.welcome-message');
                if (welcomeMessage) {
                    welcomeMessage.remove();
                }

                const messageDiv = document.createElement('div');
                messageDiv.className = `message ${sender}`;
                
                const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                
                messageDiv.innerHTML = `
                    <div class="message-content">
                        <div>${sender === 'user' ? this.formatUserMessage(content) : content}</div>
                        <div class="message-meta">
                            <span class="message-time">${time}</span>
                            ${sender === 'ai' ? '<span style="font-size: 11px; opacity: 0.7;">AI Assistant</span>' : ''}
                        </div>
                    </div>
                `;
                
                this.messagesContainer.appendChild(messageDiv);
                this.scrollToBottom();
                return messageDiv;
            }

            addErrorMessage(message) {
                const errorDiv = this.addMessage(`<div style="color: #ef4444; background: #fef2f2; padding: 8px 12px; border-radius: 6px; border-left: 3px solid #ef4444; margin: 4px 0;">⚠️ ${message}</div>`, 'ai');
                
                // Auto-remove error after 5 seconds
                setTimeout(() => {
                    if (errorDiv && errorDiv.parentNode) {
                        errorDiv.remove();
                    }
                }, 5000);
            }

            addTypingIndicator() {
                const typingDiv = document.createElement('div');
                typingDiv.className = 'typing-indicator';
                typingDiv.innerHTML = `
                    <div style="color: #6b7280; font-size: 14px; margin-right: 8px;">AI is thinking...</div>
                    <div class="typing-dots">
                        <span></span><span></span><span></span>
                    </div>
                `;
                this.messagesContainer.appendChild(typingDiv);
                this.scrollToBottom();
                return typingDiv;
            }

            formatUserMessage(content) {
                return content
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\*(.*?)\*/g, '<em>$1</em>')
                    .replace(/`(.*?)`/g, '<code style="background: rgba(255,255,255,0.2); padding: 2px 4px; border-radius: 3px; font-size: 0.9em;">$1</code>');
            }

            scrollToBottom(animate = true) {
                if (animate) {
                    this.messagesContainer.scrollTo({
                        top: this.messagesContainer.scrollHeight,
                        behavior: 'smooth'
                    });
                } else {
                    this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
                }
            }
        }

        // Initialize chat when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            window.chatInterface = new ChatInterface();
        });

        // Print function for conversation
        function printConversation() {
            window.print();
        }
    </script>
    @endpush
</body>
</html>