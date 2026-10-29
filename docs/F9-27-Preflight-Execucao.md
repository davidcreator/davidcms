# F9-27 — Pré-flight para execução autorizada

**Estado atual: BLOQUEADO / NÃO EXECUTADO.** `main` consultada em 2026-10-29 está no commit `3d481ecf65c124c17d61e1c5fa7ec6c031f1027b` (padrões BDRE pessoais, F9-25). A F9-26 de acessibilidade/localização/performance já consta no histórico como concluída (`448dd61f467e8ce2b4a0d5bce481345dace4ca77`). O status permanece `BLOCKED_NOT_RUN` e `f9_12_status=UNKNOWN_NOT_CONFIRMED`. Esta atualização é documental; não muda o gate nem afirma aprovação.

A opção escolhida para este passo é atualizar estaticamente o gate para o HEAD atual e adicionar os cenários dos padrões BDRE, sem executar testes. As restrições continuam valendo: sem autorização explícita não iniciar PHP/SQL/browser/app/E2E runtime. Nenhum ambiente foi descoberto, sondado ou acessado; F9-12 permanece não confirmada.

## Informações que o responsável deve fornecer/confirmar

Preencher fora de qualquer canal de log público; **não incluir credenciais, tokens, dump ou dados pessoais**.

- [ ] Autorização explícita e delimitada: quais famílias de teste estão autorizadas, janela, responsável de acompanhamento e condições de parada.
- [ ] Identificador/hostname do staging descartável e confirmação do dono de que não há rota, replicação, segredo ou banco compartilhado com produção.
- [ ] Commit exato a testar (`3d481ecf65c124c17d61e1c5fa7ec6c031f1027b`, ou SHA mais recente na abertura do gate) e confirmação do artefato instalado.
- [ ] Confirmação do responsável de F9-12: já aplicada no staging + versão/checksum, ou plano/migration aprovado separadamente. Não executar nem recomendar aplicação automática.
- [ ] Backup/snapshot sintético e rollback validados pelo responsável; contas de teste e mídia sintética criadas por ele.
- [ ] Permissão de acesso ao ambiente na ferramenta aprovada. Não envie senhas/chaves aqui; configure acesso por mecanismo seguro do ambiente.
- [ ] Plano para remoção dos dados sintéticos e retenção das evidências sem payloads sensíveis.

Se algum item estiver sem confirmação, estado continua `BLOCKED`; não testar conectividade e não tentar adivinhar F9-12.

## Matriz de cenários (planeada, não executada)

| Grupo | Critério de aceite | Estado |
|---|---|---|
| Contrato/validação BDRE | V1/v2 válidos; documentos malformados, tipo/shape desconhecido, subárvore não independente e payload acima de 4 MiB falham de forma fechada. | NOT RUN |
| Segurança de render | Texto não vira HTML executável; URLs/media continuam allowlisted; `custom_js` não é executado nem persistido em padrão. | NOT RUN |
| Padrões BDRE — ownership | Padrão de um administrador nunca aparece, pode ser lido ou alterado por outro; dono sempre deriva da sessão. | NOT RUN |
| Padrões BDRE — ACL/CSRF | Leitura exige `access cms/block_pattern` + `modify` do contexto; mutações exigem também `modify cms/block_pattern` e CSRF válido. | NOT RUN |
| Pages e Blog separados | Testar salvar/listar/renomear/excluir e inserir em cada contexto, sem mesclar CRUD/conteúdo; contexto incompatível deve ser recusado. | NOT RUN |
| Inserção/idioma/limites | IDs são regenerados; idioma de origem é só metadata; 50 itens por administrador e teto de 4 MiB são respeitados. | NOT RUN |
| Cascata/manual integrity | Excluir usuário limpa apenas os padrões daquele proprietário; instalação sem a tabela continua protegida; sem FK. | NOT RUN |
| Migration/schema F9-25 | Migration idempotente, schemas e grants coerentes; grupos customizados não recebem grants automáticos; rollback preserva os dados. | BLOCKED — F9-12 unknown; no SQL autorizado |
| F9-26 a11y/performance | Teclado, foco, leitores de tela, zoom/reflow e documento longo avaliados no browser real. | NOT RUN |
| F9-12 DB gate | Responsável confirma migration F9-12 já aplicada e versão/checksum no staging. | BLOCKED — F9-12 unknown |
| Release | Evidências sintéticas revisadas e decisão de release assinada pelo responsável. | BLOCKED |

Os cenários F9-25 de padrões incluem testes negativos de isolamento/ACL, CSRF, compatibilidade de contexto, payload inválido/excessivo e `custom_js`, além dos fluxos de sucesso. Pages e Blog devem ser exercitados separadamente com contas e conteúdo sintéticos. Nenhum cenário foi executado nesta atualização.

## Após autorização

O operador deve preencher a matriz cenário a cenário como `PASS`, `FAIL` ou `NOT RUN`, preservar resultados sem dados de usuário e interromper imediatamente em falha crítica. Um checker estático nunca muda o status para aprovação. A decisão de release continua humana.
