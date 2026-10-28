ReamurCMS — salvar no modo imersivo de CMS > Páginas

HEAD esperado: 622ac269063498abb4540ea275df396b73bfecb6

A faixa de contexto do BDREditor mostra um botão Salvar apenas para usuários com can_modify em cms/page. O botão é submit dentro do formulário existente e usa seus handlers de serialização, CSRF e validação. Sem permissão, não é exibido e a faixa informa o modo de consulta. Não cria endpoint, AJAX, autosave, persistência paralela ou mudança no contrato do Blog.

Aplicação local opcional dentro do checkout:
  bash apply-patch.sh Pages-Editor-Save.patch 622ac269063498abb4540ea275df396b73bfecb6

O script valida HEAD e roda git apply --check antes de aplicar. Não faz commit/push, deploy, SQL nem migrations.
