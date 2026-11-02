<?php
/**
 * Plugin Name: 센터 콘텐츠 관리
 * Description: 공지·일정·알림판·FAQ의 자료 등록, 편집 검증과 공통 조회 기능.
 * Version: 1.0.0
 * Requires PHP: 8.2
 */
defined('ABSPATH') || exit;
require_once __DIR__.'/content-types.php';
require_once __DIR__.'/content-data.php';
require_once __DIR__.'/content-admin.php';
require_once __DIR__.'/faq-admin.php';
// Featured-image editing for banners must not depend on the presentation theme.
add_action('after_setup_theme',function(){
 $support=get_theme_support('post-thumbnails');
 if(!$support)add_theme_support('post-thumbnails',['hpc_banner']);
 elseif(isset($support[0])&&is_array($support[0])&&!in_array('hpc_banner',$support[0],true))
  add_theme_support('post-thumbnails',array_merge($support[0],['hpc_banner']));
},30);
