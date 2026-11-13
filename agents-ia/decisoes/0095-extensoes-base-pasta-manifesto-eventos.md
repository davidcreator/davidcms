# ADR 0095 — Extensões: pasta, descrição conferida, eventos e saída controlada na página

| | |
| --- | --- |
| **Status** | ✅ aceita (etapa EX-01, 2026-11-13) |
| **Data** | 2026-11-13 |
| **Contexto** | Primeira etapa da frente de extensões (EX-01 → EX-02 aviso de cookies e consentimento → EX-03 meio de pagamento). O CMS já reservava `dev/extension/`, a constante `DIR_EXTENSION`, o namespace `Reamur\Extension` no autoload e um grupo `extension` no admin, mas nada disso carregava código. Apoia-se na política de conteúdo (ADR 0093) e nos segredos cifrados (ADR 0094). Guia de uso: [`docs/guias/Guia-Extensoes.md`](../../docs/guias/Guia-Extensoes.md). |

## O problema

Colocar uma etiqueta de medição, um código de verificação de site ou reagir a um pedido novo exigia editar o núcleo. Depois da ADR 0093 isso ficou mais difícil de fazer certo: um `<script>` colado no template é bloqueado pelo navegador, e a saída fácil seria afrouxar a política do site inteiro.

Faltava um lugar para esse código que:

1. possa ser ligado e desligado sem editar o núcleo;
2. mostre a quem liga o que a extensão pretende fazer, antes de qualquer código dela rodar;
3. não reabra o que as etapas de segurança fecharam (script livre na página, segredo legível no banco, erro que derruba o site).

## Decisão

### 1. Uma extensão é uma pasta com uma descrição e uma classe

`dev/extension/<código>/extension.json` (a descrição) e `extension.php` (a classe `Reamur\Extension\<Código>\Extension`, que estende `System\Library\Extension\Extension`). O código é o nome da pasta.

A extensão chega ao servidor por FTP ou deploy. **Não há envio de ZIP pelo admin**: quem já consegue gravar arquivos no servidor não ganha nada novo, e quem só tem a senha do admin não ganha um caminho para gravar PHP.

### 2. A descrição é lida de forma estrita

`Extension\Manifest` aceita só as chaves conhecidas; uma chave desconhecida, em qualquer nível, invalida o arquivo. O que a tela mostra a quem liga é exatamente o que vai valer. Tamanho máximo de 64 KB; versão mínima do CMS e do PHP em `requires`.

### 3. Desligada, nenhum código da extensão roda

Copiar a pasta não liga nada. A tela do admin lê só o `extension.json` e nunca inclui o PHP de uma extensão. O carregamento acontece em um ponto (`Extension\Manager::boot()`), só para as ligadas, só na aplicação que a extensão declarou (`apps`), depois de conferidos de novo a descrição, a compatibilidade e os campos obrigatórios.

### 4. Estado em `rms_setting`, sem mudança de schema

`code = 'extension'`, chaves `<código>.enabled`, `<código>.config` (JSON) e `<código>.secret.<campo>`. Sem tabela nova. O valor gravado é conferido contra a descrição a cada uso: o que não passa chega à extensão como o valor padrão.

### 5. Segredos de extensão seguem a ADR 0094

Campo `"type": "secret"` é gravado por `SecretBox`, preso a `extension.<código>.<campo>` (o segredo de uma extensão não abre em outra). Não volta para a tela. Entra no inventário de *Configurações → Proteção dos segredos*, de modo que "Proteger segredos" e a troca de chave o alcançam. Valor que a chave não abre chega vazio, com `secretLocked()` verdadeiro.

### 6. Eventos com prioridade; erro de extensão fica no log

`Engine\Event` ganha prioridade (número menor roda antes) e `has()`. O `Manager` embrulha cada ouvinte de extensão: exceção ou erro do PHP vira uma linha no log e os demais ouvintes rodam. Texto impresso pelo ouvinte (`echo`) é descartado, com aviso no log, para não sair antes dos cabeçalhos da resposta. O ouvinte recebe cópia dos dados do evento: a extensão observa, não altera o que o núcleo grava ou envia.

Eventos desta etapa: `page/output` (site público, ao montar o cabeçalho) e os três eventos de pedido que já alimentam os webhooks (`order_add`, `order_edit`, `order_history_edit`), entregues depois deles.

### 7. A extensão não escreve HTML na página

No `page/output` a extensão recebe um `Extension\PageOutput` e pede itens: meta tag, script por endereço, script embutido. O núcleo escreve as tags, com escape e com o nonce da resposta. Script por endereço só sai se o endereço for `https://` e estiver coberto por uma origem `script-src` da descrição **daquela** extensão. Pedido recusado vai para o log e não aparece na página. Limites: 60 itens por página, 20000 bytes por script embutido. Não há `http-equiv`.

As prévias do admin (página e blog) não disparam `page/output`.

### 8. Endereços de fora valem só com a extensão ligada, só no site público

A chave `csp` da descrição lista origens `https://` por tipo de recurso, com as mesmas regras de `REAMUR_CSP_SOURCES` (ADR 0093): sem palavras-chave, sem `*`, sem `http://`. `Csp::addSources()` as acrescenta à política do **catálogo** quando a extensão entra em funcionamento. A política do admin nunca recebe origem de extensão. Não existe como liberar script embutido livre nem `eval`.

### 9. Três formas de desligar, da tela ao arquivo

1. *Extensões → Desligar*. A tela não executa código de extensão, e no admin as extensões não rodam nas rotas de login, saída, permissão negada e na própria tela de extensões.
2. `define('REAMUR_EXTENSIONS', false);` no `config.php`: nenhuma extensão é carregada, sem tocar no banco.
3. Apagar a pasta.

