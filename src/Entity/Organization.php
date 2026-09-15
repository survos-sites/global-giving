<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Survos\FieldBundle\Attribute\EntityMeta;
use Survos\FieldBundle\Attribute\Field;
use Survos\FieldBundle\Attribute\RouteIdentity;
use Survos\FieldBundle\Entity\RouteIdentityTrait;
use Survos\FieldBundle\Entity\RouteParametersInterface;
use Survos\MeiliBundle\Metadata\MeiliIndex;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[EntityMeta(icon: 'tabler:building-community', label: 'Organizations', group: 'GlobalGiving')]
#[RouteIdentity(field: 'id')]
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['public']])]
#[MeiliIndex(name: 'organizations', primaryKey: 'id', autoIndex: false)]
final class Organization implements RouteParametersInterface
{
    use RouteIdentityTrait;

    public function __construct(#[ORM\Id, ORM\Column(type: 'bigint')] #[Field(sortable: true, order: 0)] #[Groups(['public'])] public readonly int $id) {}

    #[ORM\Column(length: 1024)] #[Field(searchable: true, sortable: true, order: 1)] #[Groups(['public'])]
    public string $name = '';
    #[ORM\Column(type: 'text', nullable: true)] #[Field(searchable: true)] #[Groups(['public'])]
    public ?string $mission = null;
    #[ORM\Column(length: 32, nullable: true)] #[Field(searchable: true, filterable: true)] #[Groups(['public'])]
    public ?string $ein = null;
    #[ORM\Column(length: 3, nullable: true)] #[Field(filterable: true, facet: true)] #[Groups(['public'])]
    public ?string $countryCode = null;
    #[ORM\Column(type: 'json')] #[Field(filterable: true, facet: true)] #[Groups(['public'])]
    public array $countries = [];
    #[ORM\Column(type: 'json')] #[Field(filterable: true, facet: true)] #[Groups(['public'])]
    public array $themes = [];
    #[ORM\Column] #[Field(sortable: true)] #[Groups(['public'])]
    public int $totalProjects = 0;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['public'])] public ?string $logoUrl = null;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['public'])] public ?string $url = null;
    #[ORM\Column(type: 'json')] public array $source = [];
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $importedAt;
    #[ORM\Column(type: 'json', nullable: true)] public ?array $research = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] public ?\DateTimeImmutable $researchedAt = null;

    public ?string $normalizedEin {
        get {
            $value = str_replace(['-', ' '], '', $this->ein ?? '');
            return strlen($value) === 9 && ctype_digit($value) ? $value : null;
        }
    }

    public ?string $researchUrl {
        get => $this->normalizedEin ? 'https://projects.propublica.org/nonprofits/organizations/'.$this->normalizedEin : null;
    }

    public function update(\Survos\GlobalGiving\Dto\Organization $dto, \DateTimeImmutable $time): void
    {
        foreach (['name', 'mission', 'countryCode', 'countries', 'totalProjects', 'logoUrl', 'url', 'source'] as $field) { $this->$field = $dto->$field; }
        // Embedded project organizations may omit EIN; preserve the organization export's identifier.
        if ($dto->ein !== null) { $this->ein = $dto->ein; }
        $this->themes = array_values($dto->themes);
        $this->importedAt = $time;
    }
}
