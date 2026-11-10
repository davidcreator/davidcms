# Fase 9 · Tarefa 13 — Parser, serializer e conversor legado do Blog

**Data:** 2026-10-24 · **HEAD base:** `9cb6e196a3d8b77008961dd1736ec77f3bc01ea7`  
**Estado:** implementação e fixtures preparados; sem integração de UI/persistência e sem execução de testes PHP.

## Implementação

`dev/system/library/blog/block_document.php` fornece classe PHP sem dependência externa, com as operações:

- `convertLegacy($html)`: parser de subset HTML para árvore F9-11, sem efeito colateral;
- `validateDocument($document)`: valida versão, tipos, campos, atributos, IDs únicos, limites e imagem;
- `serializeDocument($document)`: JSON canônico determinístico, somente para documento válido;
- `deserializeDocument($json)`: rejeita JSON inválido, oversized, tipo/versão desconhecidos e shape inválido.

O ID é determinístico para o mesmo HTML e caminho do nó. O parser usa DOM/libxml com `LIBXML_NONET`, limita tamanho e rejeita DOCTYPE/ENTITY. O JSON usa a versão F9-11 `format_version: 1` e os campos `id`, `type`, `attributes`, `children`, `content`.

## Subconjunto conversível

| HTML legado | Bloco |
| --- | --- |
| `p` | `core/paragraph` |
| `h1`–`h6` | `core/heading`, atributo `level` |
| `ul` / `ol` com `li` de texto | `core/list`, `core/list-item` |
| `blockquote` | `core/quote` |
| `img` | `core/image`, somente caminho local `/image/...` ou `image/...` |
| `hr` | `core/separator` |
| `br` dentro de texto suportado | quebra de linha no conteúdo |

Tags/atributos fora da allowlist, HTML inline formatado, listas aninhadas, mídia remota, handlers, scripts, links genéricos, estrutura desconhecida, bytes inválidos, warning de parser e entradas acima dos limites bloqueiam a conversão inteira.

## Garantia de preservação

Resultado `review_required` inclui `document: null`, diagnóstico e o `source_html` original sem alteração. Não há conversão parcial. O consumidor deve manter o HTML legado e solicitar revisão; abrir/fechar formulário não persiste nada. Não há fragmento arbitrário marcado como bloco confiável. O editor e o fluxo explícito de salvar ficam fora desta tarefa.

O limite de imagem é conservador por segurança e precisa ser confrontado com a Media Library/path guard antes da integração. A renderização pública e contextual escaping são F9-15, não são implementados aqui.

## Fixtures

`tests/fixtures/legacy-conversion.json` cobre entidade e `<br>`, headings/lista/citação/separador, imagem local, entrada vazia, formatação inline não suportada, event handler, imagem externa, script e estrutura desconhecida. `tests/run_f913_fixtures.php` é runner funcional de round-trip/determinismo para ambiente de desenvolvimento futuro; **não foi executado**, de acordo com a restrição ativa de não rodar testes PHP/app/browser/SQL.

A Tool `tools/e2e/e2e_f913.sh --check` é estática: valida contrato, mapa, fixtures e marcadores de salvaguarda; não interpreta PHP nem prova a correção runtime. Sintaxe PHP precisa ser verificada pelo mantenedor em ambiente controlado.

## Limites e sequência

- Nenhuma tela/blog form, Controller, Model, renderer ou persistência foi integrada.
- Nenhuma linha de post foi lida ou convertida; nenhuma tabela/banco foi acessado.
- Não amplia o trust boundary atual do `|raw` nem remove/configura CKEditor.
- O usuário/editor deve apresentar preview e status de revisão, persistindo documento somente depois de salvar explicitamente com sucesso.
- Antes de F9-14 integrar, validar a regra de caminhos de imagem com Media Library existente e executar os fixtures funcionais em staging/local apropriado.
