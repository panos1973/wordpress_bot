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

    // Returns '#fff' for dark backgrounds, '#2d2d2d' (charcoal) for light ones.
    function getContrastColor(cssColor) {
        var r, g, b;
        var hex = cssColor.trim();
        if (hex.charAt(0) === '#') {
            hex = hex.slice(1);
            if (hex.length === 3) {
                hex = hex[0]+hex[0]+hex[1]+hex[1]+hex[2]+hex[2];
            }
            r = parseInt(hex.substring(0, 2), 16);
            g = parseInt(hex.substring(2, 4), 16);
            b = parseInt(hex.substring(4, 6), 16);
        } else {
            var m = cssColor.match(/\d+/g);
            if (!m || m.length < 3) { return '#fff'; }
            r = parseInt(m[0]); g = parseInt(m[1]); b = parseInt(m[2]);
        }
        // YIQ perceived brightness (0–255)
        var brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 128 ? '#2d2d2d' : '#fff';
    }

    function init() {
        if (titleEl && config.chatTitle) {
            titleEl.textContent = config.chatTitle;
        }

        // Adapt send-button icon colour to the primary colour brightness
        var primaryColor = getComputedStyle(document.documentElement)
            .getPropertyValue('--mcb-primary').trim();
        if (primaryColor && sendBtn) {
            sendBtn.style.color = getContrastColor(primaryColor);
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

    function resizeMessagesArea() {
        // Explicitly set the messages area height so scrolling works
        // regardless of theme CSS interference with flexbox.
        var header = document.getElementById('mcb-chat-header');
        var inputArea = document.getElementById('mcb-chat-input-area');
        if (header && inputArea && chatWindow) {
            var windowHeight = chatWindow.offsetHeight;
            var headerHeight = header.offsetHeight;
            var inputHeight = inputArea.offsetHeight;
            var available = windowHeight - headerHeight - inputHeight;
            if (available > 0) {
                messagesArea.style.height = available + 'px';
                messagesArea.style.maxHeight = available + 'px';
            }
        }
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

            // Force correct messages area height after layout
            setTimeout(resizeMessagesArea, 50);
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
        // Ensure messages area is properly sized and scroll to bottom
        resizeMessagesArea();
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
