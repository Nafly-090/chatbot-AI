
@extends('layouts.app')

@section('content')
    <div class="chat-area-wrapper">
        <!-- Messages -->
        <div class="messages-container" id="messages-container">
            @if(empty($conversation))
                <div class="welcome-message">
                    <h2>Welcome! I'm your AI Project Consultant 🤖</h2>
                    <p>Tell me about your software project and I'll provide detailed recommendations!</p>
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
                        <button class="example-btn" data-example="Develop a blog platform with content management and SEO features">
                            Blog Platform
                        </button>
                    </div>
                </div>
            @else
                @foreach($conversation as $exchange)
                    <div class="message user">
                        <div class="message-content">
                            <div>{{ $exchange['user_message'] }}</div>
                            <div class="message-meta">
                                <span class="message-time">{{ $exchange['formatted_time'] ?? now()->format('H:i') }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="message ai">
                        <div class="message-content">
                            {!! $exchange['ai_response'] !!}
                            <div class="message-meta">
                                <span class="message-time">{{ $exchange['formatted_time'] ?? now()->format('H:i') }}</span>
                                <span style="font-size: 11px; opacity: 0.7;">AI Assistant</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
         @include('prompt-bar')
    </div>

    <!-- CRITICAL: ALL JAVASCRIPT INSIDE BLADE PROCESSING -->
    <script>
        console.log('🔥 BLADE DEBUG START');
        console.log('Route URL:', '{{ route("home") }}');  // Should be http://127.0.0.1:8000/
        console.log('CSRF Token:', document.querySelector('meta[name="csrf-token"]').content);
        console.log('BLADE DEBUG END');

        // Global functions
        window.setExample = function(exampleText) {
            const chat = window.chatInterface;
            if (chat && chat.setExampleInput) {
                chat.setExampleInput(exampleText);
            }
        };

        // Main Chat Class
        class ChatInterface {
            constructor() {
                console.log('🚀 Creating ChatInterface');
                this.messagesContainer = document.getElementById('messages-container');
                this.messageInput = document.getElementById('message-input');
                this.sendButton = document.getElementById('send-button');
                this.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
                
                console.log('Elements:', {
                    input: !!this.messageInput,
                    button: !!this.sendButton,
                    csrf: !!this.csrfToken
                });
                
                window.chatInterface = this;
                this.init();
            }

            init() {
                console.log('⚙️ Initializing...');
                
                // Input events
                this.messageInput.addEventListener('input', (e) => {
                    this.autoResize(e.target);
                    this.toggleSendButton();
                });

                this.messageInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.sendMessage();
                    }
                });

                this.sendButton.addEventListener('click', () => {
                    this.sendMessage();
                });

                // Example buttons
                document.querySelectorAll('.example-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const text = btn.getAttribute('data-example');
                        this.setExampleInput(text);
                    });
                });

                this.toggleSendButton();
                this.scrollToBottom();
                console.log('✅ Initialized');
            }

            autoResize(textarea) {
                textarea.style.height = 'auto';
                textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
            }

            toggleSendButton() {
                const empty = this.messageInput.value.trim().length === 0;
                this.sendButton.disabled = empty;
                this.sendButton.style.opacity = empty ? '0.5' : '1';
            }

            setExampleInput(text) {
                this.messageInput.value = text;
                this.messageInput.focus();
                this.autoResize(this.messageInput);
                this.toggleSendButton();
            }

            async sendMessage() {
                const message = this.messageInput.value.trim();
                if (!message) return;

                // Disable UI
                this.messageInput.disabled = true;
                this.sendButton.disabled = true;

                // Add user message
                this.addMessage(message, 'user');
                this.messageInput.value = '';

                // Show typing
                const typing = this.addTypingIndicator();

                try {
                    // CRITICAL: Use ROOT URL since POST goes to /
                    const response = await fetch('/', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({ user_input: message })
                    });

                    console.log('Response:', response.status);

                    typing.remove();

                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            this.addMessage(data.response, 'ai');
                        } else {
                            this.addErrorMessage(data.error);
                        }
                    } else {
                        this.addErrorMessage('Server error: ' + response.status);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    typing.remove();
                    this.addErrorMessage('Connection failed');
                }

                // Re-enable
                this.messageInput.disabled = false;
                this.toggleSendButton();
                this.messageInput.focus();
            }

            addMessage(content, sender) {
                // Remove welcome
                const welcome = this.messagesContainer.querySelector('.welcome-message');
                if (welcome) welcome.remove();

                const div = document.createElement('div');
                div.className = `message ${sender}`;
                const time = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});

                div.innerHTML = `
                    <div class="message-content">
                        <div>${content}</div>
                        <div class="message-meta">
                            <span class="message-time">${time}</span>
                            ${sender === 'ai' ? '<span style="font-size: 11px; opacity: 0.7;">AI Assistant</span>' : ''}
                        </div>
                    </div>
                `;

                this.messagesContainer.appendChild(div);
                this.scrollToBottom();
                return div;
            }

            addErrorMessage(msg) {
                this.addMessage(`<div style="color: #ef4444; background: #fef2f2; padding: 8px; border-radius: 4px; border-left: 3px solid #ef4444;">⚠️ ${msg}</div>`, 'ai');
            }

            addTypingIndicator() {
                const div = document.createElement('div');
                div.className = 'typing-indicator';
                div.innerHTML = '<div>AI is thinking...</div><div class="typing-dots"><span></span><span></span><span></span></div>';
                this.messagesContainer.appendChild(div);
                this.scrollToBottom();
                return div;
            }

            scrollToBottom() {
                this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOM Ready');
            new ChatInterface();

            // --- START: ADD THIS NEW RESPONSIVE JAVASCRIPT ---
            const menuToggleBtn = document.getElementById('menu-toggle-btn');
            const sidebar = document.querySelector('.sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            const body = document.body;

            if (menuToggleBtn && sidebar) {
                menuToggleBtn.addEventListener('click', () => {
                    body.classList.toggle('sidebar-visible');
                });

                backdrop.addEventListener('click', () => {
                    body.classList.remove('sidebar-visible');
                });
            }
            // --- END: NEW RESPONSIVE JAVASCRIPT ---
        });
    </script>

   
@endsection 