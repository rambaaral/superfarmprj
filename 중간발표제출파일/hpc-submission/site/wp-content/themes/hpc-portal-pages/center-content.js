(()=>{'use strict';
function monthLayout(year,month,events){
 const key=d=>d.getFullYear()+'-'+String(d.getMonth()+1).padStart(2,'0')+'-'+String(d.getDate()).padStart(2,'0');
 const first=new Date(year,month,1),last=new Date(year,month+1,0),offset=first.getDay(),weeks=Math.ceil((offset+last.getDate())/7);
 const sorted=events.map((e,id)=>({...e,id})).filter(e=>e.start<=key(last)&&e.end>=key(first)).sort((a,b)=>a.start.localeCompare(b.start)||b.end.localeCompare(a.end)||a.id-b.id);
 const lanes=[];sorted.forEach(e=>{let lane=lanes.findIndex(end=>end<e.start);if(lane<0)lane=lanes.length;lanes[lane]=e.end;e.lane=lane;});
 return Array.from({length:weeks},(_,w)=>{
 const dates=Array.from({length:7},(_,c)=>{const d=w*7+c-offset+1;return d>0&&d<=last.getDate()?key(new Date(year,month,d)):null;});
 const start=dates.find(Boolean),end=dates.filter(Boolean).pop();
 const bars=sorted.filter(e=>e.start<=end&&e.end>=start).map(e=>({...e,colStart:dates.findIndex(d=>d&&d>=e.start),colEnd:dates.reduce((n,d,c)=>d&&d<=e.end?c:n,0),continuesBefore:e.start<start,continuesAfter:e.end>end}));
 return {dates,bars};
 });
}
document.querySelectorAll('.hpcc-calendar').forEach(panel=>{
 const cfg=JSON.parse(panel.querySelector('.hpcc-events').textContent),cal=panel.querySelector('[data-center-calendar]'),[ty,tm]=cfg.today.split('-').map(Number);
 let year=ty,month=tm-1,chosen=cfg.today;
 const key=(y,m,d)=>y+'-'+String(m+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
 const matching=date=>cfg.events.filter(e=>e.start<=date&&e.end>=date);
 const choose=date=>{chosen=date;render();cal.querySelector('[aria-pressed="true"]')?.focus({preventScroll:true});};
 function render(){
 cal.querySelector('[data-calendar-title]').textContent=year+'년 '+(month+1)+'월';
 panel.querySelector('[data-selected-date]').textContent=chosen.replaceAll('-','.');
 const list=panel.querySelector('[data-event-list]');list.replaceChildren();
 const events=matching(chosen);
 if(!events.length){const p=document.createElement('p');p.textContent='등록된 일정이 없습니다.';list.append(p);}
 events.forEach(e=>{const item=document.createElement('article'),h=document.createElement('h4'),date=document.createElement('p');h.textContent=e.title;date.textContent=e.start===e.end?e.start:e.start+' ~ '+e.end;item.append(h,date);if(e.description){const p=document.createElement('p');p.className='hpcc-event-description';p.textContent=e.description;item.append(p);}list.append(item);});
 const body=cal.querySelector('[data-month-grid]');body.replaceChildren();
 monthLayout(year,month,cfg.events).forEach(week=>{
 const row=document.createElement('div');row.className='hpcc-week';row.setAttribute('role','group');row.setAttribute('aria-label',week.dates.find(Boolean)+' 주');
 week.dates.forEach((date,col)=>{
 const cell=document.createElement('div');cell.className='hpcc-day-cell';cell.style.gridColumn=String(col+1);cell.style.gridRow='1 / -1';row.append(cell);
 if(!date)return;
 const b=document.createElement('button');b.type='button';b.className='hpcc-day';b.textContent=String(Number(date.slice(-2)));b.style.gridColumn=String(col+1);b.style.gridRow='1';b.dataset.date=date;
 b.setAttribute('aria-label',date+(date===cfg.today?', 오늘':'')+', 일정 '+matching(date).length+'건');b.setAttribute('aria-pressed',String(date===chosen));if(col===0)b.classList.add('is-sunday');if(col===6)b.classList.add('is-saturday');if(date===cfg.today){b.classList.add('is-today');b.setAttribute('aria-current','date');}
 b.onclick=()=>choose(date);row.append(b);
 });
 week.bars.forEach(e=>{
 const b=document.createElement('button');b.type='button';b.className='hpcc-event-bar'+(e.continuesBefore?' continues-before':'')+(e.continuesAfter?' continues-after':'');
 b.style.gridColumn=(e.colStart+1)+' / '+(e.colEnd+2);b.style.gridRow=String(e.lane+2);
 const color=[...e.title].reduce((n,c)=>(n+c.codePointAt(0))%4,0);b.dataset.color=String(color);
 b.textContent=(e.continuesBefore?'‹ ':'')+e.title+(e.continuesAfter?' ›':'');
 b.title=e.title+' · '+e.start+(e.start!==e.end?' ~ '+e.end:'');b.setAttribute('aria-label',b.title+', 상세 보기');
 b.onclick=()=>choose(week.dates[e.colStart]);row.append(b);
 });
 const lanes=week.bars.reduce((n,e)=>Math.max(n,e.lane+1),0);row.style.gridTemplateRows='36px'+(lanes?' repeat('+lanes+', minmax(26px, auto))':'')+' minmax(10px, 1fr)';
 body.append(row);
 });
 }
 cal.querySelectorAll('[data-month]').forEach(b=>b.addEventListener('click',()=>{if(b.dataset.month==='today'){year=ty;month=tm-1;chosen=cfg.today;}else{const d=new Date(year,month+Number(b.dataset.month),1);year=d.getFullYear();month=d.getMonth();chosen=key(year,month,1);}render();}));render();
});
document.querySelectorAll('[data-hpcc-carousel]').forEach(root=>{
 const slides=[...root.querySelectorAll('.hpcc-slide')];if(!slides.length)return;
 const pause=root.querySelector('[data-slide-pause]'),count=root.querySelector('[data-slide-count]'),prev=root.querySelector('[data-slide-prev]'),next=root.querySelector('[data-slide-next]'),motion=matchMedia('(prefers-reduced-motion: reduce)');
 let index=0,paused=motion.matches,timer;
 const show=i=>{index=(i+slides.length)%slides.length;slides.forEach((s,n)=>s.hidden=n!==index);count.textContent=(index+1)+'/'+slides.length;count.setAttribute('aria-label','전체 '+slides.length+'개 중 '+(index+1)+'번째 이미지');};
 const sync=()=>{clearInterval(timer);const label=paused?'자동 넘김 시작':'자동 넘김 정지';pause.setAttribute('aria-pressed',String(paused));pause.setAttribute('aria-label',label);pause.title=label;pause.querySelector('span').textContent=paused?'▶':'Ⅱ';if(slides.length>1&&!paused&&!document.hidden&&!root.matches(':hover')&&!root.contains(document.activeElement))timer=setInterval(()=>show(index+1),5000);};
 if(slides.length===1){prev.disabled=true;next.disabled=true;pause.disabled=true;pause.title='표시할 이미지가 1개입니다.';}
 prev.onclick=()=>{show(index-1);sync();};next.onclick=()=>{show(index+1);sync();};pause.onclick=()=>{paused=!paused;sync();};
 root.addEventListener('mouseenter',()=>clearInterval(timer));root.addEventListener('mouseleave',sync);root.addEventListener('focusin',()=>clearInterval(timer));root.addEventListener('focusout',()=>setTimeout(sync,0));document.addEventListener('visibilitychange',sync);motion.addEventListener('change',()=>{paused=motion.matches;sync();});window.addEventListener('pagehide',()=>clearInterval(timer));show(0);sync();
});
})();