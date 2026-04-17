<?php

declare(strict_types=1);

namespace App\Queue\Concerns;

use App\Queue\Attributes\Backoff;
use App\Queue\Attributes\FailOnTimeout;
use App\Queue\Attributes\Timeout;
use App\Queue\Attributes\Tries;
use ReflectionClass;

/**
 * Reads PHP 8.3 queue-configuration attributes from the job class and
 * exposes their values as the public properties that Laravel's queue
 * worker inspects at runtime ($tries, $backoff, $timeout, $failOnTimeout).
 *
 * Include this trait in any job that uses #[Tries], #[Backoff],
 * #[Timeout], or #[FailOnTimeout] attributes.
 *
 * Laravel reads these four properties directly from the job instance,
 * so the trait simply bridges the attribute values to those properties
 * inside the constructor chain via initQueueAttributes().
 *
 * Usage:
 *   class MyJob implements ShouldQueue
 *   {
 *       use InteractsWithQueueAttributes;
 *
 *       public function __construct()
 *       {
 *           $this->initQueueAttributes();
 *       }
 *   }
 */
trait InteractsWithQueueAttributes
{
    /** Maximum attempt count — read by Laravel queue worker. */
    public int $tries = 1;

    /** Seconds between retries — read by Laravel queue worker. */
    public int $backoff = 0;

    /** Maximum execution seconds — read by Laravel queue worker. */
    public int $timeout = 60;

    /** Whether a timeout counts as a failure — read by Laravel queue worker. */
    public bool $failOnTimeout = false;

    /**
     * Populate the standard Laravel queue properties from class-level
     * PHP 8.3 attributes.
     *
     * Call this from the job's __construct() method.
     */
    protected function initQueueAttributes(): void
    {
        $reflection = new ReflectionClass(static::class);

        foreach ($reflection->getAttributes(Tries::class) as $attr) {
            /** @var Tries $instance */
            $instance = $attr->newInstance();
            $this->tries = $instance->value;
        }

        foreach ($reflection->getAttributes(Backoff::class) as $attr) {
            /** @var Backoff $instance */
            $instance = $attr->newInstance();
            $this->backoff = $instance->seconds;
        }

        foreach ($reflection->getAttributes(Timeout::class) as $attr) {
            /** @var Timeout $instance */
            $instance = $attr->newInstance();
            $this->timeout = $instance->seconds;
        }

        if (!empty($reflection->getAttributes(FailOnTimeout::class))) {
            $this->failOnTimeout = true;
        }
    }
}
