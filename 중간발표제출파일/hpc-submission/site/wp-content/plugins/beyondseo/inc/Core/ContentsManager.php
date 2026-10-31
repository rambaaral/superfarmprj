<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core;

if ( !defined('ABSPATH') ) {
    exit;
}

use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Modules\ModuleLibrary\Technical\MetaTags\MetaTags;
use RankingCoach\Inc\Modules\ModuleManager;
use RankingCoach\Inc\Traits\SingletonTrait;
use Throwable;
use WP_Post;

/**
 * Class ContentsManager
 */
class ContentsManager {

    use SingletonTrait;

    /** Column key of the assistant column in the posts and pages list tables. */
    public const ASSISTANT_COLUMN = 'rc_assistant';

    /** Words of post text used as the description fallback of the search preview. */
    private const FALLBACK_DESCRIPTION_WORDS = 30;

    /**
     * Add the content assistant column to the posts and pages list.
     * @return void
     */
    public function addRankingCoachColumnOnTables(): void {
        add_filter('manage_posts_columns', [$this, 'registerAssistantColumn']);
        add_filter('manage_pages_columns', [$this, 'registerAssistantColumn']);
        add_action('manage_posts_custom_column', [$this, 'populateColumn'], 10, 2);
        add_action('manage_pages_custom_column', [$this, 'populateColumn'], 10, 2);
        register_deactivation_hook(__FILE__, [$this, 'cleanup']);
    }

    /**
     * Add the content assistant column to the posts and pages list.
     *
     * @param array $columns
     *
     * @return array
     */
    public function registerAssistantColumn(array $columns): array {
        /* translators: %s: brand name of the plugin */
        $columns[self::ASSISTANT_COLUMN] = sprintf(esc_html__('%s Assistant', 'beyondseo'), RANKINGCOACH_BRAND_NAME);
        return $columns;
    }

    /**
     * Populate the content assistant column with data.
     *
     * Renders the mounting point of the React SeoScoreCell. Everything the cell needs to
     * show the search snippet right away and to open the quick editor (SEO title,
     * meta description, their structured templates, links and permissions) is passed
     * as data attributes, so the list fires no extra REST request per row; only the
     * score is fetched by the cell itself.
     *
     * @param string $column_name
     * @param int $post_id
     *
     * @return void
     */
    public function populateColumn( string $column_name, int $post_id): void {
        if ($column_name !== self::ASSISTANT_COLUMN) {
            return;
        }

        $snippet = $this->getSearchSnippet($post_id);

        $attributes = [
            'id'                            => 'rc-react-postcell-' . $post_id,
            'class'                         => 'rc-react-postcell',
            'data-id'                       => (string) $post_id,
            'data-post-type'                => (string) (get_post_type($post_id) ?: ''),
            'data-post-title'               => $snippet['postTitle'],
            'data-permalink'                => (string) (get_permalink($post_id) ?: ''),
            'data-edit-link'                => (string) (get_edit_post_link($post_id, 'raw') ?: ''),
            'data-can-edit'                 => $this->canQuickEditSnippet($post_id) ? '1' : '0',
            'data-seo-title'                => $snippet['title'],
            'data-seo-title-template'       => (string) wp_json_encode($snippet['titleTemplate']),
            'data-seo-description'          => $snippet['description'],
            'data-seo-description-template' => (string) wp_json_encode($snippet['descriptionTemplate']),
            'data-fallback-description'     => $snippet['fallbackDescription'],
        ];

        $html = '<div';
        foreach ($attributes as $name => $value) {
            $html .= ' ' . $name . '="' . esc_attr((string) $value) . '"';
        }
        $html .= '></div>';

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attribute names are fixed, every value is escaped above.
    }

    /**
     * Whether the current user may change the SEO title and description of a post from the list.
     *
     * Mirrors RestManager::checkMetatagsPermission() and adds the post-level edit capability,
     * so the quick editor is only offered when the save request would be accepted.
     *
     * @param int $post_id
     *
     * @return bool
     */
    private function canQuickEditSnippet(int $post_id): bool {
        return current_user_can('edit_post', $post_id)
            && current_user_can('manage_options');
    }

