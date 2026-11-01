# F9-84 — Sumário navegável com âncoras locais

## Escopo

Nova ferramenta de prévia no ribbon **Exibir** e na faixa lateral do editor em tela cheia. Ela coleta títulos não vazios do documento, permite selecionar níveis H1–H6 e mostra o rótulo e o identificador de cada entrada antes da confirmação. Os níveis H2 e H3 vêm selecionados inicialmente.

Ao confirmar, o editor atribui âncoras únicas aos títulos selecionados que ainda não têm uma, reutiliza âncoras válidas existentes e insere um único grupo BDRE nativo no ponto permitido. O grupo usa navegação semântica (`nav`), título e lista nativa de links internos. A ação é aditiva: não substitui blocos; apenas grava os identificadores necessários nos headings selecionados e acrescenta o grupo do sumário. É possível desfazer pela ação de histórico existente.

## Âncoras e segurança

- IDs são slugs ASCII minúsculos e únicos no documento; conteúdo que não produz caracteres ASCII alfanuméricos recebe um identificador seguro de fallback. Colisões recebem sufixos numéricos.
- Atributos de heading `anchor` e marcador booleano `toc_generated` em `core/group` são permitidos somente no formato BDRE v2 e validados no cliente e servidor. IDs malformados ou duplicados invalidam o documento.
- URLs de fragmento aceitam somente `#id` no formato allowlist; outros esquemas, fragmentos livres e dados ativos continuam bloqueados.
- O renderer público emite `id` somente após validação BDRE e apresenta sumários marcados como `<nav aria-label="…">`. Pages e Blog compartilham o renderer, mantendo seus modelos de conteúdo independentes.
- Grupos gerados são excluídos da leitura de títulos ao gerar outro sumário, evitando que o título do próprio sumário vire uma entrada.

## Limitações informadas

- Mudanças futuras no texto do heading não renomeiam automaticamente a âncora já salva, para não quebrar links existentes.
- O sumário gerado é uma lista plana (os níveis são indicados na prévia; a lista mantém semântica simples).
- Exportação Markdown avisa que IDs de heading não fazem parte do Markdown padrão e que links do sumário podem precisar de ajuste em outros leitores Markdown.

## Verificação

- `node tools/f9-84-table-of-contents-smoke.js`
- Regressões F9-76–F9-83 e URLs/mídia existentes.
- `python3 tools/f9-57-static-battery.py`
- `node --check` para JavaScript e parsing CSS com `tinycss2`.

PHP CLI e navegador real não estão disponíveis neste ambiente; nenhuma validação de runtime PHP ou interação real no browser é declarada.
