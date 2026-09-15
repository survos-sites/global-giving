<?php

declare(strict_types=1);

namespace App\Research;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class Ein
{
    public function __construct(#[Assert\Regex('/^\d{9}$/')] public string $value)
    {
        if (!preg_match('/^\d{9}$/D', $value)) { throw new \InvalidArgumentException('EIN must contain exactly nine digits.'); }
    }

    public static function fromString(string $value): self { return new self(str_replace(['-', ' '], '', $value)); }
}
