# Fase 9 — Backlog de paridade Gutenberg para o editor de conteúdo

**Escopo solicitado pelo usuário:** criar o editor exclusivo **BDREditor** para o ReamurCMS, com ferramentas de autoria de conteúdo e interação inspiradas no editor de blocos do WordPress/Gutenberg.

**Decisão de escopo:** o nome do editor é **BDREditor**. Criar uma ferramenta de autoria própria e exclusiva do ReamurCMS para posts/páginas, usando a experiência de blocos do WordPress Gutenberg somente como referência de interação. **Não instalar, incorporar, importar nem depender do Gutenberg** no projeto; não copiar código ou assets. A implementação permanece nativa do ReamurCMS e o JSON validado pelo servidor é a fonte de verdade. Não inclui o WordPress Site Editor (tema, templates globais, header/footer e estilos globais), colaboração simultânea, nem extensões de terceiros.

**Base consultada:** `main` em `d9e09d8b707f8a4489d92e07b70cc2df70b8a5c9` (2026-10-25). A comparação dos 11 arquivos de código cumulativos de F9-26 encontrou hashes idênticos aos arquivos correspondentes dessa `main`.

**Estado de execução:** este arquivo é mapa de escopo, não uma tarefa oficial numerada nem autorização para staging, deploy, migration, banco ou escrita no GitHub. A roadmap publicada termina em F9-27; uma próxima tarefa formal precisa ser nomeada sem renumerar tarefas históricas.

## O que já está na base cumulativa F9-22–F9-26

- Documento em JSON v2 com compatibilidade de leitura de documentos v1; texto inline por runs e marks `bold`, `italic`, `underline`, `strike` e `code`, links controlados, conversão em memória e renderização allowlisted.
- Inserter pesquisável, comando `/`, ações de bloco, duplicação com IDs novos, transformação de blocos de texto, outline hierárquico e reordenação acessível.
- Blocos e mídia atuais: parágrafo, título, lista, imagem, citação, vídeo embed limitado, botão, separador, grupo, colunas, galeria e cover; quatro padrões de inserção locais, sem persistência compartilhada.
- Melhorias estáticas de teclado, semântica, foco, localização PT-BR/EN-US/ES-EU e limites/performance do editor.
- A F9-25 continua parcial: persistência de padrões compartilhados e preview de drafts dependem de aprovação de ACL/segurança. Não adicionar autosave, revisões, endpoint de draft ou armazenamento compartilhado por inferência.

## Backlog funcional proposto

### 1. Formatação e comandos de edição

- Atalhos de teclado para marcas, links, undo/redo e ações de bloco, sem `execCommand`.
- Toolbar inline e de bloco com estado acessível, foco/seleção preservados e anúncios de estado.
- Colar como texto puro inicialmente; conversões explícitas e seguras de texto colado em parágrafo/lista.
- Seleção múltipla e operações em lote apenas se o modelo de seleção e undo garantir operações atômicas e sem perda.
- Estados de bloco vazio, inválido e bloqueado devem continuar visíveis; nenhum erro vira conteúdo vazio ou perda silenciosa.

### 2. Blocos editoriais adicionais

Avaliar e adicionar somente tipos que tenham contrato JSON, validação PHP, renderização segura, controles no editor, preview administrativo, idiomas e fixtures alinhados:

- Tabela estruturada (linhas/células, cabeçalho opcional e limites estritos; sem HTML livre).
- Arquivo/download com referência local validada e rótulo acessível.
- Áudio, apenas com origem permitida e markup sem scripts.
- Aviso/callout, mídia-e-texto, conjunto de botões, detalhes/accordion, espaço e código pré-formatado, conforme prioridade editorial.
- Aumentar a allowlist de embeds somente com provedores e hosts explícitos; manter iframe sandbox e política de segurança.

A ordem e limites concretos serão definidos antes de cada incremento. Blocos arbitrários, scripts, CSS inline e HTML livre não fazem parte do escopo.

### 3. Organização, transformações e navegação

- Revisar operações entre tipos e conversões com preservação de atributos compatíveis.
- Melhorar breadcrumbs/foco do bloco selecionado e navegação entre pais/filhos.
- Alternativas de teclado a drag-and-drop em todos os níveis.
- Copiar/duplicar blocos preservando dados válidos e regenerando IDs; colar entre documentos somente com validação e limites.

### 4. Padrões e fluxo editorial

- Expandir biblioteca de padrões locais incorporados e categorias, sem persistência compartilhada.
- Confirmação de alterações não salvas e status de salvamento explícito no POST já existente.
- Padrões persistentes, revisões, autosave, colaboração e preview público de draft ficam fora até aprovação separada de ACL, persistência, conflitos, CSRF e endpoints.

### 5. Qualidade de produto

- Teclado, foco, contraste, leitores de tela, navegação mobile e mensagens nos três idiomas suportados.
- Fixtures sintéticas para limites de bytes, número de blocos/runs, profundidade, URLs e composição de tipos.
- Verificações estáticas e testes unitários locais não substituem teste real. PHP/SQL/browser/app/staging só quando as autorizações e pré-condições aplicáveis estiverem resolvidas.

## Sequência sugerida (sem numeração oficial)

1. **Gap audit funcional:** matriz recurso × estado atual × critério de aceite; confirmar prioridades editoriais e limites antes de qualquer schema novo.
2. **Incremento de blocos utilitários:** começar por tabela estruturada e arquivo/download, cobrindo contrato → validador → renderer → editor → idiomas → fixtures, sem migration.
3. **Incremento de edição:** atalhos e operações de copiar/colar/seleção com undo atômico e alternativas acessíveis.
4. **Incremento de mídia/embeds:** tipos necessários escolhidos pela matriz, allowlist de provedores e restrições de segurança.
5. **Incremento de padrões locais e polimento:** melhorar inserção e feedback sem armazenamento compartilhado.
6. **Validação posterior:** quando o usuário decidir, preparar staging/release gate completo. Esta fase não se inicia agora.

## Guardrails

- Nenhum clone integral do projeto; somente arquivos-fonte pontuais e fixtures sintéticas.
- Nenhum GitHub write, deploy, acesso a produção, migration ou banco nesta etapa.
- Não atribuir uma numeração de tarefa não aprovada nem reusar/renomear tarefa histórica.
- O servidor continua autoridade para validação e renderização; a UI não persiste HTML arbitrário.
- Segredos, dumps privados, credenciais e dados reais nunca entram em pacotes, logs ou fixtures.
