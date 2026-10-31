<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB\Migrations;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use RankingCoach\Inc\Core\DB\AbstractMigration;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\Base\BaseConstants;

/**
 * Migration to move legacy SEO meta data from postmeta to the dedicated rankingcoach_metatags table.
 */
class M20260707000000_MigrateLegacyMetaToMetaTagsTable extends AbstractMigration
{
    private const BATCH_SIZE = 100;

    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool
    {
        global $wpdb;

        if (!$this->dbManager->tableExists(DatabaseTablesManager::DATABASE_MOD_METATAGS)) {
            return true;
        }

        $metatagsTable = $this->getTableName(DatabaseTablesManager::DATABASE_MOD_METATAGS);
        $postmetaTable = $wpdb->postmeta;

        // Get all post IDs that have any of our legacy keys OR Yoast keys
        $offset = 0;
        while (true) {
            $posts = $wpdb->get_results($wpdb->prepare(
                "SELECT DISTINCT post_id FROM $postmetaTable 
                 WHERE meta_key IN (
                    'rankingcoach_seo_title', 'rankingcoach_seo_title_template',
                    'rankingcoach_seo_description', 'rankingcoach_seo_description_template',
                    'rankingcoach_primary_keyword', 'rankingcoach_secondary_keywords',
                    'rankingcoach_social_title', 'rankingcoach_social_description',
                    '_yoast_wpseo_title', '_yoast_wpseo_metadesc'
                 )
                 ORDER BY post_id ASC
                 LIMIT %d OFFSET %d",
                self::BATCH_SIZE,
                $offset
            ));

            if (empty($posts)) {
                break;
            }

            foreach ($posts as $post) {
                $postId = (int) $post->post_id;
                $this->migratePost($postId);
            }

            $offset += self::BATCH_SIZE;
        }

        return true;
    }

    /**
     * Migrate all tags for a specific post
     *
     * @param int $postId
     */
    private function migratePost(int $postId): void
    {
        // Title
        $this->migrateTag($postId, 'title', [
            'rankingcoach_seo_title_template',
            'rankingcoach_seo_title',
            '_yoast_wpseo_title'
        ]);

        // Description
        $this->migrateTag($postId, 'description', [
            'rankingcoach_seo_description_template',
            'rankingcoach_seo_description',
            '_yoast_wpseo_metadesc'
        ]);

        // Social Title
        $this->migrateTag($postId, 'social_title', [
            'rankingcoach_social_title'
        ]);

        // Social Description
        $this->migrateTag($postId, 'social_description', [
            'rankingcoach_social_description'
        ]);

        // Keywords
        $this->migrateKeywords($postId);
    }

    /**
     * Migrate a specific tag type using fallback keys
     *
     * @param int $postId
     * @param string $type
     * @param array $keys
     */
    private function migrateTag(int $postId, string $type, array $keys): void
    {
        // Check if already exists in new table
        $existing = $this->dbManager->table(DatabaseTablesManager::DATABASE_MOD_METATAGS)
            ->where('post_id', $postId)
            ->where('type', $type)
            ->first();

        if ($existing) {
            return;
        }

        $value = '';
        foreach ($keys as $key) {
            $value = get_post_meta($postId, $key, true);
            if (!empty($value)) {
                break;
            }
        }

        if (empty($value)) {
            return;
        }

        $this->dbManager->insert(DatabaseTablesManager::DATABASE_MOD_METATAGS, [
            'post_id'  => $postId,
            'type'     => $type,
            'template' => (string) $value,
        ]);
    }

    /**
     * Migrate keywords to the new table format (JSON)
     *
     * @param int $postId
     */
    private function migrateKeywords(int $postId): void
    {
        $existing = $this->dbManager->table(DatabaseTablesManager::DATABASE_MOD_METATAGS)
            ->where('post_id', $postId)
            ->where('type', 'keywords')
            ->first();

        if ($existing) {
            return;
        }

        $primary = get_post_meta($postId, 'rankingcoach_primary_keyword', true);
        $secondary = get_post_meta($postId, 'rankingcoach_secondary_keywords', true);

        if (empty($primary) && empty($secondary)) {
            return;
        }

        $secondaryArray = [];
        if (is_string($secondary)) {
            $secondaryArray = array_filter(array_map('trim', explode(',', $secondary)));
        } elseif (is_array($secondary)) {
            $secondaryArray = $secondary;
        }

        $keywordsData = [
            'primaryKeyword'     => $primary ?: '',
            'additionalKeywords' => array_values($secondaryArray),
        ];

        $this->dbManager->insert(DatabaseTablesManager::DATABASE_MOD_METATAGS, [
            'post_id'  => $postId,
            'type'     => 'keywords',
            'template' => wp_json_encode($keywordsData),
        ]);
    }

    /**
     * Reverse the migration
     *
     * @return bool Success status
     */
    public function down(): bool
    {
        // We generally don't delete migrated data in rollback to prevent accidental loss
        // of data that might have been updated since migration.
        return true;
    }

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Migrate legacy SEO meta data and Yoast data to the new MetaTags table';
    }
}
