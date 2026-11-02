<?php
if(!defined('ABSPATH'))exit;
function hkc_plain($text){
 $text=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',$text);
 $text=preg_replace('~</(?:p|div|li|h[1-6])\s*>|<br\s*/?>~i',"\n",$text);
 return trim(html_entity_decode(wp_strip_all_tags(strip_shortcodes($text)),ENT_QUOTES|ENT_HTML5,'UTF-8'));
}
function hkc_terms($q){
 $q=mb_strtolower($q);
 foreach(['첨부'=>['업로드','올리기'],'수정'=>['변경','고치'],'신청'=>['접수','지원'],'계정'=>['가입','로그인'],'오류'=>['안 돼','안돼','실패']] as $term=>$variants) foreach($variants as $v) if(mb_strpos($q,$v)!==false)$q.=' '.$term;
 $words=preg_split('/[^\p{L}\p{N}]+/u',$q,-1,PREG_SPLIT_NO_EMPTY); $result=[];
 foreach($words as $word){
  $word=preg_replace('/(?:인가요|하나요|할까요|해주세요|알려주세요|있나요|되나요|으로|에서|까지|부터|처럼|은|는|이|가|을|를|의|에|도)$/u','',$word);
  if(mb_strlen($word)<2 || in_array($word,['어떤','무엇','무슨','어떻게','주세요','알려','내용','궁금','관련','싶어','대해','대한','있','하','수'],true))continue;
  $result[]=$word;
 }
 return array_slice(array_values(array_unique($result)),0,12);
}
function hkc_post_allowed($p){
 if(!$p || $p->post_status!=='publish' || $p->post_password!=='')return false;
 if(!in_array($p->post_type,['hkc_faq','hkc_public_qna','post','page','hpc_notice','hpc_event'],true))return false;
 if(get_post_meta($p->ID,'_hkc_exclude',true))return false;
 $map=get_option('hpc_portal_pages_v1',[]);
 foreach(['application','application-status','my-page','qna','forum'] as $key) if((int)($map[$key]??0)===$p->ID)return false;
 if(in_array($p->post_name,['application','application-status','my-page','qna','forum','ai-chatbot','sample-page','application-simulation','usage-simulation'],true))return false;
 if(preg_match('/\[(?:hpc_portal_|hkc_chat|center_chatbot)/',$p->post_content))return false;
 return true;
}
function hkc_source_url($p){
 if($p->post_type==='hpc_notice')return function_exists('hpcc_board_url')?hpcc_board_url($p->ID):'';
 if($p->post_type==='hpc_event')return function_exists('hpcc_event_url')?hpcc_event_url($p->ID):'';
 return get_permalink($p);
}
function hkc_documents(){
 $docs=[];
 foreach(get_posts(['post_type'=>['hkc_faq','hkc_public_qna','post','page','hpc_notice','hpc_event'],'post_status'=>'publish','has_password'=>false,'posts_per_page'=>-1,'orderby'=>'ID','order'=>'ASC']) as $p){
  if(!hkc_post_allowed($p))continue;
  $url=hkc_source_url($p);if(!$url)continue;
  $body=$p->post_content;
  // Only these reviewed, public static shortcodes may be expanded. Never run arbitrary page shortcodes.
  foreach(['hpc_usage_rules','hpc_security_programs'] as $tag) if(has_shortcode($body,$tag) && shortcode_exists($tag)) $body=str_replace('['.$tag.']',do_shortcode('['.$tag.']'),$body);
  $body=hkc_plain($body); if($body==='')continue;
  $kind=['hkc_faq'=>'공식 FAQ','hkc_public_qna'=>'공개 Q&A','hpc_notice'=>'공지','hpc_event'=>'행사'][$p->post_type]??'사이트 안내';
  $docs[]=['key'=>'post:'.$p->ID,'title'=>$p->post_title,'text'=>$body,'url'=>$url,'kind'=>$kind,'updated'=>$p->post_modified_gmt];
 }
 if(function_exists('hkb_ids') && get_option('hkb_enabled')){
  global $wpdb;$ids=array_filter(array_map('intval',hkb_ids()));
  if($ids){$rows=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}kboard_board_content WHERE board_id IN (".implode(',',$ids).") AND status='' AND secret='' ORDER BY uid DESC");
   foreach($rows as $r){$doc=hkc_kboard_document($r);if($doc)$docs[]=$doc;}
  }
 }
 return $docs;
}
function hkc_still_allowed($key,$updated=null){
 [$kind,$id]=array_pad(explode(':',$key,2),2,0);$id=(int)$id;
 if($kind==='kboard'){if(!function_exists('hkb_row'))return false;$doc=hkc_kboard_document(hkb_row($id));return $doc && ($updated===null || $doc['updated']===$updated);}
 if($kind==='post'){$p=get_post($id);return hkc_post_allowed($p) && ($updated===null || $updated===$p->post_modified_gmt);}
 return false;
}
function hkc_search($q){
 $terms=hkc_terms($q);if(!$terms)return [];$hits=[];
 foreach(hkc_documents() as $doc){
  $chunks=[];
  foreach(preg_split('/\n+/u',$doc['text'],-1,PREG_SPLIT_NO_EMPTY) as $paragraph){
   for($i=0;$i<mb_strlen($paragraph);$i+=500)$chunks[]=mb_substr($paragraph,$i,500);
  }
  foreach($chunks as $n=>$text){
   if(mb_strlen(trim($text))<8)continue;
   $score=0;$matched=0;
   foreach($terms as $term){$inTitle=mb_stripos($doc['title'],$term)!==false;$inText=mb_stripos($text,$term)!==false;if($inTitle||$inText){$matched++;$score+=($inTitle?4:0)+($inText?2:0);}}
   if(!$matched || $score<2)continue;
   // Require more than one signal for multi-topic questions; a lone generic match is insufficient.
   if(count($terms)>=3 && $matched<2)continue;
   if($doc['kind']==='공식 FAQ')$score+=2;
   if($doc['kind']==='회원 포럼')$score-=1;
   $hits[]=array_merge($doc,['text'=>trim($text),'score'=>$score,'chunk'=>$n]);
  }
 }
 usort($hits,fn($a,$b)=>$b['score']<=>$a['score']);$selected=[];$counts=[];$chars=0;
 foreach($hits as $hit){
  if(($counts[$hit['key']]??0)>=2)continue;
  if($chars+mb_strlen($hit['text'])>2200)continue;
  $hit['id']=count($selected)+1;$selected[]=$hit;$chars+=mb_strlen($hit['text']);$counts[$hit['key']]=($counts[$hit['key']]??0)+1;
  if(count($selected)>=5)break;
 }
 return $selected;
}

function hkc_kboard_document($r){
 if(!$r || !function_exists('hkb_kind') || !get_option('hkb_enabled') || $r->status!=='' || !empty($r->secret))return false;
 $kind=hkb_kind($r->board_id);
 if($kind!=='qna' && $kind!=='forum')return false;
 if($kind==='forum' && !hkc_forum_allowed())return false;
 if(!hkb_read($r))return false;
 $answer=$kind==='qna'?(string)(new KBContentOption($r->uid))->hkb_answer:'';
 $body=hkc_plain($r->content."
".$answer);
 $map=get_option('hpc_portal_pages_v1',[]);$page=(int)($map[$kind]??0);
 if(!$page)return false;
 return ['key'=>'kboard:'.$r->uid,'title'=>$r->title,'text'=>$body,'url'=>add_query_arg(['mod'=>'document','uid'=>(int)$r->uid],get_permalink($page)),'kind'=>$kind==='forum'?'회원 포럼':'공개 Q&A','updated'=>hash('sha256',$r->title.'|'.$body.'|'.$r->date)];
}
