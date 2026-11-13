# Contrato de Bootstrap — Pontos de Entrada ReamurCMS

> **Objetivo:** definir **exatamente** como cada aplicação (`catalog`, `admin`, `install`) inicia — constantes, ordem de carregamento e responsabilidades de cada arquivo. Resolve a lacuna **L1** da [Auditoria de Fundação](Auditoria-Fundacao.md): impedir que cada agente escreva um front controller diferente.
> 🔗 Modelo canônico de arquivo: [`agents-ia/modelos/front-controller-index.php.tpl`](../../agents-ia/modelos/front-controller-index.php.tpl)

| | |
| --- | --- |
| **Status** | ✅ Contrato aceito (ADR 0004) — implementar na Fase 1 |
| **Dono** | `agente-system` (mantém) · todos os agentes (obedecem) |

---

## 1. Constantes do sistema

### 1.1 Definidas pelo **config.php** (gerado pelo instalador, na raiz pública)

| Constante | Exemplo | Descrição |
| --- | --- | --- |
| `HTTP_SERVER` | `https://davidalmeida.xyz/` | URL base da aplicação (com barra final) |
| `HTTPS_SERVER` | `https://davidalmeida.xyz/` | URL base segura |
| `DIR_REAMUR` | `/var/www/site/` | **Raiz de tudo** (pai de `dev/` ou da instalação) |
| `DIR_APPLICATION` | `{DIR_REAMUR}dev/catalog/` | Pasta da aplicação em execução |
| `DIR_SYSTEM` | `{DIR_REAMUR}dev/system/` | Núcleo |
| `DIR_LANGUAGE` | `{DIR_APPLICATION}language/` | Idiomas da aplicação |
| `DIR_TEMPLATE` | `{DIR_APPLICATION}view/template/` | Templates da aplicação |
| `DIR_CONFIG` | `{DIR_SYSTEM}config/` | Arquivos `$_[]` de configuração |
| `DIR_STORAGE` | `{DIR_SYSTEM}storage/` | Dados voláteis (cache, logs, session, vendor) |
| `DIR_EXTENSION` | `{DIR_REAMUR}dev/extension/` | Uma pasta por extensão (`extension.json` + `extension.php`), fechada ao navegador (ADR 0095) |
| `DB_DRIVER` | `mysqli` | Driver ativo (espelha `db_engine`) |
| `DB_HOSTNAME`, `DB_USERNAME`, `DB_PASSWORD`, `DB_DATABASE`, `DB_PORT` | — | Credenciais (nunca no Git) |
| `DB_PREFIX` | `rms_` | Prefixo de tabelas |

**Opcionais** (ausentes = comportamento padrão; mesmos valores em `catalog/config.php` e `admin/config.php`):

