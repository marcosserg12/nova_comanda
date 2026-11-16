<?php

namespace App\Support;

use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

final class QrCode
{
    public static function dataUri(string $url): string
    {
        return (new Generator(new QROptions(['outputBase64' => true])))->render($url);
    }
}
