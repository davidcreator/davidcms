/**
 * ReamurCMS — admin.js (F8-01, ADR 0045)
 * Comportamentos do shell do painel:
 *  1) toggle dark/light (localStorage 'rms-admin-theme' + data-bs-theme no
 *     <html>, com sync pela imagem/colors iniciais do script anti-flash no
 *     header.twig) — integração futura com aparência server-driven via
 *     window.__adminAppearanceTheme (theme-switcher.js do pacote de assets);
 *  2) colapso da sidebar no desktop (classe + persistência) e offcanvas
 *     manual no mobile com backdrop;
 *  3) inicialização do dropdown do usuário (Bootstrap 5.3.2 bundle).
 *
 * Zero dependência de jQuery: os assets do pacote (CKEditor etc.) continuam
 * disponíveis para as telas internas por document.addScript.
 */
(function () {
  'use strict';

  var THEME_KEY = 'rms-admin-theme';

  /* ── 1. Tema ── */
  function applyTheme(theme) {
    if (theme !== 'dark' && theme !== 'light') {
      theme = 'light';
    }
    document.documentElement.setAttribute('data-bs-theme', theme);
  }

  function currentTheme() {
    return document.documentElement.getAttribute('data-bs-theme') || 'light';
  }

  /* F8-V (ADR 0047): o modo é PREFERÊNCIA DO USUÁRIO no servidor.
     - window.__rmsAppearanceMode = pref server-side ('system'|'light'|'dark')
     - explicit: obedece ao servidor (localStorage ignorado)
     - system:   aplica escolha local fast-path e PERSISTE no servidor no toggle
  */
  var APPEARANCE_MODE = (typeof window.__rmsAppearanceMode === 'string') ? window.__rmsAppearanceMode : 'system';

  function systemPrefersDark() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  }

  function effectiveTheme() { /* tema de fato aplicado considerando a policy */
    if (APPEARANCE_MODE === 'light' || APPEARANCE_MODE === 'dark') { return APPEARANCE_MODE; }
    return currentTheme() === 'dark' ? 'dark' : 'light';
  }

  function persistAppearanceMode(mode) {
    var urlMeta = document.querySelector('meta[name="appearance-mode-url"]');
    if (!urlMeta || !urlMeta.content || !window.fetch) { return; }
    var csrf = document.querySelector('meta[name="csrf-token"]');
    try {
      var body = new URLSearchParams();
      body.set('mode', mode);
      if (csrf && csrf.content) { body.set('csrf_token', csrf.content); }
      fetch(urlMeta.content, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: body
      }).catch(function () { /* offline — local segue como fallback */ });
    } catch (e) { /* silencioso */ }
  }

  function toggleTheme() {
    var next = currentTheme() === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* modo privado */ }
    applyTheme(next);
    refreshToggleIcon(next);
    persistAppearanceMode(next);
  }

  function refreshToggleIcon(theme) {
    var btn = document.getElementById('rmsThemeToggle');
    if (!btn) { return; }
    var icon = btn.querySelector('i');
    if (icon) {
      icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
    }
    btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
  }

  /* ── 2. Sidebar ── */
  var COLLAPSE_KEY = 'rms-admin-sidebar-collapsed';

  function isMobile() { return window.matchMedia('(max-width: 991.98px)').matches; }

  function openMobileSidebar() {
    var sb = document.getElementById('adminSidebar');
    var bd = document.getElementById('sidebarBackdrop');
    if (sb) { sb.classList.add('show'); }
    if (bd) { bd.classList.add('show'); }
  }

  function closeMobileSidebar() {
    var sb = document.getElementById('adminSidebar');
    var bd = document.getElementById('sidebarBackdrop');
    if (sb) { sb.classList.remove('show'); }
    if (bd) { bd.classList.remove('show'); }
  }

  function toggleSidebar() {
    if (isMobile()) {
      var sb = document.getElementById('adminSidebar');
      if (sb && sb.classList.contains('show')) {
        closeMobileSidebar();
      } else {
        openMobileSidebar();
      }
      return;
    }
    var shell = document.querySelector('.admin-shell');
    if (!shell) { return; }
    shell.classList.toggle('sidebar-collapsed');
    try {
      localStorage.setItem(COLLAPSE_KEY, shell.classList.contains('sidebar-collapsed') ? '1' : '0');
    } catch (e) { /* ok */ }
  }

  function restoreSidebarState() {
    if (isMobile()) { return; }
    var shell = document.querySelector('.admin-shell');
    if (!shell) { return; }
    var saved = null;
    try { saved = localStorage.getItem(COLLAPSE_KEY); } catch (e) { /* ok */ }
    if (saved === '1') {
      shell.classList.add('sidebar-collapsed');
    }
  }

  /* ── boot ── */
  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); return; }
    document.addEventListener('DOMContentLoaded', fn);
  }

  ready(function () {
    refreshToggleIcon(currentTheme());
    restoreSidebarState();

    var themeBtn = document.getElementById('rmsThemeToggle');
    if (themeBtn) {
      themeBtn.addEventListener('click', toggleTheme);
    }

    var sideBtn = document.getElementById('rmsSidebarToggle');
    if (sideBtn) {
      sideBtn.addEventListener('click', toggleSidebar);
    }

    var backdrop = document.getElementById('sidebarBackdrop');
    if (backdrop) {
      backdrop.addEventListener('click', closeMobileSidebar);
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closeMobileSidebar(); }
    });

    // dropdown do usuário via Bootstrap (bundle carregado no footer)
    if (window.bootstrap && window.bootstrap.Dropdown) {
      document.querySelectorAll('[data-bs-toggle="dropdown"]').forEach(function (el) {
        new window.bootstrap.Dropdown(el);
      });
    }

    window.addEventListener('resize', function () {
      if (!isMobile()) { closeMobileSidebar(); }
    });
  });
})();