### 10. Permissão própria

Rota `extension/extension`. Instalação nova já a concede ao grupo Administrator. Site instalado: `install/sql/upgrade_ex01_extension_grant.sql` (idempotente; `rollback_ex01_extension_grant.sql` desfaz) ou marcar a rota em *Grupos de usuários*. Ligar, desligar, salvar e remover dados são POST com permissão de modificar e token.

### 11. Pasta fechada ao navegador

`extension/.htaccess` (`Require all denied`). `tools/check_producao.sh`, check 19, reprova se um `extension.json` for entregue. Em servidor que não lê `.htaccess`, o bloqueio é da configuração do servidor.

### 12. Extensão de exemplo

`site_verification` ("Verificação de site"): quatro campos de texto com formato conferido, quatro meta tags (Google, Bing, Pinterest, Meta). Sem script e sem endereço de fora. Vem desligada.

## Efeito

| Para quem | O que muda |
| --- | --- |
| Visitante | Nada enquanto nenhuma extensão estiver ligada: a página pública sai idêntica à de antes |
| Site instalado, ao atualizar | Sobe a pasta `extension/`. Para a tela aparecer: rodar o SQL de permissão ou marcar a rota. Em nginx: fechar `extension/` |
| Quem administra | Item **Extensões** no menu; lista com situação, o que cada uma declara, ligar / desligar, configurar, remover dados |
| Quem escreve extensão | Contrato em `docs/guias/Guia-Extensoes.md`; os exemplos do guia são executados pelos testes |
| Quem escreve o núcleo | Evento novo para extensões: `$events->has()` + `trigger()`; a saída na página pública passa por `PageOutput`, nunca por `|raw` novo |

Arquivos: biblioteca `system/library/extension/` (6 arquivos), pré-ação `startup/extension` nas duas aplicações (a última da lista), tela `admin/controller/extension/extension.php` com dois templates e textos em três idiomas, dois arquivos SQL. Constante opcional `REAMUR_EXTENSIONS`. `ASSET_V` do admin de `8.1.2` para `8.1.3`.

## Verificação

Ambiente local (PHP 8.2, MariaDB em modo estrito, Chromium headless), com a extensão de exemplo e uma extensão descartável feita para tentar cada desvio.

| Situação | Resultado |
| --- | --- |
| Nenhuma extensão ligada | HTML público idêntico ao de `origin/main` |
| "Verificação de site" ligada / desligada | Meta tags aparecem no `<head>` / somem; a configuração fica gravada |
| Nome com HTML, valor com aspas e `</script>` | Saem com escape na tela e na página; o script embutido recebe o nonce |
| Script de endereço não declarado; script com `</script` | Recusados, com linha no log; não aparecem na página |
| Ouvinte que lança exceção | Linha no log; página 200; o outro ouvinte roda |
| Ouvinte que dá `echo` | Texto descartado, linha no log; a página começa em `<!DOCTYPE html>` e mantém o cabeçalho da política |
| `extension.php` gravado com BOM | Não é incluído; "not started" no log; página 200 (incluído, daria erro fatal) |
| Política de conteúdo | Origens declaradas entram em `script-src` / `connect-src` só no site público e só com a extensão ligada; admin inalterado. No navegador: scripts permitidos rodam, o não declarado não roda, console sem erro |
| Segredo | Não aparece em nenhuma tela; "Proteger segredos" o cifra e o site continua lendo; com outra chave chega vazio, com aviso, e o site segue 200 |
| `REAMUR_EXTENSIONS` falso | Nenhuma saída, política base, aviso na tela, lista utilizável |
| `extension.json` quebrado, versão exigida maior, pasta apagada | "Inválida" / "Incompatível" / "Pasta ausente"; só **Desligar** (e **Remover dados**); site 200 |
| Usuário sem a permissão | Sem item no menu; GET 403; POST redirecionado; banco inalterado |
| Idiomas do admin | Textos em `en-us`, `pt-br`, `es-eu` |

Testes: `tests/Unit/Extension/` (descrição, estado, saída, gerenciador, tela, eventos e política, fiação, inventário de segredos, exemplos do guia). Prova por mutação: 78 trocas que retiram uma garantia cada, todas acusadas por algum teste.

## Limites

- **Uma extensão ligada é PHP com o alcance do CMS.** Lê o banco, os arquivos e as configurações. A descrição mostra a intenção e limita a saída na página e a política de conteúdo; não isola o código. Só se liga extensão de origem conhecida.
- **Erro fatal que o PHP não deixa capturar derruba a página** enquanto a extensão estiver ligada: classe declarada duas vezes, falta de memória, tempo esgotado, `exit`. O caso mais comum de erro de compilação (arquivo que não começa por `<?php`, por exemplo com BOM) é conferido antes de incluir. Saída: as três formas do item 9.
- **Extensão com defeito escreve no log a cada página aberta.** Não há limite de linhas por extensão.
- **`.htaccess` só vale sob Apache.** O check 19 acusa a pasta aberta, depois do fato.
- **Desligar pela tela deixa a linha `<código>.enabled = 0`**; "Remover dados" só é oferecido quando há configuração ou segredo gravado, ou quando a pasta saiu do servidor.
- **Fora desta etapa:** tela própria de extensão, rota pública própria, arquivos da extensão servidos ao navegador, tabelas próprias, rotina de instalação / atualização, envio por ZIP, loja. Consentimento de cookies é a EX-02; meio de pagamento, a EX-03.
- **Não foi exercitado:** Apache / LiteSpeed / nginx reais (o servidor de teste ignora `.htaccess`; o check 19 reprova nele, como esperado); produção; as prévias do admin com extensão ligada foram conferidas por teste de unidade, não pelo navegador.
