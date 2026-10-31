<?php
/*
Plugin Name: HPC Forum Update
Description: 기존 HPC 사이트에 검증된 포럼 변경 파일만 적용하는 일회성 업데이트.
Version: 1.0.0
Requires PHP: 8.2
*/
if(!defined('ABSPATH'))exit;
function hpcfr_apply(){
    if(!current_user_can('manage_options') || !function_exists('hpc_private_base'))wp_die('기존 HPC 관리자 환경이 필요합니다.');
    $base=hpc_private_base();
    if(!$base || !is_dir($base) || !is_writable($base))wp_die('비공개 백업 경로를 확인해 주세요.');
    $manifest=json_decode(file_get_contents(__DIR__.'/manifest.json'),true);
    if(!is_array($manifest)||count($manifest)!==10)wp_die('업데이트 파일 목록 오류');
    $backup=$base.'/forum-update-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4));
    if(!mkdir($backup,0700))wp_die('원본 파일 백업 실패');
    $done=[];$original=[];
    try {
        foreach($manifest as $i=>$entry){
            $rel=$entry['path'];
            if(!preg_match('~^(plugins/hpc-feature-lab/(forum|portal|portal-installer|withdrawal|lab)\.php|themes/hpc-portal-pages/(functions\.php|footer\.php|pages\.json|naas-adapter\.css|style\.css))$~D',$rel))throw new RuntimeException('허용되지 않은 파일');
            $src=__DIR__.'/payload/'.$rel;$dst=WP_CONTENT_DIR.'/'.$rel;
            if(!hash_equals($entry['sha256'],hash_file('sha256',$src)))throw new RuntimeException('파일 검증 실패');
            if(!is_dir(dirname($dst))||!is_writable(dirname($dst)))throw new RuntimeException('파일 쓰기 권한 확인 필요');
            $original[$i]=is_file($dst);
            if($original[$i] && !copy($dst,$backup.'/'.$i.'.bak'))throw new RuntimeException('백업 실패');
            if($original[$i])chmod($backup.'/'.$i.'.bak',0600);
        }
        file_put_contents($backup.'/manifest.json',wp_json_encode(['files'=>$manifest,'existed'=>$original]));chmod($backup.'/manifest.json',0600);
        foreach($manifest as $i=>$entry){
            $dst=WP_CONTENT_DIR.'/'.$entry['path'];$done[]=$i;
            if(!copy(__DIR__.'/payload/'.$entry['path'],$dst))throw new RuntimeException('파일 적용 실패');
            chmod($dst,0644);if(function_exists('opcache_invalidate'))opcache_invalidate($dst,true);
        }
        update_option('hpc_forum_release_pending',['backup'=>$backup],false);
    }catch(Throwable $e){
        foreach(array_reverse($done) as $i){$dst=WP_CONTENT_DIR.'/'.$manifest[$i]['path'];if($original[$i])copy($backup.'/'.$i.'.bak',$dst);else if(is_file($dst))unlink($dst);if(function_exists('opcache_invalidate'))opcache_invalidate($dst,true);}
        wp_die(esc_html($e->getMessage()));
    }
}
register_activation_hook(__FILE__,'hpcfr_apply');
add_action('admin_init',function(){
    if(!current_user_can('manage_options') || !get_option('hpc_forum_release_pending') || !function_exists('hpcf_install'))return;
    try {
        $r=hpc_install_portal_pages();
        update_option('hpc_forum_release_result',['at'=>current_time('mysql'),'result'=>$r],false);
        delete_option('hpc_forum_release_pending');
        deactivate_plugins(plugin_basename(__FILE__));
    }catch(Throwable $e){add_action('admin_notices',function()use($e){echo '<div class="notice notice-error"><p>포럼 페이지 적용: '.esc_html($e->getMessage()).'</p></div>';});}
});
