'use strict';
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'..');
const read=(file)=>fs.readFileSync(path.join(root,file),'utf8');
const controller=read('dev/admin/controller/cms/page.php');
const template=read('dev/admin/view/template/cms/page_create_editor.twig');
const form=read('dev/admin/view/template/cms/page_form.twig');
const editor=read('dev/admin/view/js/blog-block-editor.js');
const init=read('dev/admin/view/js/blog-block-editor-init.js');
const languages=['pt-br','en-us','es-eu'].map((lang)=>read(`dev/admin/language/${lang}/cms/page.php`));
const addStart=controller.indexOf('public function add(): void');
const addEnd=controller.indexOf('public function edit(',addStart);
if(addStart<0||addEnd<0)throw new Error('Page add/edit controller boundary not found');
const add=controller.slice(addStart,addEnd);
const checks=[
 ['Page creation renders a dedicated view while existing-page edits keep the original form',add.includes("load->view('cms/page_create_editor', $data)")&&controller.includes("load->view('cms/page_form', $data)")],
 ['Creation performs one final POST only; GET/cancel do not create a server-side draft',add.includes("if ($this->request->server['REQUEST_METHOD'] === 'POST')")&&add.indexOf('addPage($this->postPayload())')>add.indexOf("if ($this->request->server['REQUEST_METHOD'] === 'POST')")&&template.includes('data-page-create-editor')],
 ['Successful creation and cancel return to the Pages list',add.includes("$this->response->redirect($this->url->link('cms/page'))")&&template.includes('href="{{ cancel|e(\'html_attr\') }}"')],
 ['Editor opens fullscreen for the editable first language and saves through the existing validated Page form',template.includes('data-blog-block-editor')&&template.includes("data-fullscreen-start=\"{{ can_modify and loop.first ? 'true' : 'false' }}\"")&&template.includes('data-cms-page-form')&&template.includes('data-page-context')],
 ['Fullscreen title editor synchronizes the create-form title and dispatches its dirty-state event',editor.includes('data-page-context-title-input')&&editor.includes("field.dispatchEvent(new global.Event('input',{bubbles:true}))")&&editor.includes("context.allowTitleEdit")],
 ['Create workflow keeps existing SEO, keyword, status, sort order and custom-field values',template.includes('name="page_description[{{ language.language_id }}][keyword]"')&&template.includes('name="page_description[{{ language.language_id }}][meta_title]"')&&template.includes('name="page_description[{{ language.language_id }}][meta_description]"')&&template.includes('name="status"')&&template.includes('name="sort_order"')&&template.includes('name="custom_field[')],
 ['Unique create-session keys survive reloads and prevent local draft recovery from crossing between new Pages',add.includes("preg_match('/^[a-f0-9]{24}$/D'")&&add.includes("create_draft_id=' . $draftCandidate")&&add.includes("'create_draft_id=' . $data['page_create_draft_id']")&&template.includes("':create:' ~ page_create_draft_id")&&template.includes('name="page_create_draft_id"')&&init.includes('sessionStorage')],
 ['Page edit form remains intact and Blog editor remains an independent route',form.includes('data-blog-block-editor')&&form.includes('data-cms-page-form')&&controller.includes("'blog/blog'")&&controller.includes("load->view('cms/page_form', $data)")],
 ['New creation workflow copy is translated in Portuguese, English and Spanish',languages.every((text)=>text.includes("$_['text_page_create_intro']")&&text.includes("$_['text_page_create_title_help']")&&text.includes("$_['text_page_create_keyword_help']"))],
];
for(const [label,ok] of checks)console.log(`${ok?'PASS':'FAIL'}: ${label}`);
if(checks.some(([,ok])=>!ok))process.exit(1);
console.log('PASS: F9-87 static workflow smoke checks completed; this does not execute PHP or browser interactions.');
