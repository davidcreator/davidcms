ReamurCMS — Blog público e execução de thumbs Three.js

Base exata: HEAD 2bbac1ad532a010e78e85d3aa61129258994ddc7

Este overlay adiciona o link Blog à navegação Catalog, faz o shell Catalog renderizar os scripts registrados via Document como deferidos, e atualiza documentação. O acesso Admin ao item Blog continua protegido por ACL; a migration existente upgrade_f7_01_blog.sql concede blog/blog ao grupo Administrator. Nenhuma migration foi executada e nenhum SQL está incluído neste patch.

Aplique somente após conferir que a árvore está exatamente na base indicada e revisar os arquivos: git apply --check CMS-Blog-Catalog-2bbac.patch; depois git apply CMS-Blog-Catalog-2bbac.patch.

O relatório DB-Readiness.md descreve o schema verificado e as pendências de confirmação no banco real.
