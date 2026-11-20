# 0127 — O escopo da checagem de caminhos é derivado das pastas, não enumerado

| | |
| --- | --- |
| **Status** | ✅ aceita |
| **Data** | 2026-11-20 |
| **Frente** | KZ-64 · documentação e guardas |
| **Decide** | quais documentos entram na checagem de "caminho citado que não existe" — e por que a lista deixa de existir |
| **Depende de** | ADR 0126 (o verificador) |
| **Não decide** | conteúdo dos documentos (nenhum guarda decide isso), integração do congelamento no CI (ver §7) |

## 1. O que a ADR 0126 entregou, e o que ela deixou de fora

A ADR 0126 fechou um buraco real: indexou as 125 ADRs e criou um verificador para caminho citado que não existe. O verificador, porém, só olhava uma **lista escrita à mão** de documentos operacionais — 27 arquivos em 314 `.md` versionados.

Duas semanas de vida útil foram suficientes para a lista ficar incompleta. Nenhum dos ausentes estava numa pasta exótica:

| Ausente | Por que é grave |
| --- | --- |
| `docs/PROJECT-HANDOFF.md` | é o **briefing da próxima sessão de trabalho** — a instrução cujo erro custa um dia inteiro, e era justamente ela que o guarda não abria |
| `docs/temas/guias/**` (20 guias) | instrução de operação de tema; o primeiro verificado achou um arquivo de adapter que não existe |
| `docs/CHANGELOG.md` | é onde o defeito veio a ser descrito — o registro do bug citava o bug |
| `agents-ia/regras/00-contexto-global.md` | regra lida por todo agente antes de qualquer tarefa |

E, na primeira execução com o escopo correto, o resultado foi **7 caminhos inexistentes** onde a ADR 0126 registrava **0**. O número anterior não estava errado por má execução: estava errado porque o universo era uma escolha.

## 2. A decisão

**A classificação é derivada da localização do arquivo.** O script `tools/verify-docs-index.js` responde uma pergunta só — *este `.md` é registro ou instrução?* — e as duas linguagens do repositório passam a usar a mesma resposta:

| Classe | Onde | Tratamento |
| --- | --- | --- |
| **histórico** | `agents-ia/decisoes/**`, `docs/historico/**`, `docs/fases/**` e os relatórios datados da raiz (`INDICE-*`, `RELATORIO-*`, `DIAGNOSTICO-*`, `Pauta-*`) | caminho inexistente **não é erro**: ali está o registro do que foi removido |
| **operacional** | todo o resto — raiz, `docs/` exceto as três pastas, `docs/**`, `agents-ia/**`, `tools/**` | caminho inexistente é **erro** (com `--strict`, como o CI roda) |

Consequências que importam:

1. **Documento novo entra sozinho.** Não há lista para atualizar, nem nasce a pergunta "entrou no guarda?".
2. **O guarda e o teste unitário não podem discordar.** O `DocsIndexTest` consome o mesmo JSON; os dois lados derivam da mesma tabela. Enquanto era PHP com lista de um lado e PHP com lista do outro, o descompasso era invisível — cada um aprovava a si mesmo.
3. **A proporção passou a ser visível:** 87 operacionais verificados, 229 históricos ignorados, 314 no total. Antes o relatório dizia "documentos verificados: 27" sem denominador, o que parece completo e não é.

## 3. Linha de citação não é instrução

`>` no começo da linha passa a ser ignorado pela checagem de caminhos. Motivo concreto: a retificação desta própria frente precisa **reproduzir** a frase errada dentro do `CHANGELOG` para explicar o que faltou. Cobrar que uma citação aponte para arquivo existente obrigaria a falsificar o original — o oposto do que um registro deve fazer.

Não é licença para esconder instrução: citação é transcrição de terceiro ou do passado, sinalizada como tal. Instrução é texto corrido, e continua sob a checagem.

## 4. `docs/README.md` e a documentação que envelhece onde ninguém lê

O índice de `docs/` listava 3 dos 11 documentos de integração. Os **nove documentos de pagamento** (núcleo, adapters, Mercado Pago, reconciliação ×2, testes, KZ-64 ×2) existiam, estavam corretos e **não eram alcançáveis a partir do índice** — e é exatamente aí que nasceu o `Webhooks.md` que ensinava arquitetura removida: documento que ninguém acha é documento que ninguém corrige.

Decisão: **toda página em `docs/` tem de ser alcançável a partir de `docs/README.md`** (um salto, ou dois quando passa por um `README.md` de pasta). Hoje: **102/102**, e é teste, não recomendação.

## 5. O que mudou no repositório

