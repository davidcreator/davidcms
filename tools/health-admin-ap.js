/* Health OBSERVACIONAL do Admin real — Frente AQ.
   SOMENTE LEITURA. Nao executa __kazHealth() (essa funcao nunca existiu no
   repositorio; aparece apenas em relatorios). Nao acessa rede, banco, sessao,
   token, conteudo de post nem gravacao de qualquer tipo.

   Uso: abrir Pages/Posts no Admin autenticado > DevTools > Console > colar.
   O copy() do DevTools copia o JSON para a area de transferencia.

   Valores esperados conforme o A/B/C da Frente AP (previa com reamurcms.css
   + themes/light.css + AN + AP). Divergencia = achado a investigar, NAO erro
   automatico: o Admin real pode usar skin/modo diferente da previa.

   Isto NAO substitui o veredito visual do usuario e NAO significa release
   aprovada. release_approved segue false; F9-27 segue BLOCKED_NOT_RUN. */
copy(JSON.stringify((() => {
  const q = s => document.querySelector(s);
  const cs = e => e ? getComputedStyle(e) : null;
  const host = q('[data-blog-block-editor]');
  const menu = q('#menu > li.active > a');
  const styles = [...document.querySelectorAll('link[rel="stylesheet"]')]
    .map(l => l.getAttribute('href') || '')
    .filter(h => h.includes('blog-block-editor-kaz'));
  return {
    path: location.pathname,
    marker_ap: styles.some(h => h.includes('-kaz-bdre-ap')),
    marker_ao: styles.some(h => h.includes('-kaz-bdre-ao')),
    styles,
    bs_primary: getComputedStyle(document.body).getPropertyValue('--bs-primary').trim(),
    rms_accent: getComputedStyle(document.documentElement).getPropertyValue('--rms-accent').trim(),
    menu_active_bg: menu ? cs(menu).backgroundColor : null,
    page_bg: getComputedStyle(document.body).backgroundColor,
    host_present: !!host,
    host_bg: host ? cs(host).backgroundColor : null,
    editor_mounted: !!q('.rb-editor'),
    canvas_bg: q('.rb-canvas') ? cs(q('.rb-canvas')).backgroundColor : null,
    data_bs_theme: document.documentElement.getAttribute('data-bs-theme')
  };
})(), null, 2))
