<?php

declare(strict_types=1);

namespace App\Input;

use Survos\GlobalGiving\Enum\Export;
use Symfony\Component\Console\Attribute\Option;

final class ImportInput
{
    #[Option('Export to fetch: activeProjects, projects, or organizations')]
    public Export $dataset = Export::ActiveProjects;
    #[Option('Maximum records to import; zero imports the complete snapshot')]
    public int $limit = 0 { set { if ($value < 0) { throw new \InvalidArgumentException('Limit must be nonnegative.'); } $this->limit = $value; } }
    #[Option('Fetch a fresh export even if a snapshot is less than one day old')]
    public bool $refresh = false;
    #[Option('Use an existing XML file instead of downloading')]
    public ?string $file = null;
}
