# Padronizacao visual: Posts (Blog) = Content Pages (CMS)

Referencia: imagens 1 e 2 (Pages + Quick Edit) e imagem 3 (Blog/Posts).
O Blog ja compartilhava o shell Bootstrap; faltavam descricao, o link/painel
Quick Edit, os badges solidos e pequenos detalhes de filtro/rodape.

Mudancas de codigo (6 arquivos):
  dev/admin/view/template/blog/blog_list.twig -> descricao, link Quick Edit, painel
       inline (Title | SEO Keyword / Status | Published), type=search + maxlength,
       |e('html_attr') nos hrefs, text-body-secondary, wrapper cms-page-list
  dev/admin/controller/blog/blog.php          -> carrega cms-page-quick-edit.js,
       expoe quick_* por post e implementa quickEdit() (POST + ACL + CSRF +
       fingerprint; preserva descricoes dos 3 idiomas e nao toca no BDRE)
  dev/admin/view/css/admin.css                -> badges com as mesmas cores solidas
       de .text-bg-success/.text-bg-secondary do Pages
  3x dev/admin/language/*/blog/blog.php      -> 9 chaves traduzidas (EN/PT/ES)

Decisoes de compatibilidade:
  - JS de Quick Edit REUTILIZADO (cms-page-quick-edit.js), nao duplicado.
  - Classes badge-status--published/--draft MANTIDAS: o checker
    tools/e2e/e2e_f805_browser.js:281 seleciona .badge-status--draft.
  - import_href e a.link-plain MANTIDOS: e2e_f703.sh e e2e_f805_browser.js.
  - Nenhum |raw novo (os 3 usos legitimos de header/footer/pagination intactos).
  - Publicado (data) e o analogo do Sort Order do Pages: o blog e cronologico.
  - Nenhum contrato, matriz, schema.sql, hash de release gate, release_approved,
    workflow ou checker foi alterado. Nenhuma escrita remota.

Os 14 CHECKSUMS*.txt foram VERIFICADOS e permanecem IDENTICOS (nenhum dos 6
arquivos de UI e coberto por eles). Por isso nao foram reemitidos nem incluidos
neste pacote: evita a colisao de manifestos que deixou o CI vermelho antes.

Validado:
  verify-checksums   14 manifestos / 142 arquivos / 0 divergentes
  repo-health        6 PASS / 0 FAIL / 0 SKIP
  verify-ci-workflow 31 PASS / 0 FAIL
  verify-contract-freeze exit 0
  Twig if/endif e for/endfor balanceados; PHP {}()[] balanceados nos 4 arquivos
  node --check no JS reutilizado
  php -l: executado apenas se houver PHP no ambiente (ver LEIA-ME da entrega)

LIMITACAO: nao foi possivel executar PHP, Composer, PHPStan, browser real, banco,
migration, staging ou deploy neste sandbox. A validacao e estatica; o
comportamento visual e o POST do Quick Edit precisam de conferencia no ambiente
do usuario antes de qualquer release.

Aplicar: bash tools/verify-checksums.sh && bash tools/repo-health.sh
