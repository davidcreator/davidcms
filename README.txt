ReamurCMS — título da página na prévia local BDREditor

HEAD esperado: def12fc2925120ab7eeaa19e20d20ffe2e92225a

A pré-visualização responsiva da instância CMS > Páginas mostra o título da página ativa (ou o rótulo localizado de página sem título) antes dos blocos. O título é lido do campo existente, sem duplicação. Não altera o renderer público, JSON, salvamento ou comportamento do Blog.

Aplicação local opcional dentro do checkout:
  bash apply-patch.sh Pages-Editor-Preview.patch def12fc2925120ab7eeaa19e20d20ffe2e92225a

O script valida HEAD e roda git apply --check antes de aplicar. Não faz commit/push, deploy, SQL nem migrations.
