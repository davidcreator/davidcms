(function bootCatalogChat() {
    if (typeof window.jQuery === 'undefined') {
        window.setTimeout(bootCatalogChat, 50);
        return;
    }

    window.jQuery(function($) {
    const chat_id = typeof current_chat_id !== 'undefined' ? current_chat_id : 0;
    const text = window.catalogChatText || {
        typingSuffix: 'is typing...'
    };

    let last_message_id = 0;
    let is_typing = false;
    let typing_timeout = null;

    const $messageInput = $('#chat-message-input');
    const notificationSound = new Audio('catalog/view/theme/default/sound/notification.mp3');

    function fetchMessages() {
        if (!chat_id) {
            return;
        }

        $.ajax({
            url: 'index.php?route=api/chat_fetch',
            type: 'GET',
            data: { chat_id: chat_id, last_message_id: last_message_id },
            dataType: 'json',
            success: function(json) {
                if (!json['success']) {
                    return;
                }

                if (Array.isArray(json['messages']) && json['messages'].length > 0) {
                    json['messages'].forEach(function(message) {
                        if (message.message_id > last_message_id && (message.sender_id != current_user_id || message.sender_type !== 'customer')) {
                            notificationSound.play().catch(function() {});
                        }

                        addMessageToUI(message);
                        last_message_id = Math.max(last_message_id, parseInt(message.message_id, 10) || 0);
                    });
                }

                if (Array.isArray(json['typing']) && json['typing'].length > 0) {
                    const typingNames = json['typing'].map(function(participant) {
                        return participant.name;
                    }).filter(Boolean).join(', ');

                    $('.typing-indicator').text(typingNames + ' ' + text.typingSuffix).show();
                } else {
                    $('.typing-indicator').hide();
                }
            },
            complete: function() {
                setTimeout(fetchMessages, 500);
            }
        });
    }

    function sendTypingStatus(status) {
        if (!chat_id) {
            return;
        }

        $.ajax({
            url: 'index.php?route=api/chat_typing',
            type: 'POST',
            data: { chat_id: chat_id, status: status },
            dataType: 'json'
        });
    }

    $messageInput.on('input', function() {
        if (!is_typing) {
            is_typing = true;
            sendTypingStatus('typing');
        }

        clearTimeout(typing_timeout);
        typing_timeout = setTimeout(function() {
            is_typing = false;
            sendTypingStatus('stop');
        }, 3000);
    });

    function sendMessage() {
        const message = $messageInput.val();

        if (message.trim() !== '' && chat_id) {
            $.ajax({
                url: 'index.php?route=api/chat_send',
                type: 'POST',
                data: { chat_id: chat_id, message: message, message_type: 'text' },
                dataType: 'json',
                success: function(json) {
                    if (json['success']) {
                        $messageInput.val('');
                        is_typing = false;
                        sendTypingStatus('stop');
                    }
                }
            });
        }
    }

    $('#send-message-btn').on('click', sendMessage);
    $messageInput.on('keypress', function(e) {
        if (e.which === 13) {
            sendMessage();
        }
    });

    $('#chat-attachment-btn').on('click', function() {
        $('#chat-file-input').trigger('click');
    });

    $('#chat-file-input').on('change', function() {
        const fileInput = this;

        if (fileInput.files.length > 0) {
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('chat_id', chat_id);

            $('#chat-attachment-btn i').removeClass('fa-paperclip').addClass('fa-spinner fa-spin');

            $.ajax({
                url: 'index.php?route=api/chat_upload',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(json) {
                    if (json['success']) {
                        $.ajax({
                            url: 'index.php?route=api/chat_send',
                            type: 'POST',
                            data: {
                                chat_id: chat_id,
                                file: json['file'],
                                message_type: json['type']
                            },
                            dataType: 'json',
                            success: function(sendJson) {
                                if (sendJson['success']) {
                                    $(fileInput).val('');
                                }
                            }
                        });
                    } else if (json['error']) {
                        alert(json['error']);
                    }
                },
                complete: function() {
                    $('#chat-attachment-btn i').removeClass('fa-spinner fa-spin').addClass('fa-paperclip');
                }
            });
        }
    });

    if (chat_id) {
        fetchMessages();
    }
    });
}());
