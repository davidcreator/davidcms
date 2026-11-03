# Frente K — Idioma por Site (continuação da J)

O roadmap **A→J está concluído** e aplicado no `main` 9e655b110. Ao pedir o
próximo passo, a K foi tratada como a **continuação funcional natural da J**,
não como uma nova frente do roadmap original: a J publicava
`catalog_theme_site_language`, mas `startup/language.php` o **ignorava** — o
idioma configurado por domínio nunca chegava a valer. A K fecha essa lacuna.
**Não é** auditoria, dossiê ou pacote de governança.

## Recorte

- **`startup/language.php`**: nova precedência — `?language=` (soberano,
  legado F2-3) → sessão com idioma ativo (legado F4-02) → **idioma do site**
  (novo) → `config_language_catalog` / `language_directory` → primeiro idioma
  ativo. `startup/theme` já roda **antes** de `startup/language` nas
  pre-actions, então o valor da J está disponível sem query nova.
- **Fail-closed**: o idioma do site só vale se estiver na allowlist
  `pt-br` / `en-us` / `es-eu` **e** constar em `getEnabledCodes()`
  (`status=1` no painel **e** diretório real). Valor ausente, vazio, inválido
  ou desativado ⇒ cai no default anterior. Nenhum dado do request é usado:
  o host já foi comparado e descartado pela J. Caixa é normalizada com o mesmo
  `strtolower` que o `?language=` legado.
- **Rastreabilidade**: `catalog_theme_language_source` publica apenas
  `request` / `session` / `site` / `default` (nenhum dado do visitante).
  A única escrita é a já existente `$session->data['language']`; a K não cria
  cookie, sessão, coluna, tabela, arquivo, cron ou escrita no banco.
- **Admin**: o card **Sites e domínios** mostra um badge com o idioma efetivo
  e o ID allowlistado do site ativo. **Nenhuma chave de idioma nova**
  (418 ×3 intactas), nenhum JS, CSS, schema ou manifesto novo.
- **Correção de robustez**: `buildDraftConfig()` emitia `Warning: Undefined
  array key "scene"` em qualquer POST sem o campo `scene` (latente desde A).
  Como `design/theme.php` já estava no pacote, o acesso passou a usar `?? ''`.
- Guia `docs/GUIA-IDIOMA-POR-SITE.md` (novo). 6 arquivos no ZIP,
  1 novo, arquivos completos, sem patches.

## Compatibilidade e limites

Sem `sites` no config ⇒ `catalog_theme_site_language` vazio ⇒ comportamento
**idêntico à J**. Se o visitante **já tem** idioma na sessão (ex.: navegou
antes noutro host com o mesmo cookie), a sessão prevalece — preservar o legado
teve prioridade sobre invalidar sessões; `?language=` sempre permite trocar.
Preservados: 6 presets D, 12 seções F, 10 componentes G, 5 campos/7 motivos H,
12 mídias/6 modelos I, 8 sites J, 25 templates, o hotfix, os 14 manifestos e
os contratos congelados. `Autoloader` real confirma `ThemeSites` e todas as
classes do `framework.php`. Sem `|raw`, `<script>`, `fetch`/`eval`, upload,
GLB, WebGL, BDREditor, `custom_js`, migration ou escrita remota.

## Validação executada

| Verificação | Resultado |
|---|---|
| `php -l` PHP 8.5 real nos 2 arquivos PHP | 2 OK / 0 FAIL |
| `node --check` (8 JS de A–J, nenhum novo) | 8 OK |
| **Autoloader real** (regressão hotfix + A–J) | 65 PASS / 0 FAIL |
| Precedência de idioma + pipeline `theme → language` | 64 PASS / 0 FAIL |
| Controller Admin + render Twig 3.14 | 42 PASS / 0 FAIL |
| **jsdom 29**: badge, sonda da J, degradação sem JS | 38 PASS / 0 FAIL |
| `verify-checksums` (A e B) | 14 manifestos / 142 arquivos / 0 divergentes |
| `repo-health` (A e B) | 6 PASS / 0 FAIL / 0 SKIP |
| `verify-ci-workflow` | 31 PASS / 0 FAIL |
| `verify-contract-freeze` | exit 0 |

**Total: 209 asserções, 0 falhas.** 39 arquivos congelados
conferidos por hash antes/depois (todos os 15 da J, os 3 idiomas, `schema.sql`,
`home.twig`, `twig.php`, `autoloader.php`, `framework.php`, `startup/theme.php`,
`common/header.php`, engines, runtime e guias). Os 14 `CHECKSUMS*.txt`
permanecem **idênticos** e não foram reemitidos. Nenhum contrato congelado,
`release_approved`, workflow, checker ou runtime WebGL foi modificado. Nenhuma
escrita remota. F9-27 segue `BLOCKED_NOT_RUN`; BDREditor pausado; P2 do F9-18
não reaberto. Nenhum `Deprecated:`/`Warning:` no PHP 8.5 WASM.

PHP 8.5 via WASM + Twig 3.14 + jsdom com mocks de DB/sessão/response. **Não**
houve CMS completo, MySQL real, sessão Admin autenticada, browser com rede,
DNS/vhost, WebGL, E2E, migration, staging, deploy ou escrita no GitHub.
**CI verde e health verde ≠ release aprovada.**

Aplicar: substituir pelos 4 arquivos completos (o guia é novo) e rodar
`bash tools/verify-checksums.sh && bash tools/repo-health.sh`.
