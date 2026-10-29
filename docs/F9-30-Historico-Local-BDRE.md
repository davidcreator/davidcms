# F9-30 — Histórico local de rascunhos BDRE

## Objetivo

Dar ao editor recuperação de versões recentes do trabalho enquanto o usuário permanece na mesma aba, complementando a recuperação do último rascunho F9-28 e o undo/redo da sessão de edição.

## Comportamento

- A Home do editor mostra **Histórico de rascunhos** quando há snapshots anteriores disponíveis.
- O histórico usa o `sessionStorage` isolado por administrador, contexto Pages/Blog, registro e idioma definido em F9-28.
- Snapshots antigos são exibidos com data/hora local e quantidade de blocos. Recuperar uma versão substitui somente o documento em memória, depois de confirmação, validação contextual e regeneração do dirty state.
- O documento restaurado continua não salvo no servidor até o clique explícito em Salvar.
- Armazenamento temporário limita-se a cinco snapshots, orçamento de 512 KiB para o histórico e o teto corrente de 4 MiB do documento. Snapshots individuais maiores que o orçamento do histórico são descartados, sem impedir a cópia atual de recuperação.
- Se `sessionStorage` estiver indisponível/cheio, o editor mantém seu fluxo normal e histórico/recuperação ficam indisponíveis sem bloquear a edição.
- Strings PT-BR, EN-US e ES-EU.

## Limites

Histórico é local e temporário, não equivale a revisões server-side, não é compartilhado entre dispositivos ou abas independentes e expira junto com a sessão do navegador (além do limite de 24 horas da recuperação). Não altera banco, schema, migration ou ACL.

## Cadência

Entrega funcional local F9-30. A bateria ampla de testes permanece adiada ao marco de conclusão do BDREditor conforme o escopo atualizado pelo responsável.
