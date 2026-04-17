<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ChannelConfigurationException extends ChannelDeliveryException
{
    public function __construct(string $message)
    {
        parent::__construct($message, false);
    }
}
