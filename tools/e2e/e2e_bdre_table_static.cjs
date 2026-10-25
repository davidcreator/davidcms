'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '../..');
const read = (relative) => fs.readFileSync(path.join(root, relative), 'utf8');
const fail = (message) => { console.error('FAIL:', message); process.exitCode = 1; };
const ok = (condition, message) => condition ? console.log('PASS:', message) : fail(message);

const fixture = JSON.parse(read('tests/fixtures/bdre_table.json'));
const context = { window:{}, URL, TextEncoder, Set, Map, JSON, Array, Object, String, Number, Math, RegExp, Error, TypeError, console, setTimeout, clearTimeout };
vm.runInNewContext(read('dev/admin/view/js/blog-block-editor.js'), context, { filename:'blog-block-editor.js' });
const validate = context.window.ReamurBlogBlockEditor.validateDocument;
ok(validate(fixture.valid).valid, 'fixture base de tabela é válida no validador cliente');
fixture.invalid.forEach((item) => ok(!validate(item.document).valid, 'fixture inválida rejeitada: ' + item.name));

function makeTable(rows, cols, contentFactory) {
  let id = 0;
  const cell = (r,c) => ({id:'n'+(++id),type:'core/table-cell',attributes:{},children:[],content:contentFactory ? contentFactory(r,c) : []});
  const tableRows = [];
  for (let r=0;r<rows;r++) {
    const cells=[]; for(let c=0;c<cols;c++) cells.push(cell(r,c));
    tableRows.push({id:'n'+(++id),type:'core/table-row',attributes:{},children:cells,content:''});
  }
  return {format_version:2,blocks:[{id:'n'+(++id),type:'core/table',attributes:{header:true},children:tableRows,content:''}]};
}
ok(validate(makeTable(20,12)).valid, 'limite máximo de 20 × 12 aceito');
ok(!validate(makeTable(21,1)).valid, '21 linhas rejeitadas');
ok(!validate(makeTable(1,13)).valid, '13 colunas rejeitadas');
const malformed = makeTable(1,1); malformed.blocks[0].children[0].children = null;
let malformedRejected = false; try { malformedRejected = !validate(malformed).valid; } catch (_) {}
ok(malformedRejected, 'row malformada rejeitada sem exceção no cliente');
ok(!validate(makeTable(1,2,(r,c)=>[{text:'x'.repeat(131073),marks:[],href:null}])).valid, 'texto total da tabela acima de 262144 bytes rejeitado');

const php = read('dev/system/library/blog/block_document.php');
const renderer = read('dev/system/library/blog/block_renderer.php');
const editor = read('dev/admin/view/js/blog-block-editor.js');
const publicCss = read('dev/catalog/view/css/blog-blocks.css');
const adminCss = read('dev/admin/view/css/blog-block-editor.css');
ok(php.includes('MAX_TABLE_ROWS = 20') && php.includes('MAX_TABLE_COLUMNS = 12') && php.includes('MAX_TABLE_TEXT_BYTES = 262144'), 'limites de servidor definidos');
ok(php.includes("'core/table-cell'") && php.includes('table_requires_v2') && php.includes('table_columns_mismatch'), 'schema server-side contém v2 e estrutura de linhas/células');
ok(renderer.includes("case 'core/table':") && renderer.includes('<th scope="col">') && renderer.includes("$this->renderInlineRuns($cell['content'])"), 'renderer usa elementos de tabela fixos e renderizador inline escapado');
ok(editor.includes('table-add-row') && editor.includes('table-remove-column') && editor.includes('core/table-cell'), 'editor tem controles estruturais e edição de células');
ok(publicCss.includes('.rms-block-table') && adminCss.includes('.rb-table-preview'), 'estilos públicos/admin limitados a classes fixas');
ok(!/execCommand\s*\(/.test(editor), 'sem document.execCommand');

const languages = ['pt-br','en-us','es-eu'];
const tableKeys = ['text_block_type_table','text_block_table_header','text_block_table_add_row','text_block_table_remove_row','text_block_table_add_column','text_block_table_remove_column','text_block_table_toggle_header','text_block_table_cell_label'];
languages.forEach((locale) => {
  const file = read('dev/admin/language/'+locale+'/blog/blog.php');
  tableKeys.forEach((key) => ok(file.includes("$_['"+key+"']"), locale+' tem tradução '+key));
});
const controller = read('dev/admin/controller/blog/blog.php');
tableKeys.forEach((key) => ok(controller.includes("'"+key+"'"), 'controller carrega '+key));

console.log('STATIC_ONLY: sem PHP, SQL, browser, app, staging, deploy, migration ou banco.');
