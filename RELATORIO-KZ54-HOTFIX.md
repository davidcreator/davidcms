# RELATÓRIO — Hotfix KZ-54: Dracaunos/JOW sem layout (faltava admin.css)

## Sintoma (WAMP)
Após o KZ-53 o Bootstrap carregava (cards ok), mas o shell do admin (sidebar/topbar/cores) continuava quebrado na página do Dracaunos-Security.

## Causa
`security/dracaunos.php` e `payments/jow.php` renderizavam `common/header` **sem adicionar `view/css/admin.css`** — o CSS do layout do painel. Todos os outros controllers (ex.: dashboard) fazem esse `addStyle`.

## Correção
Os dois controllers agora fazem:
`$this->document->addStyle($this->adminAssetUrl() . 'view/css/admin.css?v=' . Header::ASSET_V);`

## Regressão
Checker: qualquer controller em `dev/admin/controller` que chama `common/header` e não inclui `admin.css` falha → **119 verificações PASS**.

## Governança
- decision-matrix KZ-54 DECIDED; CHANGELOG + CHECKSUMS recalculado (3 PASS / 0 FAIL).
