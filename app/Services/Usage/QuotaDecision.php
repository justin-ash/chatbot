<?php

namespace App\Services\Usage;

final readonly class QuotaDecision
{
    public function __construct(
        public bool $allowed,
        public string $source, // 'free', 'paid', 'exhausted'
        public int $freeRemaining,
        public int $paidRemaining,
        public ?string $reason = null,
    ) {}
}
