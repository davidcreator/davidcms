/* Safe, browser-local import: converts approved document formats to BDREditor v2 blocks. No upload/network. */
(function (global) {
  'use strict';
  const LIMIT_FILE = 25 * 1024 * 1024, LIMIT_ENTRY = 8 * 1024 * 1024, LIMIT_TOTAL = 30 * 1024 * 1024;
  let sequence = 0;
  const id = () => 'imp-' + (++sequence).toString(36);
  const run = (text, marks, href) => ({ text: String(text), marks: marks || [], href: href || null });
  const textBlock = (type, text, attributes) => ({ id:id(), type, attributes:attributes || {}, children:[], content:[run(text || ' ')] });
  const blankCell = () => ({ id:id(), type:'core/table-cell', attributes:{}, children:[], content:[run(' ')] });
  const safeAction = (value) => {
    if (typeof value !== 'string') return null;
    const s=value.trim(); if (!s || /[\u0000-\u001f]/.test(s) || /^\s*(javascript|data|vbscript):/i.test(s)) return null;
    if (/^(https:\/\/|mailto:)/i.test(s) || s.startsWith('/') && !s.startsWith('//')) return s;
    return null;
  };
  const localAsset = (value, exts, roots) => {
    if (typeof value !== 'string' || !value || /[\u0000-\u0020\\]/.test(value) || value.startsWith('//')) return null;
    let path=value.split(/[?#]/,1)[0].replace(/^\/+/, '');
    try {
      path=decodeURIComponent(path);
      if (!roots.some((root)=>path.startsWith(root))) { const base=global.location&&global.location.origin||'https://local.invalid';const u=new URL(value,base);if(u.origin!==base||u.search||u.hash)return null;path=decodeURIComponent(u.pathname.replace(/^\//,'')); }
    } catch (_) { return null; }
    if (!roots.some((root)=>path.startsWith(root)) || path.split('/').some(part=>part==='.'||part==='..') || !exts.some((e)=>path.toLowerCase().endsWith(e))) return null;
    return path;
  };
  const safeVideoEmbed=(value)=>{try{const u=new URL(value);return u.protocol==='https:'&&!u.username&&!u.password&&['youtube.com','www.youtube.com','youtu.be','vimeo.com','www.vimeo.com','player.vimeo.com'].includes(u.hostname.toLowerCase())?u.href:null;}catch(_){return null;}};
  function bytesToText(bytes) { return new TextDecoder('utf-8').decode(bytes); }
  async function unzip(file) {
    if (file.size > LIMIT_FILE) throw new Error('O arquivo excede o limite de 25 MiB.');
    const bytes=new Uint8Array(await file.arrayBuffer()); const view=new DataView(bytes.buffer,bytes.byteOffset,bytes.byteLength);
    let eocd=-1; const min=Math.max(0,bytes.length-65557);
    for(let i=bytes.length-22;i>=min;i--) if(view.getUint32(i,true)===0x06054b50){eocd=i;break;}
    if(eocd<0) throw new Error('O pacote ZIP não possui um diretório final válido.');
    const count=view.getUint16(eocd+10,true), cdSize=view.getUint32(eocd+12,true), cdStart=view.getUint32(eocd+16,true);
    if(count>10000 || cdStart+cdSize>bytes.length) throw new Error('O pacote contém entradas demais ou está corrompido.');
    const entries=new Map(); let p=cdStart, total=0;
    for(let n=0;n<count;n++){
      if(p+46>bytes.length || view.getUint32(p,true)!==0x02014b50) throw new Error('Entrada ZIP inválida.');
      const method=view.getUint16(p+10,true), csize=view.getUint32(p+20,true), usize=view.getUint32(p+24,true);
      const nl=view.getUint16(p+28,true), el=view.getUint16(p+30,true), cl=view.getUint16(p+32,true), off=view.getUint32(p+42,true);
      const name=bytesToText(bytes.subarray(p+46,p+46+nl)).replace(/\\/g,'/');
      if(name.startsWith('/') || name.split('/').includes('..')) throw new Error('Caminho inseguro no pacote.');
      if(usize>LIMIT_ENTRY || (csize && usize/csize>150)) throw new Error('Uma entrada ZIP excede o limite permitido.');
      total+=usize; if(total>LIMIT_TOTAL) throw new Error('O conteúdo descompactado ultrapassa 30 MiB.');
      entries.set(name,{method,csize,usize,off}); p+=46+nl+el+cl;
    }
    async function read(name){
      const entry=entries.get(name); if(!entry) return null;
      const o=entry.off; if(view.getUint32(o,true)!==0x04034b50) throw new Error('Cabeçalho ZIP inválido.');
      const nl=view.getUint16(o+26,true), el=view.getUint16(o+28,true), start=o+30+nl+el, compressed=bytes.slice(start,start+entry.csize);
      let raw;
      if(entry.method===0) raw=compressed;
      else if(entry.method===8 && typeof DecompressionStream==='function'){
        try { raw=new Uint8Array(await new Response(new Blob([compressed]).stream().pipeThrough(new DecompressionStream('deflate-raw'))).arrayBuffer()); }
        catch(_){ throw new Error('Este navegador não consegue descompactar este pacote Office.'); }
      } else throw new Error('Método de compactação ZIP não suportado.');
      if(raw.length!==entry.usize) throw new Error('Tamanho ZIP inconsistente.');
      return raw;
    }
    return {entries,read};
  }
  function xml(bytes){ const doc=new DOMParser().parseFromString(bytesToText(bytes),'application/xml'); if(doc.querySelector('parsererror')) throw new Error('XML inválido no arquivo importado.'); return doc; }
  function byLocal(root,name){ return Array.from(root.getElementsByTagName('*')).filter((el)=>el.localName===name); }
  function descendants(el,name){ return Array.from(el.getElementsByTagName('*')).filter((x)=>x.localName===name); }
  function direct(el,name){ return Array.from(el.children||[]).filter((x)=>x.localName===name); }
  function inlineText(el, opts){
    const parts=[]; const walk=(n,st)=>{
      if(n.nodeType===3){ if(n.nodeValue) parts.push(run(n.nodeValue,st.marks,st.href)); return; }
      if(n.nodeType!==1) return;
      const tag=n.localName.toLowerCase(); if(['script','style','noscript','svg','canvas','object','embed','form','input','button'].includes(tag)) return;
      const next={marks:st.marks.slice(),href:st.href};
      if(['b','strong'].includes(tag)) next.marks.push('bold'); if(['i','em'].includes(tag)) next.marks.push('italic'); if(tag==='u') next.marks.push('underline'); if(['s','del','strike'].includes(tag)) next.marks.push('strike'); if(tag==='code') next.marks.push('code');
      if(tag==='a') next.href=safeAction(n.getAttribute('href'));
      if(tag==='br') { parts.push(run('\n',next.marks,next.href)); return; }
      Array.from(n.childNodes).forEach((c)=>walk(c,next));
    };
    walk(el,{marks:[],href:null});
    return parts.filter((r)=>r.text.length>0).slice(0,1000);
  }
  function tableFromHtml(table,warnings){
    const trs=Array.from(table.querySelectorAll('tr')).slice(0,20); if(!trs.length) return null;
    const cols=Math.min(12,Math.max(1,...trs.map((r)=>r.querySelectorAll('th,td').length)));
    if(table.querySelectorAll('tr').length>20 || trs.some((r)=>r.querySelectorAll('th,td').length>12)) warnings.push('Tabela limitada a 20 linhas e 12 colunas.');
    const header=!!(trs[0]&&trs[0].querySelector('th'));
    const rows=trs.map((tr)=>({id:id(),type:'core/table-row',attributes:{},content:'',children:Array.from({length:cols},(_,i)=>{const cell=tr.querySelectorAll('th,td')[i];const content=cell?inlineText(cell,{}):[];return {id:id(),type:'core/table-cell',attributes:{},children:[],content:content.length?content:[run(' ')]};})}));
    return {id:id(),type:'core/table',attributes:{header},children:rows,content:''};
  }
  function parseHtml(source,warnings){
    const doc=new DOMParser().parseFromString(source,'text/html');
    doc.querySelectorAll('script,style,noscript,form,object,embed,svg,canvas,template').forEach((n)=>{if(n.localName==='canvas')warnings.push('Elemento Canvas HTML ignorado por segurança; importado apenas o conteúdo textual.');n.remove();});
    const blocks=[];
    const parseList=(el)=>{const items=Array.from(el.children).filter((x)=>x.localName==='li').slice(0,100);if(!items.length)return null;return {id:id(),type:'core/list',attributes:{ordered:el.localName==='ol'},children:items.map((li)=>textBlock('core/list-item',li.textContent.trim()||' ')),content:''};};
    const walk=(el)=>{
      const tag=el.localName.toLowerCase();
      if(/^h[1-6]$/.test(tag)){const text=inlineText(el,{});if(text.length)blocks.push({id:id(),type:'core/heading',attributes:{level:Number(tag[1])},children:[],content:text});return;}
      if(tag==='p'||tag==='figcaption'){const text=inlineText(el,{});if(text.length)blocks.push({id:id(),type:'core/paragraph',attributes:{},children:[],content:text});return;}
      if(tag==='ul'||tag==='ol'){const list=parseList(el);if(list)blocks.push(list);return;}
      if(tag==='blockquote'){const text=inlineText(el,{});if(text.length)blocks.push({id:id(),type:'core/quote',attributes:{},children:[],content:text});return;}
      if(tag==='hr'){blocks.push({id:id(),type:'core/separator',attributes:{},children:[],content:''});return;}
      if(tag==='table'){const t=tableFromHtml(el,warnings);if(t)blocks.push(t);return;}
      if(tag==='img'){const src=localAsset(el.getAttribute('src'),['.jpg','.jpeg','.png','.gif','.webp','.avif'],['image/','view/image/blog/']);if(src)blocks.push({id:id(),type:'core/image',attributes:{src,alt:el.getAttribute('alt')||'',title:el.getAttribute('title')||''},children:[],content:''});else warnings.push('Imagem externa ou não cadastrada foi omitida; use um arquivo da biblioteca local.');return;}
      if(tag==='audio'){const raw=el.getAttribute('src')||(el.querySelector('source')&&el.querySelector('source').getAttribute('src'));const src=localAsset(raw,['.mp3','.ogg','.wav'],['view/audio/blog/']);if(src)blocks.push({id:id(),type:'core/audio',attributes:{src},children:[],content:[run(el.getAttribute('aria-label')||'Áudio',[],null)]});else warnings.push('Áudio não importado: somente MP3/OGG/WAV já existentes na biblioteca local são aceitos; nenhum arquivo foi enviado.');return;}
      if(tag==='iframe'||tag==='video'){const raw=el.getAttribute('src')||(el.querySelector('source')&&el.querySelector('source').getAttribute('src'));const video=safeVideoEmbed(raw);if(video){blocks.push({id:id(),type:'core/video_embed',attributes:{url:video},children:[],content:''});}else warnings.push('Vídeo local/externo omitido; o importador só cria blocos para fontes de vídeo já permitidas pelo editor.');return;}
      if(['html','body','main','article','section','div','figure'].includes(tag)){Array.from(el.children).forEach(walk);return;}
      if(['span','strong','b','em','i','u','s','del','code','a','br'].includes(tag)) return;
      Array.from(el.children||[]).forEach(walk);
    };
    Array.from(doc.body.children).forEach(walk);
    return blocks.slice(0,1000);
  }
  function wordRuns(p,rels){
    const runs=[]; const w='http://schemas.openxmlformats.org/wordprocessingml/2006/main',r='http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    const walk=(el,href)=>{
      if(el.localName==='hyperlink'){const idv=el.getAttributeNS(r,'id');const target=idv&&rels[idv]?safeAction(rels[idv]):null;Array.from(el.children).forEach((x)=>walk(x,target));return;}
      if(el.localName==='r'){
        const prop=Array.from(el.children).find((x)=>x.localName==='rPr');const marks=[];
        if(prop){for(const [tag,mark] of [['b','bold'],['i','italic'],['u','underline'],['strike','strike']])if(prop.getElementsByTagNameNS(w,tag).length)marks.push(mark);}
        const pieces=Array.from(el.getElementsByTagNameNS(w,'t')).map((x)=>x.textContent).join('')+(el.getElementsByTagNameNS(w,'tab').length?'\t':'')+(el.getElementsByTagNameNS(w,'br').length?'\n':'');
        if(pieces)runs.push(run(pieces,marks,href));return;
      }
      Array.from(el.children||[]).forEach((x)=>walk(x,href));
    };
    Array.from(p.children||[]).forEach((x)=>walk(x,null)); return runs;
  }
  async function docx(file,warnings){
    const zip=await unzip(file), docBytes=await zip.read('word/document.xml');if(!docBytes)throw new Error('Documento Word sem word/document.xml.');
    const relBytes=await zip.read('word/_rels/document.xml.rels'), rels={};
    if(relBytes){const rd=xml(relBytes);byLocal(rd,'Relationship').forEach((r)=>{if(r.getAttribute('TargetMode')==='External')rels[r.getAttribute('Id')]=r.getAttribute('Target');});}
    const d=xml(docBytes), body=byLocal(d,'body')[0];if(!body)throw new Error('Corpo do Word não encontrado.');const blocks=[];
    for(const el of Array.from(body.children)){
      if(el.localName==='p'){
        const runs=wordRuns(el,rels);if(!runs.length)continue;
        const pPr=Array.from(el.children).find((x)=>x.localName==='pPr');const style=pPr&&Array.from(pPr.children).find(x=>x.localName==='pStyle');const styleId=style&&(style.getAttribute('w:val')||style.getAttribute('val'))||'';const m=styleId.match(/^Heading([1-6])$/i);
        blocks.push({id:id(),type:m?'core/heading':'core/paragraph',attributes:m?{level:Number(m[1])}:{},children:[],content:runs});
      } else if(el.localName==='tbl'){
        const trs=Array.from(el.children).filter((x)=>x.localName==='tr').slice(0,20);if(!trs.length)continue;
        const cols=Math.min(12,Math.max(1,...trs.map((r)=>Array.from(r.children).filter((x)=>x.localName==='tc').length)));
        const rows=trs.map((tr)=>({id:id(),type:'core/table-row',attributes:{},content:'',children:Array.from({length:cols},(_,i)=>{const cell=Array.from(tr.children).filter((x)=>x.localName==='tc')[i];const p=cell&&byLocal(cell,'p')[0];return {id:id(),type:'core/table-cell',attributes:{},children:[],content:p?wordRuns(p,rels):[run(' ')]};})}));blocks.push({id:id(),type:'core/table',attributes:{header:false},children:rows,content:''});if(Array.from(el.children).length>20)warnings.push('Tabela Word limitada a 20 linhas e 12 colunas.');
      }
    }
    if(Array.from(zip.entries.keys()).some((n)=>n.startsWith('word/media/')))warnings.push('Imagens e mídias incorporadas no Word não foram extraídas nem enviadas. Use referências já cadastradas na biblioteca local.');
    return blocks;
  }
  function cellText(cell,shared){const t=cell.getAttribute('t');if(t==='s'){const v=Array.from(cell.children).find(x=>x.localName==='v');return v?shared[Number(v.textContent)]||'':'';}if(t==='inlineStr')return Array.from(cell.getElementsByTagName('*')).filter(x=>x.localName==='t').map(x=>x.textContent).join('');const v=Array.from(cell.children).find(x=>x.localName==='v');return v?v.textContent:'';}
  async function xlsx(file,warnings){
    const zip=await unzip(file);const wbBytes=await zip.read('xl/workbook.xml');if(!wbBytes)throw new Error('Arquivo Excel sem workbook.xml.');
    const wb=xml(wbBytes), sheets=byLocal(wb,'sheet');if(!sheets.length)throw new Error('A planilha não contém abas.');
    const relId=sheets[0].getAttribute('r:id')||sheets[0].getAttribute('id');const relBytes=await zip.read('xl/_rels/workbook.xml.rels');let sheetPath='xl/worksheets/sheet1.xml';
    if(relBytes&&relId){const rel=xml(relBytes);const hit=byLocal(rel,'Relationship').find(x=>x.getAttribute('Id')===relId);if(hit){let target=hit.getAttribute('Target').replace(/^\//,'');const parts=(target.startsWith('xl/')?target:'xl/'+target).split('/');const normalized=[];for(const part of parts){if(part==='..')normalized.pop();else if(part!=='.'&&part)normalized.push(part);}sheetPath=normalized.join('/');}}
    const sheetBytes=await zip.read(sheetPath);if(!sheetBytes)throw new Error('Não foi possível localizar a primeira aba do Excel.');
    const sstBytes=await zip.read('xl/sharedStrings.xml'),shared=[];if(sstBytes){const ss=xml(sstBytes);byLocal(ss,'si').forEach(si=>shared.push(Array.from(si.getElementsByTagName('*')).filter(x=>x.localName==='t').map(x=>x.textContent).join('')));}
    const sh=xml(sheetBytes), rows=byLocal(sh,'row').slice(0,20);if(!rows.length)throw new Error('A primeira aba está vazia.');
    const colIndex=(ref)=>{const letters=(ref.match(/^[A-Z]+/i)||['A'])[0].toUpperCase();let n=0;for(const ch of letters)n=n*26+ch.charCodeAt(0)-64;return n-1;};
    let maxCol=0;const matrix=rows.map((row)=>{const vals=[];byLocal(row,'c').forEach(c=>{const idx=colIndex(c.getAttribute('r')||'A1');if(idx>=12){warnings.push('Planilha limitada a 12 colunas.');return;}if(c.getElementsByTagName('*')&&Array.from(c.getElementsByTagName('*')).some(x=>x.localName==='f')){vals[idx]='';warnings.push('Fórmulas do Excel foram omitidas; valores calculados não são importados.');return;}vals[idx]=cellText(c,shared);maxCol=Math.max(maxCol,idx+1);});return vals;});
    const cols=Math.max(1,Math.min(12,maxCol));const outRows=matrix.map(vals=>({id:id(),type:'core/table-row',attributes:{},content:'',children:Array.from({length:cols},(_,i)=>({id:id(),type:'core/table-cell',attributes:{},children:[],content:[run(String(vals[i]||' '),[],null)]}))}));
    if(byLocal(sh,'row').length>20)warnings.push('Planilha limitada às primeiras 20 linhas.');
    return [{id:id(),type:'core/table',attributes:{header:true},children:outRows,content:''}];
  }
  async function pptx(file,warnings){
    const zip=await unzip(file);const names=Array.from(zip.entries.keys()).filter(n=>/^ppt\/slides\/slide\d+\.xml$/.test(n)).sort((a,b)=>Number(a.match(/slide(\d+)/)[1])-Number(b.match(/slide(\d+)/)[1]));if(!names.length)throw new Error('A apresentação não contém slides legíveis.');
    const blocks=[];for(let i=0;i<Math.min(names.length,100);i++){const d=xml(await zip.read(names[i]));const paragraphs=byLocal(d,'p');let slideHas=false;for(const p of paragraphs){const text=Array.from(p.getElementsByTagName('*')).filter(x=>x.localName==='t').map(x=>x.textContent).join(' ').trim();if(!text)continue;const ph=byLocal(p,'ph')[0];const isTitle=ph&&['title','ctrTitle'].includes(ph.getAttribute('type'));blocks.push(textBlock(isTitle?'core/heading':'core/paragraph',text,isTitle?{level:2}:{}));slideHas=true;}if(i<Math.min(names.length,100)-1&&slideHas)blocks.push({id:id(),type:'core/separator',attributes:{},children:[],content:''});}
    if(names.length>100)warnings.push('Apresentação limitada aos primeiros 100 slides.');if(Array.from(zip.entries.keys()).some(n=>n.startsWith('ppt/media/')))warnings.push('Mídias incorporadas no PowerPoint não foram extraídas nem enviadas; referências locais existentes podem ser inseridas depois.');return blocks;
  }
  async function imscc(file,warnings){
    const zip=await unzip(file), names=Array.from(zip.entries.keys()).filter(n=>/\.html?$/i.test(n)&&(/(^|\/)(wiki_content|pages)(\/|$)/i.test(n)||/wiki/i.test(n))).sort().slice(0,100);if(!names.length)throw new Error('Não encontrei páginas HTML de Canvas (wiki_content/pages) no IMSCC.');
    const blocks=[];for(let pageIndex=0;pageIndex<names.length;pageIndex++){const name=names[pageIndex];const html=bytesToText(await zip.read(name));const doc=new DOMParser().parseFromString(html,'text/html');const title=(doc.querySelector('h1')||doc.querySelector('title'));const label=title&&title.textContent.trim()||name.split('/').pop().replace(/\.html?$/i,'').replace(/[-_]/g,' ');if(pageIndex>0)blocks.push({id:id(),type:'core/separator',attributes:{},children:[],content:''});blocks.push(textBlock('core/heading',label,{level:2}));blocks.push(...parseHtml(html,warnings));}
    if(names.length>=100)warnings.push('Curso limitado às primeiras 100 páginas HTML.');return blocks;
  }
  async function importFile(file){
    const warnings=[];const ext=(file.name.split('.').pop()||'').toLowerCase();let blocks;
    if(['html','htm','xhtml'].includes(ext)) blocks=parseHtml(bytesToText(new Uint8Array(await file.arrayBuffer())),warnings);
    else if(ext==='docx') blocks=await docx(file,warnings);
    else if(ext==='xlsx') blocks=await xlsx(file,warnings);
    else if(ext==='pptx') blocks=await pptx(file,warnings);
    else if(ext==='imscc') blocks=await imscc(file,warnings);
    else throw new Error('Formato não suportado. Aceitos: DOCX, XLSX, PPTX, HTML e IMSCC (Canvas LMS).');
    if(!blocks.length) throw new Error('Nenhum conteúdo compatível foi encontrado no arquivo.');
    const document={format_version:2,blocks};
    if(new TextEncoder().encode(JSON.stringify(document)).length>4*1024*1024)throw new Error('O resultado convertido supera o limite v2 de 4 MiB.');
    return {document,warnings};
  }
  global.ReamurPageImporter={importFile,formats:['.docx','.xlsx','.pptx','.html','.htm','.xhtml','.imscc'],maxFileBytes:LIMIT_FILE};
})(window);
