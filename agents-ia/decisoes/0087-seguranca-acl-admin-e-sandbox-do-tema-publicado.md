# ADR 0087 — Segurança: a ACL do admin checa a rota que executa; fonte de tema roda em sandbox no site publicado

| | |
| --- | --- |
| **Status** | ✅ aceita (passe de segurança S1 + S2, 2026-11-12) |
| **Data** | 2026-11-12 |
| **Contexto** | A análise do repositório listou doze achados de segurança (S1–S12). Os dois de severidade alta foram reproduzidos no código de `01b17b7` antes desta ADR. **S1:** um usuário logado de um grupo restrito abria qualquer tela do admin escrevendo a rota com `..` no meio. **S2:** uma fonte Twig de tema, publicada, lia constantes do PHP e executava comandos no servidor; a importação de pacote aceitava a mesma fonte. As demais (S3–S12) ficam para ADRs próprias. |

## S1 — a permissão e o executor liam a rota de jeitos diferentes

### O que acontecia

`startup/permission` limpava a rota por conta própria: cortava no primeiro `.` ou `|` e, se o arquivo do controller não existisse, liberava (para o dispatcher entregar o 404). `Action` fazia outra limpeza: **removia** `..` e juntava o que sobrava.

| `?route=` | A permissão checava | O dispatcher executava |
| --- | --- | --- |
| `user/us..er` | `user/us` (não existe → liberada) | `user/user` |
| `..user/user` | vazio (liberada) | `user/user` |
| `user/user_gro..up.save` | `user/user_gro` (liberada) | `user/user_group.save` |
| `design/th..eme.canvas\|1` | `design/th` (liberada) | `design/theme.canvas` |

Alcance: leitura de qualquer tela por quem já estava logado. Os métodos que gravam continuavam protegidos por `hasPermission('modify', …)` e CSRF.

### Decisão

1. **Uma leitura só.** `Action::parse()` é a fonte única da gramática de rota (`grupo/modulo`, `.metodo`, `|args`). O construtor de `Action` a usa; `startup/permission` passa a perguntar a um `Action` qual rota será executada (`getRoute()`) e se ela é executável (`isCallable()`). Não existe mais limpeza de rota fora de `Action`.
2. **`..` não é consertado.** Rota com `..` em qualquer ponto fica vazia, `isCallable()` devolve `false` e o dispatcher entrega o 404 da aplicação. Antes `us..er` virava `user`. Vale para Admin, Catalog e Install.
3. **Rota inexistente continua sendo 404, não 403.** A permissão libera a rota que o dispatcher não consegue executar. Isso é seguro agora porque a pergunta é feita ao mesmo `Action`; antes era a origem da falha.
4. **`startup/login` não mudou, de propósito.** Ele compara a rota **crua** com `common/login` e `common/logout`. Passar a rota normalizada ali abriria `common/login.qualquer_metodo` sem sessão. A comparação exata é a mais estrita e falha fechada; os testes fixam esse comportamento.

Caracteres fora da gramática (`-`, espaço, `%`…) continuam sendo descartados por `Action`, como antes; a diferença é que a permissão agora vê o resultado.

## S2 — fonte de tema rodava sem sandbox no site publicado

### O que acontecia

`Twig::setThemeTemplates()` (chamado por `catalog/startup/theme`) trocava o Environment do site por um Environment comum com as fontes do tema dentro. A única barreira era uma regex de HTML. A prévia e o canvas do editor já usavam Twig Sandbox, com outra lista.

| Fonte | Site publicado | Canvas do editor |
| --- | --- | --- |
| `{{ constant('DB_PASSWORD') }}` | imprimia a senha do banco | bloqueado |
| `{{ ['id']\|map('system')\|join }}` | executava o comando | bloqueado |
| `{{ x\|# c` ↵ `raw }}` | imprimia HTML sem escape (a regex de `\|raw` não via) | bloqueado |

`ThemePackage::validateConfig()` (importação) e `design/theme` (salvar) usavam a mesma regex. Um `.reamurtheme.json` malicioso, importado e ativado por um admin, tomava o servidor. A prévia de página do CMS no admin (`renderCatalogPagePreview`) tinha o mesmo defeito.

### Decisão

