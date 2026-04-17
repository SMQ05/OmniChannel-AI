<?php

declare(strict_types=1);

namespace App\Voice\Null;

use App\Voice\Contracts\TextToSpeechInterface;

class NullTextToSpeech implements TextToSpeechInterface
{
    public function synthesize(string $text): string
    {
        return '';
    }
}
