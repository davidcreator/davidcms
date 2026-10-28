# Fase 9 · Tarefa 21 — Roadmap do editor avançado de blocos

**Data:** 2026-10-24  
**Repositório-base:** `https://github.com/davidcreator/davidcms`, branch `main`  
**HEAD consultado:** `81bccd80470b7f8e26eee90c43dc475fb8dafe90` — verificação estática F9-20.  
**Escopo desta tarefa:** definir alvo, arquitetura, etapas e aceite para evoluir o MVP F9-14/F9-16. Sem implementação de código de produto.

## Estado atual observado

O editor tem inserter básico, seleção, inspector, undo/redo, mover/arrastar blocos de raiz, presets de 2/3 colunas e escolha de imagem da Biblioteca. Conteúdo de texto ainda é digitado em `<textarea>`; não existe barra de formatação inline, edição WYSIWYG ou modelo de marks. Renderer e contrato de documento validam uma allowlist restrita. As tarefas F9-18/19 registraram e corrigiram guardrails de publicação; F9-20 confirmou os fontes por SHA e static checker.

O gap principal, portanto, não é apenas quantidade de blocos: falta uma experiência de autoria rica e segura, com edição inline, toolbar contextual, hierarquia/navegação e estrutura de conteúdo versionada sem HTML arbitrário.

## Princípios de produto e arquitetura

1. **WordPress/Gutenberg como referência de interação, não de dependência:** sem copiar código ou assets. Implementação nativa no DavidCMS, encaixada no Admin atual.
2. **O documento JSON é a fonte de verdade.** HTML é sempre renderizado pelo renderer allowlist do servidor; nunca persistir HTML arbitrário do `contenteditable`.
3. **Evolução versionada:** preservar documentos F9-11 v1. Propor documento v2 para inline rich text, sem alterar schema SQL F9-12; `format_version` já existe. O servidor deve aceitar/validar/renderizar v2 antes de a UI começar a salvá-lo.
4. **Compatibilidade sem surpresa:** conversão v1→v2 em memória e serialização canônica no POST normal; documentos antigos não são regravados por abrir a tela. Snapshot e backup legado continuam preservados.
5. **Sem autosave oculto nesta etapa:** histórico/undo local durante edição; persistência explícita pelo fluxo já integrado. Autosave, revisões e colaboração ficam fora do MVP inicial até existir política de conflito/recuperação.
6. **Acessibilidade e segurança são requisitos de saída:** teclado, foco, leitores de tela, limites de payload/complexidade, URLs allowlisted e escaping de cada elemento inline.

## Contrato proposto de rich text v2

Mantém o envelope do bloco (`id`, `type`, `attributes`, `children`, `content`) e a tabela sidecar existente. Em `format_version: 1`, `content` continua string. Em `format_version: 2`, blocos de texto (`paragraph`, `heading`, `list-item`, `quote`, `button`) usam uma lista de segmentos inline em `content`, com shape estrito:

```json
"content": [
  {"text":"Leia ","marks":[],"href":null},
  {"text":"mais","marks":["bold"],"href":"/sobre"}
]
```

- `marks` allowlist inicial: `bold`, `italic`, `underline`, `strike`, `code`.
- `href` é `null` ou link HTTP(S)/path local raiz. Sem `javascript:`, `data:`, protocolo `//`, credenciais, query de editor ou HTML em texto.
- Runs adjacentes idênticos são canonicalizados/mesclados; runs vazios desnecessários são removidos. Links são um atributo estrutural de run, não HTML concatenado.
- Renderização determinística usa apenas elementos fixos (`strong`, `em`, `u`, `s`, `code`, `a`) e escape contextual de todos os textos/URLs. `target`/`rel` são fixos pelo renderer.
- Conteúdo legado v1 vira um run sem marks, sem perdas nem parser de HTML na conversão v1→v2. HTML legado não se torna markup inline.
- Limites para número de runs, comprimento total, profundidade e href são parte do schema PHP e da UI; valores concretos devem ser fixados em F9-22 antes do código.

Este contrato é proposta para F9-22, não implementado nem comprometido no repositório nesta tarefa.

## Sequência sugerida

### F9-22 — Rich text inline versionado (primeira entrega de alto impacto)

