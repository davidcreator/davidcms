# Guia de correção — BK (stage do editor no front)

## O que fazer agora (2 passos)

### 1) Reimportar os 4 temas corrigidos

Na ordem que preferir, em **Admin → Temas do Catálog → Importar tema**:

| Arquivo | Checksum (novo) | Includes removidos |
|---|---|---|
| `docs/temas/David-Creator.reamurtheme.json` | `f2e53d07…` | 2 |
| `docs/temas/Atlas-Studio.reamurtheme.json` | `dced34ce…` | 2 |
| `docs/temas/Reamur-Reference.reamurtheme.json` | `0197e140…` | 2 |
| `docs/temas/Reamur-ThreeJS-Reference.reamurtheme.json` | `1c6fc07a…` | 12 |

São os **mesmos temas** — cores, conteúdo, CSS, `kaz_scene` e ThreeJS
inalterados. Só saiu a linha que chamava o stage do editor. O checksum mudou
porque o `config` mudou; cada pacote foi reexportado por
`ThemePackage::export()` e revalidado por `decode()`.

> Se algum tema já estiver ativo, importar como rascunho → revisar → **Ativar**
> substitui o `common/home`. Nada é publicado automaticamente.

### 2) Aplicar o patch de runtime (recomendado)

```bash
git apply docs/correcao/correcao-runtime.patch
```

Remove 4 linhas em 3 arquivos do **Catálogo**. Sem migration, schema,
contrato ou manifesto. O Admin (prévia sandbox e comparação de temas)
continua mostrando a cena normalmente.

**Por que os dois passos?** O passo 1 resolve os temas que existem hoje.
O passo 2 fecha a torneira para **qualquer tema futuro** e para os temas
nativos do Catálogo (que usam o `common/home.twig` do sistema).

## Verificar

```bash
node tools/verify-no-scene-leak.cjs <raiz>
node tools/verify-checksums.sh
bash tools/repo-health.sh
```

Abra a home em modo anônimo e confira que **não existe** mais
`<section class="rms-scene">` antes do hero.

## O que NÃO mudou

- `kaz_scene` continua salvo em cada tema (3/3/5/24 camadas) e editável no
  KAZEditor-Theme;
- `partial/theme-scene.twig` (slot declarativo ThreeJS, só `data-*`) continua;
- `twig.php`, `theme_compare_view.php`, `theme.php` do Admin e o próprio
  `theme-scene-block.twig` **não foram tocados**;
- nenhuma migration, `schema.sql`, contrato congelado, `CHECKSUMS.txt`,
  workflow, `ThemePackage`, allowlist ou renderer;
- nenhum arquivo `.twig`/`.css`/`.php` do repositório foi escrito pelo agente.

## Sobre a segunda observação

Você notou que o **KAZEditor-Theme não está alterando coisas dos temas
inseridos**. Confirmamos: a edição grava `kaz_scene`, que é dado de
composição; ela não altera `templates`, tokens nem layout do tema, e o único
efeito no site era o bloco solto que agora foi removido. Fazer a ferramenta
editar de fato o tema exige definir um contrato novo (`kaz_scene` →
`templates`/tokens), o que muda allowlist/validação/renderer e depende de
autorização explícita do **Recorte B**. **Não foi decidido nem executado
nesta frente.**

## Governança

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Sem escrita do agente no
GitHub, sem runtime/MySQL/staging/deploy. CI verde, PHP WASM e guardas
estáticos **não significam release aprovada**; aceite visual no Admin real
ainda pendente.
