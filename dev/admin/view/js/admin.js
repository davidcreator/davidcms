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

  function toggleTheme() {
    var next = currentTheme() === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem(THEME_KEY, next); } catch (e) { /* modo privado */ }
    applyTheme(next);
    refreshToggleIcon(next);
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