| Constante | Exemplo | Descrição |
| --- | --- | --- |
| `REAMUR_ERROR_DISPLAY` | `false` | Mostra o detalhe técnico dos erros na tela. Ausente = `false` (ADR 0037 / 0088) |
| `REAMUR_TRUSTED_PROXIES` | `'203.0.113.0/24, 2001:db8::/32'` | Endereços e faixas dos proxies / CDN cujos cabeçalhos `X-Forwarded-For` e `X-Forwarded-Proto` valem (ADR 0089) |
| `REAMUR_TRUSTED_PROXY_HOPS` | `1` | Quantos proxies ficam na frente do site. Usada só sem a lista; exige servidor inalcançável por fora do proxy (ADR 0089) |
| `REAMUR_CSP_MODE` | `'report'` | Chave de emergência da política de conteúdo. `'report'` = o navegador só relata no console, nada é bloqueado; `'off'` = sem política. Ausente ou qualquer outro valor = política aplicada (ADR 0093) |
| `REAMUR_CSP_SOURCES` | `['script-src' => 'https://cdn.exemplo.com']` | Sites de fora liberados no site público, por tipo de recurso (`script-src`, `style-src`, `img-src`, `font-src`, `connect-src`, `media-src`, `frame-src`, `form-action`). Só endereço `https://` de site; o resto é ignorado. Vale só em `catalog/config.php` (ADR 0093) |
| `REAMUR_HSTS_MAX_AGE` | `15552000` | Prazo, em segundos, do `Strict-Transport-Security`. Ausente = 180 dias; `0` desliga. O cabeçalho só sai quando `HTTP_SERVER` começa com `https://` (ADR 0093) |
| `REAMUR_SECRET_KEY` | linha gerada pelo instalador ou por *Configurações → Proteção dos segredos* | Chave que cifra os segredos gravados no banco (tokens do Mercado Pago, segredos de webhook, senha SMTP). 32 bytes aleatórios em base64, escritos como texto, **iguais nos dois arquivos**. Ausente = segredos gravados legíveis e aviso no painel. Guarde uma cópia com o backup: sem ela o banco restaurado não abre os segredos (ADR 0094) |
| `REAMUR_SECRET_KEY_OLD` | a chave anterior | Só durante uma troca de chave: abre o que a chave anterior cifrou, nunca grava. Sai depois de "Proteger segredos" (ADR 0094) |
| `REAMUR_EXTENSIONS` | `false` | Chave de emergência das extensões. `false` = nenhuma extensão é carregada, no site e no admin, sem alterar o que está gravado como ligado; a tela *Extensões* avisa e continua utilizável. Ausente = extensões ligadas rodam (ADR 0095) |

> **Regra:** `config.php` é gerado pelo wizard (`install/`) e **nunca** versionado (entra no `.gitignore`). O instalador deve validar e recusar escrever credenciais vazias em produção.

### 1.2 Definidas pelo **index.php** (front controller)

| Constante | Valor | Descrição |
| --- | --- | --- |
| `REAMUR_VERSION` | `'1.0.0'` | Versão do núcleo em execução *(constante nova — criar na Fase 1, usar em headers/diagnóstico)* |
| `APPLICATION` | `'Catalog'` · `'Admin'` · `'Install'` | **Única diferença** entre os front controllers das 3 aplicações |
| `APPLICATION_INDEX` | (opcional) caminho relativo | Usado por helpers de URL quando admin e catalog diferem |

### 1.3 Definidas pelo **startup.php**

`startup.php` **não define constantes novas** — valida PHP >= 8.0, garante que `DIR_*` existe e falha cedo com mensagem clara se `DIR_SYSTEM` estiver errado.

---

## 2. Sequência obrigatória de um front controller

Todo `index.php` (catalog, admin, install) segue **esta ordem exata** — sem passos extras, sem passos faltando:

```php
<?php
// 1. Versão do núcleo
define('REAMUR_VERSION', '1.0.0');

// 2. ÚNICA linha específica da aplicação
define('APPLICATION', 'Catalog'); // 'Admin' | 'Install'

// 3. Configuração da instalação (gerada pelo instalador)
//    (em dev, antes do wizard, pode-se incluir config-dist manualmente)
if (is_file('config.php')) {
    require_once('config.php');
}

// 4. Instalação pendente? → redireciona ao wizard (regra catalog/admin)
//    if (!defined('DIR_APPLICATION')) { header('Location: install/index.php'); exit; }

// 5. Bootstrap do núcleo — SEMPRE nesta ordem, nada entre eles
require_once(DIR_SYSTEM . 'startup.php');   // valida ambiente
require_once(DIR_SYSTEM . 'framework.php'); // monta o Registry inteiro

// 6. Hand-off para o roteador (a partir daqui o fluxo é o do MVCL)
```

