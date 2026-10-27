ReamurCMS — separação do menu Páginas (CMS) e Blog

Base: HEAD 933d03020f7c251b5dc3d6881c2f384570d2ec5f
Mudança: dois grupos independentes no menu lateral do Admin; CMS Pages continua na rota cms/page, Blog/Postagens continua na rota blog/blog. ACL por rota preservada. Guia CMS atualizado.

Não inclui mudanças de banco, migration, deploy nem GitHub write.

Aplicação: revisar a base e os arquivos atuais; a partir da raiz do repositório, executar git apply --check e depois git apply neste patch.
