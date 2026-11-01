/*
 * ReamurCMS native Blog block editor — F9-14 base + F9-22 inline text + F9-23 block UX.
 * In-memory model; form submission binding stays in blog-block-editor-init.js.
 * Mount with ReamurBlogBlockEditor.mount(element, { document, onChange }).
 */
(function (global) {
  'use strict';

  const TYPES = [
    ['core/paragraph', 'Parágrafo'], ['core/heading', 'Título'], ['core/list', 'Lista'],
    ['core/image', 'Imagem'], ['core/quote', 'Citação'], ['core/video_embed', 'Vídeo'], ['core/video_file', 'Vídeo local'], ['core/threejs_thumb', 'Thumb 3D (Three.js)'],
    ['core/button', 'Botão'], ['core/callout', 'Aviso'], ['core/details', 'Detalhes'], ['core/spacer', 'Espaçador'], ['core/code', 'Código pré-formatado'], ['core/separator', 'Separador'], ['core/group', 'Grupo'],
    ['core/columns', 'Colunas'], ['core/gallery', 'Galeria'], ['core/cover', 'Capa'], ['core/table', 'Tabela'], ['core/file', 'Arquivo'], ['core/audio', 'Áudio']
  ];
  const TEXT_TYPES = new Set(['core/paragraph', 'core/heading', 'core/list-item', 'core/quote', 'core/button', 'core/callout', 'core/details', 'core/file', 'core/audio']);
  const BLOCK_CATEGORIES = { 'core/paragraph':'content', 'core/heading':'content', 'core/list':'content', 'core/quote':'content', 'core/button':'content', 'core/callout':'content', 'core/details':'content', 'core/spacer':'layout', 'core/code':'content', 'core/image':'media', 'core/video_embed':'media', 'core/gallery':'media', 'core/cover':'media', 'core/separator':'layout', 'core/group':'layout', 'core/columns':'layout', 'core/table':'content', 'core/file':'media', 'core/audio':'media', 'core/video_file':'media', 'core/threejs_thumb':'media' };
  const RIBBON_BLOCK_TYPES = { insert:['core/paragraph','core/heading','core/list','core/quote','core/button','core/callout','core/details','core/code'], media:['core/image','core/video_embed','core/video_file','core/threejs_thumb','core/gallery','core/file','core/audio'], layout:['core/spacer','core/separator','core/group','core/columns','core/cover','core/table'] };
  const PATTERN_TOOL_DESCRIPTIONS = {pt:{two_columns:'Insere uma seção com duas colunas de conteúdo.',quote_cta:'Insere citação acompanhada de botão de chamada.',section_intro:'Insere título, introdução e separador.',cover_hero:'Insere capa com título, descrição e botão.'},en:{two_columns:'Insert a two-column content section.',quote_cta:'Insert a quote with a call-to-action button.',section_intro:'Insert a heading, introduction and divider.',cover_hero:'Insert a cover with heading, description and button.'},es:{two_columns:'Inserta una sección de contenido en dos columnas.',quote_cta:'Inserta una cita con un botón de llamada a la acción.',section_intro:'Inserta título, introducción y separador.',cover_hero:'Inserta una portada con título, descripción y botón.'}};
  function paragraphInspectorCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {paragraphTextTitle:'Texto del párrafo',paragraphTextHint:'Edita aquí o directamente en el lienzo. Conserva los formatos en línea existentes.',paragraphAlignment:'Alineación del párrafo',paragraphStyleHeading:'Estilo del párrafo',headingTextTitle:'Texto del encabezado',headingTextHint:'Edita el texto aquí o directamente en el lienzo.',headingStyleHeading:'Estilo del encabezado',headingLevelLabel:'Nivel del encabezado',headingLevels:[['1','H1 · Encabezado 1'],['2','H2 · Encabezado 2'],['3','H3 · Encabezado 3'],['4','H4 · Encabezado 4'],['5','H5 · Encabezado 5'],['6','H6 · Encabezado 6']],listItemsTitle:'Elementos de la lista',listItemTextTitle:'Texto del elemento',listItemTextHint:'Edita aquí o directamente en el lienzo.',listItemSingular:'Elemento %1',listType:'Tipo de lista',listUnordered:'Viñetas',listOrdered:'Numerada',listAddItem:'Añadir elemento',listRemoveItem:'Quitar elemento',listActions:'Acciones de la lista',listStyleHeading:'Estilo de la lista',listItemStyleHeading:'Estilo del elemento',quoteTextTitle:'Texto de la cita',quoteTextHint:'Edita aquí o directamente en el lienzo. Se conserva el formato en línea.',quoteStyleHeading:'Estilo de la cita',quoteActions:'Acciones de la cita',buttonTextTitle:'Texto del botón',buttonTextHint:'Edita la etiqueta aquí o directamente en el lienzo. Se conserva el formato en línea.',buttonDestination:'Destino del botón (URL o ruta local)',buttonStyleHeading:'Estilo del botón',buttonActions:'Acciones del botón',calloutTextTitle:'Texto del aviso',calloutTextHint:'Edita el contenido aquí o directamente en el lienzo. Se conserva el formato en línea.',calloutStyleHeading:'Estilo del aviso',calloutActions:'Acciones del aviso',detailsTextTitle:'Contenido desplegable',detailsTextHint:'Edita el contenido que se muestra al expandir la sección, aquí o directamente en el lienzo.',detailsStyleHeading:'Estilo de la sección informativa',detailsActions:'Acciones de la sección informativa',preformattedTextTitle:'Código preformateado',preformattedTextHint:'El contenido se trata como texto literal; no se ejecuta.',preformattedStyleHeading:'Estilo del código preformateado',preformattedActions:'Acciones del código preformateado',tableContentTitle:'Contenido de la tabla',tableContentHint:'Edita cada celda aquí o directamente en el canvas; los cambios se sincronizan.',tableStyleHeading:'Estilo de la tabla',tableActions:'Acciones de la tabla',tableMergeCells:'Combinar celdas seleccionadas',tableUnmergeCells:'Separar celdas combinadas',tableMarkHeader:'Definir como encabezado',tableMarkNormal:'Definir como contenido normal',tableSelectCell:'Seleccionar celda',tableSelectionHint:'Selecciona un grupo rectangular de celdas para combinarlas o elegir el formato de encabezado.',paragraphActions:'Acciones del bloque'};
    if(language.startsWith('en'))return {paragraphTextTitle:'Paragraph text',paragraphTextHint:'Edit here or directly on the canvas. Existing inline formatting is preserved.',paragraphAlignment:'Paragraph alignment',paragraphStyleHeading:'Paragraph style',headingTextTitle:'Heading text',headingTextHint:'Edit the text here or directly on the canvas.',headingStyleHeading:'Heading style',headingLevelLabel:'Heading level',headingLevels:[['1','H1 · Heading 1'],['2','H2 · Heading 2'],['3','H3 · Heading 3'],['4','H4 · Heading 4'],['5','H5 · Heading 5'],['6','H6 · Heading 6']],listItemsTitle:'List items',listItemTextTitle:'List item text',listItemTextHint:'Edit here or directly on the canvas.',listItemSingular:'Item %1',listType:'List type',listUnordered:'Bulleted',listOrdered:'Numbered',listAddItem:'Add item',listRemoveItem:'Remove item',listActions:'List actions',listStyleHeading:'List style',listItemStyleHeading:'List item style',quoteTextTitle:'Quote text',quoteTextHint:'Edit here or directly on the canvas. Inline formatting is preserved.',quoteStyleHeading:'Quote style',quoteActions:'Quote actions',buttonTextTitle:'Button text',buttonTextHint:'Edit the label here or directly on the canvas. Inline formatting is preserved.',buttonDestination:'Button destination (URL or local path)',buttonStyleHeading:'Button style',buttonActions:'Button actions',calloutTextTitle:'Notice text',calloutTextHint:'Edit the content here or directly on the canvas. Inline formatting is preserved.',calloutStyleHeading:'Notice style',calloutActions:'Notice actions',detailsTextTitle:'Expandable content',detailsTextHint:'Edit the content shown when the section is expanded, here or directly on the canvas.',detailsStyleHeading:'Information section style',detailsActions:'Information section actions',preformattedTextTitle:'Preformatted code',preformattedTextHint:'Content is treated as literal text and is never executed.',preformattedStyleHeading:'Preformatted code style',preformattedActions:'Preformatted code actions',tableContentTitle:'Table content',tableContentHint:'Edit each cell here or directly on the canvas; changes stay synchronized.',tableStyleHeading:'Table style',tableActions:'Table actions',tableMergeCells:'Merge selected cells',tableUnmergeCells:'Unmerge cells',tableMarkHeader:'Set as header',tableMarkNormal:'Set as normal content',tableSelectCell:'Select cell',tableSelectionHint:'Select a rectangular group of cells to merge or set their header format.',paragraphActions:'Block actions'};
    return {paragraphTextTitle:'Texto do parágrafo',paragraphTextHint:'Edite aqui ou diretamente no canvas. As formatações inline existentes serão preservadas.',paragraphAlignment:'Alinhamento do parágrafo',paragraphStyleHeading:'Estilo do parágrafo',headingTextTitle:'Texto do título',headingTextHint:'Edite o texto aqui ou diretamente no canvas.',headingStyleHeading:'Estilo do título',headingLevelLabel:'Nível do título',headingLevels:[['1','H1 · Título 1'],['2','H2 · Título 2'],['3','H3 · Título 3'],['4','H4 · Título 4'],['5','H5 · Título 5'],['6','H6 · Título 6']],listItemsTitle:'Itens da lista',listItemTextTitle:'Texto do item',listItemTextHint:'Edite aqui ou diretamente no canvas.',listItemSingular:'Item %1',listType:'Tipo de lista',listUnordered:'Marcadores',listOrdered:'Numerada',listAddItem:'Adicionar item',listRemoveItem:'Remover item',listActions:'Ações da lista',listStyleHeading:'Estilo da lista',listItemStyleHeading:'Estilo do item',quoteTextTitle:'Texto da citação',quoteTextHint:'Edite aqui ou diretamente no canvas. A formatação inline será preservada.',quoteStyleHeading:'Estilo da citação',quoteActions:'Ações da citação',buttonTextTitle:'Texto do botão',buttonTextHint:'Edite o rótulo aqui ou diretamente no canvas. A formatação inline será preservada.',buttonDestination:'Destino do botão (URL ou caminho local)',buttonStyleHeading:'Estilo do botão',buttonActions:'Ações do botão',calloutTextTitle:'Texto do aviso',calloutTextHint:'Edite o conteúdo aqui ou diretamente no canvas. A formatação inline será preservada.',calloutStyleHeading:'Estilo do aviso',calloutActions:'Ações do aviso',detailsTextTitle:'Conteúdo expansível',detailsTextHint:'Edite o conteúdo exibido ao expandir a seção, aqui ou diretamente no canvas.',detailsStyleHeading:'Estilo das informações',detailsActions:'Ações das informações',preformattedTextTitle:'Código pré-formatado',preformattedTextHint:'O conteúdo é tratado como texto literal e não é executado.',preformattedStyleHeading:'Estilo do código pré-formatado',preformattedActions:'Ações do código pré-formatado',tableContentTitle:'Conteúdo da tabela',tableContentHint:'Edite cada célula aqui ou diretamente no canvas; as alterações são sincronizadas.',tableStyleHeading:'Estilo da tabela',tableActions:'Ações da tabela',tableMergeCells:'Mesclar células selecionadas',tableUnmergeCells:'Desmesclar células',tableMarkHeader:'Definir como cabeçalho',tableMarkNormal:'Definir como conteúdo normal',tableSelectCell:'Selecionar célula',tableSelectionHint:'Selecione células em retângulo para mesclar ou definir o formato do cabeçalho.',paragraphActions:'Ações do bloco'};
  }

  function imageInspectorCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {imageContentTitle:'Contenido de la imagen',imageContentHint:'Describe la imagen para lectores de pantalla; si es decorativa, deja vacío el texto alternativo. También puedes añadir título y leyenda.',imageStyleHeading:'Estilo de imagen',imageReplace:'Seleccionar o cambiar imagen',coverImageTitle:'Imagen de portada',coverImageHint:'Elige una imagen de la biblioteca Pages; edita el contenido en los bloques internos.',pageMediaUnavailable:'La biblioteca de medios de Pages no se cargó. Recarga la página o contacta al administrador.'};
    if(language.startsWith('en'))return {imageContentTitle:'Image content',imageContentHint:'Describe the image for screen readers; leave alt text empty if it is decorative. You can also add a title and caption.',imageStyleHeading:'Image style',imageReplace:'Choose or replace image',coverImageTitle:'Cover image',coverImageHint:'Choose an image from the Pages library; edit cover content in its inner blocks.',pageMediaUnavailable:'The Pages media library did not load. Reload the page or contact an administrator.'};
    return {imageContentTitle:'Conteúdo da imagem',imageContentHint:'Descreva a imagem para leitores de tela; deixe o texto alternativo vazio se ela for decorativa. Você também pode incluir título e legenda.',imageStyleHeading:'Estilo da imagem',imageReplace:'Selecionar ou trocar imagem',coverImageTitle:'Imagem de capa',coverImageHint:'Escolha uma imagem na biblioteca Pages; edite o conteúdo da capa nos blocos internos.',pageMediaUnavailable:'A biblioteca de mídia do Pages não carregou. Recarregue a página ou contate o administrador.'};
  }

  function videoInspectorCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {contentTitle:'Contenido del vídeo',hint:'Selecciona el archivo y configura los controles de reproducción y la presentación.',source:'Archivo de vídeo',choose:'Seleccionar o cambiar vídeo',titleLabel:'Título del vídeo',caption:'Leyenda del vídeo',alignment:'Alineación del vídeo',size:'Ancho',mobileSize:'Ancho en móvil',actions:'Acciones del bloque de vídeo',yes:'Sí',no:'No',controls:'Mostrar controles',loop:'Repetir',muted:'Iniciar silenciado',autoplay:'Reproducción automática',alignments:{left:'Izquierda',center:'Centro',right:'Derecha',wide:'Ancho',full:'Ancho completo'},sizes:{small:'Pequeño',medium:'Mediano',large:'Grande',full:'Ancho completo'},mobileSizes:{full:'Ancho completo',large:'Grande',medium:'Mediano'}};
    if(language.startsWith('en'))return {contentTitle:'Video content',hint:'Choose the video file and configure playback controls and presentation.',source:'Video file',choose:'Choose or replace video',titleLabel:'Video title',caption:'Video caption',alignment:'Video alignment',size:'Width',mobileSize:'Mobile width',actions:'Video block actions',yes:'Yes',no:'No',controls:'Show controls',loop:'Loop playback',muted:'Start muted',autoplay:'Autoplay',alignments:{left:'Left',center:'Center',right:'Right',wide:'Wide',full:'Full width'},sizes:{small:'Small',medium:'Medium',large:'Large',full:'Full width'},mobileSizes:{full:'Full width',large:'Large',medium:'Medium'}};
    return {contentTitle:'Conteúdo do vídeo',hint:'Escolha o arquivo e configure os controles de reprodução e a apresentação.',source:'Arquivo de vídeo',choose:'Selecionar ou trocar vídeo',titleLabel:'Título do vídeo',caption:'Legenda do vídeo',alignment:'Alinhamento do vídeo',size:'Largura',mobileSize:'Largura no celular',actions:'Ações do bloco de vídeo',yes:'Sim',no:'Não',controls:'Exibir controles',loop:'Repetir reprodução',muted:'Iniciar sem áudio',autoplay:'Reprodução automática',alignments:{left:'Esquerda',center:'Centro',right:'Direita',wide:'Largo',full:'Largura total'},sizes:{small:'Pequeno',medium:'Médio',large:'Grande',full:'Largura total'},mobileSizes:{full:'Largura total',large:'Grande',medium:'Médio'}};
  }

  function audioInspectorCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {contentTitle:'Contenido de audio',hint:'Elige o cambia el audio de la biblioteca Pages. Edita la etiqueta del enlace en el bloque.',source:'Archivo de audio',choose:'Seleccionar o cambiar audio',label:'Etiqueta del audio',labelHint:'Texto del enlace mostrado junto al reproductor.',actions:'Acciones del bloque de audio'};
    if(language.startsWith('en'))return {contentTitle:'Audio content',hint:'Choose or replace audio from the Pages library. Edit the link label in the block.',source:'Audio file',choose:'Choose or replace audio',label:'Audio label',labelHint:'Link text shown alongside the player.',actions:'Audio block actions'};
    return {contentTitle:'Conteúdo do áudio',hint:'Escolha ou troque o áudio na biblioteca Pages. Edite o rótulo do link no próprio bloco.',source:'Arquivo de áudio',choose:'Selecionar ou trocar áudio',label:'Rótulo do áudio',labelHint:'Texto do link exibido junto ao player.',actions:'Ações do bloco de áudio'};
  }

  function spacerInspectorCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {title:'Espaciador vertical',hint:'Ajusta la altura con valores predefinidos. Usa los botones de la barra para aumentar o reducir rápidamente.',height:'Altura del espaciador',value:'Altura actual: %1 px',decrease:'Reducir altura',increase:'Aumentar altura'};
    if(language.startsWith('en'))return {title:'Vertical spacer',hint:'Adjust the height using preset values. Use the toolbar buttons for quick changes.',height:'Spacer height',value:'Current height: %1 px',decrease:'Decrease height',increase:'Increase height'};
    return {title:'Espaçador vertical',hint:'Ajuste a altura com valores predefinidos. Use os botões da barra para aumentar ou reduzir rapidamente.',height:'Altura do espaçador',value:'Altura atual: %1 px',decrease:'Diminuir altura',increase:'Aumentar altura'};
  }
  const SPACER_HEIGHTS=[16,32,48,64,96];

  function separatorInspectorCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {title:'Estilo del separador',hint:'Configura el trazo, el grosor, el ancho y la alineación.',lineStyle:'Estilo de línea',thickness:'Grosor',width:'Ancho',alignment:'Alineación',widthSmaller:'Reducir ancho',widthLarger:'Aumentar ancho',styles:{solid:'Continua',dashed:'Discontinua',dotted:'Punteada'},widths:{'25':'25 %','50':'50 %','75':'75 %','100':'100 %'},thicknesses:{'1':'Fino · 1 px','2':'Medio · 2 px','3':'Grueso · 3 px','4':'Muy grueso · 4 px'},alignments:{left:'Izquierda',center:'Centro',right:'Derecha'}};
    if(language.startsWith('en'))return {title:'Separator style',hint:'Configure the stroke, thickness, width and alignment.',lineStyle:'Line style',thickness:'Thickness',width:'Width',alignment:'Alignment',widthSmaller:'Decrease width',widthLarger:'Increase width',styles:{solid:'Solid',dashed:'Dashed',dotted:'Dotted'},widths:{'25':'25%','50':'50%','75':'75%','100':'100%'},thicknesses:{'1':'Thin · 1 px','2':'Medium · 2 px','3':'Thick · 3 px','4':'Extra thick · 4 px'},alignments:{left:'Left',center:'Center',right:'Right'}};
    return {title:'Estilo do separador',hint:'Configure o traço, a espessura, a largura e o alinhamento.',lineStyle:'Estilo da linha',thickness:'Espessura',width:'Largura',alignment:'Alinhamento',widthSmaller:'Diminuir largura',widthLarger:'Aumentar largura',styles:{solid:'Contínua',dashed:'Tracejada',dotted:'Pontilhada'},widths:{'25':'25%','50':'50%','75':'75%','100':'100%'},thicknesses:{'1':'Fina · 1 px','2':'Média · 2 px','3':'Grossa · 3 px','4':'Extra grossa · 4 px'},alignments:{left:'Esquerda',center:'Centro',right:'Direita'}};
  }
  const SEPARATOR_WIDTHS=[25,50,75,100];
  const SEPARATOR_STYLES=['solid','dashed','dotted'];
  const SEPARATOR_THICKNESSES=[1,2,3,4];

  function columnInspectorCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {title:'Contenido de la columna',addChild:'Añadir bloque a esta columna',position:'Columna %1 de %2 · bloques: %3'};
    if(language.startsWith('en'))return {title:'Column content',addChild:'Add block inside this column',position:'Column %1 of %2 · blocks: %3'};
    return {title:'Conteúdo da coluna',addChild:'Adicionar bloco a esta coluna',position:'Coluna %1 de %2 · blocos: %3'};
  }

  function groupInspectorCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {title:'Contenido del grupo',hint:'Inserta y organiza bloques dentro de este grupo. El nombre opcional solo se usa en el navegador de estructura.',addChild:'Añadir bloque al grupo',childCount:'Bloques dentro del grupo: %1'};
    if(language.startsWith('en'))return {title:'Group content',hint:'Insert and arrange blocks inside this group. The optional name is used in the document outline.',addChild:'Add block inside group',childCount:'Blocks inside group: %1'};
    return {title:'Conteúdo do grupo',hint:'Insira e organize blocos dentro deste grupo. O nome opcional aparece no navegador da estrutura.',addChild:'Adicionar bloco ao grupo',childCount:'Blocos dentro do grupo: %1'};
  }

  const TRANSFORMABLE_TEXT = new Set(['core/paragraph', 'core/heading', 'core/quote', 'core/button']);
  const CLONE = (value) => JSON.parse(JSON.stringify(value));
  let instanceSequence = 0;
  let toolDescriptionSequence = 0;
  function fullscreenCopy() {
    const language = String(global.document && global.document.documentElement && global.document.documentElement.lang || 'en').toLowerCase();
    if (language.startsWith('pt')) return { enter:'Abrir em tela cheia', exit:'Voltar ao formulário', dialog:'Editor em tela cheia', elements:'Elementos', structure:'Navegador', settings:'Configurações' };
    if (language.startsWith('es')) return { enter:'Abrir a pantalla completa', exit:'Volver al formulario', dialog:'Editor a pantalla completa', elements:'Elementos', structure:'Estructura', settings:'Ajustes' };
    return { enter:'Open fullscreen editor', exit:'Back to form', dialog:'Fullscreen editor', elements:'Elements', structure:'Structure', settings:'Settings' };
  }
  function workspaceCopy() {
    const language = String(global.document && global.document.documentElement && global.document.documentElement.lang || 'en').toLowerCase();
    if (language.startsWith('pt')) return {
      home:'Início', models:'Modelos', modelsBack:'Voltar ao editor', library:'Biblioteca', assistant:'Assistente IA', export:'Exportar', share:'Compartilhar',
      file:'Arquivo', edit:'Editar', insert:'Inserir', format:'Formatar', tools:'Ferramentas', help:'Ajuda',
      modelsTitle:'Modelos de conteúdo e padrões', modelsHint:'Insira composições editáveis em blocos nativos sem substituir o conteúdo existente.', patternModels:'Modelos',
      libraryTitle:'Biblioteca de mídia', libraryHint:'A biblioteca oficial abre pelo seletor do bloco selecionado. Escolha o tipo correspondente abaixo.',
      selectBlock:'Selecione primeiro um bloco compatível no documento para associar mídia.', noPicker:'O seletor de mídia não está habilitado nesta tela.',
      assistantTitle:'Assistente de IA', assistantHint:'A integração de IA não está configurada nesta instalação. Nenhum conteúdo será enviado.', assistantPrompt:'Digite sua solicitação…',
      assistantActions:['Resumir documento','Reescrever seleção','Traduzir conteúdo','Gerar ideias','Criar imagem'],
      exportTitle:'Exportar e compartilhar', exportHint:'O salvamento do site continua no formulário existente. Exporte uma cópia estrutural BDRE em JSON ou uma versão textual em Markdown; nada será salvo no site.',
      jsonTitle:'Documento BDRE · JSON', jsonHint:'Baixa uma cópia estrutural para backup ou transferência.', unavailable:'Não disponível nesta integração',
      pdf:'PDF', docx:'Word (.docx)', pptx:'PowerPoint (.pptx)', imageFormat:'Imagem (PNG/JPG)', html:'HTML',
      shareTitle:'Compartilhamento', shareHint:'Links, permissões e colaboração precisam de um serviço próprio. Nenhum link será criado por este editor.',
      shareDisabled:'Compartilhamento não configurado', patternsSearch:'Buscar modelos e padrões…', patternAll:'Todos', patternQuick:'Rápidos', patternSaved:'Salvos',
      noPatternMatches:'Nenhum modelo ou padrão corresponde à busca.', helpTitle:'Ajuda e atalhos', helpHint:'Atalhos disponíveis neste editor', imageType:'Imagem', fileType:'Arquivo', audioType:'Áudio', videoType:'Vídeo',
      insertMedia:'Abrir biblioteca', documentStats:'%1 palavras · %2 caracteres · %3 blocos'
    };
    if (language.startsWith('es')) return {
      home:'Inicio', models:'Modelos', modelsBack:'Volver al editor', library:'Biblioteca', assistant:'Asistente IA', export:'Exportar', share:'Compartir',
      file:'Archivo', edit:'Editar', insert:'Insertar', format:'Formato', tools:'Herramientas', help:'Ayuda',
      modelsTitle:'Modelos de contenido y patrones', modelsHint:'Inserta composiciones editables en bloques nativos sin reemplazar el contenido existente.', patternModels:'Modelos',
      libraryTitle:'Biblioteca multimedia', libraryHint:'La biblioteca oficial se abre desde el selector del bloque seleccionado. Elige el tipo correspondiente.',
      selectBlock:'Selecciona primero un bloque compatible en el documento para asociar medios.', noPicker:'El selector multimedia no está habilitado en esta pantalla.',
      assistantTitle:'Asistente de IA', assistantHint:'La integración de IA no está configurada en esta instalación. No se enviará contenido.', assistantPrompt:'Escribe tu solicitud…',
      assistantActions:['Resumir documento','Reescribir selección','Traducir contenido','Generar ideas','Crear imagen'],
      exportTitle:'Exportar y compartir', exportHint:'El guardado del sitio continúa en el formulario existente. Exporta una copia estructural BDRE en JSON o una versión textual en Markdown; no se guardará en el sitio.',
      jsonTitle:'Documento BDRE · JSON', jsonHint:'Descarga una copia estructural para respaldo o transferencia.', unavailable:'No disponible en esta integración',
      pdf:'PDF', docx:'Word (.docx)', pptx:'PowerPoint (.pptx)', imageFormat:'Imagen (PNG/JPG)', html:'HTML',
      shareTitle:'Compartir', shareHint:'Los enlaces, permisos y colaboración requieren un servicio propio. Este editor no creará ningún enlace.',
      shareDisabled:'Compartir no está configurado', patternsSearch:'Buscar modelos y patrones…', patternAll:'Todos', patternQuick:'Rápidos', patternSaved:'Guardados',
      noPatternMatches:'Ningún modelo o patrón coincide con la búsqueda.', helpTitle:'Ayuda y atajos', helpHint:'Atajos disponibles en este editor', imageType:'Imagen', fileType:'Archivo', audioType:'Audio', videoType:'Vídeo',
      insertMedia:'Abrir biblioteca', documentStats:'%1 palabras · %2 caracteres · %3 bloques'
    };
    return {
      home:'Home', models:'Models', modelsBack:'Back to editor', library:'Media library', assistant:'AI assistant', export:'Export', share:'Share',
      file:'File', edit:'Edit', insert:'Insert', format:'Format', tools:'Tools', help:'Help',
      modelsTitle:'Content models and patterns', modelsHint:'Insert editable native-block compositions without replacing existing content.', patternModels:'Models',
      libraryTitle:'Media library', libraryHint:'The CMS library opens through the selected block’s picker. Choose the matching media type below.',
      selectBlock:'Select a compatible block in the document first to attach media.', noPicker:'The media picker is not enabled on this screen.',
      assistantTitle:'AI assistant', assistantHint:'AI is not configured for this installation. No document content will be sent.', assistantPrompt:'Type a request…',
      assistantActions:['Summarize document','Rewrite selection','Translate content','Generate ideas','Create image'],
      exportTitle:'Export and share', exportHint:'Site saving remains in the existing form. Export a structural BDRE JSON copy or a text-focused Markdown version; nothing is saved to the site.',
      jsonTitle:'BDRE document · JSON', jsonHint:'Download a structural copy for backup or transfer.', unavailable:'Unavailable in this integration',
      pdf:'PDF', docx:'Word (.docx)', pptx:'PowerPoint (.pptx)', imageFormat:'Image (PNG/JPG)', html:'HTML',
      shareTitle:'Sharing', shareHint:'Links, permissions and collaboration require a dedicated service. This editor will not create a link.',
      shareDisabled:'Sharing is not configured', patternsSearch:'Search models and patterns…', patternAll:'All', patternQuick:'Quick', patternSaved:'Saved',
      noPatternMatches:'No models or patterns match this search.', helpTitle:'Help and shortcuts', helpHint:'Shortcuts available in this editor', imageType:'Image', fileType:'File', audioType:'Audio', videoType:'Video',
      insertMedia:'Open library', documentStats:'%1 words · %2 characters · %3 blocks'
    };
  }
  function accessibilityAuditCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {
      tool:'Accesibilidad',title:'Revisión de accesibilidad',hint:'Revisa el contenido del documento. Los resultados son orientativos y no modifican ningún bloque.',note:'Esta revisión automática no es una certificación WCAG. Comprueba el contexto y decide si aplicar cambios.',back:'Volver al editor',noIssues:'No se encontraron alertas en las comprobaciones disponibles.',count:'Hallazgos: %1',invalid:'No se puede revisar un documento que no supera la validación BDRE.',openBlock:'Localizar bloque',warning:'Revisar',info:'Sugerencia',
      issues:{empty_heading:{severity:'warning',title:'Encabezado sin texto',detail:'El encabezado no tiene texto visible.',suggestion:'Añade un título o elimina el bloque vacío.'},heading_order:{severity:'warning',title:'Salto en la jerarquía de encabezados',detail:'El nivel cambia de H%1 a H%2.',suggestion:'Considera usar niveles consecutivos para mantener una estructura clara.'},empty_paragraph:{severity:'info',title:'Párrafo vacío',detail:'Este párrafo no contiene texto visible.',suggestion:'Si solo necesitas espacio visual, considera usar el bloque Espaciador.'},image_alt:{severity:'info',title:'Texto alternativo vacío',detail:'El texto alternativo está vacío; puede ser intencional si la imagen es decorativa.',suggestion:'Confirma si la imagen es decorativa; si aporta significado, describe su propósito.'},button_empty:{severity:'warning',title:'Botón sin etiqueta',detail:'El botón no tiene un nombre visible.',suggestion:'Añade un texto que describa el destino o la acción.'},button_generic:{severity:'info',title:'Etiqueta de botón poco específica',detail:'La etiqueta “%1” puede no explicar el destino fuera de contexto.',suggestion:'Considera un texto más descriptivo si corresponde.'},table_headers:{severity:'info',title:'Tabla sin encabezados marcados',detail:'No hay celdas identificadas como encabezados.',suggestion:'Marca como encabezado las celdas que describan filas o columnas, si corresponde.'}}
    };
    if(language.startsWith('pt'))return {
      tool:'Acessibilidade',title:'Revisão de acessibilidade',hint:'Revise o conteúdo do documento. Os apontamentos são orientativos e não modificam nenhum bloco.',note:'Esta revisão automática não é uma certificação WCAG. Confira o contexto e decida se deseja fazer alterações.',back:'Voltar ao editor',noIssues:'Nenhum alerta foi encontrado nas verificações disponíveis.',count:'Apontamentos: %1',invalid:'Não é possível revisar um documento que não passou na validação BDRE.',openBlock:'Localizar bloco',warning:'Revisar',info:'Sugestão',
      issues:{empty_heading:{severity:'warning',title:'Título sem texto',detail:'Este título não tem texto visível.',suggestion:'Adicione um título ou remova o bloco vazio.'},heading_order:{severity:'warning',title:'Salto na hierarquia de títulos',detail:'O nível muda de H%1 para H%2.',suggestion:'Considere usar níveis consecutivos para manter uma estrutura clara.'},empty_paragraph:{severity:'info',title:'Parágrafo vazio',detail:'Este parágrafo não contém texto visível.',suggestion:'Se precisa apenas de espaço visual, considere usar o bloco Espaçador.'},image_alt:{severity:'info',title:'Texto alternativo vazio',detail:'O texto alternativo está vazio; isso pode ser intencional se a imagem for decorativa.',suggestion:'Confirme se a imagem é decorativa; se transmite significado, descreva sua finalidade.'},button_empty:{severity:'warning',title:'Botão sem rótulo',detail:'O botão não tem um nome visível.',suggestion:'Adicione um texto que descreva o destino ou a ação.'},button_generic:{severity:'info',title:'Rótulo de botão pouco específico',detail:'O rótulo “%1” talvez não explique o destino fora de contexto.',suggestion:'Considere um texto mais descritivo, se for adequado.'},table_headers:{severity:'info',title:'Tabela sem cabeçalhos marcados',detail:'Não há células identificadas como cabeçalhos.',suggestion:'Marque como cabeçalho as células que descrevem linhas ou colunas, se aplicável.'}}
    };
    return {
      tool:'Accessibility',title:'Accessibility review',hint:'Review document content. Findings are advisory and do not modify any blocks.',note:'This automated review is not WCAG certification. Check the context and decide whether to make changes.',back:'Back to editor',noIssues:'No findings in the available checks.',count:'Findings: %1',invalid:'A document that fails BDRE validation cannot be reviewed.',openBlock:'Locate block',warning:'Review',info:'Suggestion',
      issues:{empty_heading:{severity:'warning',title:'Heading has no text',detail:'This heading has no visible text.',suggestion:'Add a heading or remove the empty block.'},heading_order:{severity:'warning',title:'Heading hierarchy skip',detail:'The level changes from H%1 to H%2.',suggestion:'Consider using consecutive levels for a clearer structure.'},empty_paragraph:{severity:'info',title:'Empty paragraph',detail:'This paragraph contains no visible text.',suggestion:'If you only need visual space, consider using the Spacer block.'},image_alt:{severity:'info',title:'Empty alternative text',detail:'Alternative text is empty; this may be intentional for a decorative image.',suggestion:'Confirm whether the image is decorative; if meaningful, describe its purpose.'},button_empty:{severity:'warning',title:'Button has no label',detail:'The button has no visible name.',suggestion:'Add text describing its destination or action.'},button_generic:{severity:'info',title:'Button label may be vague',detail:'The label “%1” may not explain the destination out of context.',suggestion:'Consider more descriptive wording where appropriate.'},table_headers:{severity:'info',title:'No table headers marked',detail:'No cells are identified as headers.',suggestion:'Mark cells that label rows or columns as headers when appropriate.'}}
    };
  }


  function linkReviewCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {
      tool:'Enlaces',title:'Revisión de enlaces y destinos',hint:'Comprueba los destinos de enlaces del documento sin cambiar el contenido.',note:'Esta revisión no visita sitios web ni comprueba si una dirección responde. Los resultados son orientativos y no modifican bloques.',back:'Volver al editor',noIssues:'No se encontraron alertas en las comprobaciones disponibles.',count:'Hallazgos: %1',invalid:'No se puede revisar un documento que no supera la validación BDRE.',openBlock:'Localizar bloque',warning:'Revisar',info:'Sugerencia',
      issues:{http_external:{severity:'warning',title:'Enlace externo usa HTTP',detail:'El destino externo usa una conexión HTTP sin cifrar: %1',suggestion:'Si el sitio admite HTTPS, considera actualizar el destino después de comprobarlo.'},raw_url_label:{severity:'info',title:'El texto del enlace es una URL',detail:'El texto visible repite la dirección: %1',suggestion:'Considera un texto breve que describa el destino, si encaja con el contexto.'},repeated_destination:{severity:'info',title:'Destino repetido',detail:'Este mismo destino aparece %1 veces en el documento.',suggestion:'Confirma que las repeticiones sean intencionales.'}}
    };
    if(language.startsWith('pt'))return {
      tool:'Links',title:'Revisão de links e destinos',hint:'Confira os destinos dos links no documento sem alterar o conteúdo.',note:'Esta revisão não acessa sites nem verifica se um endereço responde. Os resultados são orientativos e não modificam blocos.',back:'Voltar ao editor',noIssues:'Nenhum alerta foi encontrado nas verificações disponíveis.',count:'Apontamentos: %1',invalid:'Não é possível revisar um documento que não passou na validação BDRE.',openBlock:'Localizar bloco',warning:'Revisar',info:'Sugestão',
      issues:{http_external:{severity:'warning',title:'Link externo usa HTTP',detail:'O destino externo usa uma conexão HTTP sem criptografia: %1',suggestion:'Se o site oferecer HTTPS, considere atualizar o destino depois de conferi-lo.'},raw_url_label:{severity:'info',title:'O texto do link é uma URL',detail:'O texto visível repete o endereço: %1',suggestion:'Considere um texto curto que descreva o destino, se fizer sentido no contexto.'},repeated_destination:{severity:'info',title:'Destino repetido',detail:'Este mesmo destino aparece %1 vezes no documento.',suggestion:'Confirme se as repetições são intencionais.'}}
    };
    return {
      tool:'Links',title:'Link and destination review',hint:'Review link destinations in this document without changing its content.',note:'This review does not visit websites or check whether an address responds. Findings are advisory and never modify blocks.',back:'Back to editor',noIssues:'No findings in the available checks.',count:'Findings: %1',invalid:'A document that fails BDRE validation cannot be reviewed.',openBlock:'Locate block',warning:'Review',info:'Suggestion',
      issues:{http_external:{severity:'warning',title:'External link uses HTTP',detail:'This external destination uses an unencrypted HTTP connection: %1',suggestion:'If the site supports HTTPS, consider updating the destination after checking it.'},raw_url_label:{severity:'info',title:'Link text is a URL',detail:'The visible text repeats the address: %1',suggestion:'Consider concise text that describes the destination, if it fits the context.'},repeated_destination:{severity:'info',title:'Repeated destination',detail:'This same destination appears %1 times in the document.',suggestion:'Confirm the repetitions are intentional.'}}
    };
  }


  function internalAnchorReviewCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {tool:'Anclas internas',title:'Revisión de anclas y enlaces internos',hint:'Comprueba los enlaces de fragmento (#id) frente a los identificadores de encabezados del documento.',note:'Solo lectura y local: no visita sitios, no comprueba enlaces externos y no cambia el contenido. Los enlaces externos con fragmento no se consideran enlaces internos de esta página.',back:'Volver al editor',summary:'Enlaces internos comprobados: %1 · destinos ausentes: %2',clean:'Todos los enlaces internos comprobados apuntan a un encabezado con esa ancla.',invalid:'No se puede analizar un documento que no supera la validación BDRE.',warning:'Destino ausente',openBlock:'Localizar enlace',suggestion:'Añade una ancla al encabezado correcto o revisa el destino del enlace; esta herramienta no aplica correcciones.',detail:'El destino #%1 no coincide con ninguna ancla de encabezado del documento.',destination:'Destino: #%1'};
    if(language.startsWith('pt'))return {tool:'Âncoras internas',title:'Revisão de âncoras e links internos',hint:'Confira os links de fragmento (#id) em relação aos identificadores dos títulos do documento.',note:'Somente leitura e local: não visita sites, não verifica links externos e não altera conteúdo. Links externos com fragmento não são tratados como links internos desta página.',back:'Voltar ao editor',summary:'Links internos verificados: %1 · destinos ausentes: %2',clean:'Todos os links internos verificados apontam para um título com essa âncora.',invalid:'Não é possível analisar um documento que não passou na validação BDRE.',warning:'Destino ausente',openBlock:'Localizar link',suggestion:'Adicione uma âncora ao título correto ou revise o destino do link; esta ferramenta não aplica correções.',detail:'O destino #%1 não corresponde a nenhuma âncora de título no documento.',destination:'Destino: #%1'};
    return {tool:'Internal anchors',title:'Internal anchor and link review',hint:'Check fragment links (#id) against heading IDs in this document.',note:'Read-only and local: this tool does not visit websites, check external links or change content. External URLs with fragments are not treated as links within this page.',back:'Back to editor',summary:'Internal links checked: %1 · missing targets: %2',clean:'All checked internal links point to a heading with a matching anchor.',invalid:'A document that fails BDRE validation cannot be analyzed.',warning:'Missing target',openBlock:'Locate link',suggestion:'Add an anchor to the intended heading or review the link destination; this tool does not apply fixes.',detail:'Destination #%1 does not match a heading anchor in this document.',destination:'Destination: #%1'};
  }


  function readabilityReviewCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {
      tool:'Legibilidad',title:'Revisión de legibilidad',hint:'Observa la longitud de los párrafos, las frases y las secciones del documento.',note:'Los resultados son heurísticos y orientativos; no representan una puntuación definitiva de calidad ni reescriben el contenido.',back:'Volver al editor',noIssues:'No se encontraron alertas en las comprobaciones disponibles.',count:'Hallazgos: %1',invalid:'No se puede revisar un documento que no supera la validación BDRE.',openBlock:'Localizar bloque',warning:'Revisar',info:'Sugerencia',words:'%1 palabras',readingTime:'Lectura estimada: %1 min',
      issues:{long_paragraph:{severity:'warning',title:'Párrafo extenso',detail:'Este bloque contiene %1 palabras.',suggestion:'Considera dividirlo en párrafos más breves para facilitar la lectura.'},long_sentence:{severity:'info',title:'Frase extensa',detail:'La frase más larga de este bloque contiene aproximadamente %1 palabras.',suggestion:'Considera separar la idea en frases más cortas.'},long_section:{severity:'warning',title:'Sección extensa sin subtítulo',detail:'Esta sección contiene aproximadamente %1 palabras antes del siguiente encabezado del mismo nivel.',suggestion:'Considera agregar subtítulos que ayuden a recorrer el contenido.'}}
    };
    if(language.startsWith('pt'))return {
      tool:'Legibilidade',title:'Revisão de legibilidade',hint:'Observe o tamanho dos parágrafos, das frases e das seções do documento.',note:'Os resultados são heurísticos e orientativos; não representam uma nota definitiva de qualidade nem reescrevem o conteúdo.',back:'Voltar ao editor',noIssues:'Nenhum alerta foi encontrado nas verificações disponíveis.',count:'Apontamentos: %1',invalid:'Não é possível revisar um documento que não passou na validação BDRE.',openBlock:'Localizar bloco',warning:'Revisar',info:'Sugestão',words:'%1 palavras',readingTime:'Leitura estimada: %1 min',
      issues:{long_paragraph:{severity:'warning',title:'Parágrafo extenso',detail:'Este bloco contém %1 palavras.',suggestion:'Considere dividi-lo em parágrafos menores para facilitar a leitura.'},long_sentence:{severity:'info',title:'Frase extensa',detail:'A frase mais longa deste bloco contém aproximadamente %1 palavras.',suggestion:'Considere dividir a ideia em frases mais curtas.'},long_section:{severity:'warning',title:'Seção extensa sem subtítulo',detail:'Esta seção contém aproximadamente %1 palavras antes do próximo título do mesmo nível.',suggestion:'Considere adicionar subtítulos para ajudar a percorrer o conteúdo.'}}
    };
    return {
      tool:'Readability',title:'Readability review',hint:'Review the length of paragraphs, sentences and sections in this document.',note:'Findings are heuristic and advisory; they are not a definitive quality score and never rewrite content.',back:'Back to editor',noIssues:'No findings in the available checks.',count:'Findings: %1',invalid:'A document that fails BDRE validation cannot be reviewed.',openBlock:'Locate block',warning:'Review',info:'Suggestion',words:'%1 words',readingTime:'Estimated reading time: %1 min',
      issues:{long_paragraph:{severity:'warning',title:'Long paragraph',detail:'This block contains %1 words.',suggestion:'Consider splitting it into shorter paragraphs to make it easier to read.'},long_sentence:{severity:'info',title:'Long sentence',detail:'The longest sentence in this block contains about %1 words.',suggestion:'Consider splitting the idea into shorter sentences.'},long_section:{severity:'warning',title:'Long section without a subheading',detail:'This section contains about %1 words before the next heading at the same level.',suggestion:'Consider adding subheadings to help readers navigate the content.'}}
    };
  }


  function preflightReviewCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {
      tool:'Checklist',title:'Lista previa a la publicación',hint:'Reúne las comprobaciones BDRE disponibles en una sola vista para revisar el documento antes de publicarlo.',note:'Los resultados son orientativos. Esta lista no publica, guarda ni modifica contenido y no comprueba sitios externos.',back:'Volver al editor',invalid:'El documento no supera la validación BDRE y no puede revisarse.',clean:'No se encontraron alertas en las comprobaciones disponibles.',total:'%1 hallazgos',warnings:'%1 por revisar',suggestions:'%1 sugerencias',words:'%1 palabras · lectura estimada: %2 min',categoryCount:'%1 hallazgos',categoryEmpty:'Sin hallazgos en esta área.',warning:'Revisar',info:'Sugerencia',openBlock:'Localizar bloque',
      categories:{accessibility:'Accesibilidad',links:'Enlaces y destinos',readability:'Legibilidad'}
    };
    if(language.startsWith('pt'))return {
      tool:'Checklist',title:'Checklist pré-publicação',hint:'Reúne as verificações BDRE disponíveis em uma única tela para revisar o documento antes da publicação.',note:'Os resultados são orientativos. Esta lista não publica, salva nem altera conteúdo e não verifica sites externos.',back:'Voltar ao editor',invalid:'O documento não passou na validação BDRE e não pode ser revisado.',clean:'Nenhum alerta foi encontrado nas verificações disponíveis.',total:'%1 apontamentos',warnings:'%1 para revisar',suggestions:'%1 sugestões',words:'%1 palavras · leitura estimada: %2 min',categoryCount:'%1 apontamentos',categoryEmpty:'Nenhum apontamento nesta área.',warning:'Revisar',info:'Sugestão',openBlock:'Localizar bloco',
      categories:{accessibility:'Acessibilidade',links:'Links e destinos',readability:'Legibilidade'}
    };
    return {
      tool:'Checklist',title:'Pre-publication checklist',hint:'Brings the available BDRE checks together in one view for a final content review.',note:'Findings are advisory. This checklist does not publish, save or change content, and does not check external websites.',back:'Back to editor',invalid:'The document fails BDRE validation and cannot be reviewed.',clean:'No findings in the available checks.',total:'%1 findings',warnings:'%1 to review',suggestions:'%1 suggestions',words:'%1 words · estimated reading time: %2 min',categoryCount:'%1 findings',categoryEmpty:'No findings in this area.',warning:'Review',info:'Suggestion',openBlock:'Locate block',
      categories:{accessibility:'Accessibility',links:'Links and destinations',readability:'Readability'}
    };
  }


  function revisionCompareCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {
      tool:'Comparar revisiones',title:'Comparador de revisiones locales',hint:'Compara el borrador actual con una revisión temporal guardada en esta pestaña.',note:'Solo lectura: no restaura ni modifica contenido. El historial local puede incluir hasta cinco revisiones de las últimas 24 horas; Pages y Blog usan historiales independientes.',back:'Volver al editor',historyTitle:'Revisiones disponibles',historyEmpty:'No hay revisiones locales disponibles en esta pestaña.',revisionLabel:'Guardada: %1 · %2 bloques',compare:'Comparar con el borrador actual',comparisonTitle:'Diferencias encontradas',comparisonHint:'Borrador actual comparado con la revisión seleccionada.',invalid:'No se puede comparar: una de las versiones no supera la validación BDRE.',noChanges:'No se encontraron diferencias entre estas versiones.',summary:'Añadidos: %1 · Eliminados: %2 · Modificados: %3',added:'Añadido',removed:'Eliminado',changed:'Modificado',before:'Revisión local',after:'Borrador actual',changedFields:'Campos: %1',fieldType:'tipo de bloque',fieldAttributes:'atributos',fieldContent:'contenido',fieldContainer:'contenedor',fieldPosition:'orden',noPreview:'Sin texto de vista previa.',openBlock:'Localizar bloque actual',removedHint:'Este bloque ya no existe en el borrador actual.'
    };
    if(language.startsWith('pt'))return {
      tool:'Comparar revisões',title:'Comparador de revisões locais',hint:'Compare o rascunho atual com uma revisão temporária guardada nesta aba.',note:'Somente leitura: não restaura nem altera conteúdo. O histórico local pode incluir até cinco revisões das últimas 24 horas; Pages e Blog usam históricos independentes.',back:'Voltar ao editor',historyTitle:'Revisões disponíveis',historyEmpty:'Não há revisões locais disponíveis nesta aba.',revisionLabel:'Salva em: %1 · %2 blocos',compare:'Comparar com o rascunho atual',comparisonTitle:'Diferenças encontradas',comparisonHint:'Rascunho atual comparado com a revisão selecionada.',invalid:'Não é possível comparar: uma das versões não passou na validação BDRE.',noChanges:'Nenhuma diferença foi encontrada entre estas versões.',summary:'Adicionados: %1 · Removidos: %2 · Alterados: %3',added:'Adicionado',removed:'Removido',changed:'Alterado',before:'Revisão local',after:'Rascunho atual',changedFields:'Campos: %1',fieldType:'tipo de bloco',fieldAttributes:'atributos',fieldContent:'conteúdo',fieldContainer:'contêiner',fieldPosition:'ordem',noPreview:'Sem texto para prévia.',openBlock:'Localizar bloco atual',removedHint:'Este bloco não existe mais no rascunho atual.'
    };
    return {
      tool:'Compare revisions',title:'Local revision comparison',hint:'Compare the current draft with a temporary revision saved in this browser tab.',note:'Read-only: this tool never restores or changes content. Local history may contain up to five revisions from the last 24 hours; Pages and Blog histories are independent.',back:'Back to editor',historyTitle:'Available revisions',historyEmpty:'There are no local revisions available in this tab.',revisionLabel:'Saved: %1 · %2 blocks',compare:'Compare with current draft',comparisonTitle:'Differences found',comparisonHint:'Current draft compared with the selected revision.',invalid:'Cannot compare: one version fails BDRE validation.',noChanges:'No differences were found between these versions.',summary:'Added: %1 · Removed: %2 · Changed: %3',added:'Added',removed:'Removed',changed:'Changed',before:'Local revision',after:'Current draft',changedFields:'Fields: %1',fieldType:'block type',fieldAttributes:'attributes',fieldContent:'content',fieldContainer:'container',fieldPosition:'order',noPreview:'No text preview.',openBlock:'Locate current block',removedHint:'This block no longer exists in the current draft.'
    };
  }



  function tableOfContentsCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {tool:'Sumario',title:'Sumario navegable',hint:'Previsualiza los encabezados que se incluirán. Al confirmar, se asignarán anclas únicas cuando sea necesario y se añadirá un sumario nativo en el punto seleccionado.',levels:'Niveles de título',preview:'Vista previa',empty:'No hay títulos con texto en los niveles seleccionados.',invalid:'El documento no supera la validación BDRE.',insert:'Insertar sumario',back:'Volver al editor',groupLabel:'Sumario de la página',sumHeading:'En esta página',added:'Se añadirá como grupo; no reemplaza el contenido existente.',anchor:'Ancla #%1',existingAnchor:'Ancla existente #%1',unavailable:'No se puede insertar un grupo en este punto. El documento no se modificó.',done:'Sumario insertado y anclas guardadas en los títulos.',headingLevel:'Título %1'};
    if(language.startsWith('pt'))return {tool:'Sumário',title:'Sumário navegável',hint:'Confira os títulos que serão incluídos. Ao confirmar, âncoras únicas serão atribuídas quando necessário e um sumário nativo será adicionado no ponto selecionado.',levels:'Níveis de título',preview:'Prévia',empty:'Não há títulos com texto nos níveis selecionados.',invalid:'O documento não passou na validação BDRE.',insert:'Inserir sumário',back:'Voltar ao editor',groupLabel:'Sumário da página',sumHeading:'Nesta página',added:'Será inserido como grupo; o conteúdo existente não será substituído.',anchor:'Âncora #%1',existingAnchor:'Âncora existente #%1',unavailable:'Não é possível inserir um grupo neste ponto. O documento não foi alterado.',done:'Sumário inserido e âncoras salvas nos títulos.',headingLevel:'Título %1'};
    return {tool:'Table of contents',title:'Navigable table of contents',hint:'Preview the headings to include. On confirmation, unique anchors are assigned where needed and a native table of contents is added at the selected insertion point.',levels:'Heading levels',preview:'Preview',empty:'There are no titled headings at the selected levels.',invalid:'The document fails BDRE validation.',insert:'Insert table of contents',back:'Back to editor',groupLabel:'Page contents',sumHeading:'On this page',added:'Added as a group; existing content is not replaced.',anchor:'Anchor #%1',existingAnchor:'Existing anchor #%1',unavailable:'A group cannot be inserted at this point. The document was not changed.',done:'Table of contents inserted and heading anchors saved.',headingLevel:'Heading %1'};
  }

  function createTableOfContentsPlan(document, levels, allowPageMedia) {
    const validation=validateDocument(document,allowPageMedia);
    if(!validation.valid)return {valid:false,errors:validation.errors,headings:[]};
    const selected=new Set((Array.isArray(levels)?levels:[]).filter((level)=>Number.isInteger(level)&&level>=1&&level<=6));
    const allHeadings=[],reserved=new Set();
    const textOf=(node)=>Array.isArray(node.content)?node.content.map((run)=>run&&typeof run.text==='string'?run.text:'').join(''):'';
    const visit=(nodes,insideToc)=>nodes.forEach((node)=>{
      const skip=insideToc||node.type==='core/group'&&node.attributes.toc_generated===true;
      if(node.type==='core/heading'&&node.attributes.anchor)reserved.add(node.attributes.anchor);
      if(node.type==='core/heading'&&!skip){const text=textOf(node).trim(),level=Number(node.attributes.level)||2;if(text&&selected.has(level))allHeadings.push({nodeId:node.id,text,level,anchor:node.attributes.anchor||'',needsAnchor:!node.attributes.anchor});}
      if(Array.isArray(node.children)&&node.children.length)visit(node.children,skip);
    });
    visit(document.blocks,false);
    const makeSlug=(value)=>{
      let base=String(value||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,70).replace(/-+$/g,'');
      if(!base)base='section';if(!/^[a-z]/.test(base))base='section-'+base;return base.slice(0,80);
    };
    allHeadings.forEach((heading)=>{
      if(heading.anchor)return;
      const base=makeSlug(heading.text);let candidate=base,index=2;
      while(reserved.has(candidate)){const suffix='-'+index++;candidate=base.slice(0,80-suffix.length).replace(/-+$/g,'')+suffix;}
      heading.anchor=candidate;reserved.add(candidate);
    });
    return {valid:true,errors:[],headings:allHeadings};
  }

  function markdownExportCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {
      tool:'Markdown',group:'Exportar Markdown',open:'Markdown (.md)',title:'Vista previa de Markdown',previewLabel:'Contenido Markdown generado',hint:'Previsualiza una conversión textual del documento BDRE antes de descargarla.',back:'Volver a exportaciones',download:'Descargar Markdown',preview:'Previsualizar Markdown',warningCount:'Elementos no representados: %1',warningsTitle:'Notas de conversión',noWarnings:'Todos los bloques se representaron en Markdown según las reglas disponibles.',previewTruncated:'La vista previa se limita a 200 000 caracteres; el archivo descargado incluye todo el contenido.',invalid:'No se puede exportar un documento que no supera la validación BDRE.',tooLarge:'El archivo Markdown supera el límite seguro de exportación.',downloadSuccess:'Archivo Markdown descargado.',downloadError:'No se pudo crear la descarga Markdown.',emptyMarkdown:'Este documento no contiene bloques con texto Markdown.',downloadWithWarnings:'Archivo Markdown descargado con %1 notas sobre elementos no representados.',
      warnings:{underline:'Markdown no representa el subrayado; se conservó el texto sin ese estilo.',appearance:'El estilo visual del bloque no se representa.',layout:'La disposición visual se simplificó al orden de lectura.',spacer:'El bloque Espaciador se omitió en la exportación textual.',callout_style:'El tono visual del aviso no se conserva.',details_behavior:'El contenido expandible se convirtió en texto abierto.',table_header:'La primera fila se usó como encabezado Markdown.',table_span:'Las celdas combinadas no se conservan en la tabla Markdown.',video_embed:'El vídeo incrustado se convirtió en un enlace.',media_link:'El archivo multimedia se convirtió en un enlace; no se conserva la reproducción integrada.',interactive_3d:'La escena 3D no se incluyó; custom_js no se ejecutó.',cover_layout:'La presentación visual de la portada no se conserva.',gallery_layout:'La galería se convirtió en una secuencia lineal de imágenes.',heading_anchor:'El identificador de ancla no se conserva en Markdown estándar.',generated_toc:'El sumario se exportó como lista de enlaces; los destinos de ancla pueden requerir ajustes en otros visores Markdown.'},
      calloutTones:{info:'Nota',success:'Éxito',warning:'Atención',danger:'Importante'},labels:{video:'Vídeo',audio:'Audio',file:'Archivo',caption:'Leyenda',scene3D:'Escena 3D'},
      unsupportedBlock:'Elemento BDRE sin representación Markdown directa: %1',warningBlock:'%1 · %2',
    };
    if(language.startsWith('pt'))return {
      tool:'Markdown',group:'Exportar Markdown',open:'Markdown (.md)',title:'Prévia do Markdown',previewLabel:'Código Markdown gerado',hint:'Confira a conversão textual do documento BDRE antes de baixar o arquivo.',back:'Voltar às exportações',download:'Baixar Markdown',preview:'Pré-visualizar Markdown',warningCount:'Itens não representados: %1',warningsTitle:'Notas da conversão',noWarnings:'Todos os blocos foram representados em Markdown conforme as regras disponíveis.',previewTruncated:'A prévia está limitada a 200.000 caracteres; o arquivo baixado contém todo o conteúdo.',invalid:'Não é possível exportar um documento que não passou na validação BDRE.',tooLarge:'O arquivo Markdown ultrapassa o limite seguro de exportação.',downloadSuccess:'Arquivo Markdown baixado.',downloadError:'Não foi possível criar o download Markdown.',emptyMarkdown:'Este documento não contém blocos com texto Markdown.',downloadWithWarnings:'Arquivo Markdown baixado com %1 notas sobre itens não representados.',
      warnings:{underline:'Markdown não representa sublinhado; o texto foi mantido sem esse estilo.',appearance:'O estilo visual do bloco não é representado.',layout:'A disposição visual foi simplificada para a ordem de leitura.',spacer:'O bloco Espaçador foi omitido na exportação textual.',callout_style:'O tom visual do aviso não é preservado.',details_behavior:'O conteúdo expansível foi convertido em texto aberto.',table_header:'A primeira linha foi usada como cabeçalho Markdown.',table_span:'Mesclas de células não são preservadas na tabela Markdown.',video_embed:'O vídeo incorporado foi convertido em link.',media_link:'A mídia foi convertida em link; a reprodução incorporada não é preservada.',interactive_3d:'A cena 3D não foi incluída; custom_js não foi executado.',cover_layout:'A apresentação visual da capa não é preservada.',gallery_layout:'A galeria foi convertida em uma sequência linear de imagens.',heading_anchor:'O identificador da âncora não é preservado no Markdown padrão.',generated_toc:'O sumário foi exportado como lista de links; os destinos das âncoras podem exigir ajustes em outros leitores Markdown.'},
      calloutTones:{info:'Nota',success:'Sucesso',warning:'Atenção',danger:'Importante'},labels:{video:'Vídeo',audio:'Áudio',file:'Arquivo',caption:'Legenda',scene3D:'Cena 3D'},
      unsupportedBlock:'Elemento BDRE sem representação direta em Markdown: %1',warningBlock:'%1 · %2'
    };
    return {
      tool:'Markdown',group:'Markdown export',open:'Markdown (.md)',title:'Markdown preview',previewLabel:'Generated Markdown source',hint:'Review a text-focused conversion of the BDRE document before downloading it.',back:'Back to exports',download:'Download Markdown',preview:'Preview Markdown',warningCount:'Items not represented: %1',warningsTitle:'Conversion notes',noWarnings:'All blocks are represented in Markdown according to the available conversion rules.',previewTruncated:'Preview is limited to 200,000 characters; the downloaded file contains the full content.',invalid:'A document that fails BDRE validation cannot be exported.',tooLarge:'The Markdown file exceeds the safe export size limit.',downloadSuccess:'Markdown file downloaded.',downloadError:'The Markdown download could not be created.',emptyMarkdown:'This document has no blocks with Markdown text.',downloadWithWarnings:'Markdown file downloaded with %1 notes about items not represented.',
      warnings:{underline:'Markdown has no standard underline syntax; text is preserved without that styling.',appearance:'Block visual styling is not represented.',layout:'Visual arrangement was flattened to reading order.',spacer:'Spacer block was omitted from the text export.',callout_style:'Callout visual tone is not preserved.',details_behavior:'Expandable content was exported as open text.',table_header:'First table row was used as the Markdown header.',table_span:'Merged cells are not preserved in the Markdown table.',video_embed:'Embedded video was converted to a link.',media_link:'Media was converted to a link; embedded playback is not preserved.',interactive_3d:'3D scene was not included; custom_js was not executed.',cover_layout:'Cover visual presentation is not preserved.',gallery_layout:'Gallery layout was converted to a linear sequence of images.',heading_anchor:'Heading anchor IDs are not preserved in standard Markdown.',generated_toc:'The table of contents was exported as a link list; anchor targets may need adjustment in other Markdown viewers.'},
      calloutTones:{info:'Note',success:'Success',warning:'Warning',danger:'Important'},labels:{video:'Video',audio:'Audio',file:'File',caption:'Caption',scene3D:'3D scene'},
      unsupportedBlock:'BDRE item without a direct Markdown representation: %1',warningBlock:'%1 · %2'
    };
  }


  function markdownImportCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    if(language.startsWith('es'))return {
      tool:'Importar Markdown',title:'Vista previa de importación Markdown',hint:'Revisa la conversión a bloques BDRE nativos. Nada cambia hasta confirmar la inserción.',additive:'Al confirmar, los bloques se añadirán como un grupo; el contenido existente no se reemplaza.',open:'Elegir archivo Markdown',insert:'Insertar como grupo',cancel:'Volver a exportaciones',file:'Archivo: %1',blockCount:'Bloques que se añadirán: %1',warningCount:'Notas de importación: %1',warningsTitle:'Notas y límites',previewTitle:'Vista previa de bloques convertidos',sourceTitle:'Markdown original',empty:'El archivo no contiene bloques compatibles para insertar.',invalid:'No se puede importar este archivo Markdown.',tooLarge:'El archivo Markdown supera el límite de 256 KiB.',wrongExtension:'Selecciona un archivo .md o .markdown.',parseFailed:'No se pudo leer el archivo Markdown.',insertUnavailable:'No se puede insertar un grupo en el punto seleccionado. El documento no se modificó.',insertFailed:'El resultado excede un límite BDRE o no es válido. El documento no se modificó.',inserted:'El contenido Markdown se insertó en un grupo editable; el contenido existente se conservó.',back:'Volver',
      warnings:{unsafe_link:'El destino no está permitido como enlace y se conservó como texto.',inline_media_link:'El enlace a un archivo multimedia en línea se conservó como texto; los enlaces multimedia independientes se convierten en bloques.',remote_image:'La imagen no es local o pertenece a otra integración; no se descargó y se conservó una referencia textual.',inline_image:'La imagen en línea se mantuvo como texto; solo imágenes en una línea independiente se convierten en bloque.',html_literal:'El HTML parecido a etiquetas se conservó como texto y no se ejecutó.',link_title:'El título adicional del enlace no se conserva como atributo.',nested_list:'La lista anidada se aplanó al nivel actual.',unclosed_fence:'El bloque de código no tenía una cerca de cierre y se extendió hasta el final del archivo.',table_overflow:'La tabla excede los límites BDRE y sus filas se conservaron como párrafos.',list_start:'La numeración personalizada de la lista se normalizó al inicio estándar.'},
      warningLine:'Línea %1',warningBlock:'Bloque %1'
    };
    if(language.startsWith('pt'))return {
      tool:'Importar Markdown',title:'Prévia da importação Markdown',hint:'Confira a conversão em blocos BDRE nativos. Nada muda até confirmar a inserção.',additive:'Ao confirmar, os blocos serão adicionados como um grupo; o conteúdo existente não será substituído.',open:'Escolher arquivo Markdown',insert:'Inserir como grupo',cancel:'Voltar às exportações',file:'Arquivo: %1',blockCount:'Blocos que serão adicionados: %1',warningCount:'Notas da importação: %1',warningsTitle:'Notas e limitações',previewTitle:'Prévia dos blocos convertidos',sourceTitle:'Markdown original',empty:'O arquivo não contém blocos compatíveis para inserir.',invalid:'Não foi possível importar este arquivo Markdown.',tooLarge:'O arquivo Markdown ultrapassa o limite de 256 KiB.',wrongExtension:'Selecione um arquivo .md ou .markdown.',parseFailed:'Não foi possível ler o arquivo Markdown.',insertUnavailable:'Não é possível inserir um grupo no ponto selecionado. O documento não foi alterado.',insertFailed:'O resultado ultrapassa um limite BDRE ou é inválido. O documento não foi alterado.',inserted:'O conteúdo Markdown foi inserido em um grupo editável; o conteúdo existente foi preservado.',back:'Voltar',
      warnings:{unsafe_link:'O destino não é permitido como link e foi mantido como texto.',inline_media_link:'O link de mídia em linha foi mantido como texto; links de mídia independentes viram blocos.',remote_image:'A imagem não é local ou pertence a outra integração; ela não foi baixada e uma referência textual foi mantida.',inline_image:'A imagem em linha foi mantida como texto; somente imagens em linha independente viram bloco.',html_literal:'O texto parecido com tags HTML foi mantido como texto e não foi executado.',link_title:'O título adicional do link não é preservado como atributo.',nested_list:'A lista aninhada foi achatada no nível atual.',unclosed_fence:'O bloco de código não tinha uma cerca de fechamento e foi até o final do arquivo.',table_overflow:'A tabela excede os limites BDRE e suas linhas foram preservadas como parágrafos.',list_start:'A numeração personalizada da lista foi normalizada para o início padrão.'},
      warningLine:'Linha %1',warningBlock:'Bloco %1'
    };
    return {
      tool:'Import Markdown',title:'Markdown import preview',hint:'Review the conversion to native BDRE blocks. Nothing changes until you confirm insertion.',additive:'Confirming adds the blocks as a group; existing content is not replaced.',open:'Choose Markdown file',insert:'Insert as a group',cancel:'Back to exports',file:'File: %1',blockCount:'Blocks to add: %1',warningCount:'Import notes: %1',warningsTitle:'Notes and limitations',previewTitle:'Preview of converted blocks',sourceTitle:'Original Markdown',empty:'The file has no compatible blocks to insert.',invalid:'This Markdown file could not be imported.',tooLarge:'Markdown file exceeds the 256 KiB limit.',wrongExtension:'Choose a .md or .markdown file.',parseFailed:'Could not read the Markdown file.',insertUnavailable:'A group cannot be inserted at the selected point. The document was not changed.',insertFailed:'The result exceeds a BDRE limit or is invalid. The document was not changed.',inserted:'Markdown content was inserted as an editable group; existing content was preserved.',back:'Back',
      warnings:{unsafe_link:'Destination is not allowed as a link and was preserved as text.',inline_media_link:'Inline media link was preserved as text; standalone media links become blocks.',remote_image:'Image is not local or belongs to another integration; it was not downloaded and a text reference was kept.',inline_image:'Inline image was preserved as text; only a standalone image line becomes an image block.',html_literal:'HTML-like text was preserved as text and was not executed.',link_title:'Additional link title is not preserved as a block attribute.',nested_list:'Nested list was flattened to the current level.',unclosed_fence:'Code block had no closing fence and continued to the end of the file.',table_overflow:'Table exceeds BDRE limits; its rows were preserved as paragraphs.',list_start:'Custom list numbering was normalized to the standard start.'},
      warningLine:'Line %1',warningBlock:'Block %1'
    };
  }

  function contentModelCopy() {
    const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    const n=(type,content,attributes,children)=>({type,content:content||'',attributes:attributes||{},children:children||[]});
    if(language.startsWith('es'))return {
      title:'Plantillas de contenido',hint:'Inserta un grupo editable en el punto seleccionado (dentro de un contenedor cuando corresponda); sin selección, al final. Nunca reemplaza el documento.',insert:'Insertar plantilla',unavailable:'No se pudo insertar esta plantilla en este punto. El documento no se modificó.',
      templates:[
        {id:'page_intro',name:'Presentación institucional',description:'Título, introducción y sección “Quiénes somos”.',outline:'Presentación institucional',blocks:[n('core/heading','Título de la página',{level:1}),n('core/paragraph','Resume aquí la propuesta y el propósito de esta página.'),n('core/separator',''),n('core/heading','Quiénes somos',{level:2}),n('core/paragraph','Presenta brevemente a tu organización, su historia o misión.')]},
        {id:'article',name:'Artículo o publicación',description:'Título, entradilla, secciones, cita y nota expandible.',outline:'Artículo',blocks:[n('core/heading','Título del artículo',{level:1}),n('core/paragraph','Escribe una entradilla breve que resuma el tema.'),n('core/separator',''),n('core/heading','Introducción',{level:2}),n('core/paragraph','Presenta el tema y el contexto para el lector.'),n('core/quote','Añade aquí una cita o idea destacada.'),n('core/heading','Desarrollo',{level:2}),n('core/paragraph','Desarrolla el contenido en este párrafo.'),n('core/details','Amplía la información complementaria.',{summary:'Más información'})]},
        {id:'services',name:'Servicios en tres columnas',description:'Presentación y tres tarjetas editables de servicios.',outline:'Servicios',blocks:[n('core/heading','Nuestros servicios',{level:2}),n('core/paragraph','Describe brevemente las soluciones que ofreces.'),n('core/columns','',{count:3},[n('core/column','',{},[n('core/heading','Servicio uno',{level:3}),n('core/paragraph','Describe el primer servicio.')]),n('core/column','',{},[n('core/heading','Servicio dos',{level:3}),n('core/paragraph','Describe el segundo servicio.')]),n('core/column','',{},[n('core/heading','Servicio tres',{level:3}),n('core/paragraph','Describe el tercer servicio.')])])]},
        {id:'faq',name:'Preguntas frecuentes',description:'Introducción y tres respuestas expandibles.',outline:'Preguntas frecuentes',blocks:[n('core/heading','Preguntas frecuentes',{level:2}),n('core/paragraph','Responde aquí las dudas más comunes.'),n('core/details','Escribe la primera respuesta.',{summary:'¿Cuál es la primera pregunta?'}),n('core/details','Escribe la segunda respuesta.',{summary:'¿Cuál es la segunda pregunta?'}),n('core/details','Escribe la tercera respuesta.',{summary:'¿Cuál es la tercera pregunta?'})]},
        {id:'contact',name:'Llamada a la acción',description:'Mensaje breve, aviso destacado y botón de contacto.',outline:'Llamada a la acción',blocks:[n('core/heading','¿Hablamos?',{level:2}),n('core/paragraph','Invita a tus visitantes a dar el siguiente paso.'),n('core/callout','Estamos disponibles para ayudarte.',{tone:'info'}),n('core/button','Contactar',{url:'/contacto'})]},
        {id:'steps',name:'Guía paso a paso',description:'Introducción y lista numerada de tres pasos.',outline:'Guía paso a paso',blocks:[n('core/heading','Cómo funciona',{level:2}),n('core/paragraph','Explica el proceso de forma clara y sencilla.'),n('core/list','',{ordered:true},[n('core/list-item','Primer paso: explica qué debe hacer el usuario.'),n('core/list-item','Segundo paso: describe qué sucede a continuación.'),n('core/list-item','Tercer paso: indica cómo finalizar.')]) ]}
      ]
    };
    if(language.startsWith('en'))return {
      title:'Content templates',hint:'Insert an editable group at the selected insertion point (inside a selected container where allowed); otherwise after the selection or at the end. Existing content is never replaced.',insert:'Insert template',unavailable:'This template cannot be inserted here. The document was not changed.',
      templates:[
        {id:'page_intro',name:'Organization introduction',description:'Title, introduction and an About section.',outline:'Organization introduction',blocks:[n('core/heading','Page title',{level:1}),n('core/paragraph','Summarize the purpose and value of this page.'),n('core/separator',''),n('core/heading','About us',{level:2}),n('core/paragraph','Introduce your organization, its story or mission.')]},
        {id:'article',name:'Article or post',description:'Title, lead, sections, quote and expandable note.',outline:'Article',blocks:[n('core/heading','Article title',{level:1}),n('core/paragraph','Write a concise lead that summarizes the topic.'),n('core/separator',''),n('core/heading','Introduction',{level:2}),n('core/paragraph','Introduce the subject and give readers context.'),n('core/quote','Add a highlighted quotation or key idea.'),n('core/heading','Main section',{level:2}),n('core/paragraph','Develop the article in this paragraph.'),n('core/details','Add supplementary information.',{summary:'More information'})]},
        {id:'services',name:'Three-column services',description:'Introduction and three editable service cards.',outline:'Services',blocks:[n('core/heading','Our services',{level:2}),n('core/paragraph','Briefly describe the solutions you offer.'),n('core/columns','',{count:3},[n('core/column','',{},[n('core/heading','Service one',{level:3}),n('core/paragraph','Describe the first service.')]),n('core/column','',{},[n('core/heading','Service two',{level:3}),n('core/paragraph','Describe the second service.')]),n('core/column','',{},[n('core/heading','Service three',{level:3}),n('core/paragraph','Describe the third service.')])])]},
        {id:'faq',name:'Frequently asked questions',description:'Introduction and three expandable answers.',outline:'Frequently asked questions',blocks:[n('core/heading','Frequently asked questions',{level:2}),n('core/paragraph','Answer the questions your visitors ask most.'),n('core/details','Write the first answer.',{summary:'What is the first question?'}),n('core/details','Write the second answer.',{summary:'What is the second question?'}),n('core/details','Write the third answer.',{summary:'What is the third question?'})]},
        {id:'contact',name:'Call to action',description:'Short message, highlighted note and contact button.',outline:'Call to action',blocks:[n('core/heading','Let’s talk',{level:2}),n('core/paragraph','Invite visitors to take the next step.'),n('core/callout','We are ready to help.',{tone:'info'}),n('core/button','Contact us',{url:'/contact'})]},
        {id:'steps',name:'Step-by-step guide',description:'Introduction and an ordered list of three steps.',outline:'Step-by-step guide',blocks:[n('core/heading','How it works',{level:2}),n('core/paragraph','Explain the process in a clear, simple way.'),n('core/list','',{ordered:true},[n('core/list-item','Step one: explain what the reader should do.'),n('core/list-item','Step two: describe what happens next.'),n('core/list-item','Step three: explain how to finish.')]) ]}
      ]
    };
    return {
      title:'Modelos de conteúdo',hint:'Insere um grupo editável no ponto selecionado (dentro de contêiner quando permitido); sem seleção, após a seleção ou no fim. O conteúdo existente nunca é substituído.',insert:'Inserir modelo',unavailable:'Não foi possível inserir este modelo neste ponto. O documento não foi alterado.',
      templates:[
        {id:'page_intro',name:'Apresentação institucional',description:'Título, introdução e seção “Quem somos”.',outline:'Apresentação institucional',blocks:[n('core/heading','Título da página',{level:1}),n('core/paragraph','Resuma aqui a proposta e o propósito desta página.'),n('core/separator',''),n('core/heading','Quem somos',{level:2}),n('core/paragraph','Apresente brevemente sua organização, história ou missão.')]},
        {id:'article',name:'Artigo ou publicação',description:'Título, linha fina, seções, citação e nota expansível.',outline:'Artigo',blocks:[n('core/heading','Título do artigo',{level:1}),n('core/paragraph','Escreva uma introdução breve que resuma o tema.'),n('core/separator',''),n('core/heading','Introdução',{level:2}),n('core/paragraph','Apresente o tema e dê contexto ao leitor.'),n('core/quote','Inclua aqui uma citação ou ideia em destaque.'),n('core/heading','Desenvolvimento',{level:2}),n('core/paragraph','Desenvolva o conteúdo neste parágrafo.'),n('core/details','Adicione informações complementares.',{summary:'Mais informações'})]},
        {id:'services',name:'Serviços em três colunas',description:'Apresentação e três cartões editáveis de serviços.',outline:'Serviços',blocks:[n('core/heading','Nossos serviços',{level:2}),n('core/paragraph','Descreva brevemente as soluções que você oferece.'),n('core/columns','',{count:3},[n('core/column','',{},[n('core/heading','Serviço um',{level:3}),n('core/paragraph','Descreva o primeiro serviço.')]),n('core/column','',{},[n('core/heading','Serviço dois',{level:3}),n('core/paragraph','Descreva o segundo serviço.')]),n('core/column','',{},[n('core/heading','Serviço três',{level:3}),n('core/paragraph','Descreva o terceiro serviço.')])])]},
        {id:'faq',name:'Perguntas frequentes',description:'Introdução e três respostas expansíveis.',outline:'Perguntas frequentes',blocks:[n('core/heading','Perguntas frequentes',{level:2}),n('core/paragraph','Responda às dúvidas mais comuns dos visitantes.'),n('core/details','Escreva a primeira resposta.',{summary:'Qual é a primeira pergunta?'}),n('core/details','Escreva a segunda resposta.',{summary:'Qual é a segunda pergunta?'}),n('core/details','Escreva a terceira resposta.',{summary:'Qual é a terceira pergunta?'})]},
        {id:'contact',name:'Chamada para contato',description:'Mensagem curta, aviso destacado e botão de contato.',outline:'Chamada para contato',blocks:[n('core/heading','Vamos conversar?',{level:2}),n('core/paragraph','Convide seus visitantes a dar o próximo passo.'),n('core/callout','Estamos à disposição para ajudar.',{tone:'info'}),n('core/button','Fale conosco',{url:'/contato'})]},
        {id:'steps',name:'Guia passo a passo',description:'Introdução e lista numerada com três etapas.',outline:'Guia passo a passo',blocks:[n('core/heading','Como funciona',{level:2}),n('core/paragraph','Explique o processo de forma clara e simples.'),n('core/list','',{ordered:true},[n('core/list-item','Etapa um: explique o que a pessoa deve fazer.'),n('core/list-item','Etapa dois: descreva o que acontece em seguida.'),n('core/list-item','Etapa três: indique como concluir.')]) ]}
      ]
    };
  }

  const ICON_PATHS = {
    plus:'M12 5v14M5 12h14', alignLeft:'M4 6h16M4 10h10M4 14h16M4 18h10', alignCenter:'M4 6h16M7 10h10M4 14h16M7 18h10', alignRight:'M4 6h16M10 10h10M4 14h16M10 18h10', copy:'M8 8V4h12v12h-4M4 8h12v12H4z', undo:'M9 14 4 9l5-5M4 9h10a6 6 0 0 1 0 12h-2', redo:'m15 14 5-5-5-5m5 5H10a6 6 0 0 0 0 12h2',
    desktop:'M3 4h18v13H3zM8 21h8M12 17v4', tablet:'M6 2h12v20H6zM11 18h2', mobile:'M8 2h8v20H8zM11 18h2',
    up:'m18 15-6-6-6 6M12 9v12', down:'m6 9 6 6 6-6M12 3v12', trash:'M4 7h16M10 11v6m4-6v6M6 7l1 14h10l1-14M9 7V4h6v3',
    checklist:'M9 6h12M9 12h12M9 18h12M3 6l1.5 1.5L7 5m-4 7 1.5 1.5L7 11m-4 7 1.5 1.5L7 17', markdown:'M5 3h10l4 4v14H5zM15 3v5h5M8 13h2l1 4 1-4h2v5m2-5v5m0 0 2-2m-2 2-2-2', compare:'M4 5h6v14H4zM14 5h6v14h-6zM6 8h2m-2 4h2m8-4h2m-2 4h2M10 12h4',
    bookOpen:'M12 7v14M4 5.5c3-1 5.7-.2 8 1.5 2.3-1.7 5-2.5 8-1.5v13c-3-1-5.7-.2-8 1.5-2.3-1.7-5-2.5-8-1.5zM7 9h2M15 9h2M7 12h2M15 12h2', clock:'M12 8v5l3 2M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
    paragraph:'M4 6h16M4 10h16M4 14h10M4 18h10', heading:'M4 5v14M20 5v14M4 12h16', list:'M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01',
    image:'M4 4h16v16H4zM8 14l3-3 5 5M14 9h.01', quote:'M5 6h6v6H5zM13 6h6v6h-6zM5 12c0 3-1 4-2 5m10-5c0 3-1 4-2 5', video:'m9 7 9 5-9 5zM4 5v14', threejs:'m12 3 8 4v10l-8 4-8-4V7l8-4zm-8 4 8 4 8-4m-8 4v10', button:'M4 7h16v10H4z', bold:'M7 5h6a4 4 0 0 1 0 8H7zM7 13h7a4 4 0 0 1 0 8H7z', italic:'M15 4h-5m4 16H9m5-16-4 16', underline:'M6 4v7a6 6 0 0 0 12 0V4M4 21h16', strike:'M5 12h14M7 6c1-2 3-2 5-2 3 0 5 1 5 3 0 2-2 3-5 4-3 1-5 2-5 5 0 2 2 4 5 4 2 0 4-1 5-3', link:'M10 13a5 5 0 0 0 7.1 0l3-3a5 5 0 0 0-7.1-7.1l-1.7 1.7M14 11a5 5 0 0 0-7.1 0l-3 3A5 5 0 0 0 11 21l1.7-1.7', info:'M12 8h.01M11 12h1v5h1M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0',
    details:'M5 7h14M5 12h14M5 17h14M3 7h.01M3 12h.01M3 17h.01', spacer:'M12 3v18m-4-14 4-4 4 4m-8 10 4 4 4-4', code:'m8 6-6 6 6 6m8-12 6 6-6 6m-3-14-2 16', separator:'M4 12h16M12 7v10', group:'M4 4h16v16H4zM8 8h8v8H8z', columns:'M4 4h16v16H4zM12 4v16', gallery:'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z', cover:'M4 4h16v16H4zM4 15l5-5 4 4 3-3 4 5', table:'M4 4h16v16H4zM4 10h16M4 15h16M10 4v16M16 4v16', file:'M6 3h8l4 4v14H6zM14 3v5h5M9 13h6M9 17h6', audio:'M4 10v4m4-8v12m4-16v20m4-16v12m4-8v4', settings:'M4 7h16M4 17h16M8 7v0m8 10v0', indent:'M4 6h3m3 0h10M4 12h3m3 0h10M4 18h3m3 0h10M4 12l3-3m-3 3 3 3', outdent:'M4 6h16M4 12h10M4 18h16m3-6-3-3m3 3-3 3', outline:'M4 6h2M9 6h11M4 12h2M9 12h11M4 18h2M9 18h11', layers:'m12 3 9 5-9 5-9-5 9-5zm-9 9 9 5 9-5m-18 5 9 5 9-5', grip:'M9 5h.01M15 5h.01M9 12h.01M15 12h.01M9 19h.01M15 19h.01', transform:'M4 7h14m0 0-3-3m3 3-3 3M20 17H6m0 0 3-3m-3 3 3 3',
    check:'m5 12 4 4L19 6', search:'m20 20-4.5-4.5M18 10.5a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0', help:'M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3m.1 4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0', home:'m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z', spark:'m12 3 1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM19 15l1 2.5 2.5 1-2.5 1L19 22l-1-2.5-2.5-1 2.5-1z', share:'M8 12 16 7m-8 5 8 5M6 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm12-5a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm0 12a2 2 0 1 0 0-4 2 2 0 0 0 0 4z', expand:'M8 3H3v5m0-5 7 7m6-7h5v5m0-5-7 7M3 16v5h5m-5 0 7-7m11 2v5h-5m5 0-7-7', collapse:'M8 3v5H3m0 0 7-7m6 7V3h5m0 0-7 7M3 16h5v5m0 0 7-7m1 2h5v5m0 0-7-7', history:'M3 12a9 9 0 1 0 2.6-6.4L3 9m0-6v6h6m3-3v6l4 2'
  };
  const ACTION_ICONS = { 'toggle-inserter':'plus','import-file':'file','export-document':'file','paste-block':'copy',undo:'undo',redo:'redo',preview:'desktop','move-up':'up','move-down':'down',duplicate:'copy','copy-block':'copy',delete:'trash','file-media':'file','audio-media':'audio','video-media':'video','spacer-decrease':'up','spacer-increase':'down','separator-width-less':'alignLeft','separator-width-more':'alignRight',media:'image','insert-pattern':'layers',insert:'plus','save-selected-pattern':'layers','outline-select':'outline','format-mark':'bold','link-apply':'check','link-remove':'link','table-add-row':'plus','table-remove-row':'trash','table-add-column':'plus','table-remove-column':'trash','table-toggle-header':'table','indent':'indent','outdent':'outdent','toggle-fullscreen':'expand','builder-panel':'layers','align-text':'align-left' };
  const ICON_ONLY_ACTIONS = new Set(['align-text','import-file','export-document','toggle-inserter','paste-block','undo','redo','preview','toggle-preview','move-up','move-down','duplicate','copy-block','delete','file-media','audio-media','video-media','media','format-mark','link-apply','link-remove','table-add-row','table-remove-row','table-add-column','table-remove-column','table-toggle-header','indent','outdent','toggle-fullscreen','builder-panel','customize-block','save-selected-pattern','toggle-revision-history','toggle-find-replace']);
  const TYPE_ICONS = { 'core/paragraph':'paragraph','core/heading':'heading','core/list':'list','core/list-item':'list','core/image':'image','core/quote':'quote','core/video_embed':'video','core/video_file':'video','core/threejs_thumb':'threejs','core/button':'button','core/callout':'info','core/details':'details','core/spacer':'spacer','core/code':'code','core/separator':'separator','core/group':'group','core/columns':'columns','core/column':'columns','core/gallery':'gallery','core/cover':'cover','core/table':'table','core/table-row':'table','core/table-cell':'table','core/file':'file','core/audio':'audio' };
  const BLOCK_TOOL_DESCRIPTIONS = {
    pt:{'core/paragraph':'Adiciona texto corrido editável.','core/heading':'Adiciona um título com nível configurável.','core/list':'Cria uma lista com marcadores ou numeração.','core/image':'Insere imagem com legenda e texto alternativo.','core/quote':'Destaca uma citação.','core/video_embed':'Incorpora YouTube/Vimeo ou escolhe um vídeo local da biblioteca.','core/video_file':'Adiciona um vídeo da biblioteca local.','core/threejs_thumb':'Insere uma miniatura 3D configurável.','core/button':'Adiciona um botão com destino configurável.','core/callout':'Adiciona um aviso com tom selecionável.','core/details':'Cria uma seção expansível.','core/spacer':'Adiciona espaço vertical.','core/code':'Insere conteúdo pré-formatado; não executa o código.','core/separator':'Insere uma linha divisória.','core/group':'Agrupa outros blocos.','core/columns':'Cria um layout de duas ou três colunas.','core/gallery':'Cria uma galeria de imagens.','core/cover':'Cria uma capa com imagem e conteúdo.','core/table':'Cria uma tabela editável.','core/file':'Adiciona um link para arquivo.','core/audio':'Adiciona um player de áudio local.'},
    en:{'core/paragraph':'Add editable body text.','core/heading':'Add a heading with a configurable level.','core/list':'Create a bulleted or numbered list.','core/image':'Insert an image with caption and alt text.','core/quote':'Highlight a quotation.','core/video_embed':'Embed YouTube/Vimeo or choose a local video from the media library.','core/video_file':'Add a locally hosted video.','core/threejs_thumb':'Insert a configurable 3D thumbnail.','core/button':'Add a button with a configurable destination.','core/callout':'Add a notice with a selectable tone.','core/details':'Create an expandable section.','core/spacer':'Add vertical spacing.','core/code':'Insert preformatted content; code is not executed.','core/separator':'Insert a divider.','core/group':'Group other blocks.','core/columns':'Create a two- or three-column layout.','core/gallery':'Create an image gallery.','core/cover':'Create a cover with an image and content.','core/table':'Create an editable table.','core/file':'Add a file link.','core/audio':'Add a local audio player.'},
    es:{'core/paragraph':'Añade texto editable.','core/heading':'Añade un título con nivel configurable.','core/list':'Crea una lista con viñetas o numeración.','core/image':'Inserta imagen con leyenda y texto alternativo.','core/quote':'Destaca una cita.','core/video_embed':'Incorpora YouTube/Vimeo o elige un vídeo local de la biblioteca.','core/video_file':'Añade vídeo alojado localmente.','core/threejs_thumb':'Inserta una miniatura 3D configurable.','core/button':'Añade un botón con destino configurable.','core/callout':'Añade un aviso con tono seleccionable.','core/details':'Crea una sección desplegable.','core/spacer':'Añade espacio vertical.','core/code':'Inserta contenido preformateado; no ejecuta el código.','core/separator':'Inserta un separador.','core/group':'Agrupa otros bloques.','core/columns':'Crea un diseño de dos o tres columnas.','core/gallery':'Crea una galería de imágenes.','core/cover':'Crea una portada con imagen y contenido.','core/table':'Crea una tabla editable.','core/file':'Añade un enlace a un archivo.','core/audio':'Añade un reproductor de audio local.'}
  };
  const ACTION_TOOL_DESCRIPTIONS = {
    pt:{'export-document':'Baixa uma cópia JSON do documento BDRE.','toggle-inserter':'Abre o catálogo de blocos e padrões.','paste-block':'Cola o bloco copiado após a seleção.','undo':'Desfaz a última alteração.','redo':'Refaz a alteração desfeita.','toggle-revision-history':'Abre o histórico local de rascunhos.','toggle-find-replace':'Localiza e substitui texto no documento.','import-file':'Importa um documento compatível.','align-text':'Alinha o parágrafo selecionado.','link-apply':'Aplica link ao texto selecionado.','link-remove':'Remove o link do texto selecionado.','move-up':'Move o bloco uma posição para cima.','move-down':'Move o bloco uma posição para baixo.','duplicate':'Duplica o bloco selecionado.','copy-block':'Copia o bloco para este editor.','delete':'Remove o bloco selecionado.','customize-block':'Abre as opções de aparência do bloco.','save-selected-pattern':'Salva o bloco selecionado como padrão.','media':'Escolhe uma imagem na biblioteca.','file-media':'Escolhe um arquivo na biblioteca.','audio-media':'Escolhe um áudio na biblioteca.','video-media':'Escolhe um vídeo na biblioteca.','toggle-fullscreen':'Alterna o modo de tela cheia.','preview':'Muda a largura da prévia.','toggle-preview':'Alterna entre edição e prévia do documento.','builder-panel':'Abre ou fecha um painel do editor.'},
    en:{'export-document':'Download a JSON copy of the BDRE document.','toggle-inserter':'Open the block and pattern catalog.','paste-block':'Paste the copied block after the selection.','undo':'Undo the last change.','redo':'Redo the undone change.','toggle-revision-history':'Open local draft history.','toggle-find-replace':'Find and replace text in the document.','import-file':'Import a compatible document.','align-text':'Align the selected paragraph.','link-apply':'Apply a link to the selected text.','link-remove':'Remove the selected text link.','move-up':'Move the block up one position.','move-down':'Move the block down one position.','duplicate':'Duplicate the selected block.','copy-block':'Copy the block in this editor.','delete':'Remove the selected block.','customize-block':'Open block appearance options.','save-selected-pattern':'Save the selected block as a pattern.','media':'Choose an image from the library.','file-media':'Choose a file from the library.','audio-media':'Choose audio from the library.','video-media':'Choose video from the library.','toggle-fullscreen':'Toggle full-screen mode.','preview':'Change the preview width.','toggle-preview':'Switch between editing and document preview.','builder-panel':'Open or close an editor panel.'},
    es:{'export-document':'Descarga una copia JSON del documento BDRE.','toggle-inserter':'Abre el catálogo de bloques y patrones.','paste-block':'Pega el bloque copiado después de la selección.','undo':'Deshace el último cambio.','redo':'Rehace el cambio deshecho.','toggle-revision-history':'Abre el historial local de borradores.','toggle-find-replace':'Busca y reemplaza texto en el documento.','import-file':'Importa un documento compatible.','align-text':'Alinea el párrafo seleccionado.','link-apply':'Aplica un enlace al texto seleccionado.','link-remove':'Quita el enlace del texto seleccionado.','move-up':'Mueve el bloque una posición hacia arriba.','move-down':'Mueve el bloque una posición hacia abajo.','duplicate':'Duplica el bloque seleccionado.','copy-block':'Copia el bloque en este editor.','delete':'Elimina el bloque seleccionado.','customize-block':'Abre las opciones de apariencia del bloque.','save-selected-pattern':'Guarda el bloque seleccionado como patrón.','media':'Elige una imagen de la biblioteca.','file-media':'Elige un archivo de la biblioteca.','audio-media':'Elige audio de la biblioteca.','video-media':'Elige vídeo de la biblioteca.','toggle-fullscreen':'Alterna el modo de pantalla completa.','preview':'Cambia el ancho de la vista previa.','toggle-preview':'Alterna entre edición y vista previa del documento.','builder-panel':'Abre o cierra un panel del editor.'}
  };
  function getToolDescription(action, type, lang, extra) {
    const language=String(lang||'').toLowerCase().startsWith('es')?'es':(String(lang||'').toLowerCase().startsWith('en')?'en':'pt');
    if(type&&BLOCK_TOOL_DESCRIPTIONS[language][type])return BLOCK_TOOL_DESCRIPTIONS[language][type];
    if(action==='format-mark')return ({pt:'Aplica ou remove esta formatação do texto selecionado.',en:'Apply or remove this formatting on the selected text.',es:'Aplica o quita este formato del texto seleccionado.'})[language];
    if(action==='indent'||action==='outdent')return ({pt:'Altera o nível hierárquico do bloco selecionado.',en:'Change the hierarchy level of the selected block.',es:'Cambia el nivel jerárquico del bloque seleccionado.'})[language];
    if(action&&action.startsWith('table-'))return ({pt:'Altera a estrutura ou o cabeçalho da tabela selecionada.',en:'Change the selected table structure or header.',es:'Cambia la estructura o el encabezado de la tabla seleccionada.'})[language];
    return ACTION_TOOL_DESCRIPTIONS[language][action]||({pt:'Executa esta ferramenta no editor.',en:'Use this editor tool.',es:'Usa esta herramienta del editor.'})[language];
  }

  function validLocalImage(src) {
    if (typeof src !== 'string' || !src || /[\x00-\x20\\]/.test(src)) return false;
    try {
      const base = global.location && /^https?:/.test(global.location.origin) ? global.location.origin : 'https://local.invalid';
      const url = new URL(src, base);
      if (url.origin !== base) return false;
      if (url.search || url.hash) return false;
      const path = url.pathname.replace(/^\//, '');
      return (/^(?:image|view\/image\/blog|view\/image\/cms\/page)\//).test(path) && !path.split('/').some((part) => part === '.' || part === '..');
    } catch (_) { return false; }
  }

  function validLocalDownload(src, allowPageMedia) {
    const scope = allowPageMedia ? '(?:blog|cms\\/page)' : 'blog';
    if (typeof src !== 'string' || !new RegExp('^view\\/download\\/' + scope + '\\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$').test(src) || src.includes('..') || /[\x00-\x20\x7F\\?#]/.test(src)) return false;
    return ['pdf','docx','xlsx','pptx','odt','ods','csv','txt'].includes(src.split('.').pop().toLowerCase());
  }

  function validLocalAudio(src, allowPageMedia) {
    const scope = allowPageMedia ? '(?:blog|cms\\/page)' : 'blog';
    if (typeof src !== 'string' || !new RegExp('^view\\/audio\\/' + scope + '\\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$').test(src) || src.includes('..') || /[\x00-\x20\x7F\\?#]/.test(src)) return false;
    return ['mp3','ogg','wav'].includes(src.split('.').pop().toLowerCase());
  }

  function validLocalVideo(src, allowPageMedia) {
    const scope = allowPageMedia ? '(?:blog|cms\\/page)' : 'blog';
    if (typeof src !== 'string' || !new RegExp('^view\\/video\\/' + scope + '\\/[A-Za-z0-9][A-Za-z0-9._-]{0,179}$').test(src) || src.includes('..') || /[\x00-\x20\x7F\\?#]/.test(src)) return false;
    return ['mp4','webm','ogv'].includes(src.split('.').pop().toLowerCase());
  }

  function encodeBase64Utf8(value) {
    if (!global.TextEncoder || !global.btoa) return '';
    const bytes = new global.TextEncoder().encode(String(value));
    let binary = '';
    const chunkSize = 0x8000;
    for (let i = 0; i < bytes.length; i += chunkSize) {
      const chunk = bytes.subarray(i, Math.min(i + chunkSize, bytes.length));
      binary += String.fromCharCode.apply(null, chunk);
    }
    return global.btoa(binary);
  }

  function validThreeJsSceneJson(raw) {
    if (typeof raw !== 'string' || raw.length > 16384) return false;
    let s; try { s = JSON.parse(raw); } catch (_) { return false; }
    const color = (v) => typeof v === 'string' && /^#[A-Fa-f0-9]{6}$/.test(v);
    const between = (v, min, max) => typeof v === 'number' && Number.isFinite(v) && v >= min && v <= max;
    const vector = (v, min, max) => Array.isArray(v) && v.length === 3 && v.every((x) => between(x,min,max));
    const only = (obj, keys) => obj && typeof obj === 'object' && !Array.isArray(obj) && Object.keys(obj).every((k) => keys.includes(k));
    if (!only(s,['version','background','camera','objects','lights','auto_rotate','fog']) || s.version !== 1 || !color(s.background) || typeof s.auto_rotate !== 'boolean' ||
        !only(s.camera,['fov','position']) || !between(s.camera.fov,20,80) || !vector(s.camera.position,-20,20) || !Array.isArray(s.objects) || s.objects.length < 1 || s.objects.length > 12 || !Array.isArray(s.lights) || s.lights.length < 1 || s.lights.length > 4) return false;
    if (!s.objects.every((o) => only(o,['type','color','position','rotation','scale','animation','roughness','metalness','wireframe']) &&
        ['box','sphere','torus','cone','cylinder','icosahedron','torusKnot'].includes(o.type) && color(o.color) && vector(o.position,-20,20) && vector(o.rotation,-6.3,6.3) && between(o.scale,0.1,5) && ['none','spin','bob','pulse'].includes(o.animation) &&
        (o.roughness === undefined || between(o.roughness,0,1)) && (o.metalness === undefined || between(o.metalness,0,1)) && (o.wireframe === undefined || typeof o.wireframe === 'boolean'))) return false;
    if (!s.lights.every((l) => only(l,['type','color','intensity','position']) && ['ambient','directional','point'].includes(l.type) && color(l.color) && between(l.intensity,0,5) &&
        (l.type === 'ambient' ? l.position === undefined : vector(l.position,-20,20)))) return false;
    if (s.fog !== undefined && (!only(s.fog,['color','near','far']) || !color(s.fog.color) || !between(s.fog.near,0.1,50) || !between(s.fog.far,s.fog.near+0.1,150))) return false;
    return true;
  }

  function classifyKeyboardShortcut(event, context) {
    if (!event || !context) return null;
    const key = String(event.key || '').toLowerCase();
    const modifier = !!(event.ctrlKey || event.metaKey) && !event.altKey;
    if (modifier && !context.inFormControl) {
      if (key === 'z') return event.shiftKey ? 'redo' : 'undo';
      if (key === 'y') return 'redo';
      if (key === 'f' && (context.inTextEditor || context.inBlock)) return 'find';
    }
    if (modifier && context.inTextEditor && !context.inFormControl) {
      const marks = { b: 'bold', i: 'italic', u: 'underline' };
      if (marks[key]) return marks[key];
      if (key === 'k') return 'link';
    }
    if (event.altKey && !event.ctrlKey && !event.metaKey && !context.inFormControl && context.inBlock) {
      if (key === 'arrowup') return 'move-up';
      if (key === 'arrowdown') return 'move-down';
    }
    return null;
  }

  function cloneBlockWithNewIds(source, makeId) {
    if (!source || typeof source !== 'object' || typeof makeId !== 'function') return null;
    let copy;
    try { copy = CLONE(source); } catch (_) { return null; }
    const seen = new Set();
    function refresh(node) {
      if (!node || typeof node !== 'object' || !Array.isArray(node.children)) return false;
      const id = makeId();
      if (typeof id !== 'string' || !/^[A-Za-z0-9_-]{1,96}$/.test(id) || seen.has(id)) return false;
      seen.add(id); node.id = id;
      return node.children.every(refresh);
    }
    return refresh(copy) ? copy : null;
  }

  function findNodeChain(nodes, targetId) {
    if (!Array.isArray(nodes) || typeof targetId !== 'string') return [];
    for (const node of nodes) {
      if (!node || typeof node !== 'object') continue;
      if (node.id === targetId) return [node];
      const childPath = findNodeChain(node.children, targetId);
      if (childPath.length) return [node].concat(childPath);
    }
    return [];
  }

  function getOutlineNavigationTarget(nodes, currentId, key) {
    const visible = [];
    function visit(items, parentId) {
      (Array.isArray(items) ? items : []).forEach((node) => {
        if (!node || typeof node !== 'object' || ['core/table-row','core/table-cell'].includes(node.type)) return;
        const item = { id: node.id, parentId: parentId || null, firstChildId: null };
        visible.push(item);
        const children = (Array.isArray(node.children) ? node.children : []).filter((child) => child && !['core/table-row','core/table-cell'].includes(child.type));
        if (children.length) item.firstChildId = children[0].id;
        visit(children, node.id);
      });
    }
    visit(nodes, null);
    const index = visible.findIndex((item) => item.id === currentId);
    if (index < 0) return null;
    const item = visible[index];
    if (key === 'ArrowUp') return index > 0 ? visible[index - 1].id : null;
    if (key === 'ArrowDown') return index < visible.length - 1 ? visible[index + 1].id : null;
    if (key === 'ArrowLeft') return item.parentId;
    if (key === 'ArrowRight') return item.firstChildId;
    if (key === 'Home') return visible.length ? visible[0].id : null;
    if (key === 'End') return visible.length ? visible[visible.length - 1].id : null;
    return null;
  }

  function validEmbed(url) {
    try {
      const parsed = new URL(url);
      return parsed.protocol === 'https:' && ['youtube.com', 'www.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com'].includes(parsed.hostname.toLowerCase());
    } catch (_) { return false; }
  }

  function validActionUrl(value) {
    if (typeof value !== 'string' || !value.trim() || /[\x00-\x20\\]/.test(value)) return false;
    if (/^#[a-z][a-z0-9-]{0,79}$/.test(value)) return true;
    if (value.startsWith('/') && !value.startsWith('//')) return true;
    try { const parsed = new URL(value); return ['https:', 'http:'].includes(parsed.protocol) && !parsed.username && !parsed.password; } catch (_) { return false; }
  }

  function safeActionUrl(value) {
    return validActionUrl(value) ? value : null;
  }

  const INLINE_MARKS = ['bold', 'italic', 'underline', 'strike', 'code'];
  const BLOCK_TEXT_TYPES = new Set(['core/paragraph', 'core/heading', 'core/list-item', 'core/quote', 'core/button', 'core/callout', 'core/details', 'core/table-cell', 'core/file', 'core/audio']);
  const INLINE_TAG_MARK = { STRONG: 'bold', B: 'bold', EM: 'italic', I: 'italic', U: 'underline', S: 'strike', DEL: 'strike', CODE: 'code' };

  function runsFromText(text) {
    return text === '' ? [] : [{ text: String(text), marks: [], href: null }];
  }

  function canonicalRuns(runs) {
    const markOrder = new Map(INLINE_MARKS.map((mark, index) => [mark, index]));
    const out = [];
    (Array.isArray(runs) ? runs : []).forEach((run) => {
      const marks = (run.marks || []).slice().sort((a, b) => (markOrder.get(a) || 0) - (markOrder.get(b) || 0));
      const next = { text: String(run.text || ''), marks, href: run.href == null ? null : String(run.href) };
      const last = out[out.length - 1];
      if (last && JSON.stringify(last.marks) === JSON.stringify(next.marks) && last.href === next.href) last.text += next.text;
      else out.push(next);
    });
    return out;
  }

  let unicodeWordCharacter;
  try { unicodeWordCharacter = new RegExp('^[\\p{L}\\p{N}\\p{M}_]$', 'u'); } catch (_) { unicodeWordCharacter = null; }
  function isWordCharacter(character) { return !!character && (unicodeWordCharacter ? unicodeWordCharacter.test(character) : (/[A-Za-z0-9_]/.test(character) || character.codePointAt(0) > 127)); }
  function charBefore(text, index) { if (index <= 0) return ''; const low=text.charCodeAt(index-1); if (low>=0xDC00&&low<=0xDFFF&&index>1) { const high=text.charCodeAt(index-2); if(high>=0xD800&&high<=0xDBFF)return text.slice(index-2,index); } return text.slice(index-1,index); }
  function charAfter(text, index) { if(index>=text.length)return ''; const point=text.codePointAt(index); return String.fromCodePoint(point); }

  function normalizeSearchText(text) {
    const normalizedParts=[],sourceStarts=[],sourceEnds=[];
    const append=(segment,start,end)=>{const value=segment.normalize('NFC');normalizedParts.push(value);for(let i=0;i<value.length;i++){sourceStarts.push(start);sourceEnds.push(end);}};
    if(typeof Intl!=='undefined'&&typeof Intl.Segmenter==='function'){
      const segmenter=new Intl.Segmenter(undefined,{granularity:'grapheme'});
      for(const item of segmenter.segment(text))append(item.segment,item.index,item.index+item.segment.length);
    } else {
      let cursor=0,cluster='',clusterStart=0,marks=null;
      try { marks=new RegExp('^\\p{M}$','u'); } catch (_) { marks=null; }
      for(const character of text){
        const width=character.length, code=character.codePointAt(0), isMark=marks ? marks.test(character) : (code>=0x300&&code<=0x36F || code>=0x1AB0&&code<=0x1AFF || code>=0x1DC0&&code<=0x1DFF || code>=0x20D0&&code<=0x20FF || code>=0xFE20&&code<=0xFE2F);
        if(!cluster||isMark){if(!cluster)clusterStart=cursor;cluster+=character;}
        else {append(cluster,clusterStart,cursor);cluster=character;clusterStart=cursor;}
        cursor+=width;
      }
      if(cluster)append(cluster,clusterStart,cursor);
    }
    return {text:normalizedParts.join(''),starts:sourceStarts,ends:sourceEnds};
  }

  function findTextMatchesInRuns(runs, query, caseSensitive, wholeWord) {
    if(!Array.isArray(runs)||!query)return [];
    const parts=runs.map((run)=>String(run && run.text || '')), sourceText=parts.join(''), normalized=normalizeSearchText(sourceText), text=normalized.text, normalizedQuery=query.normalize('NFC'), matches=[];
    const locate=(absolute,isEnd)=>{let cursor=0;for(let i=0;i<parts.length;i++){const next=cursor+parts[i].length;if(isEnd ? (absolute>cursor&&absolute<=next) : (absolute>=cursor&&absolute<next))return {runIndex:i,offset:absolute-cursor};cursor=next;}return null;};
    const add=(at,normalizedLength)=>{const normalizedEnd=at+normalizedLength;if((at>0&&normalized.starts[at]===normalized.starts[at-1])||(normalizedEnd<normalized.ends.length&&normalized.ends[normalizedEnd-1]===normalized.ends[normalizedEnd]))return;const start=normalized.starts[at],end=normalized.ends[normalizedEnd-1],length=end-start;if(!Number.isInteger(start)||!Number.isInteger(end)||end<=start)return;if(wholeWord){const starts=isWordCharacter(charAfter(normalizedQuery,0)),ends=isWordCharacter(charBefore(normalizedQuery,normalizedQuery.length));if((starts&&isWordCharacter(charBefore(sourceText,start)))||(ends&&isWordCharacter(charAfter(sourceText,end))))return;}const a=locate(start,false),b=locate(end,true);if(a&&b)matches.push({runIndex:a.runIndex,start:a.offset,endRunIndex:b.runIndex,endOffset:b.offset,length,absoluteStart:start});};
    if(caseSensitive){let from=0,at;while((at=text.indexOf(normalizedQuery,from))!==-1&&matches.length<=1000){add(at,normalizedQuery.length);from=at+Math.max(1,normalizedQuery.length);}}
    else {const escaped=normalizedQuery.replace(/[.*+?^${}()|[\]\\]/g,'\\$&');let re;try{re=new RegExp(escaped,'giu');}catch(_){return [];}let found;while((found=re.exec(text))!==null&&matches.length<=1000){add(found.index,found[0].length);if(!found[0].length)re.lastIndex+=1;}}
    return matches;
  }

  function documentStatistics(blocks, locale) {
    const texts=[];
    const visit=(nodes)=>{(Array.isArray(nodes)?nodes:[]).forEach((node)=>{
      if(node&&BLOCK_TEXT_TYPES.has(node.type)){
        const runs=Array.isArray(node.content)?node.content:[];
        texts.push(runs.map((run)=>String(run&&run.text||'')).join(''));
      } else if(node&&node.type==='core/code'&&typeof node.content==='string') texts.push(node.content);
      if(node&&Array.isArray(node.children))visit(node.children);
    });};
    visit(blocks);
    let words=0,characters=0;
    let wordSegmenter=null,charSegmenter=null;
    if(typeof Intl!=='undefined'&&typeof Intl.Segmenter==='function'){
      try{wordSegmenter=new Intl.Segmenter(locale||undefined,{granularity:'word'});}catch(_){try{wordSegmenter=new Intl.Segmenter(undefined,{granularity:'word'});}catch(__){wordSegmenter=null;}}
      try{charSegmenter=new Intl.Segmenter(locale||undefined,{granularity:'grapheme'});}catch(_){try{charSegmenter=new Intl.Segmenter(undefined,{granularity:'grapheme'});}catch(__){charSegmenter=null;}}
    }
    let wordFallback;try{wordFallback=new RegExp('[\\p{L}\\p{N}\\p{M}]+','gu');}catch(_){wordFallback=/\\S+/g;}
    texts.forEach((text)=>{
      if(wordSegmenter){for(const part of wordSegmenter.segment(text))if(part.isWordLike)words++;}
      else {let found;while((found=wordFallback.exec(text))!==null)words++;wordFallback.lastIndex=0;}
      if(charSegmenter){for(const _part of charSegmenter.segment(text))characters++;}
      else characters+=Array.from(text).length;
    });
    return {words,characters};
  }

  function replaceTextMatchInRuns(runs, match, replacement) {
    const content=CLONE(runs); const first=content[match.runIndex],last=content[match.endRunIndex];
    if(!first||!last||!Number.isInteger(match.start)||!Number.isInteger(match.endOffset)||match.start<0||match.endOffset<0) return content;
    const firstText=String(first.text||''),lastText=String(last.text||'');
    if(match.runIndex===match.endRunIndex){
      content[match.runIndex]={text:firstText.slice(0,match.start)+String(replacement)+firstText.slice(match.endOffset),marks:Array.isArray(first.marks)?first.marks.slice():[],href:first.href==null?null:first.href};
    } else {
      if(match.endRunIndex<match.runIndex||match.endRunIndex>=content.length)return content;
      content[match.runIndex]={text:firstText.slice(0,match.start)+String(replacement),marks:Array.isArray(first.marks)?first.marks.slice():[],href:first.href==null?null:first.href};
      content[match.endRunIndex]={text:lastText.slice(match.endOffset),marks:Array.isArray(last.marks)?last.marks.slice():[],href:last.href==null?null:last.href};
      content.splice(match.runIndex+1,match.endRunIndex-match.runIndex-1);
    }
    return canonicalRuns(content);
  }

  function upgradeDocument(input) {
    const doc = CLONE(input);
    if (!doc || doc.format_version !== 1 || !Array.isArray(doc.blocks)) return doc;
    const hasV2OnlyAudio = (nodes) => Array.isArray(nodes) && nodes.some((node) => node && (node.type === 'core/audio' || hasV2OnlyAudio(node.children)));
    if (hasV2OnlyAudio(doc.blocks)) return doc;
    function visit(node) {
      if (!node || typeof node !== 'object') return;
      if (BLOCK_TEXT_TYPES.has(node.type) && typeof node.content === 'string') node.content = runsFromText(node.content);
      if (Array.isArray(node.children)) node.children.forEach(visit);
    }
    doc.blocks.forEach(visit);
    doc.format_version = 2;
    return doc;
  }

  function utf8Length(value) {
    try {
      const text = String(value);
      if (typeof TextEncoder !== 'undefined') return new TextEncoder().encode(text).length;
      return encodeURIComponent(text).replace(/%[0-9A-F]{2}/gi, 'x').length;
    } catch (_) { return Infinity; }
  }

  function validInlineRuns(runs, type) {
    if (!Array.isArray(runs) || runs.length > 1000) return false;
    let chars = 0;
    return runs.every((run) => {
      if (!run || typeof run !== 'object' || Array.isArray(run) || Object.keys(run).sort().join(',') !== 'href,marks,text') return false;
      if (typeof run.text !== 'string' || run.text.length === 0 || utf8Length(run.text) > 262144 || !Array.isArray(run.marks) || run.marks.length > INLINE_MARKS.length) return false;
      chars += utf8Length(run.text);
      if (chars > 262144 || new Set(run.marks).size !== run.marks.length || run.marks.some((mark) => !INLINE_MARKS.includes(mark))) return false;
      if (run.href !== null && (!validActionUrl(run.href) || ['core/button','core/file','core/audio'].includes(type))) return false;
      return true;
    });
  }

  const APPEARANCE_KEYS = new Set(['text_color','background_color','border_color','border_width','border_style','border_radius','padding','margin','font_size','text_align']);
  function validBlockAppearance(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value) || !Object.keys(value).length || Object.keys(value).some((key) => !APPEARANCE_KEYS.has(key))) return false;
    const color = (item) => typeof item === 'string' && /^#[0-9a-fA-F]{6}$/.test(item);
    if (value.text_color !== undefined && !color(value.text_color)) return false;
    if (value.background_color !== undefined && !color(value.background_color)) return false;
    if (value.border_color !== undefined && !color(value.border_color)) return false;
    if (value.border_width !== undefined && (!Number.isInteger(value.border_width) || value.border_width < 0 || value.border_width > 8)) return false;
    if (value.border_style !== undefined && !['solid','dashed','dotted'].includes(value.border_style)) return false;
    if (value.border_radius !== undefined && (!Number.isInteger(value.border_radius) || value.border_radius < 0 || value.border_radius > 32)) return false;
    if (value.padding !== undefined && (!Number.isInteger(value.padding) || value.padding < 0 || value.padding > 64)) return false;
    if (value.margin !== undefined && (!Number.isInteger(value.margin) || value.margin < 0 || value.margin > 48)) return false;
    if (value.font_size !== undefined && (!Number.isInteger(value.font_size) || value.font_size < 12 || value.font_size > 48)) return false;
    if (value.text_align !== undefined && !['left','center','right'].includes(value.text_align)) return false;
    return true;
  }

  function appearanceStyleMap(appearance) {
    const value=validBlockAppearance(appearance)?appearance:{}; const style={};
    if(value.text_color)style.color=value.text_color;
    if(value.background_color)style.backgroundColor=value.background_color;
    if(value.border_width>0||(value.border_width===undefined&&value.border_color)){style.borderColor=value.border_color||'#d0d5dd';style.borderStyle=value.border_style||'solid';style.borderWidth=(value.border_width>0?value.border_width:1)+'px';}
    if(value.border_radius>0)style.borderRadius=value.border_radius+'px';
    if(value.padding>0)style.padding=value.padding+'px';
    if(value.margin>0)style.margin=value.margin+'px';
    if(value.font_size)style.fontSize=value.font_size+'px';
    if(value.text_align)style.textAlign=value.text_align;
    return style;
  }

  function tableGridInfo(table) {
    const rows=table&&Array.isArray(table.children)?table.children:[];
    if(!rows.length||rows.length>20)return null;
    const grid=Array.from({length:rows.length},()=>Array(12).fill(null));
    const origins=[];let width=0;
    for(let rowIndex=0;rowIndex<rows.length;rowIndex+=1){
      const row=rows[rowIndex];if(!row||row.type!=='core/table-row'||!Array.isArray(row.children)||row.children.length>12)return null;
      let col=0;
      for(const cell of row.children){
        if(!cell||cell.type!=='core/table-cell'||!cell.attributes||typeof cell.attributes!=='object'||Array.isArray(cell.attributes))return null;
        const attrs=cell.attributes;
        const rowspan=attrs.rowspan===undefined?1:attrs.rowspan;
        const colspan=attrs.colspan===undefined?1:attrs.colspan;
        if(!Number.isInteger(rowspan)||rowspan<1||rowspan>rows.length||!Number.isInteger(colspan)||colspan<1||colspan>12||rowIndex+rowspan>rows.length)return null;
        while(col<12&&grid[rowIndex][col]!==null)col+=1;
        if(col+colspan>12)return null;
        let overlap=false;
        for(let r=rowIndex;r<rowIndex+rowspan;r+=1)for(let c=col;c<col+colspan;c+=1)if(grid[r][c]!==null)overlap=true;
        if(overlap)return null;
        for(let r=rowIndex;r<rowIndex+rowspan;r+=1)for(let c=col;c<col+colspan;c+=1)grid[r][c]=cell;
        origins.push({cell,row:rowIndex,col,rowspan,colspan});width=Math.max(width,col+colspan);col+=colspan;
      }
    }
    if(width<1||width>12)return null;
    for(let r=0;r<rows.length;r+=1)for(let c=0;c<width;c+=1)if(!grid[r][c])return null;
    return {grid,origins,width,height:rows.length};
  }

  function tableMergeSelection(table, selectedIds) {
    const info=tableGridInfo(table),selected=new Set(Array.from(selectedIds||[]));
    if(!info||selected.size<2)return null;
    const cells=info.origins.filter((origin)=>selected.has(origin.cell.id));
    if(cells.length!==selected.size)return null;
    const top=Math.min(...cells.map((item)=>item.row)),left=Math.min(...cells.map((item)=>item.col));
    const bottom=Math.max(...cells.map((item)=>item.row+item.rowspan-1)),right=Math.max(...cells.map((item)=>item.col+item.colspan-1));
    if(cells.some((item)=>item.row<top||item.col<left||item.row+item.rowspan-1>bottom||item.col+item.colspan-1>right))return null;
    for(let r=top;r<=bottom;r+=1)for(let c=left;c<=right;c+=1){const cell=info.grid[r][c];if(!cell||!selected.has(cell.id))return null;}
    cells.sort((a,b)=>a.row-b.row||a.col-b.col);
    const anchor=cells.find((item)=>item.row===top&&item.col===left);
    if(!anchor)return null;
    return {info,cells,anchor,top,left,bottom,right,rowspan:bottom-top+1,colspan:right-left+1};
  }

  function tableCellIsHeader(table,rowIndex,cell) {
    if(cell&&cell.attributes&&typeof cell.attributes.header==='boolean')return cell.attributes.header;
    return !!(table&&table.attributes&&table.attributes.header&&rowIndex===0);
  }

  function splitTableCells(table,selectedIds,createCell) {
    const info=tableGridInfo(table),selected=new Set(Array.from(selectedIds||[]));
    if(!info)return false;
    const entries=info.origins.map((origin)=>({cell:origin.cell,row:origin.row,col:origin.col,rowspan:origin.rowspan,colspan:origin.colspan}));
    const toSplit=entries.filter((entry)=>selected.has(entry.cell.id)&&(entry.rowspan>1||entry.colspan>1));
    if(!toSplit.length)return false;
    toSplit.forEach((entry)=>{entry.rowspan=1;entry.colspan=1;delete entry.cell.attributes.rowspan;delete entry.cell.attributes.colspan;});
    const occupied=Array.from({length:info.height},()=>Array(info.width).fill(false));
    for(const entry of entries){
      for(let r=entry.row;r<entry.row+entry.rowspan;r+=1)for(let c=entry.col;c<entry.col+entry.colspan;c+=1){if(r>=info.height||c>=info.width||occupied[r][c])return false;occupied[r][c]=true;}
    }
    for(let r=0;r<info.height;r+=1)for(let c=0;c<info.width;c+=1)if(!occupied[r][c]){
      const previous=info.grid[r][c];const cell=createCell(tableCellIsHeader(table,r,previous));entries.push({cell,row:r,col:c,rowspan:1,colspan:1});occupied[r][c]=true;
    }
    table.children.forEach((row,rowIndex)=>{row.children=entries.filter((entry)=>entry.row===rowIndex).sort((a,b)=>a.col-b.col).map((entry)=>entry.cell);});
    return !!tableGridInfo(table);
  }

  function validateDocument(doc, allowPageMedia) {
    const errors = [];
    if (!doc || typeof doc !== 'object' || Array.isArray(doc) || Object.keys(doc).sort().join(',') !== 'blocks,format_version' || ![1,2].includes(doc.format_version) || !Array.isArray(doc.blocks)) return { valid: false, errors: ['root_shape_invalid'] };
    const version = doc.format_version;
    if (doc.blocks.length > 1000) return { valid: false, errors: ['too_many_blocks'] };
    const ids = new Set(), headingAnchors=new Set(); let nodes = 0;
    function visit(node, depth, parent) {
      nodes += 1;
      if (!node || typeof node !== 'object' || Array.isArray(node) || depth > 16 || nodes > 2000 || Object.keys(node).sort().join(',') !== 'attributes,children,content,id,type') { errors.push('node_shape_or_limit'); return; }
      if (typeof node.id !== 'string' || !/^[A-Za-z0-9_-]{1,96}$/.test(node.id) || ids.has(node.id)) errors.push('node_id_invalid');
      else ids.add(node.id);
      if (typeof node.type !== 'string' || !TYPES.some(([type]) => type === node.type) && node.type !== 'core/list-item' && node.type !== 'core/column' && node.type !== 'core/table-row' && node.type !== 'core/table-cell') errors.push('unknown_type');
      if (!node.attributes || typeof node.attributes !== 'object' || Array.isArray(node.attributes) || !Array.isArray(node.children)) { errors.push('node_fields_invalid'); return; }
      const textBlock = BLOCK_TEXT_TYPES.has(node.type);
      if (version === 1 && typeof node.content !== 'string') errors.push('legacy_content_invalid');
      else if (version === 2 && textBlock && !validInlineRuns(node.content, node.type)) errors.push('rich_content_invalid');
      else if ((!textBlock || version === 1) && (typeof node.content !== 'string' || (!textBlock && version === 2 && node.type !== 'core/code' && node.content !== ''))) errors.push('content_invalid');
      if (typeof node.content === 'string' && node.content.length > 262144) errors.push('content_too_large');
      const a = node.attributes;
      const allowedAttributes = {
        'core/paragraph':['align'], 'core/heading':['level'], 'core/list':['ordered'], 'core/list-item':[],
        'core/quote':[], 'core/callout':['tone'], 'core/details':['summary'], 'core/spacer':['height'], 'core/code':[], 'core/image':['src','alt','title'], 'core/video_embed':['url'], 'core/button':['url'],
        'core/separator':[], 'core/group':[], 'core/columns':['count'], 'core/column':[],
        'core/gallery':['columns','align'], 'core/cover':['src','alt','align'], 'core/table':['header'], 'core/table-row':[], 'core/table-cell':[], 'core/file':['src'], 'core/audio':['src'], 'core/video_file':['src'], 'core/threejs_thumb':['scene_json','label','custom_js']
      };
      if (version === 2) {
        allowedAttributes['core/heading'] = ['level','anchor'];
        allowedAttributes['core/image'] = ['src','alt','title','caption','align','size','mobile_size'];
        allowedAttributes['core/separator'] = ['style','width','thickness','align'];
        allowedAttributes['core/video_embed'] = ['url','src'];
        allowedAttributes['core/video_file'] = ['src','title','caption','align','size','mobile_size','controls','loop','muted','autoplay'];
        allowedAttributes['core/group'] = ['outline_label','toc_generated'];
        allowedAttributes['core/table'] = ['header'];
        allowedAttributes['core/table-cell'] = ['header','rowspan','colspan'];
        Object.keys(allowedAttributes).forEach((type) => { allowedAttributes[type] = allowedAttributes[type].concat('appearance'); });
      }
      const allowed = allowedAttributes[node.type] || [];
      if (Object.keys(a).some((key) => !allowed.includes(key))) errors.push('attributes_not_allowlisted');
      if (version === 2 && a.appearance !== undefined && !validBlockAppearance(a.appearance)) errors.push('appearance_invalid');
      if(node.type==='core/heading'&&a.anchor!==undefined){if(version!==2||typeof a.anchor!=='string'||!/^[a-z][a-z0-9-]{0,79}$/.test(a.anchor))errors.push('heading_anchor_invalid');else if(headingAnchors.has(a.anchor))errors.push('heading_anchor_duplicate');else headingAnchors.add(a.anchor);}
      if(node.type==='core/group'&&a.toc_generated!==undefined&&typeof a.toc_generated!=='boolean')errors.push('toc_marker_invalid');
      if (version === 1 && ['core/gallery','core/cover'].includes(node.type)) errors.push('media_block_requires_v2');
      if (version === 1 && node.type === 'core/callout') errors.push('callout_requires_v2');
      if (version === 1 && node.type === 'core/details') errors.push('details_requires_v2');
      if (version === 1 && node.type === 'core/spacer') errors.push('spacer_requires_v2');
      if (version === 1 && node.type === 'core/code') errors.push('code_requires_v2');
      if (version === 1 && node.type === 'core/table') errors.push('table_requires_v2');
      if (version === 1 && (node.type === 'core/audio' || node.type === 'core/video_file' || node.type === 'core/threejs_thumb')) errors.push('media_requires_v2');
      switch (node.type) {
        case 'core/paragraph': if (!['', 'left', 'center', 'right'].includes(a.align || '')) errors.push('align_invalid'); if (node.children.length) errors.push('paragraph_children'); break;
        case 'core/heading': if (![1,2,3,4,5,6].includes(a.level) || node.children.length) errors.push('heading_invalid'); break;
        case 'core/list': if (typeof a.ordered !== 'boolean' || node.children.length < 1 || node.children.some((child) => !child || child.type !== 'core/list-item')) errors.push('list_invalid'); break;
        case 'core/list-item': if (parent !== 'core/list' || node.children.length) errors.push('list_item_invalid'); break;
        case 'core/quote': if (node.children.length) errors.push('quote_children'); break;
        case 'core/callout': if (node.children.length || !['info','success','warning','danger'].includes(a.tone)) errors.push('callout_invalid'); break;
        case 'core/details': if (node.children.length || typeof a.summary !== 'string' || !a.summary.trim() || utf8Length(a.summary) > 512) errors.push('details_invalid'); break;
        case 'core/spacer': if (node.children.length || ![16,32,48,64,96].includes(a.height)) errors.push('spacer_invalid'); break;
        case 'core/code': if (node.children.length || typeof node.content !== 'string' || utf8Length(node.content) > 262144) errors.push('code_invalid'); break;
        case 'core/file': {
          const label = Array.isArray(node.content) ? node.content.map((run) => run && typeof run.text === 'string' ? run.text : '').join('').trim() : '';
          if (version !== 2 || !validLocalDownload(a.src, allowPageMedia) || !label || node.children.length || !validInlineRuns(node.content, node.type)) errors.push('file_invalid');
          break;
        }
        case 'core/video_file': if (version !== 2 || !validLocalVideo(a.src, allowPageMedia) || node.children.length || node.content || (a.title !== undefined && (typeof a.title !== 'string' || utf8Length(a.title)>4096)) || (a.caption !== undefined && (typeof a.caption !== 'string' || utf8Length(a.caption)>4096)) || (a.align !== undefined && !['left','center','right','wide','full'].includes(a.align)) || (a.size !== undefined && !['small','medium','large','full'].includes(a.size)) || (a.mobile_size !== undefined && !['full','large','medium'].includes(a.mobile_size)) || ['controls','loop','muted','autoplay'].some((key)=>a[key]!==undefined&&typeof a[key]!=='boolean')) errors.push('video_file_invalid'); break;
        case 'core/threejs_thumb': if (version !== 2 || !validThreeJsSceneJson(a.scene_json) || typeof a.label !== 'string' || !a.label.trim() || utf8Length(a.label) > 160 || (a.custom_js !== undefined && (typeof a.custom_js !== 'string' || utf8Length(a.custom_js) > 8192)) || node.children.length || node.content) errors.push('threejs_thumb_invalid'); break;
        case 'core/audio': {
          const label = Array.isArray(node.content) ? node.content.map((run) => run && typeof run.text === 'string' ? run.text : '').join('').trim() : '';
          if (version !== 2 || !validLocalAudio(a.src, allowPageMedia) || !label || node.children.length || !validInlineRuns(node.content, node.type)) errors.push('audio_invalid');
          break;
        }
        case 'core/image':
          if (!validLocalImage(a.src) || typeof a.alt !== 'string' || node.children.length || node.content) errors.push('image_invalid');
          ['alt','title','caption'].forEach((key) => { if (a[key] !== undefined && (typeof a[key] !== 'string' || utf8Length(a[key]) > 4096)) errors.push('image_text_invalid'); });
          if (a.align !== undefined && !['left','center','right','wide','full'].includes(a.align)) errors.push('image_alignment_invalid');
          if (a.size !== undefined && !['small','medium','large','full'].includes(a.size)) errors.push('image_size_invalid');
          if (a.mobile_size !== undefined && !['full','large','medium'].includes(a.mobile_size)) errors.push('image_mobile_size_invalid');
          break;
        case 'core/gallery': if (version !== 2 || node.content || ![2,3,4].includes(a.columns) || !['content','wide','full'].includes(a.align) || node.children.length < 1 || node.children.length > 20 || node.children.some((child) => !child || child.type !== 'core/image')) errors.push('gallery_invalid'); break;
        case 'core/cover':
          if (version !== 2 || !validLocalImage(a.src) || typeof a.alt !== 'string' || utf8Length(a.alt) > 4096 || !['content','wide','full'].includes(a.align) || node.content || node.children.length < 1 || node.children.length > 12 || node.children.some((child) => !child || !['core/heading','core/paragraph','core/quote','core/button'].includes(child.type))) errors.push('cover_invalid');
          break;
        case 'core/video_embed': { const local=version===2&&validLocalVideo(a.src,allowPageMedia); const external=validEmbed(a.url)&&(!a.src||a.src===''); if ((!local&&!external)|| (local&&a.url&&a.url!=='') || node.children.length || node.content) errors.push('embed_invalid'); break; }
        case 'core/button': if (!validActionUrl(a.url) || node.children.length) errors.push('button_invalid'); break;
        case 'core/separator': if (node.children.length || node.content || (a.style!==undefined&&!SEPARATOR_STYLES.includes(a.style)) || (a.width!==undefined&&(!Number.isInteger(a.width)||!SEPARATOR_WIDTHS.includes(a.width))) || (a.thickness!==undefined&&(!Number.isInteger(a.thickness)||!SEPARATOR_THICKNESSES.includes(a.thickness))) || (a.align!==undefined&&!['left','center','right'].includes(a.align))) errors.push('separator_invalid'); break;
        case 'core/group':
          if (node.content || Object.keys(a).some((key) => !['outline_label','toc_generated'].includes(key)) || node.children.length === 0) errors.push('group_invalid');
          if (a.outline_label !== undefined && (typeof a.outline_label !== 'string' || utf8Length(a.outline_label) > 160)) errors.push('outline_label_invalid');
          break;
        case 'core/columns': if (node.content || Object.keys(a).some((key) => key !== 'count') || ![2,3].includes(a.count) || node.children.length !== a.count || node.children.some((child) => !child || child.type !== 'core/column')) errors.push('columns_invalid'); break;
        case 'core/column': if (parent !== 'core/columns' || node.content || Object.keys(a).length || node.children.length === 0) errors.push('column_invalid'); break;
        case 'core/table': {
          let tableBytes = 0;
          node.children.forEach((row) => { if (!row || !Array.isArray(row.children)) return; row.children.forEach((cell) => { if (!cell || !Array.isArray(cell.content)) return; cell.content.forEach((run) => { if (run && typeof run.text === 'string') tableBytes += utf8Length(run.text); }); }); });
          if (version !== 2 || typeof a.header !== 'boolean' || node.content || node.children.length < 1 || node.children.length > 20 || tableBytes > 262144 || !tableGridInfo(node)) errors.push('table_invalid');
          break;
        }
        case 'core/table-row': if (parent !== 'core/table' || node.content || Object.keys(a).length || node.children.length > 12 || node.children.some((cell) => !cell || cell.type !== 'core/table-cell')) errors.push('table_row_invalid'); break;
        case 'core/table-cell': if (parent !== 'core/table-row' || node.children.length || version !== 2 || !validInlineRuns(node.content, node.type) || (a.header !== undefined && typeof a.header !== 'boolean') || (a.rowspan !== undefined && (!Number.isInteger(a.rowspan)||a.rowspan<1||a.rowspan>20)) || (a.colspan !== undefined && (!Number.isInteger(a.colspan)||a.colspan<1||a.colspan>12))) errors.push('table_cell_invalid'); break;
      }
      node.children.forEach((child) => visit(child, depth + 1, node.type));
    }
    doc.blocks.forEach((node) => visit(node, 0, null));
    return { valid: errors.length === 0, errors: [...new Set(errors)] };
  }

  function analyzeDocumentAccessibility(document, allowPageMedia) {
    const validation=validateDocument(document,allowPageMedia);
    if(!validation.valid)return {valid:false,issues:[],errors:validation.errors};
    const copy=accessibilityAuditCopy(),issues=[];
    const textOf=(node)=>typeof node.content==='string'?node.content:(Array.isArray(node.content)?node.content.map((run)=>run&&typeof run.text==='string'?run.text:'').join(''):'');
    const normalized=(value)=>String(value||'').trim().toLocaleLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[.!?,;:]+$/,'').trim();
    const vagueLabels=new Set(['here','click here','read more','learn more','more','click','saiba mais','leia mais','clique aqui','aqui','mais','mais informacoes','ver mais','haz clic aqui','haga clic aqui','leer mas','mas informacion','ver mas']);
    let previousHeading=0;
    const add=(code,node,values)=>{
      const message=copy.issues[code];if(!message)return;
      let detail=message.detail;
      Object.keys(values||{}).forEach((key)=>{detail=detail.replace('%'+key,String(values[key]));});
      issues.push({code,severity:message.severity,title:message.title,detail,suggestion:message.suggestion,nodeId:node.id,type:node.type});
    };
    const visit=(nodes)=>nodes.forEach((node)=>{
      if(node.type==='core/heading'){
        const level=Number(node.attributes.level)||2;const label=textOf(node).trim();
        if(!label)add('empty_heading',node);
        if(previousHeading&&level>previousHeading+1)add('heading_order',node,{1:previousHeading,2:level});
        previousHeading=level;
      }else if(node.type==='core/paragraph'&&!textOf(node).trim())add('empty_paragraph',node);
      if((node.type==='core/image'||node.type==='core/cover')&&(typeof node.attributes.alt!=='string'||!node.attributes.alt.trim()))add('image_alt',node);
      if(node.type==='core/button'){
        const label=normalized(textOf(node));
        if(!label)add('button_empty',node);
        else if(vagueLabels.has(label))add('button_generic',node,{1:textOf(node).trim()});
      }
      if(node.type==='core/table'){
        const hasHeader=node.children.some((row,rowIndex)=>row.children.some((cell)=>tableCellIsHeader(node,rowIndex,cell)));
        if(!hasHeader)add('table_headers',node);
      }
      if(Array.isArray(node.children)&&node.children.length)visit(node.children);
    });
    visit(document.blocks);
    return {valid:true,issues};
  }


  function analyzeDocumentLinks(document, allowPageMedia) {
    const validation=validateDocument(document,allowPageMedia);
    if(!validation.valid)return {valid:false,issues:[],errors:validation.errors};
    const copy=linkReviewCopy(),occurrences=[],issues=[];
    const currentOrigin=(()=>{try{return global.location&&global.location.origin?new URL(global.location.origin).origin:'';}catch(_){return '';}})();
    const normalize=(value)=>{
      try{const parsed=new URL(value,currentOrigin||'https://bdre.local');if(!['http:','https:'].includes(parsed.protocol))return null;const path=parsed.pathname.replace(/\/+$/,'')||'/';return parsed.origin.toLowerCase()+path+parsed.search+parsed.hash;}catch(_){return null;}
    };
    const addLink=(node,destination,label)=>{
      if(typeof destination!=='string'||!destination.trim())return;
      let parsed;try{parsed=new URL(destination,currentOrigin||'https://bdre.local');}catch(_){return;}
      const normalized=normalize(destination);if(!normalized)return;
      const external=!!currentOrigin&&parsed.origin!==currentOrigin;
      occurrences.push({nodeId:node.id,type:node.type,destination,normalized,label:String(label||'').trim(),external,httpExternal:external&&parsed.protocol==='http:'});
    };
    const plainText=(content)=>Array.isArray(content)?content.map((run)=>run&&typeof run.text==='string'?run.text:'').join(''):'';
    const visit=(nodes)=>nodes.forEach((node)=>{
      if(node.type==='core/button')addLink(node,node.attributes.url,plainText(node.content));
      else if(Array.isArray(node.content)){
        let current=null;
        const flush=()=>{if(current){addLink(node,current.href,current.text);current=null;}};
        node.content.forEach((run)=>{
          const href=run&&typeof run.href==='string'&&run.href.trim()?run.href.trim():'';
          if(!href){flush();return;}
          if(current&&current.href===href)current.text+=(run&&typeof run.text==='string'?run.text:'');
          else{flush();current={href,text:run&&typeof run.text==='string'?run.text:''};}
        });flush();
      }
      if(Array.isArray(node.children)&&node.children.length)visit(node.children);
    });
    visit(document.blocks);
    const duplicateCounts=new Map();occurrences.forEach((item)=>duplicateCounts.set(item.normalized,(duplicateCounts.get(item.normalized)||0)+1));
    const addIssue=(code,item,value)=>{
      const message=copy.issues[code];let detail=message.detail.replace('%1',String(value));
      issues.push({code,severity:message.severity,title:message.title,detail,suggestion:message.suggestion,nodeId:item.nodeId,type:item.type});
    };
    occurrences.forEach((item)=>{
      if(item.httpExternal)addIssue('http_external',item,item.destination);
      const visible=item.label.trim();
      if(/^(?:https?:\/\/|www\.)/i.test(visible)){
        const shown=visible.startsWith('www.')?'https://'+visible:visible;
        if(normalize(shown)===item.normalized)addIssue('raw_url_label',item,item.destination);
      }
      const count=duplicateCounts.get(item.normalized)||0;if(count>1)addIssue('repeated_destination',item,count);
    });
    return {valid:true,issues};
  }


  function analyzeInternalAnchors(document, allowPageMedia) {
    const validation=validateDocument(document,allowPageMedia);
    if(!validation.valid)return {valid:false,errors:validation.errors,checked:0,issues:[]};
    const copy=internalAnchorReviewCopy(),headings=new Map(),links=[],issues=[],seen=new Set();
    const textOf=(node)=>Array.isArray(node.content)?node.content.map((run)=>run&&typeof run.text==='string'?run.text:'').join(''):'';
    const visit=(nodes)=>nodes.forEach((node)=>{
      if(node.type==='core/heading'&&typeof node.attributes.anchor==='string')headings.set(node.attributes.anchor,{nodeId:node.id,text:textOf(node).trim(),level:Number(node.attributes.level)||2});
      const add=(destination,label)=>{
        if(typeof destination!=='string'||!/^#[a-z][a-z0-9-]{0,79}$/.test(destination))return;
        links.push({nodeId:node.id,type:node.type,destination:destination.slice(1),label:String(label||'').trim()});
      };
      if(node.type==='core/button')add(node.attributes.url,textOf(node));
      if(Array.isArray(node.content))node.content.forEach((run)=>{if(run&&typeof run.href==='string')add(run.href,run.text);});
      if(Array.isArray(node.children)&&node.children.length)visit(node.children);
    });
    visit(document.blocks);
    links.forEach((link)=>{
      if(headings.has(link.destination))return;
      const key=link.nodeId+'|'+link.destination;if(seen.has(key))return;seen.add(key);
      issues.push({code:'missing_target',severity:'warning',title:copy.warning,detail:copy.detail.replace('%1',link.destination),suggestion:copy.suggestion,nodeId:link.nodeId,type:link.type,destination:link.destination,label:link.label});
    });
    return {valid:true,checked:links.length,issues};
  }


  function analyzeDocumentReadability(document, allowPageMedia) {
    const validation=validateDocument(document,allowPageMedia);
    if(!validation.valid)return {valid:false,issues:[],errors:validation.errors,wordCount:0,readingMinutes:0};
    const copy=readabilityReviewCopy(),issues=[],entries=[];
    const proseTypes=new Set(['core/paragraph','core/list-item','core/quote','core/callout','core/details','core/table-cell']);
    const textOf=(node)=>typeof node.content==='string'?node.content:(Array.isArray(node.content)?node.content.map((run)=>run&&typeof run.text==='string'?run.text:'').join(''):'');
    const words=(text)=>String(text||'').match(/[A-Za-zÀ-ÖØ-öø-ÿ0-9]+(?:['’][A-Za-zÀ-ÖØ-öø-ÿ0-9]+)*/g)||[];
    const wordCount=(text)=>words(text).length;
    const add=(code,node,count)=>{const message=copy.issues[code];issues.push({code,severity:message.severity,title:message.title,detail:message.detail.replace('%1',String(count)),suggestion:message.suggestion,nodeId:node.id,type:node.type});};
    const visit=(nodes)=>nodes.forEach((node)=>{
      if(node.type==='core/heading')entries.push({kind:'heading',node,level:Number(node.attributes.level)||2});
      else if(proseTypes.has(node.type)){
        const text=textOf(node),count=wordCount(text);entries.push({kind:'prose',node,text,count});
        if(count>120)add('long_paragraph',node,count);
        const sentences=text.match(/[^.!?]+(?:[.!?]+(?=\s|$)|$)/g)||[];
        const longest=sentences.reduce((max,sentence)=>Math.max(max,wordCount(sentence)),0);
        if(longest>35)add('long_sentence',node,longest);
      }
      if(Array.isArray(node.children)&&node.children.length)visit(node.children);
    });
    visit(document.blocks);
    entries.forEach((entry,index)=>{
      if(entry.kind!=='heading')return;
      let count=0,hasSubheading=false;
      for(let i=index+1;i<entries.length;i+=1){
        const next=entries[i];
        if(next.kind==='heading'){
          if(next.level<=entry.level)break;
          hasSubheading=true;
        }else count+=next.count;
      }
      if(count>500&&!hasSubheading)add('long_section',entry.node,count);
    });
    const totalWords=entries.reduce((sum,entry)=>sum+(entry.kind==='prose'?entry.count:0),0);
    const locale=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'en').toLowerCase();
    const wordsPerMinute=locale.startsWith('en')?225:200;
    return {valid:true,issues,wordCount:totalWords,readingMinutes:totalWords?Math.max(1,Math.ceil(totalWords/wordsPerMinute)):0,wordsPerMinute};
  }


  function analyzeDocumentPreflight(document, allowPageMedia) {
    const validation=validateDocument(document,allowPageMedia);
    if(!validation.valid)return {valid:false,errors:validation.errors,categories:[],total:0,warnings:0,suggestions:0,wordCount:0,readingMinutes:0};
    const copy=preflightReviewCopy();
    const accessibility=analyzeDocumentAccessibility(document,allowPageMedia);
    const links=analyzeDocumentLinks(document,allowPageMedia);
    const readability=analyzeDocumentReadability(document,allowPageMedia);
    const audits=[{id:'accessibility',issues:accessibility.issues},{id:'links',issues:links.issues},{id:'readability',issues:readability.issues}];
    const categories=audits.map((audit)=>({id:audit.id,title:copy.categories[audit.id],issues:audit.issues.map((issue)=>Object.assign({category:audit.id},issue))}));
    const all=categories.reduce((items,category)=>items.concat(category.issues),[]);
    return {valid:true,categories,total:all.length,warnings:all.filter((issue)=>issue.severity==='warning').length,suggestions:all.filter((issue)=>issue.severity!=='warning').length,wordCount:readability.wordCount,readingMinutes:readability.readingMinutes};
  }


  function compareBDREDocuments(currentDocument, revisionDocument, allowPageMedia) {
    let current,previous,currentValidation,previousValidation;
    try{current=upgradeDocument(currentDocument);previous=upgradeDocument(revisionDocument);currentValidation=validateDocument(current,allowPageMedia);previousValidation=validateDocument(previous,allowPageMedia);}catch(_){return {valid:false,errors:['revision_unreadable'],items:[],added:0,removed:0,changed:0};}
    if(!currentValidation.valid||!previousValidation.valid)return {valid:false,errors:[...new Set(currentValidation.errors.concat(previousValidation.errors))],items:[],added:0,removed:0,changed:0};
    const stable=(value)=>{
      if(Array.isArray(value))return '['+value.map(stable).join(',')+']';
      if(value&&typeof value==='object')return '{'+Object.keys(value).sort().map((key)=>JSON.stringify(key)+':'+stable(value[key])).join(',')+'}';
      return JSON.stringify(value);
    };
    const flatten=(doc)=>{
      const map=new Map(),orders=new Map();
      const visit=(nodes,parentId)=>{
        const parentKey=parentId===null?'$root':parentId;
        orders.set(parentKey,(orders.get(parentKey)||[]).concat(nodes.map((node)=>node.id)));
        nodes.forEach((node,index)=>{map.set(node.id,{node,parentId,index});if(Array.isArray(node.children)&&node.children.length)visit(node.children,node.id);});
      };
      visit(doc.blocks,null);return {map,orders};
    };
    const old=flatten(previous),now=flatten(current),items=[];
    const preview=(node)=>{
      if(!node)return '';
      const raw=typeof node.content==='string'?node.content:(Array.isArray(node.content)?node.content.map((run)=>run&&typeof run.text==='string'?run.text:'').join(''):'');
      const text=String(raw||node.attributes.outline_label||node.attributes.summary||node.attributes.alt||node.attributes.title||'').replace(/\s+/g,' ').trim();
      return text.length>180?text.slice(0,177)+'…':text;
    };
    const currentNodes=Array.from(now.map.entries());
    currentNodes.forEach(([id,entry])=>{
      const oldEntry=old.map.get(id);
      if(!oldEntry){items.push({kind:'added',nodeId:id,type:entry.node.type,before:'',after:preview(entry.node),fields:[]});return;}
      const fields=[];
      if(entry.node.type!==oldEntry.node.type)fields.push('type');
      if(stable(entry.node.attributes)!==stable(oldEntry.node.attributes))fields.push('attributes');
      if(stable(entry.node.content)!==stable(oldEntry.node.content))fields.push('content');
      if(entry.parentId!==oldEntry.parentId)fields.push('container');
      if(fields.length)items.push({kind:'changed',nodeId:id,type:entry.node.type,before:preview(oldEntry.node),after:preview(entry.node),fields});
    });
    // Detect sibling reorders among blocks present in both snapshots without treating insertions/deletions as moves.
    const parentKeys=new Set([...old.orders.keys(),...now.orders.keys()]);
    parentKeys.forEach((parentKey)=>{
      const oldSequence=(old.orders.get(parentKey)||[]).filter((id)=>now.map.has(id)&&old.map.get(id).parentId===(parentKey==='$root'?null:parentKey));
      const newSequence=(now.orders.get(parentKey)||[]).filter((id)=>old.map.has(id)&&now.map.get(id).parentId===(parentKey==='$root'?null:parentKey));
      if(oldSequence.length<2||oldSequence.join('|')===newSequence.join('|'))return;
      const oldPosition=new Map(oldSequence.map((id,index)=>[id,index])),sequence=newSequence.map((id)=>oldPosition.get(id));
      const tails=[],tailIndexes=[],previousIndexes=new Array(sequence.length).fill(-1);
      sequence.forEach((value,index)=>{let low=0,high=tails.length;while(low<high){const mid=(low+high)>>1;if(tails[mid]<value)low=mid+1;else high=mid;}if(low>0)previousIndexes[index]=tailIndexes[low-1];tails[low]=value;tailIndexes[low]=index;});
      const stableIndexes=new Set();let cursor=tailIndexes[tails.length-1];while(cursor!==undefined&&cursor>=0){stableIndexes.add(cursor);cursor=previousIndexes[cursor];}
      newSequence.forEach((id,index)=>{if(stableIndexes.has(index))return;const item=items.find((entry)=>entry.nodeId===id&&entry.kind==='changed');if(item){if(!item.fields.includes('position'))item.fields.push('position');}else{const entry=now.map.get(id),oldEntry=old.map.get(id);items.push({kind:'changed',nodeId:id,type:entry.node.type,before:preview(oldEntry.node),after:preview(entry.node),fields:['position']});}});
    });
    old.map.forEach((entry,id)=>{if(!now.map.has(id))items.push({kind:'removed',nodeId:id,type:entry.node.type,before:preview(entry.node),after:'',fields:[]});});
    return {valid:true,items,added:items.filter((item)=>item.kind==='added').length,removed:items.filter((item)=>item.kind==='removed').length,changed:items.filter((item)=>item.kind==='changed').length};
  }


  function convertDocumentToMarkdown(document, allowPageMedia) {
    const validation=validateDocument(document,allowPageMedia);
    if(!validation.valid)return {valid:false,errors:validation.errors,markdown:'',warnings:[]};
    const warnings=[],seenWarnings=new Set(),copy=markdownExportCopy();
    const warn=(code,node)=>{const key=code+':'+node.id;if(seenWarnings.has(key))return;seenWarnings.add(key);warnings.push({code,nodeId:node.id,type:node.type});};
    const escapeText=(value)=>String(value||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/([\\`*_{}\[\]()#+.!|>~-])/g,'\\$1');
    const safeDestination=(value)=>String(value||'').replace(/</g,'%3C').replace(/>/g,'%3E').replace(/\\/g,'%5C');
    const inline=(runs,node)=>{
      if(!Array.isArray(runs))return '';
      return runs.map((run)=>{
        const marks=Array.isArray(run.marks)?run.marks:[];let text;
        if(marks.includes('code')){
          const ticks=(String(run.text).match(/`+/g)||[]).reduce((max,item)=>Math.max(max,item.length),0)+1;const fence='`'.repeat(Math.max(1,ticks));const pad=/^\s|\s$/.test(String(run.text))||String(run.text).startsWith('`')||String(run.text).endsWith('`')?' ':'';text=fence+pad+String(run.text)+pad+fence;
        }else{
          text=escapeText(run.text);
          if(marks.includes('underline'))warn('underline',node);
          if(marks.includes('strike'))text='~~'+text+'~~';
          if(marks.includes('italic'))text='*'+text+'*';
          if(marks.includes('bold'))text='**'+text+'**';
        }
        if(run.href)text='['+text+'](<'+safeDestination(run.href)+'>)';
        return text;
      }).join('');
    };
    const plain=(node)=>Array.isArray(node.content)?node.content.map((run)=>run&&typeof run.text==='string'?run.text:'').join(''):String(node.content||'');
    const quote=(text)=>String(text||'').split('\n').map((line)=>line?'> '+line:'>').join('\n');
    const codeFence=(text)=>'`'.repeat(Math.max(3,((String(text).match(/`+/g)||[]).reduce((max,item)=>Math.max(max,item.length),0)+1)));
    const imageMarkdown=(node)=>{
      const title=node.attributes.title?(' "'+String(node.attributes.title).replace(/[\r\n]+/g,' ').replace(/"/g,'\\"')+'"'):'';
      return '!['+escapeText(node.attributes.alt||'')+'](<'+safeDestination(node.attributes.src)+'>'+title+')';
    };
    const renderNodes=(nodes)=>nodes.map((node)=>renderNode(node)).filter((text)=>text!==null&&text!=='').join('\n\n');
    const renderNode=(node)=>{
      const a=node.attributes||{};if(a.appearance)warn('appearance',node);
      switch(node.type){
        case 'core/paragraph':return inline(node.content,node);
        case 'core/heading':if(a.anchor)warn('heading_anchor',node);return '#'.repeat(Number(a.level)||2)+' '+inline(node.content,node);
        case 'core/list':{
          const marker=a.ordered?'ordered':'unordered';return node.children.map((item,index)=>{
            const prefix=marker==='ordered'?(index+1)+'. ':'- ';return prefix+inline(item.content,item).replace(/\n/g,'\n  ');
          }).join('\n');
        }
        case 'core/list-item':return inline(node.content,node);
        case 'core/quote':return quote(inline(node.content,node));
        case 'core/callout':warn('callout_style',node);return quote('**'+(copy.calloutTones[a.tone]||'Note')+':** '+inline(node.content,node));
        case 'core/details':warn('details_behavior',node);return '**'+escapeText(a.summary||'')+'**'+(plain(node)?'\n\n'+inline(node.content,node):'');
        case 'core/code':{const fence=codeFence(node.content);return fence+'\n'+String(node.content||'')+'\n'+fence;}
        case 'core/separator':return '***';
        case 'core/spacer':warn('spacer',node);return '';
        case 'core/image':return imageMarkdown(node)+(a.caption?'\n\n*'+escapeText(a.caption)+'*':'');
        case 'core/cover':if(a.align&&a.align!=='content')warn('cover_layout',node);return imageMarkdown(node)+'\n\n'+renderNodes(node.children);
        case 'core/gallery':warn('gallery_layout',node);return node.children.map(imageMarkdown).join('  \n');
        case 'core/group':warn(a.toc_generated?'generated_toc':'layout',node);return renderNodes(node.children);
        case 'core/columns':warn('layout',node);return node.children.map((column)=>renderNodes(column.children)).filter(Boolean).join('\n\n');
        case 'core/column':return renderNodes(node.children);
        case 'core/button':return '['+inline(node.content,node)+'](<'+safeDestination(a.url)+'>)';
        case 'core/file':return '['+inline(node.content,node)+'](<'+safeDestination(a.src)+'>)';
        case 'core/audio':warn('media_link',node);return '['+(inline(node.content,node)||copy.labels.audio)+'](<'+safeDestination(a.src)+'>)';
        case 'core/video_file':warn('media_link',node);return '['+escapeText(a.title||copy.labels.video)+'](<'+safeDestination(a.src)+'>)'+(a.caption?'\n\n*'+escapeText(a.caption)+'*':'');
        case 'core/video_embed':warn('video_embed',node);{const href=a.url||a.src;return '['+copy.labels.video+'](<'+safeDestination(href)+'>)';}
        case 'core/table':{
          const rows=node.children.map((row,rowIndex)=>row.children.map((cell)=>{
            if((cell.attributes.rowspan||1)>1||(cell.attributes.colspan||1)>1)warn('table_span',node);
            return inline(cell.content,cell).replace(/\n/g,' ');
          }));
          if(!rows.length)return '';
          const hasHeader=!!a.header||node.children[0].children.some((cell)=>tableCellIsHeader(node,0,cell));
          const fullFirstHeader=node.children[0].children.every((cell)=>tableCellIsHeader(node,0,cell));
          if(!hasHeader||!fullFirstHeader)warn('table_header',node);
          const width=Math.max(1,...rows.map((row)=>row.length));const normalizeRow=(row)=>Array.from({length:width},(_,index)=>row[index]||'');
          const header=normalizeRow(rows[0]),separator=Array(width).fill('---');
          const body=rows.slice(1).map(normalizeRow);
          return [header,separator,...body].map((row)=>'| '+row.join(' | ')+' |').join('\n');
        }
        case 'core/threejs_thumb':warn('interactive_3d',node);return '**'+escapeText(copy.unsupportedBlock.replace('%1',copy.labels.scene3D))+'**'+(a.label?'\n\n'+escapeText(a.label):'');
        default:warn('appearance',node);return '';
      }
    };
    const markdown=renderNodes(document.blocks).replace(/\n{3,}/g,'\n\n').trim();
    return {valid:true,markdown,warnings};
  }


  function parseMarkdownToBDRE(source, allowPageMedia) {
    const text=String(source==null?'':source).replace(/^\uFEFF/,'');
    if(utf8Length(text)>262144)return {valid:false,errors:['markdown_too_large'],blocks:[],warnings:[]};
    const blocks=[],warnings=[],warned=new Set();let sequence=0;
    const make=(type,attributes,content,children)=>({id:'md-'+(++sequence),type,attributes:attributes||{},content,children:children||[]});
    const warn=(code,nodeId,line)=>{const key=code+':'+String(nodeId||line||'');if(warned.has(key))return;warned.add(key);warnings.push({code,nodeId:nodeId||null,line:Number.isInteger(line)?line:null});};
    const decodeEntities=(value)=>String(value||'').replace(/&(amp|lt|gt|quot|#39|#x27);/gi,(entity,name)=>({amp:'&',lt:'<',gt:'>',quot:'"','#39':"'",'#x27':"'"})[name.toLowerCase()]||entity);
    const unescapeText=(value)=>decodeEntities(String(value||'').replace(/\\([\\`*_{}\[\]()#+.!|>~-])/g,'$1'));
    const appendRun=(runs,value,marks,href)=>{
      if(!value)return;
      const normalized={text:String(value),marks:(marks||[]).slice(),href:href||null},last=runs[runs.length-1];
      if(last&&last.href===normalized.href&&JSON.stringify(last.marks)===JSON.stringify(normalized.marks))last.text+=normalized.text;else runs.push(normalized);
    };
    const parseLink=(value,start)=>{
      const image=value.startsWith('![',start),labelStart=start+(image?2:1);if(value[start+(image?1:0)]!=='[')return null;
      const closeLabel=value.indexOf(']',labelStart);if(closeLabel<0||value[closeLabel+1]!=='(')return null;
      let cursor=closeLabel+2;while(/\s/.test(value[cursor]||''))cursor++;
      let destination='',endDestination=cursor;
      if(value[cursor]==='<'){
        const closeAngle=value.indexOf('>',cursor+1);if(closeAngle<0)return null;destination=value.slice(cursor+1,closeAngle);endDestination=closeAngle+1;cursor=endDestination;
      }else{
        const startDestination=cursor;let depth=0;
        while(cursor<value.length){
          if(value[cursor]==='\\'&&cursor+1<value.length){cursor+=2;continue;}
          if(value[cursor]==='('){depth++;cursor++;continue;}
          if(value[cursor]===')'){if(depth===0)break;depth--;cursor++;continue;}
          if(/\s/.test(value[cursor])&&depth===0)break;cursor++;
        }
        destination=value.slice(startDestination,cursor);endDestination=cursor;
      }
      while(/\s/.test(value[cursor]||''))cursor++;
      let hasTitle=false;
      if(cursor<value.length&&value[cursor]!==')'){
        hasTitle=true;const quote=value[cursor]==='"'||value[cursor]==="'"?value[cursor]:'';
        if(quote){cursor++;while(cursor<value.length&&value[cursor]!==quote){if(value[cursor]==='\\')cursor++;cursor++;}if(value[cursor]===quote)cursor++;}
        else while(cursor<value.length&&value[cursor]!==')')cursor++;
        while(/\s/.test(value[cursor]||''))cursor++;
      }
      if(value[cursor]!==')')return null;
      return {image,label:unescapeText(value.slice(labelStart,closeLabel)),destination:unescapeText(destination.replace(/\\([()])/g,'$1')),title:hasTitle,end:cursor+1};
    };
    const parseInline=(value,nodeId,marks,depth)=>{
      const input=String(value||''),runs=[],active=marks||[],level=depth||0;let plain='';
      const flush=()=>{if(plain){appendRun(runs,decodeEntities(plain),active,null);plain='';}};
      if(level>6){appendRun(runs,unescapeText(input),active,null);return runs;}
      for(let i=0;i<input.length;){
        if(input[i]==='\\'&&i+1<input.length&&/[\\`*_{}\[\]()#+.!|>~-]/.test(input[i+1])){plain+=input[i+1];i+=2;continue;}
        if(input[i]==='!'&&input[i+1]==='['||input[i]==='['){
          const link=parseLink(input,i);
          if(link){flush();
            if(link.image){warn('inline_image',nodeId);appendRun(runs,'[Image: '+link.label+'] ('+link.destination+')',active,null);}
            else{
              if(link.title)warn('link_title',nodeId);
              const destination=link.destination;
              const localFile=(validLocalDownload(destination,allowPageMedia)&&safeMediaScope(destination,'download'))||(validLocalAudio(destination,allowPageMedia)&&safeMediaScope(destination,'audio'))||(validLocalVideo(destination,allowPageMedia)&&safeMediaScope(destination,'video'));
              if(validActionUrl(destination)&&!localFile){const linked=parseInline(input.slice(i+(link.image?2:1),input.indexOf(']',i+(link.image?2:1))),nodeId,active,level+1);linked.forEach((run)=>{run.href=destination;runs.push(run);});}
              else{if(localFile)warn('inline_media_link',nodeId);else warn('unsafe_link',nodeId);appendRun(runs,link.label+' ('+destination+')',active,null);}
            }
            i=link.end;continue;
          }
        }
        if(input[i]==='`'){
          let end=i;while(input[end]==='`')end++;const fence=input.slice(i,end),close=input.indexOf(fence,end);
          if(close>=0){flush();appendRun(runs,input.slice(end,close),active.concat('code'),null);i=close+fence.length;continue;}
        }
        const marker=['**','__','~~','*','_'].find((candidate)=>input.startsWith(candidate,i));
        if(marker){const close=input.indexOf(marker,i+marker.length);if(close>i+marker.length){flush();const mark=marker==='**'||marker==='__'?'bold':marker==='~~'?'strike':'italic';parseInline(input.slice(i+marker.length,close),nodeId,active.concat(mark),level+1).forEach((run)=>runs.push(run));i=close+marker.length;continue;}}
        plain+=input[i];i++;
      }
      flush();
      if(/<\/?[A-Za-z][^>]*>/.test(input))warn('html_literal',nodeId);
      return canonicalRuns(runs);
    };
    const safeMediaScope=(path,kind)=>{
      const expected='view/'+kind+'/'+(allowPageMedia?'cms/page/':'blog/');
      return typeof path==='string'&&path.startsWith(expected);
    };
    const safeImagePath=(path)=>{
      if(!validLocalImage(path))return false;
      try{const parsed=new URL(path,global.location&&global.location.origin||'https://bdre.local');
        if(parsed.pathname.startsWith('/view/image/cms/page/'))return !!allowPageMedia;
        if(parsed.pathname.startsWith('/view/image/blog/'))return !allowPageMedia;
        return true;
      }catch(_){return false;}
    };
    const lines=text.replace(/\r\n?/g,'\n').split('\n');
    const headingMatch=(line)=>/^ {0,3}(#{1,6})(?:\s+|$)(.*?)\s*#*\s*$/.exec(line);
    const fenceMatch=(line)=>/^ {0,3}(`{3,}|~{3,})(.*)$/.exec(line);
    const listMatch=(line)=>/^( *)([-+*]|(\d+)[.)])\s+(.*)$/.exec(line);
    const splitCells=(line)=>{
      let value=line.trim();if(value.startsWith('|'))value=value.slice(1);if(value.endsWith('|')&&!value.endsWith('\\|'))value=value.slice(0,-1);
      const cells=[];let current='',escaped=false;
      for(const character of value){if(escaped){current+='\\'+character;escaped=false;}else if(character==='\\')escaped=true;else if(character==='|'){cells.push(current.trim());current='';}else current+=character;}
      if(escaped)current+='\\';cells.push(current.trim());return cells;
    };
    const isTableSeparator=(line)=>{const cells=splitCells(line);return cells.length>0&&cells.every((cell)=>/^:?-{3,}:?$/.test(cell));};
    const isRule=(line)=>/^ {0,3}(?:(?:\*\s*){3,}|(?:-\s*){3,}|(?:_\s*){3,})$/.test(line);
    const isImageLine=(line)=>{const value=line.trim(),image=parseLink(value,0);return image&&image.image&&image.end===value.length?image:null;};
    const isBlockStart=(index)=>{
      const line=lines[index]||'';return !!(headingMatch(line)||fenceMatch(line)||isRule(line)||/^ {0,3}>/.test(line)||listMatch(line)||isImageLine(line)||(index+1<lines.length&&line.includes('|')&&isTableSeparator(lines[index+1])));
    };
    const addParagraph=(value,line)=>{
      const block=make('core/paragraph',{},[],[]);block.content=parseInline(value,block.id,[],0);if(block.content.length)blocks.push(block);
      if(/<\/?[A-Za-z][^>]*>/.test(value))warn('html_literal',block.id,line);
      return block;
    };
    for(let i=0;i<lines.length;){
      const raw=lines[i],lineNumber=i+1;
      if(!raw.trim()){i++;continue;}
      const fence=fenceMatch(raw);
      if(fence){const marker=fence[1],body=[];i++;let closed=false;
        while(i<lines.length){const close=new RegExp('^ {0,3}'+(marker[0]==='`'?'`':'~')+'{'+marker.length+',}\\s*$');if(close.test(lines[i])){closed=true;i++;break;}body.push(lines[i++]);}
        const node=make('core/code',{},body.join('\n'),[]);blocks.push(node);if(!closed)warn('unclosed_fence',node.id,lineNumber);continue;
      }
      const heading=headingMatch(raw);
      if(heading){const node=make('core/heading',{level:heading[1].length},[],[]);node.content=parseInline(heading[2],node.id,[],0);blocks.push(node);i++;continue;}
      if(isRule(raw)){blocks.push(make('core/separator',{},'',[]));i++;continue;}
      const image=isImageLine(raw);
      if(image){const imagePath=image.destination,title=image.title?(/\s+["']([^"']*)["']\s*$/.exec(raw)||[])[1]||'':'';
        if(safeImagePath(imagePath))blocks.push(make('core/image',{src:imagePath,alt:image.label,...(title?{title:unescapeText(title)}:{})},'',[]));
        else{const block=addParagraph('[Image: '+image.label+'] ('+imagePath+')',lineNumber);warn('remote_image',block.id,lineNumber);}
        i++;continue;
      }
      if(i+1<lines.length&&raw.includes('|')&&isTableSeparator(lines[i+1])){
        const sourceRows=[splitCells(raw)];let j=i+2;while(j<lines.length&&lines[j].trim()&&lines[j].includes('|'))sourceRows.push(splitCells(lines[j++]));
        const width=Math.max(...sourceRows.map((row)=>row.length));
        if(width>12||sourceRows.length>=20){warn('table_overflow',null,lineNumber);sourceRows.forEach((row,rowIndex)=>addParagraph(row.join(' | '),lineNumber+rowIndex));i=j;continue;}
        const rows=sourceRows.map((cells,rowIndex)=>make('core/table-row',{},'',cells.map((cell,colIndex)=>{const tableCell=make('core/table-cell',rowIndex===0?{header:true}:{},[],[]);tableCell.content=parseInline(cell,tableCell.id,[],0);return tableCell;})));
        blocks.push(make('core/table',{header:true},'',rows));i=j;continue;
      }
      if(/^ {0,3}>/.test(raw)){
        const quoteLines=[];while(i<lines.length&&/^ {0,3}>/.test(lines[i]))quoteLines.push(lines[i++].replace(/^ {0,3}> ?/,''));
        const node=make('core/quote',{},[],[]);node.content=parseInline(quoteLines.join('\n'),node.id,[],0);blocks.push(node);continue;
      }
      const firstList=listMatch(raw);
      if(firstList){const ordered=!!firstList[3],baseIndent=firstList[1].length,items=[];let nested=false,initialNumber=Number(firstList[3]||1);
        while(i<lines.length){const item=listMatch(lines[i]);if(!item)break;const indent=item[1].length,itemOrdered=!!item[3];if(indent<baseIndent||itemOrdered!==ordered)break;if(indent>baseIndent)nested=true;
          const node=make('core/list-item',{},[],[]);node.content=parseInline(item[4],node.id,[],0);items.push(node);i++;
        }
        const list=make('core/list',{ordered},'',items);blocks.push(list);if(nested)warn('nested_list',list.id,lineNumber);if(ordered&&initialNumber!==1)warn('list_start',list.id,lineNumber);continue;
      }
      const link=parseLink(raw.trim(),0);
      if(link&&!link.image&&link.end===raw.trim().length){
        const destination=link.destination,label=link.label;
        if(link.title)warn('link_title',null,lineNumber);
        if(validLocalDownload(destination,allowPageMedia)&&safeMediaScope(destination,'download')){blocks.push(make('core/file',{src:destination},[{text:label||'File',marks:[],href:null}],[]));i++;continue;}
        if(validLocalAudio(destination,allowPageMedia)&&safeMediaScope(destination,'audio')){blocks.push(make('core/audio',{src:destination},[{text:label||'Audio',marks:[],href:null}],[]));i++;continue;}
        if(validLocalVideo(destination,allowPageMedia)&&safeMediaScope(destination,'video')){blocks.push(make('core/video_file',{src:destination,title:label||'Video'},'',[]));i++;continue;}
        if(validEmbed(destination)&&['Video','Vídeo'].includes(label.trim())){blocks.push(make('core/video_embed',{url:destination},'',[]));i++;continue;}
      }
      const paragraphLines=[];let current=i;
      while(current<lines.length&&lines[current].trim()&&!isBlockStart(current)){
        if(current!==i&&isBlockStart(current))break;
        paragraphLines.push(lines[current]);current++;
      }
      if(!paragraphLines.length){paragraphLines.push(lines[i]);current=i+1;}
      const joined=paragraphLines.map((part,index)=>{
        if(index===paragraphLines.length-1)return part.trim();
        return / {2,}$|\\$/.test(part)?part.replace(/ {2,}$|\\$/,'')+'\n':part.trim()+' ';
      }).join('');
      addParagraph(joined,lineNumber);i=current;
    }
    const document={format_version:2,blocks};
    const validation=validateDocument(document,allowPageMedia);
    if(!validation.valid)return {valid:false,errors:validation.errors,blocks:[],warnings};
    if(utf8Length(JSON.stringify(document))>4194304)return {valid:false,errors:['document_too_large'],blocks:[],warnings};
    return {valid:true,blocks,warnings};
  }

  function moveBlockAcrossHierarchy(document, nodeId, direction, allowPageMedia) {
    if (!document || document.format_version !== 2 || !Array.isArray(document.blocks) || !['indent','outdent'].includes(direction) || !validateDocument(document, allowPageMedia).valid) return null;
    const next = CLONE(document);
    function locate(nodes, id, parent) {
      for (let index = 0; index < nodes.length; index += 1) {
        const node = nodes[index];
        if (node && node.id === id) return { nodes, index, node, parent: parent || null };
        const found = node && Array.isArray(node.children) ? locate(node.children, id, node) : null;
        if (found) return found;
      }
      return null;
    }
    const found = locate(next.blocks, nodeId, null);
    if (!found || ['core/table','core/table-row','core/table-cell'].includes(found.node.type)) return null;
    const requiredParents = ['core/group','core/column','core/list','core/gallery','core/cover'];
    if (found.parent && requiredParents.includes(found.parent.type) && found.nodes.length <= 1) return null;
    if (direction === 'indent') {
      if (found.index < 1) return null;
      const destination = found.nodes[found.index - 1];
      if (!destination || !['core/group','core/column','core/list','core/gallery','core/cover'].includes(destination.type)) return null;
      if (destination.type === 'core/list' && found.node.type !== 'core/list-item') return null;
      if (destination.type === 'core/gallery' && found.node.type !== 'core/image') return null;
      if (destination.type === 'core/cover' && !['core/heading','core/paragraph','core/quote','core/button'].includes(found.node.type)) return null;
      const [moving] = found.nodes.splice(found.index, 1);
      destination.children.push(moving);
    } else {
      if (!found.parent || !requiredParents.includes(found.parent.type)) return null;
      const parentPath = locate(next.blocks, found.parent.id, null);
      if (!parentPath) return null;
      const [moving] = found.nodes.splice(found.index, 1);
      parentPath.nodes.splice(parentPath.index + 1, 0, moving);
    }
    if (!validateDocument(next, allowPageMedia).valid || utf8Length(JSON.stringify(next)) > 4194304) return null;
    return { document: next, focusId: nodeId };
  }

  function normalizeDomInline(root, blockType) {
    const runs = [];
    function append(text, marks, href) {
      if (!text) return;
      const safeHref = href && validActionUrl(href) && blockType !== 'core/button' ? href : null;
      runs.push({ text, marks: [...new Set(marks)].filter((mark) => INLINE_MARKS.includes(mark)), href: safeHref });
    }
    function visit(node, marks, href) {
      if (node.nodeType === 3) { append(node.nodeValue || '', marks, href); return; }
      if (node.nodeType !== 1) return;
      const element = node;
      const nextMarks = marks.slice();
      if (INLINE_TAG_MARK[element.tagName] && !nextMarks.includes(INLINE_TAG_MARK[element.tagName])) nextMarks.push(INLINE_TAG_MARK[element.tagName]);
      const nextHref = element.tagName === 'A' ? (element.getAttribute('href') || null) : href;
      if (element.tagName === 'BR') { append('\n', marks, href); return; }
      visitChildren(element, nextMarks, nextHref);
    }
    function visitChildren(parent, marks, href) {
      const children = Array.from(parent.childNodes);
      children.forEach((child, index) => {
        const blockChild = child.nodeType === 1 && ['DIV','P'].includes(child.tagName);
        if (blockChild && index > 0) append('\n', marks, href);
        if (blockChild) visitChildren(child, marks, href);
        else visit(child, marks, href);
      });
    }
    visitChildren(root, [], null);
    return canonicalRuns(runs);
  }

  function buildInlineDom(root, runs) {
    root.replaceChildren();
    runs.forEach((run) => {
      let parent = root;
      if (run.href) { const link = document.createElement('a'); link.href = run.href; link.rel = 'nofollow noopener noreferrer'; parent.append(link); parent = link; }
      run.marks.forEach((mark) => {
        const tags = { bold:'strong', italic:'em', underline:'u', strike:'s', code:'code' };
        const wrapper = document.createElement(tags[mark] || 'span'); parent.append(wrapper); parent = wrapper;
      });
      parent.append(document.createTextNode(run.text));
    });
  }

  class Editor {
    constructor(root, options) {
      this.root = root;
      this.options = options || {};
      this.patternConfig = this.options.patternConfig || {};
      this.historyOpen = false;
      this.savedPatterns = [];
      this.patternLoadError = false;
      this.allowPageMedia = !!(root.dataset && root.dataset.pageContext);
      this.instance = ++instanceSequence;
      this.idCounter = 0;
      this.undoStack = [];
      this.redoStack = [];
      this.undoWeight = 0;
      this.redoWeight = 0;
      this.textUndoPending = false;
      this.textUndoTimer = null;
      this.selectedId = null;
      this.blockClipboard = null;
      this.tableSelection = new Map();
      this.preview = 'desktop';
      this.previewMode = false;
      this.importNotices = [];
      this.fullscreen = false;
      this.activePanel = null;
      this.inspectorOpen = true;
      this.inspectorTab = 'content';
      this.activeRibbonTab = 'home';
      this.workspaceView = 'editor';
      this.patternFilter = 'all';
      this.patternQuery = '';
      this.ui = workspaceCopy();
      this.contentModels = contentModelCopy();
      this.accessibility = accessibilityAuditCopy();
      this.linkReview = linkReviewCopy();
      this.internalAnchorReview = internalAnchorReviewCopy();
      this.readability = readabilityReviewCopy();
      this.preflight = preflightReviewCopy();
      this.revisionCompare = revisionCompareCopy();
      this.tableOfContents = tableOfContentsCopy();
      this.tocLevels = [2,3];
      this.revisionCompareIndex = null;
      this.markdownExport = markdownExportCopy();
      this.markdownImport = markdownImportCopy();
      this.pendingMarkdownImport = null;
      this.findReplaceOpen = false; this.findQuery = ''; this.replaceQuery = ''; this.findCaseSensitive = false; this.findWholeWord = false; this.activeFindIndex = -1;
      this.documentStatsTimer = null;
      this.fullscreenReturnFocus = null;
      this.fullscreenPreviousAttrs = null;
      this.fullscreenPreviousPanel = null;
      this.dragId = null;
      this.inlineBookmark = null;
      this.linkDraft = '';
      this.inlineNotice = '';
      this.inserterOpen = false;
      this.inserterPreviousPanel = null; this.inserterHasPreviousPanel = false;
      this.inserterReturnFocus = null;
      this.insertAfterId = null;
      this.slashTrigger = null;
      this.inserterQuery = '';
      this.statusMessage = '';
      this.strings = Object.assign({bold:'Negrito',italic:'Itálico',underline:'Sublinhado',strike:'Tachado',code:'Código',link:'Link',linkPlaceholder:'https:// ou /caminho',applyLink:'Aplicar link',removeLink:'Remover link',invalidLink:'URL de link inválida.',toolbar:'Formatação inline',textLabel:'Texto do bloco',placeholder:'Digite o texto…',addBlock:'Adicionar bloco',searchBlocks:'Buscar blocos…',noBlocks:'Nenhum bloco encontrado.',categoryContent:'Conteúdo',categoryMedia:'Mídia',categoryLayout:'Layout',outline:'Estrutura do documento',outlineLabel:'Título na estrutura',initialInvalid:'Documento inicial inválido; a fonte foi preservada para revisão e a edição está bloqueada',documentTooLarge:'Documento maior que o limite de 4 MiB.',importUnavailable:'Importador indisponível.',importError:'Falha ao importar arquivo.',outlineEmpty:'O documento ainda não tem blocos.',moveUp:'Mover para cima',moveDown:'Mover para baixo',duplicate:'Duplicar',transform:'Transformar bloco',remove:'Remover bloco',addAfter:'Inserir depois deste bloco',dragHint:'Arraste para reordenar; Alt+↑/↓ também move.',transformHint:'Transformação preserva somente conteúdo textual compatível.',insertEmpty:'Nenhum tipo de bloco pode ser inserido neste ponto.',statusInserted:'Bloco inserido.',statusMoved:'Bloco movido.',statusDuplicated:'Bloco duplicado.',statusTransformed:'Bloco transformado.',statusRemoved:'Bloco removido.',statusUndo:'Alteração desfeita.',statusRedo:'Alteração refeita.',undo:'Desfazer',redo:'Refazer',copyBlock:'Copiar bloco',pasteBlock:'Colar bloco',statusCopied:'Bloco copiado para este editor.',statusPasted:'Bloco colado.',pasteUnavailable:'Não é possível colar este bloco neste ponto ou o limite do documento foi atingido.',navigationPath:'Caminho do bloco selecionado',navigationRow:'Linha',navigationCell:'Célula',statusFocusChanged:'Foco movido para o bloco selecionado.',statusOutlineChanged:'Bloco selecionado atualizado pela navegação da estrutura.',indentBlock:'Indentar dentro do bloco anterior',outdentBlock:'Desindentar após o bloco pai',statusIndented:'Bloco movido para dentro do bloco anterior.',statusOutdented:'Bloco movido para depois do pai.',hierarchyMoveUnavailable:'Esta movimentação não é permitida neste contexto ou ultrapassaria um limite do documento.',outlineKeyboardHint:'↑/↓ navegar · → primeiro filho · ← bloco pai · Home/End início/fim',keyboardShortcutsTitle:'Atalhos de teclado',keyboardShortcuts:'Ctrl/⌘+B negrito · Ctrl/⌘+I itálico · Ctrl/⌘+U sublinhado · Ctrl/⌘+K link · Ctrl/⌘+Z desfazer · Ctrl/⌘+Shift+Z ou Ctrl+Y refazer · Alt+↑/↓ mover bloco · / inserir bloco',previewDesktop:'Desktop',previewTablet:'Tablet',previewMobile:'Celular',editorTools:'Ferramentas do editor de blocos',fullscreenEnter:fullscreenCopy().enter,fullscreenExit:fullscreenCopy().exit,fullscreenDialog:fullscreenCopy().dialog,builderElements:fullscreenCopy().elements,builderStructure:fullscreenCopy().structure,builderSettings:fullscreenCopy().settings,workspaceRailLabel:"Ferramentas do espaço de trabalho",workspaceToolText:"Texto",workspaceToolAppearance:"Aparência",workspaceToolPatterns:"Padrões",canvas:'Canvas de conteúdo',canvasEmpty:'Escolha um bloco no inserter para começar.',inspector:'Inspetor de bloco',inspectorEmpty:'Selecione um bloco para editar seus atributos.',columnCount:'colunas · empilham em telas estreitas',media:{caption:'Legenda',alignment:'Alinhamento da imagem',imageSize:'Tamanho',mobileSize:'Tamanho no celular',galleryColumns:'Colunas da galeria',source:'Caminho local da imagem',alt:'Texto alternativo',title:'Título da imagem',videoPreview:'Prévia do vídeo',videoPreviewHint:'Prévia administrativa segura; a reprodução ocorre na página pública.',placeholder:'Selecione uma imagem na biblioteca.',alignments:{left:'Esquerda',center:'Centro',right:'Direita',content:'Conteúdo',wide:'Largo',full:'Largura total'},sizes:{small:'Pequeno',medium:'Médio',large:'Grande',full:'Largura total'},mobileSizes:{full:'Largura total',large:'Grande',medium:'Médio'}},patternsCategory:'Padrões de layout',patternHint:'Padrões locais prontos para inserir; não são salvos como templates globais.',patternLabels:{two_columns:'Destaque em duas colunas',quote_cta:'Citação e chamada',section_intro:'Introdução de seção',cover_hero:'Capa de destaque'},file:{source:'Caminho local do arquivo',choose:'Selecionar arquivo'},audio:{source:'Caminho local do áudio',choose:'Selecionar áudio'},callout:{tone:'Estilo do aviso',defaultText:'Adicione uma informação importante.',tones:{info:'Informação',success:'Sucesso',warning:'Atenção',danger:'Crítico'}},details:{summaryLabel:'Título da seção expansível',summaryDefault:'Mais informações',defaultText:'Adicione os detalhes aqui.'},spacer:{heightLabel:'Altura do espaçador',heights:{'16':'Muito pequeno · 16 px','32':'Pequeno · 32 px','48':'Médio · 48 px','64':'Grande · 64 px','96':'Muito grande · 96 px'}},preformatted:{label:'Código pré-formatado',defaultText:'',tooLarge:'O bloco de código excede o limite de 256 KiB.'},table:{header:'Primeira linha é cabeçalho',addRow:'Adicionar linha',removeRow:'Remover última linha',addColumn:'Adicionar coluna',removeColumn:'Remover última coluna',toggleHeader:'Alternar cabeçalho',cellLabel:'Célula %1$d, %2$d'},columnsAdd:'Adicionar coluna',columnsRemove:'Remover última coluna',columnsLimit:'As colunas disponíveis são 2 ou 3. Remover a última coluna pode ser desfeito com Desfazer.',columnDefault:'Coluna',typeLabels:{}}, paragraphInspectorCopy(), imageInspectorCopy(), {videoInspector:videoInspectorCopy(),audioInspector:audioInspectorCopy(),spacerInspector:spacerInspectorCopy(),separatorInspector:separatorInspectorCopy(),groupInspector:groupInspectorCopy(),columnInspector:columnInspectorCopy()}, this.options.strings || {});
      const rawInitial = this.options.document || { format_version: 1, blocks: [] };
      const initial = upgradeDocument(rawInitial);
      const validation = validateDocument(initial, this.allowPageMedia);
      this.locked = !validation.valid;
      this.originalDocument = this.locked ? initial : null;
      this.state = validation.valid ? CLONE(initial) : { format_version: 2, blocks: [] };
      this.initialError = validation.valid ? '' : validation.errors.join(', ');
      this.onChange = typeof this.options.onChange === 'function' ? this.options.onChange : function () {};
      this.root.classList.add('rb-editor');
      this.root.dataset.instance = String(this.instance);
      this.root.setAttribute('data-preview', this.preview);
      this.bind();
      this.render();
      this.loadSavedPatterns();
      if(this.options.startFullscreen===true)this.setFullscreen(true,true);
    }

    newId() { let candidate; do { this.idCounter += 1; candidate = 'rb' + this.instance + '-' + this.idCounter; } while (this.findPath && this.findPath(this.state ? this.state.blocks : [], candidate)); return candidate; }
    node(type, attributes, content, children) { const value = BLOCK_TEXT_TYPES.has(type) ? (Array.isArray(content) ? canonicalRuns(content) : runsFromText(String(content || ''))) : (content || ''); return { id: this.newId(), type, attributes: attributes || {}, children: children || [], content: value }; }
    snapshot() { return JSON.stringify(this.state); }
    label(type) { const localized = this.strings.typeLabels && this.strings.typeLabels[type]; if (localized) return localized; const found = TYPES.find((entry) => entry[0] === type); return found ? found[1] : (type === 'core/list-item' ? 'Item de lista' : 'Coluna'); }

    categoryLabel(category) { return category === 'media' ? this.strings.categoryMedia : (category === 'layout' ? this.strings.categoryLayout : this.strings.categoryContent); }

    bind() {
      global.document.addEventListener('keydown', (event) => {
        if (!this.fullscreen) return;
        if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.setFullscreen(false); return; }
        if (event.key !== 'Tab') return;
        const focusable = Array.from(this.root.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),textarea:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])')).filter((item) => item.getClientRects().length > 0);
        if (!focusable.length) { event.preventDefault(); this.root.focus(); return; }
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && (global.document.activeElement === first || !this.root.contains(global.document.activeElement))) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && (global.document.activeElement === last || !this.root.contains(global.document.activeElement))) { event.preventDefault(); first.focus(); }
      }, true);
      global.document.addEventListener('blog-block-file-selected', (event) => { const detail = event.detail || {}; if (detail.editor !== this || !detail.nodeId || typeof detail.path !== 'string') return; this.updateNode(detail.nodeId, (node) => { if (node.type === 'core/file') node.attributes.src = detail.path; }); });
      global.document.addEventListener('blog-block-audio-selected', (event) => { const detail = event.detail || {}; if (detail.editor !== this || !detail.nodeId || typeof detail.path !== 'string') return; this.updateNode(detail.nodeId, (node) => { if (node.type === 'core/audio') node.attributes.src = detail.path; }); });
      global.document.addEventListener('blog-block-video-selected', (event) => { const detail = event.detail || {}; if (detail.editor !== this || !detail.nodeId || typeof detail.path !== 'string') return; this.updateNode(detail.nodeId, (node) => { if (node.type === 'core/video_file') node.attributes.src = detail.path; }); });
      this.root.addEventListener('mousedown', (event) => {
        const control = event.target.closest('[data-action="format-mark"],[data-action="link-apply"],[data-action="link-remove"]');
        if (!control || !this.root.contains(control)) return;
        this.captureInlineSelection();
        event.preventDefault();
      });
      this.root.addEventListener('focusin', (event) => {
        const editor = event.target.closest('[data-edit-content]');
        const codeEditor = event.target.closest('[data-edit-code]');
        if (editor) this.selectNode(editor.dataset.editContent);
        else if (codeEditor) this.selectNode(codeEditor.dataset.editCode);
      });
      this.root.addEventListener('mouseup', () => this.captureInlineSelection());
      this.root.addEventListener('keyup', (event) => {
        if (event.target.closest('[data-edit-content]')) this.captureInlineSelection();
      });
      global.document.addEventListener('selectionchange', () => this.captureInlineSelection());
      this.root.addEventListener('click', (event) => {
        const editorLink = event.target.closest('[data-edit-content] a');
        if (editorLink) { event.preventDefault(); return; }
        const button = event.target.closest('[data-action]');
        if (button && this.root.contains(button)) { this.action(button.dataset.action, button); return; }
        if (event.target.closest('textarea,input,select,button,[contenteditable="true"]')) return;
        const card = event.target.closest('[data-node-id]');
        if (card && this.root.contains(card)) this.selectNode(card.dataset.nodeId);
      });
      this.root.addEventListener('input', (event) => {
        const codeEditor = event.target.closest('[data-edit-code]');
        if (codeEditor) { this.updateCodeNode(codeEditor.dataset.editCode, codeEditor); this.scheduleDocumentStatistics(); return; }
        const search = event.target.closest('[data-inserter-search]');
        if (search) { this.inserterQuery = search.value; this.filterInserter(); return; }
        const patternSearch = event.target.closest('[data-pattern-search]');
        if (patternSearch) { this.patternQuery = patternSearch.value; this.filterWorkspacePatterns(); return; }
        const findInput = event.target.closest('[data-find-query]');
        if (findInput) { this.findQuery = findInput.value; this.activeFindIndex = -1; this.updateFindStatus(); return; }
        const replaceInput = event.target.closest('[data-replace-query]');
        if (replaceInput) { this.replaceQuery = replaceInput.value; return; }
        const linkInput = event.target.closest('[data-inline-link-input]');
        if (linkInput) { this.linkDraft = linkInput.value; return; }
        const field = event.target.closest('[data-edit-content]');
        if (!field) return;
        const id = field.dataset.editContent;
        this.updateTextNode(id, field);
        this.scheduleDocumentStatistics();
        this.captureInlineSelection();
      });
      this.root.addEventListener('change', (event) => {
        const tocLevel=event.target.closest('[data-toc-level]');
        if(tocLevel){this.tocLevels=Array.from(this.root.querySelectorAll('[data-toc-level]:checked')).map((input)=>Number(input.dataset.tocLevel));this.render();const focus=this.root.querySelector('[data-toc-level="'+tocLevel.dataset.tocLevel+'"]');if(focus)focus.focus();return;}
        const spacerRange=event.target.closest('[data-spacer-height-range]');
        if(spacerRange){const selected=SPACER_HEIGHTS[Number(spacerRange.value)]||SPACER_HEIGHTS[0];this.updateNode(spacerRange.dataset.nodeId,(node)=>{if(node.type==='core/spacer')node.attributes.height=selected;});return;}
        const tableCellSelect=event.target.closest('[data-table-cell-select]');
        if(tableCellSelect){const tableId=tableCellSelect.dataset.tableId,cellId=tableCellSelect.dataset.tableCellSelect;let selected=this.tableSelection.get(tableId);if(!selected){selected=new Set();this.tableSelection.set(tableId,selected);}if(tableCellSelect.checked)selected.add(cellId);else selected.delete(cellId);this.updateTableSelectionControls(tableId);return;}
        const caseInput = event.target.closest('[data-find-case]');
        if (caseInput) { this.findCaseSensitive = caseInput.checked; this.activeFindIndex = -1; this.updateFindStatus(); return; }
        const wholeWordInput = event.target.closest('[data-find-whole-word]');
        if (wholeWordInput) { this.findWholeWord = wholeWordInput.checked; this.activeFindIndex = -1; this.updateFindStatus(); return; }
        const markdownInput=event.target.closest('[data-markdown-import-file]');
        if(markdownInput&&markdownInput.files&&markdownInput.files[0]){const file=markdownInput.files[0];markdownInput.value='';this.readMarkdownImport(file);return;}
        const importInput = event.target.closest('[data-import-file]');
        if (importInput && importInput.files && importInput.files[0]) { const file=importInput.files[0]; importInput.value=''; this.workspaceView='editor';this.previewMode=false;this.activePanel='structure';this.importLocalFile(file); return; }
        const transform = event.target.closest('[data-transform-node]');
        if (transform && transform.value) { this.transformNode(transform.dataset.transformNode, transform.value); return; }
        const sceneField = event.target.closest('[data-threejs-kind]');
        if (sceneField) { this.updateThreeJsEntry(sceneField.dataset.nodeId, sceneField.dataset.threejsKind, Number(sceneField.dataset.index), sceneField.dataset.key, sceneField.value); return; }
        const appearanceField=event.target.closest('[data-appearance]');
        if(appearanceField){
          const id=appearanceField.dataset.nodeId,key=appearanceField.dataset.appearance;
          this.updateNode(id,(node)=>{
            const appearance=Object.assign({},node.attributes.appearance||{});
            if(appearanceField.value==='')delete appearance[key];
            else if(appearanceField.type==='number'){const ranges={border_width:[0,8,0],border_radius:[0,32,0],padding:[0,64,0],margin:[0,48,0],font_size:[12,48,16]};const range=ranges[key];const parsed=Number.parseInt(appearanceField.value,10);appearance[key]=Number.isFinite(parsed)?Math.min(range[1],Math.max(range[0],parsed)):range[2];}
            else appearance[key]=appearanceField.value;
            if((key==='border_color'||key==='border_style')&&appearance.border_width===undefined)appearance.border_width=1;
            if(Object.keys(appearance).length){const sorted={};Object.keys(appearance).sort().forEach((name)=>{sorted[name]=appearance[name];});node.attributes.appearance=sorted;}
            else delete node.attributes.appearance;
          });return;
        }
        const field = event.target.closest('[data-attribute]');
        if (!field) return;
        const id = field.dataset.nodeId, key = field.dataset.attribute;
        if (key === 'custom_js') { this.updateNode(id, (node) => { node.attributes.custom_js = field.value; }, false); return; }
        this.updateNode(id, (node) => {
          if (['ordered','controls','loop','muted','autoplay'].includes(key)) node.attributes[key] = field.value === 'true';
          else if (key === 'level' || key === 'count' || key === 'columns' || key === 'height' || key === 'width' || key === 'thickness') node.attributes[key] = Number(field.value);
          else node.attributes[key] = field.value;
          if(key==='url'&&node.type==='core/video_embed'&&String(field.value).trim())delete node.attributes.src;
          if(key==='align'&&node.type==='core/paragraph'&&node.attributes.appearance){delete node.attributes.appearance.text_align;if(!Object.keys(node.attributes.appearance).length)delete node.attributes.appearance;}
        });
      });
      this.root.addEventListener('keydown', (event) => {
        const findPanel=event.target.closest('.rb-find-replace');
        if(findPanel && event.key==='Escape'){event.preventDefault();this.closeFindReplace();return;}
        const findField=event.target.closest('[data-find-query]');
        if(findField && event.key==='Enter'){event.preventDefault();this.moveFindMatch(event.shiftKey?-1:1);findField.focus();return;}
        const replaceField=event.target.closest('[data-replace-query]');
        if(replaceField && event.key==='Enter'){event.preventDefault();this.replaceFoundMatch(false);return;}
        const inspectorTab=event.target.closest('[data-action="inspector-tab"]');
        if(inspectorTab&&['ArrowLeft','ArrowRight','Home','End'].includes(event.key)){
          const tabs=Array.from(this.root.querySelectorAll('[data-action="inspector-tab"]')),index=tabs.indexOf(inspectorTab);
          const next=event.key==='Home'?tabs[0]:(event.key==='End'?tabs[tabs.length-1]:tabs[(index+(event.key==='ArrowRight'?1:tabs.length-1))%tabs.length]);
          if(next){event.preventDefault();this.inspectorTab=next.dataset.inspectorTab;this.render();const active=Array.from(this.root.querySelectorAll('[data-action="inspector-tab"]')).find((item)=>item.dataset.inspectorTab===this.inspectorTab);if(active)active.focus();}return;
        }
        const ribbonTab = event.target.closest('[data-action="ribbon-tab"]');
        if (ribbonTab && ['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) {
          const tabs = Array.from(this.root.querySelectorAll('[data-action="ribbon-tab"]'));
          const index = tabs.indexOf(ribbonTab);
          const next = event.key === 'Home' ? tabs[0] : (event.key === 'End' ? tabs[tabs.length - 1] : tabs[(index + (event.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length]);
          if (next) { event.preventDefault(); this.activeRibbonTab = next.dataset.tab; this.render(); const focus=this.root.querySelector('[data-action="ribbon-tab"][data-tab="' + this.activeRibbonTab + '"]'); if (focus) focus.focus(); }
          return;
        }
        const outlineButton = event.target.closest('.rb-outline-button');
        if (outlineButton && ['ArrowUp','ArrowDown','ArrowLeft','ArrowRight','Home','End'].includes(event.key)) {
          const targetId = getOutlineNavigationTarget(this.state.blocks, outlineButton.dataset.nodeId, event.key);
          const targetButton = targetId ? Array.from(this.root.querySelectorAll('.rb-outline-button')).find((button) => button.dataset.nodeId === targetId) : null;
          event.preventDefault();
          if (targetButton) { targetButton.focus(); this.selectNode(targetId); this.announceStatus(this.strings.statusOutlineChanged); }
          return;
        }
        if (event.target.closest('[data-inserter-search]')) {
          if (event.key === 'Escape') { event.preventDefault(); this.closeInserter(true); return; }
          if (event.key === 'Enter') { event.preventDefault(); const first = this.root.querySelector('.rb-inserter-option:not([hidden])'); if (first) { if (first.dataset.pattern) this.insertPattern(first.dataset.pattern); else this.insert(first.dataset.type, first.dataset.count); } return; }
        }
        if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && event.target.closest('[data-edit-content]')) {
          const editor = event.target.closest('[data-edit-content]'); const offset = this.getCaretOffset(editor);
          const text = editor.textContent || '';
          if (offset === 0 || /\s/.test(text.charAt(offset - 1))) {
            event.preventDefault(); this.insertPlainText(editor, '/');
            this.slashTrigger = { nodeId: editor.dataset.editContent, offset: this.getCaretOffset(editor) };
            this.openInserter(editor.dataset.editContent, true, editor); return;
          }
        }
        const inTextEditor = !!event.target.closest('[data-edit-content]');
        const inFormControl = !!event.target.closest('input,textarea,select,[data-inserter-search]');
        const inBlock = !!event.target.closest('.rb-block[data-node-id]');
        const command = classifyKeyboardShortcut(event, { inTextEditor, inFormControl, inBlock });
        if (command === 'undo' || command === 'redo') { event.preventDefault(); this.action(command); return; }
        if (command === 'find') { event.preventDefault(); this.activeRibbonTab='review'; this.findReplaceOpen=true; this.render(); const find=this.root.querySelector('[data-find-query]'); if(find)find.focus(); return; }
        if (['bold','italic','underline'].includes(command)) { event.preventDefault(); this.captureInlineSelection(); this.applyInlineMark(command); return; }
        if (command === 'link') { event.preventDefault(); this.captureInlineSelection(); const link = this.root.querySelector('[data-inline-link-input]'); if (link) link.focus(); return; }
        if (event.key === 'Enter' && event.target.closest('[data-edit-content]')) { event.preventDefault(); this.insertPlainText(event.target.closest('[data-edit-content]'), '\n'); return; }
        if (command !== 'move-up' && command !== 'move-down') return;
        const card = event.target.closest('.rb-block[data-node-id]'); if (!card) return;
        event.preventDefault(); this.moveNode(card.dataset.nodeId, command === 'move-up' ? -1 : 1);
      });
      this.root.addEventListener('paste', (event) => {
        const field = event.target.closest('[data-edit-content]');
        if (!field) return;
        event.preventDefault();
        const text = event.clipboardData ? event.clipboardData.getData('text/plain') : '';
        this.insertPlainText(field, text);
      });
      this.root.addEventListener('dragover', (event) => {
        if (event.dataTransfer && (Array.from(event.dataTransfer.types || []).includes('Files') || Array.from(event.dataTransfer.types || []).includes('application/x-reamurcms-audio') || Array.from(event.dataTransfer.types || []).includes('application/x-reamurcms-video'))) { event.preventDefault(); this.root.classList.add('rb-file-drop-active'); }
      });
      this.root.addEventListener('dragleave', (event) => { if (!this.root.contains(event.relatedTarget)) this.root.classList.remove('rb-file-drop-active'); });
      this.root.addEventListener('drop', (event) => {
        this.root.classList.remove('rb-file-drop-active');
        const audioPath = event.dataTransfer && event.dataTransfer.getData('application/x-reamurcms-audio');
        const videoPath = event.dataTransfer && event.dataTransfer.getData('application/x-reamurcms-video');
        if (audioPath || videoPath) {
          event.preventDefault(); event.stopImmediatePropagation(); this.insertMediaReference(audioPath ? 'audio' : 'video', audioPath || videoPath); return;
        }
        const files=event.dataTransfer && Array.from(event.dataTransfer.files || []);
        if (files && files.length) { event.preventDefault(); event.stopImmediatePropagation(); this.importLocalFile(files[0]); }
      });
      this.root.addEventListener('dragstart', (event) => {
        const handle = event.target.closest('[data-drag-handle]');
        const card = handle && handle.closest('[data-node-id]');
        if (!card) { event.preventDefault(); return; }
        this.dragId = card.dataset.nodeId;
        if (event.dataTransfer) { event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', this.dragId); }
      });
      this.root.addEventListener('dragover', (event) => { if (event.target.closest('.rb-block[data-node-id]') && this.dragId) event.preventDefault(); });
      this.root.addEventListener('drop', (event) => {
        const editor = event.target.closest('[data-edit-content]');
        if (editor && !this.dragId) { event.preventDefault(); this.insertPlainText(editor, event.dataTransfer ? event.dataTransfer.getData('text/plain') : ''); return; }
        const target = event.target.closest('.rb-block[data-node-id]');
        if (!target || !this.dragId) return; event.preventDefault(); this.reorderSibling(this.dragId, target.dataset.nodeId); this.dragId = null;
      });
      this.root.addEventListener('dragend', () => { this.dragId = null; });
    }

    restoreWorkspacePanelAfterInserter() {
      if (this.inserterHasPreviousPanel) this.activePanel = this.inserterPreviousPanel;
      this.inserterOpen = false;
      this.inserterPreviousPanel = null;
      this.inserterHasPreviousPanel = false;
      this.inserterReturnFocus = null;
      this.insertAfterId = null;
      this.slashTrigger = null;
      this.inserterQuery = '';
    }

    activateWorkspaceTool(button) {
      const tool=button.dataset.tool;
      if(tool==='elements'){
        this.workspaceView='editor';
        if(this.activePanel==='elements'&&this.inserterOpen){this.closeInserter(false);const target=Array.from(this.root.querySelectorAll('[data-action="workspace-tool"]')).find((item)=>item.dataset.tool==='elements');if(target)target.focus();}
        else{this.previewMode=false;this.openInserter(this.selectedId,false,button);}
        return;
      }
      const wasPreview=this.previewMode;
      if(tool==='home'){this.workspaceView='editor';this.activeRibbonTab='home';this.previewMode=false;}
      else if(tool==='models'){this.workspaceView='models';this.previewMode=false;}
      else if(tool==='accessibility'){this.workspaceView='accessibility';this.previewMode=false;}
      else if(tool==='links'){this.workspaceView='links';this.previewMode=false;}
      else if(tool==='internal-anchors'){this.workspaceView='internal-anchors';this.previewMode=false;}
      else if(tool==='readability'){this.workspaceView='readability';this.previewMode=false;}
      else if(tool==='preflight'){this.workspaceView='preflight';this.previewMode=false;}
      else if(tool==='revision-compare'){this.workspaceView='revision-compare';this.previewMode=false;this.revisionCompareIndex=null;}
      else if(tool==='table-of-contents'){this.workspaceView='table-of-contents';this.previewMode=false;}
      else if(tool==='library'){this.workspaceView='library';this.previewMode=false;}
      else if(tool==='assistant'){this.workspaceView='assistant';this.previewMode=false;}
      else if(tool==='export'){this.workspaceView='export';this.previewMode=false;}
      else if(tool==='share'){this.workspaceView='share';this.previewMode=false;}
      else if(tool==='help'){this.workspaceView='help';this.previewMode=false;}
      else{
        this.workspaceView='editor';
        if(tool!=='view')this.previewMode=false;
      }
      if(this.inserterOpen)this.restoreWorkspacePanelAfterInserter();
      if(tool==='structure')this.activePanel='structure';
      else if(tool==='appearance')this.inspectorOpen=wasPreview?true:!this.inspectorOpen;
      else{
        const tabs={text:'home',media:'media',review:'review',view:'view'};
        if(tabs[tool])this.activeRibbonTab=tabs[tool];
      }
      if(['models','accessibility','links','internal-anchors','readability','preflight','revision-compare','table-of-contents','library','assistant','export','share','help'].includes(tool))this.activePanel='structure';
      this.findReplaceOpen=false;
      this.render();
      const focusTarget=Array.from(this.root.querySelectorAll('[data-action="workspace-tool"]')).find((item)=>item.dataset.tool===tool);
      if(focusTarget)focusTarget.focus();
    }

    activateOfficeMenu(button) {
      const item=button.dataset.menu;
      if(this.inserterOpen)this.restoreWorkspacePanelAfterInserter();
      if(item==='file'){this.workspaceView='export';this.activePanel='structure';}
      else if(item==='help'){this.workspaceView='help';this.activePanel='structure';}
      else{
        this.workspaceView='editor';this.previewMode=false;
        this.activeRibbonTab={insert:'insert',tools:'layout',format:'layout',edit:'home'}[item]||'home';
      }
      this.findReplaceOpen=false;this.render();
      const focusTarget=Array.from(this.root.querySelectorAll('[data-action="office-menu"]')).find((entry)=>entry.dataset.menu===item);
      if(focusTarget)focusTarget.focus();
    }

    openPageMediaPicker(kind,nodeId) {
      if(nodeId)this.selectedId=nodeId;
      const event=new CustomEvent('cms-page-media-open',{detail:{editor:this,nodeId:nodeId||this.selectedId,kind},cancelable:true});
      global.document.dispatchEvent(event);
      if(!event.defaultPrevented)this.announceStatus(this.strings.pageMediaUnavailable||'A biblioteca de mídia do Pages não carregou.');
      return event.defaultPrevented;
    }

    openWorkspaceMedia(kind) {
      const found=this.selectedId?this.findPath(this.state.blocks,this.selectedId):null;
      const expected={image:['core/image','core/cover'],file:['core/file'],audio:['core/audio'],video:['core/video_file','core/video_embed']};
      if(!found||!expected[kind]||!expected[kind].includes(found.node.type)){this.announceStatus(this.ui.selectBlock);return;}
      if(this.options.mediaPickers===false&&this.options.pageMediaPicker!==true&&!(kind==='image'&&this.options.pageImagePicker===true)){this.announceStatus(this.ui.noPicker);return;}
      const pageKind={image:'image',file:'download',audio:'audio',video:'video'}[kind];
      if(this.options.pageMediaPicker===true){this.openPageMediaPicker(pageKind,this.selectedId);return;}
      if(kind==='image'&&this.options.pageImagePicker===true){this.openPageMediaPicker('image',this.selectedId);return;}
      if(kind==='image')global.document.dispatchEvent(new CustomEvent('blog-block-media-open',{detail:{editor:this,nodeId:this.selectedId}}));
      else{const eventName=kind==='file'?'blog-block-file-open':kind==='audio'?'blog-block-audio-open':'blog-block-video-open';global.document.dispatchEvent(new CustomEvent(eventName,{detail:{editor:this,nodeId:this.selectedId}}));}
    }

    filterWorkspacePatterns() {
      const query=String(this.patternQuery||'').trim().toLocaleLowerCase();
      const category=this.patternFilter||'all';let count=0;
      this.root.querySelectorAll('[data-pattern-kind]').forEach((card)=>{
        const text=String(card.dataset.patternSearch||'').toLocaleLowerCase();
        const visible=(!query||text.includes(query))&&(category==='all'||card.dataset.patternKind===category);
        card.hidden=!visible;if(visible)count++;
      });
      const empty=this.root.querySelector('[data-pattern-empty]');if(empty)empty.hidden=count>0;
    }

    action(name, button) {
      if (this.locked&&!['workspace-tool','office-menu','toggle-fullscreen','compare-draft-revision'].includes(name)) return;
      if (name === 'insert') this.insert(button.dataset.type, button.dataset.count);
      else if (name === 'insert-pattern') this.insertPattern(button.dataset.pattern);
      else if (name === 'insert-content-model') this.insertContentModel(button.dataset.modelId);
      else if (name === 'accessibility-focus') { const id=button.dataset.nodeId;if(id&&this.findPath(this.state.blocks,id)){this.workspaceView='editor';this.previewMode=false;this.activeRibbonTab='home';this.activePanel='structure';this.selectedId=id;this.render();this.focusNode(id);this.announceStatus(this.strings.statusFocusChanged);} }
      else if (name === 'link-review-focus') { const id=button.dataset.nodeId;if(id&&this.findPath(this.state.blocks,id)){this.workspaceView='editor';this.previewMode=false;this.activeRibbonTab='home';this.activePanel='structure';this.selectedId=id;this.render();this.focusNode(id);this.announceStatus(this.strings.statusFocusChanged);} }
      else if (name === 'internal-anchor-focus') { const id=button.dataset.nodeId;if(id&&this.findPath(this.state.blocks,id)){this.workspaceView='editor';this.previewMode=false;this.activeRibbonTab='home';this.activePanel='structure';this.selectedId=id;this.render();this.focusNode(id);this.announceStatus(this.strings.statusFocusChanged);} }
      else if (name === 'readability-focus') { const id=button.dataset.nodeId;if(id&&this.findPath(this.state.blocks,id)){this.workspaceView='editor';this.previewMode=false;this.activeRibbonTab='home';this.activePanel='structure';this.selectedId=id;this.render();this.focusNode(id);this.announceStatus(this.strings.statusFocusChanged);} }
      else if (name === 'preflight-focus') { const id=button.dataset.nodeId;if(id&&this.findPath(this.state.blocks,id)){this.workspaceView='editor';this.previewMode=false;this.activeRibbonTab='home';this.activePanel='structure';this.selectedId=id;this.render();this.focusNode(id);this.announceStatus(this.strings.statusFocusChanged);} }
      else if (name === 'revision-compare-focus') { const id=button.dataset.nodeId;if(id&&this.findPath(this.state.blocks,id)){this.workspaceView='editor';this.previewMode=false;this.activeRibbonTab='home';this.activePanel='structure';this.selectedId=id;this.render();this.focusNode(id);this.announceStatus(this.strings.statusFocusChanged);} }
      else if (name === 'insert-saved-pattern') this.insertSavedPattern(button.dataset.patternId);
      else if (name === 'save-selected-pattern') this.saveSelectedPattern(button.dataset.nodeId);
      else if (name === 'rename-saved-pattern') this.renameSavedPattern(button.dataset.patternId);
      else if (name === 'delete-saved-pattern') this.deleteSavedPattern(button.dataset.patternId);
      else if (name === 'toggle-inserter') {
        const afterId=button.dataset.afterId||null;
        if(afterId||!(this.activePanel==='elements'&&this.inserterOpen))this.openInserter(afterId||this.selectedId,false,button);
        else{this.closeInserter(false);const trigger=this.root.querySelector('.rb-ribbon [data-action="toggle-inserter"]')||this.root.querySelector('[data-action="toggle-inserter"]');if(trigger)trigger.focus();}
      }
      else if (name === 'ribbon-tab') { this.workspaceView='editor'; this.activeRibbonTab = button.dataset.tab; this.render(); }
      else if (name === 'workspace-tool') { this.activateWorkspaceTool(button); }
      else if (name === 'office-menu') { this.activateOfficeMenu(button); }
      else if (name === 'workspace-media-open') { this.openWorkspaceMedia(button.dataset.kind); }
      else if (name === 'workspace-pattern-filter') { this.patternFilter=button.dataset.filter||'all'; this.render(); const active=Array.from(this.root.querySelectorAll('[data-action="workspace-pattern-filter"]')).find((item)=>item.dataset.filter===this.patternFilter); if(active)active.focus(); }
      else if (name === 'import-file') { const input=this.root.querySelector('[data-import-file]'); if (input) input.click(); }
      else if (name === 'export-document') this.exportDocumentFile();
      else if (name === 'download-markdown') this.exportMarkdownFile();
      else if (name === 'preview-markdown') { this.workspaceView='markdown-preview';this.previewMode=false;this.render();const title=this.root.querySelector('[data-markdown-preview-title]');if(title)title.focus(); }
      else if (name === 'markdown-preview-back') { this.workspaceView='export';this.render();const target=this.root.querySelector('[data-action="preview-markdown"]');if(target)target.focus(); }
      else if(name==='markdown-import-open'){const input=this.root.querySelector('[data-markdown-import-file]');if(input)input.click();}
      else if(name==='markdown-import-back'){this.workspaceView='export';this.render();const target=this.root.querySelector('[data-action="markdown-import-open"]');if(target)target.focus();}
      else if(name==='markdown-import-confirm')this.confirmMarkdownImport();
      else if(name==='table-of-contents-back'){this.workspaceView='editor';this.render();const focus=Array.from(this.root.querySelectorAll('[data-action=\"workspace-tool\"]')).find((item)=>item.dataset.tool==='table-of-contents');if(focus)focus.focus();}
      else if(name==='table-of-contents-confirm')this.insertTableOfContents();
      else if (name === 'markdown-warning-focus') { const id=button.dataset.nodeId;if(id&&this.findPath(this.state.blocks,id)){this.workspaceView='editor';this.previewMode=false;this.activeRibbonTab='home';this.activePanel='structure';this.selectedId=id;this.render();this.focusNode(id);this.announceStatus(this.strings.statusFocusChanged);} }
      else if (name === 'toggle-revision-history') { this.historyOpen = !this.historyOpen; this.render(); }
      else if (name === 'inspector-tab') { this.inspectorTab=button.dataset.inspectorTab==='customize'?'customize':'content'; this.render(); const active=Array.from(this.root.querySelectorAll('[data-action="inspector-tab"]')).find((item)=>item.dataset.inspectorTab===this.inspectorTab); if(active)active.focus(); }
      else if (name === 'reset-appearance') this.updateNode(button.dataset.nodeId || this.selectedId,(node)=>{delete node.attributes.appearance;});
      else if (name === 'customize-block') { this.selectedId=button.dataset.nodeId||this.selectedId;this.inspectorTab='customize';this.previewMode=false;if(this.fullscreen){this.inspectorOpen=true;this.activePanel='settings';}else this.activePanel='settings';this.render();const tab=Array.from(this.root.querySelectorAll('[data-action="inspector-tab"]')).find((item)=>item.dataset.inspectorTab==='customize');if(tab)tab.focus(); }
      else if (name === 'toggle-find-replace') { if(this.findReplaceOpen){this.closeFindReplace();}else{this.activeRibbonTab='review';this.findReplaceOpen=true;this.render();const input=this.root.querySelector('[data-find-query]');if(input)input.focus();} }
      else if (name === 'find-next') this.moveFindMatch(1);
      else if (name === 'find-previous') this.moveFindMatch(-1);
      else if (name === 'replace-one') this.replaceFoundMatch(false);
      else if (name === 'replace-all') this.replaceFoundMatch(true);
      else if (name === 'compare-draft-revision') { const index=Number(button.dataset.revisionIndex);const revisions=this.getDraftHistory().filter((revision)=>revision&&revision.document&&typeof revision.document==='object');if(Number.isInteger(index)&&revisions[index]){this.revisionCompareIndex=index;this.render();const selected=this.root.querySelector('[data-action="compare-draft-revision"][data-revision-index="'+index+'"]');if(selected)selected.focus();} }
      else if (name === 'restore-draft-revision') this.restoreDraftRevision(Number(button.dataset.revisionIndex));
      else if(name==='toggle-preview'){
        this.workspaceView='editor';
        this.previewMode=!this.previewMode;
        if(this.previewMode){this.activePanel=null;this.inserterOpen=false;this.inserterReturnFocus=null;this.inserterPreviousPanel=null;this.inserterHasPreviousPanel=false;}
        this.render();
      }
      else if (name === 'builder-panel') {
        const panel=button.dataset.panel;
        if(this.fullscreen&&panel==='settings')this.inspectorOpen=!this.inspectorOpen;
        else if(panel==='elements'){
          if(this.activePanel==='elements'&&this.inserterOpen)this.closeInserter(false);
          else this.openInserter(this.selectedId,false,button);
          return;
        }else if(this.fullscreen){this.activePanel=panel;this.inserterOpen=false;this.inserterPreviousPanel=null;this.inserterHasPreviousPanel=false;}
        else{this.activePanel=this.activePanel===panel?null:panel;this.inserterOpen=false;this.inserterPreviousPanel=null;this.inserterHasPreviousPanel=false;}
        this.render();
      }
      else if (name === 'align-text') this.alignSelectedText(button.dataset.alignment);
      else if (name === 'duplicate') this.duplicateNode(button.dataset.nodeId || this.selectedId);
      else if (name === 'copy-block') this.copyBlock(button.dataset.nodeId || this.selectedId);
      else if (name === 'paste-block') this.pasteBlock();
      else if (name === 'outline-select') { const id = button.dataset.nodeId; this.selectNode(id); this.focusNode(id); this.announceStatus(this.strings.statusFocusChanged); }
      else if (name === 'undo') this.undo();
      else if (name === 'redo') this.redo();
      else if (name === 'spacer-decrease') this.adjustSpacerHeight(button.dataset.nodeId,-1);
      else if (name === 'spacer-increase') this.adjustSpacerHeight(button.dataset.nodeId,1);
      else if (name === 'separator-width-less') this.adjustSeparatorWidth(button.dataset.nodeId,-1);
      else if (name === 'separator-width-more') this.adjustSeparatorWidth(button.dataset.nodeId,1);
      else if (name === 'group-add-child') { const id=button.dataset.nodeId||this.selectedId;this.selectedId=id;this.openInserter(id,false,button); }
      else if (name === 'column-add-child') { const id=button.dataset.nodeId||this.selectedId;this.selectedId=id;this.openInserter(id,false,button); }
      else if (name === 'delete') this.deleteNode(button.dataset.nodeId || this.selectedId);
      else if (name === 'move-up') this.moveNode(button.dataset.nodeId || this.selectedId, -1);
      else if (name === 'move-down') this.moveNode(button.dataset.nodeId || this.selectedId, 1);
      else if (name === 'indent') this.indentNode(button.dataset.nodeId || this.selectedId);
      else if (name === 'outdent') this.outdentNode(button.dataset.nodeId || this.selectedId);
      else if (name === 'media') { const nodeId=button.dataset.nodeId||this.selectedId;if(this.options.pageMediaPicker===true||this.options.pageImagePicker===true)this.openPageMediaPicker('image',nodeId);else global.document.dispatchEvent(new CustomEvent('blog-block-media-open',{detail:{editor:this,nodeId}})); }
      else if (name === 'file-media') { const nodeId=button.dataset.nodeId||this.selectedId;if(this.options.pageMediaPicker===true)this.openPageMediaPicker('download',nodeId);else global.document.dispatchEvent(new CustomEvent('blog-block-file-open',{detail:{editor:this,nodeId}})); }
      else if (name === 'audio-media') { const nodeId=button.dataset.nodeId||this.selectedId;if(this.options.pageMediaPicker===true)this.openPageMediaPicker('audio',nodeId);else global.document.dispatchEvent(new CustomEvent('blog-block-audio-open',{detail:{editor:this,nodeId}})); }
      else if (name === 'video-media') { const nodeId=button.dataset.nodeId||this.selectedId;if(this.options.pageMediaPicker===true)this.openPageMediaPicker('video',nodeId);else global.document.dispatchEvent(new CustomEvent('blog-block-video-open',{detail:{editor:this,nodeId}})); }
      else if (name === 'format-mark') this.applyInlineMark(button.dataset.mark);
      else if (name === 'threejs-toggle-editor') { const id=button.dataset.nodeId||this.selectedId; this.selectedId=id; this.openThreeJsEditor=this.openThreeJsEditor===id?null:id; this.render(); }
      else if (name === 'threejs-delete-script') { const id=button.dataset.nodeId||this.selectedId; this.statusMessage=this.strings.threejs.scriptDeleted; this.updateNode(id,(node)=>{node.attributes.custom_js='';}); }
      else if (name === 'threejs-add-object') this.updateThreeJsEntry(button.dataset.nodeId || this.selectedId, 'add-object');
      else if (name === 'threejs-add-light') this.updateThreeJsEntry(button.dataset.nodeId || this.selectedId, 'add-light');
      else if (name === 'threejs-remove-object' || name === 'threejs-remove-light') this.updateThreeJsEntry(button.dataset.nodeId || this.selectedId, name.endsWith('object') ? 'remove-object' : 'remove-light', Number(button.dataset.index));
      else if (name.startsWith('table-')) this.updateTable(name, button.dataset.nodeId || this.selectedId, button.dataset.tableHeader);
      else if (name.startsWith('columns-')) this.updateColumns(name, button.dataset.nodeId || this.selectedId);
      else if (name === 'link-apply') this.applyInlineLink(this.linkDraft);
      else if (name === 'link-remove') this.applyInlineLink(null);
      else if (name === 'toggle-fullscreen') this.setFullscreen(!this.fullscreen);
      else if (name === 'preview') { const size=button.dataset.size; if(!['desktop','tablet','mobile'].includes(size))return; this.preview=size; this.root.dataset.preview=size; this.root.querySelectorAll('[data-action="preview"]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.size === size))); }
    }

    setFullscreen(enabled, startup) {
      const next = !!enabled;
      if (next === this.fullscreen) return;
      if (next) {
        this.fullscreenReturnFocus = startup ? null : global.document.activeElement;
        this.fullscreenPreviousPanel = this.activePanel;
        this.fullscreenPreviousAttrs = { role:this.root.getAttribute('role'), modal:this.root.getAttribute('aria-modal'), label:this.root.getAttribute('aria-label'), tabindex:this.root.getAttribute('tabindex') };
        this.fullscreen = true;
        if (!this.activePanel || this.activePanel === 'settings') this.activePanel = 'structure';
        this.inspectorOpen = true;
        this.root.classList.add('is-fullscreen');
        this.root.setAttribute('role','dialog'); this.root.setAttribute('aria-modal','true'); this.root.setAttribute('aria-label',this.strings.fullscreenDialog); this.root.setAttribute('tabindex','-1');
        global.document.body.classList.add('rb-bdre-fullscreen-open');
        this.render();
        const focusExit = () => { const target=this.root.querySelector('[data-action="toggle-fullscreen"]'); if (target) target.focus(); };
        if (global.requestAnimationFrame) global.requestAnimationFrame(focusExit); else global.setTimeout(focusExit,0);
        return;
      }
      this.fullscreen = false;
      this.workspaceView = 'editor';
      this.activePanel = this.fullscreenPreviousPanel;
      this.root.classList.remove('is-fullscreen');
      const previous = this.fullscreenPreviousAttrs || {};
      if (previous.role === null) this.root.removeAttribute('role'); else if (previous.role !== undefined) this.root.setAttribute('role',previous.role);
      if (previous.modal === null) this.root.removeAttribute('aria-modal'); else if (previous.modal !== undefined) this.root.setAttribute('aria-modal',previous.modal);
      if (previous.label === null) this.root.removeAttribute('aria-label'); else if (previous.label !== undefined) this.root.setAttribute('aria-label',previous.label);
      if (previous.tabindex === null) this.root.removeAttribute('tabindex'); else if (previous.tabindex !== undefined) this.root.setAttribute('tabindex',previous.tabindex);
      if (!global.document.querySelector('.rb-editor.is-fullscreen')) global.document.body.classList.remove('rb-bdre-fullscreen-open');
      const returnFocus = this.fullscreenReturnFocus;
      this.fullscreenReturnFocus = null; this.fullscreenPreviousAttrs = null; this.fullscreenPreviousPanel = null;
      this.render();
      const target = returnFocus && returnFocus.isConnected ? returnFocus : this.root.querySelector('[data-action="toggle-fullscreen"]');
      if (target) target.focus();
    }

    findEditor(id) { return Array.from(this.root.querySelectorAll('[data-edit-content]')).find((node) => node.dataset.editContent === id) || null; }

    focusNode(id) {
      const editor = this.findEditor(id);
      const card = Array.from(this.root.querySelectorAll('.rb-block[data-node-id]')).find((item) => item.dataset.nodeId === id);
      const target = editor || card; if (!target) return;
      if (!editor) target.tabIndex = -1;
      target.focus({ preventScroll:true }); target.scrollIntoView({ block:'nearest' });
    }

    getCaretOffset(editor) {
      const selection = global.getSelection(); if (!selection || !selection.rangeCount || !editor.contains(selection.anchorNode)) return 0;
      const range = selection.getRangeAt(0).cloneRange(); range.selectNodeContents(editor); range.setEnd(selection.anchorNode, selection.anchorOffset); return range.toString().length;
    }

    selectNode(id) {
      if (!id || this.selectedId === id) return;
      this.selectedId = id;
      this.root.querySelectorAll('.rb-block').forEach((card) => card.classList.toggle('is-selected', card.dataset.nodeId === id));
      this.root.querySelectorAll('[data-action="outline-select"]').forEach((button) => { if (button.dataset.nodeId === id) button.setAttribute('aria-current','location'); else button.removeAttribute('aria-current'); });
      this.root.querySelectorAll('.rb-inline-toolbar').forEach((toolbar) => toolbar.remove());
      this.updateRibbonState();
      const oldInspector = this.root.querySelector('.rb-inspector');
      if (oldInspector) oldInspector.replaceWith(this.renderInspector());
      this.updateClipboardButtons();
    }

    captureInlineSelection() {
      const selection = global.getSelection();
      if (!selection || !selection.rangeCount || selection.isCollapsed) return;
      const range = selection.getRangeAt(0);
      const editor = range.commonAncestorContainer.nodeType === 1
        ? range.commonAncestorContainer.closest && range.commonAncestorContainer.closest('[data-edit-content]')
        : range.commonAncestorContainer.parentElement && range.commonAncestorContainer.parentElement.closest('[data-edit-content]');
      if (!editor || !this.root.contains(editor)) return;
      const startRange = global.document.createRange(); startRange.selectNodeContents(editor); startRange.setEnd(range.startContainer, range.startOffset);
      const endRange = global.document.createRange(); endRange.selectNodeContents(editor); endRange.setEnd(range.endContainer, range.endOffset);
      this.inlineBookmark = { nodeId: editor.dataset.editContent, start: startRange.toString().length, end: endRange.toString().length };
      if (this.selectedId !== editor.dataset.editContent) this.selectNode(editor.dataset.editContent);
      this.updateInlineToolbarState();
    }

    updateInlineToolbarState() {
      const bookmark = this.inlineBookmark;
      if (!bookmark || bookmark.start === bookmark.end) return;
      const found = this.findPath(this.state.blocks, bookmark.nodeId); if (!found || !Array.isArray(found.node.content)) return;
      let cursor = 0; const selected = [];
      found.node.content.forEach((run) => {
        const start = cursor, end = cursor + run.text.length; cursor = end;
        if (start < bookmark.end && end > bookmark.start) selected.push(run);
      });
      INLINE_MARKS.forEach((mark) => {
        const button = Array.from(this.root.querySelectorAll('[data-action="format-mark"]')).find((item) => item.dataset.mark === mark);
        if (button) button.setAttribute('aria-pressed', String(selected.length > 0 && selected.every((run) => run.marks.includes(mark))));
      });
      const links = selected.map((run) => run.href);
      this.linkDraft = links.length && links.every((href) => href === links[0]) ? (links[0] || '') : '';
      const input = this.root.querySelector('[data-inline-link-input]');
      if (input && global.document.activeElement !== input) input.value = this.linkDraft;
    }

    restoreInlineSelection(bookmark) {
      if (!bookmark) return;
      const editor = this.findEditor(bookmark.nodeId); if (!editor) return;
      const walker = global.document.createTreeWalker(editor, global.NodeFilter.SHOW_TEXT);
      const nodes = []; let current; let total = 0;
      while ((current = walker.nextNode())) { nodes.push({ node: current, start: total, end: total + current.nodeValue.length }); total += current.nodeValue.length; }
      if (!nodes.length) { editor.focus(); return; }
      const locate = (offset) => {
        const item = nodes.find((entry) => offset <= entry.end) || nodes[nodes.length - 1];
        return [item.node, Math.max(0, Math.min(item.node.nodeValue.length, offset - item.start))];
      };
      const [startNode, startOffset] = locate(bookmark.start); const [endNode, endOffset] = locate(bookmark.end);
      const range = global.document.createRange(); range.setStart(startNode, startOffset); range.setEnd(endNode, endOffset);
      editor.focus(); const selection = global.getSelection(); selection.removeAllRanges(); selection.addRange(range);
    }

    insertPlainText(editor, text) {
      if (!editor || !text) return;
      const selection = global.getSelection(); let range;
      if (selection && selection.rangeCount && editor.contains(selection.anchorNode)) range = selection.getRangeAt(0);
      else { range = global.document.createRange(); range.selectNodeContents(editor); range.collapse(false); }
      range.deleteContents();
      const node = global.document.createTextNode(String(text).replace(/\r\n?/g, '\n'));
      range.insertNode(node); range.setStartAfter(node); range.collapse(true);
      if (selection) { selection.removeAllRanges(); selection.addRange(range); }
      editor.dispatchEvent(new Event('input', { bubbles: true }));
    }

    removeInlineRange(runs, start, end) {
      let cursor = 0; const out = [];
      runs.forEach((run) => {
        const from = cursor, to = cursor + run.text.length; cursor = to;
        if (to <= start || from >= end) { out.push(CLONE(run)); return; }
        const left = Math.max(0, start - from), right = Math.min(run.text.length, end - from);
        if (left > 0) out.push({ text:run.text.slice(0,left), marks:run.marks.slice(), href:run.href });
        if (right < run.text.length) out.push({ text:run.text.slice(right), marks:run.marks.slice(), href:run.href });
      });
      return canonicalRuns(out);
    }

    transformRuns(runs, start, end, transform) {
      let cursor = 0; const out = [];
      runs.forEach((run) => {
        const from = cursor, to = cursor + run.text.length; cursor = to;
        if (to <= start || from >= end) { out.push(CLONE(run)); return; }
        const left = Math.max(0, start - from), right = Math.min(run.text.length, end - from);
        if (left > 0) out.push({ text: run.text.slice(0, left), marks: run.marks.slice(), href: run.href });
        const selected = { text: run.text.slice(left, right), marks: run.marks.slice(), href: run.href };
        transform(selected); out.push(selected);
        if (right < run.text.length) out.push({ text: run.text.slice(right), marks: run.marks.slice(), href: run.href });
      });
      return canonicalRuns(out);
    }

    applyInlineMark(mark) {
      const bookmark = this.inlineBookmark;
      if (!bookmark || bookmark.start === bookmark.end || !INLINE_MARKS.includes(mark)) return;
      const found = this.findPath(this.state.blocks, bookmark.nodeId); if (!found || !BLOCK_TEXT_TYPES.has(found.node.type)) return;
      const overlapping = found.node.content.filter((run, index) => {
        const start = found.node.content.slice(0, index).reduce((sum, item) => sum + item.text.length, 0);
        return start < bookmark.end && start + run.text.length > bookmark.start;
      });
      const remove = overlapping.length > 0 && overlapping.every((run) => run.marks.includes(mark));
      const next = CLONE(this.state); const node = this.findPath(next.blocks, bookmark.nodeId).node;
      node.content = this.transformRuns(node.content, bookmark.start, bookmark.end, (run) => {
        run.marks = remove ? run.marks.filter((item) => item !== mark) : [...new Set(run.marks.concat(mark))];
      });
      this.commit(next, bookmark.nodeId); this.inlineBookmark = bookmark; this.restoreInlineSelection(bookmark);
    }

    applyInlineLink(href) {
      const bookmark = this.inlineBookmark;
      if (!bookmark || bookmark.start === bookmark.end) return;
      const found=this.findPath(this.state.blocks,bookmark.nodeId);if(!found)return;
      const node=found.node;if(['core/button','core/file','core/audio'].includes(node.type))return;
      const target = href == null ? null : String(href).trim();
      if (target !== null && !validActionUrl(target)) {
        this.inlineNotice = this.strings.invalidLink || 'Invalid link.';
        const status = this.root.querySelector('.rb-inline-status'); if (status) status.textContent = this.inlineNotice;
        return;
      }
      const next = CLONE(this.state); const nextNode = this.findPath(next.blocks, bookmark.nodeId).node;
      nextNode.content = this.transformRuns(nextNode.content, bookmark.start, bookmark.end, (run) => { run.href = target; });
      this.linkDraft = target || ''; this.inlineNotice = '';
      this.commit(next, bookmark.nodeId); this.inlineBookmark = bookmark; this.restoreInlineSelection(bookmark);
    }

    createDefault(type, presetCount) {
      if (type === 'core/heading') return this.node(type, { level: 2 }, 'Título');
      if (type === 'core/list') return this.node(type, { ordered: false }, '', [this.node('core/list-item', {}, 'Novo item'), this.node('core/list-item', {}, 'Novo item')]);
      if (type === 'core/list-item') return this.node(type, {}, 'Novo item');
      if (type === 'core/image') return this.node(type, { src: '', alt: '' }, '');
      if (type === 'core/file') return this.node(type, { src: '' }, 'Baixar arquivo');
      if (type === 'core/audio') return this.node(type, { src: '' }, 'Faixa de áudio');
      if (type === 'core/gallery') return this.node(type, { columns:2, align:'content' }, '', [this.node('core/image',{src:'',alt:''},''),this.node('core/image',{src:'',alt:''},'')]);
      if (type === 'core/cover') return this.node(type, { src:'', alt:'', align:'wide' }, '', [this.node('core/heading',{level:2},'Título da capa'),this.node('core/paragraph',{},'Adicione uma descrição.'),this.node('core/button',{url:'/'},'Saiba mais')]);
      if (type === 'core/video_embed') return this.node(type, { url: '' }, '');
      if (type === 'core/video_file') return this.node(type, { src: '' }, '');
      if (type === 'core/threejs_thumb') return this.node(type, { scene_json: JSON.stringify({version:1,background:'#101827',camera:{fov:42,position:[3,2.4,4.5]},objects:[{type:'torusKnot',color:'#77aaff',position:[0,0,0],rotation:[0,0,0],scale:1,animation:'spin',roughness:0.3,metalness:0.55,wireframe:false}],lights:[{type:'ambient',color:'#ffffff',intensity:1.4},{type:'directional',color:'#ffffff',intensity:2.4,position:[4,5,3]}],auto_rotate:true}), label:'Thumb 3D', custom_js:'' }, '');
      if (type === 'core/button') return this.node(type, { url: '/' }, 'Saiba mais');
      if (type === 'core/separator') return this.node(type, {}, '');
      if (type === 'core/group') return this.node(type, {}, '', [this.node('core/paragraph', {}, 'Bloco do grupo')]);
      if (type === 'core/columns') {
        const count = Number(presetCount) === 3 ? 3 : 2;
        const columns = [];
        for (let i = 0; i < count; i += 1) columns.push(this.node('core/column', {}, '', [this.node('core/paragraph', {}, 'Coluna ' + (i + 1))]));
        return this.node(type, { count }, '', columns);
      }
      if (type === 'core/quote') return this.node(type, {}, 'Citação');
      if (type === 'core/callout') return this.node(type, { tone:'info' }, this.strings.callout.defaultText || 'Adicione uma informação importante.');
      if (type === 'core/details') return this.node(type, { summary:this.strings.details.summaryDefault || 'Mais informações' }, this.strings.details.defaultText || 'Adicione os detalhes aqui.');
      if (type === 'core/spacer') return this.node(type, { height:32 }, '');
      if (type === 'core/code') return this.node(type, {}, this.strings.preformatted.defaultText || '');
      if (type === 'core/table') {
        const rows = [];
        for (let r = 0; r < 3; r += 1) {
          const cells = [];
          for (let c = 0; c < 3; c += 1) cells.push(this.node('core/table-cell', {}, r === 0 ? 'Cabeçalho ' + (c + 1) : 'Célula'));
          rows.push(this.node('core/table-row', {}, '', cells));
        }
        return this.node(type, { header:true }, '', rows);
      }
      return this.node('core/paragraph', {}, 'Escreva seu conteúdo…');
    }

    insertTypes(afterId) {
      const found = afterId ? this.findPath(this.state.blocks, afterId) : null;
      if (found && (found.node.type === 'core/table-cell' || (found.parent && found.parent.type === 'core/table-row'))) return [];
      if (found && (found.node.type === 'core/list' || (found.parent && found.parent.type === 'core/list'))) return [{ type:'core/list-item', category:'content' }];
      if (found && found.parent && found.parent.type === 'core/gallery') return found.parent.children.length < 20 ? [{ type:'core/image', category:'media' }] : [];
      if (found && found.parent && found.parent.type === 'core/cover') return found.parent.children.length < 12 ? ['core/heading','core/paragraph','core/quote','core/button'].map((type) => ({ type, category:'content' })) : [];
      if (found && ['core/group','core/column'].includes(found.node.type)) return TYPES.map(([type]) => ({ type, category: BLOCK_CATEGORIES[type] || 'content' }));
      if (found && (found.node.type === 'core/columns' || (found.parent && found.parent.type === 'core/columns'))) return [];
      return TYPES.map(([type]) => ({ type, category: BLOCK_CATEGORIES[type] || 'content' }));
    }

    canInsertPatterns(afterId) {
      const found = afterId ? this.findPath(this.state.blocks, afterId) : null;
      if (found && found.parent && ['core/cover','core/gallery'].includes(found.parent.type)) return false;
      return this.insertTypes(afterId).some((item) => !['core/list-item','core/column'].includes(item.type));
    }

    createPattern(pattern) {
      if (pattern === 'two_columns') return this.node('core/columns',{count:2},'',[
        this.node('core/column',{},'', [this.node('core/heading',{level:2},'Destaque principal'),this.node('core/paragraph',{},'Descreva o primeiro benefício.')]),
        this.node('core/column',{},'', [this.node('core/heading',{level:2},'Outro benefício'),this.node('core/paragraph',{},'Descreva o segundo benefício.')])
      ]);
      if (pattern === 'quote_cta') return this.node('core/group',{},'',[
        this.node('core/quote',{},'Uma frase de destaque para seus leitores.'),this.node('core/button',{url:'/contato'},'Fale conosco')
      ]);
      if (pattern === 'section_intro') return this.node('core/group',{},'',[
        this.node('core/heading',{level:2},'Título da seção'),this.node('core/paragraph',{},'Apresente o conteúdo desta seção.'),this.node('core/separator',{},'')
      ]);
      if (pattern === 'cover_hero') return this.node('core/cover',{src:'',alt:'',align:'wide'},'',[
        this.node('core/heading',{level:2},'Título de destaque'),this.node('core/paragraph',{},'Uma breve introdução.'),this.node('core/button',{url:'/'},'Saiba mais')
      ]);
      return null;
    }

    createContentModelNode(spec) {
      return this.node(spec.type,spec.attributes||{},spec.content||'',(spec.children||[]).map((child)=>this.createContentModelNode(child)));
    }

    createContentModel(modelId) {
      const model=this.contentModels.templates.find((item)=>item.id===modelId);
      if(!model)return null;
      const children=model.blocks.map((spec)=>this.createContentModelNode(spec));
      return this.node('core/group',{outline_label:model.outline},'',children);
    }

    insertContentModel(modelId) {
      if(this.locked)return false;
      const afterId=this.selectedId||null;
      const allowedTypes=this.insertTypes(afterId).map((item)=>item.type);
      if(!this.canInsertPatterns(afterId)||!allowedTypes.includes('core/group')){this.announceStatus(this.contentModels.unavailable);return false;}
      const node=this.createContentModel(modelId);if(!node)return false;
      const next=CLONE(this.state);this.prepareSlashTarget(next,afterId);
      const location=this.insertionLocation(next.blocks,afterId);location.target.splice(location.index,0,node);
      const validation=validateDocument(next,this.allowPageMedia);
      if(!validation.valid||utf8Length(JSON.stringify(next))>4194304){this.announceStatus(this.contentModels.unavailable);return false;}
      this.finishInsert(next,node);return true;
    }


    insertTableOfContents() {
      const copy=this.tableOfContents,plan=createTableOfContentsPlan(this.state,this.tocLevels,this.allowPageMedia);
      if(!plan.valid||!plan.headings.length){this.announceStatus(plan.valid?copy.empty:copy.invalid);return false;}
      if(this.locked)return false;
      const afterId=this.selectedId||null,allowedTypes=this.insertTypes(afterId).map((item)=>item.type);
      if(!this.canInsertPatterns(afterId)||!allowedTypes.includes('core/group')){this.announceStatus(copy.unavailable);return false;}
      const next=CLONE(this.state);this.prepareSlashTarget(next,afterId);
      plan.headings.forEach((heading)=>{if(!heading.needsAnchor)return;const found=this.findPath(next.blocks,heading.nodeId);if(found&&found.node.type==='core/heading')found.node.attributes.anchor=heading.anchor;});
      const entries=plan.headings.map((heading)=>this.node('core/list-item',{},[{text:heading.text,marks:[],href:'#'+heading.anchor}]));
      const list=this.node('core/list',{ordered:false},'',entries);
      const title=this.node('core/paragraph',{},[{text:copy.sumHeading,marks:['bold'],href:null}]);
      const group=this.node('core/group',{outline_label:copy.groupLabel,toc_generated:true},'', [title,list]);
      const location=this.insertionLocation(next.blocks,afterId);location.target.splice(location.index,0,group);
      const validation=validateDocument(next,this.allowPageMedia);
      if(!validation.valid||utf8Length(JSON.stringify(next))>4194304){this.announceStatus(copy.invalid);return false;}
      this.workspaceView='editor';this.finishInsert(next,group);this.statusMessage=copy.done;this.announceStatus(copy.done);this.render();this.focusNode(group.id);return true;
    }

    renderPatternLibrary() {
      const panel=this.el('section','rb-pattern-library rb-pattern-library-page');
      const heading=this.el('h1','rb-pattern-library-title',this.ui.modelsTitle);
      const header=this.el('div','rb-model-library-header');header.append(heading,this.button(this.ui.modelsBack||this.ui.home,'workspace-tool',{tool:'home',icon:'collapse',showLabel:true}));
      panel.append(header,this.el('p','rb-pattern-library-hint',this.ui.modelsHint));
      const controls=this.el('div','rb-pattern-library-controls');
      const search=this.el('input','rb-pattern-library-search');search.type='search';search.placeholder=this.ui.patternsSearch;search.value=this.patternQuery;search.dataset.patternSearch='1';search.setAttribute('aria-label',this.ui.patternsSearch);controls.append(search);
      const filters=this.el('div','rb-pattern-library-filters');
      [[this.ui.patternAll,'all'],[this.ui.patternModels||this.contentModels.title,'models'],[this.ui.patternQuick,'quick'],[this.ui.patternSaved,'saved']].forEach(([label,filter])=>{const button=this.button(label,'workspace-pattern-filter',{filter});button.setAttribute('aria-pressed',String(this.patternFilter===filter));filters.append(button);});
      controls.append(filters);panel.append(controls);
      if (this.patternLoadError) panel.append(this.el('p','rb-pattern-library-warning',this.strings.patternError||'Não foi possível carregar padrões salvos.'));
      const afterId=this.selectedId||null;
      const allowed=this.canInsertPatterns(afterId)&&this.insertTypes(afterId).some((item)=>item.type==='core/group');
      const modelsTitle=this.el('div','rb-pattern-library-section-title');modelsTitle.append(this.el('strong','',this.contentModels.title));panel.append(modelsTitle);
      const modelsGrid=this.el('div','rb-pattern-library-grid rb-content-models-grid');
      this.contentModels.templates.forEach((model)=>{
        const card=this.button(model.name,'insert-content-model',{modelId:model.id,icon:'layers',showLabel:true});
        card.classList.add('rb-pattern-library-card','rb-content-model-card');card.dataset.patternKind='models';card.dataset.patternSearch=(model.name+' '+model.description).toLocaleLowerCase();
        card.append(this.el('span','rb-pattern-library-description',model.description));card.disabled=!allowed;modelsGrid.append(card);
      });
      panel.append(modelsGrid);
      const quickTitle=this.el('div','rb-pattern-library-section-title');quickTitle.append(this.el('strong','',this.ui.patternQuick));panel.append(quickTitle,this.el('p','rb-pattern-library-hint',this.strings.patternHint||'Padrões reutilizáveis de blocos; não são documentos completos.'));
      const quick=this.el('div','rb-pattern-library-grid');
      const labels=this.strings.patternLabels||{};
      const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'').toLowerCase();
      const patternLanguage=language.startsWith('es')?'es':(language.startsWith('en')?'en':'pt');
      const descriptions=PATTERN_TOOL_DESCRIPTIONS[patternLanguage];
      const icons={two_columns:'columns',quote_cta:'quote',section_intro:'group',cover_hero:'cover'};
      ['two_columns','quote_cta','section_intro','cover_hero'].forEach((pattern)=>{
        const label=labels[pattern]||pattern;
        const card=this.button(label,'insert-pattern',{pattern,icon:icons[pattern]||'layers'});
        card.classList.add('rb-pattern-library-card');card.dataset.patternKind='quick';
        const description=descriptions[pattern]||'';card.dataset.patternSearch=(label+' '+description).toLocaleLowerCase();
        if(description)card.append(this.el('span','rb-pattern-library-description',description));
        card.disabled=!allowed;
        quick.append(card);
      });
      panel.append(quick);
      const savedTitle=this.el('div','rb-pattern-library-section-title');
      savedTitle.append(this.el('strong','',this.strings.savedPatternsCategory||'Padrões salvos'));
      if(this.patternConfig.canModify&&this.selectedId)savedTitle.append(this.button(this.strings.saveAsPattern||'Salvar seleção','save-selected-pattern',{nodeId:this.selectedId,icon:'plus'}));
      panel.append(savedTitle);
      if(!this.patternConfig.canRead)panel.append(this.el('p','rb-muted',this.strings.patternReadUnavailable||'A biblioteca de padrões não está habilitada para este usuário.'));
      else if(!this.savedPatterns.length)panel.append(this.el('p','rb-muted',this.strings.savedPatternsEmpty||'Ainda não há padrões salvos.'));
      else {
        const savedGrid=this.el('div','rb-pattern-library-grid rb-pattern-library-saved');
        const allowedTypes=new Set(this.insertTypes(afterId).map((item)=>item.type));
        this.savedPatterns.forEach((item)=>{
          const card=this.el('div','rb-pattern-library-card rb-pattern-library-saved-card');card.dataset.patternKind='saved';card.dataset.patternSearch=String(item.name||'').toLocaleLowerCase();
          const insert=this.button(item.name,'insert-saved-pattern',{patternId:String(item.pattern_id),icon:'layers'});
          insert.classList.add('rb-pattern-library-insert');insert.disabled=!allowedTypes.has(item.root_type);card.append(insert);
          if(this.patternConfig.canModify){const actions=this.el('div','rb-pattern-card-actions');actions.append(this.button(this.strings.renamePattern||'Renomear','rename-saved-pattern',{patternId:String(item.pattern_id)}),this.button(this.strings.deletePattern||'Excluir','delete-saved-pattern',{patternId:String(item.pattern_id)}));card.append(actions);}
          savedGrid.append(card);
        });
        panel.append(savedGrid);
      }
      const empty=this.el('p','rb-muted rb-pattern-library-empty',this.ui.noPatternMatches);empty.dataset.patternEmpty='1';empty.hidden=true;panel.append(empty);
      return panel;
    }

    renderInserter(forceOpen) {
      const menu = this.el('div', 'rb-inserter-menu'); menu.hidden = !(forceOpen || this.inserterOpen);
      menu.setAttribute('role', 'region'); menu.setAttribute('aria-label', this.strings.addBlock);
      const search = this.el('input', 'rb-inserter-search'); search.type = 'search'; search.placeholder = this.strings.searchBlocks;
      search.setAttribute('aria-label', this.strings.searchBlocks); search.setAttribute('aria-controls','rb-inserter-results-' + this.instance); search.dataset.inserterSearch = '1'; search.value = this.inserterQuery;
      menu.append(search);
      const types = this.insertTypes(this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId);
      const list = this.el('div', 'rb-inserter-results'); list.id = 'rb-inserter-results-' + this.instance;
      if (!types.length) menu.append(this.el('p', 'rb-inserter-empty', this.strings.insertEmpty));
      ['content','media','layout'].forEach((category) => {
        const group = this.el('section', 'rb-inserter-category');
        group.append(this.el('h4', 'rb-inserter-category-title', this.categoryLabel(category)));
        types.filter((item) => item.category === category).forEach((item) => {
          const option = this.button(this.label(item.type), 'insert', { type:item.type });
          option.classList.add('rb-inserter-option');
          option.dataset.searchText = this.label(item.type).toLocaleLowerCase(); group.append(option);
          if (item.type === 'core/columns') { const three = this.button(this.label(item.type) + ' (3)', 'insert', { type:item.type, count:'3' }); three.classList.add('rb-inserter-option'); three.dataset.searchText = (this.label(item.type) + ' 3').toLocaleLowerCase(); group.append(three); }
        });
        if (group.querySelector('.rb-inserter-option')) list.append(group);
      });
      if (this.canInsertPatterns(this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId)) {
        const patterns = this.el('section','rb-inserter-category rb-pattern-category'); patterns.append(this.el('h4','rb-inserter-category-title',this.strings.patternsCategory),this.el('p','rb-pattern-hint',this.strings.patternHint));
        ['two_columns','quote_cta','section_intro','cover_hero'].forEach((pattern) => {
          const label = (this.strings.patternLabels || {})[pattern] || pattern;
          const option = this.button(label,'insert-pattern',{pattern}); option.classList.add('rb-inserter-option'); option.dataset.searchText = label.toLocaleLowerCase(); patterns.append(option);
        });
        list.append(patterns);
      }
      const allowedTypes = new Set(types.map((item) => item.type));
      const saved = this.savedPatterns;
      if (saved.length) {
        const section = this.el('section','rb-inserter-category rb-saved-pattern-category');
        section.append(this.el('h4','rb-inserter-category-title',this.strings.savedPatternsCategory || 'Padrões salvos'));
        saved.forEach((item) => {
          const row = this.el('div','rb-saved-pattern-row');
          const insert = this.button(item.name,'insert-saved-pattern',{patternId:String(item.pattern_id)});
          insert.classList.add('rb-inserter-option'); insert.dataset.searchText = item.name.toLocaleLowerCase(); insert.disabled = !allowedTypes.has(item.root_type); row.append(insert);
          if (this.patternConfig.canModify) {
            row.append(this.button(this.strings.renamePattern || 'Renomear','rename-saved-pattern',{patternId:String(item.pattern_id)}));
            row.append(this.button(this.strings.deletePattern || 'Excluir','delete-saved-pattern',{patternId:String(item.pattern_id)}));
          }
          section.append(row);
        });
        list.append(section);
      }
      const noResults = this.el('p', 'rb-inserter-empty rb-inserter-no-results', this.strings.noBlocks); noResults.setAttribute('role','status'); noResults.setAttribute('aria-live','polite'); noResults.hidden = true;
      menu.append(list, noResults);
      return menu;
    }

    filterInserter() {
      const query = this.inserterQuery.trim().toLocaleLowerCase(); let visible = 0;
      this.root.querySelectorAll('.rb-inserter-option').forEach((option) => { const show = !query || option.dataset.searchText.includes(query); option.hidden = !show; if (show) visible += 1; });
      this.root.querySelectorAll('.rb-inserter-category').forEach((group) => { group.hidden = !group.querySelector('.rb-inserter-option:not([hidden])'); });
      const empty = this.root.querySelector('.rb-inserter-no-results'); if (empty) empty.hidden = visible > 0;
    }

    openInserter(afterId, fromSlash, opener) {
      if(!this.inserterHasPreviousPanel){this.inserterPreviousPanel=this.activePanel;this.inserterHasPreviousPanel=true;}
      if(afterId)this.selectedId=afterId;
      this.activePanel='elements';this.inserterOpen=true;this.insertAfterId=afterId||null;
      this.inserterReturnFocus = fromSlash
        ? { type:'editor', id:afterId }
        : (opener && opener.dataset.afterId ? { type:'block', id:opener.dataset.afterId } : (opener && opener.dataset.workspaceTool ? { type:'workspace', tool:opener.dataset.workspaceTool } : (opener && opener.dataset.panel ? { type:'panel', panel:opener.dataset.panel } : { type:'top' })));
      if (!fromSlash) this.slashTrigger = null;
      this.inserterQuery = ''; this.render();
      const search = this.root.querySelector('[data-inserter-search]'); if (search) search.focus();
    }

    closeInserter(returnFocus) {
      this.inserterOpen=false;this.slashTrigger=null;this.inserterQuery='';
      if(this.inserterHasPreviousPanel){this.activePanel=this.inserterPreviousPanel;this.inserterPreviousPanel=null;this.inserterHasPreviousPanel=false;}
      this.render();
      if (returnFocus) {
        let target = null;
        const restore = this.inserterReturnFocus;
        if (restore && restore.type === 'editor') target = this.findEditor(restore.id);
        else if (restore && restore.type === 'block') target = Array.from(this.root.querySelectorAll('[data-action="toggle-inserter"]')).find((button) => button.dataset.afterId === restore.id) || null;
        else if (restore && restore.type === 'panel') target = Array.from(this.root.querySelectorAll('[data-action="builder-panel"]')).find((button)=>button.dataset.panel===restore.panel)||null;
        else if (restore && restore.type === 'workspace') target = Array.from(this.root.querySelectorAll('[data-action="workspace-tool"]')).find((button)=>button.dataset.tool===restore.tool)||null;
        else target = this.root.querySelector('.rb-ribbon [data-action="toggle-inserter"]')||this.root.querySelector('.rb-toolbar [data-action="toggle-inserter"]');
        if (!target) target = this.root.querySelector('[data-action="toggle-inserter"]');
        if (target) target.focus();
      }
      this.inserterReturnFocus = null;
    }

    insertionLocation(blocks, afterId) {
      const found = afterId ? this.findPath(blocks, afterId) : null;
      const container = found && ['core/group','core/column','core/list'].includes(found.node.type);
      const target = !found ? blocks : (container ? found.node.children : found.nodes);
      return { found, target, index: !found ? target.length : (container ? target.length : found.index + 1) };
    }

    prepareSlashTarget(next, afterId) {
      const found = afterId ? this.findPath(next.blocks, afterId) : null;
      if (this.slashTrigger && found && TEXT_TYPES.has(found.node.type)) {
        const at = this.slashTrigger.offset; found.node.content = this.removeInlineRange(found.node.content, at - 1, at);
      }
      return found;
    }

    finishInsert(next,node) {
      this.workspaceView='editor';
      this.inserterOpen=false;this.inserterReturnFocus=null;this.slashTrigger=null;this.insertAfterId=node.id;this.inserterQuery='';this.statusMessage=this.strings.statusInserted;
      if(this.inserterHasPreviousPanel){this.activePanel=this.inserterPreviousPanel;this.inserterPreviousPanel=null;this.inserterHasPreviousPanel=false;}
      this.commit(next,node.id);this.focusNode(node.id);
    }

    insertMediaReference(kind, path) {
      const type = kind === 'video' ? 'core/video_file' : 'core/audio';
      const valid = kind === 'video' ? validLocalVideo(path, this.allowPageMedia) : validLocalAudio(path, this.allowPageMedia);
      if (!valid) return;
      const previousId = this.selectedId; this.insert(type);
      if (this.selectedId && this.selectedId !== previousId) this.updateNode(this.selectedId, (node) => { if (node.type === type) node.attributes.src = path; });
    }

    insert(type, presetCount) {
      const afterId = this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId || this.selectedId;
      if (!this.insertTypes(afterId).some((item) => item.type === type)) return;
      const next = CLONE(this.state); this.prepareSlashTarget(next, afterId);
      const location = this.insertionLocation(next.blocks, afterId);
      const node = this.createDefault(type, presetCount); location.target.splice(location.index, 0, node);
      this.finishInsert(next,node);
    }

    async patternRequest(url, values, method) {
      if (!url) throw new Error('pattern_endpoint_missing');
      const options = { method: method || (values ? 'POST' : 'GET'), credentials: 'same-origin', headers: { 'Accept':'application/json' } };
      if (values) { options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(values); }
      const response = await fetch(url, options); const payload = await response.json();
      if (!payload || payload.success !== true) throw new Error(payload && payload.error ? payload.error : 'pattern_request_failed');
      return payload;
    }

    async loadSavedPatterns() {
      if (!this.patternConfig.listUrl || !this.patternConfig.canRead) return;
      try { const result = await this.patternRequest(this.patternConfig.listUrl); this.savedPatterns = Array.isArray(result.patterns) ? result.patterns : []; }
      catch (_) { this.patternLoadError = true; }
      if (this.root && this.root.isConnected) this.render();
    }

    containsCustomJs(node) {
      if (node && node.attributes && Object.prototype.hasOwnProperty.call(node.attributes,'custom_js')) return true;
      return !!(node && Array.isArray(node.children) && node.children.some((child) => this.containsCustomJs(child)));
    }

    async saveSelectedPattern(nodeId) {
      const targetId = nodeId || this.selectedId;
      const found = targetId && this.findPath(this.state.blocks, targetId);
      if (!found || !this.patternConfig.saveUrl || !this.patternConfig.canModify) return;
      if (this.containsCustomJs(found.node)) { this.announceStatus(this.strings.patternCustomJsRejected || 'Padrões não podem conter JavaScript.'); return; }
      const name = global.prompt(this.strings.patternNamePrompt || 'Nome do padrão');
      if (name === null || !name.trim()) return;
      const document = { format_version:this.state.format_version, blocks:[CLONE(found.node)] };
      const validation = validateDocument(document, this.allowPageMedia);
      if (!validation.valid || utf8Length(JSON.stringify(document)) > 4194304) { this.announceStatus(this.strings.patternInvalid || this.strings.initialInvalid); return; }
      try {
        await this.patternRequest(this.patternConfig.saveUrl, {csrf_token:this.patternConfig.csrfToken || '', name:name.trim(), source_language:this.patternConfig.sourceLanguage || '', document_json:JSON.stringify(document)});
        this.announceStatus(this.strings.patternSaved || 'Padrão salvo.'); await this.loadSavedPatterns();
      } catch (error) { this.announceStatus(this.strings.patternError || 'Não foi possível salvar o padrão.'); }
    }

    async insertSavedPattern(patternId) {
      const afterId = this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId || this.selectedId;
      const allowed = this.insertTypes(afterId).map((item) => item.type);
      const url = this.patternConfig.getUrl + (this.patternConfig.getUrl && this.patternConfig.getUrl.includes('?') ? '&' : '?') + 'pattern_id=' + encodeURIComponent(patternId);
      try {
        const result = await this.patternRequest(url);
        const document = JSON.parse(result.pattern.document_json);
        const validation = validateDocument(document, this.allowPageMedia);
        if (!validation.valid || !document.blocks || document.blocks.length !== 1 || !allowed.includes(document.blocks[0].type) || this.containsCustomJs(document.blocks[0])) throw new Error('pattern_incompatible');
        const next = CLONE(this.state); this.prepareSlashTarget(next, afterId); const location = this.insertionLocation(next.blocks, afterId);
        const node = cloneBlockWithNewIds(document.blocks[0], () => this.newId()); location.target.splice(location.index,0,node); this.finishInsert(next,node);
      } catch (_) { this.announceStatus(this.strings.patternIncompatible || 'Este padrão não é compatível com este ponto do editor.'); }
    }

    async renameSavedPattern(patternId) {
      const item = this.savedPatterns.find((pattern) => String(pattern.pattern_id) === String(patternId));
      if (!item || !this.patternConfig.renameUrl) return;
      const name = global.prompt(this.strings.patternRenamePrompt || 'Novo nome do padrão', item.name);
      if (name === null || !name.trim()) return;
      try { await this.patternRequest(this.patternConfig.renameUrl,{csrf_token:this.patternConfig.csrfToken || '',pattern_id:String(patternId),name:name.trim()}); this.announceStatus(this.strings.patternRenamed || 'Padrão renomeado.'); await this.loadSavedPatterns(); }
      catch (_) { this.announceStatus(this.strings.patternError || 'Não foi possível atualizar o padrão.'); }
    }

    async deleteSavedPattern(patternId) {
      const item = this.savedPatterns.find((pattern) => String(pattern.pattern_id) === String(patternId));
      if (!item || !this.patternConfig.deleteUrl) return;
      if (!global.confirm((this.strings.patternDeleteConfirm || 'Excluir o padrão') + ' “' + item.name + '”?')) return;
      try { await this.patternRequest(this.patternConfig.deleteUrl,{csrf_token:this.patternConfig.csrfToken || '',pattern_id:String(patternId)}); this.announceStatus(this.strings.patternDeleted || 'Padrão excluído.'); await this.loadSavedPatterns(); }
      catch (_) { this.announceStatus(this.strings.patternError || 'Não foi possível atualizar o padrão.'); }
    }

    insertPattern(pattern) {
      const afterId = this.slashTrigger ? this.slashTrigger.nodeId : this.insertAfterId || this.selectedId;
      if (!this.canInsertPatterns(afterId)) return;
      const next = CLONE(this.state); this.prepareSlashTarget(next, afterId);
      const location = this.insertionLocation(next.blocks, afterId); const node = this.createPattern(pattern);
      if (!node) return;
      location.target.splice(location.index,0,node); this.finishInsert(next,node);
    }

    updateTableSelectionControls(tableId) {
      const found=this.findPath(this.state.blocks,tableId),selected=this.tableSelection.get(tableId)||new Set();
      const table=found&&found.node.type==='core/table'?found.node:null;
      const canMerge=!!(table&&tableMergeSelection(table,selected));const canUnmerge=!!(table&&tableGridInfo(table)&&tableGridInfo(table).origins.some((origin)=>selected.has(origin.cell.id)&&(origin.rowspan>1||origin.colspan>1)));
      this.root.querySelectorAll('[data-table-selection-action]').forEach((button)=>{
        if(button.dataset.nodeId!==tableId)return;
        const kind=button.dataset.tableSelectionAction;
        button.disabled=kind==='merge'?!canMerge:(kind==='unmerge'?!canUnmerge:selected.size===0);
        button.setAttribute('aria-disabled',String(button.disabled));
      });
    }

    updateTable(action, id, headerValue) {
      if (this.locked) return;
      const next = CLONE(this.state), found = this.findPath(next.blocks, id);
      if (!found || found.node.type !== 'core/table') return;
      const table = found.node, rows = table.children;
      const info=tableGridInfo(table);if(!info)return;
      const selected=this.tableSelection.get(table.id)||new Set();
      if (action === 'table-toggle-header') table.attributes.header = !table.attributes.header;
      else if(action==='table-mark-header'||action==='table-mark-normal'){
        if(!selected.size)return;
        const value=action==='table-mark-header';
        info.origins.forEach(({cell})=>{if(!selected.has(cell.id))return;cell.attributes.header=value;});
      } else if(action==='table-unmerge-cells') {
        if(!splitTableCells(table,selected,(header)=>this.node('core/table-cell',{header},'')))return;this.tableSelection.delete(table.id);
      } else if(action==='table-merge-cells'){
        const merge=tableMergeSelection(table,selected);if(!merge)return;
        const keep=merge.anchor.cell;
        const ordered=merge.cells.map((origin)=>origin.cell);
        const merged=[];
        ordered.forEach((cell,index)=>{if(index)merged.push({text:'\n',marks:[],href:null});merged.push(...cell.content);});
        keep.content=canonicalRuns(merged);
        if(merge.rowspan>1)keep.attributes.rowspan=merge.rowspan;else delete keep.attributes.rowspan;
        if(merge.colspan>1)keep.attributes.colspan=merge.colspan;else delete keep.attributes.colspan;
        const removeIds=new Set(ordered.slice(1).map((cell)=>cell.id));
        rows.forEach((row)=>{row.children=row.children.filter((cell)=>!removeIds.has(cell.id));});
        this.tableSelection.delete(table.id);
      } else if (action === 'table-add-row' && rows.length < 20) {
        const cells = []; for (let c = 0; c < info.width; c += 1) cells.push(this.node('core/table-cell', {}, ''));
        rows.push(this.node('core/table-row', {}, '', cells));
        this.tableSelection.delete(table.id);
      } else if (action === 'table-remove-row' && rows.length > 1) {
        const last=rows.length-1;
        info.origins.forEach(({cell,row,rowspan})=>{if(row<last&&row+rowspan===rows.length){const nextSpan=rowspan-1;if(nextSpan>1)cell.attributes.rowspan=nextSpan;else delete cell.attributes.rowspan;}});
        rows.pop();this.tableSelection.delete(table.id);
      } else if (action === 'table-add-column' && info.width < 12) {
        rows.forEach((row) => row.children.push(this.node('core/table-cell', {}, '')));
        this.tableSelection.delete(table.id);
      } else if (action === 'table-remove-column' && info.width > 1) {
        const last=info.width-1;
        info.origins.forEach(({cell,row,col,colspan})=>{if(col<=last&&col+colspan>last){if(colspan>1){const nextSpan=colspan-1;if(nextSpan>1)cell.attributes.colspan=nextSpan;else delete cell.attributes.colspan;}else rows[row].children=rows[row].children.filter((item)=>item.id!==cell.id);}});
        this.tableSelection.delete(table.id);
      } else return;
      const validation=validateDocument(next,this.allowPageMedia);if(!validation.valid)return;
      this.commit(next, table.id);
    }

    updateColumns(action, id) {
      if (this.locked) return;
      const next=CLONE(this.state),found=this.findPath(next.blocks,id);
      if(!found||found.node.type!=='core/columns')return;
      const columns=found.node.children;
      if(action==='columns-add'&&columns.length<3){const number=columns.length+1;columns.push(this.node('core/column',{},'', [this.node('core/paragraph',{},(this.strings.columnDefault||'Column')+' '+number)]));}
      else if(action==='columns-remove'&&columns.length>2){const removed=columns[columns.length-1];columns[columns.length-2].children.push(...removed.children);columns.pop();}
      else return;
      found.node.attributes.count=columns.length;
      this.commit(next,found.node.id);
    }

    findPath(nodes, id, parent) {
      for (let i = 0; i < nodes.length; i += 1) {
        if (nodes[i].id === id) return { nodes, index: i, node: nodes[i], parent: parent || null };
        const found = this.findPath(nodes[i].children || [], id, nodes[i]); if (found) return found;
      }
      return null;
    }

    updateNode(id, mutator, rerender) {
      if (this.locked) return;
      const next = CLONE(this.state), found = this.findPath(next.blocks, id); if (!found) return;
      mutator(found.node); this.commit(next, id, rerender !== false);
    }

    pushHistory(stack, side, snapshot) {
      const weightKey = side + 'Weight';
      stack.push(snapshot);
      this[weightKey] += snapshot.length;
      while (stack.length > 50 || (stack.length > 1 && this[weightKey] > 8388608)) {
        this[weightKey] -= stack.shift().length;
      }
    }

    popHistory(stack, side) {
      const snapshot = stack.pop();
      if (snapshot !== undefined) this[side + 'Weight'] -= snapshot.length;
      return snapshot;
    }

    clearRedoHistory() {
      this.redoStack = [];
      this.redoWeight = 0;
    }

    updateCodeNode(id, editor) {
      if (this.locked) return;
      const found = this.findPath(this.state.blocks, id);
      if (!found || found.node.type !== 'core/code') return;
      if (utf8Length(editor.value) > 262144) {
        editor.setCustomValidity(this.strings.preformatted.tooLarge);
        editor.setAttribute('aria-invalid','true');
        return;
      }
      editor.setCustomValidity(''); editor.setAttribute('aria-invalid','false');
      this.recordTextEditSnapshot();
      found.node.content = editor.value;
      this.root.querySelectorAll('[data-edit-code]').forEach((peer)=>{if(peer!==editor&&peer.dataset.editCode===id&&peer.value!==editor.value){peer.value=editor.value;peer.setCustomValidity('');peer.setAttribute('aria-invalid','false');}});
      const result = validateDocument(this.state, this.allowPageMedia);
      this.root.dataset.valid = result.valid ? 'true' : 'false';
      this.onChange(result.valid ? this.getDocument() : null, result);
      this.updateHistoryButtons();
    }

    updateTextNode(id, editor) {
      if (this.locked) return;
      const found = this.findPath(this.state.blocks, id);
      if (!found || !BLOCK_TEXT_TYPES.has(found.node.type)) return;
      const runs = normalizeDomInline(editor, found.node.type);
      this.recordTextEditSnapshot();
      found.node.content = runs;
      this.root.querySelectorAll('[data-edit-content]').forEach((peer)=>{if(peer!==editor&&peer.dataset.editContent===id)buildInlineDom(peer,runs);});
      let valid = validInlineRuns(runs, found.node.type);
      let errors = valid ? [] : ['rich_content_invalid'];
      if (valid && found.node.type === 'core/table-cell') {
        let ancestor = found.parent;
        while (ancestor && ancestor.type !== 'core/table') {
          const ancestorPath = this.findPath(this.state.blocks, ancestor.id);
          ancestor = ancestorPath ? ancestorPath.parent : null;
        }
        if (ancestor && ancestor.type === 'core/table') {
          let totalBytes = 0;
          ancestor.children.forEach((row) => row.children.forEach((cell) => cell.content.forEach((run) => { totalBytes += utf8Length(run.text); })));
          if (totalBytes > 262144) { valid = false; errors = ['table_text_too_large']; }
        }
      }
      const result = { valid, errors };
      this.root.dataset.valid = valid ? 'true' : 'false';
      this.onChange(valid ? this.state : null, result);
      this.updateHistoryButtons();
    }

    recordTextEditSnapshot() {
      if (!this.textUndoPending) {
        this.pushHistory(this.undoStack, 'undo', this.snapshot());
        this.clearRedoHistory();
        this.textUndoPending = true;
      }
      if (this.textUndoTimer) global.clearTimeout(this.textUndoTimer);
      this.textUndoTimer = global.setTimeout(() => {
        this.textUndoPending = false;
        this.textUndoTimer = null;
      }, 700);
    }

    clearTextEditSnapshot() {
      if (this.textUndoTimer) global.clearTimeout(this.textUndoTimer);
      this.textUndoTimer = null;
      this.textUndoPending = false;
    }

    deleteNode(id) {
      const next = CLONE(this.state), found = this.findPath(next.blocks, id); if (!found) return;
      if (found.parent && found.parent.type === 'core/columns' && found.node.type === 'core/column') return;
      if (found.parent && found.parent.type === 'core/gallery' && found.parent.children.length <= 1) return;
      if (found.parent && found.parent.type === 'core/cover' && found.parent.children.length <= 1) return;
      found.nodes.splice(found.index, 1);
      let focusId = found.nodes[Math.min(found.index, found.nodes.length - 1)] ? found.nodes[Math.min(found.index, found.nodes.length - 1)].id : null;
      if (found.parent && !found.nodes.length) {
        if (found.parent.type === 'core/list') { const fallback = this.node('core/list-item', {}, 'Novo item'); found.nodes.push(fallback); focusId = fallback.id; }
        else if (['core/group','core/column'].includes(found.parent.type)) { const fallback = this.node('core/paragraph', {}, 'Escreva seu conteúdo…'); found.nodes.push(fallback); focusId = fallback.id; }
        else if (found.parent.type === 'core/columns') focusId = found.parent.id;
      }
      if (!focusId && next.blocks.length) focusId = next.blocks[0].id;
      this.selectedId = focusId; this.statusMessage = this.strings.statusRemoved; this.commit(next, focusId);
      if (focusId) this.focusNode(focusId); else { const add = this.root.querySelector('[data-action="toggle-inserter"]'); if (add) add.focus(); }
    }

    moveNode(id, delta) {
      const next = CLONE(this.state), found = this.findPath(next.blocks, id); if (!found) return;
      const target = found.index + delta; if (target < 0 || target >= found.nodes.length) return;
      [found.nodes[found.index], found.nodes[target]] = [found.nodes[target], found.nodes[found.index]];
      this.statusMessage = this.strings.statusMoved; this.commit(next, id); this.focusNode(id);
    }

    announceStatus(message) {
      this.statusMessage = message;
      const live = this.root.querySelector('[data-editor-status]');
      if (live) live.textContent = message;
    }

    copyBlock(id) {
      if (this.locked) return false;
      const found = id ? this.findPath(this.state.blocks, id) : null;
      if (!found) return false;
      this.blockClipboard = CLONE(found.node);
      this.selectNode(id);
      this.updateClipboardButtons();
      this.announceStatus(this.strings.statusCopied);
      return true;
    }

    pasteLocation(blocks, afterId) {
      const found = afterId ? this.findPath(blocks, afterId) : null;
      return found ? { target: found.nodes, index: found.index + 1 } : { target: blocks, index: blocks.length };
    }

    canPasteBlock() {
      if (this.locked || !this.blockClipboard || !this.blockClipboard.type) return false;
      const afterId = this.selectedId || this.insertAfterId || null;
      const used = new Set();
      const collect = (nodes) => (nodes || []).forEach((node) => { if (node && typeof node.id === 'string') used.add(node.id); if (node && Array.isArray(node.children)) collect(node.children); });
      collect(this.state.blocks);
      let sequence = 0;
      const preview = cloneBlockWithNewIds(this.blockClipboard, () => {
        let id; do { id = 'bdre-paste-check-' + sequence++; } while (used.has(id)); used.add(id); return id;
      });
      if (!preview) return false;
      const next = CLONE(this.state);
      const location = this.pasteLocation(next.blocks, afterId);
      location.target.splice(location.index, 0, preview);
      return validateDocument(next, this.allowPageMedia).valid && utf8Length(JSON.stringify(next)) <= 4194304;
    }

    pasteBlock() {
      if (!this.canPasteBlock()) { this.announceStatus(this.strings.pasteUnavailable); return false; }
      const afterId = this.selectedId || this.insertAfterId || null;
      const next = CLONE(this.state);
      const location = this.pasteLocation(next.blocks, afterId);
      const copy = cloneBlockWithNewIds(this.blockClipboard, () => this.newId());
      if (!copy) { this.announceStatus(this.strings.pasteUnavailable); return false; }
      location.target.splice(location.index, 0, copy);
      if (!validateDocument(next, this.allowPageMedia).valid || utf8Length(JSON.stringify(next)) > 4194304) { this.announceStatus(this.strings.pasteUnavailable); return false; }
      this.statusMessage = this.strings.statusPasted;
      this.commit(next, copy.id);
      this.focusNode(copy.id);
      return true;
    }

    updateClipboardButtons() {
      const enabled = this.canPasteBlock();
      this.root.querySelectorAll('[data-action="paste-block"]').forEach((button) => { button.disabled = !enabled; });
    }

    canIndent(id) { return !!moveBlockAcrossHierarchy(this.state, id, 'indent', this.allowPageMedia); }
    canOutdent(id) { return !!moveBlockAcrossHierarchy(this.state, id, 'outdent', this.allowPageMedia); }

    indentNode(id) {
      const result = moveBlockAcrossHierarchy(this.state, id, 'indent', this.allowPageMedia);
      if (!result) { this.announceStatus(this.strings.hierarchyMoveUnavailable); return false; }
      this.statusMessage = this.strings.statusIndented; this.commit(result.document, result.focusId); this.focusNode(result.focusId); return true;
    }

    outdentNode(id) {
      const result = moveBlockAcrossHierarchy(this.state, id, 'outdent', this.allowPageMedia);
      if (!result) { this.announceStatus(this.strings.hierarchyMoveUnavailable); return false; }
      this.statusMessage = this.strings.statusOutdented; this.commit(result.document, result.focusId); this.focusNode(result.focusId); return true;
    }

    duplicateNode(id) {
      const next = CLONE(this.state); const found = this.findPath(next.blocks, id); if (!found) return;
      if (found.parent && found.parent.type === 'core/columns' && found.node.type === 'core/column') return;
      if (found.parent && found.parent.type === 'core/gallery' && found.parent.children.length >= 20) return;
      if (found.parent && found.parent.type === 'core/cover' && found.parent.children.length >= 12) return;
      const copy = cloneBlockWithNewIds(found.node, () => this.newId());
      if (!copy) return;
      found.nodes.splice(found.index + 1, 0, copy); this.statusMessage = this.strings.statusDuplicated; this.commit(next, copy.id); this.focusNode(copy.id);
    }

    canTransformNode(node, type) {
      if (!node || !TRANSFORMABLE_TEXT.has(node.type) || !TRANSFORMABLE_TEXT.has(type) || node.type === type) return false;
      if (node.type === 'core/button' && !node.content.length) return false;
      if (type === 'core/button') {
        const hrefs = new Set(node.content.map((run) => run.href || null));
        return hrefs.size <= 1;
      }
      return true;
    }

    transformNode(id, type) {
      const next = CLONE(this.state); const found = this.findPath(next.blocks, id); if (!found || !this.canTransformNode(found.node, type)) return;
      const node = found.node; const previous = node.type; const buttonUrl = previous === 'core/button' ? node.attributes.url : null;
      const inlineLinks = new Set(node.content.map((run) => run.href || null));
      const targetButtonUrl = type === 'core/button' ? (Array.from(inlineLinks)[0] || buttonUrl || '/') : null;
      node.type = type;
      node.attributes = type === 'core/paragraph' ? (previous === type ? node.attributes : { align:'left' })
        : (type === 'core/heading' ? { level: previous === 'core/heading' ? node.attributes.level : 2 }
          : (type === 'core/button' ? { url: targetButtonUrl } : {}));
      if (previous === 'core/button' && type !== 'core/button') node.content = node.content.map((run) => Object.assign({}, run, { href:buttonUrl }));
      if (type === 'core/button') node.content = node.content.map((run) => Object.assign({}, run, { href:null }));
      this.statusMessage = this.strings.statusTransformed; this.commit(next, id); this.focusNode(id);
    }

    reorderSibling(fromId, toId) {
      if (fromId === toId) return;
      const next = CLONE(this.state); const from = this.findPath(next.blocks, fromId); const to = this.findPath(next.blocks, toId);
      if (!from || !to || from.nodes !== to.nodes) return;
      const [node] = from.nodes.splice(from.index, 1); from.nodes.splice(to.index, 0, node); this.statusMessage = this.strings.statusMoved; this.commit(next, fromId); this.focusNode(fromId);
    }

    commit(next, focusId, rerender) {
      this.clearTextEditSnapshot();
      this.pushHistory(this.undoStack, 'undo', this.snapshot());
      this.clearRedoHistory(); this.state = next; this.selectedId = focusId || this.selectedId;
      const result = validateDocument(this.state, this.allowPageMedia);
      this.root.dataset.valid = result.valid ? 'true' : 'false';
      this.onChange(this.getDocument(), result);
      if (rerender !== false) this.render(); else this.updateHistoryButtons();
    }

    undo() { this.clearTextEditSnapshot(); if (!this.undoStack.length) return; this.pushHistory(this.redoStack, 'redo', this.snapshot()); this.state = JSON.parse(this.popHistory(this.undoStack, 'undo')); if (this.selectedId && !this.findPath(this.state.blocks, this.selectedId)) this.selectedId = this.state.blocks[0] ? this.state.blocks[0].id : null; this.statusMessage = this.strings.statusUndo; this.render(); this.onChange(this.getDocument(), validateDocument(this.state, this.allowPageMedia)); }
    redo() { this.clearTextEditSnapshot(); if (!this.redoStack.length) return; this.pushHistory(this.undoStack, 'undo', this.snapshot()); this.state = JSON.parse(this.popHistory(this.redoStack, 'redo')); if (this.selectedId && !this.findPath(this.state.blocks, this.selectedId)) this.selectedId = this.state.blocks[0] ? this.state.blocks[0].id : null; this.statusMessage = this.strings.statusRedo; this.render(); this.onChange(this.getDocument(), validateDocument(this.state, this.allowPageMedia)); }
    adjustSeparatorWidth(nodeId,direction) {
      const id=nodeId||this.selectedId;if(!id)return;
      this.updateNode(id,(node)=>{if(node.type!=='core/separator')return;let index=SEPARATOR_WIDTHS.indexOf(Number(node.attributes.width||100));if(index<0)index=SEPARATOR_WIDTHS.length-1;index=Math.max(0,Math.min(SEPARATOR_WIDTHS.length-1,index+direction));node.attributes.width=SEPARATOR_WIDTHS[index];});
    }

    adjustSpacerHeight(nodeId,direction) {
      const id=nodeId||this.selectedId;if(!id)return;
      this.updateNode(id,(node)=>{if(node.type!=='core/spacer')return;let index=SPACER_HEIGHTS.indexOf(Number(node.attributes.height));if(index<0)index=1;index=Math.max(0,Math.min(SPACER_HEIGHTS.length-1,index+direction));node.attributes.height=SPACER_HEIGHTS[index];});
    }

    setSelectedAttribute(key, value) { if (this.locked) return false; const found = this.selectedId ? this.findPath(this.state.blocks, this.selectedId) : null; if (!found || !['core/image','core/cover','core/file','core/audio','core/video_file','core/video_embed'].includes(found.node.type) || key !== 'src') return false; this.updateNode(found.node.id, (node) => { node.attributes.src = value; if(node.type==='core/video_embed')delete node.attributes.url; }); return true; }
    replaceDocument(documentValue) {
      if (this.locked || !documentValue || typeof documentValue !== 'object') return false;
      const next = CLONE(documentValue);
      const check = validateDocument(next, this.allowPageMedia);
      if (!check.valid) return false;
      this.clearTextEditSnapshot();
      this.pushHistory(this.undoStack, 'undo', this.snapshot());
      this.clearRedoHistory();
      this.state = next;
      this.selectedId = this.state.blocks[0] ? this.state.blocks[0].id : null;
      this.statusMessage = this.strings.historyRestored || 'Revision restored; save to keep it.';
      this.render();
      this.onChange(this.getDocument(), check);
      return true;
    }
    getDocument() { return this.locked ? null : CLONE(this.state); }
    serialize() {
      if (this.locked) throw new Error('document_requires_review');
      const check = validateDocument(this.state, this.allowPageMedia);
      if (!check.valid) throw new Error(check.errors.join(','));
      const json = JSON.stringify(this.state);
      if (utf8Length(json) > 4194304) throw new Error('document_too_large');
      return json;
    }

    el(tag, className, text) { const element = document.createElement(tag); if (className) element.className = className; if (text !== undefined) element.textContent = text; return element; }
    icon(name, className) {
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('width', '18'); svg.setAttribute('height', '18');
      svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', 'currentColor'); svg.setAttribute('stroke-width', '1.8');
      svg.setAttribute('stroke-linecap', 'round'); svg.setAttribute('stroke-linejoin', 'round'); svg.setAttribute('aria-hidden', 'true'); svg.setAttribute('focusable', 'false');
      if (className) svg.setAttribute('class', className);
      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path'); path.setAttribute('d', ICON_PATHS[name] || ICON_PATHS.layers); svg.appendChild(path); return svg;
    }
    button(label, action, extra) {
      const b = this.el('button', 'rb-button'); b.type = 'button'; b.dataset.action = action;
      const type = extra && extra.type; let iconName = (extra && extra.icon) || (type && TYPE_ICONS[type]) || ACTION_ICONS[action];
      if (action === 'align-text' && extra && extra.alignment) iconName = ({left:'alignLeft',center:'alignCenter',right:'alignRight'})[extra.alignment] || 'alignLeft';
      if (action === 'preview' && extra && extra.size) iconName = extra.size === 'mobile' ? 'mobile' : (extra.size === 'tablet' ? 'tablet' : 'desktop');
      if (action === 'format-mark' && extra && extra.mark) iconName = ({bold:'bold',italic:'italic',underline:'underline',strike:'strike',code:'code',link:'link'})[extra.mark] || 'settings';
      if (iconName) b.append(this.icon(iconName, 'rb-icon'));
      const text = this.el('span', 'rb-button-label', label);
      const iconOnly=!(extra&&extra.showLabel===true)&&(ICON_ONLY_ACTIONS.has(action)||(extra&&extra.iconOnly===true)||['↑','↓','+','↔'].includes(String(label).trim()));
      let tooltipDescription='';
      if (iconOnly) {
        text.classList.add('rb-sr-only'); b.classList.add('rb-button-icon-only','rb-has-tooltip');
        const name=String(label);tooltipDescription=(extra&&extra.tooltipDescription)||getToolDescription(action,type,global.document&&global.document.documentElement&&global.document.documentElement.lang);
        b.setAttribute('aria-label', String(label));b.dataset.tooltipTitle=name;b.dataset.tooltipDescription=tooltipDescription;b.setAttribute('title',name+'\n'+tooltipDescription);
      } else if(extra&&extra.showLabel===true) {
        b.title=String(label)+' — '+((extra.tooltipDescription)||getToolDescription(action,type,global.document&&global.document.documentElement&&global.document.documentElement.lang));
      }
      b.append(text);
      if(iconOnly){const description=this.el('span','rb-sr-only',tooltipDescription);description.id='rb-tool-description-'+(++toolDescriptionSequence);b.setAttribute('aria-describedby',description.id);b.append(description);}
      let shortcut = '';
      if (action === 'undo') shortcut = 'Control+Z Meta+Z';
      else if (action === 'redo') shortcut = 'Control+Shift+Z Meta+Shift+Z Control+Y';
      else if (action === 'toggle-find-replace') shortcut = 'Control+F Meta+F';
      else if (action === 'move-up') shortcut = 'Alt+ArrowUp';
      else if (action === 'move-down') shortcut = 'Alt+ArrowDown';
      else if (action === 'format-mark' && extra && extra.mark === 'bold') shortcut = 'Control+B Meta+B';
      else if (action === 'format-mark' && extra && extra.mark === 'italic') shortcut = 'Control+I Meta+I';
      else if (action === 'format-mark' && extra && extra.mark === 'underline') shortcut = 'Control+U Meta+U';
      if (shortcut) b.setAttribute('aria-keyshortcuts',shortcut);
      if (extra) Object.keys(extra).forEach((key) => { b.dataset[key] = extra[key]; }); return b;
    }

    getDraftHistory() {
      const key = this.patternConfig && this.patternConfig.draftKey;
      if (!key) return [];
      try {
        const stored = JSON.parse(global.sessionStorage.getItem(key) || 'null');
        return stored && Array.isArray(stored.history) ? stored.history.filter((item) => item && item.savedAt && Date.now() - item.savedAt <= 86400000).slice().reverse() : [];
      } catch (_) { return []; }
    }

    restoreDraftRevision(index) {
      const revisions = this.getDraftHistory();
      const revision = Number.isInteger(index) ? revisions[index] : null;
      if (!revision || !revision.document) return;
      if (!global.confirm(this.strings.historyRestoreConfirm || 'Restore this temporary revision into the editor?')) return;
      const restored = upgradeDocument(revision.document);
      const validation = validateDocument(restored, this.allowPageMedia);
      if (!validation.valid || utf8Length(JSON.stringify(restored)) > 4194304) {
        this.announceStatus(this.strings.patternInvalid || this.strings.initialInvalid); return;
      }
      this.state = CLONE(restored);
      this.selectedId = this.state.blocks[0] ? this.state.blocks[0].id : null;
      this.insertAfterId = null; this.slashTrigger = null; this.historyOpen = false;
      this.undoStack = []; this.redoStack = [];
      this.statusMessage = this.strings.historyRestored || 'Temporary revision restored as an unsaved draft.';
      this.render(); this.onChange(this.getDocument(), validateDocument(this.state, this.allowPageMedia));
    }

    renderDraftHistory() {
      const panel = this.el('section','rb-draft-history');
      panel.setAttribute('role','region'); panel.setAttribute('aria-label',this.strings.historyTitle || 'Draft history');
      const heading = this.el('h3','',this.strings.historyTitle || 'Draft history');
      const close = this.button(this.strings.closePanel || 'Close','toggle-revision-history',{icon:'collapse'});
      panel.append(heading,close);
      const revisions = this.getDraftHistory();
      if (!revisions.length) panel.append(this.el('p','rb-note',this.strings.historyEmpty || 'No previous temporary drafts in this tab.'));
      revisions.forEach((item,index) => {
        const row = this.el('div','rb-draft-history-row');
        const when = new Date(Number(item.savedAt) || 0);
        const count = item.document && Array.isArray(item.document.blocks) ? item.document.blocks.length : 0;
        row.append(this.el('span','',when.toLocaleString() + ' · ' + count + ' blocks'));
        row.append(this.button(this.strings.historyRestore || 'Restore','restore-draft-revision',{revisionIndex:String(index)}));
        panel.append(row);
      });
      return panel;
    }

    exportDocumentFile() {
      try {
        const json = this.serialize();
        const blob = new Blob([json], { type:'application/json;charset=utf-8' });
        const url = global.URL.createObjectURL(blob);
        const link = global.document.createElement('a');
        link.href = url; link.download = 'bdre-document-' + new Date().toISOString().slice(0,10) + '.bdre.json';
        link.hidden = true; global.document.body.appendChild(link); link.click(); link.remove();
        global.setTimeout(() => global.URL.revokeObjectURL(url), 1000);
        this.announceStatus(this.strings.exportSuccess || 'Documento BDRE exportado.');
      } catch (_) { this.announceStatus(this.strings.exportError || 'Não foi possível exportar o documento BDRE.'); }
    }

    exportMarkdownFile() {
      const result=convertDocumentToMarkdown(this.state,this.allowPageMedia);
      if(!result.valid){this.announceStatus(this.markdownExport.invalid);return false;}
      if(utf8Length(result.markdown)>8388608){this.announceStatus(this.markdownExport.tooLarge);return false;}
      try{
        const blob=new Blob([result.markdown],{type:'text/markdown;charset=utf-8'});
        const url=global.URL.createObjectURL(blob),link=global.document.createElement('a');
        link.href=url;link.download='bdre-document-'+new Date().toISOString().slice(0,10)+'.md';link.hidden=true;global.document.body.appendChild(link);link.click();link.remove();
        global.setTimeout(()=>global.URL.revokeObjectURL(url),1000);
        this.announceStatus(result.warnings.length?this.markdownExport.downloadWithWarnings.replace('%1',String(result.warnings.length)):this.markdownExport.downloadSuccess);return true;
      }catch(_){this.announceStatus(this.markdownExport.downloadError);return false;}
    }


    async readMarkdownImport(file) {
      const copy=this.markdownImport;
      const name=String(file&&file.name||'');
      const extension=name.split('.').pop().toLowerCase();
      let source='',result;
      if(!/\.(md|markdown)$/i.test(name))result={valid:false,errors:['wrong_extension'],blocks:[],warnings:[]};
      else if(!file||Number(file.size)>262144)result={valid:false,errors:['markdown_too_large'],blocks:[],warnings:[]};
      else{
        try{source=await file.text();result=parseMarkdownToBDRE(source,this.allowPageMedia);}
        catch(_){result={valid:false,errors:['read_failed'],blocks:[],warnings:[]};}
      }
      this.pendingMarkdownImport={name:name||'markdown.md',source,result};
      this.workspaceView='markdown-import-preview';this.previewMode=false;this.render();
      const title=this.root.querySelector('[data-markdown-import-title]');if(title)title.focus();
    }

    confirmMarkdownImport() {
      const pending=this.pendingMarkdownImport,copy=this.markdownImport;
      if(!pending||!pending.result||!pending.result.valid||!pending.result.blocks.length)return false;
      if(this.locked){this.announceStatus(copy.insertUnavailable);return false;}
      const afterId=this.selectedId||null,allowedTypes=this.insertTypes(afterId).map((item)=>item.type);
      if(!this.canInsertPatterns(afterId)||!allowedTypes.includes('core/group')){this.announceStatus(copy.insertUnavailable);return false;}
      const next=CLONE(this.state);this.prepareSlashTarget(next,afterId);
      const location=this.insertionLocation(next.blocks,afterId);
      const children=pending.result.blocks.map((block)=>cloneBlockWithNewIds(block,()=>this.newId()));
      const group=this.node('core/group',{},'',children);location.target.splice(location.index,0,group);
      const validation=validateDocument(next,this.allowPageMedia);
      if(!validation.valid||utf8Length(JSON.stringify(next))>4194304){this.announceStatus(copy.insertFailed);return false;}
      this.pendingMarkdownImport=null;this.finishInsert(next,group);
      this.statusMessage=copy.inserted;this.announceStatus(copy.inserted);this.render();this.focusNode(group.id);
      return true;
    }

    async importLocalFile(file) {
      if (!global.ReamurPageImporter) { this.announceStatus(this.strings.importUnavailable || 'Importador indisponível.'); return; }
      try {
        const result=await global.ReamurPageImporter.importFile(file);
        const imported = upgradeDocument(result.document);
        const importedValidation = validateDocument(imported, this.allowPageMedia);
        if (!importedValidation.valid) throw new Error('O arquivo não contém um documento BDRE válido para este editor.');
        if (result.replaceDocument && !global.confirm(this.strings.importReplaceConfirm || 'Substituir todo o documento atual pelo arquivo BDRE?')) return;
        const next = result.replaceDocument ? CLONE(imported) : CLONE(this.state);
        const importedBlocks = imported.blocks.map((block) => cloneBlockWithNewIds(block, () => this.newId()));
        const resultingBlockCount = result.replaceDocument ? importedBlocks.length : next.blocks.length + importedBlocks.length;
        if (resultingBlockCount > 1000) throw new Error('A importação ultrapassaria o limite de 1.000 blocos.');
        if (result.replaceDocument) {
          next.blocks = importedBlocks;
          this.selectedId = importedBlocks[0] ? importedBlocks[0].id : null;
          this.insertAfterId = null; this.slashTrigger = null;
        } else next.blocks.push(...importedBlocks);
        const validation=validateDocument(next, this.allowPageMedia); if(!validation.valid) throw new Error('O conteúdo convertido não passou na validação BDREditor.');
        this.importNotices=(result.warnings||[]).slice(0,6);
        if(result.warnings&&result.warnings.length>6)this.importNotices.push('Há outros avisos omitidos.');
        this.statusMessage=(this.strings.importSuccess||'Conteúdo importado para o rascunho local: ')+file.name;
        this.commit(next,null);
      } catch(error) {
        const extension = String(file && file.name || '').split('.').pop().toLowerCase();
        const audioFormats = ['mp3', 'ogg', 'wav']; const videoFormats = ['mp4', 'webm', 'ogv'];
        const message = audioFormats.includes(extension) ? (this.strings.importAudioLibrary || 'Insert an Audio block and choose or upload audio from the Blog library.') : (videoFormats.includes(extension) ? (this.strings.importVideoLibrary || 'Insert a Local video block and choose or upload video from the Blog library.') : String(error&&error.message||this.strings.importError||'Falha ao importar arquivo.'));
        this.importNotices=[message];
        this.statusMessage=this.importNotices[0]; this.render();
      }
    }

    renderPreviewRuns(runs, parent) {
      (Array.isArray(runs)?runs:[]).forEach((run)=>{
        let node=global.document.createTextNode(run.text||'');
        (run.marks||[]).forEach((mark)=>{const tag=({bold:'strong',italic:'em',underline:'u',strike:'s',code:'code'})[mark];if(tag){const wrap=global.document.createElement(tag);wrap.append(node);node=wrap;}});
        if(run.href){const href=safeActionUrl(run.href);if(href){const a=global.document.createElement('a');a.href=href;a.rel='noopener noreferrer';a.append(node);node=a;}}
        parent.append(node);
      });
    }

    applyPreviewAppearance(element, node) {
      const style=appearanceStyleMap(node && node.attributes && node.attributes.appearance);
      const target=node && node.type==='core/button' ? (element.querySelector('.rb-preview-button')||element) : element;
      Object.keys(style).forEach((key)=>{target.style[key]=style[key];});
      return element;
    }

    renderPreviewBlock(node) {
      const d=global.document; let out;
      const renderChildren=(parent)=>node.children.forEach((child)=>parent.append(this.renderPreviewBlock(child)));
      const text=(tag)=>{const el=d.createElement(tag);this.renderPreviewRuns(node.content,el);return el;};
      switch(node.type){
        case 'core/paragraph': out=text('p'); out.style.textAlign=node.attributes.align||'left'; break;
        case 'core/heading': out=text('h'+Math.min(6,Math.max(1,node.attributes.level||2))); if(node.attributes.anchor)out.id=node.attributes.anchor; break;
        case 'core/quote': out=text('blockquote'); break;
        case 'core/list': out=d.createElement(node.attributes.ordered?'ol':'ul'); renderChildren(out); break;
        case 'core/list-item': out=text('li'); break;
        case 'core/separator': out=d.createElement('hr');this.applySeparatorPreview(out,node.attributes);break;
        case 'core/code': out=d.createElement('pre'); out.textContent=node.content||''; break;
        case 'core/image': out=d.createElement('figure'); if(validLocalImage(node.attributes.src)){const img=d.createElement('img');img.src=this.resolveAdminMediaUrl(node.attributes.src);img.alt=node.attributes.alt||'';img.loading='lazy';out.append(img);} if(node.attributes.caption)out.append(d.createElement('figcaption')).textContent=node.attributes.caption; break;
        case 'core/audio': out=d.createElement('figure'); if(validLocalAudio(node.attributes.src, this.allowPageMedia)){const audio=d.createElement('audio');audio.controls=true;audio.preload='none';audio.src=node.attributes.src;out.append(audio);} const cap=d.createElement('figcaption');this.renderPreviewRuns(node.content,cap);out.append(cap);break;
        case 'core/threejs_thumb': {
          out=d.createElement('figure'); out.className='rb-preview-threejs';
          const stage=d.createElement('div'); stage.className='rms-block-threejs-thumb__stage'; stage.setAttribute('data-threejs-thumb','');
          const scene=String(node.attributes.scene_json||'');
          try { JSON.parse(scene); stage.setAttribute('data-scene',scene); } catch (_) { stage.setAttribute('aria-label',this.strings.threejs.invalidScene||'Cena 3D inválida'); }
          const customScript=String(node.attributes.custom_js||'');
          if(customScript.trim()){try{const encoded=encodeBase64Utf8(customScript);if(encoded)stage.setAttribute('data-custom-js',encoded);}catch(_){}}
          const label=String(node.attributes.label||this.label(node.type)); stage.setAttribute('role','img'); stage.setAttribute('aria-label',label);
          const fallback=d.createElement('span'); fallback.className='rms-block-threejs-thumb__fallback'; fallback.textContent=label; stage.append(fallback); out.append(stage);
          const hint=d.createElement('small'); hint.textContent=this.strings.threejs.previewHint||''; if(hint.textContent)out.append(hint);
          break;
        }
        case 'core/video_file': out=d.createElement('figure'); if(validLocalVideo(node.attributes.src, this.allowPageMedia)){const video=d.createElement('video');video.controls=node.attributes.controls!==false;video.preload='none';video.playsInline=true;video.loop=!!node.attributes.loop;video.autoplay=!!node.attributes.autoplay;video.muted=!!node.attributes.muted||video.autoplay;if(video.autoplay)video.muted=true;if(node.attributes.title)video.title=node.attributes.title;video.src=this.resolveAdminMediaUrl(node.attributes.src);out.append(video);}if(node.attributes.caption)out.append(d.createElement('figcaption')).textContent=node.attributes.caption; break;
        case 'core/video_embed': if(validLocalVideo(node.attributes.src,this.allowPageMedia)){out=d.createElement('video');out.controls=true;out.preload='none';out.playsInline=true;out.src=this.resolveAdminMediaUrl(node.attributes.src);}else{out=d.createElement('div');out.className='rb-preview-video';{const label=d.createElement('span');label.textContent=this.strings.previewVideoLink||'Vídeo incorporado';out.append(label);const href=safeActionUrl(node.attributes.url);if(href){const a=d.createElement('a');a.href=href;a.target='_blank';a.rel='noopener noreferrer';a.textContent=this.strings.previewVideoOpen||'Abrir em nova aba';out.append(a);}}}break;
        case 'core/button': out=d.createElement('p');{const a=d.createElement('a');a.className='rb-preview-button';const href=safeActionUrl(node.attributes.url);if(href){a.href=href;a.rel='noopener noreferrer';}this.renderPreviewRuns(node.content,a);out.append(a);}break;
        case 'core/file': out=d.createElement('p');{const a=d.createElement('a');const href=validLocalDownload(node.attributes.src, this.allowPageMedia)?node.attributes.src:null;if(href){a.href=href;a.rel='noopener noreferrer';}this.renderPreviewRuns(node.content,a);out.append(a);}break;
        case 'core/details': out=d.createElement('details');{const summary=d.createElement('summary');summary.textContent=node.attributes.summary;out.append(summary);this.renderPreviewRuns(node.content,out);}break;
        case 'core/callout': out=d.createElement('aside');out.className='rb-preview-callout';this.renderPreviewRuns(node.content,out);break;
        case 'core/spacer': out=d.createElement('div');out.style.height=(node.attributes.height||32)+'px';out.setAttribute('aria-hidden','true');break;
        case 'core/table': out=d.createElement('table');node.children.forEach((row,ri)=>{const tr=d.createElement('tr');this.applyPreviewAppearance(tr,row);row.children.forEach((cell)=>{const cellEl=d.createElement(tableCellIsHeader(node,ri,cell)?'th':'td');cellEl.rowSpan=cell.attributes.rowspan||1;cellEl.colSpan=cell.attributes.colspan||1;this.renderPreviewRuns(cell.content,cellEl);this.applyPreviewAppearance(cellEl,cell);tr.append(cellEl);});out.append(tr);});break;
        case 'core/table-row': out=d.createElement('tr');renderChildren(out);break;
        case 'core/table-cell': out=d.createElement('td');this.renderPreviewRuns(node.content,out);break;
        case 'core/columns': out=d.createElement('div');out.className='rb-preview-columns';renderChildren(out);break;
        case 'core/column': out=d.createElement('section');renderChildren(out);break;
        case 'core/group': out=d.createElement(node.attributes.toc_generated?'nav':'section');if(node.attributes.toc_generated){out.className='rb-preview-table-of-contents';out.setAttribute('aria-label',node.attributes.outline_label||this.tableOfContents.tool);}renderChildren(out);break;
        case 'core/cover': out=d.createElement('section');out.className='rb-preview-cover';renderChildren(out);break;
        case 'core/gallery': out=d.createElement('div');out.className='rb-preview-gallery';renderChildren(out);break;
        default: out=d.createElement('p');out.textContent='';
      }
      return this.applyPreviewAppearance(out,node);
    }

    renderPreviewDocument() {
      const preview=global.document.createElement('article');preview.className='rb-live-preview';
      const title=global.document.createElement('div');title.className='rb-live-preview-label';title.textContent=this.strings.previewTitle||'Prévia da página';preview.append(title);
      if(this.options.workspaceContext){
        const context=this.options.workspaceContext;
        const currentTitle=context.pageTitleInput&&typeof context.pageTitleInput.value==='string'?context.pageTitleInput.value:context.pageTitle;
        if(currentTitle||context.untitledPage){const pageTitle=global.document.createElement('h1');pageTitle.className='rb-live-preview-page-title';pageTitle.textContent=currentTitle||context.untitledPage;preview.append(pageTitle);}
      }
      if(!this.state.blocks.length){const empty=global.document.createElement('p');empty.className='rb-muted';empty.textContent=this.strings.canvasEmpty;preview.append(empty);}
      this.state.blocks.forEach((node)=>preview.append(this.renderPreviewBlock(node)));
      return preview;
    }

    alignSelectedText(alignment) {
      const found = this.selectedId ? this.findPath(this.state.blocks, this.selectedId) : null;
      if (!found || found.node.type !== 'core/paragraph' || !['left','center','right'].includes(alignment)) return;
      this.updateNode(found.node.id, (node) => { node.attributes.align = alignment;if(node.attributes.appearance){delete node.attributes.appearance.text_align;if(!Object.keys(node.attributes.appearance).length)delete node.attributes.appearance;} });
      this.updateRibbonState();
    }

    updateRibbonState() {
      const found = this.selectedId ? this.findPath(this.state.blocks, this.selectedId) : null;
      this.root.querySelectorAll('[data-action="align-text"]').forEach((button) => {
        button.disabled = !found || found.node.type !== 'core/paragraph';
        button.setAttribute('aria-pressed', String(!!found && found.node.type === 'core/paragraph' && ((found.node.attributes.appearance&&found.node.attributes.appearance.text_align)||found.node.attributes.align||'left') === button.dataset.alignment));
      });
      const insertionPoint=this.slashTrigger?this.slashTrigger.nodeId:(this.insertAfterId||this.selectedId);
      const availableTypes=new Set(this.insertTypes(insertionPoint).map((item)=>item.type));
      this.root.querySelectorAll('[data-ribbon-insert]').forEach((button)=>{button.disabled=this.locked||!availableTypes.has(button.dataset.type);button.setAttribute('aria-disabled',String(button.disabled));});
      const patternsAvailable=this.canInsertPatterns(insertionPoint);
      this.root.querySelectorAll('[data-ribbon-pattern]').forEach((button)=>{button.disabled=this.locked||!patternsAvailable;button.setAttribute('aria-disabled',String(button.disabled));});
      this.updateInlineToolbarState();
    }

    renderDocumentStatistics() {
      const group=this.el('div','rb-document-stats');group.setAttribute('role','group');group.setAttribute('aria-live','off');group.setAttribute('aria-label',this.strings.documentStatistics||'Document statistics');
      const words=this.el('span','rb-document-stat-words');words.dataset.documentStatWords='1';
      const chars=this.el('span','rb-document-stat-characters');chars.dataset.documentStatCharacters='1';group.append(words,chars);this.updateDocumentStatistics(group);return group;
    }

    scheduleDocumentStatistics() {
      if(this.documentStatsTimer)global.clearTimeout(this.documentStatsTimer);
      this.documentStatsTimer=global.setTimeout(()=>{this.documentStatsTimer=null;this.updateDocumentStatistics();},180);
    }

    updateDocumentStatistics(target) {
      const group=target||this.root.querySelector('.rb-document-stats');if(!group)return;
      const locale=String(document.documentElement.lang||'').trim()||undefined;
      const stats=documentStatistics(this.state.blocks,locale);
      const words=group.querySelector('[data-document-stat-words]'),chars=group.querySelector('[data-document-stat-characters]');
      if(words)words.textContent=(this.strings.documentWords||'Words')+': '+stats.words;
      if(chars)chars.textContent=(this.strings.documentCharacters||'Characters (including spaces)')+': '+stats.characters;
    }

    closeFindReplace() {
      this.findReplaceOpen=false; this.activeRibbonTab='review'; this.render();
      const trigger=this.root.querySelector('[data-action="toggle-find-replace"]'); if(trigger)trigger.focus();
    }

    renderFindReplacePanel() {
      const panel=this.el('section','rb-find-replace'); panel.setAttribute('aria-label',this.strings.findReplaceButton||'Find and replace');
      const form=this.el('div','rb-find-fields');
      const findLabel=this.el('label','',this.strings.findLabel||'Find'); const find=this.el('input',''); find.type='search'; find.value=this.findQuery; find.dataset.findQuery='1'; findLabel.append(find);
      const replaceLabel=this.el('label','',this.strings.replaceLabel||'Replace with'); const replace=this.el('input',''); replace.type='text'; replace.value=this.replaceQuery; replace.dataset.replaceQuery='1'; replaceLabel.append(replace);
      const caseLabel=this.el('label','rb-find-case'); const check=this.el('input',''); check.type='checkbox'; check.checked=this.findCaseSensitive; check.dataset.findCase='1'; caseLabel.append(check,document.createTextNode(this.strings.findCaseSensitive||'Case sensitive'));
      const wholeLabel=this.el('label','rb-find-case'); const whole=this.el('input',''); whole.type='checkbox'; whole.checked=this.findWholeWord; whole.dataset.findWholeWord='1'; wholeLabel.append(whole,document.createTextNode(this.strings.findWholeWord||'Whole words only'));
      form.append(findLabel,replaceLabel,caseLabel,wholeLabel);
      const actions=this.el('div','rb-find-actions');
      actions.append(this.button(this.strings.findPrevious||'Previous match','find-previous'),this.button(this.strings.findNext||'Next match','find-next'),this.button(this.strings.replaceOne||'Replace','replace-one'),this.button(this.strings.replaceAll||'Replace all','replace-all'));
      const status=this.el('p','rb-find-status'); status.dataset.findStatus='1'; status.setAttribute('role','status'); status.setAttribute('aria-live','polite');
      panel.append(form,actions,this.el('p','rb-find-keyboard-hint',this.strings.findKeyboardHint||'Enter next · Shift+Enter previous · Esc close'),this.el('p','rb-find-hint',this.strings.findCrossRunHint||'Search spans differently formatted text runs within a block, but does not cross block boundaries.'),status);
      this.updateFindStatus(status); return panel;
    }

    findMatches() {
      if(!this.findQuery)return [];
      const matches=[];
      const visit=(nodes)=>{ (nodes||[]).forEach((node)=>{
        if(matches.length>1000)return;
        if(node && BLOCK_TEXT_TYPES.has(node.type) && Array.isArray(node.content)) findTextMatchesInRuns(node.content,this.findQuery,this.findCaseSensitive,this.findWholeWord).forEach((match)=>matches.push(Object.assign({nodeId:node.id},match)));
        if(node && Array.isArray(node.children))visit(node.children);
      }); };
      visit(this.state.blocks); return matches;
    }

    updateFindStatus(target) {
      const status=target || this.root.querySelector('[data-find-status]'); if(!status) return;
      if(!this.findQuery){status.textContent=this.strings.findEmpty||'Enter text to find.';return;}
      const matches=this.findMatches();
      if(!matches.length){status.textContent=this.strings.findNoMatches||'No matches found.';return;}
      const pos=this.activeFindIndex<0?1:Math.min(this.activeFindIndex+1,matches.length); status.textContent=(this.strings.findMatchCount||'%1 of %2 matches').replace('%1',String(pos)).replace('%2',matches.length>1000?'1000+':String(matches.length));
    }

    moveFindMatch(direction) {
      const matches=this.findMatches(); if(!matches.length){this.updateFindStatus();return;}
      this.activeFindIndex=this.activeFindIndex<0?(direction>0?0:matches.length-1):(this.activeFindIndex+direction+matches.length)%matches.length;
      const match=matches[this.activeFindIndex]; this.focusFindMatch(match); this.updateFindStatus();
    }

    focusFindMatch(match) {
      const field=Array.from(this.root.querySelectorAll('[data-edit-content]')).find((item)=>item.dataset.editContent===match.nodeId); if(!field) return;
      const found=this.findPath(this.state.blocks,match.nodeId); if(!found) return;
      let offset=0; for(let i=0;i<match.runIndex;i++) offset+=String(found.node.content[i].text||'').length; offset+=match.start;
      const walker=document.createTreeWalker(field,NodeFilter.SHOW_TEXT), nodes=[]; let textNode;
      while((textNode=walker.nextNode())) nodes.push(textNode);
      const locate=(position)=>{let remain=position;for(const n of nodes){if(remain<=n.nodeValue.length)return {node:n,offset:remain};remain-=n.nodeValue.length;}return null;};
      const a=locate(offset),b=locate(offset+match.length); if(!a||!b)return;
      const range=document.createRange();range.setStart(a.node,a.offset);range.setEnd(b.node,b.offset);
      const selection=global.getSelection(); if(selection){selection.removeAllRanges();selection.addRange(range);} field.focus();
      const parent=a.node.parentElement; if(parent&&parent.scrollIntoView)parent.scrollIntoView({block:'nearest'});
    }

    replaceFoundMatch(all) {
      if(this.locked) return;
      let matches=this.findMatches(); if(!matches.length){this.updateFindStatus();return;}
      if(all && matches.length>1000){this.statusMessage=this.strings.replaceLimit||'More than 1,000 matches; narrow the search before replacing.';this.render();return;}
      if(!all){ if(this.activeFindIndex<0)this.activeFindIndex=0; matches=[matches[Math.min(this.activeFindIndex,matches.length-1)]]; }
      const next=CLONE(this.state); let replaced=0;
      const byNode=new Map(); matches.forEach((m)=>{if(!byNode.has(m.nodeId))byNode.set(m.nodeId,[]);byNode.get(m.nodeId).push(m);});
      byNode.forEach((nodeMatches,nodeId)=>{const found=this.findPath(next.blocks,nodeId);if(!found)return;const node=found.node;
        nodeMatches.sort((a,b)=>b.absoluteStart-a.absoluteStart).forEach((m)=>{
          node.content=replaceTextMatchInRuns(node.content,m,this.replaceQuery); replaced++;
        });
      });
      if(!replaced){this.updateFindStatus();return;}
      this.clearTextEditSnapshot(); this.commit(next,this.selectedId); this.activeFindIndex=-1;
      this.statusMessage=(this.strings.replaceDone||'Replaced %1 occurrence(s).').replace('%1',String(replaced)); this.render(); const findInput=this.root.querySelector('[data-find-query]'); if(findInput)findInput.focus();
    }

    renderRibbonGroup(label, className) {
      const group = this.el('section', 'rb-ribbon-group' + (className ? ' ' + className : ''));
      group.setAttribute('aria-label', label);
      group.append(this.el('span','rb-ribbon-group-title',label));
      const tools = this.el('div','rb-ribbon-tools'); group.append(tools);
      return { group, tools };
    }

    renderRibbonContents() {
      const panel = this.el('div','rb-ribbon-content'); panel.id='rb-ribbon-panel-'+this.instance;
      const labels={home:this.strings.ribbonHome||'Início',insert:this.strings.ribbonInsert||'Inserir',media:this.strings.categoryMedia||'Mídia',layout:this.strings.categoryLayout||'Layout',review:this.strings.ribbonReview||'Revisar',view:this.strings.ribbonView||'Exibir'};
      panel.setAttribute('role','tabpanel');panel.setAttribute('aria-label',labels[this.activeRibbonTab]||labels.home);
      const addButton=(tools,label,action,extra)=>tools.append(this.button(label,action,extra));
      const language=String(global.document&&global.document.documentElement&&global.document.documentElement.lang||'').toLowerCase();
      const patternLanguage=language.startsWith('es')?'es':(language.startsWith('en')?'en':'pt');
      const appendBlockTools=(tab,label)=>{
        const group=this.renderRibbonGroup(label);
        (RIBBON_BLOCK_TYPES[tab]||[]).forEach((type)=>{
          const tool=this.button(this.label(type),'insert',{type,icon:TYPE_ICONS[type],iconOnly:true,tooltipDescription:getToolDescription('insert',type,language),ribbonInsert:true});
          tool.classList.add('rb-ribbon-block-tool');group.tools.append(tool);
        });
        panel.append(group.group);
      };
      if(this.activeRibbonTab==='home'){
        let g=this.renderRibbonGroup(this.strings.groupClipboard||'Área de transferência');
        addButton(g.tools,this.strings.exportDocument||'Exportar BDRE','export-document',{icon:'file'});
        const addBlocks=this.button(this.strings.addBlock,'toggle-inserter',{icon:'plus'});addBlocks.setAttribute('aria-expanded', String(this.inserterOpen));addBlocks.setAttribute('aria-controls','rb-inserter-'+this.instance);g.tools.append(addBlocks);
        addButton(g.tools,this.strings.pasteBlock,'paste-block',{icon:'copy'});
        const undo=this.button(this.strings.undo,'undo');undo.dataset.history='undo';g.tools.append(undo);
        const redo=this.button(this.strings.redo,'redo');redo.dataset.history='redo';g.tools.append(redo);panel.append(g.group);
        g=this.renderRibbonGroup(this.strings.groupTextFormat||'Formatação de texto');
        INLINE_MARKS.forEach((mark)=>{const b=this.button(this.strings[mark]||mark,'format-mark',{mark});b.dataset.mark=mark;b.setAttribute('aria-pressed','false');g.tools.append(b);});
        const link=this.el('input','rb-inline-link');link.type='text';link.value=this.linkDraft;link.placeholder=this.strings.linkPlaceholder;link.setAttribute('aria-label',this.strings.link);link.dataset.inlineLinkInput='1';g.tools.append(link);
        addButton(g.tools,this.strings.applyLink,'link-apply');addButton(g.tools,this.strings.removeLink,'link-remove');panel.append(g.group);
        g=this.renderRibbonGroup(this.strings.groupParagraphAlign||'Alinhamento do parágrafo');
        [['left',this.strings.alignLeft||this.strings.media.alignments.left],['center',this.strings.alignCenter||this.strings.media.alignments.center],['right',this.strings.alignRight||this.strings.media.alignments.right]].forEach(([alignment,label])=>{const b=this.button(label,'align-text',{alignment});b.dataset.alignment=alignment;g.tools.append(b);});panel.append(g.group);
      }else if(this.activeRibbonTab==='insert'){
        appendBlockTools('insert',this.strings.categoryContent||'Conteúdo');
        const g=this.renderRibbonGroup(this.strings.groupInsert||'Explorar e importar');
        const inserter=this.button(this.strings.addBlock||'Todos os blocos','toggle-inserter',{icon:'plus'});inserter.setAttribute('aria-expanded', String(this.inserterOpen));inserter.setAttribute('aria-controls','rb-inserter-'+this.instance);g.tools.append(inserter);
        addButton(g.tools,this.strings.importFile||'Importar arquivo','import-file',{icon:'file'});
        const fileInput=this.el('input','rb-import-file-input');fileInput.type='file';fileInput.accept=(global.ReamurPageImporter&&global.ReamurPageImporter.formats||[]).join(',');fileInput.dataset.importFile='1';fileInput.classList.add('rb-sr-only');fileInput.tabIndex=-1;fileInput.setAttribute('aria-label',this.strings.importFile||'Importar arquivo');g.tools.append(fileInput);panel.append(g.group);
        if(this.strings.importHint)panel.append(this.el('p','rb-import-hint',this.strings.importHint));
      }else if(this.activeRibbonTab==='media'){
        appendBlockTools('media',this.strings.categoryMedia||'Mídia');
      }else if(this.activeRibbonTab==='layout'){
        appendBlockTools('layout',this.strings.categoryLayout||'Layout');
        const g=this.renderRibbonGroup(this.strings.patternsCategory||'Padrões');
        const patternIcons={two_columns:'columns',quote_cta:'quote',section_intro:'group',cover_hero:'cover'};
        ['two_columns','quote_cta','section_intro','cover_hero'].forEach((pattern)=>{
          const label=(this.strings.patternLabels||{})[pattern]||pattern;
          const description=PATTERN_TOOL_DESCRIPTIONS[patternLanguage][pattern];
          const tool=this.button(label,'insert-pattern',{pattern,icon:patternIcons[pattern],iconOnly:true,tooltipDescription:description,ribbonPattern:true});
          tool.classList.add('rb-ribbon-pattern-tool');g.tools.append(tool);
        });panel.append(g.group);
      }else if(this.activeRibbonTab==='review'){
        let g=this.renderRibbonGroup(this.strings.historyTitle||'Histórico de revisões');
        const history=this.button(this.strings.historyButton||'Histórico','toggle-revision-history',{icon:'history'});history.disabled=!this.getDraftHistory().length;history.setAttribute('aria-expanded',String(this.historyOpen));g.tools.append(history);panel.append(g.group);
        g=this.renderRibbonGroup(this.strings.findReplaceButton||'Localizar e substituir');
        const find=this.button(this.strings.findReplaceButton||'Localizar e substituir','toggle-find-replace',{icon:'search'});find.setAttribute('aria-expanded',String(this.findReplaceOpen));g.tools.append(find);panel.append(g.group);
        g=this.renderRibbonGroup(this.accessibility.tool);const audit=this.button(this.accessibility.tool,'workspace-tool',{tool:'accessibility',icon:'check',showLabel:true});audit.dataset.workspaceTool='accessibility';g.tools.append(audit);panel.append(g.group);
        g=this.renderRibbonGroup(this.linkReview.tool);const linkAudit=this.button(this.linkReview.tool,'workspace-tool',{tool:'links',icon:'link',showLabel:true});linkAudit.dataset.workspaceTool='links';g.tools.append(linkAudit);panel.append(g.group);
        g=this.renderRibbonGroup(this.internalAnchorReview.tool);const anchorAudit=this.button(this.internalAnchorReview.tool,'workspace-tool',{tool:'internal-anchors',icon:'link',showLabel:true});anchorAudit.dataset.workspaceTool='internal-anchors';g.tools.append(anchorAudit);panel.append(g.group);
        g=this.renderRibbonGroup(this.readability.tool);const readabilityAudit=this.button(this.readability.tool,'workspace-tool',{tool:'readability',icon:'bookOpen',showLabel:true});readabilityAudit.dataset.workspaceTool='readability';g.tools.append(readabilityAudit);panel.append(g.group);
        g=this.renderRibbonGroup(this.preflight.tool);const preflightAudit=this.button(this.preflight.tool,'workspace-tool',{tool:'preflight',icon:'checklist',showLabel:true});preflightAudit.dataset.workspaceTool='preflight';g.tools.append(preflightAudit);panel.append(g.group);
        g=this.renderRibbonGroup(this.revisionCompare.tool);const compareAudit=this.button(this.revisionCompare.tool,'workspace-tool',{tool:'revision-compare',icon:'compare',showLabel:true});compareAudit.dataset.workspaceTool='revision-compare';g.tools.append(compareAudit);panel.append(g.group);
      }else if(this.activeRibbonTab==='view'){
        let g=this.renderRibbonGroup(this.strings.groupPanels||'Painéis recolhíveis');
        [['elements',this.strings.builderElements,'layers'],['structure',this.strings.builderStructure,'outline'],['settings',this.strings.builderSettings,'settings']].forEach(([panelName,label,icon])=>{const b=this.button(label,'builder-panel',{panel:panelName,icon});b.setAttribute('aria-pressed',String(this.fullscreen?(panelName==='settings'?(this.inspectorOpen&&!this.previewMode):this.activePanel===panelName):this.activePanel===panelName));if(this.fullscreen&&this.previewMode&&panelName==='settings')b.disabled=true;g.tools.append(b);});panel.append(g.group);
        const modelsTool=this.button(this.ui.models,'workspace-tool',{tool:'models',icon:'layers',showLabel:true});modelsTool.dataset.workspaceTool='models';g.tools.append(modelsTool);
        const tocTool=this.button(this.tableOfContents.tool,'workspace-tool',{tool:'table-of-contents',icon:'list',showLabel:true});tocTool.dataset.workspaceTool='table-of-contents';g.tools.append(tocTool);
        g=this.renderRibbonGroup(this.markdownExport.group);const markdownTool=this.button(this.markdownExport.open,'workspace-tool',{tool:'export',icon:'markdown',showLabel:true});markdownTool.dataset.workspaceTool='export';g.tools.append(markdownTool);panel.append(g.group);
        g=this.renderRibbonGroup(this.strings.groupPreview||'Pré-visualização');
        [['desktop',this.strings.previewDesktop],['tablet',this.strings.previewTablet],['mobile',this.strings.previewMobile]].forEach(([size,label])=>{const b=this.button(label,'preview',{size});b.setAttribute('aria-pressed',String(size===this.preview));g.tools.append(b);});
        const preview=this.button(this.previewMode?(this.strings.editMode||'Voltar à edição'):(this.strings.previewMode||'Pré-visualizar'),'toggle-preview',{icon:this.previewMode?'collapse':'desktop'});preview.setAttribute('aria-pressed',String(this.previewMode));g.tools.append(preview);
        const full=this.button(this.fullscreen?this.strings.fullscreenExit:this.strings.fullscreenEnter,'toggle-fullscreen',{icon:this.fullscreen?'collapse':'expand'});g.tools.append(full);panel.append(g.group);
      }
      return panel;
    }

    renderOfficeMenu() {
      const nav=this.el('nav','rb-office-menu');nav.setAttribute('aria-label',this.strings.editorTools||'Editor navigation');
      const menus=[[this.ui.file,'file'],[this.ui.edit,'edit'],[this.ui.insert,'insert'],[this.ui.format,'format'],[this.ui.tools,'tools'],[this.ui.help,'help']];
      menus.forEach(([label,key])=>{
        const button=this.el('button','rb-office-menu-item',label);button.type='button';button.dataset.action='office-menu';button.dataset.menu=key;
        const current=key==='file'?(this.workspaceView==='export'||this.workspaceView==='share'):key==='help'?this.workspaceView==='help':this.workspaceView==='editor'&&(key==='edit'?this.activeRibbonTab==='home':key==='format'?this.activeRibbonTab==='layout':key==='insert'?this.activeRibbonTab==='insert':key==='tools'?['media','review','view'].includes(this.activeRibbonTab):false);
        if(current)button.setAttribute('aria-current','page');nav.append(button);
      });
      return nav;
    }

    renderCanvas() {
      const canvas=this.el('div','rb-canvas');canvas.setAttribute('role','region');canvas.setAttribute('aria-label',this.strings.canvas);
      const canvasHead=this.el('div','rb-canvas-head');canvasHead.append(this.icon('layers','rb-icon'),this.el('span','',this.strings.canvas),this.el('span','rb-canvas-count',String(this.state.blocks.length)));canvas.append(canvasHead);
      if(this.previewMode){canvas.classList.add('rb-preview-canvas');canvas.append(this.renderPreviewDocument());}
      else{if(!this.state.blocks.length)canvas.append(this.el('p','rb-empty',this.strings.canvasEmpty));this.state.blocks.forEach((node)=>canvas.append(this.renderNode(node,true)));}
      return canvas;
    }

    renderAccessibilityAudit() {
      const copy=this.accessibility;
      const wrap=this.el('div','rb-workspace-screen-content rb-accessibility-workspace');
      const header=this.el('div','rb-accessibility-header');
      const titleGroup=this.el('div','');titleGroup.append(this.el('h1','rb-workspace-screen-title',copy.title));header.append(titleGroup,this.button(this.ui.modelsBack||this.ui.home,'workspace-tool',{tool:'home',icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint),this.el('p','rb-accessibility-note',copy.note));
      const result=analyzeDocumentAccessibility(this.state,this.allowPageMedia);
      if(!result.valid){const warning=this.el('p','rb-workspace-warning',copy.invalid);warning.setAttribute('role','alert');wrap.append(warning);return wrap;}
      const summary=this.el('p','rb-accessibility-summary',(copy.count||'Findings: %1').replace('%1',String(result.issues.length)));
      summary.setAttribute('role','status');summary.setAttribute('aria-live','polite');wrap.append(summary);
      if(!result.issues.length){const success=this.el('section','rb-accessibility-empty');success.append(this.icon('check','rb-icon'),this.el('strong','',copy.noIssues));wrap.append(success);return wrap;}
      const list=this.el('div','rb-accessibility-findings');list.setAttribute('role','list');
      result.issues.forEach((issue)=>{
        const item=this.el('article','rb-accessibility-issue rb-accessibility-issue--'+issue.severity);item.setAttribute('role','listitem');
        const top=this.el('div','rb-accessibility-issue-top');top.append(this.el('span','rb-accessibility-severity',issue.severity==='warning'?copy.warning:copy.info),this.el('span','rb-accessibility-block-type',this.label(issue.type)));item.append(top);
        item.append(this.el('h2','rb-accessibility-issue-title',issue.title),this.el('p','rb-accessibility-issue-detail',issue.detail),this.el('p','rb-accessibility-issue-suggestion',issue.suggestion));
        const open=this.button(copy.openBlock,'accessibility-focus',{nodeId:issue.nodeId,icon:'outline',showLabel:true});item.append(open);list.append(item);
      });
      wrap.append(list);return wrap;
    }

    renderLinkReview() {
      const copy=this.linkReview,wrap=this.el('div','rb-workspace-screen-content rb-accessibility-workspace rb-link-review-workspace');
      const header=this.el('div','rb-accessibility-header'),titleGroup=this.el('div','');titleGroup.append(this.el('h1','rb-workspace-screen-title',copy.title));
      header.append(titleGroup,this.button(copy.back,'workspace-tool',{tool:'home',icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint),this.el('p','rb-accessibility-note',copy.note));
      const result=analyzeDocumentLinks(this.state,this.allowPageMedia);
      if(!result.valid){const warning=this.el('p','rb-workspace-warning',copy.invalid);warning.setAttribute('role','alert');wrap.append(warning);return wrap;}
      const summary=this.el('p','rb-accessibility-summary',copy.count.replace('%1',String(result.issues.length)));summary.setAttribute('role','status');summary.setAttribute('aria-live','polite');wrap.append(summary);
      if(!result.issues.length){const success=this.el('section','rb-accessibility-empty');success.append(this.icon('check','rb-icon'),this.el('strong','',copy.noIssues));wrap.append(success);return wrap;}
      const list=this.el('div','rb-accessibility-findings');list.setAttribute('role','list');
      result.issues.forEach((issue)=>{
        const item=this.el('article','rb-accessibility-issue rb-accessibility-issue--'+issue.severity);item.setAttribute('role','listitem');
        const top=this.el('div','rb-accessibility-issue-top');top.append(this.el('span','rb-accessibility-severity',issue.severity==='warning'?copy.warning:copy.info),this.el('span','rb-accessibility-block-type',this.label(issue.type)));item.append(top);
        item.append(this.el('h2','rb-accessibility-issue-title',issue.title),this.el('p','rb-accessibility-issue-detail',issue.detail),this.el('p','rb-accessibility-issue-suggestion',issue.suggestion));
        item.append(this.button(copy.openBlock,'link-review-focus',{nodeId:issue.nodeId,icon:'outline',showLabel:true}));list.append(item);
      });
      wrap.append(list);return wrap;
    }


    renderInternalAnchorReview() {
      const copy=this.internalAnchorReview,wrap=this.el('div','rb-workspace-screen-content rb-accessibility-workspace rb-internal-anchor-review');
      const header=this.el('div','rb-accessibility-header'),titleGroup=this.el('div','');titleGroup.append(this.el('h1','rb-workspace-screen-title',copy.title));
      header.append(titleGroup,this.button(copy.back,'workspace-tool',{tool:'home',icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint),this.el('p','rb-accessibility-note',copy.note));
      const result=analyzeInternalAnchors(this.state,this.allowPageMedia);
      if(!result.valid){const warning=this.el('p','rb-workspace-warning',copy.invalid);warning.setAttribute('role','alert');wrap.append(warning);return wrap;}
      const summary=this.el('p','rb-accessibility-summary',copy.summary.replace('%1',String(result.checked)).replace('%2',String(result.issues.length)));summary.setAttribute('role','status');summary.setAttribute('aria-live','polite');wrap.append(summary);
      if(!result.issues.length){const success=this.el('section','rb-accessibility-empty');success.append(this.icon('check','rb-icon'),this.el('strong','',copy.clean));wrap.append(success);return wrap;}
      const list=this.el('div','rb-accessibility-findings');list.setAttribute('role','list');
      result.issues.forEach((issue)=>{
        const item=this.el('article','rb-accessibility-issue rb-accessibility-issue--warning');item.setAttribute('role','listitem');
        const top=this.el('div','rb-accessibility-issue-top');top.append(this.el('span','rb-accessibility-severity',copy.warning),this.el('span','rb-accessibility-block-type',this.label(issue.type)));item.append(top);
        item.append(this.el('h2','rb-accessibility-issue-title',issue.detail),this.el('p','rb-accessibility-issue-detail',copy.destination.replace('%1',issue.destination)),this.el('p','rb-accessibility-issue-suggestion',issue.suggestion));
        item.append(this.button(copy.openBlock,'internal-anchor-focus',{nodeId:issue.nodeId,icon:'outline',showLabel:true}));list.append(item);
      });
      wrap.append(list);return wrap;
    }

    renderReadabilityReview() {
      const copy=this.readability,wrap=this.el('div','rb-workspace-screen-content rb-accessibility-workspace rb-readability-workspace');
      const header=this.el('div','rb-accessibility-header'),titleGroup=this.el('div','');titleGroup.append(this.el('h1','rb-workspace-screen-title',copy.title));
      header.append(titleGroup,this.button(copy.back,'workspace-tool',{tool:'home',icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint),this.el('p','rb-accessibility-note',copy.note));
      const result=analyzeDocumentReadability(this.state,this.allowPageMedia);
      if(!result.valid){const warning=this.el('p','rb-workspace-warning',copy.invalid);warning.setAttribute('role','alert');wrap.append(warning);return wrap;}
      const metrics=this.el('div','rb-readability-metrics');
      metrics.append(this.icon('paragraph','rb-readability-metric-icon'),this.el('span','',copy.words.replace('%1',String(result.wordCount))),this.icon('clock','rb-readability-metric-icon'),this.el('span','',copy.readingTime.replace('%1',String(result.readingMinutes))));wrap.append(metrics);
      const summary=this.el('p','rb-accessibility-summary',copy.count.replace('%1',String(result.issues.length)));summary.setAttribute('role','status');summary.setAttribute('aria-live','polite');wrap.append(summary);
      if(!result.issues.length){const success=this.el('section','rb-accessibility-empty');success.append(this.icon('check','rb-icon'),this.el('strong','',copy.noIssues));wrap.append(success);return wrap;}
      const list=this.el('div','rb-accessibility-findings');list.setAttribute('role','list');
      result.issues.forEach((issue)=>{
        const item=this.el('article','rb-accessibility-issue rb-accessibility-issue--'+issue.severity);item.setAttribute('role','listitem');
        const top=this.el('div','rb-accessibility-issue-top'),issueIcon=issue.code==='long_section'?'heading':'paragraph';top.append(this.icon(issueIcon,'rb-readability-finding-icon'),this.el('span','rb-accessibility-severity',issue.severity==='warning'?copy.warning:copy.info),this.el('span','rb-accessibility-block-type',this.label(issue.type)));item.append(top);
        item.append(this.el('h2','rb-accessibility-issue-title',issue.title),this.el('p','rb-accessibility-issue-detail',issue.detail),this.el('p','rb-accessibility-issue-suggestion',issue.suggestion));
        item.append(this.button(copy.openBlock,'readability-focus',{nodeId:issue.nodeId,icon:'outline',showLabel:true}));list.append(item);
      });
      wrap.append(list);return wrap;
    }

    renderPreflightReview() {
      const copy=this.preflight,wrap=this.el('div','rb-workspace-screen-content rb-accessibility-workspace rb-preflight-workspace');
      const header=this.el('div','rb-accessibility-header'),titleGroup=this.el('div','');titleGroup.append(this.el('h1','rb-workspace-screen-title',copy.title));
      header.append(titleGroup,this.button(copy.back,'workspace-tool',{tool:'home',icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint),this.el('p','rb-accessibility-note',copy.note));
      const result=analyzeDocumentPreflight(this.state,this.allowPageMedia);
      if(!result.valid){const warning=this.el('p','rb-workspace-warning',copy.invalid);warning.setAttribute('role','alert');wrap.append(warning);return wrap;}
      const metrics=this.el('div','rb-preflight-metrics');metrics.setAttribute('role','group');metrics.setAttribute('aria-label',copy.title);
      const metric=(icon,label,className)=>{const card=this.el('div','rb-preflight-metric '+className);card.append(this.icon(icon,'rb-preflight-metric-icon'),this.el('span','',label));metrics.append(card);};
      metric('checklist',copy.total.replace('%1',String(result.total)),'rb-preflight-metric--total');
      metric('check',copy.warnings.replace('%1',String(result.warnings)),'rb-preflight-metric--warning');
      metric('info',copy.suggestions.replace('%1',String(result.suggestions)),'rb-preflight-metric--suggestion');
      metric('clock',copy.words.replace('%1',String(result.wordCount)).replace('%2',String(result.readingMinutes)),'rb-preflight-metric--reading');
      wrap.append(metrics);
      const status=this.el('p','rb-accessibility-summary',result.total?copy.total.replace('%1',String(result.total)):copy.clean);status.setAttribute('role','status');status.setAttribute('aria-live','polite');wrap.append(status);
      result.categories.forEach((category)=>{
        const section=this.el('section','rb-preflight-category');const headingId='rb-preflight-'+this.instance+'-'+category.id;section.setAttribute('aria-labelledby',headingId);
        const title=this.el('div','rb-preflight-category-heading'),icon=category.id==='accessibility'?'check':category.id==='links'?'link':'bookOpen';
        const heading=this.el('h2','rb-preflight-category-title',category.title);heading.id=headingId;
        title.append(this.icon(icon,'rb-preflight-category-icon'),heading,this.el('span','rb-preflight-category-count',copy.categoryCount.replace('%1',String(category.issues.length))));section.append(title);
        if(!category.issues.length){section.append(this.el('p','rb-preflight-category-empty',copy.categoryEmpty));wrap.append(section);return;}
        const list=this.el('div','rb-accessibility-findings');list.setAttribute('role','list');
        category.issues.forEach((issue)=>{
          const item=this.el('article','rb-accessibility-issue rb-accessibility-issue--'+issue.severity);item.setAttribute('role','listitem');
          const top=this.el('div','rb-accessibility-issue-top');top.append(this.el('span','rb-accessibility-severity',issue.severity==='warning'?copy.warning:copy.info),this.el('span','rb-accessibility-block-type',this.label(issue.type)));item.append(top);
          item.append(this.el('h3','rb-accessibility-issue-title',issue.title),this.el('p','rb-accessibility-issue-detail',issue.detail),this.el('p','rb-accessibility-issue-suggestion',issue.suggestion));
          item.append(this.button(copy.openBlock,'preflight-focus',{nodeId:issue.nodeId,icon:'outline',showLabel:true}));list.append(item);
        });
        section.append(list);wrap.append(section);
      });
      return wrap;
    }

    renderRevisionComparison() {
      const copy=this.revisionCompare,wrap=this.el('div','rb-workspace-screen-content rb-accessibility-workspace rb-revision-compare-workspace');
      const header=this.el('div','rb-accessibility-header'),titleGroup=this.el('div','');titleGroup.append(this.el('h1','rb-workspace-screen-title',copy.title));
      header.append(titleGroup,this.button(copy.back,'workspace-tool',{tool:'home',icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint),this.el('p','rb-accessibility-note',copy.note));
      const revisions=this.getDraftHistory().filter((revision)=>revision&&revision.document&&typeof revision.document==='object');
      if(!revisions.length){wrap.append(this.el('p','rb-revision-history-empty',copy.historyEmpty));return wrap;}
      const list=this.el('section','rb-revision-history-list');list.setAttribute('aria-label',copy.historyTitle);list.append(this.el('h2','rb-preflight-category-title',copy.historyTitle));
      const rows=this.el('div','rb-revision-history-rows');rows.setAttribute('role','list');
      revisions.forEach((revision,index)=>{
        const row=this.el('div','rb-revision-history-item');row.setAttribute('role','listitem');
        const date=new Date(Number(revision.savedAt)||0).toLocaleString();
        const blockCount=revision.document&&Array.isArray(revision.document.blocks)?revision.document.blocks.length:0;
        const label=this.el('span','rb-revision-history-label',copy.revisionLabel.replace('%1',date).replace('%2',String(blockCount)));label.id='rb-revision-label-'+this.instance+'-'+index;row.append(label);
        const compare=this.button(copy.compare,'compare-draft-revision',{revisionIndex:String(index),icon:'compare',showLabel:true});compare.setAttribute('aria-pressed',String(this.revisionCompareIndex===index));compare.setAttribute('aria-describedby',label.id);row.append(compare);rows.append(row);
      });
      list.append(rows);wrap.append(list);
      const selectedIndex=this.revisionCompareIndex;
      if(!Number.isInteger(selectedIndex)||!revisions[selectedIndex])return wrap;
      const revision=revisions[selectedIndex],result=compareBDREDocuments(this.state,revision.document,this.allowPageMedia);
      const panel=this.el('section','rb-revision-diff');panel.setAttribute('aria-label',copy.comparisonTitle);panel.append(this.el('h2','rb-preflight-category-title',copy.comparisonTitle),this.el('p','rb-workspace-screen-lead',copy.comparisonHint));
      if(!result.valid){const warning=this.el('p','rb-workspace-warning',copy.invalid);warning.setAttribute('role','alert');panel.append(warning);wrap.append(panel);return wrap;}
      const summary=this.el('p','rb-accessibility-summary',copy.summary.replace('%1',String(result.added)).replace('%2',String(result.removed)).replace('%3',String(result.changed)));summary.setAttribute('role','status');summary.setAttribute('aria-live','polite');panel.append(summary);
      if(!result.items.length){const empty=this.el('section','rb-accessibility-empty');empty.append(this.icon('check','rb-icon'),this.el('strong','',copy.noChanges));panel.append(empty);wrap.append(panel);return wrap;}
      const cards=this.el('div','rb-accessibility-findings');cards.setAttribute('role','list');
      const fieldLabels={type:copy.fieldType,attributes:copy.fieldAttributes,content:copy.fieldContent,container:copy.fieldContainer,position:copy.fieldPosition};
      result.items.forEach((change)=>{
        const item=this.el('article','rb-accessibility-issue rb-accessibility-issue--'+(change.kind==='removed'?'warning':'info'));item.setAttribute('role','listitem');
        const top=this.el('div','rb-accessibility-issue-top'),kindLabel=change.kind==='added'?copy.added:change.kind==='removed'?copy.removed:copy.changed;
        top.append(this.el('span','rb-accessibility-severity',kindLabel),this.el('span','rb-accessibility-block-type',this.label(change.type)));item.append(top);
        if(change.kind==='changed'&&change.fields.length)item.append(this.el('p','rb-revision-diff-fields',copy.changedFields.replace('%1',change.fields.map((field)=>fieldLabels[field]||field).join(', '))));
        const previews=this.el('div','rb-revision-diff-previews');
        if(change.kind!=='added'){const before=this.el('div','rb-revision-diff-preview');before.append(this.el('strong','',copy.before),this.el('p','',change.before||copy.noPreview));previews.append(before);}
        if(change.kind!=='removed'){const after=this.el('div','rb-revision-diff-preview');after.append(this.el('strong','',copy.after),this.el('p','',change.after||copy.noPreview));previews.append(after);}
        item.append(previews);
        if(change.kind==='removed')item.append(this.el('p','rb-revision-diff-removed-hint',copy.removedHint));
        else item.append(this.button(copy.openBlock,'revision-compare-focus',{nodeId:change.nodeId,icon:'outline',showLabel:true}));
        cards.append(item);
      });
      panel.append(cards);wrap.append(panel);return wrap;
    }

    renderMarkdownPreview() {
      const copy=this.markdownExport,wrap=this.el('div','rb-workspace-screen-content rb-markdown-preview-workspace');
      const header=this.el('div','rb-accessibility-header'),title=this.el('h1','rb-workspace-screen-title',copy.title);title.dataset.markdownPreviewTitle='1';title.tabIndex=-1;
      header.append(title,this.button(copy.back,'markdown-preview-back',{icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint));
      const result=convertDocumentToMarkdown(this.state,this.allowPageMedia);
      if(!result.valid){const error=this.el('p','rb-workspace-warning',copy.invalid);error.setAttribute('role','alert');wrap.append(error);return wrap;}
      if(utf8Length(result.markdown)>8388608){const error=this.el('p','rb-workspace-warning',copy.tooLarge);error.setAttribute('role','alert');wrap.append(error);return wrap;}
      const actions=this.el('div','rb-markdown-preview-actions');actions.append(this.button(copy.download,'download-markdown',{icon:'file',showLabel:true}),this.el('p','rb-markdown-warning-count',copy.warningCount.replace('%1',String(result.warnings.length))));wrap.append(actions);
      if(result.warnings.length){
        const section=this.el('section','rb-markdown-warnings');section.setAttribute('aria-label',copy.warningsTitle);section.append(this.el('h2','rb-preflight-category-title',copy.warningsTitle));
        const list=this.el('div','rb-markdown-warning-list');list.setAttribute('role','list');
        result.warnings.forEach((warning)=>{const item=this.el('article','rb-markdown-warning-item');item.setAttribute('role','listitem');item.append(this.icon('info','rb-icon'),this.el('span','',copy.warningBlock.replace('%1',this.label(warning.type)).replace('%2',copy.warnings[warning.code]||copy.unsupportedBlock.replace('%1',this.label(warning.type)))));item.append(this.button(copy.openBlock,'markdown-warning-focus',{nodeId:warning.nodeId,icon:'outline',showLabel:true}));list.append(item);});
        section.append(list);wrap.append(section);
      }else wrap.append(this.el('p','rb-markdown-no-warnings',copy.noWarnings));
      if(!result.markdown)wrap.append(this.el('p','rb-markdown-no-warnings',copy.emptyMarkdown));
      const preview= this.el('pre','rb-markdown-preview-code');preview.tabIndex=0;preview.setAttribute('aria-label',copy.previewLabel);const maxPreview=200000;preview.textContent=result.markdown.length>maxPreview?result.markdown.slice(0,maxPreview):result.markdown;wrap.append(preview);
      if(result.markdown.length>maxPreview)wrap.append(this.el('p','rb-markdown-preview-truncated',copy.previewTruncated));
      return wrap;
    }



    renderTableOfContentsPreview() {
      const copy=this.tableOfContents,wrap=this.el('div','rb-workspace-screen-content rb-toc-workspace'),header=this.el('div','rb-accessibility-header');
      const title=this.el('h1','rb-workspace-screen-title',copy.title);title.dataset.tocTitle='1';title.tabIndex=-1;
      header.append(title,this.button(copy.back,'table-of-contents-back',{icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint));
      const plan=createTableOfContentsPlan(this.state,this.tocLevels,this.allowPageMedia);
      if(!plan.valid){const error=this.el('p','rb-workspace-warning',copy.invalid);error.setAttribute('role','alert');wrap.append(error);return wrap;}
      const fieldset=this.el('fieldset','rb-toc-levels');fieldset.append(this.el('legend','',copy.levels));
      for(let level=1;level<=6;level++){
        const label=this.el('label','rb-toc-level-choice'),input=this.el('input','');input.type='checkbox';input.checked=this.tocLevels.includes(level);input.dataset.tocLevel=String(level);label.append(input,this.el('span','',copy.headingLevel.replace('%1',String(level))));fieldset.append(label);
      }
      wrap.append(fieldset);
      const section=this.el('section','rb-toc-preview');section.append(this.el('h2','',copy.preview));
      if(!plan.headings.length)section.append(this.el('p','rb-toc-empty',copy.empty));
      else{
        const list=this.el('ol','rb-toc-preview-list');
        plan.headings.forEach((heading)=>{
          const row=this.el('li','rb-toc-preview-item');row.style.setProperty('padding-inline-start','calc(.75rem + '+((heading.level-1)*.65)+'rem)');
          row.append(this.el('span','rb-toc-heading-level',copy.headingLevel.replace('%1',String(heading.level))),this.el('span','rb-toc-heading-label',heading.text),this.el('code','rb-toc-anchor',(heading.needsAnchor?copy.anchor:copy.existingAnchor).replace('%1',heading.anchor)));
          list.append(row);
        });section.append(list);
      }
      wrap.append(section,this.el('p','rb-toc-additive-note',copy.added));
      const afterId=this.selectedId||null,canInsert=plan.headings.length>0&&!this.locked&&this.canInsertPatterns(afterId)&&this.insertTypes(afterId).some((item)=>item.type==='core/group');
      const actions=this.el('div','rb-toc-actions'),confirm=this.button(copy.insert,'table-of-contents-confirm',{icon:'list',showLabel:true});confirm.disabled=!canInsert;actions.append(confirm);
      if(!canInsert&&plan.headings.length)actions.append(this.el('p','rb-markdown-import-unavailable',copy.unavailable));wrap.append(actions);return wrap;
    }

    renderMarkdownImportPreview() {
      const copy=this.markdownImport,pending=this.pendingMarkdownImport,wrap=this.el('div','rb-workspace-screen-content rb-markdown-import-workspace');
      const header=this.el('div','rb-accessibility-header'),title=this.el('h1','rb-workspace-screen-title',copy.title);title.dataset.markdownImportTitle='1';title.tabIndex=-1;
      header.append(title,this.button(copy.cancel,'markdown-import-back',{icon:'collapse',showLabel:true}));
      wrap.append(header,this.el('p','rb-workspace-screen-lead',copy.hint));
      if(!pending){const error=this.el('p','rb-workspace-warning',copy.invalid);error.setAttribute('role','alert');wrap.append(error);return wrap;}
      const fileLabel=this.el('p','rb-markdown-import-file',copy.file.replace('%1',pending.name));wrap.append(fileLabel);
      const result=pending.result||{valid:false,errors:[],blocks:[],warnings:[]};
      if(!result.valid){
        const error=this.el('p','rb-workspace-warning',result.errors.includes('wrong_extension')?copy.wrongExtension:result.errors.includes('markdown_too_large')?copy.tooLarge:result.errors.includes('read_failed')?copy.parseFailed:copy.invalid);error.setAttribute('role','alert');wrap.append(error);
      }else{
        const blockCount=(nodes)=>nodes.reduce((count,node)=>count+1+blockCount(node.children||[]),0),count=blockCount(result.blocks);
        const summary=this.el('div','rb-markdown-import-summary');summary.append(this.el('p','',copy.blockCount.replace('%1',String(count))),this.el('p','',copy.warningCount.replace('%1',String(result.warnings.length))),this.el('p','rb-markdown-import-additive',copy.additive));
        wrap.append(summary);
        if(result.blocks.length){
          const section=this.el('section','rb-markdown-import-outline');section.append(this.el('h2','',copy.previewTitle));
          const list=this.el('ol','rb-markdown-import-blocks');
          const describe=(node)=>{
            const textOf=(runs)=>Array.isArray(runs)?runs.map((run)=>run.text||'').join(''):String(runs||'');
            let detail=textOf(node.content);
            if(node.type==='core/image')detail=node.attributes.alt||node.attributes.src||'';
            else if(node.type==='core/file'||node.type==='core/audio'||node.type==='core/video_file')detail=node.attributes.src||detail;
            else if(node.type==='core/code')detail=String(node.content||'');
            return detail.replace(/\s+/g,' ').trim().slice(0,150);
          };
          const visit=(nodes,depth)=>nodes.forEach((node)=>{
            const row=this.el('li','rb-markdown-import-block');row.style.setProperty('padding-inline-start','calc(.7rem + '+(Math.min(5,depth)*1.1)+'rem)');
            row.append(this.el('span','rb-markdown-import-type',this.label(node.type)));
            const detail=describe(node);if(detail)row.append(this.el('span','rb-markdown-import-detail',detail));
            list.append(row);if(node.children&&node.children.length)visit(node.children,depth+1);
          });
          visit(result.blocks,0);section.append(list);wrap.append(section);
        }else wrap.append(this.el('p','rb-markdown-import-empty',copy.empty));
        if(result.warnings.length){
          const section=this.el('section','rb-markdown-import-warnings');section.append(this.el('h2','',copy.warningsTitle));
          const list=this.el('ul','rb-markdown-import-warning-list');
          result.warnings.forEach((warning)=>{
            const item=this.el('li','',copy.warnings[warning.code]||copy.invalid);
            if(warning.line)item.append(this.el('span','rb-markdown-import-warning-location',copy.warningLine.replace('%1',String(warning.line))));
            else if(warning.nodeId)item.append(this.el('span','rb-markdown-import-warning-location',copy.warningBlock.replace('%1',warning.nodeId)));
            list.append(item);
          });section.append(list);wrap.append(section);
        }
        const canInsert=!!result.blocks.length&&!this.locked&&this.canInsertPatterns(this.selectedId||null)&&this.insertTypes(this.selectedId||null).some((item)=>item.type==='core/group');
        const actions=this.el('div','rb-markdown-import-actions');const confirm=this.button(copy.insert,'markdown-import-confirm',{icon:'plus',showLabel:true});confirm.disabled=!canInsert;actions.append(confirm);
        if(!canInsert&&result.blocks.length)actions.append(this.el('p','rb-markdown-import-unavailable',copy.insertUnavailable));wrap.append(actions);
      }
      if(pending.source){const details=this.el('details','rb-markdown-import-source');details.append(this.el('summary','',copy.sourceTitle));const pre=this.el('pre','rb-markdown-import-source-text');pre.textContent=pending.source;details.append(pre);wrap.append(details);}
      return wrap;
    }

    renderWorkspaceScreen() {
      const surface=this.el('section','rb-canvas rb-workspace-screen');surface.id='rb-workspace-surface-'+this.instance;surface.setAttribute('role','region');
      if(this.workspaceView==='models'){
        surface.setAttribute('aria-label',this.ui.modelsTitle);surface.append(this.renderPatternLibrary());
      }else if(this.workspaceView==='accessibility'){
        surface.setAttribute('aria-label',this.accessibility.title);surface.append(this.renderAccessibilityAudit());
      }else if(this.workspaceView==='links'){
        surface.setAttribute('aria-label',this.linkReview.title);surface.append(this.renderLinkReview());
      }else if(this.workspaceView==='internal-anchors'){
        surface.setAttribute('aria-label',this.internalAnchorReview.title);surface.append(this.renderInternalAnchorReview());
      }else if(this.workspaceView==='readability'){
        surface.setAttribute('aria-label',this.readability.title);surface.append(this.renderReadabilityReview());
      }else if(this.workspaceView==='preflight'){
        surface.setAttribute('aria-label',this.preflight.title);surface.append(this.renderPreflightReview());
      }else if(this.workspaceView==='revision-compare'){
        surface.setAttribute('aria-label',this.revisionCompare.title);surface.append(this.renderRevisionComparison());
      }else if(this.workspaceView==='library'){
        surface.setAttribute('aria-label',this.ui.libraryTitle);
        const wrap=this.el('div','rb-workspace-screen-content');wrap.append(this.el('h1','rb-workspace-screen-title',this.ui.libraryTitle),this.el('p','rb-workspace-screen-lead',this.ui.libraryHint));
        const found=this.selectedId?this.findPath(this.state.blocks,this.selectedId):null;
        wrap.append(this.el('p','rb-library-selection',found?(this.strings.inspector||'Bloco selecionado')+': '+this.label(found.node.type):this.ui.selectBlock));
        const actions=this.el('div','rb-workspace-card-grid');
        [['image',this.ui.imageType,'image','core/image'],['file',this.ui.fileType,'file','core/file'],['audio',this.ui.audioType,'audio','core/audio'],['video',this.ui.videoType,'video','core/video_file']].forEach(([kind,label,icon,type])=>{
          const action=this.button(label,'workspace-media-open',{kind,icon});action.classList.add('rb-workspace-action-card');action.dataset.mediaKind=kind;
          const enabled=!!found&&found.node.type===type&&(this.options.pageMediaPicker===true||(kind==='image'&&(this.options.mediaPickers!==false||this.options.pageImagePicker===true))||(this.options.mediaPickers!==false&&this.options[kind+'Picker']!==false));
          action.disabled=!enabled;action.append(this.el('span','rb-workspace-action-description',enabled?this.ui.insertMedia:this.ui.selectBlock));actions.append(action);
        });
        wrap.append(actions);surface.append(wrap);
      }else if(this.workspaceView==='assistant'){
        surface.setAttribute('aria-label',this.ui.assistantTitle);
        const frame=this.el('div','rb-assistant-workspace');
        const rail=this.el('aside','rb-assistant-task-list');rail.append(this.el('h2','',this.ui.assistantTitle));
        this.ui.assistantActions.forEach((label,index)=>{const item=this.el('button','rb-assistant-task',label);item.type='button';item.disabled=true;item.append(this.el('span','rb-workspace-action-description',this.ui.unavailable));rail.append(item);});
        const main=this.el('div','rb-assistant-main');
        const badge=this.el('p','rb-workspace-warning',this.ui.assistantHint);badge.setAttribute('role','status');main.append(badge,this.icon('spark','rb-assistant-spark'),this.el('h1','rb-workspace-screen-title',this.ui.assistantTitle),this.el('p','rb-workspace-screen-lead',this.ui.assistantHint));
        const prompt=this.el('textarea','rb-assistant-prompt');prompt.placeholder=this.ui.assistantPrompt;prompt.disabled=true;prompt.setAttribute('aria-label',this.ui.assistantPrompt);main.append(prompt);
        const send=this.button(this.ui.assistant,'assistant-send',{icon:'spark'});send.disabled=true;main.append(send);frame.append(rail,main);surface.append(frame);
      }else if(this.workspaceView==='table-of-contents'){
        surface.setAttribute('aria-label',this.tableOfContents.title);surface.append(this.renderTableOfContentsPreview());
      }else if(this.workspaceView==='markdown-preview'){
        surface.setAttribute('aria-label',this.markdownExport.title);surface.append(this.renderMarkdownPreview());
      }else if(this.workspaceView==='markdown-import-preview'){
        surface.setAttribute('aria-label',this.markdownImport.title);surface.append(this.renderMarkdownImportPreview());
      }else if(this.workspaceView==='export'){
        surface.setAttribute('aria-label',this.ui.exportTitle);const wrap=this.el('div','rb-workspace-screen-content');wrap.append(this.el('h1','rb-workspace-screen-title',this.ui.exportTitle),this.el('p','rb-workspace-screen-lead',this.ui.exportHint));
        const grid=this.el('div','rb-workspace-export-grid');const available=this.el('section','rb-workspace-card rb-workspace-json-card');available.append(this.el('h2','',this.ui.jsonTitle),this.el('p','',this.ui.jsonHint),this.button(this.strings.exportDocument||'Exportar BDRE','export-document',{icon:'file'}));
        const input=this.el('input','rb-import-file-input');input.type='file';input.accept=(global.ReamurPageImporter&&global.ReamurPageImporter.formats||[]).join(',');input.dataset.importFile='1';input.classList.add('rb-sr-only');input.tabIndex=-1;input.setAttribute('aria-label',this.strings.importFile||'Importar arquivo');
        available.append(this.button(this.strings.importFile||'Importar documento','import-file',{icon:'file'}),input);grid.append(available);
        const markdownCard=this.el('section','rb-workspace-card rb-markdown-export-card');markdownCard.append(this.icon('markdown','rb-markdown-export-icon'),this.el('h2','',this.markdownExport.open),this.el('p','',this.markdownExport.hint),this.button(this.markdownExport.preview,'preview-markdown',{icon:'desktop',showLabel:true}),this.button(this.markdownExport.download,'download-markdown',{icon:'file',showLabel:true}));grid.append(markdownCard);
        const markdownImportCard=this.el('section','rb-workspace-card rb-markdown-import-card');markdownImportCard.append(this.icon('markdown','rb-markdown-import-icon'),this.el('h2','',this.markdownImport.tool),this.el('p','',this.markdownImport.hint));
        const markdownInput=this.el('input','rb-import-file-input');markdownInput.type='file';markdownInput.accept='.md,.markdown,text/markdown,text/plain';markdownInput.dataset.markdownImportFile='1';markdownInput.classList.add('rb-sr-only');markdownInput.tabIndex=-1;markdownInput.setAttribute('aria-label',this.markdownImport.open);
        markdownImportCard.append(this.button(this.markdownImport.open,'markdown-import-open',{icon:'file',showLabel:true}),markdownInput);grid.append(markdownImportCard);
        const formats=this.el('section','rb-workspace-card');formats.append(this.el('h2','',this.ui.unavailable));
        [[this.ui.pdf,'PDF'],[this.ui.docx,'Word'],[this.ui.pptx,'PowerPoint'],[this.ui.imageFormat,'Image'],[this.ui.html,'HTML']].forEach(([label,kind])=>{const row=this.el('div','rb-workspace-format-row');row.append(this.el('span','',label),this.el('span','rb-workspace-format-disabled',this.ui.unavailable));formats.append(row);});grid.append(formats);wrap.append(grid);surface.append(wrap);
      }else if(this.workspaceView==='share'){
        surface.setAttribute('aria-label',this.ui.shareTitle);const wrap=this.el('div','rb-workspace-screen-content rb-share-workspace');wrap.append(this.el('h1','rb-workspace-screen-title',this.ui.shareTitle),this.el('p','rb-workspace-screen-lead',this.ui.shareHint));
        const card=this.el('section','rb-workspace-card');card.append(this.el('h2','',this.ui.shareDisabled));
        const email=this.el('input','rb-input');email.type='email';email.disabled=true;email.placeholder=this.ui.shareDisabled;email.setAttribute('aria-label',this.ui.shareDisabled);card.append(email);
        const share=this.button(this.ui.share,'share-link',{icon:'share'});share.disabled=true;card.append(share);wrap.append(card);surface.append(wrap);
      }else{
        surface.setAttribute('aria-label',this.ui.helpTitle);const wrap=this.el('div','rb-workspace-screen-content');wrap.append(this.el('h1','rb-workspace-screen-title',this.ui.helpTitle),this.el('p','rb-workspace-screen-lead',this.ui.helpHint));
        const card=this.el('section','rb-workspace-card');card.append(this.el('h2','',this.strings.keyboardShortcutsTitle),this.el('p','rb-workspace-shortcuts',this.strings.keyboardShortcuts),this.el('p','rb-workspace-screen-lead',this.strings.draftRecoveryHint||''));wrap.append(card);surface.append(wrap);
      }
      return surface;
    }

    render() {
      this.root.replaceChildren();
      if (this.initialError) { const warn = this.el('p', 'rb-alert', this.strings.initialInvalid + ' (' + this.initialError + ').'); warn.setAttribute('role','alert'); this.root.setAttribute('aria-invalid','true'); this.root.append(warn); return; }
      this.root.setAttribute('aria-invalid','false');
      this.root.dataset.workspaceView=this.workspaceView;
      const masthead = this.el('div','rb-editor-masthead');
      const identity = this.el('div','rb-editor-identity'); identity.append(this.icon('layers','rb-brand-icon'));
      const identityText = this.el('div','rb-editor-identity-copy'); identityText.append(this.el('strong','rb-editor-brand','BDREditor'),this.el('span','rb-editor-subtitle',this.strings.editorTools));
      identity.append(identityText);
      const quickActions=this.el('div','rb-editor-quick-actions');
      if(this.workspaceView==='editor'){
        const quickFind=this.button(this.strings.findReplaceButton||'Localizar e substituir','toggle-find-replace',{icon:'search'}); quickActions.append(quickFind);
        const quickUndo=this.button(this.strings.undo,'undo'); quickUndo.dataset.history='undo'; quickActions.append(quickUndo);
        const quickRedo=this.button(this.strings.redo,'redo'); quickRedo.dataset.history='redo'; quickActions.append(quickRedo);
        if(this.fullscreen)quickActions.append(this.button(this.ui.share,'workspace-tool',{tool:'share',icon:'share'}));
        quickActions.append(this.button(this.strings.exportDocument||'Exportar BDRE','export-document',{icon:'file'}));
      }
      masthead.append(identity,quickActions); this.root.append(masthead);
      if (this.fullscreen) this.root.append(this.renderOfficeMenu());
      if (this.fullscreen && this.workspaceView==='editor' && this.options.workspaceContext) {
        const context=this.options.workspaceContext;
        const contextBar=this.el('section','rb-page-context'); contextBar.setAttribute('role','group'); contextBar.setAttribute('aria-label',context.pageLabel || 'Page context');
        const contextDetails=this.el('div','rb-page-context-details');
        const pageInfo=this.el('div','rb-page-context-item'); const currentTitle=context.pageTitleInput && typeof context.pageTitleInput.value === 'string' ? context.pageTitleInput.value : context.pageTitle; pageInfo.append(this.el('span','rb-page-context-label',context.pageLabel || 'Page'),this.el('strong','rb-page-context-value',currentTitle || context.untitledPage || 'Untitled page'));
        const languageInfo=this.el('div','rb-page-context-item'); languageInfo.append(this.el('span','rb-page-context-label',context.languageLabel || 'Language'),this.el('strong','rb-page-context-value',(context.languageName || '') + (context.languageCode ? ' · ' + context.languageCode : '')));
        contextDetails.append(pageInfo,languageInfo);
        const contextActions=this.el('div','rb-page-context-actions');
        const contextStatus=this.el('div','rb-page-context-status');
        const statusValue=context.saveStatus;
        const status=this.el('span','rb-page-context-save-state',statusValue ? statusValue.textContent : ''); status.dataset.pageContextSaveState='1'; status.dataset.state=statusValue ? (statusValue.dataset.state || 'saved') : 'saved';
        contextStatus.append(status,this.el('span','rb-page-context-save-hint',context.saveHint || 'Save using the existing page form.')); contextActions.append(contextStatus);
        if (context.canSave === true) {
          const save=this.el('button','rb-page-context-save'); save.type='submit'; save.setAttribute('aria-label',context.saveLabel || 'Save page');
          save.append(this.icon('check','rb-icon'),this.el('span','',context.saveLabel || 'Save')); contextActions.append(save);
        }
        contextBar.append(contextDetails,contextActions); this.root.append(contextBar);
      }
      let inserterMenu=null;
      if(this.workspaceView==='editor'){
      const ribbon = this.el('section','rb-ribbon'); ribbon.setAttribute('aria-label',this.strings.editorTools);
      const ribbonTabs=this.el('div','rb-ribbon-tabs'); ribbonTabs.setAttribute('role','tablist');
      [['home',this.strings.ribbonHome||'Início'],['insert',this.strings.ribbonInsert||'Inserir'],['media',this.strings.categoryMedia||'Mídia'],['layout',this.strings.categoryLayout||'Layout'],['review',this.strings.ribbonReview||'Revisar'],['view',this.strings.ribbonView||'Exibir']].forEach(([tab,label])=>{const b=this.el('button','rb-ribbon-tab',label);b.type='button';b.dataset.action='ribbon-tab';b.dataset.tab=tab;b.setAttribute('role','tab');b.setAttribute('aria-selected',String(this.activeRibbonTab===tab));b.setAttribute('aria-controls','rb-ribbon-panel-'+this.instance);b.tabIndex=this.activeRibbonTab===tab?0:-1;ribbonTabs.append(b);});
      ribbon.append(ribbonTabs,this.renderRibbonContents()); this.root.append(ribbon);
      if (this.findReplaceOpen) this.root.append(this.renderFindReplacePanel());
      inserterMenu = this.renderInserter(this.activePanel === 'elements' && this.inserterOpen); inserterMenu.id = 'rb-inserter-' + this.instance;
      if(this.activePanel!=='elements'){inserterMenu.hidden=true;this.root.append(inserterMenu);}
      }
      const editingWorkspace=this.workspaceView==='editor';
      const layout = this.el('div', 'rb-layout' + (this.fullscreen&&editingWorkspace ? ' rb-builder-layout' : '') + (editingWorkspace&&(this.activePanel || (this.fullscreen && this.inspectorOpen && !this.previewMode)) ? ' rb-has-panel' : '') + (editingWorkspace&&this.fullscreen&&this.inspectorOpen&&!this.previewMode ? ' rb-has-inspector' : ''));
      const canvas = this.workspaceView==='editor' ? this.renderCanvas() : this.renderWorkspaceScreen();
      if(this.workspaceView==='editor')canvas.id='rb-workspace-surface-'+this.instance;
      if (this.importNotices.length) { const notices=this.el('div','rb-import-notices'); notices.setAttribute('role','status'); this.importNotices.forEach((message)=>{const note=this.el('p','rb-import-notice',message);notices.append(note);}); this.root.append(notices); }
      if (this.fullscreen && this.workspaceView==='editor') {
        const sidebar=this.el('aside','rb-builder-sidebar rb-collapsible-panel');
        const workspaceRail=this.el('nav','rb-workspace-rail'); workspaceRail.setAttribute('aria-label',this.strings.workspaceRailLabel);
        const workspaceTools=[
          ['elements',this.strings.builderElements,'layers'],
          ['structure',this.strings.builderStructure,'outline'],
          ['appearance',this.strings.workspaceToolAppearance,'settings'],
          ['text',this.strings.workspaceToolText,'paragraph'],
          ['media',this.strings.categoryMedia,'image'],
          ['review',this.strings.ribbonReview,'search'],
          ['accessibility',this.accessibility.tool,'check'],
          ['links',this.linkReview.tool,'link'],
          ['internal-anchors',this.internalAnchorReview.tool,'link'],
          ['readability',this.readability.tool,'bookOpen'],
          ['preflight',this.preflight.tool,'checklist'],
          ['revision-compare',this.revisionCompare.tool,'compare'],
          ['table-of-contents',this.tableOfContents.tool,'list'],
          ['view',this.strings.ribbonView,'desktop'],
          ['home',this.ui.home,'layers'],
          ['models',this.ui.models,'layers'],
          ['library',this.ui.library,'image'],
          ['assistant',this.ui.assistant,'spark'],
          ['export',this.ui.export,'file'],
          ['share',this.ui.share,'share'],
          ['help',this.ui.help,'help']
        ];
        workspaceTools.forEach(([tool,label,icon])=>{
          if(tool==='text'||tool==='home'){const divider=this.el('span','rb-workspace-rail-divider');divider.setAttribute('aria-hidden','true');workspaceRail.append(divider);}
          const control=this.el('button','rb-workspace-tool'); control.type='button'; control.dataset.action='workspace-tool'; control.dataset.tool=tool;
          const workspaceViews={models:'models',accessibility:'accessibility',links:'links','internal-anchors':'internal-anchors',readability:'readability',preflight:'preflight','revision-compare':'revision-compare','table-of-contents':'table-of-contents',library:'library',assistant:'assistant',export:'export',share:'share',help:'help'};
          const pressed=tool==='home'?this.workspaceView==='editor'&&this.activeRibbonTab==='home':Object.prototype.hasOwnProperty.call(workspaceViews,tool)?this.workspaceView===workspaceViews[tool]:tool==='elements'?(this.activePanel==='elements'&&this.inserterOpen):tool==='structure'?this.activePanel==='structure':tool==='appearance'?(this.inspectorOpen&&!this.previewMode):tool==='text'?this.activeRibbonTab==='home':tool==='media'?this.activeRibbonTab==='media':tool==='review'?this.activeRibbonTab==='review':tool==='view'?this.activeRibbonTab==='view':false;
          control.setAttribute('aria-label',label); control.setAttribute('aria-pressed',String(pressed));
          if(tool==='elements'||tool==='appearance')control.setAttribute('aria-expanded',String(pressed));
          if(tool==='elements'||tool==='structure')control.setAttribute('aria-controls','rb-builder-panel-'+this.instance);
          else if(tool==='appearance'&&this.inspectorOpen&&!this.previewMode)control.setAttribute('aria-controls','rb-builder-inspector-'+this.instance);
          else if(['text','media','review','view'].includes(tool))control.setAttribute('aria-controls','rb-ribbon-panel-'+this.instance);
          else if(Object.prototype.hasOwnProperty.call(workspaceViews,tool)||tool==='home')control.setAttribute('aria-controls','rb-workspace-surface-'+this.instance);
          control.append(this.icon(icon,'rb-icon'),this.el('span','rb-workspace-tool-label',label)); workspaceRail.append(control);
        });
        const panelBody=this.el('div','rb-builder-panel-body'); panelBody.id='rb-builder-panel-'+this.instance; panelBody.setAttribute('role','region');
        if (this.activePanel==='elements') { panelBody.setAttribute('aria-label',this.strings.builderElements); panelBody.append(inserterMenu); }
        else if (this.activePanel==='patterns') { panelBody.setAttribute('aria-label',this.strings.workspaceToolPatterns||this.strings.patternsCategory); panelBody.append(this.renderPatternLibrary()); }
        else { panelBody.setAttribute('aria-label',this.strings.builderStructure); panelBody.append(this.renderOutline()); }
        sidebar.append(workspaceRail,panelBody); layout.append(sidebar,canvas);
        if (this.inspectorOpen && !this.previewMode) {
          const inspectorSidebar=this.el('aside','rb-builder-inspector-sidebar'); inspectorSidebar.id='rb-builder-inspector-'+this.instance; inspectorSidebar.setAttribute('role','region'); inspectorSidebar.setAttribute('aria-label',this.strings.builderSettings); inspectorSidebar.append(this.renderInspector()); layout.append(inspectorSidebar);
        }
      } else if (this.workspaceView==='editor' && this.activePanel) {
        const sidebar=this.el('aside','rb-builder-sidebar rb-collapsible-panel');
        const panelBody=this.el('div','rb-builder-panel-body'); panelBody.id='rb-builder-panel-'+this.instance; panelBody.setAttribute('role','region');
        if (this.activePanel==='elements') { panelBody.setAttribute('aria-label',this.strings.builderElements); panelBody.append(inserterMenu); }
        else if (this.activePanel==='patterns') { panelBody.setAttribute('aria-label',this.strings.workspaceToolPatterns||this.strings.patternsCategory); panelBody.append(this.renderPatternLibrary()); }
        else if (this.activePanel==='structure') { panelBody.setAttribute('aria-label',this.strings.builderStructure); panelBody.append(this.renderOutline()); }
        else { panelBody.setAttribute('aria-label',this.strings.builderSettings); panelBody.append(this.renderInspector()); }
        const close=this.button(this.strings.closePanel || 'Fechar painel','builder-panel',{panel:this.activePanel,icon:'collapse'}); close.classList.add('rb-panel-close'); panelBody.prepend(close); sidebar.append(panelBody); layout.append(sidebar,canvas);
      } else layout.append(canvas);
      this.root.append(layout);
      if (this.historyOpen) this.root.append(this.renderDraftHistory());
      const shortcutHelp=this.el('details', 'rb-keyboard-help');
      shortcutHelp.append(this.el('summary', '', this.strings.keyboardShortcutsTitle),this.el('p', 'rb-keyboard-help-text', this.strings.keyboardShortcuts));
      if(this.workspaceView==='editor')this.root.append(shortcutHelp);
      const footText = this.options.connectedSave === true
        ? (this.strings.draftRecoveryHint || 'Uma cópia temporária fica nesta aba; o conteúdo do site só é salvo ao clicar em Salvar.')
        : 'Rascunho em memória · não salva nem publica. O salvamento será conectado numa tarefa posterior.';
      const foot = this.el('p', 'rb-note', footText);
      if (this.fullscreen) { const done = this.button(this.strings.fullscreenExit,'toggle-fullscreen',{icon:'collapse'}); done.classList.add('rb-fullscreen-done'); foot.append(done); }
      this.root.append(foot);
      const live = this.el('span','rb-sr-only',this.statusMessage); live.setAttribute('role','status'); live.setAttribute('aria-live','polite'); live.dataset.editorStatus = '1'; this.root.append(live);
      this.updateHistoryButtons();
      this.updateClipboardButtons();
      this.updateRibbonState();
      if (this.inserterOpen && this.inserterQuery) this.filterInserter();
      if (this.workspaceView==='models') this.filterWorkspacePatterns();
    }

    renderBlockToolbar(node) {
      const toolbar = this.el('div', 'rb-block-toolbar'); toolbar.setAttribute('role','toolbar'); toolbar.setAttribute('aria-label', this.label(node.type) + ' · ' + this.strings.transform);
      const path = this.findPath(this.state.blocks, node.id);
      const labelledTextToolbar=['core/paragraph','core/heading','core/list','core/list-item','core/quote','core/button','core/callout','core/details','core/code','core/spacer','core/separator','core/image','core/video_embed','core/video_file','core/cover','core/file','core/audio','core/group','core/table'].includes(node.type);
      if(labelledTextToolbar)toolbar.classList.add('rb-labeled-text-toolbar');
      const drag = this.el('span','rb-drag-handle'); drag.append(this.icon('grip','rb-icon')); drag.draggable = true; drag.dataset.dragHandle = '1'; drag.title = this.strings.dragHint; drag.setAttribute('aria-hidden','true'); toolbar.append(drag,this.el('span','rb-sr-only',this.strings.dragHint));
      const up = this.button(this.strings.moveUp, 'move-up', { nodeId:node.id, showLabel:labelledTextToolbar }); up.setAttribute('aria-label', this.strings.moveUp); up.disabled = !path || path.index === 0; toolbar.append(up);
      const down = this.button(this.strings.moveDown, 'move-down', { nodeId:node.id, showLabel:labelledTextToolbar }); down.setAttribute('aria-label', this.strings.moveDown); down.disabled = !path || path.index === path.nodes.length - 1; toolbar.append(down);
      const fixedColumn = !!(path && path.parent && path.parent.type === 'core/columns' && node.type === 'core/column');
      const atParentLimit = !!(path && path.parent && ((path.parent.type === 'core/gallery' && path.parent.children.length >= 20) || (path.parent.type === 'core/cover' && path.parent.children.length >= 12)));
      const lastRequiredChild = !!(path && path.parent && ['core/gallery','core/cover'].includes(path.parent.type) && path.parent.children.length <= 1);
      const duplicate = this.button(this.strings.duplicate, 'duplicate', { nodeId:node.id, showLabel:labelledTextToolbar }); duplicate.disabled = fixedColumn || atParentLimit; toolbar.append(duplicate);
      const copy = this.button(this.strings.copyBlock, 'copy-block', { nodeId:node.id, showLabel:labelledTextToolbar }); toolbar.append(copy);
      if(node.type==='core/image'&&(this.options.mediaPickers!==false||this.options.pageImagePicker===true))toolbar.append(this.button(this.strings.imageReplace,'media',{nodeId:node.id,icon:'image',showLabel:true}));
      if(node.type==='core/cover'&&(this.options.mediaPickers!==false||this.options.pageImagePicker===true))toolbar.append(this.button(this.strings.imageReplace,'media',{nodeId:node.id,icon:'image',showLabel:true}));
      if(node.type==='core/file'&&(this.options.mediaPickers!==false||this.options.filePicker===true||this.options.pageMediaPicker===true))toolbar.append(this.button(this.strings.file.choose,'file-media',{nodeId:node.id,icon:'file',showLabel:true}));
      if(node.type==='core/audio'&&(this.options.mediaPickers!==false||this.options.audioPicker===true||this.options.pageMediaPicker===true))toolbar.append(this.button(this.strings.audioInspector.choose,'audio-media',{nodeId:node.id,icon:'audio',showLabel:true}));
      if(node.type==='core/spacer'){const index=SPACER_HEIGHTS.indexOf(Number(node.attributes.height));const less=this.button(this.strings.spacerInspector.decrease,'spacer-decrease',{nodeId:node.id,icon:'up',showLabel:true});less.disabled=index<=0;toolbar.append(less);const more=this.button(this.strings.spacerInspector.increase,'spacer-increase',{nodeId:node.id,icon:'down',showLabel:true});more.disabled=index>=SPACER_HEIGHTS.length-1;toolbar.append(more);}
      if(node.type==='core/separator'){const index=SEPARATOR_WIDTHS.indexOf(Number(node.attributes.width||100));const less=this.button(this.strings.separatorInspector.widthSmaller,'separator-width-less',{nodeId:node.id,icon:'alignLeft',showLabel:true});less.disabled=index<=0;toolbar.append(less);const more=this.button(this.strings.separatorInspector.widthLarger,'separator-width-more',{nodeId:node.id,icon:'alignRight',showLabel:true});more.disabled=index>=SEPARATOR_WIDTHS.length-1;toolbar.append(more);}
      if(node.type==='core/group')toolbar.append(this.button(this.strings.groupInspector.addChild,'group-add-child',{nodeId:node.id,icon:'plus',showLabel:true}));
      if(node.type==='core/column')toolbar.append(this.button(this.strings.columnInspector.addChild,'column-add-child',{nodeId:node.id,icon:'plus',showLabel:true}));
      if(node.type==='core/columns'){const addColumn=this.button(this.strings.columnsAdd||'Adicionar coluna','columns-add',{nodeId:node.id,icon:'plus',showLabel:true});addColumn.disabled=node.children.length>=3;toolbar.append(addColumn);const removeColumn=this.button(this.strings.columnsRemove||'Remover última coluna','columns-remove',{nodeId:node.id,icon:'minus',showLabel:true});removeColumn.disabled=node.children.length<=2;toolbar.append(removeColumn);}
      if(node.type==='core/video_file'&&(this.options.mediaPickers!==false||this.options.pageMediaPicker===true))toolbar.append(this.button(this.strings.videoFile.choose,'video-media',{nodeId:node.id,icon:'video',showLabel:true}));
      if(node.type==='core/video_embed'&&(this.options.mediaPickers!==false||this.options.pageMediaPicker===true))toolbar.append(this.button(this.strings.videoFile.choose,'video-media',{nodeId:node.id,icon:'video',showLabel:true}));
      const customize=this.button(this.strings.customizeTab||'Personalizar','customize-block',{nodeId:node.id,icon:'settings',showLabel:labelledTextToolbar});toolbar.append(customize);
      if (TRANSFORMABLE_TEXT.has(node.type)) {
        const wrap = this.el('label','rb-transform-wrap'); wrap.append(this.icon('transform','rb-transform-icon'),this.el('span',labelledTextToolbar?'rb-transform-visible-label':'rb-sr-only',this.strings.transform));
        const select = this.el('select','rb-input rb-transform'); select.dataset.transformNode = node.id;
        const empty = this.el('option','',this.strings.transform); empty.value = ''; empty.selected = true; select.append(empty);
        [...TRANSFORMABLE_TEXT].filter((type) => this.canTransformNode(node,type)).forEach((type) => { const option = this.el('option','',this.label(type)); option.value = type; select.append(option); });
        select.setAttribute('aria-label', this.strings.transform); select.title = this.strings.transformHint; wrap.append(select); toolbar.append(wrap);
      } else {
        const hint = this.el('span','rb-transform-hint',this.strings.transformHint); toolbar.append(hint);
      }
      if (this.patternConfig.canModify) { const savePattern = this.button(this.strings.saveAsPattern || 'Salvar como padrão','save-selected-pattern',{nodeId:node.id,showLabel:labelledTextToolbar}); savePattern.disabled = this.containsCustomJs(node); toolbar.append(savePattern); }
      const remove = this.button(this.strings.remove,'delete',{nodeId:node.id,showLabel:labelledTextToolbar}); remove.disabled = fixedColumn || lastRequiredChild; toolbar.append(remove);
      const add = this.button(this.strings.addAfter, 'toggle-inserter', { afterId:node.id, showLabel:labelledTextToolbar }); if(!labelledTextToolbar){add.classList.add('rb-button-icon-only');add.querySelector('.rb-button-label').classList.add('rb-sr-only');} add.setAttribute('aria-label', this.strings.addAfter); add.setAttribute('aria-expanded', String(this.inserterOpen && this.insertAfterId === node.id)); add.setAttribute('aria-controls','rb-inserter-' + this.instance); toolbar.append(add);
      return toolbar;
    }

    renderThreeJsInlineEditor(node) {
      const expanded=this.openThreeJsEditor===node.id;
      const wrap=this.el('section','rb-threejs-inline-wrap');
      const hasScript=!!String(node.attributes.custom_js||'').trim();
      const toggle=this.button(expanded?this.strings.threejs.closeInline:(hasScript?this.strings.threejs.editInline:this.strings.threejs.addScript),'threejs-toggle-editor',{nodeId:node.id});
      toggle.setAttribute('aria-expanded',String(expanded)); toggle.setAttribute('aria-controls','rb-threejs-inline-'+this.instance+'-'+node.id); toggle.classList.add('rb-threejs-inline-toggle'); wrap.append(toggle);
      if (!expanded) return wrap;
      const panel=this.el('div','rb-threejs-inline-editor'); panel.id='rb-threejs-inline-'+this.instance+'-'+node.id; panel.setAttribute('role','region'); panel.setAttribute('aria-label',this.strings.threejs.editInline);
      panel.append(this.renderThreeJsControls(node));
      const label=this.el('label','rb-field'); label.append(this.el('span','',this.strings.threejs.scriptLabel));
      const code=this.el('textarea','rb-input rb-threejs-script-input'); code.value=node.attributes.custom_js||''; code.maxLength=8192; code.spellcheck=false; code.autocomplete='off'; code.dataset.attribute='custom_js'; code.dataset.nodeId=node.id; code.setAttribute('aria-label',this.strings.threejs.scriptLabel); code.placeholder=this.strings.threejs.scriptPlaceholder; label.append(code); panel.append(label);
      const remove=this.button(this.strings.threejs.deleteScript,'threejs-delete-script',{nodeId:node.id}); remove.disabled=!(node.attributes.custom_js||'').length; panel.append(remove);
      panel.append(this.el('p','rb-help-text',this.strings.threejs.scriptHint)); wrap.append(panel); return wrap;
    }

    resolveAdminMediaUrl(src) {
      if(typeof src!=='string')return '';
      let baseValue=this.strings.media&&this.strings.media.catalogBase;
      if(typeof baseValue!=='string'||!baseValue){
        try{
          const current=new URL(global.location.href);const adminIndex=current.pathname.indexOf('/admin/');
          if(adminIndex>=0){current.pathname=current.pathname.slice(0,adminIndex+1)+'catalog/';current.search='';current.hash='';baseValue=current.href;}
        }catch(_){}
      }
      if(typeof baseValue!=='string'||!baseValue)return src;
      try{
        const base=new URL(baseValue,global.location.href);
        if(base.origin!==global.location.origin)return src;
        if(!base.pathname.endsWith('/'))base.pathname+='/';base.search='';base.hash='';
        const normalizedSrc=src.startsWith('/')?src.slice(1):src;
        const resolved=new URL(normalizedSrc,base);
        return resolved.origin===base.origin?resolved.href:src;
      }catch(_){return src;}
    }

    renderAdminImagePreview(node, className) {
      const preview = this.el('figure',className || 'rb-admin-image-preview');
      if (validLocalImage(node.attributes.src)) {
        const image = this.el('img',''); image.src = this.resolveAdminMediaUrl(node.attributes.src); image.alt = node.attributes.alt || ''; preview.append(image);
      } else preview.append(this.el('p','rb-admin-media-empty',this.strings.media.placeholder));
      if (node.attributes.caption) preview.append(this.el('figcaption','',node.attributes.caption));
      return preview;
    }

    renderAdminGalleryPreview(node) {
      const gallery=this.el('div','rb-admin-gallery-preview');gallery.style.gridTemplateColumns='repeat('+Math.max(2,Math.min(4,Number(node.attributes.columns)||2))+',minmax(0,1fr))';
      (node.children||[]).forEach((image)=>gallery.append(this.applyPreviewAppearance(this.renderAdminImagePreview(image,'rb-admin-gallery-item'),image)));
      return gallery;
    }

    applySeparatorPreview(element,attributes) {
      const width=SEPARATOR_WIDTHS.includes(Number(attributes.width))?Number(attributes.width):100;
      const thickness=SEPARATOR_THICKNESSES.includes(Number(attributes.thickness))?Number(attributes.thickness):1;
      const style=SEPARATOR_STYLES.includes(attributes.style)?attributes.style:'solid';
      const align=['left','center','right'].includes(attributes.align)?attributes.align:'center';
      element.style.width=width+'%';element.style.border='0';element.style.borderTop=thickness+'px '+style+' #7d8797';element.style.marginLeft=align==='right'?'auto':'0';element.style.marginRight=align==='left'?'auto':'0';
      if(align==='center'){element.style.marginLeft='auto';element.style.marginRight='auto';}
      return element;
    }

    renderAdminVideoPreview(url) {
      const preview = this.el('div','rb-video-admin-preview'); preview.setAttribute('role','img'); preview.setAttribute('aria-label',this.strings.media.videoPreview);
      preview.append(this.el('span','rb-video-admin-play','▶'));
      let provider = this.strings.media.videoPreviewHint;
      if (validEmbed(url)) { try { const host = new URL(url).hostname.toLowerCase(); provider = host.includes('vimeo') ? 'Vimeo' : 'YouTube'; } catch (_) {} }
      preview.append(this.el('span','rb-video-admin-label',this.strings.media.videoPreview + ' · ' + provider));
      return preview;
    }

    renderNode(node, isRoot) {
      const typeClass = node.type.replace(/[^a-z0-9-]/gi, '-');
      const card = this.el('section', 'rb-block rb-type-' + typeClass + (node.id === this.selectedId ? ' is-selected' : ''));
      if (node.type === 'core/columns') card.classList.add(node.attributes.count === 3 ? 'rb-columns-3' : 'rb-columns-2');
      if (node.type === 'core/callout') { card.dataset.calloutTone = node.attributes.tone; card.setAttribute('role','group'); card.setAttribute('aria-label',this.label(node.type)); }
      let textPreview = card, appearanceTarget=null;
      if (node.type === 'core/details') { const details = this.el('details','rb-details-admin-preview'); details.open = true; details.append(this.el('summary','rb-details-admin-summary',node.attributes.summary)); card.append(details); textPreview = details; appearanceTarget=details; }
      if (node.type === 'core/spacer') card.dataset.spacerHeight = String(node.attributes.height);
      if (node.type === 'core/gallery') card.classList.add('rb-gallery-preview','rb-gallery-preview--columns-' + node.attributes.columns);
      card.dataset.nodeId = node.id; card.tabIndex = -1; if (isRoot) card.dataset.rootNode = '1';
      const head = this.el('div', 'rb-block-head');
      head.append(this.icon(TYPE_ICONS[node.type] || 'layers','rb-block-type-icon'),this.el('span', 'rb-block-title', this.label(node.type)));
      card.append(head, this.renderBlockToolbar(node));
      if (TEXT_TYPES.has(node.type)) {
        const editor = this.el('div', 'rb-content' + (node.type === 'core/button' ? ' rb-content-button-preview' : '')); editor.contentEditable = 'true'; editor.dataset.editContent = node.id;
        editor.setAttribute('role', 'textbox'); editor.setAttribute('aria-multiline', 'true');
        editor.setAttribute('aria-label', this.strings.textLabel + ': ' + this.label(node.type)); editor.setAttribute('data-placeholder', this.strings.placeholder || '');
        buildInlineDom(editor, node.content);if(node.type==='core/paragraph')editor.style.textAlign=(node.attributes.appearance&&node.attributes.appearance.text_align)||node.attributes.align||'left';textPreview.append(editor); appearanceTarget=editor;
      } else if (node.type === 'core/code') {
        const code = this.el('textarea','rb-code-editor'); code.value = node.content; code.maxLength = 131072;
        code.dataset.editCode = node.id; code.setAttribute('aria-label',this.strings.preformatted.label);
        card.append(code); appearanceTarget=code;
      } else if (node.type === 'core/spacer') {
        appearanceTarget=this.el('div','rb-spacer-preview');appearanceTarget.dataset.heightLabel=String(node.attributes.height)+' px';appearanceTarget.setAttribute('aria-hidden','true');card.append(appearanceTarget);
      } else if (node.type === 'core/separator') {
        appearanceTarget=this.el('hr','rb-separator-admin-preview');this.applySeparatorPreview(appearanceTarget,node.attributes);card.append(appearanceTarget);
      } else if (node.type === 'core/image') {
        appearanceTarget=this.renderAdminImagePreview(node); card.append(appearanceTarget);
      } else if (node.type === 'core/file') {
        appearanceTarget=this.el('p', 'rb-preview-value', node.content.map((run) => run.text).join('') + (node.attributes.src ? ' · ' + node.attributes.src.split('/').pop() : '')); card.append(appearanceTarget);
      } else if (node.type === 'core/audio') {
        const audio = this.el('audio','rb-audio-admin-preview'); audio.controls = true; audio.preload = 'none';
        const label = node.content.map((run) => run.text).join('').trim(); audio.setAttribute('aria-label', label || this.label('core/audio'));
        if (validLocalAudio(node.attributes.src, this.allowPageMedia) && this.strings.audio.catalogBase) {
          try { const base = String(this.strings.audio.catalogBase); const baseUrl = new URL(base.endsWith('/') ? base : base + '/', global.location.href); if (baseUrl.origin !== global.location.origin) throw new Error('audio_preview_origin_invalid'); audio.src = new URL(node.attributes.src, baseUrl).href; } catch (_) {}
        }
        const audioFigure=this.el('figure','rb-audio-edit-preview');audioFigure.append(audio,this.el('figcaption','',label+(node.attributes.src?' · '+node.attributes.src.split('/').pop():'')));card.append(audioFigure);appearanceTarget=audioFigure;
      } else if (node.type === 'core/threejs_thumb') {
        const preview = this.el('div','rb-threejs-admin-preview');
        const stage = this.el('div','rms-block-threejs-thumb__stage'); stage.setAttribute('data-threejs-thumb','');
        const label = String(node.attributes.label || this.label(node.type));
        stage.setAttribute('role','img'); stage.setAttribute('aria-label',label);
        try { JSON.parse(String(node.attributes.scene_json || '')); stage.setAttribute('data-scene',String(node.attributes.scene_json)); }
        catch (_) { stage.setAttribute('aria-label',this.strings.threejs.invalidScene || label); }
        const fallback=this.el('span','rms-block-threejs-thumb__fallback',label); stage.append(fallback); preview.append(stage);
        const hint=this.el('small','',this.strings.threejs.previewHint); if(hint.textContent)preview.append(hint);
        card.append(preview,this.renderThreeJsInlineEditor(node)); appearanceTarget=preview;
      } else if (node.type === 'core/video_file') {
        const video = this.el('video','rb-video-file-admin-preview'); video.controls = node.attributes.controls!==false; video.preload = 'none'; video.playsInline = true; video.loop=!!node.attributes.loop; video.muted=!!node.attributes.muted; video.autoplay=!!node.attributes.autoplay;
        if(validLocalVideo(node.attributes.src,this.allowPageMedia))video.src=this.resolveAdminMediaUrl(node.attributes.src);if(node.attributes.title)video.title=node.attributes.title;
        const videoFigure=this.el('figure','rb-video-edit-preview');videoFigure.dataset.videoAlign=node.attributes.align||'center';videoFigure.dataset.videoSize=node.attributes.size||'large';videoFigure.dataset.videoMobileSize=node.attributes.mobile_size||'full';
        if(node.attributes.caption)videoFigure.append(this.el('figcaption','',node.attributes.caption));else if(node.attributes.src)videoFigure.append(this.el('figcaption','',node.attributes.src.split('/').pop()));
        videoFigure.prepend(video);card.append(videoFigure);appearanceTarget=videoFigure;
      } else if (node.type === 'core/video_embed') {
        if(validLocalVideo(node.attributes.src,this.allowPageMedia)){
          const localVideo=this.el('video','rb-video-file-admin-preview');localVideo.controls=true;localVideo.preload='none';localVideo.playsInline=true;localVideo.src=this.resolveAdminMediaUrl(node.attributes.src);
          const localFigure=this.el('figure','rb-video-edit-preview');localFigure.dataset.videoAlign='center';localFigure.dataset.videoSize='large';localFigure.dataset.videoMobileSize='full';localFigure.append(localVideo,this.el('figcaption','',node.attributes.src.split('/').pop()));appearanceTarget=localFigure;card.append(localFigure);
        } else { appearanceTarget=this.renderAdminVideoPreview(node.attributes.url); card.append(appearanceTarget); }
      }
      else if (node.type === 'core/gallery') { appearanceTarget=this.renderAdminGalleryPreview(node); card.append(appearanceTarget); card.append(this.el('p','rb-preview-value',this.label('core/gallery') + ' · ' + node.children.length)); }
      else if (node.type === 'core/cover') { appearanceTarget=this.renderAdminImagePreview(node,'rb-cover-admin-preview'); card.append(appearanceTarget); }
      else if (node.type === 'core/columns') { appearanceTarget=this.el('p', 'rb-preview-value', node.attributes.count + ' ' + this.strings.columnCount); card.append(appearanceTarget); }
      else if (node.type === 'core/table') {
        const wrap = this.el('div','rb-table-preview'); const table = this.el('table',''); appearanceTarget=wrap;
        const body = this.el('tbody','');
        node.children.forEach((row,rowIndex) => {
          const tr = this.el('tr','');this.applyPreviewAppearance(tr,row);
          row.children.forEach((cell,colIndex) => {
            const isHeader=tableCellIsHeader(node,rowIndex,cell);const td=this.el(isHeader?'th':'td','');
            if(isHeader)td.setAttribute('scope','col');td.rowSpan=cell.attributes.rowspan||1;td.colSpan=cell.attributes.colspan||1;this.applyPreviewAppearance(td,cell);
            const editor = this.el('div','rb-table-cell-content'); editor.contentEditable = 'true'; editor.dataset.editContent = cell.id; editor.setAttribute('role','textbox'); editor.setAttribute('aria-multiline','true');
            editor.setAttribute('aria-label',(this.strings.table.cellLabel || 'Cell %1$d, %2$d').replace('%1$d',String(rowIndex+1)).replace('%2$d',String(colIndex+1)));
            buildInlineDom(editor,cell.content); td.append(editor); tr.append(td);
          });
          body.append(tr);
        });
        if (node.attributes.header && body.firstElementChild) { const head = this.el('thead',''); head.append(body.firstElementChild); table.append(head); }
        if (body.childNodes.length) table.append(body); wrap.append(table); card.append(wrap);
      }
      if (appearanceTarget) this.applyPreviewAppearance(appearanceTarget,node);
      if (node.type !== 'core/table') node.children.forEach((child) => card.append(this.renderNode(child, false)));
      return card;
    }

    navigationLabel(node) {
      if (node.type === 'core/group' && typeof node.attributes.outline_label === 'string' && node.attributes.outline_label.trim()) return node.attributes.outline_label.trim();
      if (node.type === 'core/table-cell') return this.strings.navigationCell;
      if (node.type === 'core/table-row') return this.strings.navigationRow;
      return this.label(node.type);
    }

    renderBreadcrumbs(id) {
      const chain = findNodeChain(this.state.blocks, id);
      const nav = this.el('nav', 'rb-breadcrumbs'); nav.setAttribute('aria-label', this.strings.navigationPath);
      if (!chain.length) return nav;
      const list = this.el('ol', 'rb-breadcrumb-list');
      chain.forEach((node, index) => {
        const item = this.el('li', 'rb-breadcrumb-item');
        if (index === chain.length - 1) {
          const current = this.el('span', 'rb-breadcrumb-current', this.navigationLabel(node));
          current.setAttribute('aria-current', 'location'); item.append(current);
        } else {
          const ancestor = this.button(this.navigationLabel(node), 'outline-select', { nodeId:node.id });
          ancestor.classList.add('rb-breadcrumb-button'); item.append(ancestor);
        }
        list.append(item);
      });
      nav.append(list); return nav;
    }

    renderOutline() {
      const nav = this.el('nav','rb-outline'); nav.setAttribute('aria-label',this.strings.outline); const outlineTitle=this.el('h3','rb-inspector-title'); outlineTitle.append(this.icon('outline','rb-icon'),this.el('span','',this.strings.outline)); nav.append(outlineTitle);
      nav.append(this.el('p','rb-outline-keyboard-hint',this.strings.outlineKeyboardHint));
      if (!this.state.blocks.length) { nav.append(this.el('p','rb-muted',this.strings.outlineEmpty)); return nav; }
      const list = this.el('ol','rb-outline-list');
      const append = (nodes,parent,depth) => nodes.forEach((node) => {
        if (['core/table-row','core/table-cell'].includes(node.type)) return;
        const item = this.el('li','rb-outline-item');
        const outlineName = this.navigationLabel(node);
        const button = this.button(outlineName,'outline-select',{nodeId:node.id});
        button.classList.add('rb-outline-button'); button.style.setProperty('--rb-outline-depth', String(depth));
        if (node.id === this.selectedId) button.setAttribute('aria-current','location'); item.append(button);
        if (node.children && node.children.length) { const nested = this.el('ol','rb-outline-list'); append(node.children,nested,depth+1); item.append(nested); }
        parent.append(item);
      });
      append(this.state.blocks,list,0); nav.append(list); return nav;
    }

    updateThreeJsEntry(id, operation, index, key, value) {
      this.updateNode(id, (node) => {
        if (node.type !== 'core/threejs_thumb') return;
        let scene; try { scene = JSON.parse(node.attributes.scene_json); } catch (_) { return; }
        if (!validThreeJsSceneJson(node.attributes.scene_json)) return;
        if (operation === 'add-object' && scene.objects.length < 12) { const count=scene.objects.length; scene.objects.push({type:'box',color:'#7dd3fc',position:[((count%3)-1)*1.05,(count%2)*0.28,(Math.floor(count/3)-0.5)*1.05],rotation:[0,0,0],scale:0.8,animation:'none',roughness:0.45,metalness:0.1,wireframe:false}); }
        else if (operation === 'add-light' && scene.lights.length < 4) scene.lights.push({type:'point',color:'#ffffff',intensity:1.5,position:[2,3,4]});
        else if (operation === 'remove-object' && scene.objects.length > 1 && Number.isInteger(index) && index >= 0 && index < scene.objects.length) scene.objects.splice(index,1);
        else if (operation === 'remove-light' && scene.lights.length > 1 && Number.isInteger(index) && index >= 0 && index < scene.lights.length) scene.lights.splice(index,1);
        else if (operation === 'object' && scene.objects[index] && ['type','color','animation'].includes(key)) scene.objects[index][key] = value;
        else if (operation === 'light' && scene.lights[index] && ['type','color','intensity'].includes(key)) {
          const light = scene.lights[index];
          if (key === 'intensity') light.intensity = Number(value);
          else light[key] = value;
          if (key === 'type') { if (value === 'ambient') delete light.position; else if (!light.position) light.position = [2,3,4]; }
        } else return;
        const json = JSON.stringify(scene);
        if (validThreeJsSceneJson(json)) node.attributes.scene_json = json;
      });
    }

    renderThreeJsControls(node) {
      const scene = JSON.parse(node.attributes.scene_json);
      const panel = this.el('section','rb-threejs-scene-controls');
      const heading = this.el('h4','',this.strings.threejs.objects); panel.append(heading);
      const addObject = this.button(this.strings.threejs.addShape,'threejs-add-object'); addObject.dataset.nodeId=node.id; addObject.disabled=scene.objects.length>=12; panel.append(addObject);
      scene.objects.forEach((object,index) => {
        const row=this.el('div','rb-threejs-entry');
        const type=this.el('select','rb-input'); type.setAttribute('aria-label',this.strings.threejs.objects+' '+(index+1));
        [['box','Box'],['sphere','Sphere'],['torus','Torus'],['cone','Cone'],['cylinder','Cylinder'],['icosahedron','Icosahedron'],['torusKnot','Torus knot']].forEach(([value,label])=>{const option=this.el('option','',label);option.value=value;type.append(option);});
        type.value=object.type; type.dataset.threejsKind='object'; type.dataset.nodeId=node.id; type.dataset.index=String(index); type.dataset.key='type'; row.append(type);
        const color=this.el('input','rb-threejs-color'); color.type='color'; color.value=object.color; color.setAttribute('aria-label',this.strings.threejs.color); color.dataset.threejsKind='object'; color.dataset.nodeId=node.id; color.dataset.index=String(index); color.dataset.key='color'; row.append(color);
        const animation=this.el('select','rb-input'); animation.setAttribute('aria-label',this.strings.threejs.animation); [['none','None'],['spin','Spin'],['bob','Bob'],['pulse','Pulse']].forEach(([value,label])=>{const option=this.el('option','',label);option.value=value;animation.append(option);});
        animation.value=object.animation; animation.dataset.threejsKind='object'; animation.dataset.nodeId=node.id; animation.dataset.index=String(index); animation.dataset.key='animation'; row.append(animation);
        const remove=this.button(this.strings.threejs.remove,'threejs-remove-object'); remove.dataset.nodeId=node.id; remove.dataset.index=String(index); remove.disabled=scene.objects.length<=1; row.append(remove); panel.append(row);
      });
      const lightHeading=this.el('h4','',this.strings.threejs.lights); panel.append(lightHeading);
      const addLight=this.button(this.strings.threejs.addLight,'threejs-add-light'); addLight.dataset.nodeId=node.id; addLight.disabled=scene.lights.length>=4; panel.append(addLight);
      scene.lights.forEach((light,index)=>{
        const row=this.el('div','rb-threejs-entry'); const type=this.el('select','rb-input'); type.setAttribute('aria-label',this.strings.threejs.lights+' '+(index+1));
        [['ambient','Ambient'],['directional','Directional'],['point','Point']].forEach(([value,label])=>{const option=this.el('option','',label);option.value=value;type.append(option);});
        type.value=light.type; type.dataset.threejsKind='light'; type.dataset.nodeId=node.id; type.dataset.index=String(index); type.dataset.key='type'; row.append(type);
        const color=this.el('input','rb-threejs-color'); color.type='color'; color.value=light.color; color.setAttribute('aria-label',this.strings.threejs.color); color.dataset.threejsKind='light'; color.dataset.nodeId=node.id; color.dataset.index=String(index); color.dataset.key='color'; row.append(color);
        const intensity=this.el('input','rb-input'); intensity.type='number'; intensity.min='0'; intensity.max='5'; intensity.step='0.1'; intensity.value=String(light.intensity); intensity.setAttribute('aria-label',this.strings.threejs.intensity); intensity.dataset.threejsKind='light'; intensity.dataset.nodeId=node.id; intensity.dataset.index=String(index); intensity.dataset.key='intensity'; row.append(intensity);
        const remove=this.button(this.strings.threejs.remove,'threejs-remove-light'); remove.dataset.nodeId=node.id; remove.dataset.index=String(index); remove.disabled=scene.lights.length<=1; row.append(remove); panel.append(row);
      });
      return panel;
    }

    renderAppearancePanel(node) {
      const panel=this.el('div','rb-appearance-panel');panel.setAttribute('role','tabpanel');
      const current=node.attributes.appearance||{};
      if(node.type==='core/paragraph')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.paragraphStyleHeading));
      else if(node.type==='core/heading')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.headingStyleHeading));
      else if(node.type==='core/list')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.listStyleHeading));
      else if(node.type==='core/list-item')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.listItemStyleHeading));
      else if(node.type==='core/quote')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.quoteStyleHeading));
      else if(node.type==='core/button')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.buttonStyleHeading));
      else if(node.type==='core/callout')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.calloutStyleHeading));
      else if(node.type==='core/details')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.detailsStyleHeading));
      else if(node.type==='core/code')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.preformattedStyleHeading));
      else if(node.type==='core/image')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.imageStyleHeading));
      else if(node.type==='core/table')panel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.tableStyleHeading));
      const addColor=(label,key,fallback)=>{const wrap=this.el('label','rb-field rb-appearance-field');wrap.append(this.el('span','',label));const input=this.el('input','rb-input rb-appearance-color');input.type='color';input.value=current[key]||fallback;input.dataset.appearance=key;input.dataset.nodeId=node.id;wrap.append(input);panel.append(wrap);};
      const addNumber=(label,key,min,max,fallback)=>{const wrap=this.el('label','rb-field rb-appearance-field');wrap.append(this.el('span','',label));const input=this.el('input','rb-input');input.type='number';input.min=String(min);input.max=String(max);input.step='1';input.value=String(current[key]===undefined?fallback:current[key]);input.dataset.appearance=key;input.dataset.nodeId=node.id;wrap.append(input);panel.append(wrap);};
      addColor(this.strings.customTextColor||'Text color','text_color','#202124');
      addColor(this.strings.customBackground||'Background color','background_color','#ffffff');
      addColor(this.strings.customBorderColor||'Border color','border_color','#d0d5dd');
      const borderWrap=this.el('label','rb-field rb-appearance-field');borderWrap.append(this.el('span','',this.strings.customBorderStyle||'Border style'));
      const borderStyle=this.el('select','rb-input');[["solid",this.strings.customBorderSolid||'Solid'],["dashed",this.strings.customBorderDashed||'Dashed'],["dotted",this.strings.customBorderDotted||'Dotted']].forEach(([value,label])=>{const option=this.el('option','',label);option.value=value;borderStyle.append(option);});borderStyle.value=current.border_style||'solid';borderStyle.dataset.appearance='border_style';borderStyle.dataset.nodeId=node.id;borderWrap.append(borderStyle);panel.append(borderWrap);
      addNumber(this.strings.customBorderWidth||'Border width (px)','border_width',0,8,0);
      addNumber(this.strings.customCornerRadius||'Corner radius (px)','border_radius',0,32,0);
      addNumber(this.strings.customPadding||'Inner spacing (px)','padding',0,64,0);
      addNumber(this.strings.customMargin||'Outer spacing (px)','margin',0,48,0);
      addNumber(this.strings.customFontSize||'Font size (px)','font_size',12,48,16);
      if(node.type!=='core/paragraph'){
        const alignWrap=this.el('label','rb-field rb-appearance-field');alignWrap.append(this.el('span','',this.strings.customTextAlign||'Text alignment'));
        const align=this.el('select','rb-input');[['',this.strings.customInherit||'Theme default'],['left',this.strings.alignLeft||this.strings.media.alignments.left],['center',this.strings.alignCenter||this.strings.media.alignments.center],['right',this.strings.alignRight||this.strings.media.alignments.right]].forEach(([value,label])=>{const option=this.el('option','',label);option.value=value;align.append(option);});align.value=current.text_align||'';align.dataset.appearance='text_align';align.dataset.nodeId=node.id;alignWrap.append(align);panel.append(alignWrap);
      }
      panel.append(this.el('p','rb-help-text',this.strings.customAppearanceHint||'These safe style controls apply to the selected block and are included in the saved document.'));
      const reset=this.button(this.strings.customReset||'Reset customization','reset-appearance',{nodeId:node.id});reset.disabled=!Object.keys(current).length;panel.append(reset);
      return panel;
    }

    renderInspector() {
      const aside = this.el('aside', 'rb-inspector'); aside.setAttribute('aria-label', this.strings.inspector);
      const heading = this.el('h3', 'rb-inspector-title'); heading.append(this.icon('settings','rb-icon'),this.el('span','',this.strings.inspector)); aside.append(heading);
      const found = this.selectedId ? this.findPath(this.state.blocks, this.selectedId) : null;
      if (!found) { aside.append(this.el('p', 'rb-muted', this.strings.inspectorEmpty)); return aside; }
      aside.append(this.renderBreadcrumbs(found.node.id));
      const tabList=this.el('div','rb-inspector-tabs');tabList.setAttribute('role','tablist');tabList.setAttribute('aria-label',this.strings.inspectorTabs||'Block settings');
      const panelPrefix='rb-inspector-'+this.instance+'-'+found.node.id;
      [['content',this.strings.customContentTab||'Content'],['customize',this.strings.customizeTab||'Personalizar']].forEach(([tab,label])=>{const button=this.el('button','rb-inspector-tab',label);button.type='button';button.dataset.action='inspector-tab';button.dataset.inspectorTab=tab;button.setAttribute('role','tab');button.setAttribute('aria-selected',String(this.inspectorTab===tab));button.setAttribute('aria-controls',panelPrefix+'-'+tab);button.tabIndex=this.inspectorTab===tab?0:-1;tabList.append(button);});
      aside.append(tabList);
      const contentPanel=this.el('div','rb-inspector-tabpanel');contentPanel.id=panelPrefix+'-content';contentPanel.setAttribute('role','tabpanel');contentPanel.setAttribute('aria-label',this.strings.customContentTab||'Content');contentPanel.hidden=this.inspectorTab!=='content';
      const appearancePanel=this.renderAppearancePanel(found.node);appearancePanel.id=panelPrefix+'-customize';appearancePanel.setAttribute('aria-label',this.strings.customizeTab||'Personalizar');appearancePanel.hidden=this.inspectorTab!=='customize';
      const addField = (label, key, value, type, choices) => {
        const wrap = this.el('label', 'rb-field'); wrap.append(this.el('span', '', label));
        let input;
        if (choices) { input = this.el('select', 'rb-input'); choices.forEach(([val, text]) => { const option = this.el('option', '', text); option.value = val; input.append(option); }); input.value = String(value); }
        else if (type === 'textarea') { input = this.el('textarea','rb-input'); input.value = value == null ? '' : String(value); input.maxLength = key === 'custom_js' ? 8192 : (key === 'scene_json' ? 16384 : 4096); if (key === 'custom_js') { input.classList.add('rb-threejs-script-input'); input.spellcheck = false; input.autocomplete = 'off'; } }
        else { input = this.el('input', 'rb-input'); input.type = type || 'text'; input.value = value == null ? '' : String(value); if (['alt','title','caption'].includes(key)) input.maxLength = 4096; if (key === 'outline_label') input.maxLength = 160; if (key === 'summary') input.maxLength = 512; if (key === 'url') input.maxLength = 2048; }
        input.dataset.attribute = key; input.dataset.nodeId = found.node.id; wrap.append(input); contentPanel.append(wrap);
      };
      const n = found.node;
      if(['core/paragraph','core/heading','core/list-item','core/quote','core/button','core/callout','core/details'].includes(n.type)){
        const isParagraph=n.type==='core/paragraph', isHeading=n.type==='core/heading', isQuote=n.type==='core/quote', isButton=n.type==='core/button', isCallout=n.type==='core/callout', isDetails=n.type==='core/details';
        const title=isParagraph?this.strings.paragraphTextTitle:(isHeading?this.strings.headingTextTitle:(isQuote?this.strings.quoteTextTitle:(isButton?this.strings.buttonTextTitle:(isCallout?this.strings.calloutTextTitle:(isDetails?this.strings.detailsTextTitle:this.strings.listItemTextTitle)))));
        const hint=isParagraph?this.strings.paragraphTextHint:(isHeading?this.strings.headingTextHint:(isQuote?this.strings.quoteTextHint:(isButton?this.strings.buttonTextHint:(isCallout?this.strings.calloutTextHint:(isDetails?this.strings.detailsTextHint:this.strings.listItemTextHint)))));
        const sectionClass=isParagraph?'rb-paragraph-inspector-section':(isHeading?'rb-heading-inspector-section':(isQuote?'rb-quote-inspector-section':(isButton?'rb-button-inspector-section':(isCallout?'rb-callout-inspector-section':(isDetails?'rb-details-inspector-section':'rb-list-item-inspector-section')))));
        const textSection=this.el('section','rb-text-block-inspector-section '+sectionClass);
        textSection.append(this.el('h4','rb-text-block-inspector-heading',title));
        const textEditor=this.el('div','rb-content rb-inspector-text-block-content');textEditor.contentEditable='true';textEditor.dataset.editContent=n.id;textEditor.dataset.inspectorContent='1';textEditor.setAttribute('role','textbox');textEditor.setAttribute('aria-multiline','true');textEditor.setAttribute('aria-label',title);textEditor.setAttribute('data-placeholder',this.strings.placeholder||'');buildInlineDom(textEditor,n.content);
        textSection.append(textEditor,this.el('p','rb-help-text',hint));contentPanel.append(textSection);
        if(isParagraph)addField(this.strings.paragraphAlignment,'align',n.attributes.align||(n.attributes.appearance&&n.attributes.appearance.text_align)||'left','text',[['left',this.strings.media.alignments.left],['center',this.strings.media.alignments.center],['right',this.strings.media.alignments.right]]);
        else if(isHeading)addField(this.strings.headingLevelLabel,'level',n.attributes.level,'text',this.strings.headingLevels);
        else if(found.parent&&found.parent.type==='core/list'){const itemActions=this.el('div','rb-list-item-inspector-actions');const removeItem=this.button(this.strings.listRemoveItem,'delete',{nodeId:n.id,showLabel:true});removeItem.disabled=found.parent.children.length<=1;itemActions.append(removeItem);contentPanel.append(itemActions);}
      }
      if (n.type === 'core/code') {
        const codeSection=this.el('section','rb-code-inspector-section');
        const codeTitle=this.strings.preformattedTextTitle;
        codeSection.append(this.el('h4','rb-text-block-inspector-heading',codeTitle));
        const codeEditor=this.el('textarea','rb-code-editor rb-inspector-code-editor');codeEditor.value=n.content||'';codeEditor.maxLength=131072;codeEditor.dataset.editCode=n.id;codeEditor.setAttribute('aria-label',codeTitle);codeEditor.setAttribute('spellcheck','false');
        codeSection.append(codeEditor,this.el('p','rb-help-text',this.strings.preformattedTextHint));contentPanel.append(codeSection);
      }
      if (n.type === 'core/list') {
        addField(this.strings.listType, 'ordered', n.attributes.ordered ? 'true' : 'false', 'text', [['false',this.strings.listUnordered],['true',this.strings.listOrdered]]);
        const items=this.el('section','rb-list-inspector-section');items.append(this.el('h4','rb-text-block-inspector-heading',this.strings.listItemsTitle));
        const itemList=this.el(n.attributes.ordered?'ol':'ul','rb-list-inspector-items');
        n.children.filter((child)=>child.type==='core/list-item').forEach((child,index)=>{
          const row=this.el('li','rb-list-inspector-item');const editor=this.el('div','rb-content rb-inspector-text-block-content');editor.contentEditable='true';editor.dataset.editContent=child.id;editor.dataset.inspectorContent='1';editor.setAttribute('role','textbox');editor.setAttribute('aria-multiline','true');const itemTitle=this.strings.listItemSingular.replace('%1',String(index+1));editor.setAttribute('aria-label',itemTitle);editor.setAttribute('data-placeholder',this.strings.placeholder||'');buildInlineDom(editor,child.content);
          const removeItem=this.button(this.strings.listRemoveItem,'delete',{nodeId:child.id,showLabel:true});removeItem.disabled=n.children.length<=1;row.append(editor,removeItem);itemList.append(row);
        });
        items.append(itemList);const addItem=this.button(this.strings.listAddItem,'insert',{type:'core/list-item',afterId:n.id,showLabel:true});items.append(addItem);contentPanel.append(items);
      }
      if (n.type === 'core/file') {
        addField(this.strings.file.source, 'src', n.attributes.src, 'text');
        if (this.options.pageMediaPicker===true||this.options.filePicker===true||(this.options.filePicker!==false&&this.options.mediaPickers!==false)) contentPanel.append(this.button(this.strings.file.choose,'file-media',{nodeId:n.id,icon:'file',showLabel:true}));
      }
      if (n.type === 'core/audio') {
        const copy=this.strings.audioInspector;
        contentPanel.append(this.el('h4','rb-text-block-inspector-heading',copy.contentTitle),this.el('p','rb-help-text',copy.hint));
        addField(copy.source, 'src', n.attributes.src, 'text');
        if (this.options.pageMediaPicker===true||this.options.audioPicker===true||(this.options.audioPicker!==false&&this.options.mediaPickers!==false)) contentPanel.append(this.button(copy.choose,'audio-media',{nodeId:n.id,icon:'audio',showLabel:true}));
        const labelSection=this.el('section','rb-audio-inspector-section');labelSection.append(this.el('h4','rb-text-block-inspector-heading',copy.label));
        const labelEditor=this.el('div','rb-content rb-inspector-text-block-content');labelEditor.contentEditable='true';labelEditor.dataset.editContent=n.id;labelEditor.dataset.inspectorContent='1';labelEditor.setAttribute('role','textbox');labelEditor.setAttribute('aria-multiline','true');labelEditor.setAttribute('aria-label',copy.label);labelEditor.setAttribute('data-placeholder',this.strings.placeholder||'');buildInlineDom(labelEditor,n.content);
        labelSection.append(labelEditor,this.el('p','rb-help-text',copy.labelHint));contentPanel.append(labelSection);
      }
      if (n.type === 'core/video_file') {
        const copy=this.strings.videoInspector;
        contentPanel.append(this.el('h4','rb-text-block-inspector-heading',copy.contentTitle),this.el('p','rb-help-text',copy.hint));
        addField(copy.source, 'src', n.attributes.src, 'text');
        if (this.options.pageMediaPicker===true||this.options.videoPicker===true||(this.options.videoPicker!==false&&this.options.mediaPickers!==false))contentPanel.append(this.button(copy.choose,'video-media',{nodeId:n.id,showLabel:true}));
        addField(copy.titleLabel,'title',n.attributes.title||'','text');
        addField(copy.caption,'caption',n.attributes.caption||'','textarea');
        addField(copy.alignment,'align',n.attributes.align||'center','text',Object.entries(copy.alignments));
        addField(copy.size,'size',n.attributes.size||'large','text',Object.entries(copy.sizes));
        addField(copy.mobileSize,'mobile_size',n.attributes.mobile_size||'full','text',Object.entries(copy.mobileSizes));
        [['controls',copy.controls,n.attributes.controls!==false],['loop',copy.loop,!!n.attributes.loop],['muted',copy.muted,!!n.attributes.muted],['autoplay',copy.autoplay,!!n.attributes.autoplay]].forEach(([key,label,value])=>addField(label,key,value?'true':'false','text',[['true',copy.yes],['false',copy.no]]));
      }
      if (n.type === 'core/threejs_thumb') {
        addField(this.strings.threejs.label, 'label', n.attributes.label, 'text');
        try { contentPanel.append(this.renderThreeJsControls(n)); } catch (_) { contentPanel.append(this.el('p','rb-help-text',this.strings.threejs.invalidScene)); }
        addField(this.strings.threejs.sceneConfig, 'scene_json', n.attributes.scene_json, 'textarea');
        addField(this.strings.threejs.scriptLabel, 'custom_js', n.attributes.custom_js || '', 'textarea');
        const scriptHint = this.el('p','rb-help-text',this.strings.threejs.scriptHint); contentPanel.append(scriptHint);
        const hint = this.el('p','rb-help-text',this.strings.threejs.configHint); contentPanel.append(hint);
      }
      if (n.type === 'core/image') {
        contentPanel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.imageContentTitle),this.el('p','rb-help-text',this.strings.imageContentHint));
        addField(this.strings.media.source, 'src', n.attributes.src, 'text'); if (this.options.mediaPickers !== false || this.options.pageImagePicker === true) contentPanel.append(this.button(this.strings.imageReplace, 'media', {nodeId:n.id,showLabel:true}));
        addField(this.strings.media.alt, 'alt', n.attributes.alt, 'text'); addField(this.strings.media.title, 'title', n.attributes.title || '', 'text');
        addField(this.strings.media.caption, 'caption', n.attributes.caption || '', 'textarea');
        addField(this.strings.media.alignment, 'align', n.attributes.align || 'center', 'text', [['left',this.strings.media.alignments.left],['center',this.strings.media.alignments.center],['right',this.strings.media.alignments.right],['wide',this.strings.media.alignments.wide],['full',this.strings.media.alignments.full]]);
        addField(this.strings.media.imageSize, 'size', n.attributes.size || 'large', 'text', Object.entries(this.strings.media.sizes));
        addField(this.strings.media.mobileSize, 'mobile_size', n.attributes.mobile_size || 'full', 'text', Object.entries(this.strings.media.mobileSizes));
      }
      if (n.type === 'core/cover') {
        contentPanel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.coverImageTitle||'Imagem de capa'),this.el('p','rb-help-text',this.strings.coverImageHint||'Escolha uma imagem na biblioteca Pages; o conteúdo da capa é editável nos blocos internos.'));
        addField(this.strings.media.source, 'src', n.attributes.src, 'text');
        if (this.options.mediaPickers !== false || this.options.pageImagePicker === true) contentPanel.append(this.button(this.strings.imageReplace||this.strings.media.placeholder, 'media',{nodeId:n.id,icon:'image',showLabel:true}));
        addField(this.strings.media.alt, 'alt', n.attributes.alt, 'text');
        addField(this.strings.media.alignment, 'align', n.attributes.align || 'wide', 'text', [['content',this.strings.media.alignments.content],['wide',this.strings.media.alignments.wide],['full',this.strings.media.alignments.full]]);
      }
      if (n.type === 'core/gallery') {
        addField(this.strings.media.galleryColumns, 'columns', n.attributes.columns, 'number', [[2,'2'],[3,'3'],[4,'4']]);
        addField(this.strings.media.alignment, 'align', n.attributes.align || 'content', 'text', [['content',this.strings.media.alignments.content],['wide',this.strings.media.alignments.wide],['full',this.strings.media.alignments.full]]);
      }
      if (n.type === 'core/video_embed') {
        addField('URL YouTube/Vimeo', 'url', n.attributes.url || '', 'url');
        if (this.options.pageMediaPicker===true||this.options.videoPicker===true||(this.options.videoPicker!==false&&this.options.mediaPickers!==false)) {
          contentPanel.append(this.button(this.strings.videoFile.choose,'video-media',{nodeId:n.id,showLabel:true}));
          if(n.attributes.src)contentPanel.append(this.el('p','rb-help-text',String(n.attributes.src).split('/').pop()));
        }
      }
      if (n.type === 'core/button') addField(this.strings.buttonDestination, 'url', n.attributes.url, 'text');
      if (n.type === 'core/callout') addField(this.strings.callout.tone, 'tone', n.attributes.tone, 'text', Object.entries(this.strings.callout.tones));
      if (n.type === 'core/details') addField(this.strings.details.summaryLabel, 'summary', n.attributes.summary, 'text');
      if (n.type === 'core/separator') {
        const copy=this.strings.separatorInspector;
        contentPanel.append(this.el('h4','rb-text-block-inspector-heading',copy.title),this.el('p','rb-help-text',copy.hint));
        addField(copy.lineStyle,'style',n.attributes.style||'solid','text',Object.entries(copy.styles));
        addField(copy.thickness,'thickness',n.attributes.thickness||1,'number',Object.entries(copy.thicknesses));
        addField(copy.width,'width',n.attributes.width||100,'number',Object.entries(copy.widths));
        addField(copy.alignment,'align',n.attributes.align||'center','text',Object.entries(copy.alignments));
      }
      if (n.type === 'core/spacer') {
        const copy=this.strings.spacerInspector;const section=this.el('section','rb-spacer-inspector-section');
        section.append(this.el('h4','rb-text-block-inspector-heading',copy.title),this.el('p','rb-help-text',copy.hint));
        const wrap=this.el('label','rb-field');wrap.append(this.el('span','',copy.height));
        const range=this.el('input','rb-input rb-spacer-height-range');range.type='range';range.min='0';range.max=String(SPACER_HEIGHTS.length-1);range.step='1';range.value=String(Math.max(0,SPACER_HEIGHTS.indexOf(Number(n.attributes.height))));range.dataset.spacerHeightRange='1';range.dataset.nodeId=n.id;range.setAttribute('aria-label',copy.height);range.setAttribute('aria-valuetext',copy.value.replace('%1',String(n.attributes.height)));wrap.append(range);
        const valueText=this.el('output','rb-spacer-height-value',copy.value.replace('%1',String(n.attributes.height)));wrap.append(valueText);section.append(wrap);contentPanel.append(section);
      }
      if (n.type === 'core/group') {
        const copy=this.strings.groupInspector;
        addField(this.strings.outlineLabel, 'outline_label', n.attributes.outline_label || '', 'text');
        const groupSection=this.el('section','rb-group-inspector-section');groupSection.append(this.el('h4','rb-text-block-inspector-heading',copy.title),this.el('p','rb-help-text',copy.hint));
        groupSection.append(this.el('p','rb-group-child-count',copy.childCount.replace('%1',String(n.children.length))));
        groupSection.append(this.button(copy.addChild,'group-add-child',{nodeId:n.id,icon:'plus',showLabel:true}));contentPanel.append(groupSection);
      }
      if (n.type === 'core/table') {
        const cellsSection=this.el('section','rb-table-inspector-section');
        cellsSection.append(this.el('h4','rb-text-block-inspector-heading',this.strings.tableContentTitle));
        const tableWrap=this.el('div','rb-table-inspector-wrap');const inspectorTable=this.el('table','rb-table-inspector-grid');const tableBody=this.el('tbody','');
        const selectedCells=this.tableSelection.get(n.id)||new Set();
        n.children.forEach((row,rowIndex)=>{const tr=this.el('tr','');row.children.forEach((cell,colIndex)=>{
          const isHeader=tableCellIsHeader(n,rowIndex,cell);const cellElement=this.el(isHeader?'th':'td','');cellElement.rowSpan=cell.attributes.rowspan||1;cellElement.colSpan=cell.attributes.colspan||1;
          const selector=this.el('label','rb-table-cell-selector');const checkbox=this.el('input','');checkbox.type='checkbox';checkbox.dataset.tableCellSelect=cell.id;checkbox.dataset.tableId=n.id;checkbox.checked=selectedCells.has(cell.id);checkbox.setAttribute('aria-label',(this.strings.table.cellLabel||'Cell %1$d, %2$d').replace('%1$d',String(rowIndex+1)).replace('%2$d',String(colIndex+1))+' · '+this.strings.tableSelectCell);selector.append(checkbox,this.el('span','',this.strings.tableSelectCell));
          const editor=this.el('div','rb-table-cell-content rb-table-inspector-cell-editor');editor.contentEditable='true';editor.dataset.editContent=cell.id;editor.dataset.inspectorContent='1';editor.setAttribute('role','textbox');editor.setAttribute('aria-multiline','true');
          editor.setAttribute('aria-label',(this.strings.table.cellLabel||'Cell %1$d, %2$d').replace('%1$d',String(rowIndex+1)).replace('%2$d',String(colIndex+1)));editor.setAttribute('data-placeholder',this.strings.placeholder||'');buildInlineDom(editor,cell.content);
          cellElement.append(selector,editor);tr.append(cellElement);
        });tableBody.append(tr);});
        inspectorTable.append(tableBody);tableWrap.append(inspectorTable);cellsSection.append(tableWrap,this.el('p','rb-help-text',this.strings.tableContentHint));contentPanel.append(cellsSection);
        const selectionActions=this.el('div','rb-table-actions rb-table-selection-actions');
        const mergeCells=this.button(this.strings.tableMergeCells,'table-merge-cells',{nodeId:n.id,icon:'table',showLabel:true});mergeCells.dataset.tableSelectionAction='merge';mergeCells.disabled=!tableMergeSelection(n,selectedCells);selectionActions.append(mergeCells);
        const unmergeCells=this.button(this.strings.tableUnmergeCells,'table-unmerge-cells',{nodeId:n.id,icon:'table',showLabel:true});unmergeCells.dataset.tableSelectionAction='unmerge';unmergeCells.disabled=!tableGridInfo(n).origins.some((origin)=>selectedCells.has(origin.cell.id)&&(origin.rowspan>1||origin.colspan>1));selectionActions.append(unmergeCells);
        const markHeader=this.button(this.strings.tableMarkHeader,'table-mark-header',{nodeId:n.id,icon:'heading',showLabel:true});markHeader.dataset.tableSelectionAction='header';markHeader.dataset.tableHeader='true';markHeader.disabled=selectedCells.size===0;selectionActions.append(markHeader);
        const markNormal=this.button(this.strings.tableMarkNormal,'table-mark-normal',{nodeId:n.id,icon:'paragraph',showLabel:true});markNormal.dataset.tableSelectionAction='header';markNormal.dataset.tableHeader='false';markNormal.disabled=selectedCells.size===0;selectionActions.append(markNormal);
        contentPanel.append(selectionActions,this.el('p','rb-help-text',this.strings.tableSelectionHint));
        const tableActions = this.el('div','rb-table-actions');const gridInfo=tableGridInfo(n);const columns=gridInfo?gridInfo.width:0;
        const addRow = this.button(this.strings.table.addRow,'table-add-row',{nodeId:n.id,showLabel:true}); addRow.disabled = n.children.length >= 20; tableActions.append(addRow);
        const removeRow = this.button(this.strings.table.removeRow,'table-remove-row',{nodeId:n.id,showLabel:true}); removeRow.disabled = n.children.length <= 1; tableActions.append(removeRow);
        const addColumn = this.button(this.strings.table.addColumn,'table-add-column',{nodeId:n.id,showLabel:true}); addColumn.disabled = columns >= 12; tableActions.append(addColumn);
        const removeColumn = this.button(this.strings.table.removeColumn,'table-remove-column',{nodeId:n.id,showLabel:true}); removeColumn.disabled = columns <= 1; tableActions.append(removeColumn);
        const header = this.button(this.strings.table.toggleHeader,'table-toggle-header',{nodeId:n.id,showLabel:true}); header.setAttribute('aria-pressed',String(!!n.attributes.header)); tableActions.append(header);
        contentPanel.append(tableActions);
      }
      if (n.type === 'core/columns') {
        const columnActions=this.el('div','rb-table-actions');
        const addColumn=this.button(this.strings.columnsAdd||'Adicionar coluna','columns-add',{nodeId:n.id,icon:'plus',showLabel:true});addColumn.disabled=n.children.length>=3;columnActions.append(addColumn);
        const removeColumn=this.button(this.strings.columnsRemove||'Remover última coluna','columns-remove',{nodeId:n.id,icon:'minus',showLabel:true});removeColumn.disabled=n.children.length<=2;columnActions.append(removeColumn);
        contentPanel.append(this.el('h4','rb-text-block-inspector-heading',this.strings.columnInspector.title),this.el('p','rb-help-text',this.strings.columnsLimit||'O layout aceita duas ou três colunas. Ao remover a última, o conteúdo dela é movido para a coluna anterior; a operação pode ser desfeita.'));
        contentPanel.append(columnActions);
      }
      if (n.type === 'core/column') {
        const copy=this.strings.columnInspector;const path=this.findPath(this.state.blocks,n.id);const parent=path&&path.parent&&path.parent.type==='core/columns'?path.parent:null;
        const count=copy.position.replace('%1',String(path?path.index+1:1)).replace('%2',String(parent?parent.children.length:1)).replace('%3',String(n.children.length));
        const columnSection=this.el('section','rb-column-inspector-section');columnSection.append(this.el('h4','rb-text-block-inspector-heading',copy.title),this.el('p','rb-help-text',count),this.button(copy.addChild,'column-add-child',{nodeId:n.id,icon:'plus',showLabel:true}));contentPanel.append(columnSection);
      }
      const labelledTextActions=['core/paragraph','core/heading','core/list','core/list-item','core/quote','core/button','core/callout','core/details','core/code','core/video_embed','core/video_file','core/audio','core/table'].includes(n.type);
      const actions = this.el('div', 'rb-inspector-actions'+(labelledTextActions?' rb-text-block-inspector-actions':''));
      if(labelledTextActions)actions.append(this.el('h4','rb-text-block-inspector-heading',n.type==='core/list'?this.strings.listActions:(n.type==='core/quote'?this.strings.quoteActions:(n.type==='core/button'?this.strings.buttonActions:(n.type==='core/callout'?this.strings.calloutActions:(n.type==='core/details'?this.strings.detailsActions:(n.type==='core/code'?this.strings.preformattedActions:(n.type==='core/video_embed'?this.strings.videoInspector.actions:(n.type==='core/video_file'?this.strings.videoInspector.actions:(n.type==='core/audio'?this.strings.audioInspector.actions:(n.type==='core/table'?this.strings.tableActions:this.strings.paragraphActions)))))))))));
      const fixedColumn = found.parent && found.parent.type === 'core/columns' && n.type === 'core/column';
      const lastRequiredChild = found.parent && ['core/gallery','core/cover'].includes(found.parent.type) && found.parent.children.length <= 1;
      const remove = this.button(this.strings.remove, 'delete', { nodeId:n.id, showLabel:labelledTextActions }); remove.disabled = !!fixedColumn || !!lastRequiredChild;
      actions.append(this.button(this.strings.moveUp, 'move-up', { nodeId:n.id, showLabel:labelledTextActions }), this.button(this.strings.moveDown, 'move-down', { nodeId:n.id, showLabel:labelledTextActions }));
      const indent = this.button(this.strings.indentBlock, 'indent', { nodeId:n.id, showLabel:labelledTextActions }); indent.disabled = !this.canIndent(n.id); actions.append(indent);
      const outdent = this.button(this.strings.outdentBlock, 'outdent', { nodeId:n.id, showLabel:labelledTextActions }); outdent.disabled = !this.canOutdent(n.id); actions.append(outdent, remove); contentPanel.append(actions);
      aside.append(contentPanel,appearancePanel);
      return aside;
    }

    updateHistoryButtons() {
      this.root.querySelectorAll('[data-history="undo"]').forEach((b) => { b.disabled = this.undoStack.length === 0; });
      this.root.querySelectorAll('[data-history="redo"]').forEach((b) => { b.disabled = this.redoStack.length === 0; });
    }
  }

  global.ReamurBlogBlockEditor = {
    classifyKeyboardShortcut: classifyKeyboardShortcut,
    replaceTextMatchInRuns: replaceTextMatchInRuns,
    findTextMatchesInRuns: findTextMatchesInRuns,
    normalizeSearchText: normalizeSearchText,
    documentStatistics: documentStatistics,
    findNodeChain: findNodeChain,
    getOutlineNavigationTarget: getOutlineNavigationTarget,
    moveBlockAcrossHierarchy: moveBlockAcrossHierarchy,
    cloneBlockWithNewIds: cloneBlockWithNewIds,
    mount: function (root, options) { if (!(root instanceof Element)) throw new TypeError('editor root must be an Element'); return new Editor(root, options || {}); },
    validateDocument: validateDocument,
    analyzeDocumentAccessibility: analyzeDocumentAccessibility,
    analyzeDocumentLinks: analyzeDocumentLinks,
    analyzeInternalAnchors: analyzeInternalAnchors,
    analyzeDocumentReadability: analyzeDocumentReadability,
    analyzeDocumentPreflight: analyzeDocumentPreflight,
    compareBDREDocuments: compareBDREDocuments,
    convertDocumentToMarkdown: convertDocumentToMarkdown,
    parseMarkdownToBDRE: parseMarkdownToBDRE,
    createTableOfContentsPlan: createTableOfContentsPlan,
    validLocalImage: validLocalImage,
    validLocalDownload: validLocalDownload,
    validLocalAudio: validLocalAudio,
    validEmbed: validEmbed
  };
})(window);
