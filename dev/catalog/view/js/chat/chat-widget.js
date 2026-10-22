(function bootCatalogChatWidget() {
    if (typeof window.jQuery === 'undefined') {
        window.setTimeout(bootCatalogChatWidget, 50);
        return;
    }

    window.jQuery(function($) {
    let last_message_id = 0;
    let is_open = false;
    let is_typing = false;
    let typing_timeout = null;

    const notificationSound = new Audio('catalog/view/theme/default/sound/notification.mp3');

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildFileUrl(path) {
        return 'image/' + encodeURI(String(path || ''));
    }

    $('#chat-widget-toggle, #chat-widget-minimize').on('click', function() {
        $('#chat-widget-container').toggleClass('chat-widget-closed');
        is_open = !$('#chat-widget-container').hasClass('chat-widget-closed');

        if (is_open) {
            $('#chat-widget-badge').hide().text('0');
            scrollToBottom();

            $.ajax({
                url: 'index.php?route=api/chat_read',
                type: 'POST',
                data: { chat_id: chat_id },
                dataType: 'json'
            });
        }
    });

    function fetchWidgetMessages() {
        if (!chat_id) return;

        $.ajax({
            url: 'index.php?route=api/chat_fetch',
            type: 'GET',
            data: { chat_id: chat_id, last_message_id: last_message_id },
            dataType: 'json',
            success: function(json) {
                if (json['success']) {
                    if (json['messages'] && json['messages'].length > 0) {
                        json['messages'].forEach(function(message) {
                            if (message.message_id > last_message_id && (message.sender_id != chat_customer_id || message.sender_type != 'customer')) {
                                notificationSound.play().catch(function() {
                                    console.log('Audio blocked');
                                });
                            }

                            addMessageToWidget(message);
                            last_message_id = message.message_id;
                        });

                        if (!is_open) {
                            const currentCount = parseInt($('#chat-widget-badge').text(), 10) || 0;
                            $('#chat-widget-badge').text(currentCount + json['messages'].length).show();
                        }

                        scrollToBottom();
                    }

                    if (json['typing'] && json['typing'].length > 0) {
                        $('#chat-widget-typing').show();
                    } else {
                        $('#chat-widget-typing').hide();
                    }
                }
            },
            complete: function() {
                setTimeout(fetchWidgetMessages, 500);
            }
        });
    }

    function sendTypingStatus(status) {
        if (!chat_id) return;

        $.ajax({
            url: 'index.php?route=api/chat_typing',
            type: 'POST',
            data: { chat_id: chat_id, status: status },
            dataType: 'json'
        });
    }

    $('#chat-widget-msg-input').on('input', function() {
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

    function addMessageToWidget(msg) {
        if ($(`.widget-msg[data-id="${msg.message_id}"]`).length > 0) return;

        const isMe = (msg.sender_id == chat_customer_id && msg.sender_type == 'customer');
        const type = isMe ? 'sent' : 'received';

        let contentHTML = '';

        if (msg.message_type === 'image') {
            const imageUrl = buildFileUrl(msg.message);
            contentHTML = `<div class="msg-content msg-image"><a href="${imageUrl}" target="_blank" rel="noopener"><img src="${imageUrl}" class="img-fluid" style="max-width: 150px; border-radius: 4px;"></a></div>`;
        } else if (msg.message_type === 'file') {
            const fileUrl = buildFileUrl(msg.message);
            const filename = String(msg.message || '').split('/').pop();
            contentHTML = `<div class="msg-content msg-file"><a href="${fileUrl}" target="_blank" rel="noopener" style="text-decoration: none; color: inherit;"><i class="fa-solid fa-file"></i> ${escapeHtml(filename)}</a></div>`;
        } else {
            contentHTML = `<div class="msg-content">${escapeHtml(msg.message)}</div>`;
        }

        const html = `
            <div class="widget-msg ${type}" data-id="${msg.message_id}">
                ${contentHTML}
                <div class="msg-time" style="font-size: 9px; opacity: 0.7; margin-top: 2px;">${formatTime(msg.created_at)}</div>
            </div>
        `;

        $('#chat-widget-messages .chat-system-msg').remove();
        $('#chat-widget-messages').append(html);
    }

    function formatTime(datetime) {
        if (!datetime) return '';

        const date = new Date(datetime);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function scrollToBottom() {
        const container = $('#chat-widget-messages');

        if (container[0]) {
            container.scrollTop(container[0].scrollHeight);
        }
    }

    function sendWidgetMessage() {
        const message = $('#chat-widget-msg-input').val();

        if (message.trim() !== '' && chat_id) {
            $.ajax({
                url: 'index.php?route=api/chat_send',
                type: 'POST',
                data: { chat_id: chat_id, message: message, message_type: 'text' },
                dataType: 'json',
                success: function(json) {
                    if (json['success']) {
                        $('#chat-widget-msg-input').val('');
                        is_typing = false;
                        sendTypingStatus('stop');
                    }
                }
            });
        }
    }

    $('#chat-widget-send').on('click', sendWidgetMessage);
    $('#chat-widget-msg-input').on('keypress', function(e) {
        if (e.which == 13) sendWidgetMessage();
    });

    $('#chat-widget-attachment').on('click', function() {
        $('#chat-widget-file-input').trigger('click');
    });

    $('#chat-widget-file-input').on('change', function() {
        const fileInput = this;

        if (fileInput.files.length > 0) {
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            formData.append('chat_id', chat_id);

            $('#chat-widget-attachment i').removeClass('fa-paperclip').addClass('fa-spinner fa-spin');

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
                    $('#chat-widget-attachment i').removeClass('fa-spinner fa-spin').addClass('fa-paperclip');
                }
            });
        }
    });

    if (chat_id) {
        fetchWidgetMessages();
    }
    });
}());
