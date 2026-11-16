# Guia — Textos do tema por idioma (KZ-19, ADR 0112)

Um tema tem três tipos de texto. Cada um tem o seu lugar, e os três mudam com o idioma do visitante (`en-us`, `pt-br`, `es-eu`).

| Tipo de texto | Exemplo | Onde fica | Como a fonte do tema imprime |
| --- | --- | --- | --- |
| Texto do sistema | "Enviar", "Ler mais", título de uma seção padrão | `dev/catalog/language/<idioma>/<rota>.php` | `{{ button_send }}`, `{{ text_about_title }}` |
| Conteúdo | projeto, post, página, depoimento | banco, tabelas `*_description` (uma linha por idioma) | `{{ project.name }}`, `{% for post in posts %}` |
| **Texto próprio do tema** | "Iniciar missão", "Fase 01" | **`config.strings` do tema** | **`{{ theme_text.hero_start }}`** |

Regra prática: se o texto já existe no sistema ou no conteúdo, use a variável dele. Só o que é do tema vai para o dicionário. **Texto digitado direto na fonte fica num idioma só.**

## O dicionário

```json
{
  "hero_start": { "en-us": "Start mission", "pt-br": "Iniciar missão", "es-eu": "Iniciar misión" },
  "phase_label": { "en-us": "Phase", "pt-br": "Fase", "es-eu": "Fase" }
}
```

- **Chave:** letras minúsculas, números e `_`, começando por letra, até 64 caracteres.
- **Idioma:** o código do idioma do Catalog (`en-us`, `pt-br`, `es-eu`).
- **Texto:** simples, até 2000 caracteres. Quebra de linha é aceita; HTML aparece como texto.
- Até 600 chaves por tema.

## Como preencher

1. Admin → Temas → abra o tema.
2. No fim do formulário, abra **"Textos do tema por idioma"**.
3. Cole ou edite o JSON no campo e salve o rascunho.
4. O bloco mostra quantos textos existem e quantos faltam em cada idioma.

Se o JSON estiver fora do formato, nada é salvo, a tela avisa e o que foi digitado continua no campo.

Para trocar uma frase que aparece na página, não é preciso mexer no JSON: veja [Editar na própria página](#editar-na-própria-página-kz-21-adr-0114), no fim deste guia.

## Como usar na fonte

```twig
<a class="btn" href="#fase-01">{{ theme_text.hero_start }}</a>
<span>{{ theme_text.phase_label }} 01</span>
<nav aria-label="{{ theme_text.nav_label }}">…</nav>
<p lang="{{ theme_lang }}">…</p>
```

- `theme_lang` é o idioma efetivo da página.
- Para um texto opcional: `{{ theme_text.extra|default('') }}`.
- Para quebrar linhas de um texto: `{{ theme_text.intro|nl2br }}`.

## O que o visitante vê quando falta tradução

1. O texto no idioma dele.
2. Se não houver: o texto em `en-us`.
3. Se também não houver: o primeiro idioma preenchido.

## Conferir antes de publicar

No canvas do editor, troque o seletor **"Idioma"**: a página é redesenhada com os textos daquele idioma, inclusive os que ainda não foram salvos.

## Cópia de segurança

Os textos fazem parte do tema. Estão:

- no banco, na linha do tema (rascunho e publicado) e na revisão que cada save guarda;
- no arquivo `.reamurtheme.json` do botão **"Exportar"**.

Guardar o arquivo exportado ou um dump do banco guarda os textos dos três idiomas. Importar o arquivo os traz de volta; arquivo alterado à mão é recusado pela soma de conferência.

## Tema filho

O filho herda os textos do pai. Uma chave repetida no filho substitui a do pai inteira, em todos os idiomas.

## Um tema convertido: David Universe 3D

O David Universe 3D (ADR 0113) é o primeiro tema entregue que usa o dicionário. Serve de modelo para converter outro tema. A regra seguida foi separar cada frase em um de três lugares:

| A frase é… | Vem de | Exemplo na fonte |
| --- | --- | --- |
| conteúdo do site (projeto, depoimento, serviço, post) | variável do sistema, a mesma do modelo padrão | `{{ p.name }}`, `{{ p.summary }}` |
| texto do sistema (título de seção, botão, rótulo de formulário) | chave de idioma do Catalog | `{{ heading_title }}`, `{{ button_back_home }}` |
| texto que só este tema tem | `config.strings` | `{{ theme_text.catalog_title }}` |

O que ajudou na conversão:

- **Formulário:** incluir o parcial do sistema (`{% include 'partial/cms-contact-form.twig' %}`) dentro de uma moldura do tema, em vez de desenhar os campos de novo. Os rótulos e as mensagens já vêm traduzidos.
- **Texto com número:** a fonte junta as partes. `{{ theme_text.level }} 03` imprime "Nível 03", "Level 03" ou "Nivel 03".
- **Recuo para lista vazia ou campo vazio:** `{{ p.summary|default(theme_text.catalog_card) }}`.
- **Atributos lidos em voz alta:** `aria-label="{{ theme_text.nav_label }}"`. Em imagem, `alt` vindo do conteúdo (`alt="{{ p.name }}"`).
- **Texto para uma cena:** o tema escreve o texto num atributo `data-*` e a cena lê. No David Universe 3D: `data-du-rotulo`, `data-du-fase-nome`, `data-du-msg-inicio`, `data-du-msg-orbe`, `data-du-msg-todos`.
- **Maiúsculas pelo desenho, não pelo texto:** o dicionário guarda "Orbes"; a fonte usa `|upper` ou o CSS usa `text-transform`.

Para conferir que não sobrou frase num idioma só, o teste `DavidUniverseLanguageTest` tira das fontes as variáveis, as tags, a folha de estilo e os desenhos, e compara as palavras que restam com uma lista curta do que é igual em qualquer idioma (`DA`, `KM`, `LVL`, `OK`, `Power-up`).

## Editar na própria página (KZ-21, ADR 0114)

Não é preciso abrir o JSON para trocar uma frase.

1. No editor do tema, aba **Página**, escolha no seletor **Idioma** o idioma que quer editar.
2. Dê dois cliques no texto, na própria página. Ou clique nele e use o campo **"Texto do tema em <idioma>"** do painel.
3. Digite. `Enter` conclui; `Esc` desfaz.
4. Para os outros idiomas, troque o seletor **Idioma** e repita.
5. **Salvar rascunho** grava. Antes disso, nada vai para o banco.

O que é digitado troca uma linha do JSON deste bloco: a daquela chave, naquele idioma. O resto fica como estava.

Bom saber:

- **A mesma chave pode aparecer em mais de um lugar.** Editar um troca todos. A ajuda do campo mostra a chave.
- **"Sem tradução neste idioma"** quer dizer que o site está mostrando o texto de outro idioma (em geral `en-us`). Digitar cria a tradução.
- **Com o trecho escolhido e nenhum elemento marcado**, o painel lista os "Textos do tema neste trecho".
- **Abre na página** o elemento que só imprime `{{ theme_text.chave }}`, com `|upper` ou `|lower` se houver.
- **Continua no JSON:** texto do tema dentro de atributo (`aria-label`, `placeholder`, `data-…`), texto com quebra de linha, e apagar uma tradução.
- Se o campo estiver em modo leitura, o aviso logo abaixo diz por quê (JSON inválido, texto com quebra de linha, página ainda carregando o idioma novo).
