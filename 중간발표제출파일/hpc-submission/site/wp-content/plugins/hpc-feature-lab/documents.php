<?php
if (!defined('ABSPATH')) exit;
function hpc_fill_hwpx($data,$out,$template=null) {
    $template=$template?:hpc_template_source();
    if (!copy($template,$out)) throw new RuntimeException('양식 복사 실패');
    $zip=new ZipArchive();
    if ($zip->open($out)!==true) throw new RuntimeException('양식 열기 실패');
    $dom=new DOMDocument();
    $dom->preserveWhiteSpace=true;
    if (!$dom->loadXML($zip->getFromName('Contents/section0.xml'),LIBXML_NONET)) throw new RuntimeException('양식 XML 오류');
    $xp=new DOMXPath($dom);$ns='http://www.hancom.co.kr/hwpml/2011/paragraph';
    $xp->registerNamespace('hp',$ns);
    $table=$xp->query('//hp:tbl')->item(1);
    // Pin the template shape. Fail closed if a different template is installed.
    if (!$table || $table->getAttribute('rowCnt')!=='19' || $table->getAttribute('colCnt')!=='11') throw new RuntimeException('양식 버전 불일치');
    $table->setAttribute('pageBreak','CELL');$table->setAttribute('noAdjust','0');
    $xp->query('./hp:pos',$table)->item(0)->setAttribute('treatAsChar','0');
    $header=new DOMDocument();$header->loadXML($zip->getFromName('Contents/header.xml'),LIBXML_NONET);
    $hx=new DOMXPath($header);$hx->registerNamespace('hh','http://www.hancom.co.kr/hwpml/2011/head');
    // Explicit bottom edge survives row splitting (the template relies on the outer table edge).
    $lastCell=$xp->query('./hp:tr/hp:tc[hp:cellAddr[@rowAddr="18" and @colAddr="0"]]',$table)->item(0);
    $border=$hx->query('//hh:borderFill[@id="'.$lastCell->getAttribute('borderFillIDRef').'"]')->item(0)->cloneNode(true);
    $maxBorder=0;foreach($hx->query('//hh:borderFill') as $b)$maxBorder=max($maxBorder,(int)$b->getAttribute('id'));
    $border->setAttribute('id',(string)($maxBorder+1));
    $bottom=$hx->query('./hh:bottomBorder',$border)->item(0);$bottom->setAttribute('type','SOLID');$bottom->setAttribute('width','0.4 mm');
    $borderList=$hx->query('//hh:borderFills')->item(0);$borderList->appendChild($border);$borderList->setAttribute('itemCnt',(string)$hx->query('//hh:borderFill')->length);
    $lastCell->setAttribute('borderFillIDRef',(string)($maxBorder+1));
    $charList=$hx->query('//hh:charProperties')->item(0);$styles=[];
    $write=function($r,$c,$value)use($xp,$table,$dom,$ns,$header,$hx,$charList,&$styles){
        $cell=$xp->query('./hp:tr/hp:tc[hp:cellAddr[@rowAddr="'.$r.'" and @colAddr="'.$c.'"]]',$table)->item(0);
        if (!$cell) throw new RuntimeException('양식 입력 위치 없음');
        $list=$xp->query('./hp:subList',$cell)->item(0);
        $p=$xp->query('./hp:p',$list)->item(0);
        $run=$xp->query('./hp:run',$p)->item(0);
        $char=$run?$run->getAttribute('charPrIDRef'):'0';
        if(!isset($styles[$char])){
            $old=$hx->query('//hh:charPr[@id="'.$char.'"]')->item(0);
            $copy=$old->cloneNode(true);$newid=(string)$hx->query('//hh:charPr')->length;
            $copy->setAttribute('id',$newid);$copy->setAttribute('textColor','#000000');$copy->setAttribute('height','1000');
            $charList->appendChild($copy);$charList->setAttribute('itemCnt',(string)$hx->query('//hh:charPr')->length);$styles[$char]=$newid;
        }
        $char=$styles[$char];
        $base=$p->cloneNode(false);
        while($list->firstChild)$list->removeChild($list->firstChild);
        // Retain paragraph and character style; drop stale line geometry for reflow.
        foreach(explode("\n",str_replace("\r",'',(string)$value)) as $line){
            $q=$base->cloneNode(false);$q->setAttribute('id','0');
            $rr=$dom->createElementNS($ns,'hp:run');$rr->setAttribute('charPrIDRef',$char?:'0');
            $t=$dom->createElementNS($ns,'hp:t');$t->appendChild($dom->createTextNode($line));
            $rr->appendChild($t);$q->appendChild($rr);$list->appendChild($q);
        }
    };
    $category=$data['account_type']??'공동활용';
    $cell=$xp->query('./hp:tr/hp:tc[hp:cellAddr[@rowAddr="1" and @colAddr="2"]]',$table)->item(0);
    $text='';foreach($xp->query('.//hp:t',$cell) as $t)$text.=$t->textContent;
    $text=preg_replace_callback('/[□■]\\s*(운영자|전용|공동활용)/u',fn($m)=>($m[1]===$category?'■':'□').' '.$m[1],$text);
    $write(1,2,$text);
    $write(2,2,$data['organization'].' / '.$data['department']);
    foreach(['position'=>0,'name'=>2,'email'=>4,'phone'=>8] as $k=>$c)$write(5,$c,$data[$k]);
    $fields=[];foreach(hpc_domains() as $v)$fields[]=(in_array($v,$data['domains'],true)?'■':'□').$v;
    $write(6,2,implode('  ',array_slice($fields,0,5))."\n".implode('  ',array_slice($fields,5)));
    $write(8,2,$data['project'].($data['project_no']?' ('.$data['project_no'].')':''));
    $write(9,2,$data['purpose']);$write(10,2,$data['method']);
    $write(12,1,'2027. 1. 1. ~ 2027. 6. 30.');
    $write(12,5,$data['nodes'].' 노드');$write(12,9,$data['disk'].' GB');
    $write(13,2,$data['programs']);$write(15,2,'(1안) '.$data['account1'].'  (2안) '.$data['account2']);
    foreach([16,17] as $row){
        $cell=$xp->query('./hp:tr/hp:tc[hp:cellAddr[@rowAddr="'.$row.'" and @colAddr="2"]]',$table)->item(0);
        $old='';foreach($xp->query('.//hp:t',$cell) as $t)$old.=$t->textContent;
        $write($row,2,!empty($data[$row===16?'privacy':'security'])?preg_replace('/□\s*동의/u','■ 동의',$old,1):$old);
    }
    $date=new DateTimeImmutable($data['date']);
    $write(18,0,$date->format('Y').' 년 '.$date->format('n').' 월 '.$date->format('j')." 일\n신청인  ".$data['name']."                 (날인)\n확인자(부서장)  ".$data['approver'].'                 (날인)');
    foreach($xp->query('//hp:t') as $t){
        if(str_contains($t->textContent,'사용료 부과 여부')){
            $p=$t->parentNode->parentNode;
            $pr=$hx->query('//hh:paraPr[@id="'.$p->getAttribute('paraPrIDRef').'"]')->item(0)->cloneNode(true);
            $prid=(string)$hx->query('//hh:paraPr')->length;$pr->setAttribute('id',$prid);
            foreach($pr->getElementsByTagNameNS('http://www.hancom.co.kr/hwpml/2011/core','prev') as $m)$m->setAttribute('value','1000');
            foreach($pr->getElementsByTagNameNS('http://www.hancom.co.kr/hwpml/2011/core','intent') as $m)$m->setAttribute('value','0');
            $prs=$hx->query('//hh:paraProperties')->item(0);$prs->appendChild($pr);$prs->setAttribute('itemCnt',(string)$hx->query('//hh:paraPr')->length);$p->setAttribute('paraPrIDRef',$prid);
            $nodes=iterator_to_array($xp->query('.//hp:t',$p));
            foreach($nodes as $n)$n->nodeValue='';
            $nodes[0]->nodeValue='* 사용료 부과 여부 : '.($data['fee']==='paid'?'■':'□').' 유상사용 신청    '.($data['fee']==='free'?'■':'□').' 무상사용 신청';
            foreach(iterator_to_array($xp->query('./hp:linesegarray',$p)) as $n)$p->removeChild($n);
            break;
        }
    }
    $zip->addFromString('Contents/header.xml',$header->saveXML());
    $zip->addFromString('Contents/section0.xml',$dom->saveXML());
    // Do not leave a stale preview of the blank application inside the filled file.
    $zip->deleteName('Preview/PrvImage.png');
    $text='';foreach($xp->query('//hp:t') as $t)$text.=$t->textContent."\n";
    $zip->addFromString('Preview/PrvText.txt',$text);$zip->close();
}
function hpc_document($app,$format){
    if(!in_array($format,['hwpx','hwp','pdf'],true))throw new RuntimeException('출력 형식 오류');
    $template=hpc_app_template($app);$source=hpc_template_file($template);
    $converter=hpc_converter_config();
    $dir=hpc_private_dir('generated/'.$app->id.'-'.substr(hash('sha256',$app->data.'|'.$template->sha.'|'.$template->config.'|'.wp_json_encode($converter)),0,16).'-r11');
    if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('저장 공간 오류');
    $file=$dir.'/application.'.$format;
    $lock=fopen($dir.'/generation.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('문서 생성 잠금 실패');
    try { if(!is_file($file)){
        $hwpx=$dir.'/application.hwpx';
        if(!is_file($hwpx)){
            $pending=$dir.'/pending.hwpx';
            try{if($template->engine==='legacy')hpc_fill_hwpx(json_decode($app->data,true),$pending,$source);elseif($template->engine==='tokens')hpc_fill_tokens(json_decode($app->data,true),$pending,$template);else hpc_fill_mapped(json_decode($app->data,true),$pending,$template);if(!rename($pending,$hwpx))throw new RuntimeException('문서 저장 실패');}
            catch(Throwable $e){if(is_file($pending))unlink($pending);throw $e;}
        }
        if($format!=='hwpx'){
            if(!function_exists('proc_open')||!is_executable($converter['bin']))throw new RuntimeException('이 서버에서는 문서 변환 도구를 실행할 수 없습니다. 호스팅의 프로세스 실행 권한을 확인해 주세요.');
            foreach(['proc_get_status','proc_terminate','proc_close'] as $fn)if(!function_exists($fn))throw new RuntimeException('문서 변환에 필요한 프로세스 제어 기능이 제한되어 있습니다.');
            $bin=$converter['bin'];
            $temp=$dir.'/pending.'.$format;
            $args=$format==='hwp'?[$bin,'convert',$hwpx,$temp]:[$bin,'export-pdf',$hwpx,'-o',$temp,'--font-path',$converter['fonts'],'--fallback-sans',$converter['sans'],'--fallback-serif',$converter['serif'],'--fallback-mono',$converter['mono']];
            $proc=proc_open($args,[0=>['pipe','r'],1=>['file',$dir.'/convert.log','a'],2=>['file',$dir.'/convert.log','a']],$pipes);
            if(!is_resource($proc))throw new RuntimeException('변환 도구 실행 실패');
            fclose($pipes[0]);$deadline=microtime(true)+90;$code=-1;
            // Bounded execution without /usr/bin/timeout: shared hosting may limit
            // filesystem access to the account home. No shell interpolation is used.
            while(true){$status=proc_get_status($proc);if(!$status['running']){$code=$status['exitcode'];break;}if(microtime(true)>=$deadline){proc_terminate($proc,9);break;}usleep(100000);}
            $closed=proc_close($proc);if($code===-1&&$closed>=0)$code=$closed;
            if($code!==0||!is_file($temp)||filesize($temp)<100){if(is_file($temp))unlink($temp);throw new RuntimeException('문서 변환 실패 또는 시간 초과. 관리자에게 알려주세요.');}
            if(!rename($temp,$file))throw new RuntimeException('문서 저장 실패');
        }
    }}finally{flock($lock,LOCK_UN);fclose($lock);}
    return $file;
}
