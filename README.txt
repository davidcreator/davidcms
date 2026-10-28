ReamurCMS — foco programático no diálogo imersivo BDREditor

HEAD esperado: 95418caecd139f27fe3487f6a9ef7528f23cea4a

Ao entrar em tela cheia, o host do editor recebe tabindex="-1", permitindo que fluxos existentes de validação bloqueada devolvam o foco ao diálogo. Ao sair, role/aria e o valor original de tabindex são restaurados. Não altera a validação, POST, estado salvo, contrato de documento nem comportamento de layout.

Aplicação local opcional dentro do checkout:
  bash apply-patch.sh Pages-Editor-Focus.patch 95418caecd139f27fe3487f6a9ef7528f23cea4a

O script valida HEAD e roda git apply --check antes de aplicar. Não faz commit/push, deploy, SQL ou migrations.
