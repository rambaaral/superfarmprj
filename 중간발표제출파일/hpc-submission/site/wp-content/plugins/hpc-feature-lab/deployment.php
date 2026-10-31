<?php
if(!defined('ABSPATH'))exit;
require_once __DIR__.'/portal-installer.php';
function hpc_host_suggested_dir(){
    $root=wp_normalize_path(realpath(ABSPATH)?:ABSPATH);
    return preg_match('~^(/home/[^/]+)/~',$root,$m)?$m[1].'/hpc-portal-private':'';
}
function hpc_host_configure_storage($path){
    if(defined('HPC_PRIVATE_DIR'))throw new RuntimeException('wp-config.php에서 저장 경로를 지정한 환경입니다. 기존 설정을 유지합니다.');
    $suggested=hpc_host_suggested_dir();
    if($suggested===''||$path!==$suggested)throw new RuntimeException('이 설치 도구는 계정 홈 바로 아래 hpc-portal-private 폴더만 허용합니다. 권장 경로를 사용해 주세요.');
    $old=hpc_private_base();if($old!==''&&$old!==$path)throw new RuntimeException('기존 데이터가 있으므로 저장 경로를 변경할 수 없습니다.');
    $home=realpath(dirname($path));if(!$home||is_link($path))throw new RuntimeException('계정 홈 경로를 확인해 주세요.');
    if(!is_dir($path)&&!mkdir($path,0700))throw new RuntimeException('저장 폴더 생성 권한이 없습니다. cPanel에서 권장 폴더를 만들고 권한을 0700으로 설정해 주세요.');
    $real=realpath($path);
    if(!$real||dirname($real)!==$home||basename($real)!=='hpc-portal-private')throw new RuntimeException('비공개 폴더 실제 경로가 일치하지 않습니다.');
    foreach([ABSPATH,$_SERVER['DOCUMENT_ROOT']??''] as $web){if($web==='')continue;$web=realpath($web);if($web&&($real===$web||str_starts_with($real,rtrim($web,'/').'/')))throw new RuntimeException('웹 공개 폴더 안에는 신청 파일을 저장할 수 없습니다.');}
    chmod($real,0700);if(!is_writable($real))throw new RuntimeException('비공개 폴더에 쓸 수 없습니다.');
    foreach(['templates','uploads','generated','preview'] as $part){$dir=$real.'/'.$part;if(is_link($dir))throw new RuntimeException('저장 하위 폴더의 바로가기를 제거해 주세요.');if(!is_dir($dir)&&!mkdir($dir,0700))throw new RuntimeException('하위 폴더 생성 실패');chmod($dir,0700);}
    // Defense in depth. This folder must never be registered as a domain document root.
    file_put_contents($real.'/.htaccess',"Require all denied\n");chmod($real.'/.htaccess',0600);
    update_option('hpc_host_private_dir',$real,false);hpc_install();hpc_templates_install();
    if(get_option('hpc_lab_template_schema')!=='1')throw new RuntimeException('초기 양식 생성에 실패했습니다. PHP 확장과 기본 양식 파일을 확인해 주세요.');
}
function hpc_host_checks(){
    $r=['PHP 8.2 이상'=>version_compare(PHP_VERSION,'8.2','>='),'HTTPS'=>is_ssl(),'ZipArchive'=>class_exists('ZipArchive'),'DOM XML'=>class_exists('DOMDocument'),'mbstring'=>function_exists('mb_strlen'),'fileinfo'=>extension_loaded('fileinfo'),'기본 양식 파일'=>is_readable(hpc_template_source()),'테마 설치'=>wp_get_theme('hpc-portal-pages')->exists(),'비공개 폴더'=>hpc_private_base()!==''&&is_writable(hpc_private_base())];
    if(hpc_private_base()!==''){$c=hpc_converter_config();$r+=['proc_open (HWP/PDF)'=>function_exists('proc_open'),'프로세스 제어 (HWP/PDF)'=>function_exists('proc_get_status')&&function_exists('proc_terminate')&&function_exists('proc_close'),'rhwp 실행 권한 (HWP/PDF)'=>is_executable($c['bin']),'PDF 글꼴'=>is_dir($c['fonts'])&&count(glob($c['fonts'].'/*')?:[])>0];}
    return $r;
}
add_action('admin_menu',function(){add_management_page('HPC 설치','HPC 설치','manage_options','hpc-deploy','hpc_host_page');});
add_action('admin_notices',function(){if(current_user_can('manage_options')&&hpc_private_base()==='')echo '<div class="notice notice-warning"><p>HPC 포털: <a href="'.esc_url(admin_url('tools.php?page=hpc-deploy')).'">도구 → HPC 설치</a>에서 저장 폴더와 페이지를 설정해 주세요.</p></div>';});
function hpc_host_process_setup(){
    if(!current_user_can('manage_options'))wp_die('권한이 없습니다.','',403);
    $message='';$error='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_admin_referer('hpc_host_setup');
        try{
            $op=sanitize_key($_POST['setup_op']??'');
            if($op==='storage'){
                foreach(['ZipArchive','DOMDocument'] as $class)if(!class_exists($class))throw new RuntimeException('PHP 확장 사용 필요: '.$class);
                if(!function_exists('mb_strlen'))throw new RuntimeException('PHP mbstring 확장을 켜 주세요.');
                hpc_host_configure_storage(trim(wp_unslash((string)($_POST['private_dir']??''))));$message='비공개 저장 폴더와 초기 양식을 준비했습니다.';
            }elseif($op==='pages'){
                if(!current_user_can('unfiltered_html'))throw new RuntimeException('이 설치에는 단일 사이트 최고 관리자 권한이 필요합니다.');
                if(hpc_private_base()==='')throw new RuntimeException('저장 폴더를 먼저 준비해 주세요.');
                $result=hpc_install_portal_pages();$message='10개 페이지 적용 완료. '.implode(' / ',array_map(fn($k,$v)=>$k.': '.$v,array_keys($result['results']),$result['results']));
            }elseif($op==='output'){
                if(!is_ssl()&&PHP_SAPI!=='cli')throw new RuntimeException('HTTPS에서 검사해 주세요.');
                $t=hpc_active_template();$app=hpc_template_test_app($t);$formats=[];
                foreach(['hwpx','hwp','pdf'] as $fmt){try{$f=hpc_document($app,$fmt);$formats[$fmt]=['ok'=>is_file($f)&&filesize($f)>100,'message'=>'생성 성공'];}catch(Throwable $e){$formats[$fmt]=['ok'=>false,'message'=>$e->getMessage()];}}
                update_option('hpc_host_output_check',['at'=>current_time('mysql'),'formats'=>$formats],false);$message='가상 데이터로 출력 검사를 마쳤습니다. 아래 결과를 확인하세요.';
            }else throw new RuntimeException('지원하지 않는 작업입니다.');
        }catch(Throwable $e){$error=$e->getMessage();}
    }
    return [$message,$error];
}
// Authenticate and process POST before the WordPress admin sends page headers.
add_action('load-tools_page_hpc-deploy',function(){$GLOBALS['hpc_host_feedback']=hpc_host_process_setup();});
function hpc_host_page(){
    if(!current_user_can('manage_options'))wp_die('권한이 없습니다.','',403);
    [$message,$error]=$GLOBALS['hpc_host_feedback']??['',''];
    echo '<div class="wrap"><h1>HPC 설치</h1><p>기존 WordPress의 데이터베이스를 그대로 사용합니다. 시험 계정과 로컬 신청 내역은 가져오지 않습니다.</p>';
    if($message)echo '<div class="notice notice-success"><p>'.esc_html($message).'</p></div>';
    if($error)echo '<div class="notice notice-error"><p>'.esc_html($error).'</p></div>';
    echo '<h2>1. 환경 확인</h2><table class="widefat striped" style="max-width:850px"><thead><tr><th>항목</th><th>확인 결과</th></tr></thead><tbody>';
    foreach(hpc_host_checks() as $label=>$ok)echo '<tr><td>'.esc_html($label).'</td><td>'.($ok?'✓ 확인됨':'설정 필요').'</td></tr>';
    echo '</tbody></table><p>PHP 버전: '.esc_html(PHP_VERSION).' · 출력 검사가 성공하기 전에는 HWP/PDF 지원을 확정할 수 없습니다.</p>';
    echo '<h2>2. 비공개 저장 폴더 준비</h2><p>이 폴더는 public_html, 도메인 폴더, wp-content 밖에 있어야 합니다. 도메인의 문서 루트로 지정하지 마세요.</p><form method="post">';wp_nonce_field('hpc_host_setup');
    echo '<input type="hidden" name="setup_op" value="storage"><label for="private_dir">저장 폴더</label><br><input id="private_dir" name="private_dir" class="large-text code" style="max-width:850px" value="'.esc_attr(hpc_private_base()?:hpc_host_suggested_dir()).'" readonly>';
    submit_button('비공개 폴더 준비');echo '</form><h2>3. 테마와 10개 페이지 적용</h2><p>최초 적용 시 HPC Portal Pages를 활성화하고 메인페이지를 홈페이지로 지정합니다. 기존 홈페이지 설정은 보관하고, 기존 페이지의 직접 편집한 본문은 덮어쓰지 않습니다. 적용 전에 현재 사이트를 백업하세요.</p><form method="post">';wp_nonce_field('hpc_host_setup');echo '<input type="hidden" name="setup_op" value="pages">';submit_button('10개 페이지 생성·적용');echo '</form>';
    echo '<h2>4. 문서 출력 검사</h2><p>runtime 폴더를 비공개 폴더 아래에 올린 뒤 검사합니다. 서버 실행 권한이나 라이브러리가 맞지 않으면 HWP/PDF 변환이 실패할 수 있습니다. HWPX와 문서 편집기는 별도 기능입니다.</p><form method="post">';wp_nonce_field('hpc_host_setup');echo '<input type="hidden" name="setup_op" value="output">';submit_button('HWPX · HWP · PDF 검사','secondary');echo '</form>';
    $check=get_option('hpc_host_output_check');if($check){echo '<p>최근 검사: '.esc_html($check['at']).'</p><ul>';foreach($check['formats'] as $fmt=>$r)echo '<li><strong>'.esc_html(strtoupper($fmt)).'</strong> — '.esc_html(($r['ok']?'성공: ':'실패: ').$r['message']).'</li>';echo '</ul>';}
    echo '<h2>5. 사이트 확인</h2><p><a class="button" href="'.esc_url(home_url('/')).'">사이트 열기</a> <a class="button" href="'.esc_url(admin_url('admin.php?page=hpc-templates')).'">신청 양식 관리</a></p><p>현재 버전은 시험 운영용입니다. 실제 개인정보 접수 전에 보관·파기, 접근 기록, 업로드 검사 등 기존 보안 점검의 미완료 항목을 보강해야 합니다.</p></div>';
}
