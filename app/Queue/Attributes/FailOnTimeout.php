<?php

declare(strict_types=1);

namespace App\Queue\Attributes;

use Attribute;

/**
 * Marks a queued job as failed immediately upon timeout, rather than
 * allowing it to be retried. When this attribute is present the job's
 * $failOnTimeout property is set to true by InteractsWithQueueAttributes.
 *
 * Usage:
 *   #[FailOnTimeout]
 *   class MyJob implements ShouldQueue { … }
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class FailOnTimeout
{
    // Marker attribute — no constructor parameters required.
}
