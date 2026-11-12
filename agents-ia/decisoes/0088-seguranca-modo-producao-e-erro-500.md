# ADR 0088 — Segurança: o modo produção desliga a exibição de erros; falha de tela responde 500, não 404

| | |
| --- | --- |
| **Status** | ✅ aceita (passe de segurança S3 + S4, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | Achados S3 e S4 da análise do repositório, reproduzidos em `dc351f3` numa instalação real (instalador web, MariaDB 11.8.6, PHP 8.2.32) antes de qualquer mudança. Revisa as ADRs 0037 (§1) e 0041. |

## S3 — o modo produção não desligava nada

### O que acontecia

A ADR 0037 fez `config/default.php` ler `REAMUR_ERROR_DISPLAY` do `config.php` da instalação, com `false` por omissão. Só que `config/catalog.php` e `config/admin.php` são carregados depois e traziam `$_['error_display'] = true;`. `Config::load()` junta com `array_replace`: o valor da aplicação vencia sempre. `REAMUR_ERROR_DISPLAY = false` não tinha efeito.

Medido com `REAMUR_ERROR_DISPLAY = false`:

| Situação | Resposta antes |
| --- | --- |
| Senha do banco errada (site e admin) | 500 com `Access denied for user 'reamur'@'localhost'`, SQLSTATE e o caminho de `pdo.php` |
| Tabela `rms_setting` ausente | 500 com o SQL completo e o caminho do arquivo |
| Tabela `rms_page` ausente | 404 com `ReamurCMS error: PDO: SQLSTATE[42S02]… \| SQL: SHOW COLUMNS FROM …` |
| `echo` de um controller antes de uma exceção | 200 com meia página e o caminho do arquivo |
| Erro fatal do PHP | 200 com `<b>Fatal error</b>` |

### Decisão

1. `config/catalog.php` e `config/admin.php` deixam de definir `error_display`. Vale o `default.php`, que lê a constante. `config/install.php` continua com `true`: o instalador roda antes de existir `config.php` e precisa mostrar o erro a quem instala.
2. `framework.php` lê `error_display` com padrão `false` (era `true`) e ajusta `display_errors` do PHP já no primeiro passo, a partir da constante.
3. Depois que os três tratadores de erro estão registrados, `display_errors` do PHP é desligado em qualquer modo (fora do terminal). Quem mostra o detalhe no modo desenvolvimento é a página de erro do CMS: escapada, com status 500. O PHP escrevia o erro cru com status 200 e, em falta de memória, por fora de qualquer buffer.
4. Nenhuma camada escreve mais `getMessage()` direto na resposta. O último recurso antigo do Router (`exit('ReamurCMS error: ' . …)`) saiu.

### O que o instalador grava

`install/config.php.tpl` continua gravando `define('REAMUR_ERROR_DISPLAY', true);`. **Instalação nova nasce com a exibição ligada.** Em produção a linha precisa ser `false` (ou não existir) nos dois arquivos: `catalog/config.php` e `admin/config.php`. Enquanto estiver `true`, o painel inicial do admin mostra um aviso com essa instrução (`warning_error_display`, nos três idiomas).

## S4 — qualquer falha de tela respondia 404

### O que acontecia

`Action::execute()` lançava `\Exception` para "rota não existe" e o Router capturava `\Throwable` em volta de tudo, mandando qualquer coisa para `error/not_found`. Uma tabela ausente na home respondia "404 Página não encontrada". Para buscadores e CDN, a página deixou de existir; para o monitoramento, nada quebrou.

Além disso, os cabeçalhos que o controller tinha definido antes de falhar ficavam na resposta: o 404 em HTML saía com `Content-Type: application/xml` e `Cache-Control: public, max-age=3600` quando a falha era no sitemap.

### Decisão

Duas perguntas, duas respostas.

| Pergunta | Quem responde | Resposta HTTP |
| --- | --- | --- |
| A URL corresponde a uma tela? | `Action::prepare()` | Não → `NotFoundException` → rota de erro da aplicação, **404** como antes |
| A tela funcionou? | o chamável devolvido por `prepare()` | Não → **500**, página de erro estática |

- `Reamur\System\Engine\NotFoundException` (estende `\Exception`) é o único sinal que o Router transforma em 404. `Action::execute()` continua existindo e lança a mesma mensagem de antes; `catch (\Exception)` antigo segue funcionando.
- Continua 404: rota, arquivo, classe ou método inexistente; método com `_` na frente; método não público; `..` na rota; argumento da URL (`rota|a|b`) faltando ou de tipo que não aceita texto. Esse último caso é conferido por reflexão antes de chamar o método (antes dependia de um `TypeError` capturado).
- Continua 404: o controller que decide que o registro não existe e devolve `new Action('error/not_found')`.
- Passa a ser 500: exceção, aviso do PHP, erro de tipo dentro do método, sub-controller inexistente pedido por `load->controller()`, construtor do controller quebrado, erro de sintaxe no arquivo, banco fora do ar, erro fatal.
- Falha dentro da própria rota de erro responde 500 e para. Rota de erro inexistente responde um 404 mínimo, fixo.

### A resposta 500

`Reamur\System\Library\ErrorPage`: HTML curto e fixo, sem banco, tema, template, arquivo de idioma nem autoloader (o `framework.php` carrega a classe com `require_once`). Mostra o nome do site, um link para a home e um código de referência de oito caracteres. Textos em `pt-br`, `en-us` e `es-eu`; outro idioma cai em inglês.

- Tudo o que entra na página é escapado. O link da home só aceita `http(s)://` ou caminho local.
- Cabeçalhos: `500`, `Cache-Control: no-store`, `X-Robots-Tag: noindex`, `X-Content-Type-Options: nosniff`. Os cabeçalhos de segurança do config (`response_header`) são mantidos. Os que o controller definiu antes de falhar são descartados (`Response::reset()`).
- O que o controller escreveu direto na saída antes de falhar é descartado: o Router chama o método dentro de um buffer.
- Quem pede JSON (`Accept: application/json` sem `text/html`, ou `X-Requested-With: XMLHttpRequest`) recebe `{"success":false,"error":"…","ref":"…"}` com o mesmo status. É o formato que os scripts do admin já leem.
- Com `error_display` ligado, a mesma página ganha um bloco com `Classe: mensagem em arquivo:linha`, escapado; o JSON ganha `detail`.
- Falha antes de as configurações do banco carregarem (banco fora do ar, `rms_setting` ausente): a página sai sem o nome do site.

### Log

Cada 500 grava uma linha em `storage/logs/error.log`:

```
[F11E7B37] RuntimeException: mensagem em /caminho/arquivo.php:6 — request: /catalog/index.php?route=…
```

O código entre colchetes é o mesmo mostrado ao visitante. Antes o log tinha só a mensagem, sem classe, arquivo ou linha. As linhas de 404 continuam iguais (ADR 0041).

## Instalador: collation da tabela temporária

`install/sql/schema-complete.sql` e `schema.sql` criam `tmp_f10_catalog_page` com `DEFAULT CHARSET=utf8mb4` sem `COLLATE` e depois a comparam com `rms_page.system_key` (`utf8mb4_unicode_ci`). Num servidor cuja collation padrão de utf8mb4 é outra (MariaDB 11.8: `utf8mb4_uca1400_ai_ci`), o comando 91 de 139 falha com o erro 1267 e a instalação para no meio: 40 tabelas, sem usuário, com os dois `config.php` já gravados. Com o comportamento antigo do Router isso aparecia como "404".

Correção: a tabela temporária declara `COLLATE=utf8mb4_unicode_ci`. Instalação completa refeita pelo instalador web no MariaDB 11.8.6 com as configurações de fábrica: 139 comandos, 44 tabelas, usuário criado, log vazio.

Não corrigido aqui: `importSchema` não usa transação e os `config.php` não são desfeitos quando o SQL falha. Uma instalação que parar no meio ainda exige apagar o banco e os dois `config.php` à mão.

## Efeito em sites existentes

- Site com `REAMUR_ERROR_DISPLAY = false` (ou sem a linha): os erros deixam de aparecer na tela. É a mudança pedida.
- Site com `REAMUR_ERROR_DISPLAY = true`: continua vendo o detalhe, agora dentro da página 500 e escapado.
- Monitoramento e buscadores passam a ver 500 onde viam 404 durante um incidente.
- Páginas saudáveis e 404 legítimos: mesma resposta de antes (8 e 11 casos na bateria HTTP, nos dois modos).
- Sem tabela, coluna, migration, rota ou dependência nova. `Response` ganha `reset()`; `Action` ganha `prepare()`.

## Verificação

- Bateria HTTP de 41 casos na instalação de teste, antes e depois, com a exibição desligada e ligada: 8 respostas 200, 11 respostas 404, 22 respostas 500 nos dois modos. Com a exibição desligada, nenhum dos 22 corpos traz caminho, SQL, nome de classe ou mensagem.
- PHPUnit: `ErrorDispatchTest` (51), `ErrorPageTest` (35), `ErrorDisplayConfigTest` (12). Nove alterações propositais do código (voltar o `true`, mandar a falha para o 404, tirar o escape, manter os cabeçalhos do controller, tirar a conferência de argumentos, tirar o buffer, tirar o `COLLATE`, entre outras) fazem pelo menos um teste falhar.
- Aviso do painel conferido por HTTP com sessão de admin, nos três idiomas, ligado e desligado.

## Limites

- `tools/check_producao.sh` (check 12) reprova detalhe de erro nas páginas que lê, mas não consegue saber de fora se a exibição está ligada enquanto o site está saudável. O aviso do painel cobre esse caso.
- Erro antes de o `framework.php` registrar os tratadores (por exemplo, erro de sintaxe no `config.php`) segue o `display_errors` definido no primeiro passo e não passa pela página 500.
- Não exercitado: Apache/nginx com PHP-FPM (a bateria usou o servidor embutido do PHP), MySQL, driver `mysqli`, e a página 500 atrás da CDN de produção.
- `route=common/header` e `cms/slot.render` respondem 200 com corpo vazio quando chamadas direto. Fora do escopo.
