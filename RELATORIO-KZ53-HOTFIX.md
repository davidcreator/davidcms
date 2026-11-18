# RELATÓRIO — Hotfix KZ-53: CSS/JS do admin 404 (base de assets errada)

## Sintoma (WAMP)
Painel admin renderiza sem estilo (HTML puro), mesmo após o KZ-52 (`{{ header|raw }}`).

## Causa raiz
Templates e controllers do admin montavam assets com `site_url + 'view/...'`. Se o `site_url` gravado na instalação não termina em `admin/` (ex.: raiz `.../dev/`), o caminho cai fora de `dev/admin/view/` → CSS/JS 404. O catálogo não sofria disso porque já tinha `catalogAssetUrl()`; o admin não tinha equivalente.

## Correção
- Novo `adminAssetUrl()` no Controller base (espelho de `catalogAssetUrl()`): garante base terminando em `admin/` (mantém `admin/`, converte `catalog/` → `admin/`, ou anexa `admin/` na raiz).
- Aplicado em: `header.twig`, `footer.twig`, `login/forgotten/reset/appearance.twig` (12 refs `{{ site_url }}view/` → `{{ asset_url }}view/`), `adminChrome()`, controllers `common/login`, `error/permission`, `error/not_found`, `tool/tool`, `design/theme`, `common/dashboard`, `cms/page`, `catalog/project`, `catalog/category`, `blog/blog`.
- Controllers `header/footer/login/forgotten/reset/appearance` passam `asset_url` para as views.

## Regressão
Checker varre `dev/admin/` inteiro (twig + php) contra `site_url + 'view/'` → **118 verificações PASS**.

## Governança
- decision-matrix KZ-53 DECIDED; CHANGELOG + CHECKSUMS recalculado (3 PASS / 0 FAIL).
