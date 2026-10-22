function escapeCatalogChatHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function buildCatalogChatFileUrl(path) {
    return 'image/' + encodeURI(String(path || ''));
}

function addMessageToUI(message) {
    const messageId = parseInt(message.message_id, 10) || 0;

    if (messageId && $(`.message[data-id="${messageId}"]`).length > 0) {
        return;
    }

    const messageClass = (message.sender_id == current_user_id && message.sender_type === 'customer') ? 'sent' : 'received';
    let contentHTML = '';

    if (message.message_type === 'image') {
        const imageUrl = buildCatalogChatFileUrl(message.message);
        contentHTML = `<div class="message-content message-image"><a href="${imageUrl}" target="_blank" rel="noopener"><img src="${imageUrl}" alt=""></a></div>`;
    } else if (message.message_type === 'file') {
        const fileUrl = buildCatalogChatFileUrl(message.message);
        const filename = String(message.message || '').split('/').pop();
        contentHTML = `<div class="message-content message-file"><a href="${fileUrl}" target="_blank" rel="noopener"><i class="fa-solid fa-file"></i> ${escapeCatalogChatHtml(filename)}</a></div>`;
    } else {
        contentHTML = `<div class="message-content">${escapeCatalogChatHtml(message.message)}</div>`;
    }

    $('.chat-system-msg').remove();

    const messageHTML = `
        <div class="message ${messageClass}" data-id="${messageId}">
            ${contentHTML}
            <div class="message-time">${formatCatalogChatTime(message.created_at)}</div>
        </div>
    `;

    $('#chat-messages-container').append(messageHTML);
    const container = $('#chat-messages-container')[0];

    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

function formatCatalogChatTime(datetime) {
    if (!datetime) {
        return '';
    }

    const normalized = String(datetime).replace(' ', 'T');
    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
