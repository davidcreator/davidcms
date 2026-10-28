# CMS > Páginas — prévia privada de rascunho (V1)

Patch local preparado sobre o HEAD remoto `c3efce52edb5a25df10d211309bb4d1ef4c6a34a` (`feat(cms/pages): add dedicated page image library for block media`). Não publicado no GitHub.

## Escopo confirmado

- Botão por idioma, somente para conteúdo no modo de blocos e usuários com `modify cms/page`.
- Abre uma nova aba por POST para a rota própria `cms/page.preview`; a rota exige ACL e CSRF, limita e valida o documento v2 com `BlockDocument` e renderiza com `BlockRenderer`.
- Nenhum save, SQL, gravação em sessão, migration ou exposição de rascunho no Catalog. Resposta com `no-store`, `noindex` e cabeçalhos de segurança.
- A prévia mostra título e blocos num layout isolado. Não reproduz o cabeçalho/rodapé completos nem campos personalizados do Catalog.
- Os diretórios de download/áudio/vídeo do Blog são passados vazios ao renderer; blocos que dependam deles podem não aparecer. O JavaScript customizado de Three.js é removido da cópia temporária antes da renderização; as cenas aprovadas/configuráveis permanecem.
- Pequeno ajuste de ordem em `formData`: calcula `can_modify` antes da configuração condicional dos recursos próprios de Pages, mantendo a mesma ACL. Isso é necessário para que os controles autorizados sejam preparados no formulário.

## Conteúdo do pacote

- `CMS-Pages-Safe-Draft-Preview.patch` — patch para aplicar na raiz do repositório sobre o HEAD acima.
- `implementation/` — cópia dos sete arquivos adicionados/modificados, nos caminhos `dev/...`.

Aplicação local: `git apply CMS-Pages-Safe-Draft-Preview.patch`.

## Validações

- `git apply --check`: passou contra as fontes seletivas do HEAD indicado.
- `git diff --check`: passou.
- `node --check` no novo script: passou.
- Checagens estáticas de ACL, CSRF, limite, validação de documento, ausência de chamadas a model/SQL no preview, ausência de persistência, tradução nos três idiomas e remoção de custom JS Three.js: passaram.
- `php -l` não está disponível (PHP CLI ausente). Não houve execução do CMS, teste visual/browser, escrita no GitHub, migration, staging ou deploy.
