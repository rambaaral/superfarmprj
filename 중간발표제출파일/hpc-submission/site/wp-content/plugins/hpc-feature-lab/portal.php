<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/member-login.php';

// Only the active KBoard Q&A owns public reads and its write permissions.
function hpc_portal_uses_kboard_qna() {
    return function_exists('hkb_ids') && get_option('hkb_enabled') && shortcode_exists('kboard') && !empty(hkb_ids()['qna']);
}
function hpc_portal_page_key() {
    if (!is_page()) return '';
    return (string) array_search(get_queried_object_id(), array_map('intval', get_option('hpc_portal_pages_v1', [])), true);
}
add_filter('hpc_portal_url', function($fallback, $args) {
    $map = get_option('hpc_portal_pages_v1', []);
    $key = ['apply'=>'application','status'=>'application-status','detail'=>'application-status','download'=>'application-status','qna'=>'qna'][$args['view'] ?? 'status'] ?? '';
    if (!$key || empty($map[$key]) || get_post_status($map[$key]) !== 'publish') return $fallback;
    return add_query_arg($args, get_permalink($map[$key]));
}, 10, 2);

// WordPress treats POST fields named "name", "year", etc. as query variables.
// On our four member pages, resolve the page from the URL only; form fields
// remain untouched for the existing nonce/ownership/validation handlers.
add_filter('request', function($query) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return $query;
    $map = array_intersect_key(get_option('hpc_portal_pages_v1', []), array_flip(['application','application-status','my-page','qna','forum']));
    $id = isset($_GET['page_id']) && is_scalar($_GET['page_id']) ? absint($_GET['page_id']) : 0;
    if (!$id && get_option('permalink_structure')) {
        $path = rawurldecode((string)wp_parse_url($_SERVER['REQUEST_URI'] ?? '',PHP_URL_PATH));
        foreach($map as $candidate) if (rtrim($path,'/') === rtrim(rawurldecode((string)wp_parse_url(get_permalink($candidate),PHP_URL_PATH)),'/')) { $id=(int)$candidate; break; }
    }
    if ($id && in_array($id,array_map('intval',$map),true)) return ['page_id'=>$id];
    return $query;
}, 99);

add_action('template_redirect', function() {
    $key = hpc_portal_page_key();
    if (!in_array($key, ['application','application-status','my-page','qna'], true)) return;
    if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
    do_action('litespeed_control_set_nocache','HPC member page');
    nocache_headers(); header('X-Robots-Tag: noindex, nofollow');
    if ($key==='qna' && hpc_portal_uses_kboard_qna()) return;
    if (!is_user_logged_in()) { if($key==='my-page')return; auth_redirect(); exit; }
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $allowed = ['application'=>['save'],'application-status'=>['submit','transition','reopen'],'qna'=>[],'my-page'=>[]];
            if (!in_array(hpc_input('op'), $allowed[$key], true)) throw new RuntimeException(hpct_text('t69e7eb026c7c9be72fe8'));
            hpc_post();
        }
        if ($key === 'application-status' && ($_GET['view'] ?? '') === 'download') hpc_download();
    } catch (Throwable $e) { $GLOBALS['hpc_portal_error'] = $e->getMessage(); status_header(400); }
}, 5);

