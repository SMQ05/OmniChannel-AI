<?php

declare(strict_types=1);

namespace App\Queue\Attributes;

use Attribute;

/**
 * Declares the maximum number of times a queued job may be attempted
 * before it is moved to the failed jobs table.
 *
 * Usage:
 *   #[Tries(3)]
 *   class MyJob implements ShouldQueue { … }
 *
 * The companion trait InteractsWithQueueAttributes reads this value
 * and exposes it via the standard $tries property that Laravel expects.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Tries
{
    /**
     * @param  int  $value  Maximum attempt count (must be >= 1)
     */
    public function __construct(
        public readonly int $value,
    ) {}
}
