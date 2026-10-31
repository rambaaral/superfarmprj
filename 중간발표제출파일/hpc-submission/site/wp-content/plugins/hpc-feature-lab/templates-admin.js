document.addEventListener('DOMContentLoaded',()=>{
 const rows=document.querySelector('#hpc-field-rows');if(!rows)return;
 const summaries=[document.querySelector('#hpc-title-field'),document.querySelector('#hpc-name-field')];
 function refresh(){const all=[...rows.children];all.forEach((row,i)=>row.querySelectorAll('[name]').forEach(el=>el.name=el.name.replace(/^fields\[[^\]]+\]/,`fields[${i}]`)));
  for(const select of summaries){const value=select.value||select.dataset.selected;select.replaceChildren(new Option('항목을 선택해 주세요',''));for(const row of all){const id=row.querySelector('[data-field-id]').value,label=row.querySelector('[data-field-label]').value;select.add(new Option(label||'이름 없는 항목',id));}select.value=value;}
 }
 rows.addEventListener('input',e=>{if(e.target.matches('[data-field-label]'))refresh();});
 rows.addEventListener('click',e=>{const row=e.target.closest('.hpc-field-row');if(!row)return;if(e.target.classList.contains('hpc-remove'))row.remove();if(e.target.classList.contains('hpc-up')&&row.previousElementSibling)rows.insertBefore(row,row.previousElementSibling);if(e.target.classList.contains('hpc-down')&&row.nextElementSibling)rows.insertBefore(row.nextElementSibling,row);refresh();});
 document.querySelector('#hpc-add-field').addEventListener('click',()=>{const id='f_'+crypto.randomUUID().replaceAll('-','').slice(0,12);const box=document.createElement('div');box.innerHTML=document.querySelector('#hpc-field-template').innerHTML.replaceAll('__ID__',id).replaceAll('__INDEX__',String(rows.children.length));rows.append(box.firstElementChild);refresh();rows.lastElementChild.querySelector('[data-field-label]').focus();});
 refresh();
});
