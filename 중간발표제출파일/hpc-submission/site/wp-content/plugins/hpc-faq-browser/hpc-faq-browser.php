<?php
/* Plugin Name: HPC FAQ Browser
Description: Searchable FAQ with existing editorial content and categories.
Version: 1.0.0
*/
defined('ABSPATH') || exit;
add_shortcode('hpc_faq_browser', function(){
 $groups=[''=>hpct_text('tb2c0a681a5dca13af845'),'g1'=>hpct_text('tb5ed3ea4fc1ca54c00ef'),'g2'=>hpct_text('t57fa9e2ca04c8f5dcfc3'),'g3'=>hpct_text('t465f222a27475e7fc725'),'g4'=>hpct_text('t7d293567e38336f2e5e5'),'g5'=>hpct_text('tb5bf553de4e00f74778f')];
 $posts=get_posts(['post_type'=>'hkc_faq','post_status'=>'publish','numberposts'=>-1,'orderby'=>['menu_order'=>'ASC','ID'=>'ASC']]);
 $counts=array_fill_keys(array_keys($groups),0);
 foreach($posts as $p){$g=get_post_meta($p->ID,'_hpb_group',true);if(isset($counts[$g]))$counts[$g]++;}
 ob_start(); ?>
 <section class="hfaq" aria-label="<?php echo esc_attr(hpct_text('t6bd3787db4f2c573cabf')); ?>">
 <p class="hfaq-intro"><?php echo esc_html(hpct_text('tc9ada08d1db0be860ee8')); ?></p>
 <p class="hfaq-notice"><?php echo esc_html(hpct_text('tec1530b50fe80053bcd8')); ?></p>
 <div class="hfaq-tools" hidden>
 <label for="hfaq-search"><?php echo esc_html(hpct_text('t057246e61eb38431c101')); ?></label>
 <div class="hfaq-search-row"><input id="hfaq-search" type="search" placeholder="<?php echo esc_attr(hpct_text('t25ba058decf2ecae9ebd')); ?>" autocomplete="off" aria-controls="hfaq-results"><button type="button" id="hfaq-reset"><?php echo esc_html(hpct_text('t893f3d9463683c71bf5e')); ?></button></div>
 <div class="hfaq-filters" role="group" aria-label="<?php echo esc_attr(hpct_text('t3e860cd83a8542919551')); ?>">
 <button type="button" data-group="all" aria-pressed="true"><?php echo esc_html(hpct_text('t5e1c40e1414b718791c6')); ?><span><?php echo count($posts); ?></span></button>
 <?php foreach($groups as $g=>$label): ?><button type="button" data-group="<?php echo esc_attr($g); ?>" aria-pressed="false"><?php echo esc_html($label); ?> <span><?php echo (int)$counts[$g]; ?></span></button><?php endforeach; ?>
 </div>
 </div>
 <div class="hfaq-toolbar"><p id="hfaq-count" role="status" aria-live="polite"><?php echo esc_html(hpct_text('t5e1c40e1414b718791c6')); ?><?php echo count($posts); ?><?php echo esc_html(hpct_text('t526161fec90708f97c89')); ?></p><button type="button" id="hfaq-collapse" hidden><?php echo esc_html(hpct_text('t9776b19ae7c07c40f952')); ?></button></div>
 <div id="hfaq-results">
 <?php foreach($groups as $g=>$label): if(!$counts[$g])continue; ?>
 <section class="hfaq-group" data-group="<?php echo esc_attr($g); ?>" aria-label="<?php echo esc_attr($label); ?>">
 <h2><?php echo esc_html($label); ?></h2>
 <?php if($g==='g5'): ?><p class="hfaq-source"><?php echo esc_html(hpct_text('t1fc2fbd9466ef4e3eb5a')); ?><a href="https://www.korea.kr/docViewer/skin/doc.html?fn=383572919aa3711d00b106a153b7dc39&amp;rs=/docViewer/result/2025.10/14/383572919aa3711d00b106a153b7dc39"><?php echo esc_html(hpct_text('tdabcb966198ceceada72')); ?></a></p><?php endif; ?>
 <?php foreach($posts as $p): if(get_post_meta($p->ID,'_hpb_group',true)!==$g)continue;
 $note=preg_replace('/^FAQ\s*\d+\s*·\s*/u','',get_post_meta($p->ID,'_hpb_note',true));
 if($g==='g5'&&$note==='센터를 이해하는 11가지 질문')$note=hpct_text('tddd22a67cdbc74d2464a'); ?>
 <details class="hfaq-item" id="faq-<?php echo (int)$p->ID; ?>">
 <summary><?php if($note): ?><span class="hfaq-badge"><?php echo esc_html($note); ?></span><?php endif; ?><span class="hfaq-question"><?php echo esc_html($p->post_title); ?></span></summary>
 <div class="hfaq-answer"><?php echo wpautop(wp_kses_post($p->post_content)); ?>
 <?php if(strpos($note,'확인 필요')!==false): ?><p class="hfaq-answer-link"><a href="<?php echo esc_url(home_url('/qna/')); ?>"><?php echo esc_html(hpct_text('t59dfdca4e6f4057b2360')); ?></a></p><?php endif; ?>
 <a class="hfaq-permalink" href="#faq-<?php echo (int)$p->ID; ?>"><?php echo esc_html(hpct_text('t51c8a1d007757d8398b4')); ?></a></div>
 </details>
 <?php endforeach; ?></section><?php endforeach; ?>
 </div>
 <div id="hfaq-empty" hidden><strong><?php echo esc_html(hpct_text('t2fdf1245eb2357e9b23a')); ?></strong><p><?php echo esc_html(hpct_text('t4affbdc9aaf697ceee95')); ?></p></div>
 <aside class="hfaq-help"><h2><?php echo esc_html(hpct_text('tde5d7ae1a57965c311d2')); ?></h2><p><?php echo esc_html(hpct_text('t94b918ddd2aeb3b0a30f')); ?></p><a href="<?php echo esc_url(home_url('/qna/')); ?>"><?php echo esc_html(hpct_text('t54de4114eab79b7b610e')); ?></a></aside>
 </section>
 <style>
 .hfaq{--faq-ink:#173d58;--faq-line:#dce5eb;color:#263e4d;line-height:1.7}.hfaq [hidden]{display:none!important}.hfaq-intro{font-size:18px;margin:0 0 12px}.hfaq-notice{padding:13px 16px;background:#f1f6fa;border-left:3px solid #467993;font-size:14px;margin:0 0 24px}.hfaq-tools{background:#f7f9fb;border:1px solid var(--faq-line);border-radius:12px;padding:22px}.hfaq-tools label{display:block;font-weight:700;margin-bottom:8px}.hfaq-search-row{display:flex;gap:8px}.hfaq-search-row input{min-width:0;flex:1;border:1px solid #849baa;border-radius:6px;padding:12px;background:white;color:#183b50;font:inherit}.hfaq button{font:inherit;cursor:pointer;min-height:44px;border:1px solid #bacbd7;border-radius:6px;background:#fff;color:var(--faq-ink);padding:8px 14px}.hfaq :is(button,input,summary,a):focus-visible{outline:3px solid #2276b6;outline-offset:3px}.hfaq-filters{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}.hfaq-filters button[aria-pressed=true]{background:var(--faq-ink);border-color:var(--faq-ink);color:white}.hfaq-filters span{margin-left:5px;font-size:12px;opacity:.85}.hfaq-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;margin:20px 0 8px}.hfaq-toolbar p{margin:0;font-size:14px}.hfaq-toolbar button{font-size:13px}.hfaq-group{margin:24px 0 32px}.hfaq .hfaq-group h2{font-size:20px;margin:0 0 12px;color:var(--faq-ink)}.hfaq-source{font-size:14px;color:#526574}.hfaq-item{border:1px solid var(--faq-line);border-radius:9px;margin-bottom:10px;background:white;scroll-margin-top:90px}.hfaq-item summary{cursor:pointer;padding:18px 42px 18px 18px;list-style:none;position:relative;font-weight:600;line-height:1.65}.hfaq-item summary::-webkit-details-marker{display:none}.hfaq-item summary:after{content:'+';position:absolute;right:18px;top:18px;font-size:22px;font-weight:400;color:#43667f}.hfaq-item[open] summary:after{content:'−'}.hfaq-item[open]{border-color:#93b0c5}.hfaq-badge{display:block;width:fit-content;font-size:12px;color:#725313;background:#fbf5e7;border-radius:4px;padding:2px 7px;margin-bottom:7px;font-weight:500}.hfaq-question{display:block}.hfaq-answer{padding:0 18px 20px;overflow-wrap:anywhere}.hfaq-answer>p:first-child{border-top:1px solid #e7edf2;padding-top:16px}.hfaq a{color:#175d8b;text-decoration:underline;text-underline-offset:3px}.hfaq-permalink{font-size:12px}.hfaq-answer-link{font-size:14px}.hfaq-help{background:#edf4f8;border-radius:10px;padding:24px;margin-top:32px}.hfaq .hfaq-help h2{font-size:20px;margin:0 0 8px}.hfaq-help p{margin:0 0 14px}.hfaq-help>a{display:inline-block;font-weight:700;padding:6px 0}#hfaq-empty{padding:32px 16px;text-align:center;border:1px dashed #bacbd7;border-radius:8px}@media(max-width:600px){.hfaq-tools{padding:15px}.hfaq-intro{font-size:16px}.hfaq-search-row input{font-size:16px;width:100%}.hfaq-search-row{flex-wrap:wrap}.hfaq-filters{gap:6px}.hfaq-filters button{font-size:13px;padding:8px 10px}.hfaq-item summary{padding:16px 38px 16px 14px}.hfaq-answer{padding:0 14px 16px}.hfaq-help{padding:18px}}
 .hfaq .hfaq-filters button[aria-pressed="false"]{background:#fff!important;color:#173d58!important;border:1px solid #bacbd7!important}.hfaq .hfaq-filters button[aria-pressed="true"]{background:#173d58!important;color:#fff!important;border:1px solid #173d58!important}.hfaq .hfaq-group h2{border:0!important;padding:0!important;background:none!important;box-shadow:none!important}.hfaq details.hfaq-item{padding:0!important}.hfaq #hfaq-collapse,.hfaq #hfaq-reset{background:#fff!important;color:#173d58!important}</style>
 <script>
 (function(){const root=document.querySelector('.hfaq');if(!root)return;const input=root.querySelector('#hfaq-search'),buttons=[...root.querySelectorAll('[data-group][aria-pressed]')],groups=[...root.querySelectorAll('.hfaq-group')],items=[...root.querySelectorAll('.hfaq-item')];let selected='all';const norm=s=>s.toLocaleLowerCase().replace(/\s+/g,' ').trim();const texts=new Map(items.map(x=>[x,norm(x.textContent)]));
 function filter(){const words=norm(input.value).split(' ').filter(Boolean);let total=0;groups.forEach(g=>{let count=0;g.querySelectorAll('.hfaq-item').forEach(item=>{const show=(selected==='all'||selected===g.dataset.group)&&words.every(w=>texts.get(item).includes(w));item.hidden=!show;if(show)count++;});g.hidden=count===0;total+=count;});root.querySelector('#hfaq-count').textContent=(input.value.trim()?window.hpctText('t1358e247c33cf4178570'):selected==='all'?window.hpctText('t5e1c40e1414b718791c6'):window.hpctText('te3222e3b0dc335234494'))+total+window.hpctText('t526161fec90708f97c89');root.querySelector('#hfaq-empty').hidden=total>0;buttons.forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.group===selected)));}
 input.addEventListener('input',filter);buttons.forEach(b=>b.addEventListener('click',()=>{selected=b.dataset.group;filter();}));root.querySelector('#hfaq-reset').addEventListener('click',()=>{input.value='';selected='all';filter();input.focus();});root.querySelector('#hfaq-collapse').addEventListener('click',()=>items.forEach(i=>i.open=false));
 function openHash(){const item=items.find(i=>'#'+i.id===location.hash);if(item){input.value='';selected='all';filter();item.open=true;item.scrollIntoView({block:'start'});}}window.addEventListener('hashchange',openHash);root.querySelector('.hfaq-tools').hidden=false;root.querySelector('#hfaq-collapse').hidden=false;openHash();
 })();
 </script>
 <?php return ob_get_clean();
});

