/**
 * ReamurCMS — appearance.js (F8-V, ADR 0047)
 * setting/appearance: preview AO VIVO do tema no próprio painel, validador de
 * contraste WCAG AA (≥4.5:1) no cliente (espelho do model PHP / validador CLI)
 * que BLOQUEIA o submit com cor inválida, e sugestão automática da variante
 * da cor para o modo escuro.
 */
(function () {
  'use strict';

  var root = document.getElementById('content');
  if (!root || !document.querySelector('.rms-appearance')) { return; }

  /* ── WCAG (mesma fórmula do validate-themes.php e do model PHP) ── */
  function hexToRgb(hex) {
    var m = /^#?([0-9a-f]{6})$/i.exec(String(hex).trim());
    if (!m) { return null; }
    var h = m[1];
    return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)];
  }
  function rgbToHex(c) {
    return '#' + c.map(function (v) { return Math.max(0, Math.min(255, v)).toString(16).padStart(2, '0'); }).join('');
  }
  function normalizeHex(v) {
    v = String(v).trim();
    var m3 = /^#?([0-9a-f]{3})$/i.exec(v);
    if (m3) { v = '#' + m3[1].split('').map(function (ch) { return ch + ch; }).join(''); }
    if (!/^#/.test(v)) { v = '#' + v; }
    return hexToRgb(v) ? v.toLowerCase() : null;
  }
  function relLum(c) {
    var f = function (v) {
      v /= 255;
      return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]);
  }
  function ratio(hexA, hexB) {
    var a = hexToRgb(hexA), b = hexToRgb(hexB);
    if (!a || !b) { return 0; }
    var l1 = relLum(a), l2 = relLum(b);
    if (l2 > l1) { var t = l1; l1 = l2; l2 = t; }
    return (l1 + 0.05) / (l2 + 0.05);
  }
  function bestContrastOn(accentHex) {
    var rw = ratio('#ffffff', accentHex);
    var rb = ratio('#000000', accentHex);
    return rw >= rb ? { contrast: '#ffffff', r: rw } : { contrast: '#000000', r: rb };
  }
  function suggestVariant(hex, mode) { /* espelho do PHP: passo 12, até 24 iterações */
    var c = hexToRgb(hex);
    if (!c) { return hex; }
    var target = mode === 'dark' ? '#212529' : '#ffffff';
    var step = mode === 'dark' ? 12 : -12;
    for (var i = 0; i < 24; i++) {
      if (ratio(rgbToHex(c), target) >= 4.5) { break; }
      c = c.map(function (v) { return Math.max(0, Math.min(255, v + step)); });
    }
    return rgbToHex(c);
  }

  /* ── estado da página (para restaurar se o usuário cancelar navegando) ── */
  var htmlEl = document.documentElement;
  var skinLink = document.getElementById('rms-skin-css');

  function isDark() { return htmlEl.getAttribute('data-bs-theme') === 'dark'; }

  /* injeta/limpa o <style> do acento custom */
  var accentStyle = document.getElementById('rms-accent-custom');
  function applyAccentPreview(light, dark, enabled) {
    if (!accentStyle) {
      accentStyle = document.createElement('style');
      accentStyle.id = 'rms-accent-custom';
      document.head.appendChild(accentStyle);
    }
    if (!enabled || !light) { accentStyle.textContent = ''; return; }
    var lc = bestContrastOn(light).contrast;
    var dEff = dark || light;
    var dc = bestContrastOn(dEff).contrast;
    accentStyle.textContent =
      ':root{--rms-accent:' + light + ';--rms-accent-contrast:' + lc + ';}' +
      '[data-bs-theme="dark"]{--rms-accent:' + dEff + ';--rms-accent-contrast:' + dc + ';}';
  }

  /* ── preview por form ── */
  function wireForm(form) {
    if (!form) { return; }

    var verdict = form.querySelector('.rms-accent-verdict');
    var saveBtn = form.querySelector('.rms-btn-save');
    var customBox = form.querySelector('.rms-accent-custom-box');
    var lightInput = form.querySelector('input[name="accent_custom"]');
    var darkInput = form.querySelector('input[name="accent_custom_dark"]');
    var suggestBtn = form.querySelector('.rms-btn-suggest');

    function selectedAccent() {
      var c = form.querySelector('input[name="accent_choice"]:checked');
      return c ? c.value : '';
    }
    function isCustom() { return selectedAccent() === 'custom'; }

    function refreshCustomBox() {
      if (customBox) { customBox.classList.toggle('d-none', !isCustom()); }
    }

    function validate() {
      refreshCustomBox();
      if (!saveBtn) { return; }
      if (!isCustom()) {
        saveBtn.disabled = false;
        if (verdict) {
          verdict.className = 'rms-accent-verdict small text-body-secondary';
          verdict.textContent = '';
        }
        return;
      }
      var light = normalizeHex(lightInput ? lightInput.value : '');
      var dark = darkInput && darkInput.value.trim() !== '' ? normalizeHex(darkInput.value) : null;

      var problems = [];
      if (!light) {
        problems.push('hex-claro');
      } else {
        if (ratio(light, '#ffffff') < 4.5) { problems.push('claro'); }
        var dEff = dark || light;
        if (ratio(dEff, '#212529') < 4.5) { problems.push('escuro'); }
        if (bestContrastOn(light).r < 4.5 || bestContrastOn(dEff).r < 4.5) { problems.push('contraste-texto'); }
      }
      if (darkInput && darkInput.value.trim() !== '' && !dark) { problems.push('hex-escuro'); }

      var ok = problems.length === 0;
      saveBtn.disabled = !ok;
      if (verdict) {
        if (ok) {
          verdict.className = 'rms-accent-verdict small text-success fw-semibold';
          verdict.innerHTML = '<i class="bi bi-check-circle me-1"></i>WCAG AA ✓';
        } else {
          verdict.className = 'rms-accent-verdict small text-danger fw-semibold';
          verdict.innerHTML = '<i class="bi bi-x-octagon me-1"></i><span></span>';
          verdict.querySelector('span').textContent = window.__rmsAppearanceFail || 'WCAG AA falhou';
        }
      }
    }

    function preview() {
      var skin = form.querySelector('input[name="skin"]:checked');
      if (skin && skinLink && skinLink.dataset.base) {
        skinLink.setAttribute('href', skinLink.dataset.base.replace('{skin}', skin.value));
        htmlEl.setAttribute('data-rms-skin', skin.value);
      }
      var sidebar = form.querySelector('input[name="sidebar"]:checked');
      if (sidebar) { htmlEl.setAttribute('data-rms-sidebar', sidebar.value); }
      var density = form.querySelector('input[name="density"]:checked');
      if (density) { htmlEl.setAttribute('data-rms-density', density.value); }
      var font = form.querySelector('input[name="font_scale"]:checked');
      if (font) {
        if (font.value === '100') { htmlEl.style.fontSize = ''; }
        else { htmlEl.style.fontSize = font.value + '%'; }
      }
      var mode = form.querySelector('input[name="mode"]:checked');
      if (mode) {
        htmlEl.setAttribute('data-rms-mode-pref', mode.value);
        if (mode.value === 'light' || mode.value === 'dark') {
          htmlEl.setAttribute('data-bs-theme', mode.value);
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
          htmlEl.setAttribute('data-bs-theme', 'dark');
        } else {
          htmlEl.setAttribute('data-bs-theme', 'light');
        }
      }
      var choice = form.querySelector('input[name="accent_choice"]:checked');
      if (choice && choice.value !== 'custom' && choice.value !== '' && choice.dataset.light) {
        applyAccentPreview(choice.dataset.light, choice.dataset.dark || '', true);
      } else if (isCustom()) {
        var light = normalizeHex(lightInput ? lightInput.value : '');
        var dark = darkInput ? normalizeHex(darkInput.value || '') : null;
        applyAccentPreview(light || '', dark || '', !!light);
      } else {
        applyAccentPreview('', '', false);
      }
    }

    form.addEventListener('change', function () { validate(); preview(); });
    if (lightInput) { lightInput.addEventListener('input', function () { validate(); preview(); }); }
    if (darkInput) { darkInput.addEventListener('input', function () { validate(); preview(); }); }

    if (suggestBtn) {
      suggestBtn.addEventListener('click', function () {
        var light = normalizeHex(lightInput ? lightInput.value : '');
        if (!light || !darkInput) { return; }
        darkInput.value = suggestVariant(light, 'dark');
        validate();
        preview();
      });
    }

    validate();
  }

  // base do link da skin p/ preview
  if (skinLink) {
    var href = skinLink.getAttribute('href') || '';
    skinLink.dataset.base = href.replace(/themes\/[a-z-]+\.css/, 'themes/{skin}.css');
  }

  document.querySelectorAll('.rms-appearance form').forEach(wireForm);
})();
