# Contraste: links e icones de edicao invisiveis no tema claro

Sintoma (imagens 4-6): "Edit" e o icone de lapis so apareciam ao passar o
mouse; "Quick Edit" (button.btn-link) e a lixeira (button) sempre apareciam.

CAUSA RAIZ (uma unica origem, confirmada por varredura em TODOS os 62 CSS do
repo, admin + catalogo publico):
  dev/admin/view/css/reamurcms.css
    :root, [data-bs-theme=light] { --bs-anchor: #ffffff; }   <- branco
    a        { color: var(--bs-anchor); }                    <- todos os <a>
    a:hover  { color: #0056b3; }                             <- so no hover

Branco sobre fundo branco = 1.00:1. Como "Edit" e .icon-btn de edicao sao
elementos <a> e os demais controles sao <button>, apenas eles sumiam.

FALSOS POSITIVOS verificados e descartados no catalogo publico:
  dev/catalog/view/css/reamurcms.css -> a { color: #23a1d1 } (visivel); o #fff
     aparece apenas em :hover de menus com fundo azul, onde e correto.
  dev/catalog/view/css/cms_extensions.css -> .rms-catalog-card__title a
     { color: inherit } e .breadcrumb a { color: var(--rms-catalog-accent) }.

ALCANCE (varredura integral): 49 templates Twig no admin, 44 contem <a>,
8 usam .icon-btn (Pages, Posts, Catalog/Categories, Catalog/Projects, Tools,
Custom Fields, Contact Messages e o header). A regra global corrigida cobre
TODAS as telas de uma vez; nenhum template precisou ser alterado.

CORRECAO (1 arquivo, 3 pontos):
  --bs-anchor (light)        : #ffffff -> #0a58ca
  --bs-anchor-hover (light)  : #0056b3 (token novo)
  --bs-anchor (dark)         : #6ea8fe (token novo no bloco ja existente)
  --bs-anchor-hover (dark)   : #9ec5fe
  a:hover                    : #0056b3 -> var(--bs-anchor-hover)

Contraste WCAG medido (minimo AA = 4.5:1 para texto normal):
  #0a58ca sobre #ffffff 6.44 | #f8f9fa 6.11 | #e9ecef 5.43 | #dee2e6 4.94
  #6ea8fe sobre #212529 6.39 | #2b3035 5.51 | #343a40 4.76
  ANTES #ffffff sobre #ffffff 1.00 -> invisivel
O azul padrao #0d6efd foi DESCARTADO: falha AA em #e9ecef (3.80) e
#dee2e6 (3.46), fundos usados pelas linhas em hover/estado selecionado.

Decisoes:
  - Nao foi adicionada regra .icon-btn em admin.css: isso mudaria a cor da
    lixeira e dos botoes da topbar, que ja estao corretos. A causa e global.
  - Os 14 CHECKSUMS*.txt permanecem IDENTICOS (este CSS nao e coberto por
    eles) e por isso NAO entram no pacote: evita colisao de manifestos.
  - Nenhum contrato, matriz, schema.sql, hash de release gate, release_approved,
    workflow, checker, template ou JS foi alterado. Nenhuma escrita remota.

Validado:
  verify-checksums   14 manifestos / 142 arquivos / 0 divergentes
  repo-health        6 PASS / 0 FAIL / 0 SKIP
  verify-ci-workflow 31 PASS / 0 FAIL
  verify-contract-freeze exit 0
  CSS balanceado; contraste AA medido nos 2 temas; A/B do valor antigo

LIMITACAO: sem browser real, PHP, Composer, banco, staging ou deploy neste
sandbox. A conferencia visual final e do usuario. Nao ha screenshot automatizado.

Aplicar: bash tools/verify-checksums.sh && bash tools/repo-health.sh
