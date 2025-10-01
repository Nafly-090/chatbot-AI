<!-- resources/views/partials/prompt-bar.blade.php -->
<div class="prompt-container-wrapper">
    <div class="prompt-container">
        <textarea id="message-input" class="prompt-input" placeholder="Start typing a prompt" rows="1"></textarea>
        <button class="prompt-button add-context-btn" title="Add Context">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
        </button>
        <button id="send-button" class="run-button">
            <span class="run-text">Run</span>
            <span class="run-shortcut">Ctrl ←</span>
        </button>
    </div>
</div>