1. **Uma política.** `Template\ThemeSandbox` guarda a lista de tags, filtros e funções e é usada pela prévia (`renderSandboxPreview`), pelo canvas (`renderThemePage`), pela prévia de página do CMS e pelo site publicado. A política roda em modo estrito (`setStrict(true)`): nada é liberado por omissão. A lista é a do canvas (ADR 0083): tags `if for set block extends include`; filtros `escape e default join length upper lower title trim date json_encode slice nl2br`; funções `range cycle parent`. A prévia antiga não tinha `slice` e `nl2br`; passa a ter.
2. **Environment dedicado.** As fontes do tema vivem em um Environment próprio, sempre em sandbox. Tudo o que elas alcançam por `include` também roda em sandbox, inclusive os templates padrão. O Environment dos arquivos do sistema nunca recebe fonte de tema.
3. **`theme_partial()`.** `common/header.twig` é arquivo do sistema, usa `|raw` e incluía `partial/site_header`, que o tema pode redefinir. O `{% include … only %}` foi trocado por `{{ theme_partial('partial/site_header', {…}) -}}`: a função existe no Environment confiável, renderiza a chave pelo sandbox e entrega só as variáveis passadas. Nos Environments de sandbox ela existe como casca e está fora da política, então uma fonte de tema não consegue usar `common/header.twig` como procurador para o `|raw`.
4. **Violação não derruba a página.** No site publicado, a fonte que viola a política sai, o template padrão entra no lugar e a página é montada de novo; as outras fontes do tema continuam. O motivo vai para o log do sistema, no máximo uma vez por hora por (fonte, motivo), usando o cache do sistema. Escolha confirmada pelo usuário (a alternativa era a página de erro). Se a violação estiver em um arquivo do sistema que a fonte alcançou, a página inteira sai dos arquivos padrão.
5. **Salvar e importar conferem pelo parser.** `ThemeSandbox::check()` compila a fonte no sandbox e roda a mesma checagem do início do render, sem renderizar. `ThemePackage::validateConfig()` e o salvar de `design/theme` recusam a fonte fora da política; o salvar informa a fonte e a linha. Erro de sintaxe não é reprovado ali: fonte que não compila não executa, e o editor precisa salvar rascunho incompleto.
6. **O canvas e a comparação não reprovam o formulário.** `buildDraftConfig()` só confere a política quando chamado pelo salvar. No canvas a fonte fora da política já é trocada pelo padrão, com o erro no próprio trecho; reprovar o formulário inteiro apagaria da tela as outras edições não salvas.
7. **Salvar recusado devolve o que foi digitado.** Antes, um salvar recusado devolvia o formulário com as fontes salvas e a edição se perdia. Com a conferência da política isso ficaria mais comum, então `edit()` passa a devolver as fontes enviadas aos `<textarea>`.
8. **A regex de HTML fica.** Ela barra `<script>`, `<iframe>`, `on…=`, `javascript:`, coisas que o sandbox do Twig não conhece. Ela deixou de ser a defesa contra Twig perigoso.

## Efeito em sites existentes

- **Saída idêntica para os temas do repositório.** Os 6 presets × 11 rotas foram renderizados antes e depois, no canvas e no caminho publicado, com cache de template desligado, frio e quente: 132 páginas, hashes iguais. Sem tema, 11 rotas + prévia do CMS: iguais. O `-}}` em `common/header.twig` preserva a quebra de linha que a tag `include` removia.
- **Os 150 templates dos presets e os 25 templates padrão que o tema pode redefinir cabem na política.** O único arquivo padrão fora dela é `common/header.twig` (`|raw`), que não é fonte de tema.
- **Tema próprio que usa algo fora da lista** (por exemplo `|replace` ou `|number_format`) perde aquele template no site: entra o padrão, e o log diz qual fonte e por quê. O canvas do editor já mostrava esse mesmo erro desde a ADR 0083, então abrir o tema no editor antes de atualizar mostra o que vai cair. Exceção: a tag `use` e as funções `block()` e `attribute()` eram aceitas pelo canvas antigo (modo não estrito) e agora são recusadas.
- **Custo:** dois Environments em vez de um e as checagens do sandbox. No harness local, a página inicial de um preset (cabeçalho + rota + rodapé) passou de ≈0,5 ms para ≈0,9 ms com as classes já carregadas; uma medida mais grosseira, com processo novo e compilação do zero, deu ≈4,0 ms antes e de 5,1 a 5,5 ms depois. Medida de bancada, sem banco nem servidor web.
- **Rotas do admin com `..`** passam a dar 404. Uma busca por `route=` e `link('…')` com `..` em `dev/admin`, `dev/catalog`, `dev/install` e no núcleo não encontrou nenhum.

