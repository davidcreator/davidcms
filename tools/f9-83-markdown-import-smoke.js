'use strict';
const fs=require('fs'),vm=require('vm'),path=require('path');
const source=fs.readFileSync(path.join(__dirname,'../dev/admin/view/js/blog-block-editor.js'),'utf8');
const root={document:{documentElement:{lang:'pt-br'}},location:{origin:'https://admin.example'},setTimeout,clearTimeout,setInterval,clearInterval,URL,TextEncoder,Uint8Array,console};
const context={window:root,globalThis:root,URL,TextEncoder,Uint8Array,Intl,Map,Set,WeakMap,WeakSet,Date,Math,JSON,RegExp,Array,Object,String,Number,Boolean,Error,TypeError,Promise,console,setTimeout,clearTimeout};
vm.runInNewContext(source,context,{filename:'blog-block-editor.js'});
const api=root.ReamurBlogBlockEditor;
if(!api||typeof api.parseMarkdownToBDRE!=='function')throw new Error('Markdown importer parser unavailable');
const parse=(markdown,page=false)=>api.parseMarkdownToBDRE(markdown,page);
const good=parse([
  '# Heading one','',
  'A paragraph with **bold**, *italic*, `literal <tag>`, and [safe link](https://example.org/path).','',
  '- First item','- Second item','',
  '> A quoted line','',
  '| Name | Value |','| :--- | ---: |','| Item | 42 |','',
  '```js','const safe = "not executed";','```','',
  '![Local image](<image/photo.png>)','',
  '[Download guide](<view/download/blog/guide.pdf>)','',
  '[Listen](<view/audio/blog/sample.mp3>)','',
  '[Local video](<view/video/blog/clip.mp4>)','',
  '[Video](<https://www.youtube.com/watch?v=abcdefghijk>)'
].join('\n'));
if(!good.valid)throw new Error('Expected valid safe subset; errors: '+good.errors);
const expected=['core/heading','core/paragraph','core/list','core/quote','core/table','core/code','core/image','core/file','core/audio','core/video_file','core/video_embed'];
if(JSON.stringify(good.blocks.map(x=>x.type))!==JSON.stringify(expected))throw new Error('Unexpected native block conversion order: '+good.blocks.map(x=>x.type));
if(!api.validateDocument({format_version:2,blocks:good.blocks},false).valid)throw new Error('Generated BDRE document did not validate');
const paragraph=good.blocks[1];
if(!paragraph.content.some(run=>run.marks.includes('bold'))||!paragraph.content.some(run=>run.href==='https://example.org/path'))throw new Error('Inline emphasis or safe link was not preserved');
const code=good.blocks.find(x=>x.type==='core/code');if(!code||!code.content.includes('not executed'))throw new Error('Fenced code not preserved as code text');
const localImage=parse('![Page image](<view/image/cms/page/photo.png>)',true);
if(!localImage.valid||localImage.blocks[0].type!=='core/image')throw new Error('Local page image should be accepted in Pages context');
const crossScopeImage=parse('![Page image](<view/image/cms/page/photo.png>)',false);
if(!crossScopeImage.valid||crossScopeImage.blocks[0].type==='core/image'||!crossScopeImage.warnings.some(w=>w.code==='remote_image'))throw new Error('Cross-scope image should not be imported as a media block');
const pageMedia=parse('[File](<view/download/cms/page/guide.pdf>)\n\n[Audio](<view/audio/cms/page/sample.mp3>)\n\n[Video](<view/video/cms/page/clip.mp4>)',true);
if(!pageMedia.valid||JSON.stringify(pageMedia.blocks.map(b=>b.type))!==JSON.stringify(['core/file','core/audio','core/video_file']))throw new Error('Pages-local media should convert only in the Pages context');
const isolated=parse('[File](<view/download/blog/guide.pdf>)\n\n[Audio](<view/audio/blog/sample.mp3>)\n\n[Video](<view/video/blog/clip.mp4>)',true);
if(!isolated.valid||isolated.blocks.some(b=>['core/file','core/audio','core/video_file'].includes(b.type)))throw new Error('Blog media must not cross into Pages during Markdown import');
const hostile=parse('[run](javascript:alert(1))\n\n<script>alert(2)</script>\n\n![remote](https://example.org/a.png)');
if(!hostile.valid)throw new Error('Unsafe destinations and literal HTML should not invalidate safe text import');
if(hostile.blocks.some(b=>b.type==='core/image'))throw new Error('Remote image was imported or fetched');
if(!hostile.warnings.some(w=>w.code==='unsafe_link')||!hostile.warnings.some(w=>w.code==='html_literal')||!hostile.warnings.some(w=>w.code==='remote_image'))throw new Error('Expected warnings for blocked link, literal HTML, and remote image');
const literal=hostile.blocks.flatMap(b=>Array.isArray(b.content)?b.content.map(r=>r.text):[typeof b.content==='string'?b.content:'']).join(' ');
if(!literal.includes('javascript:alert(1)')||!literal.includes('<script>alert(2)</script>'))throw new Error('Unsafe text was dropped or not preserved literally');
const inlineImage=parse('Text ![inline](https://example.org/a.png)');
if(!inlineImage.valid||inlineImage.blocks[0].type!=='core/paragraph'||!inlineImage.warnings.some(w=>w.code==='inline_image'))throw new Error('Inline image should remain text with a warning');
const unclosed=parse('```\nalert(1)');
if(!unclosed.valid||unclosed.blocks[0].type!=='core/code'||!unclosed.warnings.some(w=>w.code==='unclosed_fence'))throw new Error('Unclosed code fence handling is incorrect');
const nested=parse('- Parent\n  - Child');
if(!nested.valid||!nested.warnings.some(w=>w.code==='nested_list'))throw new Error('Nested list limitation should be disclosed');
const oversize=parse('x'.repeat(262145));if(oversize.valid||oversize.errors[0]!=='markdown_too_large')throw new Error('256 KiB limit is not enforced');
const tooMany=parse(Array.from({length:1001},(_,i)=>'Paragraph '+i).join('\n\n'));
if(tooMany.valid)throw new Error('Generated document block-count limit should be validated');
const empty=parse('   \n\n');if(!empty.valid||empty.blocks.length)throw new Error('Empty Markdown should return a valid empty conversion');
console.log('PASS: Markdown import parses a safe subset into validated native BDRE blocks; unsafe links, HTML and remote images stay inert/textual; local-media scope, warnings and limits are enforced.');
