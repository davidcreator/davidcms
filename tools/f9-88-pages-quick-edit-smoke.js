'use strict';
const fs=require('fs'),path=require('path');
const root=path.resolve(__dirname,'..');
const read=(file)=>fs.readFileSync(path.join(root,file),'utf8');
const controller=read('dev/admin/controller/cms/page.php');
const model=read('dev/admin/model/cms/page.php');
const list=read('dev/admin/view/template/cms/page_list.twig');
const quickJs=read('dev/admin/view/js/cms-page-quick-edit.js');
const form=read('dev/admin/view/template/cms/page_form.twig');
const editStart=controller.indexOf('public function quickEdit('),editEnd=controller.indexOf('public function content(',editStart);
if(editStart<0||editEnd<0)throw new Error('Quick Edit route boundary not found');
const route=controller.slice(editStart,editEnd);
const checks=[
 ['Pages list has WordPress-style inline Quick Edit action and hidden per-row edit panel',list.includes('data-page-quick-edit-toggle')&&list.includes('page-quick-edit-row')&&list.includes('data-page-quick-edit-cancel')],
 ['Quick Edit fields are limited to supported title, slug, status and order properties',list.includes('name="quick_title"')&&list.includes('name="quick_keyword"')&&list.includes('name="quick_status"')&&list.includes('name="quick_sort_order"')&&!list.includes('quick_parent')&&!list.includes('quick_password')],
 ['Inline form uses CSRF, active-language binding and preserves search/pagination on return',list.includes('name="csrf_token"')&&list.includes('name="quick_language_id"')&&list.includes('name="return_page"')&&list.includes('name="return_filter_title"')&&list.includes('name="return_filter_status"')],
 ['Quick Edit route is POST-only, ACL/CSRF protected, validates data and uses optimistic transaction checks',route.includes("$requestMethod !== 'POST'")&&route.includes("!$this->user->hasPermission('modify', 'cms/page')")&&route.includes('csrfValid()')&&route.includes('keywordTaken(')&&route.includes('getPageEditFingerprint')&&route.includes('editPage(')],
 ['Quick Edit rebuilds from stored descriptions to preserve BDRE body and SEO metadata',route.includes('getPageDescriptions($pageId)')&&route.includes("'description'=>(string)($existing['description'] ?? '')")&&route.includes("'meta_title'=>(string)($existing['meta_title'] ?? '')")&&route.includes("'meta_description'=>(string)($existing['meta_description'] ?? '')")],
 ['System pages cannot have their slug changed in Quick Edit and list delete rules remain intact',route.includes('if (!$systemPage) $keywords[$languageId] = $keyword;')&&list.includes('can_modify and not page.is_system_page')],
 ['Page list query exposes the current-language slug without an N+1 lookup',model.includes("SELECT psu.`value` FROM `\" . $this->t('seo_url') . \"` psu")&&model.includes("AS `keyword`")&&controller.includes("'quick_keyword' => (string)($row['keyword'] ?? '')")],
 ['Accessible Quick Edit script toggles rows, returns focus on cancel/Escape, and does not intercept form submission',quickJs.includes('aria-expanded')&&quickJs.includes("event.key !== 'Escape'")&&quickJs.includes('activeButton.focus()')&&!quickJs.includes("submit",quickJs.indexOf('document.addEventListener'))],
 ['Full-screen block editor stays independent of the inline quick-edit row',form.includes('data-blog-block-editor')&&list.includes('page.edit')&&controller.includes("'cms/page.edit|' . $pid")],
];
for(const [label,ok] of checks)console.log(`${ok?'PASS':'FAIL'}: ${label}`);
if(checks.some(([,ok])=>!ok))process.exit(1);
console.log('PASS: F9-88 static quick-edit workflow checks completed; no PHP or browser runtime is simulated.');
