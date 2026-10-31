<?php
/* Submission-only adapter: all mail is captured by Mailpit. */
if(!defined('ABSPATH'))exit;
add_filter('pre_wp_mail',function($result,$atts){
    require_once ABSPATH.WPINC.'/PHPMailer/Exception.php';
    require_once ABSPATH.WPINC.'/PHPMailer/PHPMailer.php';
    require_once ABSPATH.WPINC.'/PHPMailer/SMTP.php';
    $mail=new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();$mail->Host='mailpit';$mail->Port=1025;
        $mail->SMTPAuth=false;$mail->SMTPAutoTLS=false;$mail->CharSet='UTF-8';
        $mail->setFrom('portal@example.test','HPC Local Portal');
        foreach((array)$atts['to'] as $to){foreach(explode(',',$to) as $address)$mail->addAddress(trim($address));}
        $mail->Subject=$atts['subject'];$mail->Body=$atts['message'];
        $headers=is_array($atts['headers'])?implode("\n",$atts['headers']):(string)$atts['headers'];
        if(stripos($headers,'text/html')!==false)$mail->isHTML(true);
        foreach((array)($atts['attachments']??[]) as $file){if($file)$mail->addAttachment($file);}
        return $mail->send();
    }catch(Throwable $e){error_log('Local mail failed: '.$e->getMessage());return false;}
},PHP_INT_MAX,2);
