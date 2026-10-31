<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Core\CircuitBreaker;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Circuit Breaker Interface
 * 
 * Defines the contract for circuit breakers that monitor specific
 * plugin dependencies and requirements.
 */
interface CircuitBreakerInterface {
    
    public const SEVERITY_CRITICAL = 'critical';
    public const SEVERITY_WARNING = 'warning';
    
    /**
     * Get unique identifier for this breaker
     */
    public function get_id(): string;
    
    /**
     * Get human-readable name for this breaker
     */
    public function get_name(): string;
    
    /**
     * Check if the monitored dependency is healthy
     */
    public function is_healthy(): bool|int;
    
    /**
     * Get failure message when check fails
     */
    public function get_failure_message(): string;
    
    /**
     * Get severity level (critical blocks functionality, warning shows notice)
     */
    public function get_severity(): string;
    
    /**
     * Get recovery action message for users
     */
    public function get_recovery_action(): string;
    
    /**
     * Get additional context data for debugging
     */
    public function get_context(): array;
}