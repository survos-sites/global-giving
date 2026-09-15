<?php

declare(strict_types=1);

namespace App\Input;

use Symfony\Component\Console\Attribute\Option;

final class LabInput
{
    #[Option('Number of active projects in the local vector experiment (1–1000)')]
    public int $limit = 200 { set { if ($value < 1 || $value > 1000) { throw new \InvalidArgumentException('Lab sample must be from 1 to 1000 projects.'); } $this->limit = $value; } }
}
