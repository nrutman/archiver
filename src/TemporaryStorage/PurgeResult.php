<?php

namespace App\TemporaryStorage;

final readonly class PurgeResult
{
    public function __construct(
        public int $removed,
        public int $skippedActive,
        public int $skippedFresh,
    ) {
    }
}
