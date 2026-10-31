<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Modules\ModuleLibrary\Schema\SchemaMarkup\Graphs;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;



/**
 * ProductReview graph class.
 */
class ProductReview extends Product\Product {
    /**
     * Returns the graph data.
     *
     * @param object|null $graphData The graph data.
     * @return array             The parsed graph data.
     * @throws Exception
     *
     */
	public function get( $graphData = null ): array {
		$this->graphData = $graphData;

		$data = $this->getCommonGraphData( $graphData );
		$data = $this->getDataWithOfferPriceData( $data, $graphData );

		// Just one review is allowed, so convert the list to a single review.
		$data['review'] = $data['review'][0] ?? [];

		return $data;
	}
}