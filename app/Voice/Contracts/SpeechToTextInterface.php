<?php

declare(strict_types=1);

namespace App\Voice\Contracts;

interface SpeechToTextInterface
{
    public function transcribe(string $audioReference): string;
}
