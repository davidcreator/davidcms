# F9-43 — Guia Personalizar para os blocos BDREditor

## Objetivo entregue

O inspetor contextual agora tem as guias **Conteúdo** e **Personalizar**. Cada bloco selecionável tem um botão **Personalizar** na própria barra de ferramentas, que abre diretamente a guia e o painel de configurações. As opções de conteúdo que já existiam permanecem na guia Conteúdo.

A guia Personalizar está disponível para todos os tipos do esquema BDRE v2, incluindo conteúdo, mídia, layout e nós internos de listas/tabelas/colunas. Oferece:

- cor do texto e cor de fundo;
- cor, estilo (sólido/tracejado/pontilhado) e espessura da borda;
- raio dos cantos, espaçamento interno e externo;
- tamanho da fonte e alinhamento de texto;
- ação para restaurar a aparência padrão.

Galerias agora mostram uma prévia com as imagens filhas e o número de colunas escolhido. As personalizações da galeria e das imagens continuam disponíveis de forma independente.

## Persistência e segurança

Os estilos são gravados no documento BDRE como `attributes.appearance`, preservados no histórico normal e entregues pelos fluxos independentes de Pages e Blog. O validador JS e o `BlockDocument` servidor permitem somente propriedades enumeradas, cores hexadecimais e valores numéricos/enum dentro de limites. O renderer público gera CSS inline somente a partir desses valores validados; não aceita CSS livre, HTML arbitrário ou execução de scripts. O botão aplica os estilos à âncora clicável; itens de lista, linhas/células de tabela e colunas recebem seus estilos através dos respectivos contêineres.

O painel não salva automaticamente: continua necessário usar o botão de salvamento existente no formulário.

## Verificações e limites

- `node --check` nos JavaScripts do editor.
- Validação unitária isolada de uma aparência completa e rejeição de cores CSS, URLs, chaves desconhecidas, valores fora do intervalo e appearance em formato legado v1.
- Verificações estáticas de guias, controles, traduções PT-BR/EN-US/ES-EU, controladores Page/Blog, allowlist do servidor e aplicação no renderer público.
- O CLI PHP não está instalado; portanto `BlockDocument` e `BlockRenderer` não foram lintados nem executados. Também não houve validação visual no navegador, banco de dados, staging ou E2E.

## Observação de escopo

A aparência é comum e contextual para todos os tipos de bloco. Os controles de conteúdo específicos existentes (por exemplo, URL do botão, mídia/caption da imagem, colunas da galeria, ordem de lista, campos de mídia) permanecem na guia Conteúdo. Este marco não substitui a validação real no ambiente de publicação nem uma auditoria visual dos estilos do tema.
