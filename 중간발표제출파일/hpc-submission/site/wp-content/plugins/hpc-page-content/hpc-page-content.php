<?php
/**
 * Plugin Name: 센터 페이지 내용 편집
 * Description: 기존 화면 구성을 보존하면서 페이지의 문구와 링크를 편집합니다.
 * Version: 1.0.1
 */
if (!defined('ABSPATH')) exit;
add_action('init', function () {
    wp_register_script('hpc-page-content', plugins_url('editor.js', __FILE__), ['wp-blocks','wp-element','wp-components','wp-block-editor','wp-data','wp-api-fetch','wp-plugins','wp-editor'], '1.0.1', true);
    wp_register_style('hpc-page-content', plugins_url('editor.css', __FILE__), [], '1.0.1');
    register_block_type(__DIR__, ['editor_script'=>'hpc-page-content','editor_style'=>'hpc-page-content']);
});
add_action('enqueue_block_editor_assets', function () {
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'page') return;
    wp_enqueue_script('hpc-page-content');
    wp_enqueue_style('hpc-page-content');
});
add_action('rest_api_init', function () {
    register_rest_route('hpc-page-content/v1', '/faq/(?P<id>\d+)', [
        'methods'=>'POST',
        'permission_callback'=>function($r) {
            $p=get_post((int)$r['id']);
            return $p && $p->post_type==='hkc_faq' && current_user_can('manage_options') && current_user_can('edit_post',$p->ID);
        },
        'callback'=>'hpcpc_save_faq',
        'args'=>[
            'title'=>['type'=>'string','required'=>true],
            'content'=>['type'=>'string','required'=>true],
            'expected_title'=>['type'=>'string','required'=>true],
            'expected_content'=>['type'=>'string','required'=>true]
        ]
    ]);
});
function hpcpc_save_faq($r) {
    $p=get_post((int)$r['id']);
    if (!$p || $p->post_type!=='hkc_faq' || $p->post_status!=='publish') return new WP_Error('faq_missing','공개된 FAQ가 아닙니다. 새로고침해 주세요.',['status'=>409]);
    if ($p->post_title!==$r['expected_title'] || $p->post_content!==$r['expected_content']) return new WP_Error('faq_changed','다른 곳에서 이 FAQ를 수정했습니다. 새로고침 후 다시 편집해 주세요.',['status'=>409]);
    $title=sanitize_text_field($r['title']);$content=sanitize_textarea_field($r['content']);
    if ($title==='' || $content==='') return new WP_Error('faq_empty','질문과 답변을 모두 입력해 주세요.',['status'=>400]);
    $result=wp_update_post(wp_slash(['ID'=>$p->ID,'post_title'=>$title,'post_content'=>$content]),true);
    if(is_wp_error($result))return $result;
    $saved=get_post($p->ID);
    return ['title'=>$saved->post_title,'content'=>$saved->post_content,'modified'=>$saved->post_modified_gmt];
}

/** Migration is explicit (CLI only), never run on ordinary page views. */
function hpcpc_convert_blocks($blocks) {
    foreach ($blocks as &$block) {
        if ($block['blockName'] === 'core/html') {
            // Preserve the HTML byte-for-byte, including existing CSS and functional controls.
            $block['blockName'] = 'hpc/page-content';
        } elseif ($block['blockName'] === 'core/shortcode' && preg_match('/^\s*\[(hpc_usage_rules|hpc_security_programs)\]\s*$/', $block['innerHTML'], $m)) {
            if (!shortcode_exists($m[1])) throw new RuntimeException('Missing source shortcode: '.$m[1]);
            $html = do_shortcode('['.$m[1].']');
            if (strlen(trim(wp_strip_all_tags($html))) < 30) throw new RuntimeException('Empty source content');
            $block = ['blockName'=>'hpc/page-content','attrs'=>[], 'innerBlocks'=>[], 'innerHTML'=>$html,'innerContent'=>[$html]];
        }
    }
    return $blocks;
}
