/**
 * Medical Chatbot Frontend Widget
 */
(function () {
    'use strict';

    var config = window.mcbChat || {};
    var chatWindow = document.getElementById('mcb-chat-window');
    var toggleBtn = document.getElementById('mcb-chat-toggle');
    var minimizeBtn = document.getElementById('mcb-chat-minimize');
    var messagesArea = document.getElementById('mcb-chat-messages');
    var inputField = document.getElementById('mcb-chat-input');
    var sendBtn = document.getElementById('mcb-chat-send');
    var iconChat = document.getElementById('mcb-icon-chat');
    var iconClose = document.getElementById('mcb-icon-close');
    var titleEl = document.getElementById('mcb-chat-title');

    var conversationHistory = [];
    var isOpen = false;
    var isWaiting = false;

    function init() {
        if (titleEl && config.chatTitle) {
            titleEl.textContent = config.chatTitle;
        }

        toggleBtn.addEventListener('click', toggleChat);
        minimizeBtn.addEventListener('click', toggleChat);
        sendBtn.addEventListener('click', sendMessage);

        inputField.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        // Auto-resize textarea
        inputField.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 80) + 'px';
        });
    }

    function toggleChat() {
        isOpen = !isOpen;

        if (isOpen) {
            chatWindow.style.display = 'flex';
            iconChat.style.display = 'none';
            iconClose.style.display = 'block';
            inputField.focus();

            // Show welcome message on first open
            if (messagesArea.children.length === 0 && config.welcomeMessage) {
                appendMessage(config.welcomeMessage, 'bot');
            }
        } else {
            chatWindow.style.display = 'none';
            iconChat.style.display = 'block';
            iconClose.style.display = 'none';
        }
    }

    function sendMessage() {
        var message = inputField.value.trim();

        if (!message || isWaiting) {
            return;
        }

        // Display user message
        appendMessage(message, 'user');

        // Clear input
        inputField.value = '';
        inputField.style.height = 'auto';

        // Show typing indicator
        showTyping();
        isWaiting = true;
        sendBtn.disabled = true;

        // Send to API
        var xhr = new XMLHttpRequest();
        xhr.open('POST', config.restUrl + '/chat');
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-WP-Nonce', config.nonce);

        xhr.onload = function () {
            hideTyping();
            isWaiting = false;
            sendBtn.disabled = false;

            if (xhr.status === 200) {
                var data = JSON.parse(xhr.responseText);
                if (data.success) {
                    appendMessage(data.message, 'bot', data.sources);

                    // Store in history (without disclaimer for context)
                    conversationHistory.push({
                        user: message,
                        assistant: data.message.split('\n\n---')[0]
                    });

                    // Keep history manageable
                    if (conversationHistory.length > 5) {
                        conversationHistory = conversationHistory.slice(-5);
                    }
                } else {
                    appendMessage(data.message || 'Λυπούμαστε, κάτι πήγε στραβά. Παρακαλώ δοκιμάστε ξανά.', 'bot');
                }
            } else if (xhr.status === 429) {
                appendMessage('Στέλνετε πολλά μηνύματα. Παρακαλώ περιμένετε λίγο και δοκιμάστε ξανά.', 'bot');
            } else {
                appendMessage('Λυπούμαστε, παρουσιάστηκε σφάλμα. Παρακαλώ δοκιμάστε αργότερα.', 'bot');
            }

            inputField.focus();
        };

        xhr.onerror = function () {
            hideTyping();
            isWaiting = false;
            sendBtn.disabled = false;
            appendMessage('Λυπούμαστε, υπήρξε σφάλμα σύνδεσης. Ελέγξτε τη σύνδεσή σας και δοκιμάστε ξανά.', 'bot');
        };

        xhr.send(JSON.stringify({
            message: message,
            history: conversationHistory
        }));
    }

    function appendMessage(text, type, sources) {
        var msgDiv = document.createElement('div');
        msgDiv.className = 'mcb-message mcb-message-' + type;

        // Format the text: handle markdown-style formatting
        var formatted = escapeHtml(text);
        // Bold
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Italic
        formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Horizontal rule (for disclaimer separator)
        formatted = formatted.replace(/\n---\n/g, '<hr>');
        // Line breaks
        formatted = formatted.replace(/\n/g, '<br>');

        msgDiv.innerHTML = formatted;

        messagesArea.appendChild(msgDiv);
        // Scroll to bottom so latest content is always visible
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function showTyping() {
        var typingDiv = document.createElement('div');
        typingDiv.className = 'mcb-typing';
        typingDiv.id = 'mcb-typing-indicator';
        typingDiv.innerHTML = '<div class="mcb-typing-dot"></div><div class="mcb-typing-dot"></div><div class="mcb-typing-dot"></div>';
        messagesArea.appendChild(typingDiv);
        messagesArea.scrollTop = messagesArea.scrollHeight;
    }

    function hideTyping() {
        var typing = document.getElementById('mcb-typing-indicator');
        if (typing) {
            typing.remove();
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
