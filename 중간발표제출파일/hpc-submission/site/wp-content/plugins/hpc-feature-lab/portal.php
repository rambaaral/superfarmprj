<?php
if (!defined('ABSPATH')) exit;
require_once __DIR__.'/member-login.php';

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
    if (!is_user_logged_in()) { if($key==='my-page')return; auth_redirect(); exit; }
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $allowed = ['application'=>['save'],'application-status'=>['submit','transition','reopen'],'qna'=>['message','answer'],'my-page'=>[]];
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
    if ($a && ((int)$a->owner !== get_current_user_id() || !in_array($a->state, ['draft','needs_revision','amending'], true))) throw new RuntimeException('수정할 수 없는 신청입니다.');
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
    echo '<p class="notice-box">본인 신청은 제출 완료·접수 확인 단계까지 수정할 수 있습니다. 수정 시작 후에는 새 서명 문서를 첨부해 재제출해야 합니다. 심사·결과 확정 후 변경은 Q&A로 문의해 주세요.</p>';
    hpc_portal_search('과제명·접수번호 검색',true);
    echo '<p class="list-count">총 <strong>'.$total.'</strong>건 · '.(hpc_admin()&&!$own_only?'관리자 전체 신청 목록':'본인 신청 목록').'</p><div class="table-scroll" role="region" aria-label="신청 목록" tabindex="0"><table><caption class="screen-reader-text">신청 현황</caption><thead><tr><th scope="col">접수번호</th><th scope="col">과제명</th><th scope="col">신청인</th><th scope="col">상태</th><th scope="col">최종 변경</th><th scope="col">수정</th></tr></thead><tbody>';
    foreach(array_slice($rows,($page-1)*10,10) as $a) echo '<tr><td>'.esc_html($a->receipt?:'작성 중 #'.$a->id).'</td><td class="title-cell">'.hpc_link(hpc_summary($a,'title'),['view'=>'detail','id'=>$a->id]).'</td><td>'.esc_html(hpc_summary($a,'name')).'</td><td><span class="status-badge state-'.esc_attr($a->state).'">'.esc_html(hpc_states()[$a->state]??$a->state).'</span></td><td>'.esc_html(substr($a->updated,0,10)).'</td><td>'.hpc_application_edit_action($a).'</td></tr>';
    if (!$total) echo '<tr><td colspan="6" class="empty-state">표시할 신청 내역이 없습니다.<br><small>이용신청에서 작성 내용을 저장하면 이곳에서 확인할 수 있습니다.</small></td></tr>';
    echo '</tbody></table></div>'; hpc_portal_pagination($total,$page);
}
add_shortcode('hpc_portal_status', function() { return hpc_portal_capture(function() {
    if (hpc_portal_query_value('view')==='detail') { echo '<p>'.hpc_link('← 신청 목록으로',['view'=>'status']).'</p>'; hpc_detail(hpc_get(absint($_GET['id']??0))); }
    elseif(hpc_portal_query_value('view')!=='download') hpc_portal_applications();
}); });
add_shortcode('hpc_portal_account', function() { if(!is_user_logged_in())return hpc_member_login_panel(); return hpc_portal_capture(function() {
    $user=wp_get_current_user();
    echo '<div class="account-summary"><span class="account-avatar" aria-hidden="true">'.esc_html(mb_substr($user->display_name,0,1)).'</span><div><h2>'.esc_html($user->display_name).' 님</h2><p>'.esc_html($user->user_email).'</p><span class="status-badge">'.(hpc_admin()?'관리자':'일반 회원').'</span></div></div>';
    echo '<div class="intro-panel"><h2>이용자 지원</h2><p>자주 묻는 질문에서 먼저 확인하고, 해결되지 않으면 비공개 Q&A로 문의해 주세요.</p><div class="actions"><a class="button" href="'.esc_url(hpc_support_url('faq')).'">FAQ 자주 묻는 질문 →</a><a class="button secondary" href="'.esc_url(hpc_support_url('qna')).'">Q&A 문의 →</a><a class="button secondary" href="'.esc_url(hpc_support_url('usage-rules')).'">이용 수칙</a></div></div>';
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
    echo '<p><a class="button secondary" href="'.esc_url(hpc_support_url('faq')).'">FAQ에서 먼저 확인하기 →</a></p>';
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

// Support pages and application amendment helpers.
function hpc_support_url($key){$map=get_option('hpc_portal_pages_v1',[]);return !empty($map[$key])?get_permalink($map[$key]):home_url('/'.$key.'/');}
function hpc_application_edit_action($a){
    if((int)$a->owner!==get_current_user_id())return '—';
    if(in_array($a->state,['draft','needs_revision','amending'],true))return '<a class="button secondary" href="'.esc_url(hpc_url(['view'=>'apply','id'=>$a->id])).'">수정</a>';
    if(in_array($a->state,['submitted','received'],true))return '<a class="button secondary" href="'.esc_url(hpc_url(['view'=>'detail','id'=>$a->id])).'#edit-application">수정</a>';
    return '<a href="'.esc_url(hpc_support_url('qna')).'">변경 문의</a>';
}
function hpc_usage_rules_ui(){return '<fieldset class="usage-rules-consent"><legend>이용 수칙 확인</legend><p>계정 공유 금지, 승인된 목적·할당 자원 준수, 연구 데이터 보호, 장애·보안사고 신고 및 신청 변경 절차를 확인해 주세요.</p><p><a href="'.esc_url(hpc_support_url('usage-rules')).'" target="_blank" rel="noopener">이용 수칙 전체 보기 (새 창)</a></p><label class="inline"><input type="checkbox" name="usage_rules" value="1" required>이용 수칙을 읽고 준수하는 데 동의합니다. (시험 운영)</label></fieldset>';}
add_shortcode('hpc_ai_chatbot',function(){return '<div class="intro-panel"><span class="eyebrow">AI CHATBOT</span><h2>슈퍼컴퓨팅센터 AI 챗봇 상담 안내</h2><p class="lead">AI 챗봇 상담 서비스는 현재 제작 준비 중입니다.</p><p>센터 이용 안내, 고성능 컴퓨팅(HPC) 자원, 시스템 사용 방법과 연구 지원 서비스에 관한 기본적인 문의를 도울 예정입니다.</p><p>서비스가 준비되면 이 페이지에 이용 방법과 바로가기를 안내하겠습니다.</p><span class="status-badge">제작 준비 중</span></div><p><a class="button secondary" href="'.esc_url(hpc_support_url('faq')).'">FAQ 확인하기 →</a></p>';});
add_shortcode('hpc_support_faq',function(){return '<div class="hpc-support-ui"><section class="support-hero"><span class="support-eyebrow">FAQ · HELP CENTER</span><h2>궁금한 내용을 빠르게 찾아보세요.</h2><p>신청부터 파일 첨부, 제출 후 변경까지.<br>자주 묻는 질문을 모아 안내해 드립니다.</p><div class="support-route"><span><b>01</b> FAQ 확인</span><span aria-hidden="true">→</span><span><b>02</b> 해결되지 않으면 Q&A 문의</span></div></section><div class="support-section-label"><h2>자주 묻는 질문</h2><span>질문을 누르면 답변이 펼쳐집니다.</span></div><div class="support-faq-list"><details class="support-faq-item"><summary><span class="support-q" aria-hidden="true">Q</span><span class="support-question"><span class="support-category">신청 절차</span><span>신청은 어떤 순서로 진행하나요?</span></span><span class="support-toggle" aria-hidden="true"></span></summary><div class="support-answer"><span class="support-a" aria-hidden="true">A</span><p>이용신청에서 내용을 작성·저장하고, 신청현황의 상세 화면에서 문서를 내려받습니다. 내용을 확인한 뒤 서명·날인한 신청서를 첨부하고 최종 제출합니다. 저장이나 다운로드만으로는 제출되지 않습니다.</p></div></details><details class="support-faq-item"><summary><span class="support-q" aria-hidden="true">Q</span><span class="support-question"><span class="support-category">파일 첨부</span><span>추가자료는 어디에 첨부하나요?</span></span><span class="support-toggle" aria-hidden="true"></span></summary><div class="support-answer"><span class="support-a" aria-hidden="true">A</span><p>신청현황 상세 화면의 최종 제출 영역에서 추가파일을 첨부합니다. PDF·HWP·HWPX·JPEG(JPG), 파일당 10MB 이하의 파일 1개를 지원합니다. 서명 신청서와 기관 공문은 별도 항목에 첨부하세요.</p></div></details><details class="support-faq-item"><summary><span class="support-q" aria-hidden="true">Q</span><span class="support-question"><span class="support-category">신청 변경</span><span>제출한 신청서를 수정할 수 있나요?</span></span><span class="support-toggle" aria-hidden="true"></span></summary><div class="support-answer"><span class="support-a" aria-hidden="true">A</span><p>본인 신청이 제출 완료 또는 접수 확인 상태이면 수정할 수 있습니다. 신청현황에서 수정 → 신청 수정 시작을 선택하면 수정 중으로 전환됩니다. 내용을 저장하고 변경된 서명 신청서를 다시 첨부한 뒤 최종 제출해 주세요. 심사·결과 확정 후에는 Q&A로 변경을 문의합니다.</p></div></details><details class="support-faq-item"><summary><span class="support-q" aria-hidden="true">Q</span><span class="support-question"><span class="support-category">오류 해결</span><span>파일이 업로드되지 않거나 내려받을 수 없어요.</span></span><span class="support-toggle" aria-hidden="true"></span></summary><div class="support-answer"><span class="support-a" aria-hidden="true">A</span><p>파일 크기와 실제 형식을 확인해 주세요. 확장자만 바꾼 파일은 허용되지 않습니다. 업로드 파일은 안전성 검사가 끝나기 전에는 내려받을 수 없습니다. 문제가 계속되면 파일 자체를 보내기 전에 오류 문구를 Q&A에 남겨 주세요.</p></div></details><details class="support-faq-item"><summary><span class="support-q" aria-hidden="true">Q</span><span class="support-question"><span class="support-category">서비스 안내</span><span>AI 챗봇은 이용할 수 있나요?</span></span><span class="support-toggle" aria-hidden="true"></span></summary><div class="support-answer"><span class="support-a" aria-hidden="true">A</span><p>AI 챗봇은 현재 제작 준비 중입니다. 서비스가 준비되면 AI 챗봇 메뉴에서 이용 방법을 안내하겠습니다.</p></div></details><details class="support-faq-item"><summary><span class="support-q" aria-hidden="true">Q</span><span class="support-question"><span class="support-category">문의 안내</span><span>FAQ로 해결되지 않으면 어떻게 하나요?</span></span><span class="support-toggle" aria-hidden="true"></span></summary><div class="support-answer"><span class="support-a" aria-hidden="true">A</span><p>아래 Q&A로 문의해 주세요. 질문과 답변은 작성자 본인과 관리자만 확인할 수 있습니다. 비밀번호, 인증번호, 연구 원본 데이터 등은 문의 내용에 적지 마세요.</p></div></details></div><section class="support-contact"><div><span class="support-eyebrow">NEED HELP?</span><h2>찾으시는 답변이 없나요?</h2><p>오류 문구와 발생 화면을 함께 알려주시면 확인에 도움이 됩니다.<br>질문과 답변은 본인과 관리자만 확인할 수 있습니다.</p></div><a class="support-cta" href="'.esc_url(hpc_support_url('qna')).'">Q&A 문의하기 <span aria-hidden="true">→</span></a></section></div>';});
add_shortcode('hpc_usage_rules',function(){return '<div class="hpc-support-ui"><section class="support-hero"><span class="support-eyebrow">USER GUIDELINES</span><h2>이용 전, 꼭 확인해 주세요.</h2><p>모두가 안전하고 원활하게 사용할 수 있도록<br>슈퍼컴퓨팅센터의 기본 이용 수칙을 안내합니다.</p><div class="support-pills"><span>계정은 안전하게</span><span>자원은 정해진 범위에서</span><span>자료는 소중하게</span></div></section><div class="support-section-label"><h2>센터 이용 수칙</h2><span>시험 운영용 · 2026.10.22</span></div><div class="support-rule-grid"><article class="support-rule"><span class="support-rule-number" aria-hidden="true">01</span><div><h3>신청과 승인</h3><p>신청 내용은 정확하게 작성하고, 승인된 목적과 사용 범위 안에서 자원을 이용해 주세요. 신청서 저장만으로 자원 사용이 승인되지는 않습니다.</p></div></article><article class="support-rule"><span class="support-rule-number" aria-hidden="true">02</span><div><h3>계정 관리</h3><p>계정은 본인이 사용하며 비밀번호·인증정보를 다른 사람과 공유하지 않습니다. 계정정보를 공개 게시판이나 Q&A에 남기지 마세요.</p></div></article><article class="support-rule"><span class="support-rule-number" aria-hidden="true">03</span><div><h3>자원 사용</h3><p>할당된 노드·저장공간·이용기간을 지키고, 안내된 작업 제출 방법과 스케줄러를 사용합니다. 다른 이용자의 작업을 방해하는 사용이나 승인받지 않은 접근을 하지 않습니다.</p></div></article><article class="support-rule"><span class="support-rule-number" aria-hidden="true">04</span><div><h3>자료와 프로그램</h3><p>이용 권한이 있는 자료·프로그램만 사용하고, 연구 데이터는 필요한 범위에서 보관·백업합니다. 민감정보와 비공개 연구자료는 시험 사이트에 올리지 마세요.</p></div></article><article class="support-rule"><span class="support-rule-number" aria-hidden="true">05</span><div><h3>신청 변경</h3><p>제출 완료·접수 확인 단계에서 수정을 시작하면 수정 중 상태가 됩니다. 변경 내용을 저장한 뒤 서명 문서를 다시 첨부하고 재제출해야 합니다. 이전 제출 파일과 변경 이력은 보존됩니다. 심사 이후 변경은 Q&A로 문의해 주세요.</p></div></article><article class="support-rule"><span class="support-rule-number" aria-hidden="true">06</span><div><h3>서비스 점검과 신고</h3><p>점검 일정과 운영 안내를 확인하고, 장애나 보안 이상이 발생하면 작업 상황과 오류 문구를 운영자에게 알려 주세요. 계정 차단이나 자원 변경 등이 필요한 경우 운영자가 상황을 확인해 안내합니다.</p></div></article><article class="support-rule support-rule-consent"><span class="support-rule-number" aria-hidden="true">07</span><div><h3>확인과 동의</h3><p>신청서의 동의란은 위 수칙을 읽고 준수하겠다는 의사를 확인하는 항목입니다. 서비스 안내 문구만 열람한 것을 동의로 처리하지 않습니다. 개인정보 안내와 보안 서약은 신청 양식의 해당 항목에서 별도로 확인합니다.</p></div></article></div><section class="support-contact"><div><span class="support-eyebrow">SUPPORT</span><h2>이용 방법이 궁금하신가요?</h2><p>FAQ를 먼저 확인하고, 추가 도움이 필요하면 Q&A로 문의해 주세요.</p></div><div class="support-contact-actions"><a class="support-cta support-cta-outline" href="'.esc_url(hpc_support_url('faq')).'">FAQ 확인하기</a><a class="support-cta" href="'.esc_url(hpc_support_url('qna')).'">Q&A 문의 <span aria-hidden="true">→</span></a></div></section></div>';});
add_shortcode('hpc_security_programs',function(){return '<div class="intro-panel"><span class="eyebrow">PC SECURITY</span><h2>PC 보안프로그램 안내</h2><p class="lead">보안프로그램 일괄다운로드는 준비 중입니다.</p><p>필수 설치 프로그램과 지원 운영체제는 아직 지정되지 않았습니다. 현재 이 페이지에서 설치 파일을 배포하지 않습니다.</p></div><h2>설치가 필요한 경우 안내할 내용</h2><ul><li>프로그램명·용도와 필수 또는 선택 여부</li><li>지원 운영체제·브라우저와 설치·삭제 방법</li><li>제조사 공식 다운로드 및 지원 경로</li></ul><p>도입 프로그램이 확정되면 검증된 공식 배포 경로를 안내하겠습니다.</p><p><a class="button secondary" href="'.esc_url(hpc_support_url('faq')).'">FAQ 확인하기 →</a></p>';});
add_action('admin_init',function(){
    if(!current_user_can('manage_options')||get_option('hpc_support_pages_20261022_v1'))return;
    $map=get_option('hpc_portal_pages_v1',[]);$definitions=['ai-chatbot'=>['AI 챗봇','hpc_ai_chatbot'],'faq'=>['FAQ 자주 묻는 질문','hpc_support_faq'],'usage-rules'=>['이용 수칙','hpc_usage_rules']];
    foreach($definitions as $slug=>[$title,$shortcode]){
        if(!empty($map[$slug])&&get_post($map[$slug]))continue;
        $existing=get_page_by_path($slug);
        if($existing){$map[$slug]=(int)$existing->ID;continue;}
        $id=wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_name'=>$slug,'post_content'=>'<!-- wp:shortcode -->['.$shortcode.']<!-- /wp:shortcode -->'],true);
        if(is_wp_error($id)||!$id)return;
        $map[$slug]=(int)$id;update_option('hpc_portal_pages_v1',$map,false);
    }
    update_option('hpc_portal_pages_v1',$map,false);update_option('hpc_support_pages_20261022_v1',1,false);
});

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
