# F8-05 · Ajuste 02 — Configuração isolada do CKEditor

> Objetivo: inicializar o CKEditor local do Blog sem executar a configuração legada dependente de jQuery, preservando o formulário multilíngue e a biblioteca de capas do Ajuste 01.

**Data:** 2026-10-23 · **Agente:** admin · **Estado:** entrega preparada; aplicação e homologação no ambiente do usuário pendentes.

## Sumário

1. [Escopo e base](#1-escopo-e-base)
2. [Diagnóstico e decisão](#2-diagnóstico-e-decisão)
3. [Arquivos do pacote](#3-arquivos-do-pacote)
4. [Aplicação e cache](#4-aplicação-e-cache)
5. [Tool E2E e resultados](#5-tool-e2e-e-resultados)
6. [Homologação e limites](#6-homologação-e-limites)
7. [Rollback](#7-rollback)
8. [Pendências e próximo passo](#8-pendências-e-próximo-passo)

## 1. Escopo e base

- Repositório: `davidcreator/davidcms`.
- Base imutável: **`c2df68993259e53fcc05080e6ea42971de7eebd8`** (`main` consultada em 2026-10-23).
- Commit-base: `fix(admin): wait for DOMContentLoaded in blog media library`.
- O usuário confirmou que o **Ajuste 01** foi aplicado com sucesso. O commit público contém essa entrega; isso não informa quais testes de navegador/servidor o usuário executou.
- Esta entrega contém **um único arquivo de runtime alterado**: `dev/admin/view/js/admin.js`, exclusivamente no inicializador de `textarea[data-rte]`.
- Não há clone do projeto, migração, mudança de schema, endpoint, ACL/CSRF, importador, vendor, licença ou versão do editor. Não há escrita remota, commit/PR ou deploy efetuado por esta entrega.

**Não reaplicar o ZIP A01.** Esta é uma entrega incremental sobre a base que já o contém. A Tarefa 05/Fase 8 não está encerrada; a validação integrada permanece pendente.

## 2. Diagnóstico e decisão

### Problema confirmado no código

`dev/admin/view/js/ckeditor/config.js` define `CKEDITOR.editorConfig` e tenta executar `$(this).attr('data-lang')`, embora o shell não carregue jQuery. Também habilita `reamur,codemirror` e desabilita a verificação de versão.

O plugin `dev/admin/view/js/ckeditor/plugins/reamur/plugin.js` depende de jQuery/modal antigo e aponta para `common/filemanager`, rota ausente no projeto consultado. A biblioteca de **capas do Blog** não é essa integração e não deve ser usada como substituição implícita dentro do editor.

No core local **4.22.1**, `customConfig` assume `config.js`. O valor local dessa opção é considerado antes do carregamento; o callback legado executa antes dos demais overrides de `replace()`. Portanto, apenas alterar `extraPlugins` ou `removePlugins` não impediria o erro com `$`.

### Correção cirúrgica

O inicializador em `dev/admin/view/js/admin.js` passa a:

1. Usar **`customConfig: ''`** para não carregar/executar o arquivo legado nas instâncias `data-rte`.
2. Manter `extraPlugins: ''`, excluir explicitamente `reamur,codemirror` em `removePlugins` e deixar vazias as URLs de filebrowser/upload. Os plugins padrão do build local continuam disponíveis.
3. Definir o idioma da **interface do editor** a partir de `<html lang>`, fornecido pelo shell, sem jQuery ou `data-lang` no textarea:

   | Idioma do painel | Idioma local do CKEditor |
   | --- | --- |
   | `en-us` | `en` |
   | `pt-br` | `pt-br` |
   | `es-eu` | `es` |
   | Ausente/desconhecido | `en` |

   Caixa e underscores são normalizados. O mapa só aceita chaves próprias, incluindo fallback seguro para uma chave como `constructor`. Isso **não traduz o conteúdo**, não altera `language_id` e não muda os códigos usados pelas tabs.
4. Preservar a toolbar existente, altura 320, formatos `p;h2;h3;h4`, redimensionamento habilitado e `allowedContent: true`. Explicitar `autoUpdateElement: true`, `htmlEncodeOutput: false` e `entities: false`, mantendo o contrato de HTML sem acrescentar uma serialização paralela.
5. Aguardar `DOMContentLoaded` caso o script seja executado cedo; ignorar campo já marcado/instância existente e não marcar `replace()` sem retorno de instância.
6. Em **falha síncrona** de `replace()`, tentar destruir a instância parcial com `destroy(true)` — sem atualizar/apagar o textarea — e restaurar seus estilos de visibilidade. A ausência do CKEditor/`replace` mantém o campo original.

A configuração e os arquivos vendor permanecem intactos: trata-se de isolamento do consumidor atual, não de saneamento global do pacote legado. `build-config.js` é metadado do builder, não a configuração runtime a corrigir.

**Preservados:** `id`/`name`/conteúdo dos textareas; submit nativo e atualização pelo CKEditor; temas/sidebar, delete, contadores, tabs/status e preview; biblioteca de capas/`blog-media.js` A01; contratos de controller/model/Twig. Não se cria outro listener de submit nem se troca a ação do formulário.

O botão **Image** da toolbar continua para o diálogo padrão/URL. Ele não passa a usar a biblioteca de capas e não ganha upload/filemanager. A toolbar **Source** usa o plugin padrão, não CodeMirror.

## 3. Arquivos do pacote

Aplicar somente os seis arquivos de projeto:

| Caminho | Alteração |
| --- | --- |
| `dev/admin/view/js/admin.js` | Substitui somente o bloco de inicialização RTE. |
| `tools/e2e/e2e_f805_a02_ckeditor.sh` | Tool Bash seguro: sintaxe, regressão A02 e opção de regressão A01. |
| `tools/e2e/e2e_f805_a02_ckeditor_dom.cjs` | 18 casos de contrato com DOM/CKEDITOR simulados; Node sem dependências externas. |
| `agents-ia/decisoes/0046-f8-02-blog-no-shell-filtros-paginacao-ckeditor-e-import-reestilizado.md` | Adendo de implementação A02, sem reescrever a decisão/histórico anteriores. |
| `docs/F8-05-A02-Configuracao-Isolada-CKEditor.md` | Este guia. |
| `docs/README.md` | Entrada no índice. |

Auxiliares de entrega: `F8-05-A02.patch`, `MANIFESTO-F8-05-A02.json` e `SHA256SUMS`. Não são dependências do runtime nem precisam ser copiados para o site.

O manifesto identifica base, caminhos e hashes antes/depois. `SHA256SUMS` cobre os seis arquivos, o patch e o manifesto; não contém hash de si próprio. O ZIP não carrega o projeto completo, assets do editor, mídia, configuração de ambiente ou credenciais.

## 4. Aplicação e cache

### 4.1 Preparação

1. Trabalhe em branch de alteração, com cópia/backup dos arquivos e sem alterações locais concorrentes nos alvos.
2. Confira `git rev-parse HEAD`: a base esperada é `c2df68993259e53fcc05080e6ea42971de7eebd8`. Se divergir, pare e revise o diff e os hashes-base do manifesto; não sobrescreva alterações posteriores.
3. Extraia o ZIP em **diretório vazio fora da raiz do projeto**. Não o descompacte por cima do repositório antes da verificação.

Exemplo, ajustando o caminho real do ZIP:

```bash
ENTREGA="$(mktemp -d /tmp/F8-05-A02.XXXXXX)"
unzip /caminho/F8-05-A02.zip -d "$ENTREGA"
(cd "$ENTREGA" && sha256sum -c SHA256SUMS)
```

### 4.2 Patch preferencial

Na raiz do repositório, mantendo `ENTREGA` apontando à extração:

```bash
git status --short
git apply --check --whitespace=error "$ENTREGA/F8-05-A02.patch"
git apply --whitespace=error "$ENTREGA/F8-05-A02.patch"
git diff --check
git diff --stat
bash tools/e2e/e2e_f805_a02_ckeditor.sh --regression
```

Os arquivos novos podem aparecer como untracked até serem adicionados ao Git; revise também `git status --short`. O patch inclui código, testes e documentação, não os três auxiliares.

Alternativa manual: copie **apenas os seis arquivos listados**, preservando seus caminhos. Não use `--delete`, não substitua pastas completas e não sobrescreva `blog-media.js` ou o diretório do CKEditor.

### 4.3 Cópia servida e cache

Alterar `dev/` não comprova que o ambiente já está servindo a nova versão. Sincronize **apenas o asset alterado** pelo fluxo de deploy controlado do projeto e confira o `admin.js` efetivamente recebido no navegador.

- O pacote **não altera o `ASSET_V` global** do header.
- Invalide o cache de `admin.js` no mecanismo que efetivamente o armazena (navegador/proxy/CDN, se houver), e recarregue com cache desativado para homologar.
- Procure `customConfig: ''` no bloco RTE do asset servido.
- Em Blog add/edit, as instâncias `data-rte` não devem solicitar o `config.js` legado nem os plugins `reamur`/`codemirror`.
- Não rode builds, cópias integrais ou limpezas de mídia como parte deste ajuste. Não foi auditado/executado deploy nesta rodada.

Sugestão de commit, após a revisão e homologação:

```text
fix(admin): isolar configuração legada do CKEditor no blog
```

## 5. Tool E2E e resultados

### Comandos seguros

```bash
# A02: sintaxe + 18 casos de contrato JS/DOM/CKEDITOR simulados
bash tools/e2e/e2e_f805_a02_ckeditor.sh --dom

# A02 + os 10 casos DOM simulados da biblioteca de capas A01
bash tools/e2e/e2e_f805_a02_ckeditor.sh --regression

bash tools/e2e/e2e_f805_a02_ckeditor.sh --help
```

Requisitos: **Node.js 20+ e Bash**. Não instala pacotes, inicia servidor, abre navegador ou acessa rede/banco. Não precisa de PHP/Composer/npm. `--regression` depende dos três arquivos A01 já presentes no projeto-base; sua ausência provoca saída 2, sem baixar nada.

Variáveis opcionais: `NODE_BIN`, `MIRROR` (pasta `dev` alternativa), `ADMIN_JS_SOURCE`. No modo A01 valem também as opções já documentadas, como `BLOG_MEDIA_SOURCE`.

O wrapper **não tem modo backend**: `--backend` é recusado (saída 2). Não chama a suíte F8-05 destrutiva nem autoriza recriação de `reamur_test`. Não há shell/HTTP/banco disparado pelo runner CJS; somente leitura de fontes locais/stdin e execução do JS na fixture de teste.

### Cobertura dos 18 casos

Sem `$`/jQuery e sem configuração herdada; en/pt-BR/es; normalização e fallback; readiness `loading/interactive/complete`; editor ausente/incompleto; falha síncrona/cleanup; retorno nulo; marcador/instância existente; plugins/URLs; toolbar e opções de HTML; ids/names/conteúdo preservados; tabs e preview da capa fora do bloco RTE.

O teste executa o **`admin.js` real inteiro**, mas **DOM e CKEDITOR são simulados**. A fila reproduz apenas a ordem relevante da configuração observada no core 4.22.1; não executa o core, os plugins nem o iframe do editor. Quando o vendor existe no checkout, lê o `config.js` local como entrada da fixture. No ZIP incremental sem vendor, identifica no relatório o recorte mínimo com a linha `$` usado no lugar dele.

### Controle negativo reproduzível

Após aplicar o pacote em checkout que contém a base:

```bash
# DEVE falhar com saída 1: testa o JS anterior, sem alterar nenhum arquivo.
git show c2df68993259e53fcc05080e6ea42971de7eebd8:dev/admin/view/js/admin.js \
  | node tools/e2e/e2e_f805_a02_ckeditor_dom.cjs -
```

Saída 1 nesse controle significa **regressão detectada**, não falha do pacote corrigido. Execute-o separadamente de automações que tratam qualquer saída não zero como erro inesperado. O runner também aceita `--stdin-json` com strings `admin` e `legacyConfig`, para fornecer ambas as fontes em memória; não faz download.

### Resultados desta preparação

| Verificação | Resultado |
| --- | --- |
| Sintaxe `admin.js` e runner CJS | Aprovada com `node --check`. |
| Sintaxe wrapper Bash | Aprovada com `bash -n`. |
| A02 na fixture incremental sem vendor | **18 PASS / 0 FAIL**. |
| A02 com o config legado real fornecido em memória | **18 PASS / 0 FAIL**. |
| Controle negativo com admin.js/config.js originais do SHA-base | **4 PASS / 14 FAIL**, saída **1**, esperada. |
| Regressão A01 DOM simulada | **10 PASS / 0 FAIL**; `blog-media.js` local comparado byte a byte com a base pública. |

Execução em Node **20.20.2**. Resultados são locais, não de GitHub Actions. Os números históricos da ADR 0046 não são testes executados nesta rodada.

**Não verificados por esses testes:** renderização real do editor/skin/diálogos, eventos reais de submit e serialização do core, respostas PHP, ACL/CSRF de servidor, upload, persistência, reabertura, produção ou deploy. A fixture não é uma barreira de segurança para executar código arbitrário não confiável.

## 6. Homologação e limites

Após aplicar e invalidar cache, em ambiente de homologação:

- [ ] Abrir Blog → criar e editar; conferir editores locais nos três idiomas, toolbar enxuta e alternância de tabs.
- [ ] Console sem `$ is not defined` vindo de `ckeditor/config.js`; sem tentativa de `common/filemanager` pelo RTE.
- [ ] Trocar o idioma da interface do painel e confirmar en/pt-BR/es sem mudar os ids/names dos campos de conteúdo.
- [ ] Conferir Bold, listas, links e Source; conteúdo/acentos existentes continuam visíveis. A serialização efetiva e a persistência serão exercitadas na Validação 03.
- [ ] O diálogo padrão Image não oferece a integração legada nem usa automaticamente a biblioteca de capas.
- [ ] A biblioteca de capas A01 continua abrindo; o campo `image` e seu preview permanecem no contrato atual.
- [ ] Conferir tema/sidebar/delete/contadores/status sem regressão visual. O diff preserva esses blocos; não houve teste visual real nesta preparação.
- [ ] Em laboratório, com o request do CKEditor bloqueado antes do reload, verificar que o textarea original segue disponível. Não usar posts/dados reais para teste de falhas.

**Limite importante do fallback:** guardas e `try/catch` cobrem ausência/incompatibilidade e erros **síncronos** de `replace()`. Um erro posterior no carregamento de skin, idioma ou plugin pode ocorrer depois do retorno e não tem recuperação automática garantida por este ajuste. `data-rte-ready=1` indica que `replace()` devolveu uma instância, não é prova de `instanceReady`/renderização concluída.

Não foram instalados navegador/Playwright/Puppeteer, PHP ou servidor nesta preparação. Não se deve registrar estes testes como E2E de navegador ou aprovação de persistência.

## 7. Rollback

Se for necessário reverter **somente A02**, mantendo A01:

```bash
# Usar o patch da extração original e ausência de mudanças posteriores nos alvos.
git apply -R --check "$ENTREGA/F8-05-A02.patch"
git apply -R "$ENTREGA/F8-05-A02.patch"
```

A reversão retira os três arquivos novos (dois tools e este guia) e restaura os três arquivos alterados (admin.js, ADR 0046 e índice) à base. Se o patch reverso não aplicar, pare e faça revisão manual; não use `git reset --hard` ou limpeza ampla.

Sincronize de volta somente o asset revertido à cópia servida e invalide o cache. Após o rollback, a configuração legada do editor volta a ser um problema conhecido; a biblioteca A01 permanece intacta. Se aplicado por cópia manual, restaure os três arquivos pré-existentes do backup e remova apenas os três novos, desde que não tenham sido modificados por trabalho posterior.

## 8. Pendências e próximo passo

1. **Usuário aplicar/homologar A02** e atualizar o GitHub; não foi aplicado no seu ambiente por esta entrega.
2. **F8-05 · Validação 03:** fluxo integrado criar/editar post → editor em cada idioma e capa → upload/seleção → salvar → reabrir → confirmar conteúdo, imagem e preview, além das regressões/guards pertinentes. Ainda não executado.
3. **Dívida separada de segurança/licenciamento:** o core continua CKEditor **4.22.1**, sem suporte comunitário. A02 não atualiza a versão, não avalia CVEs e não suprime a verificação/aviso de versão. Avisos do próprio CKEditor podem ficar visíveis e não devem ser mascarados como correção. Os assets de edição seguem locais, sem adicionar jQuery/CDN; verificações de versão do próprio core não são uma troca de distribuição do editor.
4. `allowedContent: true` permanece por compatibilidade; isso **não é sanitização de HTML**. Revisão de segurança do corpo de posts e demais achados anteriores continua fora desta rodada.

### Referências verificadas

- [Código-base de admin.js](https://github.com/davidcreator/davidcms/blob/c2df68993259e53fcc05080e6ea42971de7eebd8/dev/admin/view/js/admin.js).
- [Configuração legada](https://github.com/davidcreator/davidcms/blob/c2df68993259e53fcc05080e6ea42971de7eebd8/dev/admin/view/js/ckeditor/config.js) e [plugin reamur](https://github.com/davidcreator/davidcms/blob/c2df68993259e53fcc05080e6ea42971de7eebd8/dev/admin/view/js/ckeditor/plugins/reamur/plugin.js).
- [Core local consultado](https://github.com/davidcreator/davidcms/blob/c2df68993259e53fcc05080e6ea42971de7eebd8/dev/admin/view/js/ckeditor/ckeditor.js).
- [ADR 0046](../agents-ia/decisoes/0046-f8-02-blog-no-shell-filtros-paginacao-ckeditor-e-import-reestilizado.md) e [guia A01](F8-05-A01-Inicializacao-Biblioteca-Midia.md).
- [Informações oficiais do CKEditor 4](https://ckeditor.com/ckeditor-4/).
