<?php

declare(strict_types=1);

namespace App\Voice\Null;

use App\Voice\Contracts\LlmStreamInterface;

class NullLlmStream implements LlmStreamInterface
{
    public function stream(array $messages): iterable
    {
        yield '';
    }
}