    /**
     * Resolved SEO title and meta description of a post, plus the structured templates they
     * come from and a description fallback built from the post text.
     *
     * Any failure (module not loaded, storage error) degrades to the post title with an empty
     * description, so the list keeps rendering.
     *
     * @param int $post_id
     *
     * @return array{postTitle: string, title: string, titleTemplate: array, description: string, descriptionTemplate: array, fallbackDescription: string}
     */
    private function getSearchSnippet(int $post_id): array {
        $post      = get_post($post_id);
        $postTitle = $post instanceof WP_Post ? html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8') : '';

        $snippet = [
            'postTitle'           => $postTitle,
            'title'               => $postTitle,
            'titleTemplate'       => [],
            'description'         => '',
            'descriptionTemplate' => [],
            'fallbackDescription' => $post instanceof WP_Post ? $this->buildFallbackDescription($post) : '',
        ];

        if (!$post instanceof WP_Post) {
            return $snippet;
        }

        try {
            $metaTagsModule = $this->getMetaTagsModule();
            if (!$metaTagsModule instanceof MetaTags) {
                return $snippet;
            }

            $data = $metaTagsModule->getTitleAndDescriptionData($post_id);

            $titleTemplate       = is_array($data['title']['template'] ?? null) ? $data['title']['template'] : [];
            $descriptionTemplate = is_array($data['description']['template'] ?? null) ? $data['description']['template'] : [];

            $snippet['titleTemplate']       = $titleTemplate;
            $snippet['descriptionTemplate'] = $descriptionTemplate;
            $snippet['title']               = $this->resolveSnippetTemplate($post_id, $titleTemplate) ?: $postTitle;
            $snippet['description']         = $this->resolveSnippetTemplate($post_id, $descriptionTemplate);
        } catch (Throwable $e) {
            // Keep the column usable: the score cell still renders and the editor starts from the post title.
        }

        return $snippet;
    }

    /**
     * The MetaTags module. Modules are normally loaded on `plugins_loaded`; when that has not
     * happened in the current request (e.g. CLI or an early call) they are loaded once here.
     *
     * @return MetaTags|null
     */
    private function getMetaTagsModule(): ?MetaTags {
        static $loadAttempted = false;

        $moduleManager = ModuleManager::instance();
        $module        = $moduleManager->get_module(MetaTags::MODULE_NAME);

        if (!$module instanceof MetaTags && !$loadAttempted) {
            $loadAttempted = true;
            $moduleManager->load_modules();
            $module = $moduleManager->get_module(MetaTags::MODULE_NAME);
        }

        return $module instanceof MetaTags ? $module : null;
    }

    /**
     * Resolve a structured template (text / variable / separator elements) into plain text
     * for the given post.
     *
     * @param int $post_id
     * @param array $template
     *
     * @return string
     */
    private function resolveSnippetTemplate(int $post_id, array $template): string {
        if (empty($template)) {
            return '';
        }

        $encoded = wp_json_encode($template);
        if (!is_string($encoded)) {
            return '';
        }

        return html_entity_decode(WordpressHelpers::resolveTemplate($post_id, $encoded), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Plain-text summary of the post used by the search preview when no meta description is set.
     *
     * @param WP_Post $post
     *
     * @return string
     */
    private function buildFallbackDescription(WP_Post $post): string {
        $source = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
        $text   = wp_strip_all_tags(strip_shortcodes(excerpt_remove_blocks($source)), true);

        return wp_trim_words(html_entity_decode($text, ENT_QUOTES, 'UTF-8'), self::FALLBACK_DESCRIPTION_WORDS, '…');
    }

    /**
     * Cleanup the plugin on deactivation.
     *
     * @return void
     */
    public function cleanup(): void {
        remove_filter('manage_posts_columns', [$this, 'registerAssistantColumn']);
        remove_filter('manage_pages_columns', [$this, 'registerAssistantColumn']);
        remove_action('manage_posts_custom_column', [$this, 'populateColumn']);
        remove_action('manage_pages_custom_column', [$this, 'populateColumn']);
    }

	/**
	 * Get content insights for the site.
	 *
	 * @return array
	 */
	public function get_contents_data(): array {
		// Retrieve counts for different content types
		$total_posts = wp_count_posts()->publish ?? 0;
		$total_pages = wp_count_posts('page')->publish ?? 0;

		// Example custom post-type insights (adjust for actual CPTs)
		$custom_post_types = [];
		$post_types = get_post_types(['_builtin' => false]);
		foreach ($post_types as $post_type) {
			$custom_post_types[$post_type] = wp_count_posts($post_type)->publish ?? 0;
		}

		// Publishing trends
		$published_last_week = count(get_posts([
			'post_type' => 'post',
			'date_query' => [
				'after' => gmdate('Y-m-d', strtotime('-7 days'))
			],
			'posts_per_page' => -1,
			'fields' => 'ids'
		]));
		$published_last_month = count(get_posts([
			'post_type' => 'post',
			'date_query' => [
				'after' => gmdate('Y-m-d', strtotime('-30 days'))
			],
			'posts_per_page' => -1,
			'fields' => 'ids'
		]));

		// Content status breakdown
		$status_counts = [];
		$post_statuses = ['publish', 'draft', 'pending', 'trash'];
		foreach ($post_statuses as $status) {
			$status_counts[$status] = wp_count_posts()->{$status} ?? 0;
		}

		return [
			'total_posts' => $total_posts,
			'total_pages' => $total_pages,
			'custom_post_types' => $custom_post_types,
			'published_last_7_days' => $published_last_week,
			'published_last_30_days' => $published_last_month,
			'status_counts' => $status_counts
		];
	}

}
