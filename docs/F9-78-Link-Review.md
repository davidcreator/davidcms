# F9-78 — Revisão de links e destinos

## Escopo

Ferramenta consultiva e localizada em português, espanhol e inglês para revisar links no documento BDRE atual. A ferramenta é somente leitura: não altera blocos e não busca URLs na internet. Cada apontamento mantém o tipo e o ID do bloco para permitir **Localizar bloco** e retornar ao editor.

Disponível na guia Revisão e na navegação do espaço de trabalho. Pages e Blog continuam independentes; o cache dos respectivos assets é invalidado separadamente.

## Verificações

- Links externos HTTP, apresentados como potenciais conexões sem criptografia.
- Rótulos de link que exibem a própria URL completa.
- Destinos repetidos no documento, apresentados como sugestão para confirmar se a repetição é intencional.

São sinais heurísticos, não uma verificação de disponibilidade, segurança geral ou conteúdo do site de destino. A ferramenta não faz requisições externas e não certifica se um link está quebrado ou funcionando. A validação BDRE é executada antes da auditoria; um documento inválido não é analisado.

## Implementação e preservação

- Links em atributos `url` de `core/button` e URLs `href` em runs de texto rico.
- Runs vizinhos com o mesmo `href` são tratados como um único link, evitando falsos duplicados por formatação.
- Destinos repetidos são agrupados por URL normalizada; cada ocorrência aponta para o seu bloco.
- Analisador puro exportado pela API do editor, sem chamadas de rede ou mutações do documento.
- Cache-bust Pages/Blog: `threejs-25-link-destination-review`.

## Arquivos alterados

- `dev/admin/view/js/blog-block-editor.js`
- `dev/admin/controller/cms/page.php`
- `dev/admin/controller/blog/blog.php`
- `tools/f9-57-static-battery.py`
- `tools/f9-78-link-review-smoke.js`
- `docs/F9-78-Link-Review.md`

## Validação prevista/executada

- Sintaxe JavaScript, smoke sem navegador para HTTP externo, rótulo URL, destino repetido, não mutação e localização PT/ES/EN.
- Bateria estática de regressão, CSS e integridade do ZIP.

PHP CLI e navegador real não estão disponíveis neste ambiente; não se afirma validação de runtime. Não é uma auditoria de segurança nem uma garantia sobre a disponibilidade dos destinos.
