<?php
if(!defined('ABSPATH'))exit;
function hpc_fill_mapped($data,$out,$template){
    $path=hpc_template_file($template);$scan=hpc_template_scan($path);$cfg=json_decode($template->config,true);$groups=[];
    foreach($cfg['fields'] as $f){
        if($f['target']==='')continue;if(!isset($scan['targets'][$f['target']]))throw new RuntimeException('연결된 문서 위치가 없습니다.');
        $v=$f['type']==='fixed'?$f['example']:($data[$f['id']]??'');
        if($f['type']==='checkbox')$v=$v?'■ 동의    □ 미동의':'□ 동의    ■ 미동의';
        elseif(in_array($f['type'],['select','multicheck'],true)){
            $selected=is_array($v)?$v:[$v];$parts=[];foreach($f['options'] as $o)$parts[]=(in_array($o,$selected,true)?'■':'□').$o;$v=implode('  ',$parts);
        }
        $groups[$f['target']]??=[];
        if($v==='')continue;
        $groups[$f['target']][]=$f['prefix'].(string)$v.$f['suffix'];
    }
    if(!copy($path,$out))throw new RuntimeException('출력 양식 복사 실패');$z=new ZipArchive();if($z->open($out)!==true)throw new RuntimeException('출력 양식 열기 실패');
    try{
        $header=hpc_xml($z->getFromName('Contents/header.xml'));$hx=new DOMXPath($header);$hx->registerNamespace('hh','http://www.hancom.co.kr/hwpml/2011/head');
        $styles=[];$allText=[];
        foreach($scan['sections'] as $section=>$entry){
            $d=hpc_xml($z->getFromName($entry));$x=new DOMXPath($d);$ns='http://www.hancom.co.kr/hwpml/2011/paragraph';$x->registerNamespace('hp',$ns);
            $touchedTables=[];
            foreach($groups as $target=>$values){
                $pos=$scan['targets'][$target];if($pos['section']!==$section)continue;
                $text=implode(' ',$values);
                if($pos['kind']==='cell'){
                    $table=$x->query('//hp:tbl')->item($pos['table']);$touchedTables[$pos['table']]=$table;
                    $cell=$x->query('./hp:tr/hp:tc[hp:cellAddr[@rowAddr="'.$pos['row'].'" and @colAddr="'.$pos['col'].'"]]',$table)->item(0);
                    $container=$x->query('./hp:subList',$cell)->item(0);$p=$x->query('./hp:p',$container)->item(0);
                }else{$p=$x->query('/*/hp:p')->item($pos['paragraph']);$container=null;}
                if(!$p)throw new RuntimeException('문서 입력 위치를 읽을 수 없습니다.');
                $run=$x->query('./hp:run',$p)->item(0);$char=$run?$run->getAttribute('charPrIDRef'):'0';
                if(!isset($styles[$char])){
                    $old=$hx->query('//hh:charPr[@id="'.$char.'"]')->item(0);if(!$old)throw new RuntimeException('지원되지 않는 글자 서식입니다.');
                    $copy=$old->cloneNode(true);$max=0;foreach($hx->query('//hh:charPr') as $s)$max=max($max,(int)$s->getAttribute('id'));
                    $newid=(string)($max+1);$copy->setAttribute('id',$newid);$copy->setAttribute('textColor','#000000');$copy->setAttribute('height','1000');
                    $list=$hx->query('//hh:charProperties')->item(0);$list->appendChild($copy);$list->setAttribute('itemCnt',(string)$hx->query('//hh:charPr')->length);$styles[$char]=$newid;
                }
                $base=$p->cloneNode(false);
                if($container){while($container->firstChild)$container->removeChild($container->firstChild);}
                else{
                    // Preserve top-level paragraph indexing while replacing the text in place.
                    while($p->firstChild)$p->removeChild($p->firstChild);
                    $pr=$hx->query('//hh:paraPr[@id="'.$p->getAttribute('paraPrIDRef').'"]')->item(0)->cloneNode(true);$max=0;foreach($hx->query('//hh:paraPr') as $s)$max=max($max,(int)$s->getAttribute('id'));$newid=(string)($max+1);$pr->setAttribute('id',$newid);
                    foreach($pr->getElementsByTagNameNS('http://www.hancom.co.kr/hwpml/2011/core','prev') as $m)$m->setAttribute('value','1000');
                    $list=$hx->query('//hh:paraProperties')->item(0);$list->appendChild($pr);$list->setAttribute('itemCnt',(string)$hx->query('//hh:paraPr')->length);$p->setAttribute('paraPrIDRef',$newid);
                }
                $lines=explode("\n",str_replace("\r",'',$text));
                foreach($lines as $i=>$line){
                    $q=$container?$base->cloneNode(false):$p;$q->setAttribute('id','0');
                    $r=$d->createElementNS($ns,'hp:run');$r->setAttribute('charPrIDRef',$styles[$char]);$t=$d->createElementNS($ns,'hp:t');
                    if(!$container&&$i>0)$t->appendChild($d->createElementNS($ns,'hp:lineBreak'));
                    $t->appendChild($d->createTextNode($line));$r->appendChild($t);$q->appendChild($r);if($container)$container->appendChild($q);
                }
            }
            foreach($touchedTables as $table){
                $table->setAttribute('pageBreak','CELL');$table->setAttribute('noAdjust','0');$pos=$x->query('./hp:pos',$table)->item(0);if($pos)$pos->setAttribute('treatAsChar','0');
                // Add an explicit closing edge for bottom-row cells after pagination.
                $last=(int)$table->getAttribute('rowCnt')-1;
                foreach($x->query('./hp:tr/hp:tc',$table) as $cell){
                    $a=$x->query('./hp:cellAddr',$cell)->item(0);$sp=$x->query('./hp:cellSpan',$cell)->item(0);
                    if(!$a||(int)$a->getAttribute('rowAddr')+(int)($sp?->getAttribute('rowSpan')?:1)-1!==$last)continue;
                    $b=$hx->query('//hh:borderFill[@id="'.$cell->getAttribute('borderFillIDRef').'"]')->item(0);if(!$b)continue;$b=$b->cloneNode(true);$edge=$hx->query('./hh:bottomBorder',$b)->item(0);if(!$edge)continue;
                    $max=0;foreach($hx->query('//hh:borderFill') as $v)$max=max($max,(int)$v->getAttribute('id'));$id=(string)($max+1);$b->setAttribute('id',$id);$edge->setAttribute('type','SOLID');$edge->setAttribute('width','0.4 mm');$list=$hx->query('//hh:borderFills')->item(0);$list->appendChild($b);$list->setAttribute('itemCnt',(string)$hx->query('//hh:borderFill')->length);$cell->setAttribute('borderFillIDRef',$id);
                }
            }
            $z->addFromString($entry,$d->saveXML());foreach($x->query('//hp:t') as $t)$allText[]=$t->textContent;
        }
        $z->addFromString('Contents/header.xml',$header->saveXML());$z->deleteName('Preview/PrvImage.png');$z->addFromString('Preview/PrvText.txt',implode("\n",$allText));
    }finally{$z->close();}
}
