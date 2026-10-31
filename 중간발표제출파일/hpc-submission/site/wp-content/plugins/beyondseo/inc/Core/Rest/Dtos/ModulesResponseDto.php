<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Rest\Dtos;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Class ModulesResponseDto
 * @property string[] $modules
 */
class ModulesResponseDto {

	/**
     * @var string[] $modules
	 * The list of available modules.
     */
	public array $modules = [];
}
