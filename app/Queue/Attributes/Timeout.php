<?php

declare(strict_types=1);

namespace App\Queue\Attributes;

use Attribute;

/**
 * Declares the maximum number of seconds a queued job may run
 * before it is considered to have timed out.
 *
 * Usage:
 *   #[Timeout(30)]
 *   class MyJob implements ShouldQueue { … }
 *
 * The companion trait InteractsWithQueueAttributes reads this value
 * and exposes it via the standard $timeout property that Laravel expects.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Timeout
{
    /**
     * @param  int  $seconds  Maximum execution time in seconds
     */
    public function __construct(
        public readonly int $seconds,
    ) {}
}
