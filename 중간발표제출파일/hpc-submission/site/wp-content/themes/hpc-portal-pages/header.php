<!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main-content"><?php echo esc_html(hpct_text('t04eb17f495281d3d6f27')); ?></a>
<div class="gov_top"><div class="inner"><p class="gov_desc"><?php echo esc_html(hpct_text('tf073249ebcae9914b0d2')); ?></p></div></div>
<header id="header" class="site-header">
<div class="wrap"><div class="logoWrap"><a id="logo" class="brand" href="<?php echo esc_url(hp_url('home')); ?>" aria-label="<?php echo esc_attr(hpct_text('tc08c21df2f26336ce2f7')); ?>"><span class="brand-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span><span><small><?php echo esc_html(hpct_text('site_brand_en')); ?></small><strong><?php echo esc_html(hpct_text('t26bd551efdba9b898efe')); ?></strong></span></a></div>
<ul id="topMenu"><li><a href="<?php echo esc_url(hp_url('my-page')); ?>"><?php echo esc_html(hpct_text('t92926204706ba7315a5c')); ?></a></li><?php if(current_user_can('manage_options')): ?><li><a href="<?php echo esc_url(admin_url()); ?>"><?php echo esc_html(hpct_text('t78496a61deb74f2d0ad4')); ?></a></li><?php endif; ?><li><?php if(is_user_logged_in()): ?><a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php echo esc_html(hpct_text('t919c1b32322583720f00')); ?></a><?php else: ?><a href="<?php echo esc_url(wp_login_url(hp_url('my-page'))); ?>"><?php echo esc_html(hpct_text('t6d253673db906e71d346')); ?></a><?php endif; ?></li><?php if(!is_user_logged_in() && function_exists('hpcm_url')): ?><li><a href="<?php echo esc_url(hpcm_url()); ?>"><?php echo esc_html(hpct_text('te668cc2bec841912922f')); ?></a></li><?php endif; ?></ul>
<button class="search-open" type="button" data-open-search><?php echo esc_html(hpct_text('tc0df0750a68cf9e38429')); ?></button>
<button class="mobile-menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false"><span aria-hidden="true">☰</span><?php echo esc_html(hpct_text('t22b9aee7bc5ce4f35ff0')); ?></button></div>
<nav id="primary-navigation" aria-label="<?php echo esc_attr(hpct_text('taa1bd377d81d50503f1d')); ?>"><div id="gnb"><ul class="primary-links">

<?php foreach(hp_menu_tree('primary') as $node): $item=$node['item'];$id='nav-group-'.$item->ID; ?>
<li><?php if($node['children']): ?><button class="group-toggle" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr($id); ?>"><?php echo esc_html(wp_specialchars_decode($item->title,ENT_QUOTES)); ?></button>
<div class="depth2" id="<?php echo esc_attr($id); ?>"><ul><?php echo hp_menu_list($node['children']); ?></ul><div class="nav-description"><strong><?php echo esc_html(wp_specialchars_decode($item->title,ENT_QUOTES)); ?></strong><p><?php echo esc_html($item->description?:hpct_text('t1d612b9c5c247bd2685c')); ?></p></div></div>
<?php else: echo hp_menu_link($item); endif; ?></li>
<?php endforeach; ?></ul></div></nav>
</header>
<dialog class="menu-search-dialog" aria-labelledby="menu-search-title"><div class="section-heading"><h2 id="menu-search-title"><?php echo esc_html(hpct_text('tc0df0750a68cf9e38429')); ?></h2><button type="button" data-close-search aria-label="<?php echo esc_attr(hpct_text('t1922a3fca8492cb06ce5')); ?>">✕</button></div><label for="menu-search-input"><?php echo esc_html(hpct_text('t13109d642c4a5ee2225b')); ?></label><input type="search" id="menu-search-input" placeholder="<?php echo esc_attr(hpct_text('t918db397f8c3b236d6e8')); ?>"><ul><?php foreach(hp_menu_flat(hp_menu_tree('primary')) as $item): if($item->url==='#'||!$item->url)continue; ?><li><?php echo hp_menu_link($item,'',' <span aria-hidden="true">→</span>'); ?></li><?php endforeach; ?></ul><p data-search-empty hidden><?php echo esc_html(hpct_text('t543af267688a93a7fd9e')); ?></p></dialog>