Depois do passo 5, o fluxo é o documentado em [Arquitetura-MVCL §4](Arquitetura-MVCL.md#4-ciclo-de-vida-da-requisição): pré-ações (`startup/*`) → router → controller → view → response.

**O step 4 difere por aplicação:**

| Aplicação | Regra do fallback de instalação |
| --- | --- |
| `catalog`, `admin` | Sem `config.php` → redirecionar para `install/index.php` |
| `install` | Nunca redireciona; **autodefine** `DIR_*` + `HTTP_*` (somente as já previstas no §1.1) quando `config.php` ausente — Adendo F1-4 |

> **Adendo F1-4 (Fase 1 · Tarefa 4 — aceito pelo dono):** o passo 4 do `install/index.php` autodefine o ambiente (padrão OpenCart) para o wizard rodar **antes** do `config.php` existir — sem criar nenhuma constante nova (§5.2 preservado). Pendente para a verificação de bootstrap: criar `dev/system/config/install.php` e garantir que o `framework.php` não monte o serviço `db` sem `DB_*` (db-autoload desligado na app install).

---

## 3. Responsabilidades dos arquivos de bootstrap

| Arquivo | Faz | NUNCA faz |
| --- | --- | --- |
| `index.php` | Define 2 constantes, inclui 3 arquivos, chama o bootstrap | Lógica, URL parsing, sessão, output, `new` em classes |
| `config.php` (gerado) | Apenas `define()`/`$_` | Funções, includes, lógica |
| `startup.php` | Valida PHP/constantes; sanitiza ambiente mínimo | Registrar serviços, conectar banco |
| `framework.php` | Monta Registry (autoloader → config → log → handlers → event → loader → request/response → db → cache → session → template → language → url) | Saber de rotas, renderizar view |
| `vendor.php` | `require` do `storage/vendor/autoload.php` | Qualquer outra coisa |

---

## 4. Contrato por aplicação

| | **catalog** | **admin** | **install** |
| --- | --- | --- | --- |
| `APPLICATION` | `'Catalog'` | `'Admin'` | `'Install'` |
| Config carregado depois do `default.php` | `config/catalog.php` | `config/admin.php` | `config/install.php` |
| Sessão | visitante (`RMSSESSION`) | admin (+ token ACL) | efêmera do wizard |
| Pré-ações típicas | `startup/seo_url`, `startup/language`, `startup/session`, … `startup/extension` (a última) | `startup/login`, `startup/permission`, … `startup/extension` (a última; só com usuário logado) | `startup/requirements` |
| Rota padrão | `common/home` | `common/dashboard` (após login) | `install/step_1` |
| Sem config.php | → redireciona p/ install | → redireciona p/ install | prossegue |

A montagem de "config por aplicação" **já existe** no framework: `$config->load(strtolower(APPLICATION))` — este contrato apenas a formaliza.

---

## 5. Regras para os agentes

1. Front controller novo = **copiar `agents-ia/modelos/front-controller-index.php.tpl`** e mudar somente `APPLICATION`;
2. Nenhuma constante nova fora das tabelas do §1 — constante nova exige ADR;
3. `REAMUR_VERSION` é bumpada só em release (orquestrador + tag Git);
4. Placeholders `index.html` das 3 aplicações viram `index.php` neste contrato **na Fase 1**, em uma única tarefa do `agente-system` (commit `feat(system): adicionar front controllers das aplicações`);
5. Arquivo `config-dist.php` de exemplo entra no Git na raiz de cada app (sem credenciais reais);
6. Aplicação webhook: entry point dedicado será formalizado na Fase 5 (ver [Webhooks §9](../integracoes/Webhooks.md#9-roadmap-do-módulo)) — até lá, webhooks são chamados como controllers internos, não por HTTP direto.

---

## Veja também

- [Arquitetura-MVCL.md](Arquitetura-MVCL.md) — o que acontece depois do bootstrap
- [Estrutura-de-Pastas.md](../Estrutura-de-Pastas.md) — onde vive cada arquivo
- [Auditoria-Fundacao.md](Auditoria-Fundacao.md) — lacuna L1 que este contrato fecha
- `agents-ia/regras/01` §4 — regras do núcleo compartilhado
