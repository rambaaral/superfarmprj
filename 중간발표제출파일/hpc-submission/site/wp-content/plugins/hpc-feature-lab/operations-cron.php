<?php
// Dedicated cPanel CLI entry point. No HTTP execution or request parameters.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('DOING_CRON',true);
require dirname(__DIR__,3).'/wp-load.php';
if(!function_exists('hpco_tick'))exit(1);
hpco_tick();
if(function_exists('hpcw_worker'))hpcw_worker();
