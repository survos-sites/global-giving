<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Input\ResearchInput;
use App\Research\Ein;
use App\Research\ProPublicaClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class ResearchService
{
    public function __construct(private ProPublicaClient $client, private EntityManagerInterface $em) {}

    #[AsCommand('app:research', 'Match nonprofit research using exact US EIN identifiers')]
    public function research(SymfonyStyle $io, #[MapInput] ResearchInput $input): int
    {
        $query = $this->em->createQueryBuilder()->select('o')->from(Organization::class, 'o')->where('o.ein IS NOT NULL');
        if ($input->organization !== null) { $query->andWhere('o.id = :id')->setParameter('id', $input->organization); }
        else { $query->andWhere('o.researchedAt IS NULL'); }
        $matched = 0;
        $attempted = 0;
        foreach ($query->orderBy('o.id')->getQuery()->toIterable() as $org) {
            if (!$org->normalizedEin) { continue; }
            $result = $this->client->lookup(Ein::fromString($org->normalizedEin));
            ++$attempted;
            $org->research = $result;
            $org->researchedAt = new \DateTimeImmutable();
            $this->em->flush();
            if ($result !== null) { ++$matched; $io->writeln($org->id.': '.$org->name.' — matched EIN '.$org->normalizedEin); }
            $this->em->detach($org);
            if ($attempted >= $input->limit) { break; }
        }
        $io->success(sprintf('%d exact EIN matches from %d lookups. Source: ProPublica / IRS.', $matched, $attempted));
        return Command::SUCCESS;
    }
}
