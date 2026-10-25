# BDREditor — reordenação hierárquica acessível

Incremento cumulativo sobre **BDREditor-Outline-Keyboard-Navigation**. Introduz ações explícitas de indentar e desindentar no inspetor do editor exclusivo de conteúdo do ReamurCMS.

## Comportamento

- **Indentar** move o bloco selecionado para o fim dos filhos do contêiner compatível imediatamente anterior no mesmo nível.
- **Desindentar** move o bloco selecionado para a posição imediatamente posterior ao seu pai.
- Os controles são botões nativos, nomeados por localização e indisponíveis quando o movimento não passa pelas precondições estruturais. O foco permanece no mesmo ID após o movimento; mensagens de estado são anunciadas pela infraestrutura acessível existente.
- O helper `moveBlockAcrossHierarchy` opera sobre uma cópia, exige documento v2 válido antes da tentativa, valida o documento inteiro e o limite de 4 MiB após a operação e retorna `null` sem mutação em caso de impossibilidade. A classe só chama `commit` após receber resultado validado, portanto cada movimento é uma única transação e uma única etapa de undo.
- IDs, atributos e conteúdo dos blocos são preservados. Não há alteração de schema nem de persistência.

## Limites e segurança estrutural

- Contêineres elegíveis: `core/group`, `core/column`, `core/list`, `core/gallery` e `core/cover`, sujeitos às regras de filhos permitidos no schema v2.
- Blocos `core/table`, `core/table-row` e `core/table-cell` não podem ser movidos com estas ações.
- O movimento é recusado se esvaziar um contêiner que exige filhos, criar uma estrutura incompatível, ultrapassar limites de documento ou operar sobre origem inválida. A movimentação não reescreve nem normaliza os dados.
- O checker automatizado é determinístico, local e não destrutivo. Nenhum staging, banco, migration, deploy ou escrita no GitHub foi usado.

## Arquivos desta etapa

- `dev/admin/view/js/blog-block-editor.js` — preflight, helper puro, ações e controles do inspetor.
- `dev/admin/controller/blog/blog.php` e `dev/admin/language/{pt-br,en-us,es-eu}/blog/blog.php` — strings PT-BR, EN-US e ES-EU.
- `tests/fixtures/hierarchy_move.json` — fixture v2.
- `tests/check_hierarchical_reordering.js` — checker de movimento, validação, IDs, foco e não mutação.

## Verificação

Executar a partir da raiz do pacote:

```sh
node --check dev/admin/view/js/blog-block-editor.js
node tests/check_hierarchical_reordering.js
```

O checker desta etapa deve reportar `23 PASS`. A checagem de PHP depende de `php -l` quando o interpretador PHP estiver instalado.
