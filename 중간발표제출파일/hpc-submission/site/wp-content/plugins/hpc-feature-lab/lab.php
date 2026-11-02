<?php
/*
Plugin Name: HPC Feature Lab
Description: 회원 인증, 이용신청, 문서 출력 및 심사 기능.
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
require_once __DIR__.'/operations.php';
require_once __DIR__.'/withdrawal.php';
require_once __DIR__.'/board-access.php';
require_once __DIR__.'/legacy-routes.php';
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
}
register_activation_hook(__FILE__,'hpc_install');
add_filter('pre_wp_mail',function($v){return defined('HPC_LAB_ROOT')?false:$v;});
function hpc_admin(){return current_user_can('manage_options');}
function hpc_url($args=[]){return apply_filters('hpc_portal_url',add_query_arg(array_merge(['hpc_lab'=>1],$args),home_url('/')),$args);}
function hpc_domains(){return ['농업기초','식량','원예','축산','식물','동물','미생물','보건','의료','기타'];}
function hpc_states(){return ['draft'=>hpct_text('t52296b752eb1223b91b5'),'amending'=>hpct_text('t8cbe91962728d175f8e0'),'submitted'=>hpct_text('t89c964d5e39c4da72690'),'received'=>hpct_text('t282ae8d4ec9c92e3b4c6'),'review'=>hpct_text('t956e824e48c3cceabe71'),'committee'=>hpct_text('tda0b1d2a00e920ca9863'),'selected'=>hpct_text('te72037deae93fe62f0d6'),'rejected'=>hpct_text('t2d326bd2009fd4ad9edd'),'needs_revision'=>hpct_text('t9037a2a179456e84f1bf')];}
function hpc_transitions(){return ['submitted'=>['received','needs_revision'],'received'=>['review','needs_revision'],'review'=>['committee','needs_revision','rejected'],'committee'=>['selected','rejected','needs_revision'],'needs_revision'=>[],'selected'=>[],'rejected'=>[],'draft'=>[]];}
function hpc_get($id,$lock=false){
    hpcm_require();
    global $wpdb;$a=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.hpc_table().' WHERE id=%d'.($lock?' FOR UPDATE':''),$id));
    if(!$a||(!hpc_admin()&&(int)$a->owner!==get_current_user_id()))throw new RuntimeException(hpct_text('t202554b62f8a9ebfa058'));
    return $a;
}
function hpc_event($type,$text){return ['at'=>current_time('mysql'),'by'=>get_current_user_id(),'type'=>$type,'text'=>$text];}
function hpc_input($key){return isset($_POST[$key])&&is_scalar($_POST[$key])?trim(wp_unslash((string)$_POST[$key])):'';}
function hpc_phone_parts($input, $strict=false){
    $parts=[];foreach([1,2,3] as $i){$v=$input['phone_'.$i]??'';if(!is_scalar($v))throw new RuntimeException(hpct_text('tac742591c6e24dac9602'));$parts[]=trim((string)$v);}
    if($strict && (!preg_match('/^[0-9]{2,4}$/D',$parts[0])||!preg_match('/^[0-9]{3,4}$/D',$parts[1])||!preg_match('/^[0-9]{4}$/D',$parts[2])))throw new RuntimeException(hpct_text('t08a6ffce207f3373a2d9'));
    return implode('',$parts)===''?'':implode('-',$parts);
}
function hpc_validate(){
    if(isset($_POST['phone_1']))$_POST['phone']=hpc_phone_parts(wp_unslash($_POST),true);
    $limits=['organization'=>100,'department'=>100,'position'=>50,'name'=>40,'email'=>120,'phone'=>30,'project'=>160,'project_no'=>60,'purpose'=>400,'method'=>1500,'programs'=>200,'account1'=>20,'account2'=>20,'approver'=>40];$d=[];
    foreach($limits as $k=>$max){$d[$k]=sanitize_textarea_field(hpc_input($k));if(($d[$k]===''&&!in_array($k,['project_no'],true))||mb_strlen($d[$k])>$max)throw new RuntimeException(hpct_text('tbae8193edf85f6b6ab6d').$k);}
    $d['account_type']=hpc_input('account_type');if(!in_array($d['account_type'],['운영자','전용','공동활용'],true))throw new RuntimeException(hpct_text('tb3c179509209b7000637'));
    if(!is_email($d['email']))throw new RuntimeException(hpct_text('ta60c19afae9625a6ac94'));
    if(!preg_match('/^[0-9+() -]{7,30}$/',$d['phone']))throw new RuntimeException(hpct_text('tac742591c6e24dac9602'));
    foreach(['account1','account2'] as $k)if(!preg_match('/^[a-z0-9_]{5,20}$/D',$d[$k]))throw new RuntimeException(hpct_text('te7e617a1b957f1a6274d'));
    if($d['account1']===$d['account2'])throw new RuntimeException(hpct_text('t588ca529a640186197d3'));
    foreach(['nodes'=>100,'disk'=>1000000] as $k=>$max){$s=hpc_input($k);if(!ctype_digit($s)||(int)$s<1||(int)$s>$max)throw new RuntimeException(hpct_text('t012019d04578e6fb5695'));$d[$k]=(int)$s;}
    $domains=isset($_POST['domains'])&&is_array($_POST['domains'])?array_map('sanitize_text_field',wp_unslash($_POST['domains'])):[];
    $d['domains']=array_values(array_intersect(hpc_domains(),$domains));if(!$d['domains'])throw new RuntimeException(hpct_text('t52142612c5cba41587d1'));
    $d['fee']=hpc_input('fee');if(!in_array($d['fee'],['paid','free'],true))throw new RuntimeException(hpct_text('t0b0f93c8ae3aa070d3b7'));
    if(hpc_input('privacy')!=='1'||hpc_input('security')!=='1')throw new RuntimeException(hpct_text('td8b60eab859d67f54433'));
    $d['privacy']=true;$d['security']=true;$d['consent_version']='2027-H1-vf';$d['consented_at']=current_time('mysql');
    $date=hpc_input('date');$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if(!$dt||$dt->format('Y-m-d')!==$date)throw new RuntimeException(hpct_text('t4d583d5790a32bedb825'));$d['date']=$date;
    return $d;
}
function hpc_upload($key,$required){
    if(!isset($_FILES[$key])||$_FILES[$key]['error']===UPLOAD_ERR_NO_FILE){if($required)throw new RuntimeException(hpct_text('taf8294acc718a34d8132'));return null;}
    $f=$_FILES[$key];if(is_array($f['name'])||$f['error']!==UPLOAD_ERR_OK||$f['size']<8||$f['size']>10*1024*1024)throw new RuntimeException(hpct_text('tb4708e0441fec09a96c3'));
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
    if($key==='extra'&&in_array($ext,['jpg','jpeg'],true)){$image=@getimagesize($f['tmp_name']);$ok=$image&&($image[2]??0)===IMAGETYPE_JPEG;}
    if(!$ok)throw new RuntimeException($key==='extra'?hpct_text('t245f32b0ead5f166c519'):hpct_text('t97575578a6cbb2efbaf2'));
    $dir=hpc_private_dir('uploads');if(!is_dir($dir))mkdir($dir,0700,true);
    $token=bin2hex(random_bytes(20));$path=$dir.'/'.$token.'.'.$ext;
    if(!move_uploaded_file($f['tmp_name'],$path))throw new RuntimeException(hpct_text('ta0c9253fb9022c340cf9'));chmod($path,0600);
    return ['token'=>$token,'ext'=>$ext,'name'=>sanitize_file_name($f['name']),'at'=>current_time('mysql'),'type'=>$key,'sha256'=>hash_file('sha256',$path)];
}
function hpc_post(){
    hpcm_require();
    global $wpdb;
    if(!wp_verify_nonce(hpc_input('_wpnonce'),'hpc_lab'))throw new RuntimeException(hpct_text('t1a672ccdb3f49d406000'));
    $op=hpc_input('op');
    if(!in_array($op,['save','reopen','submit','transition'],true))throw new RuntimeException(hpct_text('tdef94cb9e22dfa7aef36'));
    $id=absint(hpc_input('id'));$newfiles=[];$wpdb->query('START TRANSACTION');
    try{
        if($op==='save'){
            $a=$id?hpc_get($id,true):null;$template=$a?hpc_app_template($a):hpc_active_template();
            if(!$a&&absint(hpc_input('template_id'))!==(int)$template->id)throw new RuntimeException(hpct_text('t6b688240a80c14c2fb02'));
            $data=$template->engine==='legacy'?hpc_validate():hpc_schema_validate(json_decode($template->config,true),wp_unslash($_POST));
            if(hpc_input('usage_rules')!=='1')throw new RuntimeException(hpct_text('t64abcce793c07367ebb6'));
            $data['_usage_rules_version']='2026-10-22-v1';$data['_usage_rules_at']=current_time('mysql');
            $data['_template_id']=(int)$template->id;
            if($id){$a=hpc_get($id,true);if((int)$a->owner!==get_current_user_id()||!in_array($a->state,['draft','needs_revision','amending'],true))throw new RuntimeException(hpct_text('t5e3d7695736943594da8'));
                if((int)$a->version!==absint(hpc_input('version')))throw new RuntimeException(hpct_text('t65f26329af62ddf36aaa'));
                $history=json_decode($a->history,true);$history[]=array_merge(hpc_event('save',hpct_text('t8b659c6131297fb0daee')),['previous_data'=>json_decode($a->data,true),'previous_version'=>(int)$a->version]);
                $ok=$wpdb->update(hpc_table(),['data'=>wp_json_encode($data),'version'=>$a->version+1,'history'=>wp_json_encode($history),'updated'=>current_time('mysql')],['id'=>$id]);
            }else{$ok=$wpdb->insert(hpc_table(),['owner'=>get_current_user_id(),'state'=>'draft','data'=>wp_json_encode($data),'files'=>'[]','history'=>wp_json_encode([hpc_event('save',hpct_text('t542a5a90f0a6cd187178'))]),'created'=>current_time('mysql'),'updated'=>current_time('mysql')]);$id=$wpdb->insert_id;}
        }elseif($op==='reopen'){
            $a=hpc_get($id,true);
            if((int)$a->owner!==get_current_user_id()||!in_array($a->state,['submitted','received'],true))throw new RuntimeException(hpct_text('tdd467a725f4f2726ea19'));
            if((int)$a->version!==absint(hpc_input('version')))throw new RuntimeException(hpct_text('t65f26329af62ddf36aaa'));
            $history=json_decode($a->history,true);$history[]=hpc_event('amending',hpct_text('t9e1b05480a48c85fadc8').(hpc_states()[$a->state]??$a->state).hpct_text('t276551465b339ea1876e'));
            $ok=$wpdb->update(hpc_table(),['state'=>'amending','history'=>wp_json_encode($history),'version'=>$a->version+1,'updated'=>current_time('mysql')],['id'=>$id]);
        }elseif($op==='submit'||$op==='transition'){
            $a=hpc_get($id,true);$history=json_decode($a->history,true);
            if((int)$a->version!==absint(hpc_input('version')))throw new RuntimeException(hpct_text('t65f26329af62ddf36aaa'));
            if($op==='submit'){
                if((int)$a->owner!==get_current_user_id()||!in_array($a->state,['draft','needs_revision','amending'],true))throw new RuntimeException(hpct_text('t3be34b290f84a593d631'));
                if(hpc_input('attest')!=='1')throw new RuntimeException(hpct_text('tddce055a94701e47d28d'));
                $newfiles[]=hpc_upload('signed',true);$official=hpc_upload('official',false);if($official)$newfiles[]=$official;
                $extra=hpc_upload('extra',false);if($extra)$newfiles[]=$extra;
                $reference=sanitize_text_field(hpc_input('official_ref'));
                if(!$official&&$reference==='')throw new RuntimeException(hpct_text('t3693d9aac811599dff0a'));
                if(mb_strlen($reference)>300)throw new RuntimeException(hpct_text('t76f3959bd757484bb496'));
                foreach($newfiles as $f)hpco_record($f);
                $allfiles=array_merge(json_decode($a->files,true),$newfiles);$history[]=hpc_event('submitted',hpct_text('t3b7b5817907901d8abc9').($reference?hpct_text('t8aa17921f038480df9e2').$reference:''));
                $receipt=$a->receipt?:'HPC-TEST-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
                $ok=$wpdb->update(hpc_table(),['state'=>'submitted','receipt'=>$receipt,'files'=>wp_json_encode($allfiles),'history'=>wp_json_encode($history),'version'=>$a->version+1,'updated'=>current_time('mysql')],['id'=>$id]);
            }else{
                if(!hpc_admin())throw new RuntimeException(hpct_text('t8917ce06c2075599dcb5'));
                $next=hpc_input('next');$note=sanitize_textarea_field(hpc_input('note'));
                if(!in_array($next,hpc_transitions()[$a->state]??[],true))throw new RuntimeException(hpct_text('t2f9cc9fe8115236fc917'));
                if($note===''||mb_strlen($note)>1000)throw new RuntimeException(hpct_text('tb4f12b9575780f64b428'));
                $history[]=hpc_event($next,$note);$ok=$wpdb->update(hpc_table(),['state'=>$next,'history'=>wp_json_encode($history),'version'=>$a->version+1,'updated'=>current_time('mysql')],['id'=>$id]);
            }
        }else throw new RuntimeException(hpct_text('tdef94cb9e22dfa7aef36'));
        if($ok===false)throw new RuntimeException(hpct_text('tae94ec51b2c23b4fdef9'));$wpdb->query('COMMIT');
    }catch(Throwable $e){$wpdb->query('ROLLBACK');foreach($newfiles as $f)if($f)@unlink(hpc_private_dir('uploads/'.$f['token'].'.'.$f['ext']));throw $e;}
    $view=$op==='reopen'?'apply':'detail';
    wp_safe_redirect(hpc_url(['view'=>$view,'id'=>$id,'saved'=>1]));exit;
}
function hpc_download(){
    hpcm_require();
    if(!wp_verify_nonce($_GET['_wpnonce']??'','hpc_download'))wp_die(hpct_text('t11ae1210280311eebcc8'),'',403);
    $a=hpc_get(absint($_GET['id']??0));$format=sanitize_key($_GET['format']??'');
    if($format){$path=hpc_document($a,$format);$name='application-'.$a->id.'-v'.$a->version.'.'.$format;}
    else{
        $path=null;$token=sanitize_key($_GET['file']??'');
        foreach(json_decode($a->files,true) as $f)if(hash_equals($f['token'],$token)){hpco_require_clean($f);$path=hpc_private_dir('uploads/'.$f['token'].'.'.$f['ext']);$name=$f['name'];break;}
        if(!$path)throw new RuntimeException(hpct_text('tafdc66fded0b38f28428'));
    }
    if(!is_file($path))throw new RuntimeException(hpct_text('t55c7961182264fae7045'));
    nocache_headers();header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');
    header("Content-Disposition: attachment; filename*=UTF-8''".rawurlencode($name));header('Content-Length: '.filesize($path));readfile($path);exit;
}
function hpc_nonce($op,$a=null){wp_nonce_field('hpc_lab');echo '<input type="hidden" name="op" value="'.esc_attr($op).'">';if($a)echo '<input type="hidden" name="id" value="'.(int)$a->id.'"><input type="hidden" name="version" value="'.(int)$a->version.'">';}
function hpc_link($label,$args){return '<a href="'.esc_url(hpc_url($args)).'">'.esc_html($label).'</a>';}
function hpc_download_link($a,$format){return hpc_link(strtoupper($format).hpct_text('t25b6984fee7d2c98dc1a'),['view'=>'download','id'=>$a->id,'format'=>$format,'_wpnonce'=>wp_create_nonce('hpc_download')]);}
function hpc_form($a=null){
    $template=$a?hpc_app_template($a):hpc_active_template();
    if($template->engine!=='legacy'){ob_start();hpc_schema_form($template,$a);$form=ob_get_clean();echo str_replace((''.'<button>'.esc_html(hpct_text('t8b659c6131297fb0daee')).'</button>'.''),hpc_usage_rules_ui().(''.'<button>'.esc_html(hpct_text('t8b659c6131297fb0daee')).'</button>'.''),$form);return;}
    $d=$a?json_decode($a->data,true):[];
    if($_SERVER['REQUEST_METHOD']==='POST'&&hpc_input('op')==='save'){
        foreach($_POST as $k=>$v)if(is_scalar($v))$d[$k]=sanitize_textarea_field(wp_unslash($v));
        $d['domains']=isset($_POST['domains'])&&is_array($_POST['domains'])?array_values(array_intersect(hpc_domains(),wp_unslash($_POST['domains']))):[];
        $d['privacy']=hpc_input('privacy')==='1';$d['security']=hpc_input('security')==='1';
    }
    echo (''.'<h2>'.esc_html(hpct_text('t4e6a3c83c8330450932b')).'</h2>'.''.'<p>'.esc_html(hpct_text('t6d7b941f1f15faf35ffe')).'</p>'.''.'<form method="post">'.'');hpc_nonce('save',$a);
    echo '<input type="hidden" name="template_id" value="'.(int)$template->id.'">';
    $fields=[
        'organization'=>[hpct_text('ta84fa7935b5b6c90bb70'),100],'department'=>[hpct_text('t57bcfb6e119961a2aa23'),100],'position'=>[hpct_text('t5c05f87e7706f0856162'),50],'name'=>[hpct_text('t2a93785b96acbee08279'),40],'email'=>[hpct_text('t3b7dbc4c0c4cebcaa2ed'),120,'email'],'phone'=>[hpct_text('t4edc94394feec0a6ca6b'),30],
        'project'=>[hpct_text('tdd1954eb2a453614f1f7'),160],'project_no'=>[hpct_text('t2257fd9b33f609eecd19'),60],
        'purpose'=>[hpct_text('t70ea5deceb39b0d7b54d'),400,'textarea'],'method'=>[hpct_text('t00cdcd621625e722da5c'),1500,'textarea'],
        'nodes'=>[hpct_text('t3a5c8cbeaddf25b7f150'),100,'number'],'disk'=>[hpct_text('t9f97b44dff2eda30e56d'),1000000,'number'],'programs'=>[hpct_text('t77c7f1ae6af5405d7e79'),200],
        'account1'=>[hpct_text('t9c2e9371265ffd35d5ae'),20],'account2'=>[hpct_text('t9816c9050da591941a75'),20]
    ];
    echo (''.'<p>'.''.'<strong>'.esc_html(hpct_text('t9fc8947a85cb24228b66')).'</strong>'.''.'<br>'.esc_html(hpct_text('t231714362c9de32b15b6')).'</p>'.'');
    echo (''.'<fieldset>'.''.'<legend>'.esc_html(hpct_text('t2c2060d9574dcd85d16d')).'</legend>'.'');foreach(['운영자'=>hpct_text('t47c50d619a3a251c8ad7'),'전용'=>hpct_text('te7daca26c87b8a4ddfe4'),'공동활용'=>hpct_text('t7a0995e4423e4506a4bd')] as $type=>$help)echo '<label class="inline"><input type="radio" name="account_type" value="'.esc_attr($type).'" required '.checked($d['account_type']??($a?'공동활용':''),$type,false).'>'.esc_html(hpct_label($type).' — '.$help).'</label>';echo '</fieldset>';
    foreach($fields as $k=>$f){
        if($k==='project'){echo (''.'<fieldset>'.''.'<legend>'.esc_html(hpct_text('t3d1100b56f747d630b04')).'</legend>'.'');foreach(hpc_domains() as $v)echo '<label class="inline"><input type="checkbox" name="domains[]" value="'.esc_attr($v).'" '.checked(in_array($v,$d['domains']??[],true),true,false).'>'.esc_html(hpct_label($v)).'</label>';echo '</fieldset>';}
        $sections=['organization'=>hpct_text('t302be97f00b35f93082e'),'position'=>hpct_text('tb2f7700ad1106d4a9baa'),'project'=>hpct_text('t746d6f023f2340080d61'),'nodes'=>hpct_text('tc16197deb83e5d76f282'),'programs'=>hpct_text('tefe5938d1751e27bc843'),'account1'=>hpct_text('t6c9d6248a9d40ba12c52')];
        if(isset($sections[$k]))echo '<h3>'.esc_html($sections[$k]).'</h3>';
        if($k==='nodes')echo (''.'<p>'.esc_html(hpct_text('t9fc8947a85cb24228b66')).'</p>'.'');
$type=$f[2]??'text';$value=$d[$k]??($k==='date'?current_time('Y-m-d'):'');echo '<label for="'.$k.'">'.esc_html($f[0]).'</label>';
        if($k==='phone'){
            $parts=explode('-',(string)$value);if(count($parts)!==3&&preg_match('/^(02|0[0-9]{2,3})([0-9]{3,4})([0-9]{4})$/',preg_replace('/[^0-9]/','',(string)$value),$m))$parts=array_slice($m,1);
            echo (''.('<div class="hpc-phone-parts" role="group" aria-label="'.esc_attr(hpct_text('t4edc94394feec0a6ca6b')).'" style="display:flex;align-items:center;gap:8px;max-width:440px">').'');
            foreach([1,2,3] as $n){if($n>1)echo '<span aria-hidden="true">-</span>';$part=$d['phone_'.$n]??($parts[$n-1]??'');echo '<input style="min-width:0;flex:1" id="'.($n===1?'phone':'phone_'.$n).'" name="phone_'.$n.'" type="tel" inputmode="numeric" maxlength="4" pattern="[0-9]{'.($n===1?'2,4':($n===2?'3,4':'4')).'}" aria-label="전화번호 '.['',hpct_text('tf107ef18851de1c9c119'),hpct_text('t89c9ab91ab96a5d98e54'),hpct_text('tfb89e74d52334b038ec8')][$n].'" value="'.esc_attr($part).'" required>';}
            echo '</div>';
        }
        elseif($type==='textarea')echo '<textarea id="'.$k.'" name="'.$k.'" maxlength="'.$f[1].'" rows="4" required>'.esc_textarea($value).'</textarea>';
        else echo '<input id="'.$k.'" name="'.$k.'" type="'.$type.'" '.($type==='number'?'min="1" max="'.$f[1].'"':'maxlength="'.$f[1].'"').' value="'.esc_attr($value).'" '.($k==='project_no'?'':'required').'>';
    }
    echo (''.'<p>'.esc_html(hpct_text('tfb13e426a99ba068a80d')).'</p>'.'');
    echo (''.'<details>'.''.'<summary>'.esc_html(hpct_text('tce832265f7525fb026eb')).'</summary>'.''.'<p>'.esc_html(hpct_text('tfdeef333a3062728f136')).'</p>'.''.'</details>'.'');
    echo '<label class="inline"><input name="privacy" type="checkbox" value="1" required '.checked($d['privacy']??false,true,false).(''.'>'.esc_html(hpct_text('t6a2a158a1fba2ce34f0a')).'</label>'.''.'<label class="inline">'.''.'<input name="security" type="checkbox" value="1" required '.'').checked($d['security']??false,true,false).(''.'>'.esc_html(hpct_text('tdfc0c00412be95b6274d')).'</label>'.'');
    echo (''.'<h3>'.esc_html(hpct_text('t7a27e494d9af23fbdd4d')).'</h3>'.''.'<label for="date">'.esc_html(hpct_text('t26c8f2fa225e672cd5da')).'</label>'.''.'<input id="date" name="date" type="date" value="'.'').esc_attr($d['date']??current_time('Y-m-d')).(''.'" required>'.''.'<label for="approver">'.esc_html(hpct_text('t491b7bb17adb7a6b12fb')).'</label>'.''.'<input id="approver" name="approver" maxlength="40" value="'.'').esc_attr($d['approver']??'').'" required>';
    echo (''.'<label for="fee">'.esc_html(hpct_text('t1e433ffe7877b7f75026')).'</label>'.''.'<select id="fee" name="fee" required>'.''.'<option value="">'.esc_html(hpct_text('t8d1a750c9351d1b803fc')).'</option>'.''.'<option value="paid" '.'').selected($d['fee']??'','paid',false).(''.'>'.esc_html(hpct_text('t552ed35d5e3b059da789')).'</option>'.''.'<option value="free" '.'').selected($d['fee']??'','free',false).(''.'>'.esc_html(hpct_text('t5d2cca472caed36d134c')).'</option>'.''.'</select>'.'');
    echo hpc_usage_rules_ui().(''.'<button>'.esc_html(hpct_text('t8b659c6131297fb0daee')).'</button>'.''.'</form>'.'');
}
function hpc_detail($a){
    $d=json_decode($a->data,true);$template=hpc_app_template($a);echo '<h2>'.esc_html(hpc_summary($a,'title')).(''.'</h2>'.''.'<p>'.''.'<strong>'.esc_html(hpct_text('t24aec271b9e2c194d08c'))).esc_html(hpc_states()[$a->state]).'</strong> / '.esc_html($a->receipt?:hpct_text('t8ad65ef22bc8fbd09190')).hpct_text('tad1011c4a51c1afff927').(int)$a->version.(''.'</p>'.''.'<p>'.esc_html(hpct_text('t37993e0bb406d3106764'))).esc_html($template->name).hpct_text('t211639425338c187c6cb').(int)$template->id.')</p>';
    echo '<div class="actions">'.hpc_download_link($a,'hwpx').' '.hpc_download_link($a,'hwp').' '.hpc_download_link($a,'pdf').(''.'</div>'.''.'<p>'.esc_html(hpct_text('t1b3feeea949abba14338')).'</p>'.'');
    $labels=['account_type'=>hpct_text('t00cc3812550592399248'),'organization'=>hpct_text('ta84fa7935b5b6c90bb70'),'department'=>hpct_text('t57bcfb6e119961a2aa23'),'position'=>hpct_text('t5c05f87e7706f0856162'),'name'=>hpct_text('t5aa32deffdf1362feb45'),'email'=>hpct_text('t3b7dbc4c0c4cebcaa2ed'),'phone'=>hpct_text('t4edc94394feec0a6ca6b'),'project'=>hpct_text('tdd1954eb2a453614f1f7'),'project_no'=>hpct_text('t15c02897a5714b8a9ea7'),'purpose'=>hpct_text('ta0fbc067cebc8ba7fa81'),'method'=>hpct_text('t00cdcd621625e722da5c'),'nodes'=>hpct_text('t59c0b0969500e51dbed5'),'disk'=>hpct_text('t0fe281a7fb7c7c765bd6'),'programs'=>hpct_text('t77c7f1ae6af5405d7e79'),'account1'=>hpct_text('t9c2e9371265ffd35d5ae'),'account2'=>hpct_text('t9816c9050da591941a75'),'approver'=>hpct_text('t9c0c0a2bdb749ceff0ca'),'date'=>hpct_text('t26c8f2fa225e672cd5da'),'domains'=>hpct_text('t1a3d1e3e27421b55a1ed'),'fee'=>hpct_text('t1e433ffe7877b7f75026'),'privacy'=>hpct_text('te58b9176ea29bd0a2e42'),'security'=>hpct_text('t7728b31bb32be7c68140'),'consent_version'=>hpct_text('t434230da93c946dbf8a7'),'consented_at'=>hpct_text('t9b95db9064fd48a73ac7')];
    if($template->engine!=='legacy')foreach(json_decode($template->config,true)['fields'] as $f)$labels[$f['id']]=$f['label'];
    echo (''.'<details>'.''.'<summary>'.esc_html(hpct_text('t4d327df387def21505ca')).'</summary>'.''.'<dl>'.'');foreach($d as $k=>$v){if(str_starts_with($k,'_'))continue;if(is_bool($v))$v=$v?hpct_text('t051a33c2b3a71197783e'):hpct_text('t1c15cddb3f52a6e5e3ce');if($k==='fee'&&$template->engine==='legacy')$v=$v==='paid'?hpct_text('t552ed35d5e3b059da789'):hpct_text('t5d2cca472caed36d134c');echo '<dt>'.esc_html($labels[$k]??$k).'</dt><dd>'.esc_html(is_array($v)?implode(', ',$v):(string)$v).'</dd>';}echo '</dl></details>';
    if((int)$a->owner===get_current_user_id()&&in_array($a->state,['draft','needs_revision','amending'],true)){
        echo '<p>'.hpc_link(hpct_text('tbdbf893a9f68b47896da'),['view'=>'apply','id'=>$a->id]).(''.'</p>'.''.'<h3>'.esc_html(hpct_text('t6fd19ee67fd83c1e4a66')).'</h3>'.''.'<form method="post" enctype="multipart/form-data">'.'');hpc_nonce('submit',$a);
        echo (''.'<label for="signed">'.esc_html(hpct_text('t7fb611083753f2afcb71')).'</label>'.''.'<input id="signed" type="file" name="signed" accept=".hwp,.hwpx,.pdf" required>'.''.'<label for="official">'.esc_html(hpct_text('tb007d308306038e6f437')).'</label>'.''.'<input id="official" type="file" name="official" accept=".hwp,.hwpx,.pdf">'.''.'<label for="extra">'.esc_html(hpct_text('t91d3c1fffd5e5092c7ab')).'</label>'.''.'<input id="extra" type="file" name="extra" accept=".pdf,.hwp,.hwpx,.jpg,.jpeg" aria-describedby="extra-help">'.''.'<p id="extra-help">'.esc_html(hpct_text('t0d529a27992a91d5d646')).'</p>'.''.'<label for="official_ref">'.esc_html(hpct_text('t588671172f64b4c9ae77')).'</label>'.''.(''.('<input id="official_ref" name="official_ref" maxlength="300" placeholder="'.esc_attr(hpct_text('t65cb9063aed175042a2d')).'">').'').''.'<label class="inline">'.''.'<input type="checkbox" name="attest" value="1" required>'.esc_html(hpct_text('tdc1953d0798136dff542')).'</label>'.''.'<button>'.esc_html(hpct_text('t052b10c850a7268e8d56')).'</button>'.''.'</form>'.'');
    }
    if((int)$a->owner===get_current_user_id()&&in_array($a->state,['submitted','received'],true)){
        echo (''.'<h3 id="edit-application">'.esc_html(hpct_text('t97a3a9567c727e960b7d')).'</h3>'.''.'<p>'.esc_html(hpct_text('t4578581a12cf092ecc29')).'</p>'.''.'<form method="post">'.'');hpc_nonce('reopen',$a);echo (''.'<button>'.esc_html(hpct_text('tf5f5165a7b11cc103caa')).'</button>'.''.'</form>'.'');
    }elseif($a->state==='amending')echo (''.'<p class="notice-box">'.esc_html(hpct_text('ta616efaaa68329cb2ed0')).'</p>'.'');
    elseif((int)$a->owner===get_current_user_id()&&!in_array($a->state,['draft','needs_revision'],true))echo (''.'<p class="notice-box">'.esc_html(hpct_text('t87439649e278878591fe')).'</p>'.'');
    echo (''.'<h3>'.esc_html(hpct_text('te48d9d1b3cdeb947b9f7')).'</h3>'.''.'<ul>'.'');foreach(json_decode($a->files,true) as $f)echo '<li>'.esc_html($f['at'].' / '.(['signed'=>hpct_text('td756749ae151055bb3f2'),'official'=>hpct_text('t09b0af75c2f621090745'),'extra'=>hpct_text('t109b1e1344016faf6513')][$f['type']]??hpct_text('t0e89a5d4dfa949efca3d')).' / '.hpco_label($f)).' '.hpc_link($f['name'],['view'=>'download','id'=>$a->id,'file'=>$f['token'],'_wpnonce'=>wp_create_nonce('hpc_download')]).'</li>';echo '</ul>';
    if(hpc_admin()&&($next=hpc_transitions()[$a->state]??[])){
        echo (''.'<h3>'.esc_html(hpct_text('t46eb27d02e7abe1f5e20')).'</h3>'.''.'<form method="post">'.'');hpc_nonce('transition',$a);echo (''.'<label for="next">'.esc_html(hpct_text('t7ff6390c3baa42126f0e')).'</label>'.''.'<select name="next" id="next">'.'');foreach($next as $s)echo '<option value="'.$s.'">'.esc_html(hpc_states()[$s]).'</option>';echo (''.'</select>'.''.'<label for="note">'.esc_html(hpct_text('tf870e8db40b8f1020665')).'</label>'.''.'<textarea name="note" id="note" maxlength="1000" required>'.''.'</textarea>'.''.'<button>'.esc_html(hpct_text('t22916f6e066b6b04adc1')).'</button>'.''.'</form>'.'');
    }
    echo (''.'<h3>'.esc_html(hpct_text('t4458424e5c32e78e846d')).'</h3>'.''.'<ol>'.'');foreach(json_decode($a->history,true) as $h){$actor=get_userdata($h['by']);echo '<li>'.esc_html($h['at'].' / '.(hpc_states()[$h['type']]??hpct_text('tea11c1e2bee2a4ae18f9')).' / '.($actor?$actor->display_name:hpct_text('t2908d2df40e64c7ed60d')).' / '.$h['text']).'</li>';}echo '</ol>';
}
function hpc_listing(){
    global $wpdb;$rows=hpc_admin()?$wpdb->get_results('SELECT * FROM '.hpc_table().' ORDER BY id DESC LIMIT 100'):$wpdb->get_results($wpdb->prepare('SELECT * FROM '.hpc_table().' WHERE owner=%d ORDER BY id DESC LIMIT 100',get_current_user_id()));
    echo '<h2>'.(hpc_admin()?hpct_text('tfb82169994b8e6f46abe'):hpct_text('t317d397e8a96fca3bac1')).(''.'</h2>'.''.'<table>'.''.'<thead>'.''.'<tr>'.''.'<th>'.esc_html(hpct_text('tb736160e9f7f0d321a2b')).'</th>'.''.'<th>'.esc_html(hpct_text('tdd1954eb2a453614f1f7')).'</th>'.''.'<th>'.esc_html(hpct_text('tef1661bfe1d0b773aea1')).'</th>'.''.'<th>'.esc_html(hpct_text('te10195a1239fb321327b')).'</th>'.''.'</tr>'.''.'</thead>'.''.'<tbody>'.'');
    foreach($rows as $a){echo '<tr><td>'.esc_html($a->receipt?:hpct_text('t6ac6b84288d94618443b').$a->id).'</td><td>'.hpc_link(hpc_summary($a,'title'),['view'=>'detail','id'=>$a->id]).'</td><td>'.esc_html(hpc_summary($a,'name')).'</td><td>'.esc_html(hpc_states()[$a->state]).'</td></tr>';}
    if(!$rows)echo (''.'<tr>'.''.'<td colspan="4">'.esc_html(hpct_text('t80ef1d1d4f2a51621f17')).'</td>'.''.'</tr>'.'');echo '</tbody></table>';
}
add_action('template_redirect',function(){
    if(!isset($_GET['hpc_lab']))return;
    if(hpc_private_base()==='')wp_die(hpct_text('t95620154cc806fbcb2ee'));
    nocache_headers();header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; img-src 'self' blob: data:; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'self'");
    if(!is_user_logged_in()){auth_redirect();exit;}
    $view=sanitize_key($_GET['view']??'status');$error='';
    try{if($_SERVER['REQUEST_METHOD']==='POST')hpc_post();if($view==='download')hpc_download();}catch(Throwable $e){$error=$e->getMessage();status_header(400);}
    ?><!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?php echo esc_html(hpct_text('t2a7772e3622008dd6566')); ?></title><style>
    *{box-sizing:border-box}body{font:16px/1.6 system-ui,sans-serif;color:#172b3a;background:#f4f6f8;margin:0}header,main{max-width:1020px;margin:auto;padding:24px}header{background:white}nav,.actions{display:flex;gap:18px;flex-wrap:wrap}a{color:#0759a0}main{background:white;margin:20px auto}label{display:block;margin-top:14px;font-weight:600}input:not([type=checkbox]),select,textarea{width:100%;padding:10px;border:1px solid #8d9dab;border-radius:4px;font:inherit}input[type=checkbox]{width:18px;height:18px}.inline{display:inline-flex;gap:8px;align-items:center;margin-right:16px}button{display:block;background:#0759a0;color:white;border:0;padding:12px 24px;margin:20px 0;cursor:pointer;font:inherit;border-radius:4px}fieldset,article,details{border:1px solid #bcc8d3;padding:16px;margin:20px 0}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #ccd5de}th{background:#edf2f7}.banner{background:#fff4d1;padding:12px}.error{border:2px solid #a62424;padding:14px;color:#a62424}.success{background:#e6f4e9;padding:12px}dt{font-weight:bold}dd{white-space:pre-wrap;margin-bottom:8px}a:focus-visible,button:focus-visible,input:focus-visible,textarea:focus-visible{outline:3px solid #ec9900}form{max-width:800px}@media(max-width:640px){header,main{padding:16px}table{font-size:13px}th,td{padding:8px}.actions{gap:10px}}
    </style></head><body><header><h1><?php echo esc_html(hpct_text('t2a7772e3622008dd6566')); ?></h1><p class="banner"><?php echo esc_html(hpct_text('tcd18b240e85505e67ad7')); ?></p><p><?php echo esc_html(wp_get_current_user()->user_login).' / '.(hpc_admin()?hpct_text('t78496a61deb74f2d0ad4'):hpct_text('tf0c33d60b9097c6a06cc'));?> · <a href="<?php echo esc_url(wp_logout_url(hpc_url()));?>"><?php echo esc_html(hpct_text('t919c1b32322583720f00')); ?></a></p><nav><?php echo hpc_link(hpct_text('t4e6a3c83c8330450932b'),['view'=>'apply']).hpc_link(hpc_admin()?hpct_text('tfb82169994b8e6f46abe'):hpct_text('t317d397e8a96fca3bac1'),['view'=>'status']).hpc_link(hpct_text('t4732a58f5f977c4b3be7'),['view'=>'board']).hpc_link('Q&A',['view'=>'qna']);if(hpc_admin())echo '<a href="'.esc_url(hpc_template_admin_url()).(''.'">'.esc_html(hpct_text('t4ef7a783f4ef51e722d3')).'</a>'.'');?></nav></header><main>
    <?php if($error)echo '<p class="error" role="alert">'.esc_html($error).'</p>';elseif(isset($_GET['saved']))echo (''.'<p class="success" role="status">'.esc_html(hpct_text('tba971bfea1b3477dd1cd')).'</p>'.'');
    try{
        if($view==='apply'){$a=!empty($_GET['id'])?hpc_get(absint($_GET['id'])):null;if($a&&((int)$a->owner!==get_current_user_id()||!in_array($a->state,['draft','needs_revision','amending'],true)))throw new RuntimeException(hpct_text('t13a4b4fd0884dfcb1e4c'));hpc_form($a);hpc_live_preview_ui($a);}
        elseif($view==='detail')hpc_detail(hpc_get(absint($_GET['id']??0)));
        elseif($view!=='download')hpc_listing();
    }catch(Throwable $e){echo '<p class="error" role="alert">'.esc_html($e->getMessage()).'</p>';}
    ?></main></body></html><?php exit;
});
