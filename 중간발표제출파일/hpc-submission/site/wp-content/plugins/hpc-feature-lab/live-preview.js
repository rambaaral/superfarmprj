const cfg=JSON.parse(document.querySelector('#hpc-preview-config').textContent),form=document.querySelector('main form:has(input[name=op][value=save])');
if(form){
 document.body.classList.add('hpc-with-preview');const layout=document.createElement('div');layout.className='hpc-compose-layout';form.before(layout);const entry=document.createElement('div');entry.className='hpc-entry';entry.append(form);layout.append(entry);
 const aside=document.createElement('aside');aside.className='hpc-preview';aside.innerHTML=(""+"<h2>"+window.hpctEscape(window.hpctText("tbfe79f60760f64efb0eb"))+"</h2>"+""+"<p>"+window.hpctEscape(window.hpctText("te0473cd89a8ab04d9d8c"))+"</p>"+""+"<p class=\"hpc-preview-status\" role=\"status\">"+""+"</p>"+""+("<div class=\"hpc-preview-pages\" tabindex=\"0\" role=\"region\" aria-label=\""+window.hpctEscape(window.hpctText("js_preview_pages"))+"\">")+""+"</div>"+"");layout.append(aside);const toggle=document.createElement('button');toggle.type='button';toggle.className='hpc-preview-toggle';toggle.textContent=window.hpctText("t99f36dc06b611ccf6502");layout.before(toggle);
 const query=matchMedia('(min-width:1200px) and (min-aspect-ratio:4/3)'),status=aside.querySelector('.hpc-preview-status'),pages=aside.querySelector('.hpc-preview-pages');let open=false,seq=0,timer,running=false,core,urls=[];
 const visible=()=>query.matches||open;
 const invalidate=()=>{seq++;aside.dataset.stale='true';status.textContent=window.hpctText("td659e6c65fcbe0546dd1");clearTimeout(timer);if(visible())timer=setTimeout(render,650);};
 async function render(){if(running||!visible())return;running=true;const current=seq;let doc;
  try{
   if(!core){const ctx=document.createElement('canvas').getContext('2d');globalThis.measureTextWidth=(font,text)=>{ctx.font=font;return ctx.measureText(text).width;};core=await import('./vendor/core/rhwp.js');await core.default({module_or_path:cfg.base+'vendor/core/rhwp_bg.wasm'});}
   const data=new FormData(form);data.set('action','hpc_live_preview');data.set('nonce',cfg.nonce);data.set('application_id',String(cfg.id));data.set('template_id',String(cfg.template));
   const r=await fetch(cfg.ajax,{method:'POST',body:data,cache:'no-store'});if(!r.ok){let msg=window.hpctText("t079b5e102e4dba452458");try{msg=(await r.json()).message||msg;}catch{}throw new Error(msg);}const bytes=new Uint8Array(await r.arrayBuffer());if(current!==seq)return;
   doc=new core.HwpDocument(bytes);const total=doc.pageCount(),fragment=document.createDocumentFragment(),next=[];if(total>20)throw new Error(window.hpctText("tb5ca9c0d0fe1b0ab3f20"));
   for(let i=0;i<total;i++){const svg=doc.renderPageSvg(i),url=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml'})),img=new Image();img.src=url;img.alt=window.hpctText("t1fca4e47262105074abd")+(i+1)+window.hpctText("t077b6c57af048c3baee6");next.push(url);fragment.append(img);}
   if(current!==seq){next.forEach(u=>URL.revokeObjectURL(u));return;}pages.replaceChildren(fragment);urls.forEach(u=>URL.revokeObjectURL(u));urls=next;aside.dataset.stale='false';status.textContent=window.hpctText("t082e6d96ec22c7dce43c")+total+window.hpctText("t077b6c57af048c3baee6");aside.dataset.sequence=String(current);
  }catch(e){if(current===seq){status.textContent=e.message;aside.dataset.stale='true';}}finally{doc?.free();running=false;if(current!==seq&&visible())timer=setTimeout(render,100);}
 }
 toggle.onclick=()=>{open=!open;aside.dataset.open=String(open);toggle.textContent=open?window.hpctText("tc523a87e451fa7fdfb4d"):window.hpctText("t99f36dc06b611ccf6502");if(open)invalidate();};query.addEventListener('change',()=>{if(visible())invalidate();});form.addEventListener('input',invalidate);form.addEventListener('change',invalidate);if(visible())invalidate();
 window.addEventListener('pagehide',()=>{clearTimeout(timer);urls.forEach(u=>URL.revokeObjectURL(u));});
}
