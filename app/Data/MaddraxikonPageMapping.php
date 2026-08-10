<?php

namespace App\Data;

final readonly class MaddraxikonPageMapping
{
    public function __construct(
        public int $pageId,
        public string $pageTitle,
    ) {}
}
