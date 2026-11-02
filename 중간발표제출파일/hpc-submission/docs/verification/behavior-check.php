<?php
require '/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/user.php';
$results=[];
function check($name,$ok){global $results;$results[$name]=(bool)$ok;if(!$ok)throw new RuntimeException($name);}
$uid=wp_create_user('submission_check_'.time(),wp_generate_password(30),'check-'.time().'@example.test');
if(is_wp_error($uid))throw new RuntimeException($uid->get_error_message());
update_user_meta($uid,'hpcm_verified_email',hpcm_email_key(get_userdata($uid)->user_email));
$ids=hkb_ids();$created=[];
foreach(['public'=>'','private'=>'true','forum'=>''] as $kind=>$secret){
    $c=new KBContent();
    $c->insertContent(['board_id'=>$ids[$kind==='forum'?'forum':'qna'],'member_uid'=>$uid,'member_display'=>'Local test',
        'title'=>'검증자료 '.$kind,'content'=>'로컬검증 검색용 본문입니다.','secret'=>$secret,'status'=>'']);
    check('create_'.$kind,$c->uid>0);$created[$kind]=$c->uid;
}
$opt=new KBContentOption($created['public']);$opt->hkb_answer='관리자답변검증 고유 문구입니다.';
wp_set_current_user(0);
check('public_read_guest',hkb_read(hkb_row($created['public'])));
check('private_denied_guest',!hkb_read(hkb_row($created['private'])));
check('forum_denied_guest',!hkb_read(hkb_row($created['forum'])));
$doc=hkc_kboard_document(hkb_row($created['public']));
check('answer_in_search',str_contains($doc['text'],'관리자답변검증'));
check('private_not_in_search',!hkc_kboard_document(hkb_row($created['private'])));
check('forum_not_in_guest_search',!hkc_kboard_document(hkb_row($created['forum'])));
$wpdb->update($wpdb->prefix.'kboard_board_content',['secret'=>'true'],['uid'=>$created['public']]);
check('visibility_rechecked',!hkc_still_allowed($doc['key'],$doc['updated']));
$wpdb->update($wpdb->prefix.'kboard_board_content',['secret'=>''],['uid'=>$created['public']]);
wp_set_current_user($uid);
check('own_private_read',hkb_read(hkb_row($created['private'])));
check('unapproved_forum_denied',!hkb_read(hkb_row($created['forum'])));
$files=[];$paths=[];
if(!is_dir(hpc_private_dir('uploads')))mkdir(hpc_private_dir('uploads'),0700,true);
foreach(['jpg','jpeg'] as $ext){$token=bin2hex(random_bytes(20));$p=hpc_private_dir('uploads/'.$token.'.'.$ext);file_put_contents($p,'local synthetic erasure check');$paths[]=$p;$files[]=['token'=>$token,'ext'=>$ext];}
$wpdb->insert(hpc_table(),['owner'=>$uid,'state'=>'selected','data'=>'{}','files'=>wp_json_encode($files),'history'=>'[]','created'=>current_time('mysql'),'updated'=>current_time('mysql')]);
check('approved_forum_read',hkb_read(hkb_row($created['forum'])));
check('approved_forum_search',!!hkc_kboard_document(hkb_row($created['forum'])));
update_option(hpcw_key($uid),['uid'=>$uid,'requested'=>time(),'attempts'=>0,'error'=>'']);
wp_set_current_user(0);
hpcw_erase($uid);
check('local_account_erased',!get_userdata($uid));
check('jpg_jpeg_removed',!file_exists($paths[0])&&!file_exists($paths[1]));
foreach($created as $kind=>$id){$r=hkb_row($id);check('kboard_anonymized_'.$kind,(int)$r->member_uid===0&&!str_contains($r->content,'로컬검증'));}
// Remove only the fixtures created by this check in the isolated local database.
foreach($created as $id){$c=new KBContent();$c->initWithUID($id);$c->delete(false);}
echo json_encode($results,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
