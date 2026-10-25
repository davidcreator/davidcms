'use strict';
const fs=require('fs');const path=require('path');const root=path.resolve(__dirname,'../..');let checks=0;
function ok(v,label){checks++;if(!v)throw new Error('FAIL: '+label);console.log('PASS: '+label);}
const js=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor.js'),'utf8');
const php=fs.readFileSync(path.join(root,'dev/system/library/blog/block_document.php'),'utf8');
const renderer=fs.readFileSync(path.join(root,'dev/system/library/blog/block_renderer.php'),'utf8');
const ctl=fs.readFileSync(path.join(root,'dev/admin/controller/blog/blog.php'),'utf8');
const css=fs.readFileSync(path.join(root,'dev/catalog/view/css/blog-blocks.css'),'utf8');
const adminCss=fs.readFileSync(path.join(root,'dev/admin/view/css/blog-block-editor.css'),'utf8');
const fixture=JSON.parse(fs.readFileSync(path.join(root,'tests/fixtures/core_callout.json'),'utf8')).document;
ok(fixture.format_version===2&&fixture.blocks.length===4,'fixture v2 covers four callout tones');
ok(/\['core\/callout', 'Aviso'\]/.test(js)&&/core\/callout':'content'/.test(js),'callout is registered in the content inserter');
ok(/core\/callout':\['tone'\]/.test(js)&&/callout_invalid/.test(js),'client schema allowlists tone and rejects invalid structures');
ok(/if \(type === 'core\/callout'\) return this\.node\(type, \{ tone:'info' \}/.test(js),'new callout uses a valid safe default');
ok(/this\.strings\.callout\.tone/.test(js)&&/Object\.entries\(this\.strings\.callout\.tones\)/.test(js),'inspector exposes localized closed tone choices');
ok(/card\.dataset\.calloutTone/.test(js)&&/typeClass/.test(js),'admin preview reflects callout state');
ok(/'core\/callout'/.test(php)&&/\['tone'\]/.test(php)&&/callout_tone_invalid/.test(php),'server validator allowlists the callout type and tone enum');
ok(/callout_requires_v2/.test(php)&&/core\/callout/.test(php),'server validator prevents legacy v1 callouts');
ok(/case 'core\/callout':/.test(renderer)&&/role="note"/.test(renderer)&&/rms-block-callout--/.test(renderer),'public renderer emits semantic note with enum class');
ok(/renderInlineRuns\(\$node\['content'\]\)/.test(renderer),'callout text uses the existing safe rich-text renderer');
ok(/rms-block-callout--info/.test(css)&&/rms-block-callout--danger/.test(css),'public output styles only fixed tones');
ok(/data-callout-tone="warning"/.test(adminCss),'admin preview styles fixed callout tones');
ok(/'core\/callout' => \$data\['text_block_type_callout'\]/.test(ctl)&&/'callout' => \['defaultText' => \$data\['text_block_callout_default'\]/.test(ctl),'controller passes localized type and inspector strings');
for(const locale of ['pt-br','en-us','es-eu']){const text=fs.readFileSync(path.join(root,'dev/admin/language',locale,'blog/blog.php'),'utf8');for(const key of ['text_block_type_callout','text_block_callout_default','text_block_callout_tone','text_block_callout_tone_info','text_block_callout_tone_success','text_block_callout_tone_warning','text_block_callout_tone_danger'])ok(text.includes("$_['"+key+"']"),locale+' includes '+key);}
console.log(`STATIC_ONLY: ${checks} PASS; no PHP runtime, browser, database, staging, deploy or migrations.`);
