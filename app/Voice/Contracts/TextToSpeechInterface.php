<?php

declare(strict_types=1);

namespace App\Voice\Contracts;

interface TextToSpeechInterface
{
    public function synthesize(string $text): string;
}
