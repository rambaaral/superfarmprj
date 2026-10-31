<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs\WebPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ContactPage graph class.
 */
class ContactPage extends WebPage {
	/**
	 * The graph type.
	 *
	 * @var string
	 */
	protected $type = 'ContactPage';
}