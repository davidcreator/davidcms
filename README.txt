ReamurCMS — retorno ao campo inválido ao salvar CMS > Páginas

HEAD esperado: 3e79ba078c09356c7a728589ffcc1a25ca934796

Quando a validação HTML nativa interromper o submit e houver um editor de Página em tela cheia, o fluxo fecha a tela cheia, ativa a aba que contém o primeiro campo inválido e foca esse campo. Fora desse caso, a validação nativa permanece intocada. Escopo exclusivo do formulário `data-cms-page-form`; sem alterações em Blog, controller, POST, ACL, CSRF ou persistência.

Aplicação local opcional dentro do checkout:
  bash apply-patch.sh Pages-Editor-Next.patch 3e79ba078c09356c7a728589ffcc1a25ca934796

O script valida HEAD e roda git apply --check antes de aplicar. Não faz commit/push, deploy, SQL nem migrations.
