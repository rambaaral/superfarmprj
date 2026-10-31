<?php
declare(strict_types=1);

namespace RankingCoach\Inc\Traits;

if ( !defined('ABSPATH') ) {
    exit;
}

use RuntimeException;

trait SingletonTrait
{
    private static array $instances = [];

    public static function getInstance(): static
    {
        $class = static::class;
        
        if (!isset(self::$instances[$class])) {
            self::$instances[$class] = new static();
        }
        
        return self::$instances[$class];
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot deserialize singleton');
    }

    protected function __clone() {}
}
