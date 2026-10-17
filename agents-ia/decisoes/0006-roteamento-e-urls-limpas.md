# ADR 0006 — Roteamento e URLs limpas (Fase 2, Tarefa F2-5)

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-10-17 |
| **Proponente** | humano (David L. Almeida) |
| **Implementação** | agente-devops (F2-5) |

## Contexto

Até a F2-4, as três apps declaravam `action_router = ''` e só respondiam
`index.php?route=grupo/modulo`. O Contrato-Bootstrap deixou `action_router` no
mapa, mas o framework nem sabia executá-lo — e as pré-ações liam a rota só do
parâmetro (`request->get['route']`), o que quebraria a muralha na primeira URL
limpa.

## Decisão

1. **Ordem de resolução (framework, passo 14)** — a **primeira** fonte vence:
   1. `?route=` explícito (contrato legado **intocável**: deep links antigas,
      formulários e harnesses continuam funcionando);
   2. `action_router` da app — `startup/router` devolve a rota interna a partir
      do `REQUEST_URI` limpo;
   3. `action_default` da app.
2. **`_route` no config** — a rota resolvida fica registrada ANTES das
   pré-ações; pre-actions e controllers leem `config->_route` (fonte única),
   com fallback para `?route=` (CLI/cron);
3. **Mapeamento do router** — caminho limpo = rota direta
   (`/common/dashboard`, `/error/not_found`); a existência do controller é
   julgada pelo dispatcher (404 honesto); aliases curtos são constantes
   explícitas por app (admin: `/login`, `/logout`; catalog: nenhum além da
   rota padrão). Sanitização idêntica à da `Action` (alfabeto de rotas,
   sem traversal);
4. **Saída limpa** — `Url::addRewrite` (gancho que já existia) é registrado
   pelo próprio router: links internos saem como `grupo/modulo?x=y`
   (aliases substituem pelo caminho curto). Nenhum template muda;
5. **Install fora** — o wizard mantém `action_router = ''` (vida curta e
   controlada; a rota limpa não agrega confiança ali);
6. **Futuro (Fase 3+)** — a tabela `rms_seo_url` (seed) assume slugs
   dinâmicos de produtos/blog; o router ganha um segundo passo de lookup
   registrado por módulo, sem contrato novo.

## Consequências

- ✅ Muralha de login e ACL da F2-2 passam a valer em ambas as formas de URL
  (prova em E2E: `/login` sem `?route=` abre o formulário; `/` segue 302→login);
- ✅ Mudança cirúrgica no coração: o framework só **reordena** a resolução
  (pré-ações depois da rota) — nenhum contrato de app muda;
- ⚠️ `framework.php` entra no portão PHPStan (paths engine): o novo trecho é
  nível 2 limpo, sem entrada de baseline;
- ⚠️ `.htaccess`/`nginx` do deploy precisam mandar tudo que não é arquivo para
  `index.php` da app correspondente (front controller único — Contrato §2);
  documentado aqui para o passo de publicação (davidalmeida.xyz).

## Alternativas consideradas

1. **Router dentro das pré-ações** — rejeitada: misturaria decisão de rota
   com efeitos colaterais de boot (sessão/ACL) e esconderia a ordem real;
2. **Tabela `seo_url` já agora** — rejeitada: sem entidades com slug (catálogo
   vazio), prematuro; o lookup entra junto da primeira entidade publicável.