/* ── F8-02 · Blog (ADR 0046) ──────────────────────────────────────────── */

/* Contadores de caracteres (inputs com maxlength + data-counter) */
(function () {
    document.querySelectorAll('input[maxlength][data-counter]').forEach(function (input) {
        var el = document.createElement('p');
        el.className = 'char-counter';
        var render = function () {
            var max = parseInt(input.getAttribute('maxlength'), 10) || 0;
            var len = input.value.length;
            el.textContent = len + ' / ' + max;
            el.classList.toggle('char-counter--near', max > 0 && len > max * 0.9);
        };
        if (input.parentNode) { input.parentNode.appendChild(el); }
        input.addEventListener('input', render);
        render();
    });
})();

/* Confirmação de delete via modal Bootstrap (fallback: confirm nativo) */
(function () {
    var forms = document.querySelectorAll('form[data-rms-confirm-delete]');
    if (!forms.length) { return; }

    var modal = document.getElementById('rmsDeleteModal');
    var bsModal = null;

    if (modal && window.bootstrap && window.bootstrap.Modal) {
        bsModal = new window.bootstrap.Modal(modal);
    }

    var pendingForm = null;

    forms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (form.dataset.rmsConfirmed === '1') { return; }
            e.preventDefault();
            if (bsModal) {
                pendingForm = form;
                var msg = form.getAttribute('data-rms-confirm-delete') || '';
                var body = modal.querySelector('.modal-body');
                if (body) { body.textContent = msg; }
                bsModal.show();
            } else if (!window.confirm(form.getAttribute('data-rms-confirm-delete') || 'Confirmar?')) {
                /* cancelado */
            } else {
                form.dataset.rmsConfirmed = '1';
                form.submit();
            }
        });
    });

    if (modal) {
        var okBtn = modal.querySelector('[data-rms-delete-confirm]');
        if (okBtn) {
            okBtn.addEventListener('click', function () {
                if (pendingForm) {
                    pendingForm.dataset.rmsConfirmed = '1';
                    bsModal.hide();
                    pendingForm.submit();
                    pendingForm = null;
                }
            });
        }
    }
})();

