<?php

declare(strict_types=1);

namespace App\Queue\Attributes;

use Attribute;

/**
 * Declares the delay (in seconds) between retry attempts for a queued job.
 *
 * Usage:
 *   #[Backoff(60)]
 *   class MyJob implements ShouldQueue { … }
 *
 * The companion trait InteractsWithQueueAttributes reads this value
 * and exposes it via the standard $backoff property that Laravel expects.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Backoff
{
    /**
     * @param  int  $seconds  Seconds to wait before the next retry attempt
     */
    public function __construct(
        public readonly int $seconds,
    ) {}
}