Implementar v2 no validador/serializer/renderer antes de ligar a UI; contenteditable controlado por modelo de runs; toolbar inline (negrito, itálico, sublinhado, tachado, código, link); seleção persistida ao usar toolbar; v1→v2 em memória; paste inicial como texto puro; undo/redo inclui operações inline.

**Aceite:** texto e marks sobrevivem serialize/deserialize; links e marks inválidos são rejeitados no cliente e servidor; output não contém HTML arbitrário; seleção/foco/teclas funcionam sem `execCommand`; bloqueios de sidecar não viram conteúdo vazio; HTML legado permanece intacto até POST válido.

### F9-23 — Block UX Gutenberg-like

Inserter pesquisável por categoria, popover contextual e comando `/`; toolbar de bloco (mover, duplicar, transformar, remover); outline de documento; drag/drop e reordenação acessível também para filhos; foco/seleção clara; undo/redo estável.

**Aceite:** qualquer ação tem alternativa de teclado; estrutura aninhada continua válida; duplicação gera IDs únicos; limite de undo e mensagem de estado definidos; sem perda silenciosa ao trocar bloco.

### F9-24 — Blocos de conteúdo e mídia

Galeria, cover, imagem com caption/alinhamento e controles responsivos; embed e botão com previews administrativos; padrões de layout reutilizáveis. Cada tipo recebe contrato JSON/renderer allowlist e previsão mobile; seleção da Media Library mantém paths locais seguros.

**Aceite:** contrato sincronizado JS/PHP/renderer; nenhum CSS/HTML livre; renderizações desktop/mobile; mídia é validada/referenciada e não removida inadvertidamente.

### F9-25 — Padrões, templates e fluxo editorial

Padrões de blocos próprios do CMS, salvar/inserir padrões (se permitido pelo modelo de permissões), title/outline, preview em contexto de catálogo e confirmação de alterações não salvas. Política de autosave/revisões só entra se aprovada separadamente.

### F9-26 — Acessibilidade, localização e polimento

Auditoria por teclado/leitor de tela, mensagens PT-BR/EN-US/ES-EU, contraste/foco, mobile e performance para documentos longos. Limites de payload testados com fixtures sintéticas.

### F9-27 — E2E de staging e release gate

Rodar, com autorização, testes de servidor/browser/DB em instância descartável após confirmar F9-12. Nunca em produção; evidências sem dados reais.

## Requisitos transversais de cada tarefa

- Atualizar em conjunto contrato JSON, JS editor, validador PHP, renderer, preview e fixtures.
- Servidor é autoridade: client validation é UX, não segurança.
- Public render inválido, versão futura ou erro de metadata/query continua fail-closed.
- Verificar suporte de multi-idioma, ACL/CSRF, transação e snapshots nos fluxos existentes.
- Nunca aplicar migrations nem presumir F9-12 sem autorização explícita.
- Nesta conversa permanecem proibidos testes PHP/SQL/browser/app e GitHub write; somente leitura estática e ferramentas de fonte podem ser usadas.

## Decisão recomendada

Começar F9-22 por rich text v2 e edição inline, porque é o maior gap entre um CRUD de blocos e um editor de alto nível, e porque deixa a arquitetura versionada pronta para toolbar contextual, links e padrões sem expor HTML raw.

## Refinamento do espaço de trabalho (implementação posterior à roadmap)

No BDREditor atual, a opção **tela cheia** organiza o trabalho como um espaço de três áreas: navegador do documento (Estrutura ou Elementos) à esquerda, canvas editável no centro e inspetor contextual à direita. Elementos substitui Estrutura somente na navegação esquerda; o inspetor permanece disponível. A opção Configurações recolhe/mostra o inspetor sem trocar a navegação. Fora da tela cheia, o painel compacto continua com o comportamento anterior. Na pré-visualização, o inspetor fica oculto e retorna ao voltar à edição.

**Aceite funcional:** a edição e seleção continuam vinculadas ao documento JSON existente; Estrutura e Elementos não duplicam dados nem alteram persistência; o inspetor continua dependente do bloco selecionado; a interface empilha as áreas em telas menores; salvamento explícito, validação e renderer allowlist não mudam. Nenhuma dependência nova, autosave ou edição de lógica de módulos dinâmicos foi introduzida.
