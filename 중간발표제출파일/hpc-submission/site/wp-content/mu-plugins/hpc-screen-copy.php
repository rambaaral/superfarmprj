<?php
/*
Plugin Name: 센터 화면 문구 관리
Description: 화면 문구를 데이터베이스에서 읽고 관리자에게 편집 화면을 제공합니다.
Version: 1.0.0
*/
defined('ABSPATH') || exit;
function hpct_text($key){
 static $texts=null;
 if($texts===null)$texts=get_option('hpct_texts',[]);
 return isset($texts[$key])?(string)$texts[$key]['text']:'';
}
add_action('admin_menu',function(){
 add_options_page('센터 화면 문구','센터 화면 문구','manage_options','hpct-texts','hpct_admin');
});
function hpct_admin(){
 if(!current_user_can('manage_options'))return;
 $all=get_option('hpct_texts',[]);
 $notice='';
 if($_SERVER['REQUEST_METHOD']==='POST'){
  check_admin_referer('hpct-save');
  $key=sanitize_key(wp_unslash($_POST['key']??''));
  if(isset($all[$key])){
   if(!hash_equals(hash('sha256',$all[$key]['text']),sanitize_text_field(wp_unslash($_POST['version']??''))))$notice='다른 곳에서 수정되었습니다. 새로고침 후 다시 시도해 주세요.';
   else {
    $value=wp_unslash($_POST['value']??'');
    if(!is_string($value)||mb_strlen($value)>5000)$notice='문구가 너무 깁니다.';
    else {
     $value=sanitize_textarea_field($value);
     preg_match('/^\s*/u',$all[$key]['text'],$leading);preg_match('/\s*$/u',$all[$key]['text'],$trailing);
     $value=$leading[0].trim($value).$trailing[0];
     if(trim($value)==='')$notice='문구를 비워 둘 수 없습니다.';
     else {
      $history=get_option('hpct_history',[]);$history[]=['key'=>$key,'text'=>$all[$key]['text'],'time'=>time(),'user'=>get_current_user_id()];
      update_option('hpct_history',array_slice($history,-200),false);
      $all[$key]['text']=$value;update_option('hpct_texts',$all,false);$notice='저장했습니다.';
     }
    }
   }
  }
 }
 $section=sanitize_text_field(wp_unslash($_GET['section']??''));
 $query=sanitize_text_field(wp_unslash($_GET['s']??''));
 echo '<div class="wrap"><h1>센터 화면 문구</h1><p>문구를 수정하면 연결된 화면에 반영됩니다. 페이지 본문은 페이지에서, 메뉴 이름·순서·링크는 모양 → 메뉴에서, FAQ 답변은 게시판 관리에서 편집하세요. 같은 문구를 사용하는 여러 화면에 함께 반영될 수 있습니다.</p>';
 echo '<p><a class="button" href="'.esc_url(admin_url('nav-menus.php')).'">메뉴 편집</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type=page')).'">페이지 본문 편집</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type=hkc_faq')).'">FAQ 편집</a></p>';
 if($notice)echo '<div class="notice notice-info"><p>'.esc_html($notice).'</p></div>';
 echo '<form method="get"><input type="hidden" name="page" value="hpct-texts"><label>문구 검색 <input name="s" value="'.esc_attr($query).'"></label> <label>영역 <select name="section"><option value="">모든 영역</option>';
 $sections=array_unique(array_column($all,'section'));sort($sections);
 foreach($sections as $name)echo '<option value="'.esc_attr($name).'"'.selected($section,$name,false).'>'.esc_html($name).'</option>';
 echo '</select></label> <button class="button">검색</button></form>';
 $filtered=array_filter($all,fn($item)=>($section===''||$item['section']===$section)&&($query===''||mb_stripos($item['text'].' '.$item['section'],$query)!==false));
 $page=max(1,(int)($_GET['paged']??1));$total=count($filtered);$items=array_slice($filtered,($page-1)*30,30,true);
 echo '<p>총 '.(int)$total.'개 문구</p>';
 foreach($items as $key=>$item){
  echo '<form method="post" style="background:#fff;border:1px solid #ddd;padding:18px;margin:12px 0;max-width:900px"><h2>'.esc_html($item['section']).'</h2>';
  wp_nonce_field('hpct-save');
  echo '<input type="hidden" name="key" value="'.esc_attr($key).'"><input type="hidden" name="version" value="'.esc_attr(hash('sha256',$item['text'])).'"><label>표시 문구<textarea name="value" rows="3" style="width:100%">'.esc_textarea($item['text']).'</textarea></label><p><button class="button button-primary">이 문구 저장</button></p></form>';
 }
 for($p=1;$p<=ceil($total/30);$p++)echo '<a class="button" href="'.esc_url(add_query_arg(['page'=>'hpct-texts','s'=>$query,'section'=>$section,'paged'=>$p],admin_url('options-general.php'))).'">'.$p.'</a> ';
 echo '</div>';
}

add_action('wp_head',function(){
 $all=get_option('hpct_texts',[]);$copy=[];
 foreach(get_option('hpct_js_keys',[]) as $key)if(isset($all[$key]))$copy[$key]=$all[$key]['text'];
 echo '<script id="hpct-screen-texts">window.hpctCopy='.wp_json_encode($copy,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';window.hpctText=function(k){return Object.prototype.hasOwnProperty.call(window.hpctCopy,k)?window.hpctCopy[k]:"";};window.hpctEscape=function(v){return String(v).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/\x27/g,"&#39;");};</script>';
},0);

function hpct_label($identifier){return hpct_text('t'.substr(hash('sha256',$identifier),0,20));}
