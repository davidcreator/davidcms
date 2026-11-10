# Fase 9 · Tarefa 11 — Contrato e arquitetura do editor de blocos do Blog

**Data:** 2026-10-24 · **Base verificada:** `davidcreator/davidcms`, branch `main`  
**HEAD:** `61f75381265bb9b4f20741d266a5f8db61c8664b`  
**Estado desta subtarefa:** requisitos e contrato; nenhum código de runtime, schema ou conteúdo foi alterado.

## Objetivo

Iniciar a expansão do editor de conteúdo do Blog para uma experiência de blocos inspirada no Gutenberg do WordPress. A referência é de experiência/produto: a implementação será **nativa do ReamurCMS**, sem transformar o projeto em WordPress nem depender de pacotes/runtime de WordPress.

## Auditoria pontual do Blog atual

- Formulário: `dev/admin/view/template/blog/blog_form.twig`, com abas multilíngues e `textarea[data-rte]` para a descrição.
- Inicialização: `dev/admin/controller/blog/blog.php` carrega CKEditor 4 local; seu fallback atual é textarea.
- Persistência atual: `rms_blog_post_description.description` é `MEDIUMTEXT` HTML por idioma, criada por `upgrade_f7_01_blog.sql`.
- Frente pública: `catalog/blog` coalesce idioma atual → en-US e imprime descrição como HTML (`|raw`), conforme contrato legado.
- Repositório não tem pipeline Node/build para React; assets do admin são servidos localmente. Por isso o plano escolhe editor próprio e entrega incremental, evitando biblioteca externa em runtime.

## Decisões de produto fornecidas pelo mantenedor

1. **Implementação:** editor próprio de blocos, não pacote Gutenberg oficial.
2. **MVP:** inclui layout avançado desde o início (grupos e colunas, com comportamento responsivo).
3. **Conteúdo existente:** converter ao editar, em todas as línguas.

A terceira decisão é implementada com salvaguarda: conversão automática **em memória ao abrir a edição**, com pré-visualização; a conversão só substitui o formato salvo depois de um POST válido e um salvamento explícito bem-sucedido. Abrir/fechar sem salvar não muda o HTML legado.

## Experiência e blocos iniciais

- Canvas visual com inserter, seleção de bloco, inspector lateral, mover por teclado e drag/drop.
- Undo/redo; preview em desktop/tablet/mobile.
- Conteúdo rico por idioma, mantendo título, SEO, imagem de capa, data e status atuais no sidebar.
- Tipos de bloco iniciais:
  - parágrafo, heading, lista, imagem, citação;
  - embed de vídeo com hosts allowlist;
  - botão/CTA, separador;
  - grupo/stack e colunas de 2 ou 3 trilhos, empilhadas no mobile.
- Alinhamento e espaçamento por presets; sem CSS arbitrário, scripts, HTML livre ou iframes genéricos.
- Biblioteca de mídia existente deve ser reutilizada; não duplicar uploads/assets.

## Documento e formato — contrato conceitual

O editor opera em uma árvore JSON versionada por idioma. Forma conceitual de nó:

```json
{
  "id": "identificador-estavel",
  "type": "core/paragraph",
  "attributes": {"align": "left"},
  "children": [],
  "content": "Texto editável"
}
```

O contrato completo está em `F9-11-Contrato-Editor-Blocos-Blog.json`. IDs são estáveis dentro do documento; tipos e atributos são allowlistados; blocos aninhados são permitidos só nos containers aprovados. O conteúdo JSON precisa de `format_version` e rejeição fail-closed para versões inválidas/desconhecidas.

### Persistência a decidir/implementar em subtarefa própria

A coluna atual `description` não deve ser repurposeada sem plano: a frente pública e posts legados dependem dela como HTML. A próxima tarefa técnica deve comparar:

- formato JSON em coluna versionada de `blog_post_description`, mantendo `description` como snapshot/legado; ou
- tabela de conteúdo por idioma, com referência/versionamento explícitos.

A tarefa futura precisa entregar migration **upgrade + rollback**, placeholder de prefixo, backup, compatibilidade com MariaDB do projeto, limites de tamanho e estratégia de fallback. Nenhum SQL é criado ou aplicado aqui.

## Conversão e preservação do legado

1. Ao abrir um post legado, converter a árvore conhecida para blocos em memória e mostrar uma prévia da conversão.
2. Mapear tags suportadas (`p`, headings, lists, blockquote, image) para tipos nativos; normalizar apenas whitespace que não altere a apresentação.
3. Conteúdo não representável deve permanecer recuperável como fragmento legado isolado ou bloquear conversão até decisão — **nunca ser removido silenciosamente**.
4. Após salvar o documento novo, original HTML segue recuperável pelo período de compatibilidade/rollback; política de retenção será fechada na tarefa de migration.
5. Conteúdo com elementos perigosos (`script`, event handlers, protocolos `javascript:`) não é copiado para blocos confiáveis. O leitor legado continua no contrato existente até uma sanitização/migração separada.

## Segurança, renderização e acessibilidade

- O renderer público da tarefa futura produzirá HTML apenas de bloco/type allowlist, com escaping contextual de texto/atributos.
- Imagens só vêm da Media Library/path guard existente. Links aceitam protocolo explícito permitido; embeds somente hosts aprovados.
- HTML legado não passa a ser considerado seguro por ser convertido. A tarefa não amplia o `|raw` atual.
- O CKEditor 4 local (4.22.1) é legado/EOL e tem configuração histórica permissiva; não será usado como editor para novos documentos em blocos nem terá sua configuração permissiva ampliada. A remoção do editor legado do Blog exige cobertura de conversão e plano de rollout próprio.
- Interações principais precisam de alternativas por teclado, foco visível, labels acessíveis, reordenação sem mouse e estados anunciados.
- Conteúdo é por língua existente no Blog; conversão não junta nem sobrescreve línguas.

## Sequência proposta

- **F9-11 (esta):** requisito, formato conceitual e contrato de segurança/migração.
- **F9-12:** ADR de persistência + migration/rollback e fixture round-trip; sem aplicar DB.
- **F9-13:** parser/serializer e conversor legado determinístico (testes com fixtures; sem UI).
- **F9-14:** canvas MVP/editor inserter/inspector/undo/redo; só concluir substituição no Blog após conversor aprovado. CKEditor 4.22.1 é legado/EOL; não deve virar fallback permanente nem ganhar plugins/config permissiva novos.
- **F9-15:** renderer público allowlisted, preview responsivo e compatibilidade de conteúdo.
- **F9-16:** validação integrada de salvamento, migração manual de amostra em laboratório e plano gradual de rollout.

Sequência é proposta, não renomeia tarefas históricas. Cada subtarefa continua sendo um ZIP revisável e independente.

## Fora desta subtarefa

- Nada foi instalado, copiado ou removido do CKEditor.
- Nenhum React/npm/Gutenberg foi adicionado.
- Nenhum post, tabela, página ou configuração de produção foi alterado.
- Nenhuma conversão de conteúdo real foi executada.

## Validação

`tools/e2e/e2e_f911.sh --check` valida o contrato JSON e os guardrails do plano. É uma checagem estática, não testa editor em navegador nem parser PHP/JS de produção.
