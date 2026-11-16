# ADR 0113 — Temas: o David Universe 3D acompanha o idioma do site (textos do tema em `config.strings`, conteúdo vindo do sistema, HUD da cena lido da página)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-20, 2026-11-16) |
| **Data** | 2026-11-16 |
| **Contexto** | A ADR 0112 criou o lugar para o texto próprio do tema por idioma (`config.strings`), mas nenhum tema o usava. O David Universe 3D, nos dois pacotes (`docs/temas/David-Universe-3D.reamurtheme.json` e `tema/david_universe_3d_bo.reamurtheme.json`, com a cena real), trazia tudo escrito em português dentro das 25 fontes: menu, HUD, títulos das fases, projetos, depoimentos, serviços, formulários e rodapé. Com o site em `en-us` ou `es-eu`, o visitante via a página inteira em português. A cena `david-universe.js` também escrevia no HUD palavras fixas em português ("FASE 03 — PROCESSO", "Orbe coletado"). |
| **Relacionadas** | ADR 0112 (textos do tema por idioma), ADR 0102 e 0103 (selos e pendências do David Universe 3D), ADR 0097 (seções de página), ADR 0083 (canvas do editor) |

## Decisão

O David Universe 3D passa a ter três fontes de texto, cada uma com o seu lugar, e nenhuma frase escrita num idioma só dentro das fontes do tema.

### De onde vem cada texto

1. **Conteúdo do site** (projetos, depoimentos, etapas do processo, habilidades, serviços, ferramentas, clientes, posts, páginas, endereço, redes): das mesmas variáveis que o modelo padrão do sistema recebe. O tema deixa de trazer cópias fixas desse conteúdo.
2. **Textos do sistema** (títulos das seções, botões, rótulos dos formulários, mensagens): das chaves de idioma do Catalog (`dev/catalog/language/<idioma>/`), como no modelo padrão.
3. **Textos que só existem neste tema** ("Fase extra", "Escolha o seu nível", "Voltar à decolagem", rótulos do HUD, avisos da cena): em `config.strings`, nos três idiomas, impressos com `{{ theme_text.chave }}`. São 62 chaves no pacote da cena real e 59 no outro (sem os três avisos que só a cena mostra).

Ficam iguais em qualquer idioma, por serem marca, unidade ou rótulo de jogo: `DA`, `KM`, `LVL`, `OK`, `Power-up`.

### As fontes

- As 25 chaves de modelo continuam as mesmas, e os dois pacotes continuam diferindo só nos ganchos da cena real.
- A home inclui os parciais do próprio tema (`partial/hero`, `about`, `methodology`, `skills`, `services`, `portfolio`, `testimonials`, `news`, `contact`), cada um lendo as variáveis do sistema.
- As páginas internas seguem a ordem de seções do sistema (`cms_page_sections`, ADR 0097).
- **Formulários:** contato e orçamento incluem os parciais do sistema (`partial/cms-contact-form.twig`, `partial/cms-quote-form.twig`) dentro de uma moldura do tema. O formulário passa a ter os campos, a proteção e os textos traduzidos do sistema; o tema só dá a aparência.
- **Imagens:** capa do projeto, logo do cliente e imagem do post vêm do conteúdo, com `alt` do conteúdo. Os 30 selos desenhados para os projetos fixos saíram. Ficaram seis desses desenhos como capa de reserva, em rodízio, para o projeto sem imagem.
- Toda tag continua legível pelo editor (nenhuma com Twig entre os atributos), e nenhum `aria-label`, `alt`, `title` ou `placeholder` tem palavra fixa.

### A cena

`david-universe.js` continua sem escrever HTML. Passa a ler as palavras do HUD em atributos da página, que o tema preenche com `theme_text`:

| Atributo | Onde | Texto |
| --- | --- | --- |
| `data-du-rotulo` | elemento `[data-du-fase]` | a palavra "Fase" |
| `data-du-fase-nome` | cada seção `fase-NN` | o nome da fase |
| `data-du-msg-inicio`, `data-du-msg-orbe`, `data-du-msg-todos` | elemento `[data-du-toast]` | os três avisos |

A função `themeText(node, name, fallback)` lê o atributo, aceita até 120 caracteres e usa o valor só em `textContent`. Sem o atributo, vale o texto fixo de antes: um tema antigo com a cena nova continua igual.

### No sistema

- **`skill_chips` da home** vinha de uma lista fixa em inglês no controller. Passa a vir da chave de idioma `text_skill_chips` (itens separados por `|`). Sem a chave, vale a lista de antes.
- **Três textos da home corrigidos em `pt-br` e `es-eu`**, que estavam em inglês: `text_hero_kicker`, `text_contact_kicker`, `text_contact_address`. Valem para qualquer tema e para o modelo padrão.

### Aparência

Uma folha "KZ-20" no fim do `<style>` do cabeçalho do tema veste o que agora vem do sistema (texto editorial, filtros, capas, galeria, ficha do projeto, formulário, logos). Dois ajustes de aparência entraram junto:

- As páginas internas carregam o `common.css` do sistema, que centraliza o texto do `body`. O tema volta o alinhamento à esquerda no HUD, na página e no rodapé.
- No pacote da cena real, o aviso e a barra de progresso ficavam em fluxo dentro do HUD e ocupavam uma faixa dele. Agora ficam presos ao HUD: a barra no topo, o aviso logo abaixo, à direita.

### Testes

