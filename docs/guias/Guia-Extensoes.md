# Guia de Extensões — ReamurCMS

> Como instalar, ligar e escrever uma extensão. Base entregue na etapa EX-01 ([ADR 0095](../../agents-ia/decisoes/0095-extensoes-base-pasta-manifesto-eventos.md)).
> Exemplo pronto para copiar: `dev/extension/site_verification/` ("Verificação de site").

## Sumário

1. [O que é uma extensão](#1-o-que-é-uma-extensão)
2. [Instalar, ligar, desligar](#2-instalar-ligar-desligar)
3. [Se uma extensão quebrar o site](#3-se-uma-extensão-quebrar-o-site)
4. [Escrever uma extensão](#4-escrever-uma-extensão)
5. [`extension.json`, campo a campo](#5-extensionjson-campo-a-campo)
6. [Eventos](#6-eventos)
7. [Saída na página pública](#7-saída-na-página-pública)
8. [Endereços de fora](#8-endereços-de-fora)
9. [Configurações e segredos](#9-configurações-e-segredos)
10. [O que esta etapa não oferece](#10-o-que-esta-etapa-não-oferece)

---

## 1. O que é uma extensão

Uma pasta dentro de `dev/extension/` (em produção, `extension/`) com dois arquivos:

```text
dev/extension/
└── minha_extensao/
    ├── extension.json   # a descrição: nome, versão, campos de configuração, eventos, endereços de fora
    ├── extension.php    # o código: uma classe com os métodos que os eventos chamam
    └── index.html
```

**Uma extensão ligada é código PHP com o mesmo alcance do CMS**: lê e grava no banco, lê arquivos, usa as configurações. O `extension.json` descreve o que ela pretende fazer e limita o que ela consegue colocar na página pública; não é uma cerca em volta do PHP dela. Só ligue extensão de origem conhecida.

O que o CMS garante:

- extensão **desligada não tem nenhum código executado**; a tela de extensões lê só o `extension.json`;
- copiar a pasta para o servidor não liga nada;
- erro de uma extensão vai para o log e a página continua (limites em [§3](#3-se-uma-extensão-quebrar-o-site));
- o navegador não alcança a pasta `extension/` (sob Apache; veja [§2](#2-instalar-ligar-desligar)).

## 2. Instalar, ligar, desligar

1. Copie a pasta da extensão para `extension/` no servidor (FTP ou deploy). Não há envio de ZIP pelo admin.
2. Abra **admin → Extensões**. A extensão aparece desligada, com o que ela declara: onde roda, que eventos escuta e que endereços de fora pede.
3. Se ela tiver campos, use **Configurar**. Campo obrigatório vazio impede ligar.
4. **Ligar**. Vale a partir da próxima página aberta.

**Desligar** mantém a configuração gravada. **Remover dados** (só com a extensão desligada, e só quando há configuração ou segredo gravado) apaga configuração, segredos e estado dela no banco; a pasta continua no servidor até você apagá-la.

Situações que a tela mostra:

| Situação | Significado | O que fazer |
| --- | --- | --- |
| Ligada / Desligada | Normal | — |
| Inválida | O `extension.json` não passou na conferência; os problemas aparecem na linha | Corrigir o arquivo. Se estava ligada, não roda |
| Incompatível | Pede uma versão do CMS ou do PHP que o site não tem | Atualizar o site ou a extensão |
| Pasta ausente | Há dados gravados, mas a pasta saiu do servidor | Devolver a pasta, ou desligar e remover os dados |

**Permissão.** A tela é a rota `extension/extension`. Instalação nova já nasce com ela no grupo Administrator. Em site já instalado, rode `install/sql/upgrade_ex01_extension_grant.sql` no phpMyAdmin, ou marque `extension/extension` (acessar e modificar) em *Usuários → Grupos de usuários*.

**Servidor que não é Apache.** `extension/.htaccess` fecha a pasta sob Apache. Em nginx ou outro servidor, bloqueie `extension/` na configuração dele, do mesmo jeito que `system/`. `tools/check_producao.sh` (check 19) reprova se um `extension.json` for entregue ao navegador.

## 3. Se uma extensão quebrar o site

Em ordem, do mais simples ao mais forte:

1. **admin → Extensões → Desligar.** Essa tela nunca executa código de extensão, então abre mesmo com uma extensão defeituosa ligada no admin. O mesmo vale para as telas de login e de saída.
2. **`config.php`.** Ponha a linha abaixo em `catalog/config.php` e em `admin/config.php`. Desliga todas de uma vez sem tocar no banco; ao tirar a linha, volta o que estava ligado.

   ```php
   define('REAMUR_EXTENSIONS', false);
   ```

3. Apagar ou renomear a pasta da extensão no servidor.

O que o CMS contém sozinho: exceção e erro do PHP dentro de um ouvinte, `extension.json` inválido, versão incompatível, pasta ausente, classe ou método que falta, erro de sintaxe no `extension.php`, arquivo que não começa por `<?php` (por exemplo, gravado com BOM pelo editor). Em todos esses casos sai uma linha em `system/storage/logs/error.log` e a página abre.

O que o CMS **não** contém: erro fatal que o PHP não deixa capturar (classe declarada duas vezes, falta de memória, tempo de execução esgotado) e código que encerra a requisição (`exit`). Nesses casos a página quebra até a extensão ser desligada por um dos três caminhos acima.

Uma extensão com defeito escreve no log a cada página aberta. Desligue-a em vez de deixá-la falhando.

## 4. Escrever uma extensão

O nome da pasta é o **código** da extensão: letras minúsculas, dígitos e `_`, de 2 a 32 caracteres, começando por letra (`ga4_tags`, `site_verification`). Depois de cada `_` vem uma letra (`ga4_tags` vale, `ga_4tags` não), e dois trechos seguidos de uma letra só não valem (`a_b_c`). A tela de extensões avisa quando o nome da pasta não serve.

`extension.json` mínimo:

```json
{
  "schema": 1,
  "code": "minha_extensao",
  "name": "Minha extensão",
  "version": "1.0.0",
  "events": [
    { "trigger": "page/output", "handler": "onPageOutput" }
  ]
}
```

`extension.php`:

```php
<?php declare(strict_types=1);
namespace Reamur\Extension\MinhaExtensao;

use Reamur\System\Library\Extension\PageOutput;

final class Extension extends \Reamur\System\Library\Extension\Extension {
    public function onPageOutput(PageOutput $page): void {
        $page->meta('exemplo', 'valor');
    }
}
```

Regras da classe:

- namespace `Reamur\Extension\<Código em StudlyCase>` (`minha_extensao` → `MinhaExtensao`), classe `Extension`, estendendo `\Reamur\System\Library\Extension\Extension`;
- o construtor é fechado: a extensão só executa código quando um evento que ela declarou acontece;
- cada `handler` do `extension.json` é um método público, não estático, com nome `on` + letra maiúscula;
- o arquivo começa por `<?php`, sem BOM e sem nada antes; senão a extensão não é carregada;
- nada de `echo` ou `print`, dentro ou fora dos métodos: o CMS descarta o texto e avisa no log. O que vai para a página passa pelo `PageOutput` ([§7](#7-saída-na-página-pública)).

O que a classe-base oferece:

| Método | Para quê |
| --- | --- |
| `$this->setting('chave')` | Valor de um campo de configuração (texto, ou `true`/`false` para caixa de marcar). Segredo chega aberto |
| `$this->secretLocked('chave')` | O segredo está gravado, mas a chave do `config.php` não o abre; `setting()` devolve `''` |
| `$this->app()` | `'catalog'` (site público) ou `'admin'` |
| `$this->log('texto')` | Linha no log do sistema, com o código da extensão |
| `$this->registry` | Os serviços do CMS (`db`, `config`, `request`, `session`...) |

Outras classes da extensão podem ficar em arquivos próprios na mesma pasta; o autoload segue o padrão do CMS (`Reamur\Extension\MinhaExtensao\Servico\Envio` → `minha_extensao/servico/envio.php`).

## 5. `extension.json`, campo a campo

A leitura é estrita: **chave desconhecida, em qualquer nível, invalida o arquivo**. Tamanho máximo: 64 KB.

| Chave | Obrigatória | Conteúdo |
| --- | --- | --- |
| `schema` | sim | `1` |
| `code` | sim | Igual ao nome da pasta |
| `name` | sim | Texto de até 80 caracteres, ou objeto por idioma: `{"en-us": "...", "pt-br": "...", "es-eu": "..."}` |
| `version` | sim | `X.Y.Z` (com sufixo opcional, `1.2.0-beta`) |
| `description` | não | Texto ou objeto por idioma, até 400 caracteres |
| `author` | não | Texto de até 80 caracteres |
| `link` | não | Endereço `https://` de até 200 caracteres |
| `requires` | não | `{"reamur": ">=1.0.0", "php": ">=8.2"}`. Operadores `>=`, `<=`, `>`, `<`, `=`; vários separados por espaço (`">=1.0.0 <2.0.0"`). Sem operador vale `>=` |
| `apps` | não | Onde roda: `["catalog"]` (padrão), `["admin"]` ou os dois |
| `settings` | não | Campos de configuração ([§9](#9-configurações-e-segredos)). Até 40 |
| `events` | não | Lista de `{"trigger", "handler", "priority"}` ([§6](#6-eventos)). Até 40 |
| `csp` | não | Endereços de fora, por tipo de recurso ([§8](#8-endereços-de-fora)) |

Texto por idioma: o CMS usa o idioma do admin, depois `en-us`, depois o primeiro que houver.

## 6. Eventos

Um evento é declarado no `extension.json` e recebido por um método:

```json
{ "trigger": "order_add", "handler": "onOrderAdd", "priority": 10 }
```

`priority` vai de -1000 a 1000; o padrão é 0. Número menor roda antes. No empate, vale a ordem de registro.

Eventos desta etapa:

| Evento | Onde acontece | Argumentos do método |
| --- | --- | --- |
| `page/output` | Site público, uma vez por página, ao montar o cabeçalho | `PageOutput $page` |
| `order_add` | Site público, quando um pedido de orçamento é gravado | `array $dados, string $evento` |
| `order_history_edit` | Site público (retorno de pagamento) e admin (mudança de situação) | `array $dados, string $evento` |
| `order_edit` | Admin, ao editar um pedido | `array $dados, string $evento` |

Os três eventos de pedido trazem os mesmos dados que o CMS envia aos webhooks, e chegam depois deles.

Pontos de atenção:

- **O evento só chega se a extensão roda na aplicação em que ele acontece.** Para receber `order_edit`, declare `"apps": ["catalog", "admin"]`.
- A extensão **observa**. Ela recebe cópia dos dados; alterar o array não muda o que o CMS grava ou envia.
- No admin, extensão só roda para usuário logado, e nunca nas telas de login, saída e extensões.
- Exceção no método vai para o log (`extension <código>: error in <método> (<evento>): ...`) e os demais ouvintes rodam.

## 7. Saída na página pública

A extensão não escreve HTML. No evento `page/output` ela pede itens ao `PageOutput`, e o CMS escreve as tags, com escape e com o nonce da política de conteúdo:

```php
public function onPageOutput(PageOutput $page): void {
    $id = (string)$this->setting('id');

    if ($id === '') {
        return;
    }

    $page->meta('exemplo-verificacao', $id);
    $page->scriptSrc('https://cdn.exemplo.com/lib.js', ['async' => true]);
    $page->script('window.exemploId = ' . PageOutput::js($id) . ';');
}
```

| Método | Sai como | Regras |
| --- | --- | --- |
| `meta($name, $content)` | `<meta name="..." content="...">` no `<head>` | Conteúdo de 1 a 1024 bytes, sem quebra de linha. Conteúdo vazio é recusado: confira antes de pedir |
| `property($property, $content)` | `<meta property="..." content="...">` | Idem. Não existe `http-equiv` |
| `scriptSrc($url, $opções)` | `<script src="...">` | Endereço `https://` coberto por uma origem `script-src` do **próprio** manifesto. Opções: `position`, `async`, `defer`, `id`, `integrity`, `crossorigin`, `data` |
| `script($código, $posição)` | `<script nonce="...">código</script>` | Até 20000 bytes; não pode conter `</script` nem `<!--` |
| `PageOutput::js($valor)` | Literal JavaScript seguro | **Todo valor de configuração ou do visitante entra no script por aqui** |
| `$page->route()`, `$page->language()` | Rota e idioma da página | Ex.: `common/home`, `pt-br` |

Posições: `PageOutput::HEAD` (padrão, antes de `</head>`) e `PageOutput::BODY_START` (logo depois de `<body>`). Limite de 60 itens por página, somando todas as extensões.

Cada método devolve `true` se o item foi aceito. Pedido recusado gera uma linha no log (`extension output refused, <código>: <motivo>`) e não aparece na página.

As prévias do admin (página e blog) não mostram saída de extensão.

## 8. Endereços de fora

A política de conteúdo do site (ADR 0093) só deixa o navegador carregar script, imagem, fonte etc. do próprio site. Se a extensão precisa de outro endereço, declara:

```json
"csp": {
  "script-src":  ["https://www.googletagmanager.com"],
  "connect-src": ["https://*.google-analytics.com"],
  "img-src":     ["https://www.google-analytics.com"]
}
```

- Tipos aceitos: `script-src`, `style-src`, `img-src`, `font-src`, `connect-src`, `media-src`, `frame-src`, `form-action`.
- Só endereço `https://` de um site (`https://site.com`, `https://*.site.com`, com porta e caminho opcionais). Palavras como `'unsafe-inline'` e `'unsafe-eval'`, `*`, `https:`, `data:` e `http://` invalidam o arquivo. Até 20 por tipo.
- Valem **só enquanto a extensão está ligada** e **só no site público**. A política do admin nunca recebe endereço de extensão.
- A tela de extensões mostra a lista antes de alguém ligar.
- Script embutido continua exigindo o nonce, que só o `PageOutput` escreve. Não há como liberar script embutido livre nem `eval`.

## 9. Configurações e segredos

Cada item de `settings` vira um campo do formulário **Configurar**:

```json
"settings": [
  { "key": "id", "type": "text", "label": {"en-us": "Measurement ID", "pt-br": "ID de medição"},
    "required": true, "max": 20, "pattern": "G-[A-Z0-9]{4,12}", "placeholder": "G-XXXXXXX",
    "help": "Só o ID, sem a tag." },
  { "key": "modo", "type": "select", "label": "Modo", "default": "basico",
    "options": [ {"value": "basico", "label": "Básico"}, {"value": "completo", "label": "Completo"} ] },
  { "key": "depurar", "type": "checkbox", "label": "Depurar", "default": false },
  { "key": "token", "type": "secret", "label": "Token da API" }
]
```

| Chave do campo | Conteúdo |
| --- | --- |
| `key` | Minúsculas, dígitos e `_`, até 40 caracteres, sem `__` |
| `type` | `text`, `textarea`, `checkbox`, `select` ou `secret` |
| `label` | Texto ou objeto por idioma (obrigatório) |
| `help` | Texto de ajuda sob o campo, opcional (texto ou objeto por idioma) |
| `placeholder` | Exemplo dentro do campo vazio, opcional; só `text` e `textarea` |
| `required` | `true` impede ligar a extensão enquanto o campo estiver vazio. Não vale para `checkbox` |
| `max` | Tamanho máximo (padrão 255; `textarea` 2000; teto 4000) |
| `pattern` | Expressão regular que o valor inteiro precisa atender (`text`, `textarea`, `secret`). Sem `~` |
| `default` | Valor inicial. Segredo não tem |
| `options` | Só para `select`: lista de `{"value", "label"}` |

O valor é conferido ao salvar **e de novo a cada uso**: se o que está no banco não passa no manifesto atual (foi alterado direto no banco, ou o manifesto mudou), a extensão recebe o valor padrão. Mesmo assim, trate o valor como dado: em script, sempre por `PageOutput::js()`.

**Segredo (`"type": "secret"`):**

- é gravado cifrado, com a chave `REAMUR_SECRET_KEY` do `config.php` (ADR 0094), preso à extensão e ao campo;
- nunca volta para a tela: o formulário mostra só "valor gravado". Campo vazio mantém o atual; a caixa "apagar" remove;
- sem chave no `config.php`, fica legível no banco e a tela avisa. *Configurações → Proteção dos segredos → Proteger segredos* cifra depois, e a troca de chave regrava também os segredos de extensão;
- se a chave do `config.php` não abre o valor, a extensão recebe `''` e `secretLocked()` devolve `true`.

Estado gravado, para referência: tabela `rms_setting`, `code = 'extension'`, chaves `<código>.enabled`, `<código>.config` (JSON) e `<código>.secret.<campo>`.

## 10. O que esta etapa não oferece

- Tela própria da extensão no admin (só o formulário gerado), rota pública própria, e arquivos da pasta servidos ao navegador (CSS, JS, imagem).
- Tabelas próprias criadas pelo CMS, rotina de instalação ou de atualização da extensão.
- Envio de extensão por ZIP pelo admin e loja de extensões.
- Aviso de cookies e consentimento (etapa EX-02) e contrato de meio de pagamento (etapa EX-03).
- Isolamento do PHP da extensão: veja [§1](#1-o-que-é-uma-extensão).
