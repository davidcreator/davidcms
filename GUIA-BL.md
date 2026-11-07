# Guia operacional — BL (ordem exata)

## Resumo em uma frase

**Os JSONs em `docs/temas/` e o `.patch` são arquivos; o Admin e o Catálogo
leem a tabela `design_theme`.** Nada do que foi commitado em `3219ccae…` foi
aplicado ao runtime. Por isso "atualizar as informações" não mudou o site.

## 0. Diagnosticar (30 segundos, somente leitura)

Edite `<PREFIX>` em `tools/diagnostico-tema.sql` e rode no MySQL. Anote:

- qual `theme_id` tem `ativo = 1`;
- se `include_no_publicado = 1` (é o que o site está renderizando);
- se `include_no_draft = 0` (você já corrigiu o rascunho, mas não publicou);
- se `templates_no_draft = 0` (é por isso que o editor mostra fontes nativos).

## 1. Aplicar os patches (na raiz do repositório)

```bash
git apply docs/correcao/A-vazamento-failclosed.patch
git apply docs/correcao/B-editor-dados-reais.patch
```

- **A** fecha o vazamento de forma **fail-closed**: mesmo com o
  `published_config` antigo no banco, o stage não renderiza no Catálogo.
- **B** faz o editor carregar os dados reais do tema e impede que um Salvamento
  reintroduza o include.

Verifique com `git diff --stat`: 5 arquivos no patch A, 2 no patch B.
Nenhum deles está em `CHECKSUMS.txt`.

## 2. Limpar o cache de templates

```bash
rm -rf storage/cache/template/*
```

`twig.php::setThemeTemplates()` reconstrói o `ChainLoader`; se o cache estiver
ligado, sem limpar você continua vendo o HTML antigo.

## 3. Publicar o tema (o passo que faltou)

Admin → **Temas do Catálog**:

1. Abra o **tema que está ATIVO** (o `theme_id` do passo 0) — não crie outro.
2. **Importar tema** → selecione o `.json` corrigido correspondente
   (`David-Creator.reamurtheme.json` etc.).
3. Confira a prévia: 25 fontes, sem include de `theme-scene-block`.
4. **ATIVAR**. ⚠️ Sem este clique, `save()`/`import()` gravaram apenas
   `draft_config` e o Catálogo continua no `published_config` antigo.

> Se você importou pela lista (`Importar tema` sem abrir um registro), foi
> criado um **novo tema inativo**. Ative esse novo (e desative o antigo) ou
> refaça a importação dentro do registro ativo.

## 4. Conferir

- Aba anônima / Ctrl+F5 → **não** deve existir
  `<section class="rms-scene">` antes do hero.
- DevTools → Elementos → procurar `data-rms-layer` → **0 resultados**.
- Admin → editar o tema → aba **Templates** deve mostrar os fontes **do tema**;
  os nativos aparecem desabilitados com o aviso *"Fonte nativa do disco"*.

## 5. Verificar no código

```bash
node tools/verify-bl.cjs .
```

## O que NÃO fazer

- Não editar `published_config` diretamente no banco.
- Não rodar `UPDATE`/`ALTER`/migration.
- Não reimportar sem **Ativar**.
- Não salvar no editor antes do patch B (reintroduz o include).
- Não assumir que commitar um `.json` em `docs/` instala um tema.

## Limites

`release_approved: false` · F9-27 `BLOCKED_NOT_RUN` · F9-18
`release_ready: false` · 10 `PENDING_DECISION`. Nenhuma escrita do agente no
GitHub, nenhum deploy/runtime executado por mim. CI verde, PHP WASM e guardas
**não significam release aprovada**. Aceite visual e health no Admin real
ainda pendentes.
