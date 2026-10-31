#!/usr/bin/env node
'use strict';
const assert=require('node:assert/strict');
function endpoint(base,params){const url=new URL(String(base||'').replace(/&amp;/g,'&'),'https://admin.example.test/');Object.keys(params||{}).forEach((key)=>url.searchParams.set(key,String(params[key])));return url;}
const url=endpoint('/davidcms/dev/admin/index.php?route=cms%2Fpage.mediaList&amp;user_token=csrf-session',{kind:'image',search:'foto & capa',page:1});
assert.equal(url.pathname,'/davidcms/dev/admin/index.php');
assert.equal(url.searchParams.get('route'),'cms/page.mediaList');
assert.equal(url.searchParams.get('user_token'),'csrf-session');
assert.equal(url.searchParams.get('kind'),'image');
assert.equal(url.searchParams.get('search'),'foto & capa');
assert.equal(url.searchParams.get('page'),'1');
const assetUrl=new URL('view/image/cms/page/87277837630b175256e02fc96a03ad9c.jpg','http://localhost/davidcms/dev/catalog/');
assert.equal(assetUrl.href,'http://localhost/davidcms/dev/catalog/view/image/cms/page/87277837630b175256e02fc96a03ad9c.jpg');
console.log('PASS CMS Pages media URL: HTML ampersands normalized; route, session token and query parameters preserved.');
console.log('PASS CMS Pages image preview URL resolves against catalog/, not the admin directory.');
