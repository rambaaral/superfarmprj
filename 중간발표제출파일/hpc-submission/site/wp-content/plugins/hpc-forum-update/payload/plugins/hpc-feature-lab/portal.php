<?php
if (!defined('ABSPATH')) exit;

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
    if (!is_user_logged_in()) { auth_redirect(); exit; }
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $allowed = ['application'=>['save'],'application-status'=>['submit','transition'],'qna'=>['message','answer'],'my-page'=>[]];
            if (!in_array(hpc_input('op'), $allowed[$key], true)) throw new RuntimeException('이 화면에서 처리할 수 없는 요청입니다.');
            if ($key === 'qna' && hpc_input('kind') !== 'qna') throw new RuntimeException('문의 유형을 확인해 주세요.');
            hpc_post();
        }
        if ($key === 'application-status' && ($_GET['view'] ?? '') === 'download') hpc_download();
    } catch (Throwable $e) { $GLOBALS['hpc_portal_error'] = $e->getMessage(); status_header(400); }
}, 5);

function hpc_portal_capture($callback) {
    if (!is_user_logged_in()) return '<p>로그인 후 이용할 수 있습니다.</p>';
    if (!hpcm_verified()) return '<p><a href="'.esc_url(hpcm_url()).'">이메일 인증 후 이용해 주세요.</a></p>';
    ob_start();
    echo '<div class="portal-service">';
    if (!empty($GLOBALS['hpc_portal_error'])) echo '<p class="error" role="alert">'.esc_html($GLOBALS['hpc_portal_error']).'</p>';
    elseif (isset($_GET['saved'])) echo '<p class="success" role="status">저장되었습니다.</p>';
    try { $callback(); } catch(Throwable $e) { echo '<p class="error" role="alert">'.esc_html($e->getMessage()).'</p>'; }
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('hpc_portal_application', function() { return hpc_portal_capture(function() {
    $a = !empty($_GET['id']) ? hpc_get(absint($_GET['id'])) : null;
    if ($a && ((int)$a->owner !== get_current_user_id() || !in_array($a->state, ['draft','needs_revision'], true))) throw new RuntimeException('수정할 수 없는 신청입니다.');
    hpc_form($a); hpc_live_preview_ui($a);
}); });

function hpc_portal_query_value($key) { return isset($_GET[$key]) && is_scalar($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : ''; }
function hpc_portal_search($label, $status = false) {
    echo '<form class="portal-search" method="get"><input type="hidden" name="page_id" value="'.get_queried_object_id().'"><label for="portal-q">'.esc_html($label).'</label>';
    if ($status) { echo '<select name="state" aria-label="신청 상태"><option value="">전체 상태</option>'; foreach(hpc_states() as $k=>$v) echo '<option value="'.esc_attr($k).'" '.selected(hpc_portal_query_value('state'),$k,false).'>'.esc_html($v).'</option>'; echo '</select>'; }
    echo '<input id="portal-q" name="q" maxlength="100" placeholder="검색어를 입력하세요" value="'.esc_attr(hpc_portal_query_value('q')).'"><button type="submit">검색</button></form>';
}
function hpc_portal_pagination($total, $page, $size=10) {
    if ($total <= $size) return;
    echo '<nav class="pagination" aria-label="목록 페이지">';
    $last=(int)ceil($total/$size);
    if ($page>1) echo '<a href="'.esc_url(add_query_arg('list_page',$page-1)).'">이전</a>';
    for($i=max(1,$page-2);$i<=min($last,$page+2);$i++) echo '<a '.($i===$page?'aria-current="page"':'').' href="'.esc_url(add_query_arg('list_page',$i)).'">'.$i.'</a>';
    if ($page<$last) echo '<a href="'.esc_url(add_query_arg('list_page',$page+1)).'">다음</a>';
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
    hpc_portal_search('과제명·접수번호 검색',true);
    echo '<p class="list-count">총 <strong>'.$total.'</strong>건 · '.(hpc_admin()&&!$own_only?'관리자 전체 신청 목록':'본인 신청 목록').'</p><div class="table-scroll" role="region" aria-label="신청 목록" tabindex="0"><table><caption class="screen-reader-text">신청 현황</caption><thead><tr><th scope="col">접수번호</th><th scope="col">과제명</th><th scope="col">신청인</th><th scope="col">상태</th><th scope="col">최종 변경</th></tr></thead><tbody>';
    foreach(array_slice($rows,($page-1)*10,10) as $a) echo '<tr><td>'.esc_html($a->receipt?:'작성 중 #'.$a->id).'</td><td class="title-cell">'.hpc_link(hpc_summary($a,'title'),['view'=>'detail','id'=>$a->id]).'</td><td>'.esc_html(hpc_summary($a,'name')).'</td><td><span class="status-badge state-'.esc_attr($a->state).'">'.esc_html(hpc_states()[$a->state]??$a->state).'</span></td><td>'.esc_html(substr($a->updated,0,10)).'</td></tr>';
    if (!$total) echo '<tr><td colspan="5" class="empty-state">표시할 신청 내역이 없습니다.<br><small>이용신청에서 작성 내용을 저장하면 이곳에서 확인할 수 있습니다.</small></td></tr>';
    echo '</tbody></table></div>'; hpc_portal_pagination($total,$page);
}
add_shortcode('hpc_portal_status', function() { return hpc_portal_capture(function() {
    if (hpc_portal_query_value('view')==='detail') { echo '<p>'.hpc_link('← 신청 목록으로',['view'=>'status']).'</p>'; hpc_detail(hpc_get(absint($_GET['id']??0))); }
    elseif(hpc_portal_query_value('view')!=='download') hpc_portal_applications();
}); });
add_shortcode('hpc_portal_account', function() { return hpc_portal_capture(function() {
    $user=wp_get_current_user();
    echo '<div class="account-summary"><span class="account-avatar" aria-hidden="true">'.esc_html(mb_substr($user->display_name,0,1)).'</span><div><h2>'.esc_html($user->display_name).' 님</h2><p>'.esc_html($user->user_email).'</p><span class="status-badge">'.(hpc_admin()?'관리자':'일반 회원').'</span></div></div>';
    echo '<h2>나의 신청 내역</h2>'; hpc_portal_applications(true);
    echo '<h2>이용자 포럼</h2><p>'.(hpcf_allowed()?'이용 승인 회원으로 포럼을 이용할 수 있습니다.':'신청이 선정·이용 승인되면 포럼을 이용할 수 있습니다.').'</p><a class="button secondary" href="'.esc_url(hpcf_url()).'">이용자 포럼 →</a>';
    echo '<h2>나의 교육 이력</h2><p class="notice-box">교육 수료 이력은 아직 연결되지 않았습니다. 모의 실습은 수료 또는 실제 이용 기록으로 저장되지 않습니다.</p>';
    if(hpc_admin()) echo '<p><a class="button" href="'.esc_url(hpc_template_admin_url()).'">신청 양식 관리 →</a></p>';
    if(!hpc_admin() && function_exists('hpcw_url')) echo '<hr><h2>회원 탈퇴</h2><p>진행 중 신청이 없으면 계정과 신청 자료 삭제를 요청할 수 있습니다.</p><a class="button secondary" href="'.esc_url(hpcw_url()).'">회원 탈퇴 안내 및 본인 확인</a>';
}); });
add_shortcode('hpc_portal_qna', function() { return hpc_portal_capture(function() {
    global $wpdb;
    $where="kind='qna'";
    if(!hpc_admin()) $where.=$wpdb->prepare(' AND owner=%d',get_current_user_id());
    $q=mb_substr(hpc_portal_query_value('q'),0,100);
    if($q!=='') $where.=$wpdb->prepare(' AND (title LIKE %s OR body LIKE %s)','%'.$wpdb->esc_like($q).'%','%'.$wpdb->esc_like($q).'%');
    $total=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.hpc_table('messages').' WHERE '.$where);
    $page=max(1,min(max(1,(int)ceil($total/10)),absint($_GET['list_page']??1)));
    $rows=$wpdb->get_results('SELECT * FROM '.hpc_table('messages').' WHERE '.$where.$wpdb->prepare(' ORDER BY id DESC LIMIT 10 OFFSET %d',($page-1)*10));
    echo '<p class="notice-box">비공개 문의 게시판입니다. 질문과 답변은 작성자 본인과 관리자만 확인할 수 있습니다.</p>'; hpc_portal_search('제목·내용 검색');
    echo '<div class="section-heading"><p class="list-count">총 <strong>'.$total.'</strong>건</p><a class="button secondary" href="#write-question">문의 작성</a></div><div class="table-scroll" role="region" aria-label="문의 목록" tabindex="0"><table><caption class="screen-reader-text">문의 목록</caption><thead><tr><th scope="col">번호</th><th scope="col">제목 · 클릭하여 내용 확인</th><th scope="col">답변 상태</th><th scope="col">등록일</th></tr></thead><tbody>';
    foreach($rows as $m) {
        echo '<tr><td>'.(int)$m->id.'</td><td class="title-cell"><details class="question-detail"><summary>'.esc_html($m->title).' <span class="private-label">비공개</span></summary><div class="question-body"><p>'.nl2br(esc_html($m->body)).'</p><div class="notice-box"><strong>관리자 답변</strong><p>'.nl2br(esc_html($m->answer?:'답변을 기다리고 있습니다.')).'</p></div>';
        if(hpc_admin()) { echo '<form method="post">'; hpc_nonce('answer'); echo '<input type="hidden" name="id" value="'.(int)$m->id.'"><input type="hidden" name="kind" value="qna"><label for="answer-'.(int)$m->id.'">답변 입력</label><textarea id="answer-'.(int)$m->id.'" name="answer" maxlength="3000" rows="4" required>'.esc_textarea($m->answer).'</textarea><button>답변 저장</button></form>'; }
        echo '</div></details></td><td><span class="status-badge">'.($m->answer?'답변 완료':'답변 대기').'</span></td><td>'.esc_html(substr($m->created,0,10)).'</td></tr>';
    }
    if(!$rows) echo '<tr><td colspan="4" class="empty-state">등록된 문의가 없습니다.<br><small>궁금한 내용을 아래에서 작성해 주세요.</small></td></tr>';
    echo '</tbody></table></div>';hpc_portal_pagination($total,$page);
    echo '<h2 id="write-question">문의 작성</h2><p>시험 운영 중입니다. 개인정보가 포함되지 않은 가상 내용으로 작성해 주세요.</p><form method="post" class="question-form">';hpc_nonce('message');
    echo '<input type="hidden" name="kind" value="qna"><label for="question-title">제목 <span class="required">필수</span></label><input id="question-title" name="title" maxlength="150" required><label for="question-body">문의 내용 <span class="required">필수</span></label><textarea id="question-body" name="body" maxlength="3000" rows="6" required></textarea><button>문의 등록</button></form>';
}); });
