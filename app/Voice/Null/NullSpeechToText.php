<?php

declare(strict_types=1);

namespace App\Voice\Null;

use App\Voice\Contracts\SpeechToTextInterface;

class NullSpeechToText implements SpeechToTextInterface
{
    public function transcribe(string $audioReference): string
    {
        return '';
    }
}