## Alternativas consideradas

- **Só validar ao salvar/publicar, sem sandbox no render.** Recusada: não protege o que já está salvo no banco, e a checagem estática não cobre chamada de método em objeto do contexto.
- **Sandbox por fonte (`SourcePolicyInterface`).** Recusada: a interface foi descontinuada no Twig 3.27 sem substituto (o mesmo changelog registra um bypass corrigido nesse modo), e sandbox por fonte deixa a fonte de tema incluir um arquivo confiável com dados dela (o `|raw` do cabeçalho rodaria com conteúdo do tema).
- **`include(…, sandboxed = true)` no cabeçalho.** Recusada: argumento descontinuado no Twig 3.29.
- **`Twig\Sandbox\Sandbox` e `SandboxBridgeExtension` (Twig 3.29).** É o mesmo desenho (Environment dedicado, sempre em sandbox, ponte explícita a partir do template confiável), agora oferecido pelo próprio Twig. Não usado nesta etapa: o `composer.json` aceita `^3.3.7`, e depender dessas classes exigiria subir o mínimo para 3.29. Candidata a substituir `ThemeSandbox::environment()` e `theme_partial()` em uma etapa própria.
- **Negar (403) em vez de 404 para rota inexistente.** Recusada: um administrador que erra uma URL passaria a ver "sem permissão". Com a leitura única a liberação deixou de ser explorável.
- **Ampliar a lista de filtros agora** para reduzir o impacto em temas próprios. Adiada: é decisão de política, não de correção. A lista está em um lugar só e pode crescer com ADR.

## Limites conhecidos

- Não foi testado em uma instalação com banco, sessão real de admin e servidor web. S1 é provado com o controller real `startup/permission` e um `User` real em PHPUnit; S2 com o adaptador `Twig` real e, no editor, com o controller real `design/theme` (modelo e view substituídos).
- `ThemeSandbox::check()` usa `TemplateWrapper::unwrap()` e o método gerado `checkSecurity()`, internos do Twig. Se uma versão futura os mudar, a conferência reprova (não aprova) e os testes acusam. O Twig está fixado em 3.30.0 pelo `composer.lock`.
- `Twig\Extension\SandboxExtension`, usada aqui e já usada pelo canvas e pela prévia antes desta ADR, foi marcada como interna no Twig 3.29 em favor de `Twig\Sandbox\Sandbox`. Continua funcionando em 3.30.0 sem aviso de descontinuação (medido); é o motivo da etapa futura citada nas alternativas.
- Um diretório de templates próprio (`template_directory`) com arquivos que usem filtros fora da lista: se uma fonte de tema os incluir, a página sai dos arquivos padrão. Nenhum existe no repositório.
- O limite de uma linha de log por hora depende do cache do sistema; sem cache, o aviso sai a cada página.
- Um tema publicado com fonte fora da política refaz a montagem da página a cada visita até a fonte ser corrigida (uma remontagem por fonte culpada).
- A política não cobre o conteúdo HTML em si além da regex existente (por exemplo CSS em `<style>`). Fora do escopo de S2.
- Outros achados da análise (S3–S12) continuam abertos.

## Consequências

- Arquivo novo: `dev/system/library/template/theme_sandbox.php`.
- `Twig::setThemeTemplates()` ganha o segundo parâmetro opcional `?\Closure $report`; deixa de substituir o Environment do site.
- `Action::parse()` é API pública nova. `Action` passa a devolver rota vazia para entrada com `..`.
- Uma chave de idioma nova, `error_template_source_detail`, nos três idiomas (pt-br, en-us, es-eu).
- Testes: `tests/Unit/Security/AdminAclRouteTest.php` (45) e `tests/Unit/Security/ThemeSandboxPublishedTest.php` (58). Os casos centrais falham no código anterior e passam neste.
- Sem tabela, coluna, migration, rota ou dependência nova. Dados salvos iguais.

## Próximas etapas (fora desta ADR)

- S3 em diante, pela ordem da análise.
- Avaliar filtros de dados puros para a política (`replace`, `number_format`, `first`, `last`…), com ADR.
- Tela no admin que liste as fontes do tema publicado que caíram para o padrão (hoje só o log).
