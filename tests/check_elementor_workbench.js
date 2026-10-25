'use strict';
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'..');
const js=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor.js'),'utf8');
const css=fs.readFileSync(path.join(root,'dev/admin/view/css/blog-block-editor.css'),'utf8');
const checks=[
 ['Fullscreen render has a dedicated two-pane builder layout',/rb-builder-layout/.test(js)&&/rb-builder-sidebar/.test(js)&&/rb-canvas/.test(js)],
 ['Sidebar has Elements, Structure, and Settings panels',/builderElements/.test(js)&&/builderStructure/.test(js)&&/builderSettings/.test(js)],
 ['Elements panel uses the existing searchable block registry',/panelBody\.append\(inserterMenu\)/.test(js)&&/renderInserter\(this\.fullscreen\)/.test(js)],
 ['Structure and settings use existing outline and inspector',/panelBody\.append\(this\.renderOutline\(\)\)/.test(js)&&/panelBody\.append\(this\.renderInspector\(\)\)/.test(js)],
 ['Left panel controls expose selection state and remain keyboard buttons',/aria-pressed',String\(this\.activePanel===panel\)/.test(js)&&/rb-builder-panel-button/.test(js)],
 ['Toolbar Add block opens the Elements panel and focuses search',/this\.activePanel = 'elements'.*render\(\).*inserter-search/s.test(js)],
 ['Current immersive mode retains the accessible dialog and Escape exit',/aria-modal','true'/.test(js)&&/event\.key === 'Escape'.*setFullscreen\(false\)/s.test(js)],
 ['Fullscreen workbench has a lateral dock and broad canvas',/\.rb-editor\.is-fullscreen \.rb-builder-layout\{grid-template-columns:minmax\(17rem,21rem\)/.test(css)&&/\.rb-editor\.is-fullscreen \.rb-builder-layout>\.rb-canvas/.test(css)],
 ['Dock and canvas adapt to narrow viewports',/@media\(max-width:680px\).*rb-builder-layout/.test(css)],
 ['Labels are localized in Portuguese, English, and Spanish',/startsWith\('pt'\).*Elementos/s.test(js)&&/startsWith\('es'\).*Estructura/s.test(js)&&/Elements/.test(js)],
 ['No external stylesheet or icon dependency was introduced',!/@import\s+url\(|https?:\/\//i.test(css)&&/createElementNS\('http:\/\/www\.w3\.org\/2000\/svg'/.test(js)],
];
let failed=0;for(const [name,ok] of checks){console.log(`${ok?'PASS':'FAIL'} ${name}`);if(!ok)failed++;}
process.exitCode=failed?1:0;
