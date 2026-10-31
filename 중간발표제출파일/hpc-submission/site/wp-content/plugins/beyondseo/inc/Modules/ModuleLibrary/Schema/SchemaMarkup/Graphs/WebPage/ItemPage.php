<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\WebPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ItemPage graph class.
 */
class ItemPage extends WebPage {
	/**
	 * The graph type.
	 *
	 * This value can be overridden by WebPage child graphs that are more specific.
	 *
	 * @var string
	 */
	protected $type = 'ItemPage';
}