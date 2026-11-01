# F9-97 — Copiar e aplicar estilo

## Objetivo e uso

Copie a apresentação de um bloco e aplique-a a um bloco compatível ou a uma seleção de blocos. A seleção em lote usa a barra atual de seleção múltipla; a ferramenta não cria nem agrupa blocos.

- **Copiar estilo** guarda apenas uma lista fechada de atributos de apresentação.
- **Aplicar estilo** abre prévia por bloco com os valores atuais → novos e requer confirmação explícita. Cancelar ou pressionar `Esc` não altera o documento.
- A aplicação em lote é validada integralmente antes de qualquer alteração e gera uma única ação de histórico (Desfazer).

## Escopo de transferência

- Atributos `appearance` permitidos são compartilhados entre tipos de bloco, pois descrevem a apresentação geral.
- Atributos visuais nativos só são transferidos entre blocos do mesmo tipo: alinhamento de parágrafo, dimensões/alinhamento de mídia, apresentação de separador, colunas de galeria, tom de aviso e altura do Espaçador.
- Nunca são copiados texto, links, URL/destino, imagens/arquivos, atributos de conteúdo, IDs, bloqueios, hierarquia ou estrutura.
- Tipos diferentes sem aparência compartilhável são incompatíveis. Alvos bloqueados ou sem mudanças efetivas são rejeitados; um lote com qualquer alvo inválido é recusado por inteiro.
- Pages e Blog compartilham apenas a ferramenta/editor; documentos continuam independentes. Asset cache key: `threejs-43-copy-style`.

## Validação disponível

- `node --check dev/admin/view/js/blog-block-editor.js`
- `node tools/f9-97-copy-style-smoke.js`
- Smokes F9-61, F9-66 e F9-76–F9-96.
- `python3 tools/f9-57-static-battery.py`
- Parsing de CSS com `tinycss2`, se disponível.
- PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação runtime PHP/browser.
