<?php

namespace App\Support;

/** One destination of the home screen and of the "Telas" menu. */
final readonly class Screen
{
    public function __construct(
        public string $key,
        public string $url,
        public string $label,
        public string $description,
        public string $icon,
        public ?string $badge = null,
    ) {}
}
