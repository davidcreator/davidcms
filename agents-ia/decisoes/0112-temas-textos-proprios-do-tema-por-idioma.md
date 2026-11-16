# ADR 0112 — Temas: textos próprios do tema por idioma (`config.strings`, impressos com `{{ theme_text.chave }}`)

| | |
| --- | --- |
| **Status** | ✅ aceita (KZ-19, 2026-11-16) |
| **Data** | 2026-11-16 |
| **Contexto** | O Catalog tem três idiomas (`en-us`, `pt-br`, `es-eu`) e dois lugares para texto: os textos do sistema em `dev/catalog/language/<idioma>/` e o conteúdo nas tabelas `*_description`. O tema não tinha um lugar próprio. Um texto que só existe no tema ("Iniciar missão", "Fase 01") era digitado na fonte Twig, num idioma só. Como a fonte do tema substitui o modelo do sistema, a página deixava de mudar com o idioma: medido na bancada, a página inicial sem tema troca de 85 a 88 textos de 181 ao mudar de idioma; com o David Universe 3D, 0 de 249. |

## Decisão

O pacote de tema ganha um dicionário de textos por idioma. A fonte do tema imprime o texto pela chave, e o sistema entrega o texto do idioma da requisição.

### O contrato

```json
"strings": {
  "hero_start": { "en-us": "Start mission", "pt-br": "Iniciar missão", "es-eu": "Iniciar misión" }
}
```

1. **Onde fica:** em `config.strings` do tema, ao lado de `templates` e `tokens`. Vai na mesma linha de `rms_design_theme` (`draft_config`, `published_config`) e no mesmo arquivo `.reamurtheme.json` da exportação. Nenhuma tabela, coluna ou arquivo novo.
2. **Chave:** `^[a-z][a-z0-9_]{0,63}$`. **Idioma:** `^[a-z]{2}-[a-z]{2}$`. **Texto:** UTF-8 válido, não vazio, sem caractere de controle (só `\n`), até 2000 caracteres.
3. **Limites:** 600 chaves por tema, 12 idiomas por chave. O campo do editor aceita até 262144 bytes de JSON, metade do teto do pacote (524288), para o tema salvo caber na exportação junto com as fontes.
4. **Fora do contrato, nada é gravado:** o save do editor é recusado (`error_theme_strings`) e o arquivo importado é recusado inteiro (`strings_invalid`). Publicar copia o rascunho já validado. Se a linha do banco for alterada à mão, o site ignora só a entrada estragada. Tema sem a chave continua válido e sai igual ao que entrou.
5. **É texto simples.** A fonte do tema escapa na saída; HTML dentro de um texto aparece como texto.
6. **Tema filho:** herda os textos do pai e substitui chave por chave. A chave do filho troca a do pai inteira (o filho responde por todos os idiomas dela).

### Na fonte do tema

- `{{ theme_text.hero_start }}` imprime o texto no idioma da requisição.
- `{{ theme_lang }}` é o idioma efetivo (`pt-br`, por exemplo).
- Tema sem textos: `theme_text` é um mapa vazio, e `{{ theme_text.x|default('…') }}` funciona.

### Qual idioma é entregue

1. O idioma da requisição, decidido como já era: `?language=`, sessão, idioma do site, `config_language_catalog`.
2. Se a chave não tem texto nesse idioma: o de `en-us` (o idioma de recuo do Catalog).
3. Se também não tem: o primeiro idioma preenchido.

Um texto nunca some por falta de tradução; aparece em outro idioma. O painel mostra quantos textos faltam em cada um dos três idiomas.

### O caminho

1. `ThemeStrings` (classe nova, pura, em `dev/system/library/theme/theme_strings.php`) valida, normaliza (chaves e idiomas em ordem) e resolve o mapa para um idioma.
2. `ThemePackage` inclui `strings` em `CONFIG_KEYS` (entra na soma de conferência, na exportação e na importação) e em `MAP_KEYS` (herança por chave).
3. `ThemeSnapshot::resolve()` devolve `language` e `strings` (mapa plano `chave => texto`, já no idioma do contexto).
4. `startup/theme.php` do Catalog chama `Twig::setThemeStrings()` com esse mapa, antes das fontes do tema.
5. `Twig` entrega `theme_text` e `theme_lang` ao ambiente comum e ao `ThemeSandbox`. É um canal separado de `setThemeGlobals()`, que corta em 64 itens e 256 bytes por valor (limites de variável de regra); um parágrafo passa disso.
6. As prévias do editor (canvas, prévia isolada, comparação) recebem os mesmos dois nomes por `ThemePreviewState::globals()`, no idioma escolhido na prévia.

### No editor

- Bloco novo no fim do formulário do tema: **"Textos do tema por idioma (n)"**, com um campo de JSON.
- **É um campo só** (`strings_json`), não uma grade de inputs: 250 textos em 3 idiomas passariam do `max_input_vars` do PHP (1000 por padrão) e seriam cortados sem aviso.
- O servidor valida ao salvar. JSON inválido: nada é salvo, a mensagem `error_theme_strings` aparece e o que foi digitado volta para a tela.
- Campo vazio ou `{}`: o tema fica sem textos. **Formulário sem o campo** (integração antiga): os textos gravados ficam.
- O canvas envia o formulário inteiro, então mostra os textos digitados antes de salvar, no idioma do seletor "Idioma".

