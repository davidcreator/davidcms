# Hotfix F9-48 — fallback de strings da barra de ferramentas

Este hotfix responde ao log de 30/10/2026:

```text
Undefined array key "text_block_workspace_rail_label"
```

A causa encontrada é um desencontro entre o controller `cms/page.php` do F9-48 e o arquivo de idioma carregado no servidor: o controller indexava quatro chaves novas sem valor alternativo. Se a versão do idioma instalada não continha as chaves, o aviso podia interromper a construção da tela e ser apresentado como falha de rota.

O ZIP do F9-47 fornecido anteriormente continha **apenas** `blog-block-editor.js`, `blog.php` e a documentação. Portanto, ele não restaurava `cms/page.php` nem os idiomas de CMS Page que vieram no pacote F9-48. Isso explica por que voltar somente ao ZIP F9-47 não removeu a referência que aparece no log.

## Aplicação do hotfix

O pacote contém os dois controllers completos:

- `dev/admin/controller/cms/page.php`
- `dev/admin/controller/blog/blog.php`

As quatro strings da barra agora usam fallback `??`, de modo que idioma ausente não gere `Undefined array key`. As rotas e os nomes dos controllers não foram alterados. Antes de copiar, guarde também uma cópia dos arquivos atuais; substitua os dois controllers pelos arquivos do ZIP e reteste as rotas de Page e Blog.

Não é necessário alterar banco, rodar migrações, trocar configurações ou publicar o restante do F9-48 para aplicar este hotfix.

## Limites

O hotfix foi verificado por inspeção das referências e por checks estáticos de presença dos fallbacks e métodos de entrada dos controllers. PHP CLI não está disponível neste ambiente; portanto, não foi possível executar `php -l`, nem testar o site local/staging. Se o aviso persistir, confira se o arquivo instalado é exatamente o `cms/page.php` deste pacote e se o PHP está servindo o código atualizado (OPcache).