// Compatibility names resolve to the independent FAQ presentation.
function hkc_render_faq(){return do_shortcode('[hpc_faq_browser]');}
function hpb_faq_render($group){
 $posts=get_posts(['post_type'=>'hkc_faq','post_status'=>'publish','numberposts'=>-1,'orderby'=>['menu_order'=>'ASC','ID'=>'ASC']]); $html='';
 foreach($posts as $p){if(get_post_meta($p->ID,'_hpb_group',true)!==$group)continue;$note=get_post_meta($p->ID,'_hpb_note',true);$html.='<details style="border:1px solid #dce2e8;border-radius:8px;margin-bottom:12px;padding:18px 20px"><summary style="cursor:pointer;font-weight:600;line-height:1.7">'.($note?'<span style="display:block;font-size:13px;color:#526475">'.esc_html($note).'</span>':'').esc_html($p->post_title).'</summary><div style="line-height:1.9;padding-top:12px">'.wpautop(wp_kses_post($p->post_content)).'</div></details>';}
 return $html;
}
add_action('init',function(){
 add_shortcode('hpb_faq_group',function($a){$a=shortcode_atts(['group'=>''],$a);return hpb_faq_render(sanitize_key($a['group']));});
 add_shortcode('hpc_support_faq',function(){return '<div class="hkc-faq"><h2>자주 묻는 질문</h2>'.hpb_faq_render('').'<p><a href="'.esc_url(home_url('/qna/')).'">Q&amp;A 문의하기</a></p></div>';});
},999);
