# F9-98 — Biblioteca local de estilos

## Objetivo

Salvar os estilos visuais permitidos da F9-97 como presets nomeados, para reutilização posterior sem armazenar nem duplicar blocos.

## Uso

- **Salvar como estilo** abre um diálogo acessível para nomear o preset; nomes duplicados não são aceitos. Cada usuário pode manter até 50 presets por contexto.
- **Biblioteca de estilos** oferece busca, tipo de bloco e resumo dos campos visuais; permite aplicar ou excluir presets.
- O preset é aplicado ao bloco atual ou à seleção múltipla compatível. A aplicação reutiliza a prévia da F9-97, lista os valores anteriores e novos, exige confirmação e executa uma única etapa de desfazer.
- O diálogo de salvamento e a biblioteca confinam o foco; `Esc` fecha sem alterar o documento.

## Persistência e segurança

- Armazenamento apenas no `localStorage` deste navegador, com chave identificada por usuário e separada entre Pages e Blog. Não há escrita no servidor, banco de dados ou migração.
- Limite de 50 presets e tamanho serializado de 512 KiB; erros de disponibilidade/quota não alteram o conteúdo.
- Só são armazenados descritores `sourceType`, `appearance` validado e atributos visuais de uma lista fechada. Nunca texto, URLs, mídia, conteúdo, IDs ou bloqueios.
- Aplicação valida todos os alvos como uma operação atômica; alvos bloqueados/incompatíveis, estruturas inválidas e alterações sem efeito são recusados.
- Namespaces locais por usuário e contexto preservam a independência de Pages e Blog. Asset cache key: `threejs-44-style-presets`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-98-style-presets-smoke.js`
- Smokes F9-61, F9-66 e F9-76–F9-97.
- `python3 tools/f9-57-static-battery.py`
- Parsing de CSS com `tinycss2`, se disponível.
- A validação da persistência no ambiente real do navegador não é simulada pelo smoke. PHP CLI e navegador real não estão disponíveis neste ambiente.