/* CKEditor 4: upgrade de textareas com data-rte (assets locais) */
(function () {
    if (!window.CKEDITOR) { return; }
    document.querySelectorAll('textarea[data-rte]').forEach(function (ta) {
        if (ta.dataset.rteReady === '1') { return; }
        try {
            window.CKEDITOR.replace(ta, {
                height: 320,
                toolbar: [
                    { name: 'basicstyles', items: ['Bold', 'Italic', 'Underline', 'Strike', 'RemoveFormat'] },
                    { name: 'paragraph', items: ['NumberedList', 'BulletedList', '-', 'Blockquote'] },
                    { name: 'links', items: ['Link', 'Unlink'] },
                    { name: 'insert', items: ['Image', 'Table', 'HorizontalRule'] },
                    { name: 'styles', items: ['Format'] },
                    { name: 'tools', items: ['Maximize', 'Source'] }
                ],
                removeButtons: '',
                format_tags: 'p;h2;h3;h4',
                removePlugins: 'elementspath,exportpdf',
                resize_enabled: true,
                allowedContent: true
            });
            ta.dataset.rteReady = '1';
        } catch (e) {
            /* sem editor — textarea continua funcional */
        }
    });
})();

/* Tabs de idioma por âncora (#lang-pt-br etc.) — deep-link/persistência leve */
(function () {
    var tabs = document.querySelectorAll('#blogLangTabs [data-bs-toggle="tab"]');
    if (!tabs.length) { return; }

    tabs.forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (e) {
            var code = e.target.getAttribute('data-lang-tab');
            if (code && window.history && window.history.replaceState) {
                window.history.replaceState(null, '', '#lang-' + code);
            }
        });
    });

    if (window.location.hash && window.location.hash.indexOf('#lang-') === 0) {
        var code = window.location.hash.substring(6);
        var target = document.querySelector('#blogLangTabs [data-lang-tab="' + code + '"]');
        if (target && window.bootstrap && window.bootstrap.Tab) {
            new window.bootstrap.Tab(target).show();
        }
    }
})();

/* Hint do status (publicado/rascunho) no switch do form */
(function () {
    var sw = document.getElementById('input-status');
    if (!sw) { return; }
    var hint = sw.closest('.form-check').querySelector('[data-status-hint]');
    if (!hint) { return; }
    var pub = hint.getAttribute('data-text-published');
    var drf = hint.getAttribute('data-text-draft');
    if (!pub || !drf) {
        pub = pub || hint.textContent;
        drf = drf || hint.textContent;
        return; /* textos ficam como vieram do servidor */
    }
    sw.addEventListener('change', function () {
        hint.textContent = sw.checked ? pub : drf;
    });
})();

/* Preview da imagem de capa quando o campo falta de um picker real */
(function () {
    var input = document.getElementById('input-image');
    var box = document.getElementById('coverPreview');
    if (!input || !box) { return; }

    input.addEventListener('input', function () {
        var v = input.value.trim();
        var old = box.querySelector('img');
        var empty = box.querySelector('.cover-preview--empty');
        if (v === '') {
            if (old) { old.remove(); }
            if (empty) { empty.hidden = false; }
            return;
        }
        var src = /^https?:\/\//i.test(v) ? v : (input.getAttribute('data-preview-base') || '') + v.replace(/^\//, '');
        if (!old) {
            old = document.createElement('img');
            box.insertBefore(old, box.firstChild);
        }
        old.src = src;
        old.onload = function () { if (empty) { empty.hidden = true; } old.hidden = false; };
        old.onerror = function () { old.hidden = true; if (empty) { empty.hidden = false; } };
    });
})();
