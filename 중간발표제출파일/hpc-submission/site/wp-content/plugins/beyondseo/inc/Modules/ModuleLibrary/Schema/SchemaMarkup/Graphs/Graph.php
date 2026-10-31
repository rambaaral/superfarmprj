<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The base graph class.
 */
abstract class Graph {

	/**
	 * The graph data to overwrite.
	 *
	 * @var array
	 */
	protected static $overwriteGraphData = [];

	/**
	 * Returns the graph data.
	 *
	 * @param object|null $graphData Optional custom graph data configuration.
	 * @return array Schema structured data array.
	 */
	abstract public function get( $graphData = null ): array;

	/**
	 * Iterates over a list of functions and sets the results as graph data.
	 *
	 * @param  array $data          The graph data to add to.
	 * @param  array $dataFunctions List of functions to loop over, associated with a graph property.
	 * @return array $data          The graph data with the results added.
	 */
	protected function getData( $data, $dataFunctions ) {
		foreach ( $dataFunctions as $k => $f ) {
			if ( ! method_exists( $this, $f ) ) {
				continue;
			}

			$value = $this->$f();
			if ( $value || in_array( $k, [
                'price',       // Needs to be 0 if free for Software Application.
                'ratingValue', // Needs to be 0 for 0-star ratings.
                'value',       // Needs to be 0 if free for product shipping details.
                'minValue',    // Needs to be 0 for product delivery time.
                'maxValue'     // Needs to be 0 for product delivery time.
            ], true ) ) {
				$data[ $k ] = $value;
			}
		}

		return $data;
	}

	/**
	 * Decodes a multiselect field and returns the values.
	 *
	 * @param  string $json The JSON encoded multiselect field.
	 * @return array        The decoded values.
	 */
	protected function extractMultiselectTags( $json ) {
		$tags = is_string( $json ) ? json_decode( $json ) : [];
		if ( ! $tags ) {
			return [];
		}

		return wp_list_pluck( $tags, 'value' );
	}

	/**
	 * Merges in data from our addon plugins.
	 *
	 * @param  array $data The graph data.
	 * @return array       The graph data.
	 */
	protected function getAddonData( $data, $className, $methodName = 'getAdditionalGraphData' ) {
		return $data;
	}

	/**
	 * A way to overwrite the graph data.
	 *
	 * @param  array $data The data to overwrite.
	 * @return void
	 */
	public static function setOverwriteGraphData( $data ) {
		self::$overwriteGraphData[ static::class ] = $data;
	}
}