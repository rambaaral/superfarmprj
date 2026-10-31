<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if (!defined('ABSPATH')) {
    exit;
}

use RankingCoach\Inc\Core\DB\AbstractMigration;

class M20260928000000_RemoveLegacyCapabilities extends AbstractMigration
{
    private const LEGACY_CORE_CAPABILITIES = [
        'rankingcoach_import_categories',
        'rankingcoach_read_website_pages',
        'rankingcoach_read_plugin_data',
        'rankingcoach_read_modules_list',
        'rankingcoach_read_account_details',
        'rankingcoach_read_location_keywords',
        'rankingcoach_read_module_data',
        'rankingcoach_made_onboarding',
        'rankingcoach_api_documentation',
        'rankingcoach_api_integration',
        'rankingcoach_api_sync',
        'rankingcoach_api_admin',
        'rankingcoach_api_config',
        'rankingcoach_api_metatags',
        'rankingcoach_api_content_analysis',
        'rankingcoach_api_onboarding',
        'rankingcoach_api_plugin_info',
        'rankingcoach_api_optimiser',
        'rankingcoach_api_social',
        'rankingcoach_manage_settings',
        'rankingcoach_read_breadcrumbs',
        'rankingcoach_factor_assign_keywords',
        'rankingcoach_factor_content_quality_and_length',
        'rankingcoach_factor_content_readability',
        'rankingcoach_factor_first_paragraph_keyword_usage',
        'rankingcoach_factor_header_tags_structure',
        'rankingcoach_factor_local_keywords_in_content',
        'rankingcoach_factor_meta_description_format_optimization',
        'rankingcoach_factor_meta_description_keywords',
        'rankingcoach_factor_meta_title_format_optimization',
        'rankingcoach_factor_meta_title_keywords',
        'rankingcoach_factor_page_content_keywords',
        'rankingcoach_factor_analyze_backlink_profile',
        'rankingcoach_factor_fix_broken_links_on_page',
        'rankingcoach_factor_alt_text_to_images',
        'rankingcoach_factor_image_optimization',
        'rankingcoach_factor_optimize_page_speed',
        'rankingcoach_factor_optimize_url_structure',
        'rankingcoach_factor_schema_markup',
        'rankingcoach_factor_search_engine_indexation',
        'rankingcoach_factor_use_canonical_tags',
    ];

    private const LEGACY_MODULES = [
        'metaTags',
        'customSidebar',
        'linkCounter',
        'redirectManager',
        'linkAnalyzer',
        'rankingcoachDashboard',
        'contentAnalysis',
        'schemaMarkup',
    ];

    public function up(): bool
    {
        foreach (['administrator', 'editor', 'author'] as $roleName) {
            $role = get_role($roleName);
            if (!$role) {
                continue;
            }

            foreach (self::LEGACY_CORE_CAPABILITIES as $capability) {
                if ($role->has_cap($capability)) {
                    $role->remove_cap($capability);
                }
            }
        }

        foreach (['administrator', 'editor', 'author', 'contributor'] as $roleName) {
            $role = get_role($roleName);
            if (!$role) {
                continue;
            }

            foreach (self::LEGACY_MODULES as $module) {
                foreach (['rankingcoach_read_', 'rankingcoach_write_'] as $prefix) {
                    $capability = $prefix . $module;
                    if ($role->has_cap($capability)) {
                        $role->remove_cap($capability);
                    }
                }
            }
        }

        return true;
    }

    public function down(): bool
    {
        return true;
    }

    public function getDescription(): string
    {
        return 'Remove legacy plugin capabilities from the roles to which the plugin granted them';
    }
}
