# F9-27 — Pré-flight para execução autorizada

**Estado atual: NÃO PRONTO / NÃO EXECUTADO.** A `main` consultada em 2026-10-25 está no commit `2d9ab58d026ed310ab048c828fbe9da2c2083f31`. O status publicado mantém `BLOCKED_NOT_RUN` e `f9_12_status=UNKNOWN_NOT_CONFIRMED`.

A opção escolhida é preparar o gate, não executar. As restrições atuais continuam valendo: sem autorização explícita não iniciar PHP/SQL/browser/app/E2E runtime. Nenhum ambiente foi descoberto, sondado ou acessado.

## Informações que o responsável deve fornecer/confirmar

Preencher fora de qualquer canal de log público; **não incluir credenciais, tokens, dump ou dados pessoais**.

- [ ] Autorização explícita e delimitada: quais famílias de teste estão autorizadas, janela, responsável de acompanhamento e condições de parada.
- [ ] Identificador/hostname do staging descartável e confirmação do dono de que não há rota, replicação, segredo ou banco compartilhado com produção.
- [ ] Commit exato a testar (padrão atual: `2d9ab58d026ed310ab048c828fbe9da2c2083f31`) e confirmação do artefato instalado.
- [ ] Confirmação do responsável de F9-12: já aplicada no staging + versão/checksum, ou plano/migration aprovado separadamente. Não executar nem recomendar aplicação automática.
- [ ] Backup/snapshot sintético e rollback validados pelo responsável; contas de teste e mídia sintética criadas por ele.
- [ ] Permissão de acesso ao ambiente na ferramenta aprovada. Não envie senhas/chaves aqui; configure acesso por mecanismo seguro do ambiente.
- [ ] Plano para remoção dos dados sintéticos e retenção das evidências sem payloads sensíveis.

Se algum item estiver sem confirmação, estado continua `BLOCKED`; não testar conectividade e não tentar adivinhar F9-12.

## Após autorização

O operador deve preencher a matriz existente cenário a cenário como `PASS`, `FAIL` ou `NOT RUN`, preservar resultados sem dados de usuário e assinalar a falha para interromper imediatamente. Um checker estático nunca muda o status para aprovação. A decisão de release continua humana.
