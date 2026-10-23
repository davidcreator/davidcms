# Fase 9 · Tarefa 01 — Inventário e proposta de campos personalizados

> Objetivo: propor contratos de campos de conteúdo tipoACF com base no código real, antes de autorizar runtime, tabelas ou migrações.

**Data:** 2026-10-23 · **Base:** `96158da5810262246aa2dd34ca900cf45c0ecbed`.
**Estado:** planejamento solicitado; inventário/modelos preparados e validados. **Desenho/MVP/schema pendentes de aprovação do usuário.** Não há campos personalizados funcionando após aplicar esta entrega.

## Sumário

1. [Transição e limites](#1-transição-e-limites)
2. [Inventário verificado](#2-inventário-verificado)
3. [MVP proposto](#3-mvp-proposto)
4. [Payload e semântica de valores](#4-payload-e-semântica-de-valores)
5. [Modelo lógico, segurança e lifecycle](#5-modelo-lógico-segurança-e-lifecycle)
6. [Arquivos, aplicação e Tool](#6-arquivos-aplicação-e-tool)
7. [Aprovações e próximas tarefas](#7-aprovações-e-próximas-tarefas)
8. [Rollback e evidências](#8-rollback-e-evidências)

## 1. Transição e limites

O usuário declarou Tarefa12 concluída e escolheu explicitamente **encerrar Fase8 por aceite consolidado do operador e iniciar F9/Tarefa01**, só planejamento. Seis fontes públicas da entrega F8-12 conferem com o commit-base. Isso confirma publicação/decisão humana, não um novo teste independente de produção.

**Fase8 encerrada por autoridade do operador.** O arquivo público F8-12-Aceite-Painel.json continua seu modelo pendente; não foi convertido em prova de execução ou substituído por aceite sintético. Nenhum teste aceito é reaberto. Dívidas registradas na fase continuam separadas, não “resolvidas” por transição.

Este ZIP é **somente docs/contrato/Tool**. Não cria controller/model/rota/tabela/coluna/grant, não muda native POST, schema/instalador/Composer/vendor/site/editor/defaults/idiomas, não migra ou preenche dado antigo, não executa PHP/SQL/HTTP/DB/browser. Não instala WordPress/ACF ou pacote externo; “tipoACF” descreve a capacidade desejada, não dependência escolhida.

## 2. Inventário verificado

14arquivos com Git blob/SHA256/bytes no [contrato JSON proposto](F9-01-Contrato-Campos-Personalizados.json), todos lidos na base imutável. Somente arquivos necessários, sem clone/projeto/assets/vendor inteiros.

| Área | Fatos atuais que não podem ser confundidos |
| --- | --- |
| CMS páginas |Admin `dev/admin/controller/cms/page.php` e `dev/admin/model/cms/page.php`; ID page_id; POST page_description[N][title,description,meta_title,meta_description,keyword], status/sort_order. Tabela page_description não armazena keyword; SEO é separado. Sem capa/upload. |
| Público CMS |Controller `dev/catalog/controller/cms/page.php` carrega **model catalog/page**, arquivo real `dev/catalog/model/catalog/page.php`; view cms/page_info. Só status1, idioma ativo/fallback EN e SEO existentes. Não assumir model público cms/page. |
| Projetos |`dev/admin/model/catalog/project.php`: ID project_id, descrição usa **name**, summary/description/tag/meta/keyword, client/url/status/featured/sort, capa/galeria e N:N. Não trocar por title de páginas. |
| Blog |`dev/admin/model/blog/blog.php`: ID post_id, title/description/meta/keyword, image/status/date_published e importador/mídia próprios. Não usar blog_id inventado ou migrar RTE agora. |
| Schema |schema.sql contém page/page_description, project e filhos; Blog vem também do upgrade_f7_01_blog.sql. Nenhuma tabela dedicada custom_field/field_group encontrada nesses schemas verificados. |
| Idiomas/CSRF |Model admin localization/language e serviço system/library/security/csrf reais. IDs das traduções devem vir do registry/tabela; não presumir que pt/es são2/3. |

A busca por nomes de módulo/arquivos e os schemas selecionados não encontraram módulo dedicado. Isso **não é auditoria textual exaustiva de todo vendor/núcleo** nem afirma que nenhum array legado em biblioteca contém a expressão “custom_field”.

O comentário do controller público CMS diz “sanitizado lá”; o payload admin verificado apenas trim/limita strings. **Não presumir sanitização efetiva desse comentário.** O HTML nativo antigo e CKEditor/sanitização seguem dívidas separadas; o novo campo plain-text proposto não é inserido em description|raw.

## 3. MVP proposto

**Tudo nesta seção é proposta, não decisão de arquitetura aprovada.** Primeiro alvo sugerido: **cms/page**, por ser menor e sem upload. Projetos/Blog só candidatos posteriores, não liberados no modelo desta Tarefa01. Não expandir para dados pessoais/contatos/clientes/configurações/idiomas.

| Tipo proposto | DTO normalizado modelado | Limite/regra proposta |
| --- | --- | --- |
| text |string |255codepoints; plain-text. |
| textarea |string |5000codepoints; plain-text com quebras, não rich HTML. |
| number |decimal **string** |precision18/scale6, ponto, sem float/expoente/vírgula/leading zeros/negative zero; sem arredondar silenciosamente. |
| boolean |bool |false é valor válido, não ausência. |
| url |string |http(s), autoridade válida, sem credenciais, até2048codepoints; apenas parsing, sem DNS/requisição. |
| select |string/chave |Uma opção da definição, chave até64; não multiselect/relationship. |

O limite numérico do DTO é **até18 dígitos totais e no máximo6fracionais**, não uma coluna SQL DECIMAL(18,6) já escolhida. Exatidão/faixa/armazenamento físico precisam ser reconciliados no desenho posterior; o modelo usa string e não arredonda ou converte para float.

Fora do MVP: richtext, imagem/arquivo/media, repeater, relationship, cálculos/código arbitrário, conditional UI e migração genérica do conteúdo existente. Não integra a biblioteca de capas do Blog ou jQuery/filemanager.

Chaves snake_case estáveis até64, únicas por entity-type entre grupos. Nome/code de campos nativos e reservados/prototype/config não podem ser usados: title/name/description/keyword/status/image/... não são redefinidos. Exemplos usam prefixo **qa_** para serem claramente sintéticos.

Labels da definição são dados multilíngues; proposta inicial traz en-us/pt-br/es-eu completos. Rótulos e erros comuns da futura interface continuam na camada Language do projeto. Valores começam **admin_only**. Não há exposição pública automática.

## 4. Payload e semântica de valores

Namespace novo **proposto** custom_fields, independente dos names existentes. Nenhum controller atual passa a aceitá-lo por aplicar este ZIP. Exemplo normalizado futuro, **não endpoint atual**:

```json
{
  "custom_fields": {
    "qa_subtitle": { "by_language": { "7": "Title", "12": "Título" } },
    "qa_notes": { "by_language": { "7": "Linha 1\nLinha 2" } },
    "qa_score": { "shared": "0" },
    "qa_enabled": { "shared": false },
    "qa_link": { "shared": "https://example.invalid/reference" },
    "qa_style": { "shared": "compact" }
  }
}
```

IDs7/12 neste exemplo são **fixtures**, não IDs para enviar ao CMS. A tabela/registry real resolve en-us, inclusive se ele não for posição/ID1 no novo componente. Não alterar as convenções antigas do payload nativo.

### Regras propostas a aprovar

- Compartilhado usa shared; localizado usa by_language por ID instalado. Não misturar slots ou criar “idioma0” real; “shared” é marcador lógico, não linha de language.
- **Namespace inteiro ausente preserva nativo/valores e não impõe required novo.** Trade-off deliberado de compatibilidade: cliente legado pode omitir campos até rollout/opt-in explícito. Não vender required como barreira de segurança absoluta; essa regra precisa de aprovação antes de integrar.
- Com namespace presente/grupo ativo, required valida valor compartilhado ou canônico en-us após patch. Ausência de campo/idioma preserva anterior; string vazia explícita limpa só opcionais. Campos required não aceitam vazio/ausência. false e "0" são presentes/válidos. Null/undefined não significam limpar implicitamente.
- Boolean do formulário futuro precisa de normalização explícita de "0"/"1" (hidden+checkbox) para bool; os modelos deste Tool recebem DTO **já normalizado**, não implementam o parser PHP de produção.
- Fallback só quando tradução está **ausente**, não quando o usuário salvou string vazia. Não igualar false/zero/vazio a missing. Essa política nova não altera o COALESCE legado dos módulos existentes.
- Grupo draft/inativo preserva armazenamento e deixa de renderizar/validar required. Não apagar valores porque o campo desapareceu da UI.
- Patch desconhecido/nativo/prototype, idioma não instalado, slot incompatível e tipo inválido são rejeitados no modelo sem alterar o anterior.

O Tool demonstra preservação/atomicidade **em objetos clonados em memória**; isso não prova transação/SQL ou compatibilidade de backend existente. Salvamento nativo+extensão atômico será desenho posterior, não inferido destes testes.

## 5. Modelo lógico, segurança e lifecycle

Três coleções conceituais, sem DDL/nome físico aprovado:

| Coleção proposta | Identidade lógica | Propósito |
| --- | --- | --- |
| groupDefinition |entityType+groupKey |Um entity-type por grupo no MVP, labels, estado/ordem. |
| fieldDefinition |entityType+fieldKey |Grupo, tipo, labels, localized/required/visibility e opções/limites. |
| fieldValue |entityType+entityId+fieldKey+logicalLanguageSlot |Valor normalizado separado de *_description/SEO nativos. |

Avaliar no desenho físico: tabelas de extensão normalizadas e metadados JSON/TEXT validados versus tabelas próprias para labels. Índices/unique keys, versionamento, charset, tamanho e consultas sem N+1 precisam de proposta do agente-install/admin/catalog. **Não há SQL CREATE/ALTER, FK, upgrade, coluna native ou migração neste pacote.**

### Segurança futura — ainda não implementada

- Valores herdam access/modify da entidade e CSRF do serviço existente em toda escrita.
- Gestão de grupos/definições precisará de rota/ACL próprias **a aprovar**, em grupo existente; por exemplo arquivo em admin/cms é possibilidade, não API existente.
- SQL só no Model com binding/escape/IDs int. Não converter chave do cliente em nome de coluna/tabela ou executar eval/expressão/código.
- Publicação só de definição explicitamente public, grupo ativo e conteúdo publicado; valores privados não entram em dados do template/API por engano. Posicionamento e partial Twig precisam de aprovação do agente-catalog, autoescape/plain-text, sem raw.
- Não pressupor um beginTransaction do driver: verificar biblioteca/adapters antes. Sem suporte suficiente, abrir plano do agente-system, não fingir API ou gravar metade dos dados.
- Desativação preserva; hard-delete automático de definição/grupo/backfill está fora. Key/tipo/localização incompatíveis não mudam silenciosamente. Exclusão do conteúdo pai/valores, backups e migração de tipo exigem política explícita posterior.
- Não criar serviço global/Registry ou framework paralelo prematuramente; plano de impacto system/admin/catalog/install se alguma extração vier a ser necessária.

Dívidas carregadas da F8 (CKEditor/licença/sanitização, uploads/órfãos/referências, paginação global/confirm inline, roadmap visual catalog) continuam separadas, não são resolvidas por custom_fields.

## 6. Arquivos, aplicação e Tool

**Seis arquivos de projeto:** novos `tools/e2e/e2e_f901.sh`/`e2e_f901.cjs`, este guia, [contrato JSON proposto](F9-01-Contrato-Campos-Personalizados.json), [ADR0057](../agents-ia/decisoes/0057-f9-01-proposta-campos-personalizados.md) e modificação só da linha24 em docs/README.md.

Acompanhantes: F9-01.patch, MANIFESTO-F9-01.json e SHA256SUMS. Sem dev/**, PHP, SQL, credenciais, dados reais, vendor ou app completo. Modelos sintéticos não são dados de produção nem relatórios nativos falsos.

1. Conferir base96158da5810262246aa2dd34ca900cf45c0ecbed e mudanças posteriores nos alvos; não sobrescrever trabalho concorrente.
2. Extrair fora do repo e `sha256sum -c SHA256SUMS` na extração; branch/PR para staging conforme projeto.
3. Na raiz do repositório, ENTREGA é extração absoluta:

```bash
git apply --check --whitespace=error "$ENTREGA/F9-01.patch"
git apply --whitespace=error "$ENTREGA/F9-01.patch"
git diff --check
bash tools/e2e/e2e_f901.sh --check
```

Alternativa: copiar apenas seis fontes do manifesto. Não copiar árvore/vendor, instalar algo, executar build/upgrade/SQL ou mudar configs de produção. Não há runtime web/cache/asset a atualizar nesta entrega.

### Tool E2E de contrato, não E2E de CMS real

Node20+/Bash, sem npm/PHP/Composer/DB/browser/servidor necessários:

```bash
bash tools/e2e/e2e_f901.sh --self-test
export F901_REPORT='/tmp/novo-f901-proposta.json'  # opcional,novo/absoluto/0600
bash tools/e2e/e2e_f901.sh --check
```

--self-test valida proposta/modelos sem exigir árvore runtime completa; --check também confere14fontes SHA256/Gitblob da referência. Se fonte mudar depois, INVENTORY_STALE bloqueia: revisar inventário em nova entrega, não editar hash para obter verde sem análise. MIRROR exportado para outro dev bloqueia em vez de misturar projetos.

CLI não tem --apply/--migrate/--http/--backend. Não executa contrato como código, PHP/SQL/URL/HTTP ou migração. Arquivo de relatório novo/absoluto, sem sobrescrever. Código0 coerente,1 modelo falhou,2 bloqueio/requisito/inventário inválido. JSON: kind=contract-proposal-validation, approval pending, runtime/schema false e dbOrHttpExecuted false.

**32PASS/0FAIL** nos modelos Node: chaves/reservados/prototype, seis tipos/limites, seleção/URLs parsing sem rede, decimais exatos, false/zero, registry de idiomas dinâmico, ausência/vazio/patch, required, grupo inativo, migração explícita, privacidade/publicação/autoescape modelado, guardas de modify/CSRF **simulados**, inventário stale/symlink em FS privado e proposta nunca automaticamente aprovada.

**Não são32E2Es reais de custom fields funcionando**: não há feature runtime, model PHP persistente, SQL, transação, servidor, Twig novo de produção, navegador ou ACL/CSRF nativos implementados/testados. O escape/guard de bool deste Tool é modelo de intenção, não serviço de aplicação. Diretório de fixture privado é removido e nenhum dado nativo muda.

Commit sugerido:

```text
docs: propor contrato de campos personalizados para a Fase 9
```

## 7. Aprovações e próximas tarefas

Oito escolhas pendentes no JSON, todas **pending**; aplicar ZIP/PASS ou concluir a preparação Tarefa01 não as aprova implicitamente:

1. Primeiro alvo CMS/page e seis tipos/limites do MVP, versus outro foco.
2. Compatibilidade quando namespace ausente e aplicação de required/rollout.
3. Política localizada/EN/fallback só ausência e explicitEmpty/null.
4. Modelo lógico→schema físico/índices/labels/compatibilidade do DB, pelo install.
5. Rota/ACL de gestão de definições e permissões de valores, sem novos grupos fora do mapa.
6. Visibilidade privada inicial e opt-in/posicionamento público Twig.
7. Lifecycle, key/type, exclusão de pai/valores, deprecação/backfill/migração.
8. Testes, backup/atomicidade/rollback/feature rollout.

Fila **proposta** (não comandos/entregas autorizados agora):

- Tarefa02: revisar escolhas e preparar desenho físico/upgrade idempotente/rollback, **só após aprovação de schema**.
- Tarefa03: admin grupos/definições, ACL/CSRF/idiomas/shell.
- Tarefa04: primeiro fluxo CMS form/save/reopen sem reescrever campos nativos.
- Tarefa05: leitura/apresentação pública explícita, sem vazamento de privados/raw.
- Tarefa06: homologação integrada/rollout com fixtures descartáveis, sem reset destrutivo automático.

Cada uma uma subtarefa/ZIP. Não pular do contrato diretamente para tabela/endpoint ou repetir aprovação de F8. A aprovação das escolhas deve ser declarada pelo usuário; uma entrega posterior revisará a versão/estado do contrato. Não trocar flags pending/runtime/schema manualmente só para obter PASS no Tool desta proposta. Antes da Tarefa02, o usuário pode aprovar o MVP proposto ou especificar alterações; detalhes ainda não aprovados ficam declarados, não assumidos como fatos.

## 8. Rollback e evidências

Com patch original, alvos sem mudanças posteriores:

```bash
git apply -R --check "$ENTREGA/F9-01.patch"
git apply -R "$ENTREGA/F9-01.patch"
```

Restaura só índice/remove cinco novos tools/docs/ADR. Não reverte F8/aceites/runtime ou apaga dados/relatórios. Se não aplicar, parar/revisar, sem reset--hard/limpeza ampla. Não há rollback de DB porque nenhum DB foi alterado.

Verificados sintaxe Node/Bash/JSON,32modelos,14fontes por blob/hash, bloqueios de modo/saída/inventário, links, patchcheck/apply/reverse/check após aplicação e hashes/CRC na embalagem. Modelos e FS temporários são evidências de contrato, não execução da aplicação. Sem schema/runtime/HTTP/DB/produção/Actions/deploy ou GitHubwrite realizados pelo agente.

Referências: [ADR0057](../agents-ia/decisoes/0057-f9-01-proposta-campos-personalizados.md), [contrato](F9-01-Contrato-Campos-Personalizados.json), [mapa existente](Estrutura-de-Pastas.md), [Dicionário de Dados atual](Dicionario-de-Dados.md), [F8-12](F8-12-Consolidacao-Validacao-Painel.md), [base pública](https://github.com/davidcreator/davidcms/commit/96158da5810262246aa2dd34ca900cf45c0ecbed).
