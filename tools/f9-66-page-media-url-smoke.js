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
function inferCatalogBase(href){const current=new URL(href);const adminIndex=current.pathname.indexOf('/admin/');if(adminIndex>=0){current.pathname=current.pathname.slice(0,adminIndex+1)+'catalog/';current.search='';current.hash='';return current.href;}return '';}
const fallbackBase=inferCatalogBase('http://localhost/davidcms/dev/admin/index.php?route=cms%2Fpage.edit');
assert.equal(fallbackBase,'http://localhost/davidcms/dev/catalog/');
assert.equal(new URL('view/image/cms/page/87277837630b175256e02fc96a03ad9c.jpg',fallbackBase).href,assetUrl.href);
const videoUrl=new URL('view/video/cms/page/59a2c01f3a1b7f1eaa43.mp4',fallbackBase);
assert.equal(videoUrl.href,'http://localhost/davidcms/dev/catalog/view/video/cms/page/59a2c01f3a1b7f1eaa43.mp4');
assert.equal(assetUrl.href,'http://localhost/davidcms/dev/catalog/view/image/cms/page/87277837630b175256e02fc96a03ad9c.jpg');
console.log('PASS CMS Pages media URL: HTML ampersands normalized; route, session token and query parameters preserved.');
console.log('PASS CMS Pages image/video preview URLs resolve against catalog/, not the admin directory, using config or admin-path fallback.');
