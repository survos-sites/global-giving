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
#[EntityMeta(icon: 'tabler:world-heart', label: 'Projects', group: 'GlobalGiving')]
#[RouteIdentity(field: 'id')]
#[ApiResource(operations: [new Get(), new GetCollection()], normalizationContext: ['groups' => ['public']])]
#[MeiliIndex(name: 'projects', primaryKey: 'id', autoIndex: false)]
final class Project implements RouteParametersInterface
{
    use RouteIdentityTrait;
    public function __construct(#[ORM\Id, ORM\Column(type: 'bigint')] #[Field(sortable: true, order: 0)] #[Groups(['public'])] public readonly int $id) {}

    #[ORM\Column(type: 'text')] #[Field(searchable: true, sortable: true, order: 1)] #[Groups(['public'])]
    public string $title = '';
    #[ORM\Column(type: 'text', nullable: true)] #[Field(searchable: true)] #[Groups(['public'])] public ?string $summary = null;
    #[ORM\Column(type: 'text', nullable: true)] #[Field(searchable: true)] #[Groups(['public'])] public ?string $need = null;
    #[ORM\Column(type: 'text', nullable: true)] #[Field(searchable: true)] #[Groups(['public'])] public ?string $activities = null;
    #[ORM\Column(type: 'text', nullable: true)] #[Field(searchable: true)] #[Groups(['public'])] public ?string $longTermImpact = null;
    #[ORM\Column] #[Field(filterable: true, facet: true)] #[Groups(['public'])] public bool $active = false;
    #[ORM\Column(length: 64, nullable: true)] #[Field(filterable: true, facet: true)] #[Groups(['public'])] public ?string $status = null;
    #[ORM\Column(length: 3, nullable: true)] #[Field(filterable: true, facet: true)] #[Groups(['public'])] public ?string $countryCode = null;
    #[ORM\Column(type: 'json')] #[Field(filterable: true, facet: true)] #[Groups(['public'])] public array $countries = [];
    #[ORM\Column(type: 'json')] #[Field(filterable: true, facet: true)] #[Groups(['public'])] public array $themes = [];
    #[ORM\Column(nullable: true)] #[Field(sortable: true, format: 'currency')] #[Groups(['public'])] public ?float $funding = null;
    #[ORM\Column(nullable: true)] #[Field(sortable: true, format: 'currency')] #[Groups(['public'])] public ?float $goal = null;
    #[ORM\Column(nullable: true)] #[Groups(['public'])] public ?float $latitude = null;
    #[ORM\Column(nullable: true)] #[Groups(['public'])] public ?float $longitude = null;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['public'])] public ?string $imageUrl = null;
    #[ORM\Column(type: 'text', nullable: true)] #[Groups(['public'])] public ?string $projectUrl = null;
    #[ORM\Column(length: 64, nullable: true)] #[Groups(['public'])] public ?string $modifiedDate = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(nullable: true)] public ?Organization $organization = null;
    #[ORM\Column(type: 'json')] public array $source = [];
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $importedAt;
    #[ORM\Column(length: 64)] public string $importRun = '';

    #[Field(searchable: true, filterable: true, facet: true)] #[Groups(['public'])]
    public ?string $organizationName { get => $this->organization?->name; }
    #[Groups(['public'])] public ?int $organizationId { get => $this->organization?->id; }
    #[Groups(['public'])] public ?float $fundedPercent { get => $this->goal > 0 ? round(100 * ($this->funding ?? 0) / $this->goal, 1) : null; }

    public string $embeddingText {
        get => implode("\n\n", array_filter([$this->title, $this->summary, $this->need, $this->activities, $this->longTermImpact, implode(', ', $this->themes), implode(', ', $this->countries)]));
    }

    public function update(\Survos\GlobalGiving\Dto\Project $dto, ?Organization $org, \DateTimeImmutable $time, string $run): void
    {
        foreach (['title', 'summary', 'need', 'activities', 'longTermImpact', 'active', 'status', 'countryCode', 'countries', 'funding', 'goal', 'latitude', 'longitude', 'imageUrl', 'projectUrl', 'modifiedDate', 'source'] as $field) { $this->$field = $dto->$field; }
        $this->organization = $org;
        $this->themes = array_values($dto->themes);
        $this->importedAt = $time;
        $this->importRun = $run;
    }
}