### Textos e versão

- Seis chaves novas em cada arquivo de idioma do admin (`pt-br`, `en-us`, `es-eu`), em `design/theme.php`: `error_theme_strings`, `text_kaz_strings_summary`, `text_kaz_strings_note`, `text_kaz_strings_key`, `text_kaz_strings_missing`, `text_kaz_strings_field`.
- Nenhum CSS ou script mudou; a versão dos arquivos do editor continua `-kaz-theme-editor-kz17`.

### Testes

- `KazThemeStringsTest` (16 testes): gramática e limites, recuo de idioma, pacote (validar, exportar, importar, soma, herança), estado efetivo nos três idiomas, entrega no site publicado (escape, texto de 1900 caracteres, ordem das chamadas, global de regra que não se passa pelos textos), canvas e prévia isolada nos três idiomas, validação do campo no servidor, save (grava, preserva, apaga, recusa) e a tela nos três idiomas.
- `KazFrameTextTest`: contagem de textos do editor de 242 para 247.

## O que muda para quem usa

- **Nada muda nos temas entregues nem no site.** Esta etapa cria o lugar e o caminho. Os seis temas de `docs/temas/` continuam com o texto fixo que tinham; converter um tema é trocar cada texto fixo por `{{ theme_text.chave }}` e preencher o dicionário (etapa seguinte).
- **Cópia de segurança:** os textos do tema vão onde o tema já vai: no banco (linha do tema, mais a revisão que cada save guarda em `rms_design_theme_revision`) e no arquivo exportado pelo botão "Exportar". Um dump do banco ou o arquivo exportado recuperam os textos nos três idiomas. O sistema continua sem rotina própria de backup.

## Alternativas consideradas

| Alternativa | Por que não |
| --- | --- |
| Arquivos de idioma por tema em `dev/catalog/language/` | O tema vive no banco e viaja num arquivo só. Texto em disco ficaria fora da exportação e da revisão, e exigiria escrever arquivo PHP pelo painel. |
| Tabela nova `design_theme_string` | Migration em site instalado e um segundo lugar para manter em sincronia com rascunho, publicado e revisões. O `config` já tem os três. |
| Uma fonte Twig por idioma (`common/home@pt-br`) | Triplica as 25 fontes e a manutenção; uma correção de marcação teria de ser feita três vezes. |
| `{% if theme_lang == 'pt-br' %}` na fonte | Funciona, mas mistura texto e marcação, não tem recuo e não permite ao painel dizer o que falta. |
| Passar os textos por `setThemeGlobals()` | Corta em 256 bytes por valor e 64 itens. |
| Grade de inputs no editor (um por texto e idioma) | `max_input_vars`. Fica para uma etapa própria, com a grade montada no navegador e enviada pelo mesmo campo único. |

## Limites conhecidos

- **Nenhum tema usa o dicionário ainda.** Ver "O que muda para quem usa".
- **O campo é JSON digitado.** Serve para colar e conferir; não é uma tela de tradução. Um erro de vírgula recusa o save inteiro (com a mensagem e o texto de volta).
- **Idiomas do aviso de falta fixos** em `en-us`, `pt-br`, `es-eu`, os mesmos do seletor do canvas. O contrato aceita outros códigos.
- **Texto simples, sem variável dentro.** "Olá, {nome}" não é interpolado; a fonte junta as partes.
- **O texto digitado direto na página (ADR 0111)** continua escrevendo na fonte, num idioma só. Um elemento que imprime `{{ theme_text.x }}` é tratado como dado do site e não abre para edição ali.
- **Atributos** (`aria-label`, `alt`, `title`) aceitam `{{ theme_text.x }}` como qualquer variável; o painel "Elemento" os mostra como valor vindo de dado.

## Verificação

- PHPUnit em PHP 8.2 e 8.3: 1702 testes, 46748 conferências. PHPStan sem erro. Os dez verificadores em Node passam.
- Bancada, Chromium (`prova.cjs`, 34 conferências): tema novo sem a chave na exportação; canvas em `pt-br`, `en-us` e `es-eu` com o texto do idioma, o recuo para inglês, um texto de mais de 1000 caracteres inteiro e HTML mostrado como texto; JSON inválido recusado sem mudar a soma do tema; save, reabertura, contador e aviso de falta; exportação com os três idiomas; tela do campo nos três idiomas do admin; nenhum erro no console.
- Bancada, site publicado: tema de prova ligado, página 404 pedida com `?language=` nos três idiomas (texto certo em cada um), de novo sem o parâmetro com a sessão (o idioma fica), sem sessão e com idioma desconhecido (inglês). O arquivo exportado voltou inteiro pelo leitor de pacotes. Tema de prova apagado; as somas das linhas de tema ficaram iguais às de antes.
- Bancada, regressão: os 77 roteiros de navegador das etapas KZ-05 a KZ-18 passam com o código novo. Um deles (`kz14/avisos.cjs`) contava 20 blocos de texto na tela do editor e passou a contar 21, o bloco novo; os textos do bloco passaram na conferência de idioma desse roteiro em `en-us` e `es-eu`. A tela do editor, comparada linha a linha com a da KZ-18 nos três idiomas, só difere no bloco novo.
- Guardas do repositório (`verify-checksums`, `verify-ci-workflow`, `verify-deps-layout`, `verify-build-prod`) e links dos `.md` sem erro.
