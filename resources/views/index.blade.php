<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Project Consultant</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        /* Base Styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* App Layout */
        .app-container {
            display: flex; height: 100vh; max-width: 1400px; margin: 0 auto;
        }

        /* History Sidebar */
        .history-sidebar {
            width: 300px; background: #f8fafc; border-right: 1px solid #e2e8f0;
            display: flex; flex-direction: column; transition: transform 0.3s ease;
        }
        .sidebar-header {
            padding: 20px; background: white; border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .sidebar-title { font-size: 16px; font-weight: 600; color: #1f2937; }
        .new-chat-btn {
            background: #10b981; color: white; border: none; padding: 8px 16px;
            border-radius: 6px; cursor: pointer; font-weight: 500; font-size: 14px;
            transition: background 0.2s;
        }
        .new-chat-btn:hover { background: #059669; }
        .chat-list {
            flex: 1; overflow-y: auto; padding: 10px; 
        }
        .chat-item {
            padding: 12px; margin-bottom: 8px; background: white; border-radius: 8px;
            cursor: pointer; border-left: 3px solid #e2e8f0; transition: all 0.2s;
            display: flex; justify-content: space-between; align-items: center;
        }
        .chat-item:hover { border-left-color: #3b82f6; transform: translateX(2px); box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .chat-item.active { border-left-color: #3b82f6; background: #eff6ff; }
        .chat-info { flex: 1; }
        .chat-title { font-weight: 600; color: #1f2937; font-size: 14px; margin-bottom: 2px; }
        .chat-preview { font-size: 12px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .chat-time { font-size: 11px; color: #9ca3af; margin-top: 4px; }
        .delete-chat { 
            color: #ef4444; font-size: 14px; cursor: pointer; opacity: 0; 
            padding: 4px; border-radius: 4px; transition: opacity 0.2s;
        }
        .chat-item:hover .delete-chat { opacity: 1; }
        .delete-chat:hover { background: #fef2f2; }

        /* Main Chat Area */
        .main-chat { flex: 1; display: flex; flex-direction: column; }
        .chat-container { flex: 1; height: 100%; display: flex; flex-direction: column; }
        .chat-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; padding: 20px 30px; display: flex; align-items: center; gap: 15px; 
        }
        .header-avatar { 
            width: 50px; height: 50px; border-radius: 50%; background: rgba(255,255,255,0.2); 
            display: flex; align-items: center; justify-content: center; font-size: 24px; 
        }
        .header-content h1 { font-size: 28px; font-weight: 600; margin-bottom: 2px; }
        .header-content p { opacity: 0.9; font-size: 14px; margin: 0; }
        .messages-container { flex: 1; overflow-y: auto; padding: 20px; background: #f8fafc; }
        .message { margin-bottom: 20px; display: flex; animation: fadeInUp 0.3s ease-out; }
        .message.user { justify-content: flex-end; }
        .message.ai { justify-content: flex-start; }
        .message-content { max-width: 70%; padding: 16px 20px; border-radius: 18px; word-wrap: break-word; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .message.user .message-content { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-bottom-right-radius: 4px; }
        .message.ai .message-content { background: white; border: 1px solid #e2e8f0; border-bottom-left-radius: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .message.ai .message-content::before { content: ''; position: absolute; left: -8px; top: 12px; width: 0; height: 0; border: 8px solid transparent; border-right-color: #e2e8f0; }
        .message.user .message-content::after { content: ''; position: absolute; right: -8px; top: 12px; width: 0; height: 0; border: 8px solid transparent; border-left-color: #667eea; }
        .message-meta { font-size: 12px; opacity: 0.7; margin-top: 4px; display: flex; align-items: center; gap: 8px; }
        .message.user .message-meta { justify-content: flex-end; color: rgba(255,255,255,0.8); }
        .message-time { font-family: 'SF Mono', monospace; }
        .typing-indicator { display: flex; align-items: center; gap: 8px; padding: 12px 16px; background: white; border-radius: 18px; border: 1px solid #e2e8f0; max-width: 70%; border-bottom-left-radius: 4px; margin-bottom: 20px; }
        .typing-dots { display: flex; gap: 4px; }
        .typing-dots span { width: 8px; height: 8px; border-radius: 50%; background: #cbd5e0; animation: typing 1.4s infinite ease-in-out; }
        .typing-dots span:nth-child(1) { animation-delay: -0.32s; }
        .typing-dots span:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing { 0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; } 40% { transform: scale(1); opacity: 1; } }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .input-container { padding: 20px 30px; background: white; border-top: 1px solid #e2e8f0; display: flex; gap: 12px; align-items: flex-end; }
        .input-wrapper { flex: 1; position: relative; }
        #message-input { width: 100%; padding: 16px 20px 16px 50px; border: 2px solid #e2e8f0; border-radius: 25px; font-size: 16px; resize: none; min-height: 20px; max-height: 120px; line-height: 1.5; }
        #message-input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .input-icons { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); display: flex; gap: 8px; opacity: 0.6; }
        .input-icon { width: 20px; height: 20px; color: #6b7280; cursor: pointer; }
        #send-button { width: 52px; height: 52px; border: none; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; }
        #send-button:hover:not(:disabled) { transform: scale(1.05); box-shadow: 0 4px 12px rgba(102,126,234,0.3); }
        #send-button:disabled { opacity: 0.5; cursor: not-allowed; }
        .welcome-message { text-align: center; padding: 60px 40px; color: #6b7280; }
        .welcome-message h2 { font-size: 24px; margin-bottom: 12px; color: #374151; }
        .welcome-message p { max-width: 500px; margin: 0 auto 24px; line-height: 1.6; }
        .examples { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 32px; }
        .example-btn { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); color: #475569; border: 2px solid #e2e8f0; padding: 12px 20px; border-radius: 25px; font-size: 14px; cursor: pointer; transition: all 0.3s ease; min-width: 180px; }
        .example-btn:hover { background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e0 100%); transform: translateY(-2px); border-color: #667eea; }
        @media (max-width: 768px) { .app-container { flex-direction: column; } .history-sidebar { width: 100%; height: auto; transform: translateX(-100%); position: fixed; z-index: 1000; } .history-sidebar.open { transform: translateX(0); } .main-chat { flex: 1; } .chat-container { height: calc(100vh - 60px); } .example-btn { min-width: 140px; } }
        .mobile-menu-toggle { display: none; }
        @media (max-width: 768px) { .mobile-menu-toggle { display: block; position: fixed; top: 20px; left: 20px; z-index: 1001; background: #667eea; color: white; border: none; padding: 10px; border-radius: 50%; cursor: pointer; } }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- History Sidebar -->
        <div class="history-sidebar" id="history-sidebar">
            <div class="sidebar-header">
                <h3 class="sidebar-title">Chat History</h3>
                <button class="new-chat-btn" onclick="startNewChat()">+ New Chat</button>
            </div>
            <div class="chat-list" id="chat-list">
                @if(!empty($saved_conversations))
                    @foreach($saved_conversations as $chat)
                        <div class="chat-item" onclick="loadChat('{{ $chat['id'] }}')" data-chat-id="{{ $chat['id'] }}">
                            <div class="chat-info">
                                <div class="chat-title">{{ $chat['title'] }}</div>
                                <div class="chat-preview">{{ Str::limit($chat['preview'], 50) }}</div>
                                <div class="chat-time">{{ \Carbon\Carbon::parse($chat['timestamp'])->format('M d, Y') }}</div>
                            </div>
                            <span class="delete-chat" onclick="event.stopPropagation(); deleteChat('{{ $chat['id'] }}')">×</span>
                        </div>
                    @endforeach
                @else
                    <div style="text-align: center; padding: 40px; color: #6b7280;">
                        <p>No saved conversations yet.</p>
                        <p>Start a new chat to save history!</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Main Chat -->
        <div class="main-chat">
            <div class="chat-container">
                <div class="chat-header">
                    <div class="header-avatar">🤖</div>
                    <div class="header-content">
                        <h1>AI Project Consultant</h1>
                        <p>Current: {{ $has_conversation ? 'Ongoing Conversation' : 'New Chat' }}</p>
                    </div>
                </div>

                <div class="messages-container" id="messages-container">
                    @if(empty($current_conversation))
                        <div class="welcome-message">
                            <h2>Welcome! I'm your AI Project Consultant 🤖</h2>
                            <p>Tell me about your software project and I'll provide detailed recommendations on features, technology, timeline, and budget.</p>
                            <div class="examples">
                                <button class="example-btn" data-example="I need to build a student exam system to manage student details, exam scheduling, and results">Student Exam System</button>
                                <button class="example-btn" data-example="I want an inventory management system for tracking stock, orders, and suppliers">Inventory Management</button>
                                <button class="example-btn" data-example="Create an e-commerce store with product catalog, shopping cart, and payment integration">E-commerce Store</button>
                                <button class="example-btn" data-example="Develop a blog platform with content management and SEO features">Blog Platform</button>
                            </div>
                        </div>
                    @else
                        @foreach($current_conversation as $exchange)
                            <div class="message user">
                                <div class="message-content">
                                    <div>{{ $exchange['user_message'] }}</div>
                                    <div class="message-meta">
                                        <span class="message-time">{{ $exchange['formatted_time'] }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="message ai">
                                <div class="message-content">
                                    {!! $exchange['ai_response'] !!}
                                    <div class="message-meta">
                                        <span class="message-time">{{ $exchange['formatted_time'] }}</span>
                                        <span style="font-size: 11px; opacity: 0.7;">AI Assistant</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div class="input-container">
                    <div class="input-wrapper">
                        <div class="input-icons">
                            <svg class="input-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                            </svg>
                        </div>
                        <textarea id="message-input" placeholder="Type your project description or question..." rows="1"></textarea>
                    </div>
                    <button id="send-button">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>

    <script>
        // Chat History Management
        let currentChatId = null;

        // Toggle sidebar on mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('history-sidebar');
            sidebar.classList.toggle('open');
        }

        // Start new chat
        async function startNewChat() {
            try {
                const response = await fetch('/new-chat', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    console.log('New chat started:', data);
                    location.reload(); // Reload to show updated history
                }
            } catch (error) {
                console.error('Error starting new chat:', error);
            }
        }

        // Load chat from history
        async function loadChat(chatId) {
            try {
                const response = await fetch(`/load-chat?chat_id=${chatId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    console.log('Chat loaded:', data);
                    location.reload(); // Reload to show loaded conversation
                } else {
                    console.error('Failed to load chat');
                }
            } catch (error) {
                console.error('Load chat error:', error);
            }
        }

        // Delete chat
        async function deleteChat(chatId) {
            if (!confirm('Delete this conversation?')) return;
            
            try {
                const response = await fetch(`/delete-chat?chat_id=${chatId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                if (response.ok) {
                    console.log('Chat deleted');
                    location.reload(); // Reload to update list
                }
            } catch (error) {
                console.error('Delete error:', error);
            }
        }

        // Main Chat Interface
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

                document.querySelectorAll('.example-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        const text = btn.getAttribute('data-example');
                        this.setExampleInput(text);
                    });
                });

                this.toggleSendButton();
                this.scrollToBottom();
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

                this.messageInput.disabled = true;
                this.sendButton.disabled = true;

                this.addMessage(message, 'user');
                this.messageInput.value = '';

                const typing = this.addTypingIndicator();

                try {
                    const response = await fetch('/', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ user_input: message })
                    });

                    typing.remove();

                    if (response.ok) {
                        const data = await response.json();
                        if (data.success) {
                            this.addMessage(data.response, 'ai');
                        } else {
                            this.addErrorMessage(data.error);
                        }
                    } else {
                        this.addErrorMessage('Server error');
                    }
                } catch (error) {
                    typing.remove();
                    this.addErrorMessage('Connection failed');
                }

                this.messageInput.disabled = false;
                this.toggleSendButton();
                this.messageInput.focus();
            }

            addMessage(content, sender) {
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

        document.addEventListener('DOMContentLoaded', () => {
            new ChatInterface();
        });
    </script>
</body>
</html>