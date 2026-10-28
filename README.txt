# CMS Pages — layouts públicos via Themes

Pacote local para aplicar à árvore do repositório DavidCMS. Baseline remoto consultado antes das edições: `2fb77d3dcf661d4561b5672ef29050edc3560fe6`.

Conteúdo: allowlist ampliada de views públicas no Themes, link contextual de Pages, separação da barra visual do header, fontes de template sem `raw`, campos HTML confiáveis marcados explicitamente pelo adaptador, bloqueios para script/handlers/frames/autoescape override e documentação/ADR atualizadas. Sem alterações de schema/migration, sem publicação, deploy, staging ou GitHub write.

Aplicar `CMS-Pages-All-Pages.patch` na raiz do repositório (`patch -p1`), ou copiar as pastas do ZIP para a árvore correspondente.

Validação local: allowlists do Admin e Twig conferidas idênticas (25 chaves), todos os arquivos existem, as 10 rotas `system_key` mapeadas apontam para templates allowlisted existentes, fontes editáveis sem `raw`/script/event handler, sem caminhos ausentes; padrão de bloqueio verificado por amostras e patch aplicado com `--dry-run`. PHP CLI indisponível; lint/runtime Twig não executados. Revisão visual/runtime ainda pendente.
