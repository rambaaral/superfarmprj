<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main-content">본문 바로가기</a>
<div class="gov_top"><div class="inner"><p class="gov_desc">슈퍼컴퓨팅센터 시험 운영 사이트입니다. 실제 접수는 진행하지 않습니다.</p></div></div>
<header id="header" class="site-header">
<div class="wrap"><div class="logoWrap"><a id="logo" class="brand" href="<?php echo esc_url(hp_url('home')); ?>" aria-label="슈퍼컴퓨팅센터 메인페이지"><span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span><small>SUPERCOMPUTING CENTER</small><strong>슈퍼컴퓨팅센터</strong></span></a></div>
<ul id="topMenu"><li><a href="<?php echo esc_url(hp_url('my-page')); ?>">마이페이지</a></li><?php if(current_user_can('manage_options')): ?><li><a href="<?php echo esc_url(admin_url()); ?>">관리자</a></li><?php endif; ?><li><?php if(is_user_logged_in()): ?><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>">로그아웃</a><?php else: ?><a href="<?php echo esc_url(wp_login_url(hp_url('my-page'))); ?>">로그인</a><?php endif; ?></li><?php if(!is_user_logged_in() && function_exists('hpcm_url')): ?><li><a href="<?php echo esc_url(hpcm_url()); ?>">회원가입</a></li><?php endif; ?></ul>
<button class="search-open" type="button" data-open-search>메뉴 검색</button>
<button class="mobile-menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false"><span aria-hidden="true">☰</span> 메뉴</button></div>
<nav id="primary-navigation" aria-label="상단 메뉴"><div id="gnb"><ul class="primary-links">

<?php $group_index=0;foreach(hp_groups() as $group=>$keys):$group_index++;$id='nav-group-'.$group_index; ?>
<li><button class="group-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($id); ?>"><?php echo esc_html(str_replace('·실습','',$group)); ?></button>
<div class="depth2" id="<?php echo esc_attr($id); ?>"><ul><?php foreach(hp_navigation_items($keys) as $key=>$entry): ?><li><a href="<?php echo esc_url($entry['url']); ?>" <?php if(hp_current()===$key)echo 'aria-current="page"'; ?>><?php echo esc_html($entry['title']); ?><span aria-hidden="true">›</span></a></li><?php endforeach; ?></ul><div class="nav-description"><strong><?php echo esc_html($group); ?></strong><p>원하는 메뉴를 선택해 주세요.</p></div></div></li>
<?php endforeach; ?></ul></div></nav>
</header>
<dialog class="menu-search-dialog" aria-labelledby="menu-search-title"><div class="section-heading"><h2 id="menu-search-title">메뉴 검색</h2><button type="button" data-close-search aria-label="메뉴 검색 닫기">✕</button></div><label for="menu-search-input">찾고 싶은 메뉴를 입력하세요</label><input type="search" id="menu-search-input" placeholder="예: 신청, 교육"><ul><?php foreach(hp_pages() as $key=>$label): ?><li><a href="<?php echo esc_url(hp_url($key)); ?>"><?php echo esc_html($label); ?> <span aria-hidden="true">→</span></a></li><?php endforeach; ?></ul><p data-search-empty hidden>일치하는 메뉴가 없습니다.</p></dialog>