function hpc_portal_capture($callback) {
    if (!is_user_logged_in()) return (''.'<p>'.esc_html(hpct_text('t7c70b5efece27b696e8e')).'</p>'.'');
    if (!hpcm_verified()) return '<p><a href="'.esc_url(hpcm_url()).(''.'">'.esc_html(hpct_text('tf012da77029eb62d2979')).'</a>'.''.'</p>'.'');
    ob_start();
    echo '<div class="portal-service">';
    if (!empty($GLOBALS['hpc_portal_error'])) echo '<p class="error" role="alert">'.esc_html($GLOBALS['hpc_portal_error']).'</p>';
    elseif (isset($_GET['saved'])) echo (''.'<p class="success" role="status">'.esc_html(hpct_text('tba971bfea1b3477dd1cd')).'</p>'.'');
    try { $callback(); } catch(Throwable $e) { echo '<p class="error" role="alert">'.esc_html($e->getMessage()).'</p>'; }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('hpc_portal_application', function() { return hpc_portal_capture(function() {
    $a = !empty($_GET['id']) ? hpc_get(absint($_GET['id'])) : null;
    if ($a && ((int)$a->owner !== get_current_user_id() || !in_array($a->state, ['draft','needs_revision','amending'], true))) throw new RuntimeException(hpct_text('t13a4b4fd0884dfcb1e4c'));
    hpc_form($a); hpc_live_preview_ui($a);
}); });

function hpc_portal_query_value($key) { return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : ''; }
function hpc_portal_search($label, $status = false) {
    echo '<form class="portal-search" method="get"><input type="hidden" name="page_id" value="'.get_queried_object_id().'"><label for="portal-q">'.esc_html($label).'</label>';
    if ($status) { echo (''.('<select name="state" aria-label="'.esc_attr(hpct_text('t3114a9d2f4c852497824')).'">').''.'<option value="">'.esc_html(hpct_text('t87947923d48aec693e91')).'</option>'.''); foreach(hpc_states() as $k=>$v) echo '<option value="'.esc_attr($k).'" '.selected(hpc_portal_query_value('state'),$k,false).'>'.esc_html($v).'</option>'; echo '</select>'; }
    echo (''.('<input id="portal-q" name="q" maxlength="100" placeholder="'.esc_attr(hpct_text('tfb6d5363977c73b6221f')).'" value="').'').esc_attr(hpc_portal_query_value('q')).(''.'">'.''.'<button type="submit">'.esc_html(hpct_text('t4b8d541ebe6d9003aba3')).'</button>'.''.'</form>'.'');
}
function hpc_portal_pagination($total, $page, $size=10) {
    if ($total <= $size) return;
    echo (''.('<nav class="pagination" aria-label="'.esc_attr(hpct_text('t30f5e15c8dc320fbf341')).'">').'');
    $last=(int)ceil($total/$size);
    if ($page>1) echo '<a href="'.esc_url(add_query_arg('list_page',$page-1)).(''.'">'.esc_html(hpct_text('tda7e61c67cc5a90f0630')).'</a>'.'');
    for($i=max(1,$page-2);$i<=min($last,$page+2);$i++) echo '<a '.($i===$page?'aria-current="page"':'').' href="'.esc_url(add_query_arg('list_page',$i)).'">'.$i.'</a>';
    if ($page<$last) echo '<a href="'.esc_url(add_query_arg('list_page',$page+1)).(''.'">'.esc_html(hpct_text('taef613c6612d424ab454')).'</a>'.'');
    echo '</nav>';
}
function hpc_portal_applications($own_only=false) {
    global $wpdb;
    $where = hpc_admin() && !$own_only ? '1=1' : $wpdb->prepare('owner=%d',get_current_user_id());
    $state = hpc_portal_query_value('state');
    if (isset(hpc_states()[$state])) $where .= $wpdb->prepare(' AND state=%s',$state);
    // Filter on human-readable summaries after ownership restriction, never across another user's rows.
    $rows=$wpdb->get_results('SELECT * FROM '.hpc_table().' WHERE '.$where.' ORDER BY id DESC');
    $q=mb_substr(hpc_portal_query_value('q'),0,100);
    if ($q !== '') $rows=array_values(array_filter($rows, fn($a)=>mb_stripos(hpc_summary($a,'title').' '.$a->receipt, $q)!==false));
    $total=count($rows);$page=max(1,min(max(1,(int)ceil($total/10)),absint($_GET['list_page']??1)));
    echo (''.'<p class="notice-box">'.esc_html(hpct_text('tba1dd07145fbe124c68a')).'</p>'.'');
    hpc_portal_search(hpct_text('t5e81c919a49ea90ef426'),true);
    echo (''.'<p class="list-count">'.esc_html(hpct_text('t90252b07abe2958686a5')).'<strong>'.'').$total.(''.'</strong>'.esc_html(hpct_text('t0d7c778eaf2555f796f4'))).(hpc_admin()&&!$own_only?hpct_text('teeafcfed11e74f393a88'):hpct_text('te115dfe1893276efcac0')).(''.'</p>'.''.(''.('<div class="table-scroll" role="region" aria-label="'.esc_attr(hpct_text('tfc6dff2bc16e59e08a66')).'" tabindex="0">').'').''.'<table>'.''.'<caption class="screen-reader-text">'.esc_html(hpct_text('t9b6e3b1d8e5df61aa49b')).'</caption>'.''.'<thead>'.''.'<tr>'.''.'<th scope="col">'.esc_html(hpct_text('tb736160e9f7f0d321a2b')).'</th>'.''.'<th scope="col">'.esc_html(hpct_text('tdd1954eb2a453614f1f7')).'</th>'.''.'<th scope="col">'.esc_html(hpct_text('tef1661bfe1d0b773aea1')).'</th>'.''.'<th scope="col">'.esc_html(hpct_text('te10195a1239fb321327b')).'</th>'.''.'<th scope="col">'.esc_html(hpct_text('t1f1d2ebd2f600897cde6')).'</th>'.''.'<th scope="col">'.esc_html(hpct_text('t3537f0cc3ec9fa10bc0d')).'</th>'.''.'</tr>'.''.'</thead>'.''.'<tbody>'.'');
    foreach(array_slice($rows,($page-1)*10,10) as $a) echo '<tr><td>'.esc_html($a->receipt?:hpct_text('t6ac6b84288d94618443b').$a->id).'</td><td class="title-cell">'.hpc_link(hpc_summary($a,'title'),['view'=>'detail','id'=>$a->id]).'</td><td>'.esc_html(hpc_summary($a,'name')).'</td><td><span class="status-badge state-'.esc_attr($a->state).'">'.esc_html(hpc_states()[$a->state]??$a->state).'</span></td><td>'.esc_html(substr($a->updated,0,10)).'</td><td>'.hpc_application_edit_action($a).'</td></tr>';
    if (!$total) echo (''.'<tr>'.''.'<td colspan="6" class="empty-state">'.esc_html(hpct_text('td11dc31d6894a73a1bfe')).'<br>'.''.'<small>'.esc_html(hpct_text('t697a5c3f9885108a99f4')).'</small>'.''.'</td>'.''.'</tr>'.'');
    echo '</tbody></table></div>'; hpc_portal_pagination($total,$page);
}
add_shortcode('hpc_portal_status', function() { return hpc_portal_capture(function() {
    if (hpc_portal_query_value('view')==='detail') { echo '<p>'.hpc_link(hpct_text('ta9e385fd115a74c80391'),['view'=>'status']).'</p>'; hpc_detail(hpc_get(absint($_GET['id']??0))); }
    elseif(hpc_portal_query_value('view')!=='download') hpc_portal_applications();
}); });
add_shortcode('hpc_portal_account', function() { if(!is_user_logged_in())return hpc_member_login_panel(); return hpc_portal_capture(function() {
    $user=wp_get_current_user();
    echo '<div class="account-summary"><span class="account-avatar" aria-hidden="true">'.esc_html(mb_substr($user->display_name,0,1)).'</span><div><h2>'.esc_html($user->display_name).(esc_html(hpct_text('t7583dfcbfa8d11382ec3')).'</h2>'.''.'<p>'.'').esc_html($user->user_email).'</p><span class="status-badge">'.(hpc_admin()?hpct_text('t78496a61deb74f2d0ad4'):hpct_text('tf21fd733ef0c8238ffdf')).'</span></div></div>';
    echo (''.'<div class="intro-panel">'.''.'<h2>'.esc_html(hpct_text('t3107c0dcfcb1ad65ddbe')).'</h2>'.''.'<p>'.esc_html(hpct_text('tee3626dd8ebae1504659')).'</p>'.''.'<div class="actions">'.''.'<a class="button" href="'.'').esc_url(hpc_support_url('faq')).(''.'">'.esc_html(hpct_text('t4d1cb165c5ac74f9bc87')).'</a>'.''.'<a class="button secondary" href="'.'').esc_url(hpc_support_url('qna')).(''.'">'.esc_html(hpct_text('t918ecf5ac4fe0436f778')).'</a>'.''.'<a class="button secondary" href="'.'').esc_url(hpc_support_url('usage-rules')).(''.'">'.esc_html(hpct_text('t27e597f8d61a70af3a1a')).'</a>'.''.'</div>'.''.'</div>'.'');
    echo (''.'<h2>'.esc_html(hpct_text('taf96a3fb80ff7fe6388a')).'</h2>'.''); hpc_portal_applications(true);
    echo (''.'<h2>'.esc_html(hpct_text('t4b6108492bc78eb3b3a1')).'</h2>'.''.'<p>'.'').(hpcf_allowed()?hpct_text('te145c376845dedd4d614'):hpct_text('t1644363a4626d59a932c')).'</p><a class="button secondary" href="'.esc_url(hpcf_url()).(''.'">'.esc_html(hpct_text('t2ffa5f78fccabdbaa516')).'</a>'.'');
    echo (''.'<h2>'.esc_html(hpct_text('t95e732b40589ab38d9e9')).'</h2>'.''.'<p class="notice-box">'.esc_html(hpct_text('tcd18d45e2d8a58a34e56')).'</p>'.'');
    if(hpc_admin()) echo '<p><a class="button" href="'.esc_url(hpc_template_admin_url()).(''.'">'.esc_html(hpct_text('t420562e1ac248b70cd4e')).'</a>'.''.'</p>'.'');
    if(!hpc_admin() && function_exists('hpcw_url')) echo (''.'<hr>'.''.'<h2>'.esc_html(hpct_text('t4406c379c287135fb450')).'</h2>'.''.'<p>'.esc_html(hpct_text('t944ec046e69f08412c30')).'</p>'.''.'<a class="button secondary" href="'.'').esc_url(hpcw_url()).(''.'">'.esc_html(hpct_text('t263270d89f213cc70a23')).'</a>'.'');
}); });
// Public guidance is now stored in WordPress page content; no duplicate prose in PHP.
function hpc_support_editable_page($slug){
    static $rendering=[];
    if(!empty($rendering[$slug]))return '';
    $map=get_option('hpc_portal_pages_v1',[]);
    $p=!empty($map[$slug])?get_post((int)$map[$slug]):get_page_by_path($slug);
    if(!$p || $p->post_status!=='publish' || $p->post_password!=='')return '';
    $rendering[$slug]=true;
    try{return apply_filters('the_content',$p->post_content);}
    finally{unset($rendering[$slug]);}
}
add_shortcode('hpc_usage_rules',function(){return hpc_support_editable_page('usage-rules');});
add_shortcode('hpc_security_programs',function(){return hpc_support_editable_page('security-programs');});
add_shortcode('hpc_support_faq',function(){return function_exists('hkc_render_faq')?hkc_render_faq():'';});
add_shortcode('hpc_ai_chatbot',function(){return function_exists('hkc_render_chat')?hkc_render_chat():'';});

// Support pages and application amendment helpers.
function hpc_support_url($key){$map=get_option('hpc_portal_pages_v1',[]);return !empty($map[$key])?get_permalink($map[$key]):home_url('/'.$key.'/');}
function hpc_application_edit_action($a){
    if((int)$a->owner!==get_current_user_id())return '—';
    if(in_array($a->state,['draft','needs_revision','amending'],true))return '<a class="button secondary" href="'.esc_url(hpc_url(['view'=>'apply','id'=>$a->id])).(''.'">'.esc_html(hpct_text('t3537f0cc3ec9fa10bc0d')).'</a>'.'');
    if(in_array($a->state,['submitted','received'],true))return '<a class="button secondary" href="'.esc_url(hpc_url(['view'=>'detail','id'=>$a->id])).'#edit-application">수정</a>';
    return '<a href="'.esc_url(hpc_support_url('qna')).(''.'">'.esc_html(hpct_text('t742444b4c27fc0a177ec')).'</a>'.'');
}
function hpc_usage_rules_ui(){return (''.'<fieldset class="usage-rules-consent">'.''.'<legend>'.esc_html(hpct_text('tf18b05fd2b37dddca55a')).'</legend>'.''.'<p>'.esc_html(hpct_text('t18a8061c8db6b53ccc49')).'</p>'.''.'<p>'.''.'<a href="'.'').esc_url(hpc_support_url('usage-rules')).(''.'" target="_blank" rel="noopener">'.esc_html(hpct_text('tb595bdf0636bf50b3696')).'</a>'.''.'</p>'.''.'<label class="inline">'.''.'<input type="checkbox" name="usage_rules" value="1" required>'.esc_html(hpct_text('t43956fd6ae3eb89e82e3')).'</label>'.''.'</fieldset>'.'');}
// Scoped presentation for FAQ and user guidelines.
add_action('wp_enqueue_scripts',function(){
    if(!in_array(hpc_portal_page_key(),['faq','usage-rules'],true))return;
    wp_add_inline_style('hpc-naas-adapter', <<<'HPC_SUPPORT_CSS'
.hpc-support-ui{--support-navy:#003c78;--support-blue:#087da5;--support-pale:#edf4f8;color:#263747;font-size:16px;line-height:1.75;overflow-wrap:anywhere}
#contents .hpc-support-ui h2,#contents .hpc-support-ui h3,.hpc-support-ui h2,.hpc-support-ui h3{background:none;border:0;box-shadow:none;padding:0;margin:0;color:#16324c;letter-spacing:-.035em;line-height:1.4}
.hpc-support-ui h2:before,.hpc-support-ui h2:after,.hpc-support-ui h3:before,.hpc-support-ui h3:after{display:none!important;content:none!important}
.hpc-support-ui p{margin:0;padding:0;color:#536574;font-size:16px;line-height:1.8;word-break:keep-all}
.hpc-support-ui .support-hero{background:var(--support-pale);border-left:5px solid var(--support-navy);border-radius:14px;padding:34px 36px;margin-bottom:36px}
.hpc-support-ui .support-eyebrow{display:block;font-size:12px;line-height:1.5;letter-spacing:.13em;font-weight:800;color:var(--support-navy);margin-bottom:14px}
#contents .hpc-support-ui .support-hero h2,.hpc-support-ui .support-hero h2{font-size:29px;font-weight:800;color:var(--support-navy);margin:0 0 16px}
.hpc-support-ui .support-route{display:flex;align-items:center;gap:15px;flex-wrap:wrap;margin-top:24px;padding-top:20px;border-top:1px solid #d5e2eb;color:#415d72;font-size:14px}
.hpc-support-ui .support-route b{color:var(--support-blue);margin-right:6px;font-size:12px}
.hpc-support-ui .support-pills{display:flex;gap:8px;flex-wrap:wrap;margin-top:24px}
.hpc-support-ui .support-pills span{background:#fff;border:1px solid #d9e5ee;border-radius:30px;color:var(--support-navy);font-size:13px;font-weight:600;padding:6px 13px}
.hpc-support-ui .support-section-label{display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 16px}
#contents .hpc-support-ui .support-section-label h2,.hpc-support-ui .support-section-label h2{font-size:21px;font-weight:750}
.hpc-support-ui .support-section-label>span{font-size:13px;color:#718292}
.hpc-support-ui .support-faq-list{display:grid;gap:12px}
.hpc-support-ui details.support-faq-item{margin:0;padding:0;border:1px solid #dbe4ec;border-radius:12px;background:#fff;overflow:hidden;transition:border-color .15s}
.hpc-support-ui details.support-faq-item:hover{border-color:#93b6d0}
.hpc-support-ui details.support-faq-item[open]{border-color:#7faac7;background:#f6f9fc}
.hpc-support-ui .support-faq-item summary{display:flex;align-items:center;gap:16px;list-style:none;padding:21px 24px;cursor:pointer;margin:0;font-weight:700;line-height:1.5;color:#1b354c;background:transparent}
.hpc-support-ui .support-faq-item summary::-webkit-details-marker{display:none}
.hpc-support-ui .support-faq-item summary::marker{content:''}
.hpc-support-ui .support-q{display:grid;place-items:center;flex:0 0 38px;width:38px;height:38px;border-radius:12px;background:#eaf3f8;color:var(--support-navy);font-size:20px;font-weight:800}
.hpc-support-ui .support-question{display:grid;gap:3px;flex:1;font-size:17px;word-break:keep-all}
.hpc-support-ui .support-category{font-size:11px;letter-spacing:.03em;color:#6e8394;font-weight:600}
.hpc-support-ui .support-toggle{position:relative;flex:0 0 24px;width:24px;height:24px;margin-left:4px}
.hpc-support-ui .support-toggle:before,.hpc-support-ui .support-toggle:after{content:'';position:absolute;background:#426680;border-radius:1px;left:5px;top:11px;width:14px;height:2px}
.hpc-support-ui .support-toggle:after{transform:rotate(90deg);transition:transform .15s}
.hpc-support-ui details[open] .support-toggle:after{transform:rotate(0)}
.hpc-support-ui details[open] .support-q{color:#fff;background:var(--support-navy)}
.hpc-support-ui .support-answer{display:flex;gap:16px;padding:22px 24px 26px;border-top:1px solid #dce7ef}
.hpc-support-ui .support-a{display:grid;place-items:center;flex:0 0 38px;height:30px;font-size:18px;font-weight:800;color:var(--support-blue)}
.hpc-support-ui .support-answer p{flex:1;font-size:15px}
.hpc-support-ui .support-rule-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
.hpc-support-ui article.support-rule{display:flex;align-items:flex-start;gap:18px;border:1px solid #dbe4ec;border-radius:12px;background:#fff;margin:0;padding:26px}
.hpc-support-ui .support-rule-number{flex:0 0 36px;font-size:21px;line-height:1.5;font-weight:800;color:#1684a7;letter-spacing:-.04em}
#contents .hpc-support-ui .support-rule h3,.hpc-support-ui .support-rule h3{font-size:19px;font-weight:750;margin:1px 0 12px}
.hpc-support-ui .support-rule p{font-size:15px;line-height:1.85}
.hpc-support-ui article.support-rule-consent{grid-column:1/-1;border-color:#c6dae9;background:#f1f6fa}
.hpc-support-ui .support-contact{display:flex;align-items:center;justify-content:space-between;gap:24px;background:var(--support-pale);border-left:4px solid var(--support-navy);border-radius:12px;padding:28px 30px;margin-top:32px}
.hpc-support-ui .support-contact .support-eyebrow{margin-bottom:8px}
#contents .hpc-support-ui .support-contact h2,.hpc-support-ui .support-contact h2{font-size:21px;font-weight:750;margin-bottom:10px}
.hpc-support-ui .support-contact p{font-size:14px}
.hpc-support-ui .support-contact-actions{display:flex;gap:8px;flex-wrap:wrap;flex-shrink:0}
.hpc-support-ui a.support-cta{display:inline-flex;align-items:center;justify-content:center;gap:20px;white-space:nowrap;text-decoration:none;background:var(--support-navy);color:white;border:1px solid var(--support-navy);border-radius:8px;padding:12px 20px;font-size:14px;font-weight:700;line-height:1.5;flex-shrink:0}
.hpc-support-ui a.support-cta:hover{background:#002b58}
.hpc-support-ui a.support-cta-outline{background:white;color:var(--support-navy)}
.hpc-support-ui a.support-cta-outline:hover{background:#e1edf5}
.hpc-support-ui summary:focus-visible,.hpc-support-ui a:focus-visible{outline:3px solid #0a89b4;outline-offset:3px}
@media(max-width:760px){.hpc-support-ui .support-hero{padding:26px 24px}.hpc-support-ui .support-rule-grid{grid-template-columns:1fr}.hpc-support-ui .support-contact{flex-direction:column;align-items:flex-start}.hpc-support-ui .support-section-label>span{font-size:12px}}
@media(max-width:480px){.hpc-support-ui .support-hero{padding:24px 20px}#contents .hpc-support-ui .support-hero h2,.hpc-support-ui .support-hero h2{font-size:24px}.hpc-support-ui .support-hero p{font-size:14px}.hpc-support-ui .support-route{gap:8px;font-size:12px}.hpc-support-ui .support-faq-item summary{padding:18px 16px;gap:10px}.hpc-support-ui .support-question{font-size:15px}.hpc-support-ui .support-q{flex-basis:30px;width:30px;height:34px;font-size:18px}.hpc-support-ui .support-answer{padding:18px 16px;gap:10px}.hpc-support-ui .support-a{flex-basis:30px}.hpc-support-ui article.support-rule{padding:22px 18px;gap:12px}.hpc-support-ui .support-contact{padding:24px 20px}}
.hpc-support-ui h2,.hpc-support-ui h3{background:none!important;background-image:none!important;border:0!important;box-shadow:none!important;padding:0!important;margin:0!important;min-height:0!important;height:auto!important;line-height:1.4!important;letter-spacing:-.035em!important;color:#16324c!important}
.hpc-support-ui .support-hero h2{font-size:29px!important;font-weight:800!important;color:#003c78!important;margin-bottom:16px!important}
.hpc-support-ui .support-section-label h2{font-size:21px!important;font-weight:750!important}
.hpc-support-ui .support-rule h3{font-size:19px!important;font-weight:750!important;margin:1px 0 12px!important}
.hpc-support-ui .support-contact h2{font-size:21px!important;font-weight:750!important;margin-bottom:10px!important}
@media(max-width:480px){.hpc-support-ui .support-hero h2{font-size:24px!important}}

HPC_SUPPORT_CSS
    );
},30);
