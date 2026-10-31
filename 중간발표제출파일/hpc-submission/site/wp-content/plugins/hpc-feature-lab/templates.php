<?php
if(!defined('ABSPATH'))exit;

function hpc_template_table(){return hpc_table('templates');}
function hpc_template_file($t){
    if(!preg_match('/^[a-z0-9_-]+\.hwpx$/D',$t->file))throw new RuntimeException('양식 경로 오류');
    $path=hpc_private_dir('templates/'.$t->file);
    if(!is_file($path)||!hash_equals($t->sha,hash_file('sha256',$path)))throw new RuntimeException('양식 파일 무결성 확인 실패');
    return $path;
}
function hpc_template_get($id){global $wpdb;$t=$wpdb->get_row($wpdb->prepare('SELECT * FROM '.hpc_template_table().' WHERE id=%d',$id));if(!$t)throw new RuntimeException('양식을 찾을 수 없습니다.');return $t;}
function hpc_active_template(){return hpc_template_get((int)get_option('hpc_lab_active_template',1));}
function hpc_app_template($a){$d=json_decode($a->data,true);return hpc_template_get((int)($d['_template_id']??1));}
function hpc_template_hash($t){return hash('sha256',$t->sha.'|'.$t->config.'|'.$t->revision);}
function hpc_xml($xml){
    if(!is_string($xml)||strlen($xml)>8*1024*1024||preg_match('/<!DOCTYPE|<!ENTITY/i',$xml))throw new RuntimeException('안전하게 처리할 수 없는 양식 XML입니다.');
    $d=new DOMDocument();$d->preserveWhiteSpace=true;$old=libxml_use_internal_errors(true);
    $ok=$d->loadXML($xml,LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($old);
    if(!$ok)throw new RuntimeException('양식 XML 형식이 올바르지 않습니다.');return $d;
}
function hpc_template_scan($path){
    $z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('HWPX 파일을 열 수 없습니다.');
    try{
        if(trim((string)$z->getFromName('mimetype'))!=='application/hwp+zip')throw new RuntimeException('HWPX 형식의 양식을 올려 주세요.');
        if($z->numFiles>500)throw new RuntimeException('양식 내부 파일 수가 너무 많습니다.');
        $total=0;$sections=[];
        for($i=0;$i<$z->numFiles;$i++){$s=$z->statIndex($i);$total+=$s['size'];if($s['size']>8*1024*1024||$total>30*1024*1024||str_contains($s['name'],'..')||str_starts_with($s['name'],'/')||str_contains($s['name'],'\\'))throw new RuntimeException('양식 파일 크기 또는 경로가 허용 범위를 넘습니다.');if(preg_match('/^Contents\/section([0-9]+)\.xml$/D',$s['name'],$m))$sections[(int)$m[1]]=$s['name'];}
        if(!$sections||count($sections)>20)throw new RuntimeException('양식 구역이 없거나 너무 많습니다.');ksort($sections);
        hpc_xml($z->getFromName('Contents/header.xml'));$targets=[];$tables=[];
        foreach($sections as $s=>$entry){
            $d=hpc_xml($z->getFromName($entry));$x=new DOMXPath($d);$x->registerNamespace('hp','http://www.hancom.co.kr/hwpml/2011/paragraph');
            foreach($x->query('//hp:tbl') as $ti=>$table){
                $rows=[];foreach($x->query('./hp:tr',$table) as $tr){$cells=[];foreach($x->query('./hp:tc',$tr) as $cell){
                    $a=$x->query('./hp:cellAddr',$cell)->item(0);$span=$x->query('./hp:cellSpan',$cell)->item(0);if(!$a)continue;
                    $r=(int)$a->getAttribute('rowAddr');$c=(int)$a->getAttribute('colAddr');$id='s'.$s.'t'.$ti.'r'.$r.'c'.$c;
                    $texts=[];foreach($x->query('.//hp:t',$cell) as $v)$texts[]=$v->textContent;$text=implode(' ',$texts);
                    $safe=$x->query('.//hp:tbl|.//hp:pic|.//hp:ctrl',$cell)->length===0&&$x->query('./hp:subList/hp:p',$cell)->length>0;
                    $label='구역 '.($s+1).' · 표 '.($ti+1).' · 행 '.($r+1).' · 열 '.($c+1).' — '.($text!==''?mb_substr($text,0,35):'빈칸');
                    if($safe)$targets[$id]=['label'=>$label,'text'=>$text,'section'=>$s,'entry'=>$entry,'table'=>$ti,'row'=>$r,'col'=>$c,'kind'=>'cell'];
                    $cells[]=['id'=>$safe?$id:'','text'=>$text,'rowspan'=>max(1,(int)($span?->getAttribute('rowSpan')??1)),'colspan'=>max(1,(int)($span?->getAttribute('colSpan')??1))];
                }$rows[]=$cells;}$tables[]=['name'=>'구역 '.($s+1).' / 표 '.($ti+1),'rows'=>$rows];
            }
            foreach($x->query('/*/hp:p') as $pi=>$p){
                if($x->query('.//hp:tbl|.//hp:pic|.//hp:ctrl',$p)->length)continue;
                $text='';foreach($x->query('.//hp:t',$p) as $v)$text.=$v->textContent;
                if(trim($text)==='')continue;$id='s'.$s.'p'.$pi;$targets[$id]=['label'=>'구역 '.($s+1).' · 본문 '.($pi+1).' — '.mb_substr($text,0,45),'text'=>$text,'section'=>$s,'entry'=>$entry,'paragraph'=>$pi,'kind'=>'paragraph'];
            }
        }
        $targets=array_merge($targets,hpc_marker_targets($path));if(count($targets)>1000)throw new RuntimeException('양식 입력 위치가 너무 많습니다.');return ['targets'=>$targets,'tables'=>$tables,'sections'=>$sections];
    }finally{$z->close();}
}
function hpc_default_schema(){
    $spec=[
        ['organization','소속기관','text','s0t1r2c2','가상농업연구원'],['department','부서','text','s0t1r2c2','기능검증팀'],
        ['position','직급','text','s0t1r5c0','연구원'],['name','신청인 성명','text','s0t1r5c2','테스트신청자'],['email','이메일','email','s0t1r5c4','researcher@example.test'],['phone','전화번호','text','s0t1r5c8','010-0000-0000'],
        ['domains','업무분야','multicheck','s0t1r6c2','식물'],['project','과제명','text','s0t1r8c2','가상 작물 연구'],['project_no','과제번호','text','s0t1r8c2','TEST-001'],
        ['purpose','연구 목적','textarea','s0t1r9c2','연구용 계산 자원 활용 가능성을 확인합니다.'],['method','수행내용 및 방법','textarea','s0t1r10c2','가상 데이터의 분석 방법과 결과 검증 절차를 기술합니다.'],
        ['period','사용기간','fixed','s0t1r12c1','2027. 1. 1. ~ 2027. 6. 30.'],['nodes','노드 수','number','s0t1r12c5','10'],['disk','디스크 용량','number','s0t1r12c9','100'],['programs','이용 프로그램','text','s0t1r13c2','Python, R, bwa'],
        ['account1','희망 계정명 1안','text','s0t1r15c2','testuser1'],['account2','희망 계정명 2안','text','s0t1r15c2','testuser2'],
        ['privacy','개인정보 안내 확인 및 동의','checkbox','s0t1r16c2','동의'],['security','보안 서약 확인 및 동의','checkbox','s0t1r17c2','동의'],
        ['date','작성일','date','s0t1r18c0','2026-10-13'],['signer','신청인 이름 (날인란)','text','s0t1r18c0','테스트신청자'],['approver','확인자·부서장 이름','text','s0t1r18c0','테스트부서장'],['fee','사용료 구분','select','s0p2','무상사용 신청']
    ];$fields=[];
    foreach($spec as [$id,$label,$type,$target,$example]){
        $f=['id'=>$id,'label'=>$label,'type'=>$type,'target'=>$target,'required'=>!in_array($id,['project_no','period'],true),'max'=>$type==='textarea'?1500:($type==='number'?1000000:200),'help'=>'','options'=>[],'prefix'=>'','suffix'=>'','example'=>$example];
        if($id==='domains')$f['options']=hpc_domains();if($id==='fee'){$f['options']=['유상사용 신청','무상사용 신청'];$f['prefix']='* 사용료 부과 여부 : ';}
        if($id==='nodes'){$f['max']=100;$f['suffix']=' 노드';}if($id==='disk')$f['suffix']=' GB';
        if($id==='department')$f['prefix']='/ ';
        if(in_array($id,['account1','account2'],true))$f['prefix']=$id==='account1'?'(1안) ':'(2안) ';
        if($id==='project_no'){$f['prefix']='과제번호: ';$f['required']=false;}
        if($id==='signer'){$f['prefix']="\n신청인 ";$f['suffix']='                 (날인)';}
        if($id==='approver'){$f['prefix']="\n확인자(부서장) ";$f['suffix']='                 (날인)';}
        if(in_array($id,['privacy','security'],true))$f['suffix']=$id==='privacy'?"\n※ 개인정보보호법 제15조 1항(개인정보의 수집·이용)에 의거하여 본인의 개인정보를 제공할 것을 동의 합니다.":"\n※ 국립농업과학원 초고성능컴퓨터 운영 지침 제18조 및 제21조의 내용을 확인하였음을 동의합니다.";
        $fields[]=$f;
    }
    return ['fields'=>$fields,'title_field'=>'project','name_field'=>'name','intro'=>'공동활용 신청서 작성 · 가상 정보로 시험해 주세요.','font_size'=>10];
}
function hpc_templates_install(){
    if(hpc_private_base()===''||!class_exists('ZipArchive')||!class_exists('DOMDocument')||!function_exists('mb_strlen'))return;
    if(get_option('hpc_lab_template_schema')==='1')return;
    global $wpdb;require_once ABSPATH.'wp-admin/includes/upgrade.php';$c=$wpdb->get_charset_collate();
    dbDelta('CREATE TABLE '.hpc_template_table()." (
      id bigint unsigned NOT NULL AUTO_INCREMENT,
      name varchar(180) NOT NULL,
      state varchar(20) NOT NULL,
      engine varchar(20) NOT NULL,
      file varchar(150) NOT NULL,
      sha varchar(64) NOT NULL,
      config longtext NOT NULL,
      revision bigint unsigned NOT NULL DEFAULT 1,
      tested_hash varchar(64) NOT NULL DEFAULT '',
      created_by bigint unsigned NOT NULL,
      updated datetime NOT NULL,
      PRIMARY KEY  (id)
    ) ENGINE=InnoDB $c;");
    $dir=hpc_private_dir('templates');if(!is_dir($dir)&&!mkdir($dir,0700,true))return;
    if(!is_file($dir.'/original-v1.hwpx')&&!copy(hpc_template_source(),$dir.'/original-v1.hwpx'))return;
    $wpdb->query($wpdb->prepare('INSERT IGNORE INTO '.hpc_template_table().' (id,name,state,engine,file,sha,config,created_by,updated) VALUES (1,%s,%s,%s,%s,%s,%s,0,%s)','기존 2027년 상반기 신청서','published','legacy','original-v1.hwpx',hash_file('sha256',$dir.'/original-v1.hwpx'),wp_json_encode(hpc_default_schema()),current_time('mysql')));
    if($wpdb->get_var('SELECT id FROM '.hpc_template_table().' WHERE id=1')){add_option('hpc_lab_active_template',1);update_option('hpc_lab_template_schema','1');}
}
add_action('init','hpc_templates_install');
add_action('admin_menu',function(){add_menu_page('신청 양식 관리','신청 양식','manage_options','hpc-templates','hpc_templates_page','dashicons-media-document',26);});
add_action('admin_enqueue_scripts',function($hook){if($hook==='toplevel_page_hpc-templates')wp_enqueue_script('hpc-template-admin',plugins_url('templates-admin.js',__FILE__),[], '0.2.0',true);});
function hpc_template_admin_url($id=0){return add_query_arg(['page'=>'hpc-templates','template'=>$id],admin_url('admin.php'));}
function hpc_template_action_form($op,$t=null){echo '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'">';wp_nonce_field('hpc_templates');echo '<input type="hidden" name="action" value="hpc_template_action"><input type="hidden" name="op" value="'.esc_attr($op).'">';if($t)echo '<input type="hidden" name="template" value="'.(int)$t->id.'"><input type="hidden" name="revision" value="'.(int)$t->revision.'">';}
function hpc_template_config_post($scan){
    $rows=$_POST['fields']??[];if(!is_array($rows)||!$rows||count($rows)>60)throw new RuntimeException('입력 항목은 1~60개로 구성해 주세요.');
    $fields=[];$ids=[];$types=['text','textarea','email','number','date','select','multicheck','checkbox','fixed'];
    foreach($rows as $row){
        if(!is_array($row))throw new RuntimeException('항목 형식 오류');$get=function($k)use($row){
            if(!isset($row[$k])||!is_scalar($row[$k]))return '';
            $raw=wp_unslash((string)$row[$k]);$v=sanitize_textarea_field($raw);
            if(in_array($k,['prefix','suffix'],true)&&trim($raw)!==''){preg_match('/^[ \t\r\n]*/',$raw,$start);preg_match('/[ \t\r\n]*$/',$raw,$end);$v=$start[0].$v.$end[0];}
            return $v;
        };
        $id=$get('id');$label=$get('label');$type=$get('type');$target=$get('target');
        if(!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$id)||isset($ids[$id])||in_array($id,['op','id','version','template_id','action'],true))throw new RuntimeException('중복되거나 올바르지 않은 항목 코드입니다.');$ids[$id]=true;
        if(!$label||mb_strlen($label)>80||!in_array($type,$types,true))throw new RuntimeException('항목명과 입력 방식을 확인해 주세요.');
        if($target!==''&&!isset($scan['targets'][$target]))throw new RuntimeException($label.': 문서 위치를 다시 선택해 주세요.');
        $options=array_values(array_filter(array_map('trim',explode("\n",$get('options'))),fn($v)=>$v!==''));
        if(in_array($type,['select','multicheck'],true)&&(!$options||count($options)>30||count(array_unique($options))!==count($options)))throw new RuntimeException($label.': 선택지는 중복 없이 1~30개입니다.');
        foreach($options as $option)if(mb_strlen($option)>80)throw new RuntimeException('선택지가 너무 깁니다.');
        $max=$get('max');if(!ctype_digit($max)||(int)$max<1||(int)$max>($type==='number'?1000000:3000))throw new RuntimeException($label.': 허용 길이·최댓값을 확인해 주세요.');
        $f=['id'=>$id,'label'=>$label,'type'=>$type,'target'=>$target,'required'=>$get('required')==='1','max'=>(int)$max,'options'=>$options];
        foreach(['help','prefix','suffix','example'] as $k){$f[$k]=$get($k);if(mb_strlen($f[$k])>1000)throw new RuntimeException($label.': 도움말·출력 문구가 너무 깁니다.');}
        $fields[]=$f;
    }
    $title=hpc_input('title_field');$name=hpc_input('name_field');if(!isset($ids[$title])||!isset($ids[$name]))throw new RuntimeException('신청 목록에 표시할 제목·신청인 항목을 선택해 주세요.');
    $intro=sanitize_textarea_field(hpc_input('intro'));if(mb_strlen($intro)>2000)throw new RuntimeException('작성 안내는 2000자 이하입니다.');
    return ['fields'=>$fields,'title_field'=>$title,'name_field'=>$name,'intro'=>$intro,'font_size'=>10];
}
function hpc_schema_validate($config,$input){
    $d=[];$length=0;
    foreach($config['fields'] as $f){
        $id=$f['id'];$type=$f['type'];$raw=$input[$id]??'';
        if($type==='fixed'){$v=$f['example'];}
        elseif($type==='multicheck'){
            if(!is_array($raw))$raw=$raw===''?[]:[$raw];$v=[];
            foreach($raw as $r){if(!is_scalar($r)||!in_array((string)$r,$f['options'],true))throw new RuntimeException($f['label'].': 허용되지 않는 선택입니다.');$v[]=(string)$r;}$v=array_values(array_unique($v));
        }elseif($type==='checkbox'){$v=$raw==='1'||$raw===true;}
        else{
            if(!is_scalar($raw))throw new RuntimeException('입력 형식 오류');$v=sanitize_textarea_field(trim((string)$raw));
            if($v!==''){
                if($type==='number'){if(!ctype_digit($v)||(int)$v>(int)$f['max'])throw new RuntimeException($f['label'].': 0~'.$f['max'].' 범위의 정수를 입력하세요.');$v=(int)$v;}
                else if(mb_strlen($v)>(int)$f['max'])throw new RuntimeException($f['label'].': 글자 수를 줄여 주세요.');
                if($type==='email'&&!is_email($v))throw new RuntimeException($f['label'].': 이메일을 확인해 주세요.');
                if($type==='date'){$dt=DateTimeImmutable::createFromFormat('!Y-m-d',$v);if(!$dt||$dt->format('Y-m-d')!==$v)throw new RuntimeException($f['label'].': 날짜를 확인해 주세요.');}
                if($type==='select'&&!in_array($v,$f['options'],true))throw new RuntimeException($f['label'].': 선택지를 확인해 주세요.');
            }
        }
        if($f['required']&&($v===''||$v===[]||$v===false))throw new RuntimeException($f['label'].': 필수 항목입니다.');
        $length+=mb_strlen(is_array($v)?implode('',$v):(string)$v);$d[$id]=$v;
    }
    if($length>12000)throw new RuntimeException('전체 입력 내용은 12,000자 이하로 작성해 주세요.');return $d;
}
function hpc_schema_example($config){
    $d=[];foreach($config['fields'] as $f){$v=$f['example'];if($f['type']==='checkbox')$v='1';if($f['type']==='multicheck')$v=array_slice($f['options'],0,1);if($f['type']==='select'&&!in_array($v,$f['options'],true))$v=$f['options'][0];if($v===''&&$f['required'])$v=match($f['type']){'email'=>'test@example.test','number'=>'1','date'=>current_time('Y-m-d'),default=>'테스트 '.$f['label']};$d[$f['id']]=$v;}return hpc_schema_validate($config,$d);
}
function hpc_template_test_app($t){$d=hpc_schema_example(json_decode($t->config,true));if($t->engine==='legacy')$d['fee']=($d['fee']??'')==='유상사용 신청'?'paid':'free';$d['_template_id']=(int)$t->id;return (object)['id'=>'template-'.$t->id.'-'.$t->revision,'version'=>1,'data'=>wp_json_encode($d)];}
function hpc_template_preview($t){
    $app=hpc_template_test_app($t);$files=[];foreach(['hwpx','hwp','pdf'] as $fmt)$files[$fmt]=hpc_document($app,$fmt);
    $log=@file_get_contents(dirname($files['pdf']).'/convert.log');
    if($log&&str_contains($log,'LAYOUT_OVERFLOW'))throw new RuntimeException('시험 출력에서 페이지 넘침이 감지됐습니다. 양식의 표 나누기·입력 분량을 조정해 주세요.');return $files;
}
function hpc_template_uploaded(){
    $f=$_FILES['template_file']??null;
    if(!$f||is_array($f['name'])||$f['error']!==UPLOAD_ERR_OK||$f['size']>10*1024*1024||strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='hwpx')throw new RuntimeException('10MB 이하 HWPX 양식 파일을 선택해 주세요.');
    hpc_template_scan($f['tmp_name']);$file=bin2hex(random_bytes(20)).'.hwpx';$path=hpc_private_dir('templates/'.$file);
    if(!move_uploaded_file($f['tmp_name'],$path))throw new RuntimeException('양식 저장 실패');chmod($path,0600);return [$file,hash_file('sha256',$path)];
}
add_action('admin_post_hpc_template_action',function(){
    if(!current_user_can('manage_options'))wp_die('양식 관리 권한이 없습니다.','',403);
    check_admin_referer('hpc_templates');global $wpdb;$id=absint(hpc_input('template'));$op=hpc_input('op');
    try{
        if($op==='create'){
            $name=sanitize_text_field(hpc_input('template_name'));if(!$name||mb_strlen($name)>120)throw new RuntimeException('양식 이름을 입력해 주세요.');
            [$file,$sha]=hpc_template_uploaded();$cfg=['fields'=>[],'title_field'=>'','name_field'=>'','intro'=>'','font_size'=>10];
            $ok=$wpdb->insert(hpc_template_table(),['name'=>$name,'state'=>'draft','engine'=>'mapped','file'=>$file,'sha'=>$sha,'config'=>wp_json_encode($cfg),'created_by'=>get_current_user_id(),'updated'=>current_time('mysql')]);$id=$wpdb->insert_id;if(!$ok)throw new RuntimeException('양식 등록 실패');
        }elseif($op==='clone'){
            $t=hpc_template_get($id);hpc_template_file($t);$ok=$wpdb->insert(hpc_template_table(),['name'=>$t->name.' 복사본','state'=>'draft','engine'=>$t->engine==='tokens'?'tokens':'mapped','file'=>$t->file,'sha'=>$t->sha,'config'=>$t->engine==='legacy'?wp_json_encode(hpc_default_schema()):$t->config,'created_by'=>get_current_user_id(),'updated'=>current_time('mysql')]);$id=$wpdb->insert_id;if(!$ok)throw new RuntimeException('복제 실패');
        }elseif(in_array($op,['save','replace','preview','activate','reactivate'],true)){
            $t=hpc_template_get($id);$rev=absint(hpc_input('revision'));if((int)$t->revision!==$rev)throw new RuntimeException('다른 화면에서 양식이 변경됐습니다. 새로고침해 주세요.');
            if($op==='reactivate'){
                if($t->state!=='published')throw new RuntimeException('적용 이력이 있는 양식만 복구할 수 있습니다.');hpc_template_file($t);update_option('hpc_lab_active_template',$id);
            }else{
                if($t->state!=='draft')throw new RuntimeException('적용된 양식은 수정할 수 없습니다. 새 버전으로 복제해 주세요.');
                $changes=['updated'=>current_time('mysql')];
                if($op==='save'){$scan=hpc_template_scan(hpc_template_file($t));if($t->engine==='tokens')$scan['targets']=hpc_marker_targets(hpc_template_file($t));$cfg=hpc_template_config_post($scan);$name=sanitize_text_field(hpc_input('template_name'));if(!$name||mb_strlen($name)>120)throw new RuntimeException('양식 이름을 확인해 주세요.');$changes+=['name'=>$name,'config'=>wp_json_encode($cfg),'tested_hash'=>'','revision'=>$rev+1];}
                if($op==='replace'){
                    [$file,$sha]=hpc_template_uploaded();$cfg=json_decode($t->config,true);foreach($cfg['fields'] as &$f)$f['target']='';unset($f);
                    $changes+=['file'=>$file,'sha'=>$sha,'config'=>wp_json_encode($cfg),'tested_hash'=>'','revision'=>$rev+1];
                }
                if($op==='preview'){
                    $cfg=json_decode($t->config,true);if(!$cfg['fields'])throw new RuntimeException('입력 항목을 먼저 저장해 주세요.');
                    hpc_template_preview($t);$changes+=['tested_hash'=>hpc_template_hash($t)];
                }
                if($op==='activate'){
                    if(!$t->tested_hash||!hash_equals(hpc_template_hash($t),$t->tested_hash))throw new RuntimeException('최신 설정으로 시험 출력을 먼저 실행해 주세요.');
                    if(hpc_input('reviewed')!=='1')throw new RuntimeException('시험 출력 문서를 확인한 뒤 적용해 주세요.');
                    $cfg=json_decode($t->config,true);if(!array_filter($cfg['fields'],fn($f)=>$f['target']!==''))throw new RuntimeException('문서에 연결된 입력 항목이 없습니다.');
                    $changes+=['state'=>'published'];
                }
                $count=$wpdb->update(hpc_template_table(),$changes,['id'=>$id,'revision'=>$rev,'state'=>'draft']);if($count!==1)throw new RuntimeException('다른 화면에서 양식이 변경됐습니다. 새로고침해 주세요.');
                if($op==='activate')update_option('hpc_lab_active_template',$id);
            }
        }else throw new RuntimeException('지원하지 않는 작업입니다.');
        wp_safe_redirect(add_query_arg('saved',1,hpc_template_admin_url($id)));exit;
    }catch(Throwable $e){wp_die(esc_html($e->getMessage()).'<p><a href="'.esc_url(hpc_template_admin_url($id)).'">양식 관리로 돌아가기</a></p>','양식 처리 안내',['response'=>400]);}
});
add_action('admin_post_hpc_template_download',function(){
    if(!current_user_can('manage_options'))wp_die('양식 관리 권한이 없습니다.','',403);check_admin_referer('hpc_templates');
    try{$t=hpc_template_get(absint($_GET['template']??0));if(!$t->tested_hash||!hash_equals(hpc_template_hash($t),$t->tested_hash))throw new RuntimeException('시험 출력을 먼저 실행해 주세요.');$fmt=sanitize_key($_GET['format']??'pdf');$file=hpc_document(hpc_template_test_app($t),$fmt);nocache_headers();header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="template-'.$t->id.'-preview.'.$fmt.'"');header('Content-Length: '.filesize($file));readfile($file);exit;}catch(Throwable $e){wp_die(esc_html($e->getMessage()),'',400);}
});
function hpc_schema_form($t,$a=null){
    $cfg=json_decode($t->config,true);$d=$a?json_decode($a->data,true):[];
    if($_SERVER['REQUEST_METHOD']==='POST'&&hpc_input('op')==='save')$d=array_merge($d,wp_unslash($_POST));
    echo '<h2>신청서 작성</h2><p>양식: '.esc_html($t->name).' (버전 '.(int)$t->id.')</p><p>'.nl2br(esc_html($cfg['intro'])).'</p><form method="post">';hpc_nonce('save',$a);echo '<input type="hidden" name="template_id" value="'.(int)$t->id.'">';
    foreach($cfg['fields'] as $f){$k=$f['id'];$type=$f['type'];$v=$d[$k]??'';$req=$f['required']?'required':'';$label=$f['label'].($f['required']?' *':'');
        if($type==='fixed'){echo '<p><strong>'.esc_html($f['label']).':</strong> '.nl2br(esc_html($f['example'])).'</p>';continue;}
        if(in_array($type,['multicheck','checkbox'],true)){
            echo '<fieldset><legend>'.esc_html($label).'</legend>';$opts=$type==='checkbox'?['1'=>$f['label']]:array_combine($f['options'],$f['options']);
            foreach($opts as $val=>$text){$checked=$type==='checkbox'?($v===true||$v==='1'):(is_array($v)&&in_array($val,$v,true));echo '<label class="inline"><input type="checkbox" name="'.esc_attr($k.($type==='multicheck'?'[]':'')).'" value="'.esc_attr($val).'" '.checked($checked,true,false).' '.($type==='checkbox'?$req:'').'>'.esc_html($text).'</label>';}echo '</fieldset>';
        }else{
            echo '<label for="'.esc_attr($k).'">'.esc_html($label).'</label>';$v=is_scalar($v)?(string)$v:'';
            if($type==='textarea')echo '<textarea id="'.esc_attr($k).'" name="'.esc_attr($k).'" rows="4" maxlength="'.$f['max'].'" '.$req.'>'.esc_textarea($v).'</textarea>';
            elseif($type==='select'){echo '<select id="'.esc_attr($k).'" name="'.esc_attr($k).'" '.$req.'><option value="">선택해 주세요</option>';foreach($f['options'] as $o)echo '<option '.selected($v,$o,false).' value="'.esc_attr($o).'">'.esc_html($o).'</option>';echo '</select>';}
            else echo '<input id="'.esc_attr($k).'" name="'.esc_attr($k).'" type="'.esc_attr($type).'" value="'.esc_attr($v).'" '.($type==='number'?'min="0" max="'.$f['max'].'"':'maxlength="'.$f['max'].'"').' '.$req.'>';
        }
        if($f['help'])echo '<p>'.nl2br(esc_html($f['help'])).'</p>';
    }echo '<button>작성 내용 저장</button></form>';
}
function hpc_summary($a,$role){$d=json_decode($a->data,true);$t=hpc_app_template($a);$cfg=json_decode($t->config,true);$key=$cfg[$role.'_field']??($role==='title'?'project':'name');$v=$d[$key]??'';return is_array($v)?implode(', ',$v):(string)$v;}

function hpc_templates_page(){
    if(!current_user_can('manage_options'))return;global $wpdb;
    $all=$wpdb->get_results('SELECT * FROM '.hpc_template_table().' ORDER BY id DESC');$active=(int)get_option('hpc_lab_active_template',1);$id=absint($_GET['template']??0);
    echo '<div class="wrap"><h1>신청 양식 관리</h1><p><a class="button button-primary" href="'.esc_url(hpc_editor_url()).'">문서 편집기로 새 양식 만들기</a></p><p>양식 업로드 → 입력 항목과 문서 위치 연결 → 시험 출력 → 새 신청에 적용 순서로 진행합니다. 기존 신청은 작성 당시 양식을 유지합니다.</p>';
    if(isset($_GET['saved']))echo '<div class="notice notice-success"><p>처리되었습니다.</p></div>';
    echo '<table class="widefat striped"><thead><tr><th>버전</th><th>양식 이름</th><th>상태</th><th>작업</th></tr></thead><tbody>';
    foreach($all as $t){echo '<tr><td>'.(int)$t->id.'</td><td><a href="'.esc_url(hpc_template_admin_url($t->id)).'">'.esc_html($t->name).'</a></td><td>'.($active===(int)$t->id?'현재 사용 중':($t->state==='draft'?'준비 중':'이전 양식')).'</td><td>';hpc_template_action_form('clone',$t);echo '<button class="button">새 버전으로 복제</button></form>';if($t->state==='published'&&$active!==(int)$t->id){hpc_template_action_form('reactivate',$t);echo '<button class="button">이 양식으로 복구</button></form>';}echo '</td></tr>';}
    echo '</tbody></table><details style="margin:20px 0"><summary><strong>새 양식 파일 등록</strong></summary>';hpc_template_action_form('create');echo '<p><label>양식 이름 <input name="template_name" required maxlength="120"></label></p><p><label>HWPX 파일 <input name="template_file" type="file" accept=".hwpx" required></label></p><p>HWP 파일은 한글에서 HWPX로 저장한 뒤 올려 주세요. 최대 10MB입니다.</p><button class="button button-primary">새 양식 등록</button></form></details>';
    if(!$id){echo '</div>';return;}
    try{$t=hpc_template_get($id);$scan=hpc_template_scan(hpc_template_file($t));$cfg=json_decode($t->config,true);}catch(Throwable $e){echo '<p>'.esc_html($e->getMessage()).'</p></div>';return;}
    if($t->engine==='tokens')$scan['targets']=hpc_marker_targets(hpc_template_file($t));
    echo '<hr><h2>'.esc_html($t->name).' — 버전 '.(int)$t->id.'</h2><p><a class="button" href="'.esc_url(hpc_editor_url($id)).'">문서 편집기로 열기</a></p>';
    if($t->state!=='draft'){echo '<p>적용된 양식은 변경할 수 없습니다. 위의 “새 버전으로 복제”로 수정본을 준비하세요.</p></div>';return;}
    echo '<details><summary>양식 파일 교체</summary><p>교체하면 문서 위치 연결과 시험 출력 결과가 초기화됩니다. 입력 항목은 유지됩니다.</p>';hpc_template_action_form('replace',$t);echo '<input type="file" name="template_file" accept=".hwpx" required><button class="button">파일 교체</button></form></details>';
    echo '<details style="margin:18px 0"><summary>문서의 표와 입력 위치 확인</summary><p>위치 선택을 돕는 표입니다. 실제 출력 모양은 시험 PDF에서 확인하세요.</p>';
    foreach($scan['tables'] as $table){echo '<h3>'.esc_html($table['name']).'</h3><table class="widefat" style="width:auto;max-width:100%;table-layout:fixed">';foreach($table['rows'] as $row){echo '<tr>';foreach($row as $cell)echo '<td rowspan="'.$cell['rowspan'].'" colspan="'.$cell['colspan'].'" style="border:1px solid #ccd0d4;max-width:300px;overflow-wrap:anywhere"><small>'.esc_html($cell['id']).'</small><br>'.esc_html(mb_substr($cell['text'],0,100)).'</td>';echo '</tr>';}echo '</table>'; }echo '</details>';
    hpc_template_action_form('save',$t);echo '<p><label>양식 이름 <input name="template_name" value="'.esc_attr($t->name).'" maxlength="120" required class="regular-text"></label></p><p><label>작성 안내<br><textarea name="intro" rows="3" class="large-text">'.esc_textarea($cfg['intro']).'</textarea></label></p><p>같은 문서 위치에 여러 항목을 연결하면 항목 순서대로 이어서 출력합니다. 줄을 바꾸려면 “출력 앞 문구” 또는 “출력 뒤 문구”에 줄바꿈을 넣으세요. 문서에 넣지 않을 항목은 “웹에서만 수집”을 선택하세요.</p><div id="hpc-field-rows">';
    foreach($cfg['fields'] as $i=>$f)hpc_template_field_row($i,$f,$scan);echo '</div><button type="button" class="button" id="hpc-add-field">입력 항목 추가</button>';
    echo '<p><label>신청 목록 제목 <select name="title_field" id="hpc-title-field" data-selected="'.esc_attr($cfg['title_field']).'"></select></label> <label>신청인 표시 <select name="name_field" id="hpc-name-field" data-selected="'.esc_attr($cfg['name_field']).'"></select></label></p><button class="button button-primary">항목 설정 저장</button></form>';
    echo '<template id="hpc-field-template">';hpc_template_field_row('__INDEX__',['id'=>'__ID__','label'=>'','type'=>'text','target'=>'','required'=>false,'max'=>200,'help'=>'','options'=>[],'prefix'=>'','suffix'=>'','example'=>''],$scan);echo '</template>';
    echo '<hr><h2>시험 출력 및 적용</h2>';hpc_template_action_form('preview',$t);echo '<p>저장된 시험 입력값으로 HWP와 PDF를 만듭니다. 입력 항목을 바꾸면 먼저 설정을 저장하세요.</p><button class="button">시험 출력 생성</button></form>';
    if($t->tested_hash&&hash_equals($t->tested_hash,hpc_template_hash($t))){echo '<p>';foreach(['hwpx','hwp','pdf'] as $fmt)echo '<a class="button" href="'.esc_url(wp_nonce_url(add_query_arg(['action'=>'hpc_template_download','template'=>$id,'format'=>$fmt],admin_url('admin-post.php')),'hpc_templates')).'">시험 '.strtoupper($fmt).' 다운로드</a> ';echo '</p>';hpc_template_action_form('activate',$t);echo '<p><label><input type="checkbox" name="reviewed" value="1" required> 시험 출력 문서의 내용과 표·날인란을 확인했습니다.</label></p><button class="button button-primary">새 신청에 이 양식 적용</button></form>';}
    echo '</div>';
}
function hpc_template_field_row($i,$f,$scan){
    $n='fields['.$i.']';$types=['text'=>'짧은 글','textarea'=>'긴 글','email'=>'이메일','number'=>'숫자','date'=>'날짜','select'=>'하나 선택','multicheck'=>'여러 개 선택','checkbox'=>'동의 체크','fixed'=>'고정 안내 문구'];
    echo '<fieldset class="hpc-field-row" style="padding:16px;margin:14px 0;border:1px solid #b7bdc3;background:#fff"><input type="hidden" data-field-id name="'.$n.'[id]" value="'.esc_attr($f['id']).'"><p><label>항목명 <input data-field-label name="'.$n.'[label]" value="'.esc_attr($f['label']).'" maxlength="80" required></label> <label>입력 방식 <select name="'.$n.'[type]">';foreach($types as $v=>$label)echo '<option value="'.$v.'" '.selected($v,$f['type'],false).'>'.$label.'</option>';echo '</select></label> <label><input type="checkbox" name="'.$n.'[required]" value="1" '.checked($f['required'],true,false).'>필수</label> <button type="button" class="button hpc-up">위로</button> <button type="button" class="button hpc-down">아래로</button> <button type="button" class="button hpc-remove">항목 삭제</button></p>';
    echo '<p><label>도움말<br><textarea name="'.$n.'[help]" rows="2" class="large-text" maxlength="1000">'.esc_textarea($f['help']).'</textarea></label></p><p><label>최대 글자 수 / 숫자 최댓값 <input type="number" min="1" max="1000000" name="'.$n.'[max]" value="'.(int)$f['max'].'" required></label></p><p><label>선택지 (선택형 항목만, 한 줄에 하나)<br><textarea name="'.$n.'[options]" rows="2" class="large-text">'.esc_textarea(implode("\n",$f['options'])).'</textarea></label></p>';
    echo '<p><label>문서에 들어갈 위치 <select style="max-width:100%" name="'.$n.'[target]"><option value="">웹에서만 수집 (문서에는 넣지 않음)</option>';foreach($scan['targets'] as $key=>$target)echo '<option value="'.esc_attr($key).'" '.selected($key,$f['target'],false).'>'.esc_html($target['label']).'</option>';echo '</select></label></p>';
    foreach(['prefix'=>'출력 앞 문구','suffix'=>'출력 뒤 문구','example'=>'시험 입력값 / 고정 안내 문구'] as $k=>$label)echo '<p><label>'.esc_html($label).'<br><textarea rows="2" class="large-text" name="'.$n.'['.$k.']" maxlength="1000">'."\n".esc_textarea($f[$k]).'</textarea></label></p>';echo '</fieldset>';
}
