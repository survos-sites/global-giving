<?php

declare(strict_types=1);

namespace App\Input;

use Symfony\Component\Console\Attribute\Option;

final class ResearchInput
{
    #[Option('Maximum organizations to look up per run')]
    public int $limit = 10 { set { if ($value < 1 || $value > 100) { throw new \InvalidArgumentException('Choose a limit from 1 to 100.'); } $this->limit = $value; } }
    #[Option('Only look up this GlobalGiving organization ID')]
    public ?int $organization = null;
}
