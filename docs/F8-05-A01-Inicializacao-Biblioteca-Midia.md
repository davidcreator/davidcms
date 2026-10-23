# F8-05 · Ajuste 01 — Inicialização da biblioteca de mídia

> **Objetivo:** corrigir a inicialização de `blog-media.js` quando o shell o carrega no `<head>`, preservando integralmente os contratos da biblioteca de capas do Blog.

| Item | Referência |
| --- | --- |
| Projeto | ReamurCMS — `davidcreator/davidcms` |
| Fase / tarefa / ajuste | Fase 8 / tarefa 05 / ajuste 01 (`F8-05-A01`) |
| Data / revisão do pacote | 2026-10-23 / 1.0 |
| Agente responsável | admin; documentação consolidada pelo orquestrador |
| Base verificada | `main` em `722f98294beaac85a75b111075222e3187abb0e7` |
| ADR relacionada | [0049 — Biblioteca de mídia para capas do Blog](../agents-ia/decisoes/0049-f8-05-blog-media-library.md) |
| Situação da entrega | Implementada; regressão DOM simulada validada; aplicação e homologação reais pendentes |

## Sumário

1. [Escopo e causa](#1-escopo-e-causa)
2. [Arquivos entregues](#2-arquivos-entregues)
3. [Aplicação segura](#3-aplicação-segura)
4. [Tool E2E e regressão](#4-tool-e2e-e-regressão)
5. [Aceite em navegador](#5-aceite-em-navegador)
6. [Verificação da entrega](#6-verificação-da-entrega)
7. [Rollback e continuidade](#7-rollback-e-continuidade)

## 1. Escopo e causa

O formulário registra o JavaScript em `Document::addScript`. O shell administrativo em `dev/admin/view/template/common/header.twig` o renderiza no `<head>` sem `defer`. Antes do ajuste, a IIFE consultava os elementos imediatamente e retornava, porque o modal e o campo de capa ainda não existiam no DOM. Não havia uma segunda tentativa.

A correção nomeia a IIFE como `initBlogMedia` e, se `document.readyState === 'loading'`, agenda sua execução para `DOMContentLoaded` com `{ once: true }`. Quando o documento está `interactive` ou `complete`, a inicialização continua imediata. A saída silenciosa em páginas sem os elementos permanece.

**Mudança de runtime:** somente `dev/admin/view/js/blog-media.js`. Nenhum código existente foi reformatado ou movido. Não há mudança de schema, banco, endpoints, payloads, ACL/CSRF, traduções, Twig, núcleo ou comportamento do importador WordPress. A configuração do CKEditor fica para o Ajuste 02.

A identificação A01 é uma subdivisão de acompanhamento aprovada nesta conversa, não uma nova fase nem uma renumeração das ADRs.

## 2. Arquivos entregues

### Arquivos do projeto (6)

| Caminho na raiz do repositório | Operação |
| --- | --- |
| `dev/admin/view/js/blog-media.js` | Substituir; correção pontual do boot |
| `tools/e2e/e2e_f805_a01_blog_media.sh` | Adicionar; entrada de testes e proteção do modo destrutivo |
| `tools/e2e/e2e_f805_a01_blog_media_dom.cjs` | Adicionar; regressão JavaScript com DOM simulado |
| `agents-ia/decisoes/0049-f8-05-blog-media-library.md` | Atualizar; adendo sem alterar a decisão original |
| `docs/F8-05-A01-Inicializacao-Biblioteca-Midia.md` | Adicionar; este guia |
| `docs/README.md` | Atualizar; índice deste guia |

### Arquivos auxiliares do ZIP

- `F8-05-A01.patch`: alternativa de aplicação com Git; não aplicar também por sobreposição.
- `MANIFESTO-F8-05-A01.json`: origem, escopo, hashes antes/depois e limites de validação.
- `SHA256SUMS`: integridade dos demais arquivos do pacote.

Esses três auxiliares pertencem ao pacote, não são novos arquivos da aplicação. O ZIP não contém o restante do projeto, vendor, assets de terceiros, configurações reais, credenciais, banco, backups ou histórico Git.

## 3. Aplicação segura

Trabalhe no repositório de desenvolvimento, em uma branch `feature/*`, e valide em homologação antes de seguir para `main`. **Não publique este ZIP inteiro no docroot de produção**: documentação, agentes e ferramentas não são runtime. O modelo de build já existente permanece.

1. Faça backup/commit das alterações locais pertinentes.
2. Compare sua referência com a base indicada. Se os arquivos existentes foram alterados depois dela, revise o diff; não os sobrescreva cegamente.
3. Extraia o pacote em uma pasta temporária separada e verifique a integridade.
4. Escolha **um** dos dois métodos abaixo.

### Método recomendado — patch

Ajuste os dois caminhos do exemplo:

```bash
PACOTE="$(mktemp -d)"
unzip /caminho/do/download/F8-05-A01.zip -d "$PACOTE"
(cd "$PACOTE" && sha256sum -c SHA256SUMS)

cd /caminho/do/repositorio/davidcms
git status --short
git rev-parse HEAD
git switch -c feature/f8-05-a01-blog-media-init
git apply --check "$PACOTE/F8-05-A01.patch"
git apply "$PACOTE/F8-05-A01.patch"
git diff --check
git status --short
```

Se `git apply --check` falhar, interrompa e compare as versões. Não use força nem apague alterações locais para fazer o pacote caber. O patch cobre os seis arquivos da tabela, mas `git diff` não mostra o conteúdo de arquivos novos ainda não adicionados ao índice; revise-os também.

### Alternativa — arquivos completos

Em uma cópia compatível com a base verificada, copie **somente os seis arquivos do projeto**, preservando os caminhos relativos. Não substitua/apague pastas inteiras. Os hashes `before_sha256` e `after_sha256` do manifesto permitem conferir a versão de cada arquivo substituído.

### Cache e cópia servida

O ajuste mantém `Header::ASSET_V` e a URL do asset para não modificar o cache global nem os testes de aparência. Após atualizar a cópia servida em homologação:

- recarregue sem cache / use **Disable cache** no DevTools;
- se houver CDN/proxy, invalide especificamente `admin/view/js/blog-media.js`;
- confirme na aba Network que a resposta contém `function initBlogMedia` e o guard `document.readyState`.

O teste em disco não comprova que o navegador recebeu a versão nova.

## 4. Tool E2E e regressão

### Modo padrão — seguro, sem instalar dependências

No computador de desenvolvimento ou CI, com **Node.js 20+ e Bash**:

```bash
bash tools/e2e/e2e_f805_a01_blog_media.sh --dom
```

Não é necessário instalar Node no servidor de produção. Este modo não roda PHP, não usa rede, não grava imagens, não acessa banco e não reinstala o site. Verifica sintaxe e executa o arquivo JavaScript real em `node:vm`, com DOM, HTTP e Bootstrap simulados.

Casos cobertos:

1. Carregamento no `<head>`: aguarda o DOM e registra os eventos após o parsing.
2. Documento `interactive`: inicialização imediata.
3. Documento `complete`: inicialização imediata.
4. Listener de `DOMContentLoaded` consumido uma vez, sem duplicar handlers nessa transição.
5. Página sem os elementos: saída silenciosa.
6. Configuração JSON inválida: saída segura existente.
7. Abertura do modal: rota de listagem e credenciais `same-origin` preservadas.
8. Seleção da imagem: valor, eventos `input`/`change` e fechamento do modal.
9. Pastas e busca: parâmetros do módulo preservados.
10. URL externa: rejeição de outros protocolos e aceitação de HTTP(S).

**Não é um navegador real nem um teste de backend.** Não comprova renderização, preview carregado, upload real, ACL/CSRF do servidor ou persistência.

Para uma cópia `dev/` em outro caminho:

```bash
MIRROR=/caminho/da/copia/dev bash tools/e2e/e2e_f805_a01_blog_media.sh --dom
```

### Modo opcional — E2E HTTP/banco existente

> **DESTRUTIVO:** a suíte F8-05 existente executa `DROP DATABASE reamur_test`, remove os `config.php` de `FUI`, reinstala o sandbox e manipula arquivos de teste. Só execute em uma cópia descartável, jamais em produção ou em uma instalação com conteúdo a preservar.

O wrapper não altera nem inclui uma cópia redundante de `tools/e2e/e2e_f805_blog_media.sh`: ele o chama no repositório completo já existente, depois de passar na regressão DOM.

Pré-requisitos adicionais: sandbox Apache/PHP/MariaDB preparado conforme o projeto, `sudo`, cliente `mysql`, `curl`, permissões de escrita apropriadas e o script F8-05 original presente. A URL `B` precisa ser local (HTTP(S), localhost/127.0.0.1/[::1]); as variáveis devem apontar ao mesmo sandbox.

Somente após conferir que **FUI é uma cópia descartável**, configure:

```bash
export B=http://127.0.0.1:8090/davidcms/dev
export FUI=/var/www/html/davidcms/dev
export MIRROR="$PWD/dev"

# Declaração explícita de que FUI é descartável; não cria o ambiente.
printf '%s\n' reamur_test > "$FUI/.f805-a01-disposable"

ALLOW_DESTRUCTIVE_F805=YES_RECREATE_REAMUR_TEST \
  bash tools/e2e/e2e_f805_a01_blog_media.sh --backend
```

Sem confirmação, URL local, variáveis explícitas, marcador e igualdade do JS entre `MIRROR` e `FUI`, o wrapper bloqueia a execução. Esses bloqueios reduzem o risco de erro operacional; não substituem a conferência humana do sandbox. O E2E legado não foi refatorado neste ajuste.

## 5. Aceite em navegador

Executar após aplicação no ambiente de homologação:

- [ ] Entrar no admin com permissão `modify` em `blog/blog`.
- [ ] Abrir criar/editar post e confirmar na Network a versão nova do JS, sem cache.
- [ ] Abrir a biblioteca; observar requisição `mediaList` e listagem/estado vazio válido.
- [ ] Se houver imagens, selecionar uma e conferir `input-image`, preview e fechamento do modal.
- [ ] Se houver pastas, navegar, buscar e verificar que os controles respondem.
- [ ] Fechar/reabrir o modal e confirmar uma requisição de listagem por abertura.
- [ ] Conferir o console para erros da biblioteca. Problemas independentes do CKEditor devem ser anotados para o Ajuste 02, não ocultados.

Upload real, salvar/reabrir o post, persistência e segurança do backend continuam na validação integrada F8-05. A Validação 03 não está concluída por este pacote.

## 6. Verificação da entrega

Executado durante a preparação (Node.js v20.20.2):

| Verificação | Resultado |
| --- | --- |
| `node --check` no arquivo corrigido e no runner | Aprovado |
| `bash -n` no wrapper | Aprovado |
| Regressão DOM simulada com fonte corrigida | **10 pass / 0 fail** |
| Controle negativo com fonte original do commit-base, recebida em memória | **4 pass / 6 fail**, saída 1: reproduziu a regressão esperada |
| Ajuda e rejeição de modo desconhecido | Aprovado |
| Backend sem autorização, host externo, sem marcador ou sem MIRROR explícito | Bloqueado, saída 2; suíte destrutiva não iniciada |

Verificações finais de empacotamento: aplicação e reversão do patch em árvore parcial temporária com arquivos-base; comparação byte a byte; integridade ZIP e hashes. Não houve clone do repositório.

**Não executados:** aplicação PHP, E2E HTTP/MariaDB, navegador real, CI remoto e publicação. `php -l` não se aplica: nenhum arquivo PHP foi alterado.

### Checklist do agente

- [x] Arquivos existentes lidos na referência-base antes de editar.
- [x] Mudança de runtime restrita ao boot da biblioteca.
- [x] Sem pasta nova de aplicação, rota, schema, SQL ou biblioteca externa.
- [x] Sem nova string de interface; idiomas preservados.
- [x] ACL/CSRF e payloads não modificados; sua execução no servidor não foi revalidada aqui.
- [x] Documento e ADR relacionada atualizados; índice incluído.
- [ ] Aplicação e aceite em navegador pelo responsável pelo ambiente.

Commit sugerido:

```text
fix(admin): aguardar o DOM na biblioteca de mídia do blog
```

## 7. Rollback e continuidade

Antes de um commit, e desde que esses arquivos não tenham recebido outras mudanças, reverta o mesmo patch após checar a aplicação inversa:

```bash
git apply -R --check "$PACOTE/F8-05-A01.patch"
git apply -R "$PACOTE/F8-05-A01.patch"
```

Se a reversão falhar, pare e revise; não force. Se a entrega já foi commitada, prefira `git revert` do commit específico. Não há rollback de banco porque este ajuste não altera dados/schema.

### Resumo para a próxima rodada

- Base analisada: `722f98294beaac85a75b111075222e3187abb0e7`.
- Entrega: F8-05-A01, ZIP revisão 1.0.
- Alteração de runtime: inicialização do seletor após o DOM estar disponível.
- Teste executado: regressão DOM simulada 10/10; não confundir com E2E real.
- Pendente: aplicar no repositório/cópia servida, informar o novo commit e homologar em navegador.
- Próxima subtarefa: F8-05 · Ajuste 02 — configuração legada do CKEditor; ainda não implementada.
- A Fase 8 e a Tarefa 05 não são declaradas concluídas por esta entrega.