| Arquivo | Mudança |
| --- | --- |
| `tools/verify-docs-index.js` | **novo** — a tabela: `operacionais`, `historicos`, `caminhos_gerados`, derivada de `git ls-files '*.md'` |
| `tools/verify-docs-index.php` | consome a tabela; `DOCS_OPERACIONAIS`/`ehOperacional()`/`documentosOperacionais()` removidos; retorna **2** (não 0) se não conseguir classificar — fail-closed; `--json` ganha `documentos_historicos` e `caminhos_gerados`; linhas de citação ignoradas |
| `tests/Unit/Docs/DocsIndexTest.php` | **8 casos** (eram 5): escopo derivado (falha se voltar a ser lista), handoff e guia de tema no escopo, ADR e fase como histórico, e toda página de `docs/` alcançável do índice |
| `docs/PROJECT-HANDOFF.md` | o caminho de fechamento do BDREditor estava errado (arquivo vive em `docs/fases/f9/`) e mandava rodar `tools/f9-57-static-battery.py` e `tools/*smoke.js`, que **nunca** existiram no repositório |
| `docs/CHANGELOG.md` | **retificação** da entrada da 22ª parte: eram 27 documentos, não "os documentos"; e o total de caminhos quebrados era 7, não 9; mais duas frases que só passavam porque o documento emitter não era verificado |
| `agents-ia/regras/00-contexto-global.md` | a linha da ADR 0004 descrevia `dev/tests/` no presente; agora diz que a suíte vive em `tests/`, na raiz |
| `docs/temas/guias/GUIA-TEMAS-CONDICOES-THREEJS.md` | citava `dev/system/library/template.php`, que não existe: o carregamento é `framework.php` + `library/template/<engine>.php` |
| `docs/README.md` | 9 documentos de pagamento + 1 relatório histórico no índice; 102/102 alcançáveis |

## 6. Limites desta decisão (declarados, não escondidos)

1. **A regra de classe é uma convenção, não uma garantia.** Um documento operacional colocado em `docs/fases/` deixa de ser verificado. A convenção é a mesma que o repositório já usava para separar registro de instrução; a diferença é que agora ela está escrita e é a única fonte.
2. **Guarda continua não vendo conteúdo.** A ADR 0126 já declarou: caminho certo com conteúdo errado passa. O `PROJECT-HANDOFF.md` é a prova de novo — o caminho de fechamento estava errado *e* o passo de verificação mandava rodar ferramenta inexistente; o guarda pegou os dois, mas o comando de entrega do mesmo parágrafo (ZIP em `entregas/`) continua sendo instrução que ninguém verifica.
3. **Node passa a ser exigido** por um guarda do CI. O job `repo-guards` não instala dependências; `node` está no runner do GitHub Actions e no ambiente de desenvolvimento (F11-01/ADR 0081 já usa Node para `staging`). Sem Node o verificador **reprova** (código 2) em vez de passar em silêncio.

## 7. Achado que esta ADR NÃO resolve — e por que

`tools/verify-contract-freeze.py` (F11-11) **reprova hoje** no `main`: 25 de 30 artefatos congelados conferem; 5 divergem (`dev/admin/controller/common/login.php`, `dev/admin/controller/startup/dracaunos.php`, `dev/admin/model/tool/internal-tools.json`, `dev/install/sql/schema.sql`, `docs/F9-14-Contrato-Editor-MVP.json`). Nenhum dos cinco tem decisão em `PENDING_DECISION` que o justifique — as 34 decisões estão `DECIDED`, então o guarda imprime `DIVERGENTE` sem dono.

Consequência prática: `bash tools/repo-health.sh` termina **REPROVADO**, e **o `repo-guards` do CI não roda esse guarda** (não há passo para ele no workflow). O guarda existe, funciona e está fora do caminho.

Não consertei atualizando os hashes, por dois motivos: (a) atualizar hash é decidir que a mudança de contrato está aceita, e essa decisão é do responsável, não de quem passa por aqui; (b) `retified_in` aponta para **F11-14**, que não existe no repositório (`docs/fases/f11/` vai até F11-10) — a retificação foi prometida e nunca registrada. Fica registrado como pendência com o número, que é o que faltava para alguém decidir.

## 8. Verificação

- `php tools/verify-docs-index.php --strict` → **126 ADRs, 0 fora do índice, 0 entrada quebrada, 87 documentos (229 históricos), 0 caminho inexistente**;
- suíte completa verde; `DocsIndexTest` **8 casos**;
- **teste de mutação feito à mão:** reintroduzir `LISTA_A_MAO` com o handoff dentro derruba `testOperationalScopeIsDerivedAndNotAHandWrittenList` com a mensagem certa — o teste existe para pegar exatamente essa regressão;
- `verify-checksums`, `verify-deps-layout`, `verify-ci-workflow` inalterados e verdes; `repo-health` continua **REPROVADO pelo `verify-contract-freeze`**, conforme §7.
