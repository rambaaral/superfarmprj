<?php
/*
Plugin Name: HPC Feature Lab
Description: 신청·양식 편집·심사 포털의 시험 운영 및 호스팅 설치 도구.
Version: 0.6.0
Requires PHP: 8.2
*/
if(!defined('ABSPATH'))exit;
require_once __DIR__.'/runtime.php';
require_once __DIR__.'/membership.php';
require_once __DIR__.'/documents.php';
require_once __DIR__.'/templates.php';
require_once __DIR__.'/mapped-documents.php';
require_once __DIR__.'/editor.php';
require_once __DIR__.'/portal.php';
require_once __DIR__.'/deployment.php';
require_once __DIR__.'/operations.php';
require_once __DIR__.'/withdrawal.php';
require_once __DIR__.'/forum.php';
function hpc_table($suffix='applications'){global $wpdb;return $wpdb->prefix.'hpc_lab_'.$suffix;}
function hpc_install(){
    global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();
    dbDelta('CREATE TABLE '.hpc_table()." (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      owner bigint unsigned NOT NULL,
      state varchar(30) NOT NULL DEFAULT 'draft',
      version int NOT NULL DEFAULT 1,
      data longtext NOT NULL,
      files longtext NOT NULL,
      history longtext NOT NULL,
      receipt varchar(40) NOT NULL DEFAULT '',
      created datetime NOT NULL,
      updated datetime NOT NULL,
      PRIMARY KEY  (id), KEY owner (owner)
    ) ENGINE=InnoDB $c;");
    dbDelta('CREATE TABLE '.hpc_table('messages')." (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      owner bigint unsigned NOT NULL,
      kind varchar(10) NOT NULL,
      title varchar(200) NOT NULL,
      body text NOT NULL,
      answer text NOT NULL,
      created datetime NOT NULL,
      PRIMARY KEY  (id), KEY owner (owner)
    ) ENGINE=InnoDB $c;");
}
register_activation_hook(__FILE__,'hpc_install');
add_filter('pre_wp_mail',function($v){return defined('HPC_LAB_ROOT')?false:$v;});
function hpc_admin(){return current_user_can('manage_options');}
function hpc_url($args=[]){return apply_filters('hpc_portal_url',add_query_arg(array_merge(['hpc_lab'=>1],$args),home_url('/')),$args);}
function hpc_domains(){return ['농업기초','식량','원예','축산','식물','동물','미생물','보건','의료','기타'];}
function hpc_states(){return ['draft'=>'작성 중','submitted'=>'제출 완료','received'=>'접수 확인','review'=>'전문가 검토','committee'=>'운영위원회 심의','selected'=>'선정','rejected'=>'미선정','needs_revision'=>'보완 요청'];}
function hpc_transitions(){return ['submitted'=>['received','needs_revision'],'received'=>['review','needs_revision'],'review'=>['committee','needs_revision','rejected'],'committee'=>['selected','rejected','needs_revision'],'needs_revision'=>[],'selected'=>[],'rejected'=>[],'draft'=>[]];}
function hpc_get($id,$lock=false){
    hpcm_require();
    global $wpdb;$a=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.hpc_table().' WHERE id=%d'.($lock?' FOR UPDATE':''),$id));
    if(!$a||(!hpc_admin()&&(int)$a->owner!==get_current_user_id()))throw new RuntimeException('이 신청에 접근할 권한이 없습니다.');
    return $a;
}
function hpc_event($type,$text){return ['at'=>current_time('mysql'),'by'=>get_current_user_id(),'type'=>$type,'text'=>$text];}
function hpc_input($key){return isset($_POST[$key])&&is_scalar($_POST[$key])?trim(wp_unslash((string)$_POST[$key])):'';}
function hpc_validate(){
    $limits=['organization'=>100,'department'=>100,'position'=>50,'name'=>40,'email'=>120,'phone'=>30,'project'=>160,'project_no'=>60,'purpose'=>400,'method'=>1500,'programs'=>200,'account1'=>20,'account2'=>20,'approver'=>40];$d=[];
    foreach($limits as $k=>$max){$d[$k]=sanitize_textarea_field(hpc_input($k));if(($d[$k]===''&&!in_array($k,['project_no'],true))||mb_strlen($d[$k])>$max)throw new RuntimeException('필수 입력과 글자 수를 확인해 주세요: '.$k);}
    if(!is_email($d['email']))throw new RuntimeException('이메일 형식을 확인해 주세요.');
    if(!preg_match('/^[0-9+() -]{7,30}$/',$d['phone']))throw new RuntimeException('전화번호 형식을 확인해 주세요.');
    foreach(['account1','account2'] as $k)if(!preg_match('/^[a-z0-9_]{5,20}$/D',$d[$k]))throw new RuntimeException('희망 계정명은 영문 소문자·숫자·밑줄 5~20자입니다.');
    if($d['account1']===$d['account2'])throw new RuntimeException('계정명 1안과 2안을 다르게 입력해 주세요.');
    foreach(['nodes'=>100,'disk'=>1000000] as $k=>$max){$s=hpc_input($k);if(!ctype_digit($s)||(int)$s<1||(int)$s>$max)throw new RuntimeException('자원 요구량 범위를 확인해 주세요.');$d[$k]=(int)$s;}
    $domains=isset($_POST['domains'])&&is_array($_POST['domains'])?array_map('sanitize_text_field',wp_unslash($_POST['domains'])):[];
    $d['domains']=array_values(array_intersect(hpc_domains(),$domains));if(!$d['domains'])throw new RuntimeException('업무분야를 선택해 주세요.');
    $d['fee']=hpc_input('fee');if(!in_array($d['fee'],['paid','free'],true))throw new RuntimeException('사용료 구분을 선택해 주세요.');
    if(hpc_input('privacy')!=='1'||hpc_input('security')!=='1')throw new RuntimeException('개인정보와 보안 서약 동의가 필요합니다.');
    $d['privacy']=true;$d['security']=true;$d['consent_version']='2027-H1-vf';$d['consented_at']=current_time('mysql');
    $date=hpc_input('date');$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$dt||$dt->format('Y-m-d')!==$date)throw new RuntimeException('작성일을 확인해 주세요.');$d['date']=$date;
    return $d;
}
function hpc_upload($key,$required){
    if(!isset($_FILES[$key])||$_FILES[$key]['error']===UPLOAD_ERR_NO_FILE){if($required)throw new RuntimeException('서명 또는 날인한 신청서를 첨부해 주세요.');return null;}
    $f=$_FILES[$key];if(is_array($f['name'])||$f['error']!==UPLOAD_ERR_OK||$f['size']<8||$f['size']>10*1024*1024)throw new RuntimeException('첨부파일은 10MB 이하로 올려 주세요.');
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));$head=file_get_contents($f['tmp_name'],false,null,0,65536);
    $ok=false;
    if($ext==='pdf')$ok=str_starts_with($head,'%PDF-');
    if($ext==='hwp')$ok=str_starts_with($head,"\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")&&str_contains($head,'HWP Document File');
    if($ext==='hwpx'){
        $z=new ZipArchive();if($z->open($f['tmp_name'])===true){
            $total=0;$safe=$z->numFiles<500;
            for($i=0;$i<$z->numFiles;$i++){$s=$z->statIndex($i);$total+=$s['size'];if(str_contains($s['name'],'..')||str_starts_with($s['name'],'/'))$safe=false;}
            $ok=$safe&&$total<30*1024*1024&&trim((string)$z->getFromName('mimetype'))==='application/hwp+zip'&&$z->locateName('Contents/section0.xml')!==false;$z->close();
        }
    }
    if(!$ok)throw new RuntimeException('실제 HWP·HWPX·PDF 문서만 첨부할 수 있습니다.');
    $dir=hpc_private_dir('uploads');if(!is_dir($dir))mkdir($dir,0700,true);
    $token=bin2hex(random_bytes(20));$path=$dir.'/'.$token.'.'.$ext;
    if(!move_uploaded_file($f['tmp_name'],$path))throw new RuntimeException('첨부파일 저장 실패');chmod($path,0600);
    return ['token'=>$token,'ext'=>$ext,'name'=>sanitize_file_name($f['name']),'at'=>current_time('mysql'),'type'=>$key,'sha256'=>hash_file('sha256',$path)];
}
function hpc_post(){
    hpcm_require();
    global $wpdb;
    if(!wp_verify_nonce(hpc_input('_wpnonce'),'hpc_lab'))throw new RuntimeException('요청 인증이 만료되었습니다. 새로고침 후 다시 시도해 주세요.');
    $op=hpc_input('op');$id=absint(hpc_input('id'));$newfiles=[];$wpdb->query('START TRANSACTION');
    try{
        if($op==='save'){
            $a=$id?hpc_get($id,true):null;$template=$a?hpc_app_template($a):hpc_active_template();
            if(!$a&&absint(hpc_input('template_id'))!==(int)$template->id)throw new RuntimeException('신청 양식이 변경되었습니다. 작성 화면을 새로 열어 주세요.');
            $data=$template->engine==='legacy'?hpc_validate():hpc_schema_validate(json_decode($template->config,true),wp_unslash($_POST));
            $data['_template_id']=(int)$template->id;
            if($id){$a=hpc_get($id,true);if((int)$a->owner!==get_current_user_id()||!in_array($a->state,['draft','needs_revision'],true))throw new RuntimeException('작성 중이거나 보완 요청된 본인 신청만 수정할 수 있습니다.');
                if((int)$a->version!==absint(hpc_input('version')))throw new RuntimeException('다른 화면에서 변경됐습니다. 새로고침해 주세요.');
                $history=json_decode($a->history,true);$history[]=array_merge(hpc_event('save','작성 내용 저장'),['previous_data'=>json_decode($a->data,true),'previous_version'=>(int)$a->version]);
                $ok=$wpdb->update(hpc_table(),['data'=>wp_json_encode($data),'version'=>$a->version+1,'history'=>wp_json_encode($history),'updated'=>current_time('mysql')],['id'=>$id]);
            }else{$ok=$wpdb->insert(hpc_table(),['owner'=>get_current_user_id(),'state'=>'draft','data'=>wp_json_encode($data),'files'=>'[]','history'=>wp_json_encode([hpc_event('save','신청 작성 시작')]),'created'=>current_time('mysql'),'updated'=>current_time('mysql')]);$id=$wpdb->insert_id;}
        }elseif($op==='submit'||$op==='transition'){
            $a=hpc_get($id,true);$history=json_decode($a->history,true);
            if((int)$a->version!==absint(hpc_input('version')))throw new RuntimeException('다른 화면에서 변경됐습니다. 새로고침해 주세요.');
            if($op==='submit'){
                if((int)$a->owner!==get_current_user_id()||!in_array($a->state,['draft','needs_revision'],true))throw new RuntimeException('지금은 제출할 수 없습니다.');
                if(hpc_input('attest')!=='1')throw new RuntimeException('최종 문서 확인에 체크해 주세요.');
                $newfiles[]=hpc_upload('signed',true);$official=hpc_upload('official',false);if($official)$newfiles[]=$official;
                $reference=sanitize_text_field(hpc_input('official_ref'));
                if(!$official&&$reference==='')throw new RuntimeException('기관 공문을 첨부하거나 별도 발송 정보를 입력해 주세요.');
                if(mb_strlen($reference)>300)throw new RuntimeException('공문 발송 정보는 300자 이하입니다.');
                foreach($newfiles as $f)hpco_record($f);
                $allfiles=array_merge(json_decode($a->files,true),$newfiles);$history[]=hpc_event('submitted','최종 제출'.($reference?' / 공문: '.$reference:''));
                $receipt=$a->receipt?:'HPC-TEST-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
                $ok=$wpdb->update(hpc_table(),['state'=>'submitted','receipt'=>$receipt,'files'=>wp_json_encode($allfiles),'history'=>wp_json_encode($history),'version'=>$a->version+1,'updated'=>current_time('mysql')],['id'=>$id]);
            }else{
                if(!hpc_admin())throw new RuntimeException('관리자만 심사 상태를 변경할 수 있습니다.');
                $next=hpc_input('next');$note=sanitize_textarea_field(hpc_input('note'));
                if(!in_array($next,hpc_transitions()[$a->state]??[],true))throw new RuntimeException('허용되지 않는 단계 변경입니다.');
                if($note===''||mb_strlen($note)>1000)throw new RuntimeException('사용자에게 공개할 사유를 1~1000자로 입력해 주세요.');
                $history[]=hpc_event($next,$note);$ok=$wpdb->update(hpc_table(),['state'=>$next,'history'=>wp_json_encode($history),'version'=>$a->version+1,'updated'=>current_time('mysql')],['id'=>$id]);
            }
        }elseif($op==='message'){
            $kind=hpc_input('kind');if(!in_array($kind,['board','qna'],true)||($kind==='board'&&!hpc_admin()))throw new RuntimeException('게시 권한이 없습니다.');
            $title=sanitize_text_field(hpc_input('title'));$body=sanitize_textarea_field(hpc_input('body'));
            if(!$title||!$body||mb_strlen($title)>150||mb_strlen($body)>3000)throw new RuntimeException('제목·내용 길이를 확인해 주세요.');
            $ok=$wpdb->insert(hpc_table('messages'),['owner'=>get_current_user_id(),'kind'=>$kind,'title'=>$title,'body'=>$body,'answer'=>'','created'=>current_time('mysql')]);
        }elseif($op==='answer'){
            if(!hpc_admin())throw new RuntimeException('관리자만 답변할 수 있습니다.');$answer=sanitize_textarea_field(hpc_input('answer'));
            if(!$answer||mb_strlen($answer)>3000)throw new RuntimeException('답변은 1~3000자입니다.');
            $ok=$wpdb->update(hpc_table('messages'),['answer'=>$answer],['id'=>$id,'kind'=>'qna']);
        }else throw new RuntimeException('지원하지 않는 요청입니다.');
        if($ok===false)throw new RuntimeException('저장 실패');$wpdb->query('COMMIT');
    }catch(Throwable $e){$wpdb->query('ROLLBACK');foreach($newfiles as $f)if($f)@unlink(hpc_private_dir('uploads/'.$f['token'].'.'.$f['ext']));throw $e;}
    $view=in_array($op,['message','answer'],true)?(hpc_input('kind')?:'qna'):'detail';
    wp_safe_redirect(hpc_url(['view'=>$view,'id'=>$id,'saved'=>1]));exit;
}
function hpc_download(){
    hpcm_require();
    if(!wp_verify_nonce($_GET['_wpnonce']??'','hpc_download'))wp_die('인증 오류','',403);
    $a=hpc_get(absint($_GET['id']??0));$format=sanitize_key($_GET['format']??'');
    if($format){$path=hpc_document($a,$format);$name='application-'.$a->id.'-v'.$a->version.'.'.$format;}
    else{
        $path=null;$token=sanitize_key($_GET['file']??'');
        foreach(json_decode($a->files,true) as $f)if(hash_equals($f['token'],$token)){hpco_require_clean($f);$path=hpc_private_dir('uploads/'.$f['token'].'.'.$f['ext']);$name=$f['name'];break;}
        if(!$path)throw new RuntimeException('첨부파일 접근 오류');
    }
    if(!is_file($path))throw new RuntimeException('파일 없음');
    nocache_headers();header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name));header('Content-Length: '.filesize($path));readfile($path);exit;
}
function hpc_nonce($op,$a=null){wp_nonce_field('hpc_lab');echo '<input type="hidden" name="op" value="'.esc_attr($op).'">';if($a)echo '<input type="hidden" name="id" value="'.(int)$a->id.'"><input type="hidden" name="version" value="'.(int)$a->version.'">';}
function hpc_link($label,$args){return '<a href="'.esc_url(hpc_url($args)).'">'.esc_html($label).'</a>';}
function hpc_download_link($a,$format){return hpc_link(strtoupper($format).' 다운로드',['view'=>'download','id'=>$a->id,'format'=>$format,'_wpnonce'=>wp_create_nonce('hpc_download')]);}
function hpc_form($a=null){
    $template=$a?hpc_app_template($a):hpc_active_template();
    if($template->engine!=='legacy'){hpc_schema_form($template,$a);return;}
    $d=$a?json_decode($a->data,true):[];
    if($_SERVER['REQUEST_METHOD']==='POST'&&hpc_input('op')==='save'){
        foreach($_POST as $k=>$v)if(is_scalar($v))$d[$k]=sanitize_textarea_field(wp_unslash($v));
        $d['domains']=isset($_POST['domains'])&&is_array($_POST['domains'])?array_values(array_intersect(hpc_domains(),wp_unslash($_POST['domains']))):[];
        $d['privacy']=hpc_input('privacy')==='1';$d['security']=hpc_input('security')==='1';
    }
    echo '<h2>신청서 작성</h2><p>가상 정보만 입력해 주세요. 필수 항목을 채워 저장한 뒤 문서를 다운로드합니다. 연구내용은 PDF에서 줄바꿈을 확인해 주세요.</p><form method="post">';hpc_nonce('save',$a);
    echo '<input type="hidden" name="template_id" value="'.(int)$template->id.'">';
    $fields=[
        'organization'=>['소속기관',100],'department'=>['부서',100],'position'=>['직급',50],'name'=>['신청인 성명',40],'email'=>['이메일',120,'email'],'phone'=>['전화번호',30],
        'project'=>['과제명',160],'project_no'=>['과제번호 (없으면 비워두기)',60],
        'purpose'=>['연구 목적',400,'textarea'],'method'=>['수행내용 및 방법',1500,'textarea'],
        'nodes'=>['노드 수 (1~100)',100,'number'],'disk'=>['디스크 용량 GB (1~1,000,000)',1000000,'number'],'programs'=>['이용 프로그램',200],
        'account1'=>['희망 계정명 1안',20],'account2'=>['희망 계정명 2안',20],'approver'=>['확인자·부서장 성명',40],'date'=>['작성일',10,'date']
    ];
    echo '<p><strong>공동활용 신청 / 사용기간: 2027. 1. 1. ~ 2027. 6. 30.</strong><br>과제번호가 있으면 입력하고, 자체 연구는 과제명에 “과제 없음”을 표시하세요. 100노드 초과는 별도 공문 대상입니다.</p>';
    foreach($fields as $k=>$f){$type=$f[2]??'text';$value=$d[$k]??($k==='date'?current_time('Y-m-d'):'');echo '<label for="'.$k.'">'.esc_html($f[0]).'</label>';
        if($type==='textarea')echo '<textarea id="'.$k.'" name="'.$k.'" maxlength="'.$f[1].'" rows="4" required>'.esc_textarea($value).'</textarea>';
        else echo '<input id="'.$k.'" name="'.$k.'" type="'.$type.'" '.($type==='number'?'min="1" max="'.$f[1].'"':'maxlength="'.$f[1].'"').' value="'.esc_attr($value).'" '.($k==='project_no'?'':'required').'>';
    }
    echo '<fieldset><legend>업무분야 (복수 선택 가능)</legend>';foreach(hpc_domains() as $v)echo '<label class="inline"><input type="checkbox" name="domains[]" value="'.esc_attr($v).'" '.checked(in_array($v,$d['domains']??[],true),true,false).'>'.esc_html($v).'</label>';echo '</fieldset>';
    echo '<label for="fee">사용료 구분</label><select id="fee" name="fee" required><option value="">선택</option><option value="paid" '.selected($d['fee']??'','paid',false).'>유상사용 신청</option><option value="free" '.selected($d['fee']??'','free',false).'>무상사용 신청</option></select>';
    echo '<p>희망 계정명은 사이트 로그인과 별개이며, 소문자·숫자·밑줄 5~20자로 입력합니다. Password는 문서에 “개별통보”로 유지됩니다.</p>';
    echo '<details><summary>개인정보·보안 안내 (테스트용)</summary><p>이 테스트는 가상 정보만 사용합니다. 입력한 기관·성명·연락처·연구내용·계정명은 테스트 DB와 생성 문서에 저장됩니다. 운영용 동의 문구와 보유기간 확정은 별도입니다. 원본 양식의 개인정보 안내와 보안 규정은 다운로드 문서에 유지됩니다.</p></details>';
    echo '<label class="inline"><input name="privacy" type="checkbox" value="1" required '.checked($d['privacy']??false,true,false).'>개인정보 안내 확인 및 동의 (가상 정보 테스트)</label><label class="inline"><input name="security" type="checkbox" value="1" required '.checked($d['security']??false,true,false).'>보안 서약 확인 및 동의 (테스트)</label><button>작성 내용 저장</button></form>';
}
function hpc_detail($a){
    $d=json_decode($a->data,true);$template=hpc_app_template($a);echo '<h2>'.esc_html(hpc_summary($a,'title')).'</h2><p><strong>상태: '.esc_html(hpc_states()[$a->state]).'</strong> / '.esc_html($a->receipt?:'접수번호 미발급').' / 처리 버전 '.(int)$a->version.'</p><p>작성 양식: '.esc_html($template->name).' (버전 '.(int)$template->id.')</p>';
    echo '<div class="actions">'.hpc_download_link($a,'hwpx').' '.hpc_download_link($a,'hwp').' '.hpc_download_link($a,'pdf').'</div><p>다운로드만으로는 접수되지 않습니다. 내용을 확인하고 서명·날인 후 다시 첨부하세요. 생성 문서는 서식 호환성 검증용입니다.</p>';
    $labels=['organization'=>'소속기관','department'=>'부서','position'=>'직급','name'=>'성명','email'=>'이메일','phone'=>'전화번호','project'=>'과제명','project_no'=>'과제번호','purpose'=>'목적','method'=>'수행내용 및 방법','nodes'=>'노드 수','disk'=>'디스크 GB','programs'=>'이용 프로그램','account1'=>'희망 계정명 1안','account2'=>'희망 계정명 2안','approver'=>'확인자','date'=>'작성일','domains'=>'업무분야','fee'=>'사용료 구분','privacy'=>'개인정보 동의','security'=>'보안 서약 동의','consent_version'=>'동의 양식 버전','consented_at'=>'동의 시각'];
    if($template->engine!=='legacy')foreach(json_decode($template->config,true)['fields'] as $f)$labels[$f['id']]=$f['label'];
    echo '<details><summary>저장된 작성 내용</summary><dl>';foreach($d as $k=>$v){if(str_starts_with($k,'_'))continue;if(is_bool($v))$v=$v?'동의':'미동의';if($k==='fee'&&$template->engine==='legacy')$v=$v==='paid'?'유상사용 신청':'무상사용 신청';echo '<dt>'.esc_html($labels[$k]??$k).'</dt><dd>'.esc_html(is_array($v)?implode(', ',$v):(string)$v).'</dd>';}echo '</dl></details>';
    if((int)$a->owner===get_current_user_id()&&in_array($a->state,['draft','needs_revision'],true)){
        echo '<p>'.hpc_link('작성 내용 수정',['view'=>'apply','id'=>$a->id]).'</p><h3>서명·날인 후 최종 제출</h3><form method="post" enctype="multipart/form-data">';hpc_nonce('submit',$a);
        echo '<label for="signed">최종 신청서 (HWP·HWPX·PDF, 10MB 이하)</label><input id="signed" type="file" name="signed" accept=".hwp,.hwpx,.pdf" required><label for="official">기관 공문 (선택)</label><input id="official" type="file" name="official" accept=".hwp,.hwpx,.pdf"><label for="official_ref">공문 별도 발송 정보 (공문을 첨부하지 않으면 필수)</label><input id="official_ref" name="official_ref" maxlength="300" placeholder="발송일·문서번호·발송 경로"><label class="inline"><input type="checkbox" name="attest" value="1" required>최종 첨부 문서의 내용과 서명·날인을 확인했습니다. 테스트 시에는 가상 문서를 사용합니다.</label><button>최종 신청 제출</button></form>';
    }
    echo '<h3>제출 파일 이력</h3><ul>';foreach(json_decode($a->files,true) as $f)echo '<li>'.esc_html($f['at'].' / '.($f['type']==='signed'?'신청서':'공문').' / '.hpco_label($f)).' '.hpc_link($f['name'],['view'=>'download','id'=>$a->id,'file'=>$f['token'],'_wpnonce'=>wp_create_nonce('hpc_download')]).'</li>';echo '</ul>';
    if(hpc_admin()&&($next=hpc_transitions()[$a->state]??[])){
        echo '<h3>관리자 처리</h3><form method="post">';hpc_nonce('transition',$a);echo '<label for="next">다음 단계</label><select name="next" id="next">';foreach($next as $s)echo '<option value="'.$s.'">'.esc_html(hpc_states()[$s]).'</option>';echo '</select><label for="note">사용자에게 공개할 처리 사유</label><textarea name="note" id="note" maxlength="1000" required></textarea><button>상태 변경</button></form>';
    }
    echo '<h3>처리 이력</h3><ol>';foreach(json_decode($a->history,true) as $h){$actor=get_userdata($h['by']);echo '<li>'.esc_html($h['at'].' / '.(hpc_states()[$h['type']]??'작성 저장').' / '.($actor?$actor->display_name:'삭제된 사용자').' / '.$h['text']).'</li>';}echo '</ol>';
}
function hpc_listing(){
    global $wpdb;$rows=hpc_admin()?$wpdb->get_results('SELECT * FROM '.hpc_table().' ORDER BY id DESC LIMIT 100'):$wpdb->get_results($wpdb->prepare('SELECT * FROM '.hpc_table().' WHERE owner=%d ORDER BY id DESC LIMIT 100',get_current_user_id()));
    echo '<h2>'.(hpc_admin()?'관리자 신청 관리':'내 신청현황').'</h2><table><thead><tr><th>접수번호</th><th>과제명</th><th>신청인</th><th>상태</th></tr></thead><tbody>';
    foreach($rows as $a){echo '<tr><td>'.esc_html($a->receipt?:'작성 중 #'.$a->id).'</td><td>'.hpc_link(hpc_summary($a,'title'),['view'=>'detail','id'=>$a->id]).'</td><td>'.esc_html(hpc_summary($a,'name')).'</td><td>'.esc_html(hpc_states()[$a->state]).'</td></tr>';}
    if(!$rows)echo '<tr><td colspan="4">신청 내역이 없습니다.</td></tr>';echo '</tbody></table>';
}
function hpc_messages($kind){
    global $wpdb;$where=$kind==='board'||hpc_admin()?'':$wpdb->prepare(' AND owner=%d',get_current_user_id());
    $q=isset($_GET['q'])?sanitize_text_field(wp_unslash($_GET['q'])):'';
    if($q!=='')$where.=$wpdb->prepare(' AND (title LIKE %s OR body LIKE %s)','%'.$wpdb->esc_like($q).'%','%'.$wpdb->esc_like($q).'%');
    $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.hpc_table('messages').' WHERE kind=%s',$kind).$where.' ORDER BY id DESC LIMIT 100');
    echo '<h2>'.($kind==='board'?'게시판':'비공개 Q&A').'</h2><p>'.($kind==='board'?'관리자가 공지를 등록합니다.':'질문은 작성자 본인과 관리자만 볼 수 있습니다.').'</p><form method="get"><input type="hidden" name="hpc_lab" value="1"><input type="hidden" name="view" value="'.$kind.'"><label for="q">제목·내용 검색</label><input id="q" name="q" value="'.esc_attr($q).'"><button>검색</button></form>';
    foreach($rows as $m){echo '<article><h3>'.esc_html($m->title).'</h3><p>'.nl2br(esc_html($m->body)).'</p><p>답변: '.nl2br(esc_html($m->answer?:'답변 대기')).'</p>';
        if(hpc_admin()&&$kind==='qna'){echo '<form method="post">';hpc_nonce('answer');echo '<input type="hidden" name="id" value="'.(int)$m->id.'"><input type="hidden" name="kind" value="qna"><label>관리자 답변<textarea name="answer" maxlength="3000" required>'.esc_textarea($m->answer).'</textarea></label><button>답변 저장</button></form>';}
        echo '</article>';
    }
    if(!$rows)echo '<p>등록된 글이 없습니다.</p>';
    if($kind==='qna'||hpc_admin()){echo '<h3>글 작성</h3><form method="post">';hpc_nonce('message');echo '<input type="hidden" name="kind" value="'.$kind.'"><label for="title">제목</label><input id="title" name="title" maxlength="150" required><label for="body">내용</label><textarea id="body" name="body" maxlength="3000" required></textarea><button>글 등록</button></form>';}
}
add_action('template_redirect',function(){
    if(!isset($_GET['hpc_lab']))return;
    if(hpc_private_base()==='')wp_die('신청 시스템 저장 경로 설정이 필요합니다.');
    nocache_headers();header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; img-src 'self' blob: data:; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'self'");
    if(!is_user_logged_in()){auth_redirect();exit;}
    $view=sanitize_key($_GET['view']??'status');$error='';
    try{if($_SERVER['REQUEST_METHOD']==='POST')hpc_post();if($view==='download')hpc_download();}catch(Throwable $e){$error=$e->getMessage();status_header(400);}
    ?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>슈퍼컴퓨팅센터 기능 테스트</title><style>
    *{box-sizing:border-box}body{font:16px/1.6 system-ui,sans-serif;color:#172b3a;background:#f4f6f8;margin:0}header,main{max-width:1020px;margin:auto;padding:24px}header{background:white}nav,.actions{display:flex;gap:18px;flex-wrap:wrap}a{color:#0759a0}main{background:white;margin:20px auto}label{display:block;margin-top:14px;font-weight:600}input:not([type=checkbox]),select,textarea{width:100%;padding:10px;border:1px solid #8d9dab;border-radius:4px;font:inherit}input[type=checkbox]{width:18px;height:18px}.inline{display:inline-flex;gap:8px;align-items:center;margin-right:16px}button{display:block;background:#0759a0;color:white;border:0;padding:12px 24px;margin:20px 0;cursor:pointer;font:inherit;border-radius:4px}fieldset,article,details{border:1px solid #bcc8d3;padding:16px;margin:20px 0}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #ccd5de}th{background:#edf2f7}.banner{background:#fff4d1;padding:12px}.error{border:2px solid #a62424;padding:14px;color:#a62424}.success{background:#e6f4e9;padding:12px}dt{font-weight:bold}dd{white-space:pre-wrap;margin-bottom:8px}a:focus-visible,button:focus-visible,input:focus-visible,textarea:focus-visible{outline:3px solid #ec9900}form{max-width:800px}@media(max-width:640px){header,main{padding:16px}table{font-size:13px}th,td{padding:8px}.actions{gap:10px}}
    </style></head><body><header><h1>슈퍼컴퓨팅센터 기능 테스트</h1><p class="banner">로컬 시연 전용 · 실제 접수 아님 · 가상 정보만 사용 · 이메일 발송 없음</p><p><?php echo esc_html(wp_get_current_user()->user_login).' / '.(hpc_admin()?'관리자':'일반 사용자');?> · <a href="<?php echo esc_url(wp_logout_url(hpc_url()));?>">로그아웃</a></p><nav><?php echo hpc_link('신청서 작성',['view'=>'apply']).hpc_link(hpc_admin()?'관리자 신청 관리':'내 신청현황',['view'=>'status']).hpc_link('게시판',['view'=>'board']).hpc_link('Q&A',['view'=>'qna']);if(hpc_admin())echo '<a href="'.esc_url(hpc_template_admin_url()).'">신청 양식 관리</a>';?></nav></header><main>
    <?php if($error)echo '<p class="error" role="alert">'.esc_html($error).'</p>';elseif(isset($_GET['saved']))echo '<p class="success" role="status">저장되었습니다.</p>';
    try{
        if($view==='apply'){$a=!empty($_GET['id'])?hpc_get(absint($_GET['id'])):null;if($a&&((int)$a->owner!==get_current_user_id()||!in_array($a->state,['draft','needs_revision'],true)))throw new RuntimeException('수정할 수 없는 신청입니다.');hpc_form($a);hpc_live_preview_ui($a);}
        elseif($view==='detail')hpc_detail(hpc_get(absint($_GET['id']??0)));
        elseif(in_array($view,['board','qna'],true))hpc_messages($view);
        elseif($view!=='download')hpc_listing();
    }catch(Throwable $e){echo '<p class="error" role="alert">'.esc_html($e->getMessage()).'</p>';}
    ?></main></body></html><?php exit;
});
