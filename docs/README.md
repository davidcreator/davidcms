# Documentação — ReamurCMS

Índice central da pasta `docs/`, organizado por domínio técnico, guias, sistema de temas, Three.js, integrações, fases e histórico.

---

## Mapa de pastas

| Pasta | Conteúdo |
| --- | --- |
| **Raiz (`docs/`)** | Índice (`README.md`), [CHANGELOG.md](CHANGELOG.md), [Estrutura-de-Pastas.md](Estrutura-de-Pastas.md) e os **10 artefatos congelados** por SHA-256 em `tools/decision-matrix.json` |
| [`arquitetura/`](#arquitetura-e-fundação) | MVCL, Contrato de Bootstrap, Dicionário de Dados e Auditoria de Fundação |
| [`guias/`](#guias-técnicos) | Análise de migração, Guia CMS, PHP 8+, Composer, Twig, Upload de mídia e Formatos de importação |
| [`temas/`](#temas-design-system-e-frentes-at) | Presets `.reamurtheme.json`, guias principais de temas e `guias/` (21 guias das Frentes A–T) |
| [`threejs/`](#threejs-e-cenas-3d) | Guias, runtime nativo, thumbs, admin preview, E2E 3D e especificação de cena |
| [`integracoes/`](#integrações-e-operação) | Webhooks, Mercado Pago Marketplace e Runbook de cutover (F6) |
| [`fases/`](#fases-do-projeto) | Registros, planos e contratos das fases [`f7-f8/`](#fases-7-e-8-painel-e-snippets), [`f9/`](#fase-9-bdr-editor-e-campos-personalizados) e [`f11/`](#fase-11-ci-dependências-e-governança) |
| [`historico/`](#histórico-e-consolidados-legados) | Relatórios consolidados e pacotes antigos preservados para consulta |

---

## Comece aqui (Raiz)

- [PROJECT-HANDOFF.md](PROJECT-HANDOFF.md) — estado do projeto, pausa do BDREditor em F9-103 e restrições permanentes *(congelado em `decision-matrix.json`)*
- [CHANGELOG.md](CHANGELOG.md) — histórico de entregas
- [Estrutura-de-Pastas.md](Estrutura-de-Pastas.md) — layout completo do repositório

---

## Arquitetura e Fundação

- [arquitetura/Arquitetura-MVCL.md](arquitetura/Arquitetura-MVCL.md) — ciclo de vida da requisição, Engine, Registry, Loader e Events
- [arquitetura/Contrato-Bootstrap.md](arquitetura/Contrato-Bootstrap.md) — contrato dos pontos de entrada (`index.php`) e constantes
- [arquitetura/Dicionario-de-Dados.md](arquitetura/Dicionario-de-Dados.md) — schema canônico do banco de dados
- [arquitetura/Auditoria-Fundacao.md](arquitetura/Auditoria-Fundacao.md) — auditoria de fundação (lacunas L1–L10)

---

## Guias Técnicos

- [guias/Analise-DavidAlmeida.md](guias/Analise-DavidAlmeida.md) — inventário do site atual e plano oficial de migração para o ReamurCMS
- [guias/Guia-CMS.md](guias/Guia-CMS.md) — módulos do CMS, conteúdo, ACL, SEO e segurança
- [guias/Guia-PHP8.md](guias/Guia-PHP8.md) — PHP 8+ no núcleo e extensões obrigatórias
- [guias/Guia-Composer.md](guias/Guia-Composer.md) — Composer, autoload, dependências e CI
- [guias/Guia-Twig.md](guias/Guia-Twig.md) — Twig 3, herança de templates e adaptadores
- [guias/BLOG_MEDIA_UPLOAD.md](guias/BLOG_MEDIA_UPLOAD.md) — upload e segurança de mídia no Blog
- [guias/IMPORT_FORMATS.md](guias/IMPORT_FORMATS.md) — formatos aceitos pelo importador do Blog

---

## Temas, Design System e Frentes A–T

Veja o índice dedicado em **[temas/README.md](temas/README.md)** com os 6 presets `.reamurtheme.json`, os 4 guias principais (`GUIA-TEMA-*.md`), [Pages-Temas-Revisao-HEAD-2f28.md](temas/Pages-Temas-Revisao-HEAD-2f28.md) e todos os **21 guias funcionais** em `temas/guias/`.

---

## Three.js e Cenas 3D

- [threejs/Guia-ThreeJS.md](threejs/Guia-ThreeJS.md) — guia geral de cenas 3D no front-end
- [threejs/Implementacao-ThreeJS.md](threejs/Implementacao-ThreeJS.md) — plano prático, budgets e arquitetura `SceneRunner`
- [threejs/THREEJS_NATIVE_SYSTEM.md](threejs/THREEJS_NATIVE_SYSTEM.md) — arquitetura do sistema nativo em `system/library/three/`
- [threejs/THREEJS_THUMB.md](threejs/THREEJS_THUMB.md) — thumbnails WebGL declarativas
- [threejs/THREEJS_ADMIN_PREVIEW.md](threejs/THREEJS_ADMIN_PREVIEW.md) — prévia isolada no painel Admin
- [threejs/THREEJS_CMS_E2E.md](threejs/THREEJS_CMS_E2E.md) — verificação E2E do Three.js no CMS
- [threejs/THREEJS_SANDBOX_ISOLATED_E2E.md](threejs/THREEJS_SANDBOX_ISOLATED_E2E.md) — testes E2E do iframe sandbox isolado
- [threejs/ESPECIFICACAO-CENA.md](threejs/ESPECIFICACAO-CENA.md) · [threejs/especificacao.json](threejs/especificacao.json) — especificação declarativa de cenas

---

## Integrações e Operação

- [integracoes/Webhooks.md](integracoes/Webhooks.md) — engine de webhooks (pedidos e produtos) e assinatura HMAC
- [integracoes/MercadoPago-Marketplace.md](integracoes/MercadoPago-Marketplace.md) — fundação OAuth e splits Mercado Pago
- [integracoes/Runbook-Cutover-F6.md](integracoes/Runbook-Cutover-F6.md) — runbook de cutover e verificação em produção

---

## Fases do Projeto

### Fases 7 e 8 (Painel e Snippets)

- [fases/f7-f8/F7-03-301-snippets.md](fases/f7-f8/F7-03-301-snippets.md)
- [fases/f7-f8/F8-05-A01-Inicializacao-Biblioteca-Midia.md](fases/f7-f8/F8-05-A01-Inicializacao-Biblioteca-Midia.md) · [F8-05-A02](fases/f7-f8/F8-05-A02-Configuracao-Isolada-CKEditor.md) · [F8-05 Validação Integrada](fases/f7-f8/F8-05-Validacao-Integrada-Blog.md)
- [fases/f7-f8/F8-06-Paginas-CMS.md](fases/f7-f8/F8-06-Paginas-CMS.md) · [F8-07 Categorias](fases/f7-f8/F8-07-Categorias-Portfolio.md) · [F8-08 Projetos](fases/f7-f8/F8-08-Projetos-Portfolio.md)
- [fases/f7-f8/F8-09-Ferramentas-Conectadas.md](fases/f7-f8/F8-09-Ferramentas-Conectadas.md) · [F8-10 Mensagens](fases/f7-f8/F8-10-Mensagens-Contato.md) · [F8-11 Idiomas](fases/f7-f8/F8-11-Idiomas-no-Painel.md)
- [fases/f7-f8/F8-12-Consolidacao-Validacao-Painel.md](fases/f7-f8/F8-12-Consolidacao-Validacao-Painel.md) · [F8-12-Aceite-Painel.json](fases/f7-f8/F8-12-Aceite-Painel.json)

### Fase 9 (BDREditor, Blog Blocos, Migração e Campos Personalizados)

> **Contratos congelados por SHA-256 na raiz de `docs/`** *(regra F11-11 — `tools/decision-matrix.json`; não mover sem decisão registrada)*:
> [F9-05](F9-05-Contrato-Definicoes-Campos.json) · [F9-14](F9-14-Contrato-Editor-MVP.json) · [F9-15](F9-15-Contrato-Renderer-Publico.json) · [F9-18-Risk-Register](F9-18-Risk-Register.json) · [F9-25](F9-25-Contrato-Editor-Editorial.json) · [F9-26](F9-26-Contrato-A11y-Performance.json) · [F9-27-Base-Hashes](F9-27-Base-Hashes.json) · [F9-27-Preflight-Status](F9-27-Preflight-Status.json) · [F9-27-Status](F9-27-Status.json)

O projeto do BDREditor está **pausado em F9-103** por determinação do responsável; consulte [PROJECT-HANDOFF.md](PROJECT-HANDOFF.md) antes de retomar.

- **Campos personalizados & Migração DavidAlmeida:** [F9-01](fases/f9/F9-01-Campos-Personalizados.md) ([contrato](fases/f9/F9-01-Contrato-Campos-Personalizados.json)) · [F9-02](fases/f9/F9-02-Desenho-Campos-Personalizados.md) · [F9-03 Inventário](fases/f9/F9-03-Inventario-Banco-DavidAlmeida.json) · [F9-04 Mapa Migração](fases/f9/F9-04-Mapa-Migracao-DavidAlmeida.json)
- **Blog Blocos & Parser:** [F9-11](fases/f9/F9-11-Editor-de-Blocos-Blog.md) ([contrato](fases/f9/F9-11-Contrato-Editor-Blocos-Blog.json)) · [F9-12 Contrato](fases/f9/F9-12-Contrato-Persistencia-Blog-Blocos.json) · [F9-13](fases/f9/F9-13-Parser-Serializer-Conversor-Legado.md) ([contrato](fases/f9/F9-13-Contrato-Parser-y-Conversion-Blog.json)) · [F9-14 MVP](fases/f9/F9-14-Editor-de-Blocos-MVP.md) · [F9-15 Renderer](fases/f9/F9-15-Renderer-Publico-Blog-Blocos.md) · [F9-17 Contrato E2E](fases/f9/F9-17-Contrato-E2E-Blog-Blocos.json)
- **BDREditor, Revisão e Release Gate:** [F9-18 Revisão Estática](fases/f9/F9-18-Relatorio-Revisao-Estatica.md) · [F9-27 Release Gate](fases/f9/F9-27-Release-Gate.md) · [F9-27 Preflight](fases/f9/F9-27-Preflight-Execucao.md) · [F9-28 Rascunhos](fases/f9/F9-28-Recuperacao-Rascunhos-BDRE.md) · [F9-31 Histórico](fases/f9/F9-31-Historico-Persistente-BDRE.md) · [F9-47 Auditoria Funcional](fases/f9/F9-47-Auditoria-Funcional-BDREditor.md) · [F9-71 Vertical Spacer](fases/f9/F9-71-Vertical-Spacer.md) · [F9-75 Interface](fases/f9/F9-75-BDREditor-Interface.md) · [F9-103 Acessibilidade](fases/f9/F9-103-Media-Accessibility-Audit.md) · [Referência Completa BDREditor](fases/f9/BDREditor-Complete-Reference-and-Closure.md)

### Fase 11 (CI, Dependências e Governança)

- [fases/f11/F11-01-CI-e-Dependencias.md](fases/f11/F11-01-CI-e-Dependencias.md) — layout de dependências em `dev/system/storage/`
- [fases/f11/F11-02-Verificacao-Dependencias.md](fases/f11/F11-02-Verificacao-Dependencias.md) — verificação de `vendor/` e `node_modules/`
- [fases/f11/F11-04-Modernizacao-CI.md](fases/f11/F11-04-Modernizacao-CI.md) — modernização do workflow CI
- [fases/f11/F11-05-Hotfix-Repo-Guards.md](fases/f11/F11-05-Hotfix-Repo-Guards.md) — hotfix do `repo-guards`
- [fases/f11/F11-07-Inventario-Checkers.md](fases/f11/F11-07-Inventario-Checkers.md) — inventário e estabilidade de checkers
- [fases/f11/F11-08-Hotfix-Docs-Links.md](fases/f11/F11-08-Hotfix-Docs-Links.md) — performance do `docs-links`
- [fases/f11/F11-09-Triagem-Marcadores.md](fases/f11/F11-09-Triagem-Marcadores.md) — triagem dos 10 marcadores pendentes
- [fases/f11/F11-10-Links-Quebrados.md](fases/f11/F11-10-Links-Quebrados.md) — correção de links quebrados no CI

---

## Histórico e Consolidados Legados

Documentos de registro histórico e pacotes consolidados anteriores:

- [historico/Analise-DavidAlmeida-2026-11-09.md](historico/Analise-DavidAlmeida-2026-11-09.md) — cópia datada da análise
- [historico/FRENTES-CONSOLIDADO.md](historico/FRENTES-CONSOLIDADO.md) — consolidado histórico de frentes
- [historico/RELATORIO-GERAL.md](historico/RELATORIO-GERAL.md) — relatório geral de entrega anterior
- [historico/GUIA-BO.md](historico/GUIA-BO.md) — guia resumido de back-office do pacote legado

---

## Ferramentas de verificação

- [`tools/verify-deps-layout.sh`](../tools/verify-deps-layout.sh) — layout de `vendor/` e `node_modules/`
- [`tools/verify-checksums.sh`](../tools/verify-checksums.sh) — integridade dos manifestos `CHECKSUMS*.txt`
- [`tools/verify-ci-workflow.sh`](../tools/verify-ci-workflow.sh) — contrato do `.github/workflows/php.yml`
- [`tools/verify-build-prod.sh`](../tools/verify-build-prod.sh) — gera `prod/` e prova que a build não leva pacotes de desenvolvimento (ADR 0092; precisa do `vendor/` instalado)
- [`tools/verify-checkers-inventory.py`](../tools/verify-checkers-inventory.py) — estabilidade do conjunto de checkers
- [`tools/verify-contract-freeze.py`](../tools/verify-contract-freeze.py) — congelamento dos 11 artefatos em `decision-matrix.json`
- [`tools/verify-evidence-refs.py`](../tools/verify-evidence-refs.py) — integridade de `tools/decision-evidence.json`

> O CI verde **não** significa release aprovada: F9-27 segue `BLOCKED_NOT_RUN` e `release_approved: false`.
