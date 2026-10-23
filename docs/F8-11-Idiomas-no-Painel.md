# Fase 8 · Tarefa 11 — Idiomas no painel

> Objetivo: migrar lista/form de localization/language ao shell, preservar idiomas/padrões existentes e proteger POSTs com CSRF, sem novo idioma, schema ou troca automática de padrão.

**Data:** 2026-10-23 · **Base:** `b3eb7262e77ef25335e21446c0f64c7017dfe1a6`.
**Estado:** inclusão da tela escolhida pelo usuário; implementação preparada/testada localmente, aplicação/homologação pendentes. Tarefa10 concluída conforme informado; seus15 arquivos públicos conferem com a entrega, sem inferir backend/deploy.

## Sumário

1. [Escopo e contratos](#1-escopo-e-contratos)
2. [Arquivos, aplicação e cache](#2-arquivos-aplicação-e-cache)
3. [Tool local e evidências](#3-tool-local-e-evidências)
4. [HTTP limitado de laboratório](#4-http-limitado-de-laboratório)
5. [Aceite no navegador](#5-aceite-no-navegador)
6. [Falhas, restauração e rollback](#6-falhas-restauração-e-rollback)
7. [Limites](#7-limites)

## 1. Escopo e contratos

O usuário escolheu migrar **Idiomas** antes da validação final/planejamentoF9. Um módulo/ZIP, somente fase/tarefa, sem novos ajustes/subgrupos.

### Entregue

- Lista no shell/cards/tabela responsiva, busca por nome/código/locale, filtro status, total e paginação20; mesma ordem sort_order ASC/language_id ASC, recuo além do fim e filtros nos links.
- Form Bootstrap para nome/locale/ordem/status, código imutável e aviso de diretório ausente. Padrão de qualquer app não pode ser desativado: UI bloqueia checkbox e inclui status hidden1; backend rejeita POST adulterado.
- Seletores de padrões usam **todas as linhas**, separados da grade. Busca/página/empty state não limitam as opções ou alteram seleção atual.
- CSRF em POST de padrões e edit, access/modify existentes e readonly com fieldset disabled/sem Save.
- Repopulação de POST/unchecked após erro corrigida, callback não estático para usar `$this`, error inicializado em GET e returns explícitos após redirects.
- Textos en-us/pt-br/es-eu completos. Sem JS runtime novo; CSS local sem alterar prefixo anterior.

### Preservado

Rotas `localization/language` (GET lista/POST padrões) e `.edit|ID`. Sem add/delete/upload. Names e IDs `name`, `locale`, `sort_order`, `status`, `config_language_catalog`, `config_language_admin`, código `id=code` sem name e disabled. Name/locale até32; payload de metadados mantém quatro campos, code nunca atualiza.

`getLanguages()` sem argumentos continua completo na ordem original, e `getLanguage/editLanguage/getEnabledCodes` permanecem byte a byte. Gate getEnabledCodes continua status1+diretório existente no admin; nenhum diretório de idioma é criado/apagado. Validações de domínio de padrões e edit permanecem as da base: padrão precisa estar habilitado e default vigente não pode ser desativado. Não se amplia a política de diretório do validator de padrões neste lote.

**Configurações atuais não mudam por GET, patch ou Tool local.** Padrões só são gravados se o operador submeter explicitamente o formulário existente com access/modify+CSRF. Fallback en-us já existente é preservado, não semeado novamente. O HTTP automático descrito abaixo **nunca faz POST de padrões**.

Métodos do model e validações preservados não significam manter bugs de apresentação: a união `$language + [post]` não repovoava os campos, e `static fn` com `$this` falhava ao traduzir erros. Isso foi corrigido sem alterar regras/tabelas. Response já encerra web no redirect, mas retorna em CLI; returns explícitos deixam o fluxo previsível também nos testes.

Sem schema/grants/SQL de instalação, novos códigos/packs/pastas, `system/**`, `catalog/**`, `install/**`, outros CRUDs, Appearance/admin.js/ASSET_V, Composer/SMTP/editorBlog alterados. As grafias localization do admin e localisation onde existente no catalog não são renomeadas.

## 2. Arquivos, aplicação e cache

**15 arquivos de projeto**: dez modificados/cinco novos.

- Runtime8: `dev/admin/controller/localization/language.php`, model correspondente, templates `language_list.twig`/`language_form.twig`, três idiomas UI existentes e `dev/admin/view/css/admin.css`.
- Novos tools3: `tools/e2e/e2e_f811.sh`, `e2e_f811_contracts.php`, `e2e_f811_http.cjs`.
- `tools/e2e/e2e_f402.sh`: só captura CSRF da lista e token nos oito POSTs de idioma/padrões. **Permanece destrutivo**, troca padrões/status, recria DB e contém agregados legados; só bash-n aqui. Não é chamado pelo Tool novo nem sua bateria é homologada integralmente.
- Este guia, índice docs/README.md (linha22), [ADR0055](../agents-ia/decisoes/0055-f8-11-idiomas-no-painel.md).

Acompanhantes: F8-11.patch, MANIFESTO-F8-11.json, SHA256SUMS. Sem projeto inteiro/vendor/configs/dados/credenciais reais ou packs de idioma. Script antigo conserva fixtures públicas preexistentes.

1. Conferir `git rev-parse HEAD`:b3eb7262e77ef25335e21446c0f64c7017dfe1a6. Se alvos mudaram, revisar hashes/diff; não sobrescrever trabalho concorrente.
2. Branch/PR para staging, backup; não aplicar direto em produção.
3. Extrair ZIP fora do repositório e conferir `sha256sum -c SHA256SUMS` na extração.
4. Na raiz do repositório, ENTREGA é a extração absoluta:

```bash
git apply --check --whitespace=error "$ENTREGA/F8-11.patch"
git apply --whitespace=error "$ENTREGA/F8-11.patch"
git diff --check
bash tools/e2e/e2e_f811.sh --check
```

Alternativa: copiar somente os15 alvos. Sem árvore inteira/vendor/--delete/reaplicar ZIP/upgradeSQL. Sincronizar só alvos pelo fluxo controlado e invalidar CSS/PHP/Twig na homologação. **ASSET_V não muda**, conferir cache de admin.css. Código/dev/GitHub atualizado não prova deploy.

CSS extra só `.localization-language-list`; normalização de `&amp;amp;` na paginação gerada com query codificada, sem decodificar HTML arbitrário nem alterar library/aria-labels globais.

Commit sugerido:

```text
feat(admin): migrar idiomas para o shell com CSRF e seletores completos
```

## 3. Tool local e evidências

```bash
bash tools/e2e/e2e_f811.sh --check
bash tools/e2e/e2e_f811.sh --help
```

PHP CLI8.1+/mbstring/Twig3 pelo Composer do projeto, Node20+ fetch/Headers.getSetCookie e Bash. Helpers F8-05/F8-06 já na base, sem seus fluxos. Sem instalar pacotes/dependência nova do CMS ou inicializar config/framework nos testes.

Twig padrão: dev/system/storage/vendor/autoload.php; TWIG_AUTOLOAD absoluto permite autoload QA local e MIRROR escolhe o dev. Apenas fontes/autoload confiáveis; não é sandbox para código desconhecido.

| Grupo executado pelo agente | Resultado | Natureza |
| --- | --- | --- |
| PHP lint | **6/6 aprovados** | Cinco PHP de runtime+runner. |
| Contratos PHP/Twig | **40PASS/0FAIL** | PHP8.4.26/Twig3.30.0 reais, DB/serviços/model do controller/IO sessão em fixtures. |
| Auxiliares HTTP novos | **8PASS/0FAIL** | Parser/seletores/metadados/guardas/restore em simulação. |
| Compartilhados F8-06 | **16PASS/0FAIL** | Fetch/respostas simulados. |
| Controle negativo na tela antiga | **4PASS/36FAIL**, saída1 | Falha esperada com fontes anteriores; não36bugs independentes. |
| Sintaxe CJS/Bash | Aprovada | f402 antigo só bash-n, não executado. |

Regressões locais F8-10/09/08/07/06/05 verdes; não reabre seus aceites ou comprova backend/browser real.

Classes reais são carregadas, controller/model/Twig strict/autoescape executados. SQL capturado, não enviado a DB; Session real sem construtor/só memória. Diretórios en/pt/es da fixture ficam **em FS temporário privado não servido**, separado de MIRROR/FUI e removido ao final; nenhum idioma/pasta/config real é criado ou alterado.

**Não chamar os64 casos de E2Es reais aprovados.** Sem servidor/DB/HTTP/browser real, padrões/status/idiomas reais modificados, GitHubwrite/deploy/Actions/PHPStan/PHPUnit completo/matrizPHP nesta preparação. Sem JS runtime novo; visual/foco/default-lock precisam de navegador.

## 4. HTTP limitado de laboratório

O modo --http **não testa automaticamente a troca de padrões/status/locale/code**: para preservar seu limite, ele muda apenas **sort_order de um idioma ativo instalado NÃO-PADRÃO** informado pelo operador, verifica e restaura. Name/locale/status/código vão com os mesmos valores apenas por serem o payload exigido do edit; qualquer divergência faz parar, sem sobrescrever trabalho desconhecido.

### Pré-requisitos

Laboratório local descartável em reamur_test, conta fictícia access/modify localization/language, alvo real obtido da lista pelo ID+code, ativo/diretório presente. Alvo não pode ser default de catálogo/admin nem **en-us** canônico. Sort original0..99999, teste+1, depois restauração exata. Não presumirID2/3.

Se não houver alvo elegível, usar --check/aceite manual; **não instalar idioma, mudar defaults, desativar ou renomear para forçar teste**. Não criar fixture ou alterar seed. Sem --fixture neste Tool.

Guardas F8-06/F8-05: B/FUI/MIRROR explícitos loopback, configs literais do wizard com DB_DATABASE=reamur_test/hostname loopback/URL coerente, `.f805-disposable`, fontes/assets CMS/idiomas iguais MIRROR/FUI sem symlinks. Marcador é compatibilidade, não novo ajuste nem garantia de descarte.

**Proibido produção/dados reais/ambiente misto/túnel/proxy produção/concorrência/integrações produtivas.** Operador confirma que PHP usa B/FUI/DB/diretórios corretos; config/nonce/hash não atesta proxy/backend mal configurado.

```bash
export B='http://127.0.0.1:8090/davidcms/dev'
export FUI='/var/www/html/davidcms/dev'
export MIRROR="$(pwd -P)/dev"
# Só após conferir laboratório/DB/configs e alvo NÃO-PADRÃO:
printf 'reamur_test\n' > "$FUI/.f805-disposable"
export ALLOW_F811_HTTP='YES_WRITE_REAMUR_TEST'
read -r -p 'Usuário fictício: ' F811_USERNAME
read -r -s -p 'Senha fictícia: ' F811_PASSWORD; printf '\n'
read -r -p 'ID REAL do idioma não-padrão: ' F811_LANGUAGE_ID
read -r -p 'Código EXATO desse idioma: ' F811_LANGUAGE_CODE
export F811_USERNAME F811_PASSWORD F811_LANGUAGE_ID F811_LANGUAGE_CODE
export F811_REPORT='/tmp/novo-resultado-f811.json'  # novo/inexistente
bash tools/e2e/e2e_f811.sh --http
unset F811_USERNAME F811_PASSWORD ALLOW_F811_HTTP
```

Não enviar credenciais/tokens pelo chat. Report opcional absoluto novo, diretório existente,0600, sem sobrescrever anterior. Preflight bloqueado sai2 antes de workflow. Não remover guardas/desativar TLS/alterar configs reais para forçar verde; config dinâmica/DBDocker não-loopback pode exigir avaliação.

### Fluxo de10etapas preparado — NÃO executado pelo agente no backend

1. Probe único `FUI/admin/view/js/f811-probe-<nonce>.txt`, conferido por B antes de credenciais.
2. Login único CSRF/cookies só em memória, sem jar/storage state.
3. Lista GET ×3 idiomas, snapshot de padrões/opções completas e ativos; sem trocar default.
4. Localizar ID/código real ativo, diretório presente, não default/não en-us.
5. GETedit verifica código disabled sem name, modify, campos originais/ordem segura; captura estado sem expor name/locale no relatório.
6. POSTedit com token errado e sort+1 deve não alterar nada; GETconfere.
7. POSTedit válido só sort+1; reabrir e conferir todos os outros campos e padrões inalterados.
8. Revalidar estado inteiro e POST restaurando sort original, depois GET exato.
9. GET com/sem filtro mantém padrões/status/opções, alvo correto.
10. Revalidar restauração exata, mesmos códigos/contagem/status/opções e padrões; nenhum idioma novo.

Não há POST de padrões, troca de code/locale/status, criação/exclusão de idioma/diretório, SQL/reset/recreate/upgrade/seed, disparo de serviço externo ou suíte antiga. Redirect externo não recebe request/cookie; POST307/308/timeout não é repetido. A restauração ocorre só depois de conferir o estado esperado; em falha para e preserva evidências/ordem pendente para inspeção, sem retry/restauração cega.

JSON: kind=http-workflow, operation=nondefault-sort-and-restore, ID/code/ordem original/teste, status/etapas/restoration/cleanup/defaultsUnchanged. **Aceite:** passed+10etapas pass+restoration complete+cleanup complete+defaultsUnchanged true. Aprova apenas fluxo HTTP limitado, não teste de todos os saves/defaults nem visual. Não inclui name/locale, HTML completo, credenciais/cookies/tokens.

Probe só é removido se regular/conteúdo igual. Dados normais de login/sessão/rate limit ficam como operação do laboratório. Se probe não puder ser removido, status/cleanup sinalizam falha, mesmo com ordem restaurada.

## 5. Aceite no navegador

- [ ] Lista/form no shell, desktop/mobile/skins/claro/escuro, legibilidade/contraste e sem overflow externo.
- [ ] Busca nome/code/locale, status0/1, reset inclusive0, total/página2/recuo. Volume21+ no servidor só com dados de teste previamente autorizados;45 foram fixture, não são novos idiomas instalados pelo pacote.
- [ ] Seletores de padrão completos mesmo com filtro/grade vazia/página2; atuais selecionados, inativos disabled; GET não muda padrões.
- [ ] Edit repopula nome/locale/ordem/status após erro sem fatal; name/locale até32 e campos/IDs preservados.
- [ ] Code disabled/sem name permanece imutável mesmo POST adulterado; aviso de diretório ausente não cria pasta/pacote.
- [ ] Default de qualquer app não pode ser desativado; UI bloqueia e backend nega adulteração. Readonly não vê Save, fieldset disabled e POSTs negados.
- [ ] CSRF em defaults/edit e valores inválidos não persistem; teste positivo manual de padrões/status/locale somente no laboratório e restauração consciente pelo operador (fora do HTTP limitado).
- [ ] Padrão pode ser salvo manualmente apenas se habilitado, mantém regra antiga; não há nova política de diretório no validator. Nunca usar idioma padrão real/produção em teste.
- [ ] i18n en-us/pt-br/es-eu, contadores, gate getEnabledCodes preservado; nenhum outro módulo/site/editor/Appearance/configSMTP alterado.

ConfirmarTarefa11 somente após aplicação/homologação do usuário; não encerrarFase8 automaticamente.

## 6. Falhas, restauração e rollback

Timeout pode ocorrer depois da escrita. Consultar JSON (ID/code/ordens/etapa), inspecionar somente o idioma de laboratório e seu estado/padrões antes de qualquer ação. Não presumir que o POST não ocorreu nem reenviar para forçar verde.

Se restoration=pending/failed, restaurar **apenas a ordem original** do alvo conhecido pela UI após validar identidade/name/locale/status/code. Não trocar defaults ou outros campos/pastas. Se houve divergência inesperada, parar para diagnóstico; o Tool não faz compensação cega. Não usar SQL/reset/rm-rf/limpeza ampla.

Probe alterado/processo morto pode restar; inspecionar apenas f811-probe-<nonce>.txt exato, nunca limpar view/js amplamente. Rollback de código não restaura metadados de testes ou relatórios: acertar o laboratório primeiro.

Com patch original/backup e alvos sem mudanças posteriores:

```bash
git apply -R --check "$ENTREGA/F8-11.patch"
git apply -R "$ENTREGA/F8-11.patch"
```

Parar/revisar se não aplicar; sem reset--hard. Restaura dez/remove cinco novos, sem reverter tarefas aceitas; sincronizar só alvos e invalidar cache normal. A base volta a não exigir CSRF e às limitações de repovo/callback/CLI antigas; risco conhecido, não operação recomendada.

## 7. Limites

Nenhum idioma/default/status/diretório real, DB/HTTP/browser/produção/deploy/GitHubwrite ouActions foi alterado/executado nesta preparação. Sem PHPStan/PHPUnit completo/matrizPHP nem prova de contraste nativo. Sem JS runtime, pack, schema, grant ou novo serviço.

F402 histórico continua destrutivo e não auditado integralmente (inclusive agregados/SQL legados); só compatibilidade de token. Não se prometem10checks reais verdes nem se usa a neutralização de agregados antigos como evidência. Não chamar f402 para preparar teste novo.

Publicação de código não comprova deploy. Tarefa10 concluída segundo o usuário; apenas igualdade15fontes públicas foi confirmada independentemente. Não se infere encerramento da Fase8.

Referências: [ADR0055](../agents-ia/decisoes/0055-f8-11-idiomas-no-painel.md), [F8-10](F8-10-Mensagens-Contato.md), [F8-06](F8-06-Paginas-CMS.md), [base pública](https://github.com/davidcreator/davidcms/commit/b3eb7262e77ef25335e21446c0f64c7017dfe1a6).
