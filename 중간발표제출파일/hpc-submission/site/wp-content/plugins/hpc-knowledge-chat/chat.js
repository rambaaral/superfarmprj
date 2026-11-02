document.querySelectorAll('.hkc').forEach(root=>{
 const form=root.querySelector('form'),input=form.elements.question,log=root.querySelector('.hkc-log');let busy=false;
 function message(text,cls){const p=document.createElement('p');p.className=cls;p.textContent=text;log.append(p);return p;}
 function source(parent,s){try{const u=new URL(s.url,location.href);if(u.origin!==location.origin)return;const a=document.createElement('a');a.href=u.href;a.textContent=`${s.kind} · ${s.title}`;parent.append(a);}catch{}}
 root.querySelectorAll('.hkc-examples button').forEach(b=>b.addEventListener('click',()=>{if(!busy){input.value=b.textContent;form.requestSubmit();}}));
 form.addEventListener('submit',async e=>{
  e.preventDefault();if(busy||!input.value.trim())return;busy=true;
  const q=input.value.trim();input.value='';message(q,'hkc-question');const answer=message(window.hpctText("t70cc85805bf1daf5f962"),'hkc-answer');
  root.querySelectorAll('button').forEach(b=>b.disabled=true);
  try{
   const headers={'Content-Type':'application/json'};if(root.dataset.nonce)headers['X-WP-Nonce']=root.dataset.nonce;
   const r=await fetch(root.dataset.api,{method:'POST',credentials:'same-origin',cache:'no-store',headers,body:JSON.stringify({question:q}),signal:AbortSignal.timeout(50000)});
   if(r.status===403){answer.textContent=window.hpctText("t725eba104bab76cace17");return;}
   if(!r.ok)throw Error('request');const data=await r.json();answer.textContent=data.answer;
   const refs=document.createElement('div');refs.className='hkc-sources';log.append(refs);(data.sources||[]).forEach(s=>source(refs,s));
   if(!data.sources?.length && data.links?.qna)source(refs,{url:data.links.qna,title:window.hpctText("td43f0e91c65e26ebb432"),kind:window.hpctText("te58442c8b6eccc904b0e")});
   message(data.mode,'hkc-mode');log.scrollTop=log.scrollHeight;
  }catch{answer.textContent=window.hpctText("t7dbc3c51bf65ce3493cc");}
  finally{busy=false;root.querySelectorAll('button').forEach(b=>b.disabled=false);input.focus();}
 });
});
