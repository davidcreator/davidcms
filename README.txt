ReamurCMS — contexto de CMS > Páginas no BDREditor

HEAD esperado: cfc5d694139fe3f26b1791b7aeb4ccd1bf46b5eb

No modo tela cheia do editor de blocos de Páginas, apresenta título, idioma ativo e indicador sincronizado de alterações salvas/não salvas; informa explicitamente que salvar continua no formulário. É apenas contexto visual: não duplica campos do formulário, não muda o JSON, não cria autosave e não altera os fluxos de validação ou persistência. Textos adicionados em pt-br, en-us e es-eu. A opção é fornecida apenas às instâncias CMS Page, não às do Blog.

Aplicação local opcional dentro do checkout:
  bash apply-patch.sh Pages-Editor-Context.patch cfc5d694139fe3f26b1791b7aeb4ccd1bf46b5eb

O script valida HEAD e roda git apply --check antes de aplicar. Não faz commit/push, deploy, SQL nem migrations.
