<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\DB;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Interface for database migrations
 */
interface MigrationInterface
{
    /**
     * Run the migration
     *
     * @return bool Success status
     */
    public function up(): bool;

    /**
     * Reverse the migration
     *
     * @return bool Success status
     */
    public function down(): bool;

    /**
     * Get the migration description
     *
     * @return string
     */
    public function getDescription(): string;
}
