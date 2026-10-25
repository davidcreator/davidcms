'use strict';
const fs=require('fs'); const path=require('path'); const root=path.resolve(__dirname,'../..');
let checks=0; function ok(condition,label){checks++;if(!condition)throw new Error('FAIL: '+label);console.log('PASS: '+label);}
const init=fs.readFileSync(path.join(root,'dev/admin/view/js/blog-block-editor-init.js'),'utf8');
const controller=fs.readFileSync(path.join(root,'dev/admin/controller/blog/blog.php'),'utf8');
const css=fs.readFileSync(path.join(root,'dev/admin/view/css/blog-block-editor.css'),'utf8');
const form=fs.readFileSync(path.join(root,'dev/admin/view/template/blog/blog_form.twig'),'utf8');
ok(/role', 'status'/.test(init)&&/aria-live', 'polite'/.test(init)&&/aria-atomic', 'true'/.test(init),'save feedback uses an atomic polite live region');
ok(/saveStateSaved/.test(init)&&/saveStateUnsaved/.test(init)&&/saveStateSaving/.test(init),'status supports saved, unsaved, and saving states');
ok(/event\.target\.matches\('\[name\^="blog_blocks\["/.test(init),'hidden block payload/ready fields do not falsely mark the form dirty');
ok(/form\.addEventListener\('input'/.test(init)&&/form\.addEventListener\('change'/.test(init),'ordinary form edits mark the form dirty');
ok(/changeState\.markDirty\(\)/.test(init),'block editor changes mark the same form dirty');
ok(/global\.addEventListener\('beforeunload'/.test(init)&&/if \(!state\.dirty \|\| state\.bypass\) return/.test(init),'native leave-warning is limited to dirty forms and valid submission bypass');
ok(/changeState\.status\.dataset\.state = 'saving'/.test(init),'valid form submission announces saving instead of claiming completion');
ok(/instance\.serialize\(\)/.test(init)&&/event\.preventDefault\(\)/.test(init),'existing POST path serializes locally and invalid submissions are stopped');
ok(!/fetch\s*\(|XMLHttpRequest|sendBeacon|setInterval\s*\(/.test(init),'no autosave or network request was introduced');
ok(/global\.addEventListener\('pageshow'/.test(init)&&/state\.bypass = false/.test(init),'page restoration re-enables unsaved-change protection');
ok(/<form method="post" action="\{\{ action \}\}"/.test(form),'feedback remains attached to the existing POST form');
ok(/\.rb-save-status/.test(css)&&/data-state="unsaved"/.test(css),'save feedback has visible responsive-independent state styling');
ok(/'saveStateSaved' => \$data\['text_block_save_state_saved'\]/.test(controller),'controller passes localized save-state strings');
for(const locale of ['pt-br','en-us','es-eu']){const text=fs.readFileSync(path.join(root,'dev/admin/language',locale,'blog/blog.php'),'utf8');for(const key of ['text_block_save_state_saved','text_block_save_state_unsaved','text_block_save_state_saving'])ok(text.includes("$_['"+key+"']"),locale+' includes '+key);}
console.log(`STATIC_ONLY: ${checks} PASS; no browser, PHP runtime, network, database, staging, deploy or migrations.`);
