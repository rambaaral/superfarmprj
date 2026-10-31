<?php
if(!defined('ABSPATH'))exit;
function hpc_marker_targets($path){
    $z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('양식 열기 실패');$targets=[];
    try{for($i=0;$i<$z->numFiles;$i++){$name=$z->getNameIndex($i);if(!preg_match('/^Contents\/section\d+\.xml$/D',$name))continue;$d=hpc_xml($z->getFromName($name));$x=new DOMXPath($d);$x->registerNamespace('hp','http://www.hancom.co.kr/hwpml/2011/paragraph');foreach($x->query('//hp:p') as $p){$text='';foreach($x->query('./hp:run/hp:t',$p) as $t)$text.=$t->textContent;preg_match_all('/\[\[HPC:([a-z][a-z0-9_]{0,39})\]\]/',$text,$m);foreach($m[1] as $id)$targets['m_'.$id]=['kind'=>'marker','label'=>'문서 입력 표시: '.$id,'text'=>$id];}}
    }finally{$z->close();}return $targets;
}
function hpc_field_output($f,$data){
    $v=$f['type']==='fixed'?$f['example']:($data[$f['id']]??'');
    if($f['type']==='checkbox')$v=$v?'■ 동의    □ 미동의':'□ 동의    □ 미동의';
    elseif(in_array($f['type'],['select','multicheck'],true)){$selected=is_array($v)?$v:[$v];$parts=[];foreach($f['options'] as $o)$parts[]=(in_array($o,$selected,true)?'■ ':'□ ').$o;$v=implode('  ',$parts);}
    return $v===''?'':$f['prefix'].(is_array($v)?implode(', ',$v):(string)$v).$f['suffix'];
}
function hpc_fill_tokens($data,$out,$template){
    $path=hpc_template_file($template);$cfg=json_decode($template->config,true);$values=[];foreach($cfg['fields'] as $f){if($f['target']!=='')$values['[[HPC:'.substr($f['target'],2).']]']=hpc_field_output($f,$data);}
    if(!copy($path,$out))throw new RuntimeException('양식 복사 실패');$z=new ZipArchive();if($z->open($out)!==true)throw new RuntimeException('양식 열기 실패');$preview=[];
    try{for($i=0;$i<$z->numFiles;$i++){$entry=$z->getNameIndex($i);if(!preg_match('/^Contents\/section\d+\.xml$/D',$entry))continue;$d=hpc_xml($z->getFromName($entry));$x=new DOMXPath($d);$ns='http://www.hancom.co.kr/hwpml/2011/paragraph';$x->registerNamespace('hp',$ns);
        foreach($x->query('//hp:p') as $p){$nodes=iterator_to_array($x->query('./hp:run/hp:t',$p));$text=implode('',array_map(fn($n)=>$n->textContent,$nodes));preg_match_all('/\[\[HPC:[a-z][a-z0-9_]{0,39}\]\]/',$text,$matches,PREG_OFFSET_CAPTURE);if(!$matches[0])continue;
            // Replace back-to-front so markers split across character runs retain surrounding styles.
            foreach(array_reverse($matches[0]) as [$marker,$byte]){$start=mb_strlen(substr($text,0,$byte));$end=$start+mb_strlen($marker);$pos=0;$inserted=false;foreach($nodes as $node){$s=$node->textContent;$len=mb_strlen($s);$next=$pos+$len;if($next>$start&&$pos<$end){$left=mb_substr($s,0,max(0,$start-$pos));$right=mb_substr($s,max(0,$end-$pos));$replacement=$left.(!$inserted?($values[$marker]??''):'').$right;$node->nodeValue=$replacement;$inserted=true;}$pos=$next;}}
            foreach($nodes as $node){$s=$node->textContent;if(!str_contains($s,"\n"))continue;while($node->firstChild)$node->removeChild($node->firstChild);foreach(explode("\n",str_replace("\r",'',$s)) as $j=>$line){if($j)$node->appendChild($d->createElementNS($ns,'hp:lineBreak'));$node->appendChild($d->createTextNode($line));}}
            foreach(iterator_to_array($x->query('./hp:linesegarray',$p)) as $line)$p->removeChild($line);
        }
        foreach($x->query('//hp:t') as $t)$preview[]=$t->textContent;$z->addFromString($entry,$d->saveXML());
    }$z->deleteName('Preview/PrvImage.png');$z->addFromString('Preview/PrvText.txt',implode("\n",$preview));}finally{$z->close();}
}
function hpc_private_temp(){ $dir=hpc_private_dir('preview');if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('임시 저장 공간 오류');$path=tempnam($dir,'preview-');if(!$path)throw new RuntimeException('임시 파일 생성 실패');return $path; }
function hpc_editor_url($id=0){return add_query_arg(['page'=>'hpc-document-editor','template'=>$id],admin_url('admin.php'));}
add_action('admin_menu',function(){add_submenu_page('hpc-templates','문서로 양식 만들기','문서 편집기','manage_options','hpc-document-editor','hpc_editor_page');});
function hpc_editor_page(){
    if(!current_user_can('manage_options'))return;$id=absint($_GET['template']??0);$cfg=['fields'=>[],'title_field'=>'','name_field'=>'','intro'=>'','font_size'=>10];$revision=0;$name='새 신청 양식';
    try{if($id){$t=hpc_template_get($id);hpc_template_file($t);$cfg=$t->engine==='legacy'?hpc_default_schema():json_decode($t->config,true);$revision=(int)$t->revision;$name=$t->name.' 편집본';}}
    catch(Throwable $e){echo '<p>'.esc_html($e->getMessage()).'</p>';return;}
    $boot=['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('hpc_editor'),'template'=>$id,'revision'=>$revision,'config'=>$cfg,'base'=>plugins_url('/',__FILE__)];
    echo '<div class="wrap hpc-editor-wrap"><h1>문서로 신청 양식 만들기</h1><p>새 문서 또는 HWP·HWPX 파일을 편집하고, 내용을 입력받을 커서 위치에 “입력 항목 넣기”를 누르세요. 저장하면 새 초안을 만들며 원래 양식은 유지합니다.</p><div class="hpc-editor-toolbar"><label>양식 이름 <input id="editor-name" value="'.esc_attr($name).'" maxlength="120"></label><button class="button" id="editor-new">빈 문서로 시작</button><label class="button">HWP / HWPX 열기 <input id="editor-file" type="file" accept=".hwp,.hwpx" hidden></label><button class="button button-primary" id="editor-save">새 초안으로 저장</button><a class="button" href="'.esc_url(hpc_template_admin_url()).'">양식 관리</a></div><p id="editor-status" role="status">문서 편집기를 준비하고 있습니다.</p><div class="hpc-editor-grid"><section id="hpc-studio"></section><aside class="hpc-editor-fields"><h2>사용자 입력 항목</h2><p>문서에서 입력 위치를 클릭한 후 항목명과 방식을 정해 넣으세요. 같은 항목을 여러 곳에 넣을 수도 있습니다.</p><label>항목명<input id="marker-label" maxlength="80"></label><label>입력 방식<select id="marker-type"><option value="text">짧은 글</option><option value="textarea">긴 글</option><option value="email">이메일</option><option value="number">숫자</option><option value="date">날짜</option><option value="checkbox">동의 체크</option></select></label><label><input id="marker-required" type="checkbox" checked> 필수 항목</label><button class="button button-primary" id="marker-add">입력 항목 넣기</button><ul id="marker-list"></ul><p>도움말·선택지·길이 제한은 저장 후 양식 관리에서 설정합니다. 문서에 남아 있는 입력 표시는 연결된 항목을 삭제하기 전에 지워 주세요.</p></aside></div><script type="application/json" id="hpc-editor-config">'.wp_json_encode($boot,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script><link rel="stylesheet" href="'.esc_url(plugins_url('editor.css',__FILE__)).'"><script type="module" src="'.esc_url(plugins_url('editor.js',__FILE__)).'"></script></div>';
}
add_action('wp_ajax_hpc_editor_source',function(){
    if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'관리자만 사용할 수 있습니다.'],403);check_ajax_referer('hpc_editor','nonce');$tmp=null;
    try{$t=hpc_template_get(absint($_POST['template']??0));$path=hpc_template_file($t);
        if($t->engine!=='tokens'){$cfg=$t->engine==='legacy'?hpc_default_schema():json_decode($t->config,true);$data=[];foreach($cfg['fields'] as &$f){$data[$f['id']]='[[HPC:'.$f['id'].']]';$f['type']='text';$f['prefix']='';$f['suffix']='';}unset($f);$copy=clone $t;$copy->config=wp_json_encode($cfg);$tmp=hpc_private_temp();hpc_fill_mapped($data,$tmp,$copy);$path=$tmp;}
        nocache_headers();header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');readfile($path);
    }catch(Throwable $e){if($tmp&&is_file($tmp))unlink($tmp);wp_send_json_error(['message'=>$e->getMessage()],400);}finally{if($tmp&&is_file($tmp))unlink($tmp);}exit;
});
add_action('wp_ajax_hpc_editor_save',function(){
    if(!current_user_can('manage_options'))wp_send_json_error(['message'=>'관리자만 사용할 수 있습니다.'],403);check_ajax_referer('hpc_editor','nonce');$file=null;
    try{
        $id=absint($_POST['template']??0);if($id){$old=hpc_template_get($id);if((int)$old->revision!==absint($_POST['revision']??0))throw new RuntimeException('원래 양식이 변경되었습니다. 새로 열어 주세요.');}
        $name=sanitize_text_field(hpc_input('template_name'));if(!$name||mb_strlen($name)>120)throw new RuntimeException('양식 이름을 확인해 주세요.');
        [$file,$sha]=hpc_template_uploaded();$path=hpc_private_dir('templates/'.$file);$scan=hpc_template_scan($path);$markers=hpc_marker_targets($path);$input=json_decode(wp_unslash($_POST['config']??''),true);if(!is_array($input)||!isset($input['fields'])||!is_array($input['fields'])||count($input['fields'])>60)throw new RuntimeException('입력 항목 설정을 확인해 주세요.');
        $rows=[];foreach($input['fields'] as $f){$f['target']=isset($markers['m_'.($f['id']??'')])?'m_'.$f['id']:'';$f['options']=implode("\n",is_array($f['options']??null)?$f['options']:[]);$f['required']=!empty($f['required'])?'1':'';$rows[]=$f;}
        foreach($markers as $key=>$m){if(!array_filter($rows,fn($f)=>'m_'.($f['id']??'')===$key))throw new RuntimeException('설정이 없는 입력 표시가 있습니다: '.$m['text']);}
        if(!$markers)throw new RuntimeException('문서에 입력 항목을 한 개 이상 넣어 주세요.');
        $_POST['fields']=wp_slash($rows);$_POST['title_field']=wp_slash($input['title_field']??($rows[0]['id']??''));$_POST['name_field']=wp_slash($input['name_field']??($rows[0]['id']??''));$_POST['intro']=wp_slash($input['intro']??'');$cfg=hpc_template_config_post(['targets'=>$markers]);
        global $wpdb;$ok=$wpdb->insert(hpc_template_table(),['name'=>$name,'state'=>'draft','engine'=>'tokens','file'=>$file,'sha'=>$sha,'config'=>wp_json_encode($cfg),'created_by'=>get_current_user_id(),'updated'=>current_time('mysql')]);if(!$ok)throw new RuntimeException('양식 저장 실패');$new=(int)$wpdb->insert_id;$file=null;
        wp_send_json_success(['id'=>$new,'url'=>hpc_template_admin_url($new),'editorUrl'=>hpc_editor_url($new)]);
    }catch(Throwable $e){if($file&&is_file(hpc_private_dir('templates/'.$file)))unlink(hpc_private_dir('templates/'.$file));wp_send_json_error(['message'=>$e->getMessage()],400);}
});
function hpc_live_preview_ui($a=null){
    $t=$a?hpc_app_template($a):hpc_active_template();$cfg=['ajax'=>admin_url('admin-ajax.php'),'nonce'=>wp_create_nonce('hpc_live_preview'),'id'=>$a?(int)$a->id:0,'template'=>(int)$t->id,'base'=>plugins_url('/',__FILE__)];
    echo '<script type="application/json" id="hpc-preview-config">'.wp_json_encode($cfg,JSON_HEX_TAG|JSON_HEX_AMP).'</script><link rel="stylesheet" href="'.esc_url(plugins_url('live-preview.css',__FILE__)).'"><script type="module" src="'.esc_url(plugins_url('live-preview.js',__FILE__)).'"></script>';
}
add_action('wp_ajax_hpc_live_preview',function(){
    check_ajax_referer('hpc_live_preview','nonce');$tmp=null;
    try{
        $id=absint($_POST['application_id']??0);$a=$id?hpc_get($id):null;$t=$a?hpc_app_template($a):hpc_active_template();if((int)$t->id!==absint($_POST['template_id']??0))throw new RuntimeException('양식이 변경되었습니다. 작성 화면을 다시 열어 주세요.');
        $cfg=json_decode($t->config,true);$input=wp_unslash($_POST);$partial=$cfg;foreach($partial['fields'] as &$f)$f['required']=false;unset($f);
        if($t->engine==='legacy'){$input['fee']=($input['fee']??'')==='paid'?'유상사용 신청':(($input['fee']??'')==='free'?'무상사용 신청':'');}
        $data=hpc_schema_validate($partial,$input);
        if($t->engine==='legacy'){$data['phone']=isset($input['phone_1'])?hpc_phone_parts($input):($data['phone']??'');$data['account_type']=in_array($input['account_type']??'',['운영자','전용','공동활용'],true)?$input['account_type']:'';}
        if($t->engine==='legacy'){$data['date']=$data['date']?:current_time('Y-m-d');$data['fee']=($input['fee']??'')==='유상사용 신청'?'paid':(($input['fee']??'')==='무상사용 신청'?'free':'');}
        $tmp=hpc_private_temp();if($t->engine==='legacy')hpc_fill_hwpx($data,$tmp,hpc_template_file($t));elseif($t->engine==='tokens')hpc_fill_tokens($data,$tmp,$t);else hpc_fill_mapped($data,$tmp,$t);
        nocache_headers();header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');readfile($tmp);
    }catch(Throwable $e){status_header(400);header('Content-Type: application/json; charset=utf-8');echo wp_json_encode(['message'=>$e->getMessage()]);}finally{if($tmp&&is_file($tmp))unlink($tmp);}exit;
});
