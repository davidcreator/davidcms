(() => {
  const hook = document.querySelector('[data-blog-interact]');
  if (!hook) return;

  const endpoint = hook.dataset.endpoint;
  let payload;
  try {
    payload = JSON.parse(hook.dataset.payload || '{}');
  } catch (e) {
    return;
  }

  if (!endpoint || !payload.post_id) return;

  const clientKey = (() => {
    try {
      const key = localStorage.getItem('reamur_blog_actor');
      if (key) return key;
      const fresh = crypto.randomUUID();
      localStorage.setItem('reamur_blog_actor', fresh);
      return fresh;
    } catch (e) {
      return '';
    }
  })();

  const send = (action, extra = {}) => {
    const body = { ...payload, action, actor_key: payload.actor_key || clientKey, ...extra };
    const json = JSON.stringify(body);
    if (navigator.sendBeacon) {
      const blob = new Blob([json], { type: 'application/json' });
      if (navigator.sendBeacon(endpoint, blob)) return;
    }
    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: json,
      keepalive: true
    }).catch(() => {});
  };

  const likeBtn = document.querySelector('[data-blog-like]');
  const likeCounter = likeBtn ? likeBtn.querySelector('[data-like-count]') : null;
  if (likeBtn) {
    likeBtn.addEventListener('click', () => {
      send('like');
      if (likeCounter) {
        const current = parseInt(likeCounter.textContent || '0', 10);
        likeCounter.textContent = current + 1;
      }
    });
  }

  const shareBtn = document.querySelector('[data-blog-share]');
  if (shareBtn) {
    const shareOriginal = shareBtn.textContent;
    shareBtn.addEventListener('click', async () => {
      const shareUrl = shareBtn.dataset.shareUrl || window.location.href;
      const data = { title: document.title, url: shareUrl };
      if (navigator.share) {
        try {
          await navigator.share(data);
          send('share');
          return;
        } catch (e) {
          // fallthrough to copy
        }
      }
      try {
        await navigator.clipboard.writeText(shareUrl);
        shareBtn.textContent = shareBtn.dataset.copiedLabel || 'Copied!';
        setTimeout(() => (shareBtn.textContent = shareOriginal), 2000);
        send('share');
      } catch (e) {
        send('share');
      }
    });
  }

  const commentForm = document.querySelector('#comment-form');
  if (commentForm) {
    const endpoint = commentForm.dataset.commentEndpoint;
    const slug = commentForm.dataset.commentSlug;
    const postId = commentForm.dataset.commentPost;
    const statusLabel = document.querySelector('#comment-status');
    const labelSending = commentForm.dataset.labelSending || 'Sending...';
    const labelError = commentForm.dataset.labelError || 'Could not send comment.';
    const labelNetwork = commentForm.dataset.labelNetwork || 'Network error';
    const labelSuccess = commentForm.dataset.labelSuccess || 'Comment received.';
    commentForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!endpoint) return;

      const formData = new FormData(commentForm);
      const payload = Object.fromEntries(formData.entries());
      payload.slug = slug;
      payload.post_id = parseInt(postId, 10);

      statusLabel.textContent = labelSending;
      try {
        const res = await fetch(endpoint, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const body = await res.json();
        if (!res.ok) {
          const msg = body.errors ? Object.values(body.errors).join(' ') : (body.error || labelError);
          statusLabel.textContent = msg;
          statusLabel.classList.add('text-danger');
          return;
        }
        statusLabel.classList.remove('text-danger');
        statusLabel.textContent = body.message || labelSuccess;
        commentForm.reset();
      } catch (err) {
        statusLabel.textContent = labelNetwork;
        statusLabel.classList.add('text-danger');
      }
    });
  }
})();
