# Fase 8 · Tarefa 09 — Ferramentas conectadas

> Objetivo: migrar somente o cadastro `tool/tool` ao shell do painel, preservando nomes/descrições/fornecedor/URL/ícone, com filtros/paginação e CSRF, sem schema ou integração externa nova.

**Data:** 2026-10-23 · **Base:** `175da8e75d6b076bcce23b6a7ac939ce72145caa`.
**Estado:** implementação preparada/testada localmente; aplicação e homologação real pendentes. A Tarefa08 foi declarada concluída; seus17 arquivos públicos conferem com a entrega, sem reabrir seu aceite ou inventar resultados de backend.

## Sumário

1. [Escopo e contratos](#1-escopo-e-contratos)
2. [Arquivos, aplicação e cache](#2-arquivos-aplicação-e-cache)
3. [Tool local e evidências](#3-tool-local-e-evidências)
4. [E2E HTTP no laboratório](#4-e2e-http-no-laboratório)
5. [Aceite no navegador](#5-aceite-no-navegador)
6. [Falhas, limpeza e rollback](#6-falhas-limpeza-e-rollback)
7. [Limites](#7-limites)

## 1. Escopo e contratos

**Um CRUD/ZIP por rodada**, somente fase/tarefa, sem novo ajuste/subgrupo. Trata cartões/links de ferramentas conectadas existentes, não implementação de Fluxa, Quotia, Vertex, Solis, APIs, execução de ferramenta ou abertura automática de URLs externas.

### Interface

- Lista com titlebar/total, ícone sem recorte, nome/fornecedor/URL, status/ordenação/data de criação, tabela responsiva e modal delete.
- Filtros nome/fornecedor/status com paginação server-side20, contagem consistente, preservação dos três filtros, reset inclusive `0` e recuo além do fim.
- Form: dois campos por idioma em tabs, dados e ícone na sidebar; readonly sem Add/Delete/Save e upload desabilitado.
- Nome EN obrigatório, com abertura da tab do **primeiro inválido**, sem impedir validação nativa; contadores/hint reaproveitam admin.js sem alterá-lo.
- Upload via MediaUpload existente (JPG/PNG/WebP/GIF até2MB), com token e JS externo, preview DOM seguro/contain, input/change, bloqueio de salvar/duplo envio durante upload e sem retry automático.
- en-us/pt-br/es-eu completos. Sem SEO/keyword/meta, categorias/galeria, CKEditor ou novo gerenciador de arquivos.

### Preservação precisa

Rotas `tool/tool`, `.add`, `.edit|ID`, `.delete|ID`, `.upload`; `tool_description[N][name|description]`; os **seis IDs** `input-name-N` e `input-description-N`; name128/description255 como **input text**, não textarea; vendor64, URL255, image, status, sort_order; `input-vendor`, `input-url`, `input-sort-order`, `input-image`, `input-icon-file`, `btn-upload-icon`, `icon-preview`. Não se troca por `title`, campos de projetos ou editor rico.

`getTools()` sem argumentos mantém todos e ordem **sort_order ASC, tool_id ASC**. Métodos de persistência/descrições e normalização/validação do controller ficam byte a byte iguais. Data é `date_added`, sem inventar date_modified.

COALESCE de nome/descrição é o da base, **sem NULLIF**: valor NULL usa EN, string vazia ativa continua vazia como antes. Busca usa esse mesmo nome visível e LIKE (curingas não alterados), não fulltext/índice novo.

**Mudança intencional de segurança:** add/edit/delete/**upload** exigem csrf_token da mesma sessão além da ACL tool/tool. Delete inválido não chega ao unlink; upload recusa antes de store. JSON `{success,path,error}` mantido; clientes antigos precisam de token, sem bypass.

**URL mantém regra antiga:** opcional; se preenchida começa com http:// ou https:// (case-insensitive). Não se introduz FILTER_VALIDATE_URL, DNS, verificação online ou nova política de links. O navegador ainda aplica sua validação nativa de input URL. O Tool apenas armazena URL fictícia, não a abre.

Sem schema, SQL de instalação/grants, `system/**`, `catalog/**`, `install/**`, outros CRUDs, Appearance/Localization, admin.js/ASSET_V, Composer ou editor do Blog alterados. MediaUpload compartilhado permanece intacto.

## 2. Arquivos, aplicação e cache

### 17 arquivos de projeto

**Runtime (9):**

- `dev/admin/controller/tool/tool.php` e `dev/admin/model/tool/tool.php`.
- `dev/admin/view/template/tool/tool_list.twig` e `tool_form.twig`.
- Novo `dev/admin/view/js/connected-tools.js`.
- `dev/admin/view/css/admin.css`: só adendo `.connected-tools-list`, prefixo anterior completo preservado.
- `dev/admin/language/{en-us,pt-br,es-eu}/tool/tool.php`.

**QA/docs (8):**

- `tools/e2e/e2e_f809.sh` (entrada), `e2e_f809_contracts.php`, `e2e_f809_dom.cjs`, `e2e_f809_http.cjs`.
- `dev/tests/e2e/e2e_f213.sh`: compatibilidade CSRF do login/8 mutações e contagem das tabs no lugar de legends. **Permanece destrutivo, nunca chamado pelo Tool novo.** Setup/SQL/limpeza/grants legados não foram refatorados nem executados.
- Este guia, índice `docs/README.md` (linha20), [ADR0053](../agents-ia/decisoes/0053-f8-09-ferramentas-conectadas-no-shell.md).

Sete novos/dez modificados, mais `F8-09.patch`, `MANIFESTO-F8-09.json`, `SHA256SUMS`. Sem projeto inteiro/vendor/configs/dados/credenciais reais. O script antigo conserva apenas suas fixtures públicas anteriores.

### Aplicar

1. Conferir `git rev-parse HEAD`: base175da8e75d6b076bcce23b6a7ac939ce72145caa. Se alvos mudaram depois, revisar hashes/diff; não sobrescrever trabalho concorrente.
2. Branch/PR para staging e backup conforme projeto; não aplicar diretamente em produção.
3. Extrair ZIP fora do repositório e rodar `sha256sum -c SHA256SUMS` na extração.
4. Na raiz do repositório, `ENTREGA` é o caminho absoluto dessa extração:

```bash
git apply --check --whitespace=error "$ENTREGA/F8-09.patch"
git apply --whitespace=error "$ENTREGA/F8-09.patch"
git diff --check
bash tools/e2e/e2e_f809.sh --check
```

Alternativa: copiar só os17 alvos exatos do manifesto. Sem árvore inteira/vendor, --delete, reaplicar pacotes ou executar upgrade SQL.

Sincronizar só alvos pelo fluxo controlado. Conferir `connected-tools.js` HTTP200 e invalidar cache CSS/PHP/Twig na homologação. **ASSET_V não muda**, então conferir cache do CSS existente. Alterar GitHub/dev não comprova deploy.

CSS de paginação é isolado; controller normaliza apenas `&amp;amp;` no HTML da library, com query URL-encoded, como nas tarefas anteriores. Não decodifica HTML arbitrário nem altera a library/aria-labels globais.

Commit sugerido:

```text
feat(admin): migrar ferramentas conectadas para o shell com CSRF
```

## 3. Tool local e evidências

```bash
bash tools/e2e/e2e_f809.sh --check
bash tools/e2e/e2e_f809.sh --help
```

Requer PHP CLI8.1+/mbstring, Twig3 do Composer do projeto, Node20+ com fetch/FormData/Blob/Headers.getSetCookie e Bash. Helpers F8-05/F8-06/F8-07 já versionados são usados sem executar seus fluxos. Sem instalação automática/dependência nova da aplicação.

Twig padrão: dev/system/storage/vendor/autoload.php. Pode definir TWIG_AUTOLOAD absoluto para autoload local de QA e MIRROR para escolher o dev testado. Executar apenas fontes/autoload confiáveis: não é sandbox para código desconhecido.

| Grupo executado pelo agente | Resultado | Natureza |
| --- | --- | --- |
| PHP lint | **6/6 aprovados** | Cinco PHP de runtime + runner. |
| Contratos PHP/Twig | **36 PASS / 0 FAIL** | PHP8.4.26/Twig3.30.0 reais; DB/serviços/model do controller/IO sessão em fixtures. |
| JS/form/upload | **9 PASS / 0 FAIL** | Fonte real com DOM/Bootstrap/fetch simulados. |
| Auxiliares HTTP novos | **8 PASS / 0 FAIL** | Parser/IDs/vendor/PNG/flags/FS em simulação/temporários. |
| Auxiliares compartilhados | **25 PASS / 0 FAIL** | F8-07(9)+F8-06(16), fetch/respostas simulados. |
| Negativo na ferramenta anterior | **17 PASS / 19 FAIL**, saída1 | Falha esperada em contratos PHP/Twig. |
| Sintaxe JS/CJS/Bash | Aprovada | f213 apenas bash -n; não foi executado. |

Regressões locais verdes sem reabrir aceite: F8-08(45 PHP/Twig+12 DOM+35 auxiliares), F8-07(40+9+25), F8-06(30+6+16), F8-05(32 guardas/modelos+18RTE+10mídia simulados).

Classes reais são carregadas e controller/model/Twig strict/autoescape executados. SQL é **capturado**, não enviado a MariaDB; Session real sem construtor, só dados em memória. MediaUpload guard/delete opera em PNGs de **FS temporário privado não servido**, fora de MIRROR/FUI, removido ao final; store só no-file. Não é upload HTTP/move/persistência de uma instalação real.

**Não chamar os78 casos locais de E2Es reais aprovados.** Não houve servidor/DB/browser, HTTP/upload real do CMS, URL externa aberta, deploy/Actions/PHPStan/PHPUnit completo/matrizPHP. Não se alterou vendor/Composer do CMS ou baixou projeto inteiro para preparar esta entrega.

## 4. E2E HTTP no laboratório

### Segurança/pré-requisitos

`--http` grava **uma ferramenta fictícia e um PNG2×2**, além do probe temporário. Laboratório local descartável com reamur_test, idiomas en/pt-BR/es e conta fictícia com access/modify **tool/tool**. Não exige criar outra entidade ou modificar ACL/grants para obter acesso.

Reutiliza guardas F8-06/F8-05: B/FUI/MIRROR explícitos, loopback, configs literais wizard com DB_DATABASE=reamur_test/DB_HOSTNAME loopback e HTTP_SERVER correspondente; `.f805-disposable`; fontes/assets iguais. Compara também nove alvos tool/tool e media_upload.php, caminhos de ícones sem symlinks. O marker mantém nome por compatibilidade, não cria ajuste nem torna DB real descartável.

**Proibido produção/dados/credenciais reais, ambiente misto, túnel/proxy para produção, concorrência ou integrações produtivas.** Operador confirma B/FUI, DB e diretórios reais do PHP; config/nonce/hash não atesta proxy/backend mal configurado. Não associar dados do ensaio a outro conteúdo.

### Executar

Ajuste caminhos no terminal do laboratório; não envie senhas/tokens pelo chat:

```bash
export B='http://127.0.0.1:8090/davidcms/dev'
export FUI='/var/www/html/davidcms/dev'
export MIRROR="$(pwd -P)/dev"
# Só depois de conferir banco/configs/diretórios descartáveis:
printf 'reamur_test\n' > "$FUI/.f805-disposable"
export ALLOW_F809_HTTP='YES_WRITE_REAMUR_TEST'
read -r -p 'Usuário fictício com modify tool/tool: ' F809_USERNAME
read -r -s -p 'Senha fictícia: ' F809_PASSWORD; printf '\n'
export F809_USERNAME F809_PASSWORD
export F809_REPORT='/tmp/novo-resultado-f809.json'  # novo/inexistente
bash tools/e2e/e2e_f809.sh --http
unset F809_USERNAME F809_PASSWORD ALLOW_F809_HTTP
```

Relatório opcional, absoluto novo, diretório existente, 0600, sem sobrescrever anterior. Preflight bloqueado sai2 antes de execução/não gera JSON de workflow. Não remover guardas, desativar TLS ou alterar configs reais para forçar verde. Config PHP dinâmica/DB Docker por hostname não loopback podem exigir avaliação pontual.

### Fluxo preparado em13 etapas — NÃO executado pelo agente contra o backend

1. Probe único `FUI/admin/view/js/f809-probe-<nonce>.txt`, verificado por B antes de credenciais.
2. Login único com CSRF/cookies em memória, sem jar/storage state.
3. Form ×3 idiomas com seis campos, vendor/URL/modify/config de upload; IDs dos idiomas do HTML, sem supor pt/es=2/3; marker próprio ausente.
4. Add sem token recusa sem registro.
5. Upload de PNG sem token recusa sem arquivo novo.
6. PNG válido com CSRF: path novo exclusivo do módulo, arquivo regular/sem symlink, hash no FUI e mesmos bytes HTTP no catalog.
7. Criar ferramenta desabilitada com vendor e URL vazia, descobrir ID real pelo nome/link único, não ID1 fixo.
8. Reabrir seis campos/vendor/URL/ícone/status/sort_order.
9. Edit sem token e URL ftp com token correto preservam salvo.
10. Editar mesmo ID v2, habilitar, salvar URL HTTPS fictícia e reabrir, **sem abrir esse link**.
11. Filtros combinados nome/fornecedor/status.
12. GET delete e POST sem token mantêm registro/ícone. 403 do controle GET tratado explicitamente, sem esconder500.
13. Revalidar dados/ID/hash próprios, excluir só ferramenta/PNG e conferir ausência.

Sem SQL direto, drop/reset/recreate, instalação/upgrade/seed, suíte destrutiva, upload de arquivo do usuário ou execução de API/ferramenta externa. Redirects externos não recebem novo request/cookie; multipart não segue redirects e POST307/308/timeout não é repetido. Em falha preservar artefatos conhecidos para inspeção, sem adivinhar ID ou reenvio cego.

JSON: kind=http-workflow, status, marker, artifacts.toolId/image,13 etapas e cleanup. **Aceite do fluxo:** passed +13etapas pass +cleanup complete; aprova só HTTP, não navegador. Sem HTML completo, credenciais, cookies ou tokens de autenticação/CSRF.

Cleanup refere a registro/PNG/probe próprios; diretórios padrão, sessão/rate limit e auto-increment permanecem como operação normal. Não remove arquivo concorrente para obter verde.

## 5. Aceite no navegador

Navegador moderno com Bootstrap local/fetch/AbortSignal.timeout. HTTP não executa o JS, nem comprova foco/preview/estilos.

- [ ] Lista/form no shell responsivo desktop/mobile, skins/claro/escuro legíveis, sem overflow externo.
- [ ] Nome/fornecedor/status, inclusive zero, reset, total, página2/recuo; volume real21+ requer dados fictícios próprios (45 só em fixture).
- [ ] EN vazio em outra tab revela/foca o primeiro inválido, inclusive múltiplos inválidos, sem erro de controle não focalizável.
- [ ] Seis IDs/names, input description255, nome128, vendor64, URL opcional255, status/sort/image preservados em criar/editar/salvar/reabrir; sem SEO/editor/galeria.
- [ ] Ícones válidos/JPG/PNG/WebP/GIF, tamanho/tipo inválido, preview sem recorte object-fit-contain, input/change e persistência.
- [ ] Upload pendente impede salvar/Enter/duplo envio; erro é texto, controles restaurados sem retry.
- [ ] Modal delete: cancelar preserva, confirmar POST+CSRF remove só ferramenta/ícone exclusivos fictícios; GET/token inválido não mutam.
- [ ] Readonly sem Add/Delete/Save, upload desabilitado e quatro POSTs negados com perfil real separado (não testado automaticamente).
- [ ] Três idiomas/contadores/hint, links http(s) manuais como antes; não usar URLs reais no ensaio. Outros CRUDs/site/Blog/Appearance intactos.

Confirmar conclusão da Tarefa09 somente após aplicação/homologação do usuário; não encerra Fase8 automaticamente.

## 6. Falhas, limpeza e rollback

Timeout pode ocorrer depois de gravar. Não repetir POST às cegas. Examinar JSON e marker f809-…, verificar ID/dados e excluir somente conteúdo próprio. ID desconhecido não se presume.

Ícone sem associação pode restar: inspecionar path/checksum exato conhecido, sem wildcard, rm-rf/reset SQL/limpeza de view/image/tool. Nunca imagem compartilhada/real. Upload imediato, troca/cancelamento e órfãos/exclusão best-effort permanecem a base; não se cria coleta automática ou endpoint novo.

Probe removido em finally só se regular/conteúdo igual. Processo morto/probe alterado pode deixá-lo; inspecionar apenas o f809-probe-<nonce>.txt exato, não limpar view/js amplamente. Falha de cleanup sinaliza failed/pending.

Rollback de código, patch original/backup e alvos sem alterações posteriores:

```bash
git apply -R --check "$ENTREGA/F8-09.patch"
git apply -R "$ENTREGA/F8-09.patch"
```

Se não aplicar, parar/revisar; sem reset--hard. Restaura dez/remove sete novos, sem reverter tarefas aceitas. Antes limpar só registro/PNG/probe próprios; rollback não apaga dados/relatórios. Sincronizar só alvos revertidos e invalidar cache normal. Base antiga volta a não exigir CSRF em ferramentas, limitação conhecida, não recomendação.

## 7. Limites

Sem HTTP do CMS/DB/persistência/upload/navegador reais; sem URL/API externa aberta/executada, GitHub write/deploy/produção, Actions/PHPStan/PHPUnit completo/matrizPHP nesta preparação. F213 permanece destrutivo/não auditado integralmente nem executado; mudanças restritas à compatibilidade UI/CSRF, não promessa de14checks reais verdes.

URL mantém só prefixo http(s) ou vazio como antes, sem verificação online/DNS/sintaxe adicional. COALESCE sem NULLIF preservado. CSS/escape da paginação locais; aria-labels internos da library intactos. Sem sanitização HTML nova, coleta global de órfãos/referências/symlinks do componente, CKEditor/Blog media/licença novos.

Conclusão da Tarefa08 veio do usuário; só igualdade dos17 arquivos públicos foi conferida independentemente. Publicação de código não comprova deploy.

Referências: [ADR0053](../agents-ia/decisoes/0053-f8-09-ferramentas-conectadas-no-shell.md), [F8-08](F8-08-Projetos-Portfolio.md), [F8-07](F8-07-Categorias-Portfolio.md), [F8-06](F8-06-Paginas-CMS.md), [base pública](https://github.com/davidcreator/davidcms/commit/175da8e75d6b076bcce23b6a7ac939ce72145caa).
