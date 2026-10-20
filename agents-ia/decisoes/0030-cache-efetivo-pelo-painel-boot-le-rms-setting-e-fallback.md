# ADR 0030 — Cache efetivo pelo painel: boot lê rms_setting, fallback File e guardas

- **Status:** Aceita — 2026-10-20 (F4 Tarefa 06)
- **Contexto:** A F4-01 (ADR 0024) entregou a seção **Cache** de
  `setting/setting` — `cache_engine` e `cache_expire` persistem em
  `rms_setting` e já eram injetados no Config pela pré-ação
  `startup/setting`. Porém a própria ADR 0024 registrou a **renúncia**:
  *"framework monta o cache antes das pré-ações"* — passo 8.1 do boot vs
  pré-ações no passo 15 de `system/framework.php`. Consequência real: os
  valores gravados pelo painel **jamais afetavam o driver montado** (a
  injeção chegava tarde demais) e a ajuda da tela mentia ("aplicado na
  próxima requisição"). Trocar engine/TTL no admin era operação inerte.
- **Leitura real (antes de escrever — prova do estado do HEAD `693a26f`):**
  - `system/framework.php` linhas 102–109: cache montado de
    `$config->get('cache_engine')` (só arquivo) com `throw` em classe
    ausente; pré-ações confirmadas no passo 15, DEPOIS de Session/Template.
  - Ovo-e-galinha confirmado: `admin/model/setting/setting.php::getSettings()`
    usa `$this->cache->get/set` para cachear o próprio mapa de settings —
    a startup/setting não pode rodar antes do cache existir.
  - Form F4-01 oferece `apc` no select, mas a única classe existente é
    `Cache\APCU` (arquivo `apcu.php`); `Cache\Memcached::__construct()`
    lança exceção quando `CACHE_HOSTNAME/PORT/PREFIX` não estão definidas
    (não estão, em nenhum config); apenas `Cache\File` implementa
    `clear()` entre os 5 adapters — `setting/setting.clearCache` chamava
    `$this->cache->clear()` às cegas.
  - `admin/catal​og startup/setting` injetam `cache_engine/expire` no
    Config (hoje inócuo para a montagem; mantidos — display e defaults).
- **Decisões:**

  1. **O boot lê `rms_setting` DIRETAMENTE antes de montar o cache**
     (guardado por `try/catch` + `registry->has('db')` + `defined('DB_PREFIX')`).
     Reordenar pré-ações para antes da montagem foi **recusado**: a
     startup/setting precisa do cache pronto (cachea settings) e Session/
     Template dependem do Registry maduro — mover o 8.1 para depois do 15
     quebraria mais invariantes do que resolve. A consulta do boot repete
     a semântica da startup (valor não-vazio vence o `default.php`), é
     `SELECT` de 2 chaves com o driver já montado no passo 8 e custo
     desprezível (1 query/request, sem join). `install` não monta DB
     (`db_autostart` off) → bloco pulado: wizard inalterado.

  2. **Escolha ruim NUNCA derruba a aplicação — fallback `File` + log.**
     Antes desta ADR, um `cache_engine` inválido **no arquivo** lançava
     exceção (500 global). Com o painel podendo escolher engines, o throw
     viraria auto-sequestro: salvar `memcached` num servidor sem a extensão
     brickaria até o admin que corrige. O boot passa por um **verificador
     de usabilidade** (alias do form → classe real → pré-requisito):
     `file` sempre; `apc`→`APCU`+`ext-apcu`; `apcu` idem; `mem`→`ext-memcache`;
     `memcached`→`ext-memcached`+constantes `CACHE_*`; `redis`→`ext-redis`.
     Indisponível, classe ausente ou construtor falhando →
     `new Cache\File($cacheExpire)` + `$log->write()` (degradação silenciosa
     — mesma renúncia das ADRs 0023/0029). A mudança do comportamento de
     throw (config-arquivo) para fallback fica **registrada aqui**: rotas
     de bateria não cobriam o throw e a direção é sempre "mais resiliente".

  3. **`clearCache` com guarda de contrato.** Apenas `Cache\File` tem
     `clear()`; com outra engine ativa a limpeza total viraria fatal.
     `setting/setting.clearCache` verifica `method_exists` e, sem suporte,
     responde flash informativo (`text_cache_clear_unsupported`) em vez de
     500. **Renúncia registrada:** dotar Mem/Redis/APCU/Memcached de
     `clear()` (flush) fica para tarefa própria de hardening dos adapters
     — os arquivos residuais do `File` ao trocar de engine são considerados
     inertes (o driver ativo não os lê).

  4. **TTL exibido = configurado; engine exibida = CLASSE REAL montada.**
     O indicador do dashboard compõe
     `Cache: {classe-do-adapter} · TTL {cache_expire}s` e acrescenta aviso
     de fallback quando configurada ≠ ativa (normalizando o alias
     `apc`≡`apcu`). Lê a verdade operacional via `get_class($this->cache)`,
     não a intenção gravada — diagnóstico honesto para o mantenedor.

  5. **Dedupe residual do repair (3ª ocorrência).** O refator manual
     `61057c1`+`3cfe860`+restauro F4-repair deixou o bloco F4-03 duplicado
     também na ALLOWLIST de `admin/model/setting/setting.php` (os dois
     startups foram corrigidos na F4-05/ADR 0029 §6). Removido aqui —
     inócuo em runtime, comportamento inalterado.

- **Alternativas consideradas:**
  1. **Reordenar o boot** (cache após pré-ações) — recusado, ver §1;
  2. **Serviço-cache lazy/proxy** que se remonta no primeiro uso pós-injeção
     — recusado: complexidade nova no núcleo para um problema de 1 query;
     mudaria o contrato de quando o cache existe (Session monta no passo 9);
  3. **Validar engine no SAVE** (bloquear escolha sem extensão) — parcial:
     o servidor do admin pode não ser o da produção; o safe path é o
     fallback observável (log + indicador), não uma validação que mente.
     Fica como possível melhoria futura, não como regra agora;
  4. **Limpar storage/cache ao trocar de engine** — recusado como efeito
     colateral escondido de um save; arquivos ficam inertes (§3).

- **Limitações conhecidas (declaradas):**
  - `getSettings()` chama `cache->set(KEY, $map, 3600)` com 3º argumento,
    que `File::set()` (2 parâmetros) ignora — o TTL efetivo do blob de
    settings no driver File é o TTL montado no boot (desejado e assertado
    na bateria). Adapters com assinatura `(key, value, expire)` honram o
    3º arg por envio. Nenhum código alterado aqui — apenas documentado.
  - `cache_expire` lido no boot não tem clamp 60..2.592.000 (a tela valida;
    SQL direto não) — aceito: quem escreve direto na tabela assume o risco.

- **DoD verificado:** bateria `e2e_f406.sh` com 10 checks: wizard + grants,
  login, indicador no dashboard, save TTL 1800 com round-trip no banco,
  **prova discriminatória** (o arquivo `cache.setting.config` de warm nasce
  com `expire` ≈ agora+1800 — pré-fix seria sempre 3600), `clearCache`
  limpa o artefato (File), engine `redis` sem extensão → **todo o site
  continua 200** + linha de fallback no `error.log`, dashboard sinaliza
  "fallback ativo", retorno a `file` recompeõe o indicador, i18n ×3 do
  indicador, muralha anônima íntegra.
- **Registro de suposições (anti-alucinação):** sandbox e XAMPP-alvo sem
  `ext-redis/apcu/memcached` hoje — por isso a bateria prova o FALLBACK
  (não um redis vivo); em servidor com as extensões + constantes
  `CACHE_*` definidas, o mesmo caminho monta o driver real sem mudança
  de código.
