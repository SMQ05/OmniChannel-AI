<?php

declare(strict_types=1);

namespace App\Voice\Contracts;

interface LlmStreamInterface
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return iterable<string>
     */
    public function stream(array $messages): iterable;
}
