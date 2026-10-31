<?php
// Local, fail-closed attachment scanning. No documents are sent to external services.
if (!defined('ABSPATH')) exit;
function hpco_table(){global $wpdb;return $wpdb->prefix.'hpc_lab_scans';}
add_filter('cron_schedules',function($s){$s['hpc_five_minutes']=['interval'=>300,'display'=>'HPC every five minutes'];return $s;});
function hpco_install(){
    global $wpdb;
    if(get_option('hpco_schema')!=='1'){
        require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();
        dbDelta('CREATE TABLE '.hpco_table()." (
          token char(40) NOT NULL,
          ext varchar(4) NOT NULL,
          sha char(64) NOT NULL,
          state varchar(16) NOT NULL DEFAULT 'queued',
          result varchar(40) NOT NULL DEFAULT '',
          checked bigint unsigned NOT NULL DEFAULT 0,
          PRIMARY KEY  (token), KEY state (state)
        ) ENGINE=InnoDB $c;");
        if($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like(hpco_table())))===hpco_table())update_option('hpco_schema','1',false);
    }
    if(!wp_next_scheduled('hpc_ops_tick'))wp_schedule_event(time()+30,'hpc_five_minutes','hpc_ops_tick');
    // Repair a missing membership cleanup schedule, including after a cron reset.
    if(!wp_next_scheduled('hpcm_cleanup'))wp_schedule_event(time()+3600,'hourly','hpcm_cleanup');
}
add_action('init','hpco_install',5);
function hpco_scanner(){
    $paths=defined('HPC_SCAN_BIN')?[HPC_SCAN_BIN]:['/usr/local/cpanel/3rdparty/bin/clamscan','/usr/bin/clamscan','/usr/local/bin/clamscan'];
    foreach($paths as $p)if(@is_file($p)&&@is_executable($p))return $p;
    return '';
}
function hpco_exec($args,$seconds=45){
    foreach(['proc_open','proc_get_status','proc_terminate','proc_close'] as $fn)if(!function_exists($fn))return ['state'=>'error','result'=>'process_unavailable'];
    $p=@proc_open($args,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))return ['state'=>'error','result'=>'start_failed'];
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);
    $until=microtime(true)+$seconds;$code=-1;$timeout=false;
    while(true){fread($pipes[1],8192);fread($pipes[2],8192);$s=proc_get_status($p);if(!$s['running']){$code=$s['exitcode'];break;}if(microtime(true)>$until){$timeout=true;proc_terminate($p,9);break;}usleep(100000);}
    fclose($pipes[1]);fclose($pipes[2]);
    $closed=proc_close($p);if($code<0&&$closed>=0)$code=$closed;
    if($timeout)return ['state'=>'error','result'=>'timeout'];
    return $code===0?['state'=>'clean','result'=>'scan_passed']:($code===1?['state'=>'blocked','result'=>'scanner_alert']:['state'=>'error','result'=>'scanner_error']);
}
function hpco_record($f){
    global $wpdb;
    if(!preg_match('/^[a-f0-9]{40}$/D',$f['token']??'')||!in_array($f['ext']??'', ['pdf','hwp','hwpx','jpg','jpeg'],true)||!preg_match('/^[a-f0-9]{64}$/D',$f['sha256']??''))throw new RuntimeException('첨부파일 검사 정보가 올바르지 않습니다.');
    $ok=$wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.hpco_table().' (token,ext,sha,state,result,checked) VALUES (%s,%s,%s,%s,%s,0)',$f['token'],$f['ext'],$f['sha256'],'queued',''));
    if($ok===false)throw new RuntimeException('파일 검사 대기 등록에 실패했습니다.');
    return $wpdb->get_row($wpdb->prepare('SELECT * FROM '.hpco_table().' WHERE token=%s',$f['token']));
}
function hpco_path($r){
    if(!preg_match('/^[a-f0-9]{40}$/D',$r->token)||!in_array($r->ext,['pdf','hwp','hwpx','jpg','jpeg'],true))return '';
    $p=hpc_private_dir('uploads/'.$r->token.'.'.$r->ext);
    if(is_link($p)||!is_file($p)||filesize($p)>10*1024*1024)return '';
    return $p;
}
function hpco_scan_one($r){
    global $wpdb;
    $p=hpco_path($r);$bin=hpco_scanner();
    if(!$p)$out=['state'=>'error','result'=>'file_unavailable'];
    elseif(!hash_equals($r->sha,hash_file('sha256',$p)))$out=['state'=>'blocked','result'=>'hash_mismatch'];
    elseif(!$bin)$out=['state'=>'error','result'=>'scanner_unavailable'];
    else{
        // Treat encrypted, oversized and unscannable containers as alerts too.
        $out=hpco_exec([$bin,'--cvdcertsdir='.__DIR__.'/resources/clamav-certs','--no-summary','--infected','--alert-encrypted=yes','--alert-exceeds-max=yes','--max-filesize=12M','--max-scansize=40M','--max-files=500','--max-recursion=16','--',$p]);
        if($out['state']==='clean'&&(!is_file($p)||!hash_equals($r->sha,hash_file('sha256',$p))))$out=['state'=>'blocked','result'=>'hash_mismatch'];
    }
    $wpdb->update(hpco_table(),$out+['checked'=>time()],['token'=>$r->token]);
    return $out;
}
function hpco_tick(){
    global $wpdb;
    if(PHP_SAPI!=='cli')return; // Keep large signature databases out of web workers.
    update_option('hpco_last_tick',['at'=>gmdate('c'),'php'=>PHP_VERSION,'sapi'=>PHP_SAPI],false);
    if(hpc_private_base()==='')return;
    $lock=@fopen(hpc_private_dir('scan-worker.lock'),'c');
    if(!$lock)return;
    @chmod(hpc_private_dir('scan-worker.lock'),0600);
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return;}
    try{
        if(get_option('hpco_probe_requested')){
            $probe=null;
            try{
                $bin=hpco_scanner();
                if(!$bin)$result=['state'=>'error','result'=>'scanner_unavailable'];
                else{
                    $probe=hpc_private_temp();file_put_contents($probe,"HPC harmless scanner availability check\n");
                    $result=hpco_exec([$bin,'--cvdcertsdir='.__DIR__.'/resources/clamav-certs','--no-summary','--infected','--alert-encrypted=yes','--alert-exceeds-max=yes','--max-filesize=12M','--max-scansize=40M','--max-files=500','--max-recursion=16','--',$probe]);
                }
                update_option('hpco_probe',['at'=>gmdate('c')]+$result,false);delete_option('hpco_probe_requested');
            }finally{if($probe&&is_file($probe))unlink($probe);}
        }
        $rows=$wpdb->get_results($wpdb->prepare('SELECT * FROM '.hpco_table()." WHERE state='queued' OR (state='error' AND checked<%d) ORDER BY checked ASC LIMIT 2",time()-3600));
        foreach($rows as $r)hpco_scan_one($r);
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
add_action('hpc_ops_tick','hpco_tick');
function hpco_require_clean($f){
    $r=hpco_record($f);
    if(!$r||$r->ext!==$f['ext']||!hash_equals($r->sha,$f['sha256']))throw new RuntimeException('첨부파일 검사 정보가 일치하지 않습니다.');
    $p=hpco_path($r);
    if(!$p||!hash_equals($r->sha,hash_file('sha256',$p)))throw new RuntimeException('첨부파일 무결성을 확인할 수 없습니다.');
    if($r->state!=='clean')throw new RuntimeException('첨부파일 안전성 검사 완료 전에는 다운로드할 수 없습니다. 검사 대기 또는 검사 오류 상태입니다. 잠시 후 다시 확인하거나 운영자에게 문의해 주세요.');
}
function hpco_label($f){
    global $wpdb;$state=$wpdb->get_var($wpdb->prepare('SELECT state FROM '.hpco_table().' WHERE token=%s',$f['token']));
    return ['queued'=>'검사 대기','clean'=>'검사 통과','blocked'=>'다운로드 차단','error'=>'검사 오류 · 다운로드 차단'][$state]??'미검사 · 다운로드 시 검사 대기 등록';
}
add_action('admin_menu',function(){add_management_page('HPC 운영 점검','HPC 운영 점검','manage_options','hpc-operations','hpco_page');});
function hpco_page(){
    if(!current_user_can('manage_options'))wp_die('권한이 없습니다.','',403);
    global $wpdb;$message='';
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_admin_referer('hpc_operations');
        if(($_POST['op']??'')==='probe'){
            update_option('hpco_probe_requested',time(),false);
            $message='서버 정기 작업에 가상 파일 검사를 요청했습니다. 5분 후 새로고침해 결과를 확인하세요.';
        }
    }
    $tick=get_option('hpco_last_tick',[]);$probe=get_option('hpco_probe',[]);
    echo '<div class="wrap"><h1>HPC 운영 점검</h1><p>문서는 외부 서비스로 전송하지 않습니다. 검사되지 않았거나 검사 오류가 난 첨부는 다운로드가 차단됩니다. 검사 통과도 모든 문서 위협의 부재를 보증하지는 않습니다.</p>';
    if($message)echo '<div class="notice notice-info"><p>'.esc_html($message).'</p></div>';
    echo '<h2>정기 작업</h2><p>최근 실행(UTC): '.esc_html($tick['at']??'아직 없음').' / PHP '.esc_html($tick['php']??'').' / '.esc_html($tick['sapi']??'').'</p>';
    echo '<p>다음 검사 예약(UTC): '.esc_html(($next=wp_next_scheduled('hpc_ops_tick'))?gmdate('c',$next):'예약 없음').'</p>';
    echo '<h2>첨부 검사</h2><p>웹 환경에서 보이는 검사기: '.esc_html(hpco_scanner()?:'접근 제한 또는 검사기 없음 — 서버 정기 작업 결과를 확인하세요.').'</p><p>최근 기본 시험: '.esc_html(($probe['at']??'없음').' / '.($probe['state']??'미실행')).'</p>';
    echo '<form method="post">';wp_nonce_field('hpc_operations');echo '<input type="hidden" name="op" value="probe">';submit_button('가상 텍스트로 검사기 실행 확인','secondary');echo '</form><ul>';
    foreach($wpdb->get_results('SELECT state,COUNT(*) AS n FROM '.hpco_table().' GROUP BY state') as $r)echo '<li>'.esc_html($r->state).' : '.(int)$r->n.'</li>';
    echo '</ul><p>새 첨부는 자동으로 검사 대기에 들어갑니다. 기존 첨부는 최초 다운로드 시 검사 대기에 등록됩니다. 최대 5분 간격으로 두 건씩 검사하고, 검사 오류는 한 시간 뒤 재시도합니다. 차단된 파일은 자동 삭제하지 않습니다.</p></div>';
}