- Novo `DavidUniverseLanguageTest` (8 testes): os três arquivos com os textos completos nos três idiomas; cada chave pedida existe e nenhuma sobra; nenhuma palavra fixa além das cinco listadas, nas fontes e nos atributos; toda tag aberta ao editor e `alt` vindo do conteúdo; a página 404 do tema renderizada pelo motor real nos três idiomas; o contrato dos atributos entre a cena e o tema; o recuo da cena; `text_skill_chips` nos três idiomas.
- Contagens fixas do David Universe 3D ajustadas em `KazLooseEndsTest`, `KazElementNameTest`, `KazElementAttrsTest`, `KazElementA11yTest`, `KazElementSpaceTest` e `KazElementStyleTest`. As que diziam "nenhuma pendência" continuam em zero.

## O que muda para quem usa

- **Visitante:** com o tema ativo, a página sai no idioma escolhido, na home e nas páginas internas, inclusive o HUD e os avisos da cena.
- **Quem administra o conteúdo:** projetos, depoimentos, serviços e demais listas da home passam a ser os cadastrados no painel. O que estava escrito dentro do tema não aparece mais.
- **Quem já tem o tema instalado:** o tema gravado no banco não muda sozinho. É preciso importar o arquivo novo (a importação atualiza o rascunho do tema de mesmo slug) e publicar. Ajustes feitos à mão nas fontes antigas são substituídos.

## Alternativas consideradas

1. **Traduzir as frases fixas dentro do tema** (pôr os projetos e depoimentos fixos em `config.strings`). Recusada: manteria duas cópias do conteúdo, e o cadastro do painel continuaria sem efeito na home.
2. **Uma fonte por idioma** (três cópias de cada modelo). Recusada: triplica o pacote e o trabalho de qualquer ajuste de layout.
3. **Passar os textos do HUD à cena por um bloco de dados** (`<script type="application/json">`). Recusada: o sandbox do tema recusa `<script`; atributos `data-*` já são o canal da cena.
4. **Manter os formulários do tema e só traduzir os rótulos.** Recusada: os formulários do tema não tinham os campos nem a proteção do formulário do sistema.

## Limites conhecidos

- **O que é dado continua no idioma em que foi cadastrado.** Depoimentos, descrições de projeto e de ferramenta, nomes dos filtros do portfólio, nomes das páginas legais e o endereço (configuração do site) aparecem como estão no banco. Se só existem em inglês, saem em inglês nos três idiomas.
- **O texto do tema é editado como JSON** no bloco "Textos do tema por idioma" do editor (ADR 0112). A edição direta na página não escreve nesses textos. *(Mudou na KZ-21: pela ADR 0114, o elemento que só imprime um texto do tema é editado na página, no idioma do canvas.)*
- **As traduções para inglês e espanhol não passaram por revisão de um falante nativo.**
- **O nome da fase no HUD troca com pequeno atraso em relação à rolagem**, como antes desta etapa.
- **Só o David Universe 3D foi convertido.** Atlas Studio, Atlas Studio 3D e David Creator continuam com texto fixo.
- **Os roteiros de navegador das etapas KZ-05 a KZ-18** editam textos fixos do tema antigo. Rodam contra o pacote antigo, mantido como peça de teste da bancada.

## Verificação

- PHPUnit em PHP 8.2 e 8.3: 1710 testes, 44541 conferências (eram 1702 e 46748; as conferências caem porque o tema tem menos elementos fixos para percorrer). PHPStan sem erro. Os dez verificadores em Node passam.
- Os três arquivos passam pelo leitor de pacotes, pela política do sandbox e pelo analisador do Twig, fonte por fonte. Tamanhos: `David-Universe-3D.reamurtheme.json` 87.965 → 82.721 bytes; `david_universe_3d_bo.reamurtheme.json` 90.215 → 86.203; `bo_config.json` 82.754 → 75.382.
- Bancada, site publicado: cada pacote ligado e 11 páginas pedidas nos três idiomas (home, contato, orçamento, portfólio, projeto, blog, post, página, 404, ferramentas, ferramenta). As 66 respostas foram lidas: o texto do tema e do sistema sai no idioma pedido; fica num idioma só apenas o que é dado. Nenhuma recusa do sandbox no log. Tema desligado ao fim, com as linhas de tema iguais às de antes.
- Bancada, Chromium, site publicado (`fotos.cjs`): no pacote da cena real, o `canvas` da cena monta e o HUD mostra "Fase 00 — Decolagem" / "Phase 00 — Liftoff" / "Fase 00 — Despegue", o rótulo dos orbes, a distância e os avisos ("Orbe coletado 3/14", "Orb collected 1/14", "Orbe recogido 2/14") no idioma da página; o nome da fase acompanha a rolagem até "Fase 08 — Contato". Retratos da home em cinco pontos e de sete páginas internas conferidos a olho. Único erro de console: o 404 da própria página de teste.
- Bancada, Chromium, editor (`editor.cjs`, 39 conferências em cada pacote): o bloco "Textos do tema por idioma" traz as 62 (ou 59) chaves sem falta em nenhum idioma; o canvas da home, do contato e do 404 mostra o tema em `pt-br`, `en-us` e `es-eu` conforme o seletor "Idioma", sem frase de outro idioma; os elementos continuam marcados para o painel; nenhum erro no console.
- Bancada, regressão: os 79 roteiros de navegador das etapas KZ-05 a KZ-19 passam com o código novo. Eles editam textos fixos do tema antigo, então rodaram com o pacote antigo importado na bancada; depois o pacote novo voltou e a soma dos temas ficou igual à de antes da regressão.
- Guardas do repositório (`verify-checksums`, `verify-ci-workflow`, `verify-deps-layout`, `verify-build-prod`) e links dos `.md` sem erro.
