# BDREditor — escopo confirmado: arquivo/download

## Estado

Escopo aprovado pelo usuário para o próximo incremento local do BDREditor. Ainda **não implementado**. Nenhuma alteração foi feita no GitHub, staging, produção ou banco.

## Base revisada

- `main` verificada em `a67b14dc4cb553a800729146dd8490b48643f39f` (tarefa 01, `core/table`).
- Os hashes dos fontes da tarefa 01 comparados com o pacote local continuam correspondentes.
- A biblioteca atual do Blog restringe listagem/upload a imagens em `catalog/view/image/blog/`; `catalog/view` não tem raiz de downloads.

## Decisões aprovadas

- Incremento funcional: bloco `core/file` de arquivo/download no BDREditor (editor exclusivo do ReamurCMS; Gutenberg não é dependência nem código/assets incorporados).
- Expandir a biblioteca de mídia do Blog para selecionar/gerir documentos, em área separada.
- Raiz pública proposta e aprovada: `catalog/view/download/blog/`.
- Allowlist inicial: PDF, DOCX, XLSX, PPTX, ODT, ODS, CSV e TXT.
- Excluir ZIP, HTML, scripts e tipos arbitrários. Sem exclusão de arquivos.
- Validar referência local no servidor e renderizar link determinístico com rótulo acessível; sem URL remota, traversal, symlink ou atributos livres.
- Não adicionar schema/migration nem persistência compartilhada. Preservar renderer e validação server-side.
- Guardrails de execução permanecem: apenas leituras pontuais e verificações estáticas; não executar PHP, SQL, browser/app, testes runtime, staging ou deploy; não escrever no GitHub.
- Não atribuir número oficial de tarefa novo.

## Critérios de aceite a implementar

1. `core/file` só aceita referência relativa sob `view/download/blog/`, normalizada e sem segmentos vazios, `.`/`..`, barras invertidas, query/fragmento, esquema, protocolo relativo, controles ou symlinks.
2. Extensão permitida é validada no servidor e no upload usando MIME detectado; extensão e conteúdo precisam concordar. Limite inicial: 10 MiB por arquivo (a confirmar/ajustar na implementação conforme limites já existentes do ReamurCMS).
3. Upload usa endpoints admin existentes com ACL `modify` e CSRF, nome físico aleatório, sem confiar no nome enviado e sem sobrescrita. Não há rota de exclusão.
4. Biblioteca apresenta apenas arquivos permitidos na área de downloads; não mistura documentos com imagens. Picker não permite inserir outro tipo de bloco.
5. Bloco usa atributo de caminho local e conteúdo rich-text v2 como nome visível; rejeita label vazio, somente whitespace ou acima do limite textual existente. Não permite abrir em nova aba, HTML arbitrário ou CSS livre nesta fase.
6. Renderer server-side gera somente `<a>` e texto escapado, com `href` local validado e atributo `download` fixo. Link inacessível/inválido não é renderizado; comportamento deve ser definido fail-closed.
7. Strings PT-BR, EN-US e ES-EU, fixture válida e inválidas, checker estático e notas de limitações.
8. Nenhuma alteração em operações de upload de imagem de outros módulos; qualquer helper compartilhado mantém o comportamento anterior byte/semântica-equivalente.

## Ponto de implementação a resolver

Antes de escrever fontes, verificar por leitura pontual os limites de upload e convenções de segurança do ReamurCMS (incluindo config PHP/web para tamanho de request e MIME) e revisar o fluxo Blog de lista/upload de mídia, ACL e CSRF. Não presumir que a extensão do helper compartilhado é segura para todos os consumidores; preferir caminho dedicado que preserve integralmente o upload de imagens.
