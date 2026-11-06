# Relatório Final — Frente BK (correção do vazamento do KAZEditor-Theme)

**Data:** 2026-11-06 · **HEAD:** `ab8b76588010a5349930c6326e947e5af36fcb49`
**Gatilho:** `image-12.png` + HTML `<section class="rms-scene">` enviado pelo usuário.
**Classificação:** falha de produto confirmada (não é erro de CSS do tema).

## 1. Investigação

Cadeia causal completa em `DIAGNOSTICO-BK.md`. Resumo:
`ThemeSnapshot` → `ThemeSceneCss::compile($config['kaz_scene'])` →
`scene_view.enabled = (layers !== [])` →
`startup/theme.php:155` publica `catalog_theme_scene_view` →
`home.php:369` injeta `theme_scene_view` →
`home.twig:4` inclui `theme-scene-block.twig` no topo do `<main>` →
`<section class="rms-scene">` no site público.

O próprio editor (`kaz_theme_editor.twig:566`) documenta que a cena é
*"dado de edição e versionamento (`kaz_scene`), não renderização no site
público"*. A Frente AB contradiz essa garantia; a Frente AF (prévia sandbox
no Admin) é legítima.

**Abrangência:** os 4 pacotes entregues continham o include — BJ/BI/BG com
2 pontos cada e BH com **12**. O `common/home.twig` nativo do Catálogo
também o contém, então o bug atinge qualquer tema com `kaz_scene` visível.

## 2. Correção entregue

### A — 4 temas reexportados (aplicáveis já)

| Tema | Includes removidos | Camadas `kaz_scene` | Checksum novo |
|---|---|---|---|
| David Creator (BJ) | 2 | 3 | `f2e53d070b252e71b07e64128191f51a02c908642e5d3c8c8f16a051894354ff` |
| Atlas Studio (BI) | 2 | 3 | `dced34ce27247a9295f5185b4b5299e86bcb007c27b7748722f3a4fb3d972dd4` |
| Reamur Reference (BG) | 2 | 5 | `0197e1400ccadf51155d58371c49350448b72446cfdc7ddd51f921a18e15a9e5` |
| ThreeJS Reference (BH) | **12** | 24 | `1c6fc07ac9fb9995fead752dd642ea0c8b0cb831b2ed24320fc29a31dbd8c053` |

Mantidos: cores, conteúdo, CSS, `kaz_scene`, ThreeJS e
`partial/theme-scene.twig`. Removida **apenas** a linha do include.

### B — patch de runtime (PROPOSTA, não aplicado)

`docs/correcao/correcao-runtime.patch`: **4 linhas removidas em 3 arquivos**
do Catálogo (`common/home.twig`, `common/home.php`, `startup/theme.php`).
Admin intacto (4 pontos inventariados). Os 3 arquivos não constam em
`CHECKSUMS.txt`. Aplicação: `git apply …`. **Nenhuma escrita do agente no GitHub.**

## 3. Sobre "o KAZEditor-Theme não está alterando coisas dos temas inseridos"

Confirmado e explicado: a edição grava `kaz_scene` (`theme.php:651-667`),
que não altera `templates`, tokens nem layout renderizado; o único efeito no
site era o bloco avulso agora removido. Fazer a ferramenta editar o tema de
fato exige contrato novo (`kaz_scene` → `templates`/tokens), com mudança de
allowlist/validação/renderer e autorização do **Recorte B**. **Não decidido,
não executado, nenhuma decisão por inferência.**

## 4. Validação

| Verificação | Resultado |
|---|---|
| `ThemePackage::validateConfig()` / `export()` / `decode()` reais (PHP 8.4 WASM) | ✅ **32 OK / 0 FALHAS** nos 4 temas |
| Checksum canônico independente (Node, `ksort` recursivo) | ✅ 4/4 idênticos |
| 25 fontes por tema, `kaz_scene` preservado, Twig balanceado | ✅ 4×25 fontes |
| Nenhum `theme-scene-block.twig` restante | ✅ 0 ocorrências |
| `theme-scene.twig` declarativo mantido | ✅ 4/4 |
| Patch: 3 arquivos / 4 linhas / Admin intacto / sem contrato | ✅ |
| `tools/verify-no-scene-leak.cjs` | ✅ 0 FALHAS |
| Prova estrutural antes/depois | ✅ 1 stage+3 camadas → **0/0**; hero/CTA/footer/10 seções preservados |
| `evidencia/antes-depois.png` | ✅ captura real do usuário × prévia corrigida |
| CI remoto do HEAD | ✅ 7/7 success (lido, não disparado) |

**Ambiente:** nesta sessão `php-wasm-cli` e `/tmp/acbrowser` não
persistiram; reinstalamos `php-wasm@0.2.0` (PHP 8.4.1) em
`/home/user/phptest` com wrapper `php-cli.cjs`, e `playwright@1.56.0` em
`/home/user/browser`. O Chromium build 1194 foi reinstalado, mas o sandbox
desta sessão não dispõe de `libnspr4.so`/`libnss3` — por isso a prova visual
usa **a captura real enviada por você** e a prévia estática validada na BJ,
com medição estrutural determinística em vez de reexecução de browser. Isso
não altera a conclusão: o HTML colado e a cadeia de código são inequívocos.

## 5. O que NÃO foi mexido

Nenhum arquivo do repositório foi escrito pelo agente; nenhuma migration,
`schema.sql`, contrato congelado, `CHECKSUMS.txt`, workflow, `ThemePackage`,
`TEMPLATE_KEYS`, allowlist, renderer, DOM ou tradução. O patch é **proposta
em arquivo**, não commit. Nada é instalado automaticamente.

## 6. Limites e pendências

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Sem runtime/MySQL/staging/
deploy, sem converter `PENDING`/`NOT_RUN`/`BLOCKED_NOT_RUN` em `PASS`, sem
decidir D01–D10 nem o Recorte B. CI verde, PHP WASM, PIL e guardas estáticos
**não significam release aprovada**.

**Aceite visual:** a frente BJ **não** foi aprovada — você reportou esta
falha. Segue pendente, junto do health no Admin real e dos demais aceites.

## 7. Próximos passos

1. Reimportar os 4 temas corrigidos e aplicar o patch de runtime.
2. Conferir a home em modo anônimo (sem `<section class="rms-scene">`).
3. **Decidir** o contrato do KAZEditor-Theme: manter `kaz_scene` como dado de
   edição (estado atual, documentado) ou autorizar o **Recorte B** para que a
   ferramenta passe a gerar `templates`/tokens do tema inserido.
4. Roteiro pendente: health (2), D01–D10 com AR (3), F918 com AS (4).
