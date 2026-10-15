# 03 — Protocolo Anti-Alucinação

> Regra zero do esquadrão: **é melhor dizer "não sei, preciso verificar" do que entregar algo plausível e errado.** Este protocolo é executado em TODA tarefa, sem exceção.

---

## 1. Hierarquia da verdade

Quando houver dúvida, consulte nesta ordem — o primeiro que responder vence:

1. **O próprio arquivo no repositório** (ler o código real);
2. `agents-ia/regras/00-contexto-global.md` (fatos verificados);
3. `docs/` (Arquitetura-MVCL, Estrutura-de-Pastas, guias);
4. Convenções desta pasta de regras;
5. **O humano** — pergunta direta, listando as opções que você considerou.

> Conhecimento "de memória" sobre OpenCart, Laravel, Symfony ou WordPress **não é fonte**. O ReamurCMS é *inspirado* no OpenCart, não *é* o OpenCart: constantes, nomes e fluxos podem divergir (ex.: `DIR_REAMUR`, `RMSSESSION`).

## 2. Comportamentos obrigatórios

| # | Regra |
| --- | --- |
| 1 | **Ler antes de escrever.** Nunca edite/estenda um arquivo sem ter lido seu conteúdo atual na tarefa em curso |
| 2 | **Verificar existência.** Antes de criar classe/método/rota/pasta, buscar se já existe algo equivalente |
| 3 | **Citar caminhos reais.** Toda referência a código sai com caminho completo (ex.: `dev/system/engine/loader.php`) |
| 4 | **Chamar só o que existe.** Antes de usar `$this->algo->metodo()`, confirmar o método no arquivo do serviço |
| 5 | **Declarar incerteza.** Se não verificou, escrever explicitamente: *"Não verifiquei X; assumi Y — deseja confirmar?"* |
| 6 | **Escopo cirúrgico.** Entregar exatamente o pedido; melhorias adjacentes viram *sugestão* separada |
| 7 | **Sem auto-completar o sistema.** Se falta uma peça (ex.: método no Loader), propor a criação como tarefa — não fingir que existe |

## 3. Alertas vermelhos (stop & verify)

Interromper a execução e verificar **antes** de continuar quando sentir que está:

- 🚩 Inventando o nome de um método da engine/paginação/template ("deve se chamar `getLinks()`...");
- 🚩 Assumindo chaves de config que não constam em `system/config/default.php`;
- 🚩 Criando pasta/rota nova "porque no OpenCart é assim";
- 🚩 Gerando SQL para tabela cujo schema você nunca leu;
- 🚩 Estendendo classe cujo construtor você não conferiu;
- 🚩 "Completando" a requisição do humano com funcionalidades não pedidas.

## 4. Protocolo de execução por tarefa

1. **ENTENDER:** reescrever a tarefa em 1–3 linhas; listar dúvidas antes de codar;
2. **MAPEAR:** listar os arquivos reais envolvidos (usando `docs/Estrutura-de-Pastas.md` + leitura dos arquivos);
3. **PLANEJAR:** emitir o cabeçalho padrão com plano numerado; se impacto > 1 arquivo ou toca `system/`, **aguardar confirmação**;
4. **IMPLEMENTAR:** seguir regras 01 e 02; comentários em pt-BR só onde agregam;
5. **VALIDAR:** checklist do agente + `php -l` + releitura do diff ("este código usa apenas o que existe?");
6. **REPORTAR:** o que mudou, por quê, como testar, e **estado das incertezas** (nenhuma pendente ou listadas).

## 5. Como declarar incerteza (modelo)

```
⚠️ PENDÊNCIA DE VERIFICAÇÃO
Item: assinatura do método Loader::modelWebhook()
Estado: usado em system/webhook/controller/sale/order.php, não confirmado em system/engine/loader.php
Ação sugerida: confirmar implementação antes do merge / criar tarefa para o agente-system
```

## 6. Checklist obrigatório pré-entrega (copiar e marcar)

- [ ] Li todos os arquivos que modifiquei **antes** de modificá-los
- [ ] Cada classe/método/função que chamo existe em arquivo que li ou criei nesta tarefa
- [ ] Cada pasta/rota/arquivo novo segue o mapa de Estrutura-de-Pastas (ou foi aprovado)
- [ ] Nenhum texto de interface fora da camada Language
- [ ] Nenhum SQL fora de Model
- [ ] `php -l` passou em todos os arquivos PHP tocados
- [ ] Diff contém somente o escopo da tarefa
- [ ] Incertezas (se houver) declaradas no modelo do §5
- [ ] Docs impactados atualizados
- [ ] Mensagem de commit no padrão (regra 02 §8)

---

<p align="center">Verificar é mais rápido que corrigir. Alucinar é proibido.</p>
