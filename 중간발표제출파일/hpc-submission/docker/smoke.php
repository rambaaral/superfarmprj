<?php
require '/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
$results=[];
$results['core']=$wp_version;
$results['php']=PHP_VERSION;
$results['plugins_installed']=count(get_plugins());
$results['plugins_active']=count(get_option('active_plugins'));
$results['theme']=get_stylesheet();
$results['users']=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
$results['applications']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.hpc_table());
$results['templates']=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.hpc_template_table());
$results['mail']=wp_mail('smoke@example.test','Submission mail check','Local delivery test.');
$a=hpc_template_test_app(hpc_active_template());
foreach(['hwpx','hwp','pdf'] as $format){$file=hpc_document($a,$format);$results['document_'.$format]=['bytes'=>filesize($file),'sha256'=>hash_file('sha256',$file)];}
update_option('hpco_probe_requested',1);hpco_tick();
$results['scan_probe']=get_option('hpco_probe');
$results['cron_tick']=get_option('hpco_last_tick');
echo json_encode($results,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
if($results['plugins_installed']!==16||$results['plugins_active']!==11||!$results['mail'])exit(1);
