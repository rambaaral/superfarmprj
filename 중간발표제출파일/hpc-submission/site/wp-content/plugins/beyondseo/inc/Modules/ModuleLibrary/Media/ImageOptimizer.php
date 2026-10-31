<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Modules\ModuleLibrary\Media;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Modules\ModuleBase\BaseModule;
use RankingCoach\Inc\Modules\ModuleManager;
use ReflectionException;

/**
 * Class ImageOptimizer
 */
class ImageOptimizer extends BaseModule {

	/** @var int $optimizedImagesCount The number of images optimized on the page. */
	protected int $optimizedImagesCount;

	/** @var mixed $optimization_level The level of optimization to apply to images. */
	private mixed $optimization_level = null;

	/** @var mixed $compression_type The type of compression to apply to images. */
	private mixed $compression_type = null;

	/**
	 * ImageOptimizer constructor.
	 *
	 * @param ModuleManager $moduleManager
	 *
	 * @throws ReflectionException
	 */
	public function __construct(ModuleManager $moduleManager)
	{
		$initialization = [
            'active' => false,
			'title' => 'Image Optimizer',
			'description' => 'Optimizes images upon upload or manually to reduce file sizes, improve page load speed, and enhance SEO.  Uses various optimization techniques configurable through settings.',
			'version' => '1.0.0',
			'name' => 'imageOptimizer',
			'priority' => 2,
			'dependencies' => [],
			'settings' => [['key' => 'optimize_on_upload', 'type' => 'boolean', 'default' => true, 'description' => 'Automatically optimize images when they are uploaded to the media library.'], ['key' => 'compression_level', 'type' => 'integer', 'default' => 75, 'description' => 'Sets the compression level for image optimization (0-100, where 100 is the least compression).'], ['key' => 'optimization_method', 'type' => 'string', 'default' => 'lossy', 'description' => 'Choose the image optimization method: \'lossy\' for smaller file sizes (some quality loss) or \'lossless\' for preserving image quality.']],
			'explain' => 'When an image is uploaded, this module automatically reduces its file size using the configured compression level and optimization method (e.g., lossy or lossless). This results in faster page load times and improved SEO performance without significant visual impact. Users can also manually optimize existing images in the media library.'
		];
		parent::__construct($moduleManager, $initialization);

		// Specify properties
		$this->optimizedImagesCount = 0;
	}

	/**
	 * Create necessary SQL tables if they don't already exist.
	 * @param string $table_name
	 * @param string $charset_collate
	 * @return string
	 * @noinspection SqlNoDataSourceInspection
	 */
	protected function getTableSchema(string $table_name, string $charset_collate): string
	{
        if(!$this->isActive()) {
            return '';
        }
		return "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            page_id bigint(20) NOT NULL,
            optimized_images_count int(11) NOT NULL,
            optimization_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
	}

	/**
	 * Registers the hooks for the module.
	 */
	public function initializeModule(): void {
		//add_action( 'save_post', [ $this, 'optimizeImages' ] );
		parent::initializeModule();
	}

	/**
	 * Initializes the module with a set of custom settings.
	 * This method allows the module to configure itself with specific settings.
	 *
	 * @return void
	 */
	public function initializeWithSettings(): void {
		$settings = $this->module_settings;

		// Configure the module based on the provided settings
		if (isset($settings['optimization_level'])) {
			$this->optimization_level = $settings['optimization_level'];
		}
		if (isset($settings['compression_type'])) {
			$this->compression_type = $settings['compression_type'];
		}
	}

	/**
	 * Override to include specific data for ImageOptimizer module.
	 * @return array Custom data specific to ImageOptimizer.
	 */
	public function getData(): array {
		return [
			'optimized_images_count' => $this->optimizedImagesCount,
			'optimization_level'     => $this->optimization_level,
			'compression_type'       => $this->compression_type
		];
	}

	/**
	 * Optimizes images on the page.
	 */
	public function optimizeImages(int $postId): void {
		if (!$this->isModuleInstalled()) {
			return;
		}

		// ignore revisions posts
		if ( wp_is_post_revision( $postId ) ) {
			return;
		}

		// Logic to count and optimize images on the page
		$imageCount = $this->countImages($postId);
		$this->optimizedImagesCount = $imageCount;

		// Update the optimized images count in the database
		$this->insertData($postId);
	}

	/**
	 * Inserts the optimized images count into the database.
	 * @param int $postId
	 */
	protected function insertData(int $postId): void
	{
		$dbManager = DatabaseManager::getInstance();
		$table_name = $this->getTableName();
		
		$data = [
			'page_id' => $postId,
			'optimized_images_count' => $this->optimizedImagesCount,
			'optimization_date' => current_time('mysql')
		];
		
		$dbManager->db()->table($table_name)->insert()->set($data)->get();
	}

	/**
	 * Counts images on the page for optimization purposes.
	 * @param int $postId
	 * @return int Number of images found
	 */
	protected function countImages(int $postId): int
	{
		// Example logic for counting images on a page
		$content = get_post_field('post_content', $postId);
		preg_match_all('/<img[^>]+>/', $content, $matches);

		return count($matches[0]);
	}

	/**
	 * Renders a view displaying the number of optimized images if necessary.
	 */
	public function renderView(): void {
		$message = 'Number of optimized images on this page: ' . $this->getOptimizedImagesCount();
		echo sprintf(
			'<div class="notice rankingcoach-notice notice-info"><p>%s</p></div>',
			esc_html($message)
		);
	}

	/**
	 * Returns the count of optimized images for the current page.
	 * @return int
	 */
	public function getOptimizedImagesCount(): int
	{
		return $this->optimizedImagesCount;
	}
}
