<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Put;
use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Survos\ApiGrid\Api\Filter\FacetsFieldSearchFilter;
use Survos\ApiGrid\State\MeiliSearchStateProvider;
use Survos\CoreBundle\Entity\RouteParametersInterface;
use Survos\CoreBundle\Entity\RouteParametersTrait;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: OrganizationRepository::class)]

#[ApiResource(
    operations: [new Get(), new Put(), new Delete(), new Patch(),
        new GetCollection(name: 'doctrine-orgs',
            uriTemplate: "doctrine/orgs",

//            provider: MeilliSearchStateProvider::class,
        )],
    shortName: 'org',
    normalizationContext: [
        'groups' => ['org.read', 'rp', 'preview', 'translation'],
    ]
)]
#[GetCollection(
    name: 'meili-orgs',
    uriTemplate: "meili/orgs",
//    uriVariables: ["indexName"],
    provider: MeiliSearchStateProvider::class,
    normalizationContext: [
        'groups' => ['org.read', 'tree', 'rp'],
    ]
)]

#[ApiFilter(SearchFilter::class, properties: ['mission' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['name'])]
#[ApiFilter(FacetsFieldSearchFilter::class,
    properties: ['themes', 'countries']
)]
//#[Groups(['org.read'])]
class Organization implements RouteParametersInterface
{
    use RouteParametersTrait;

    #[ORM\Id]
    #[ORM\Column]
    #[Groups(['org.read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['org.read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['org.read'])]
    private ?string $ein = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['org.read'])]
    private ?int $totalProjects = null;

    #[ORM\OneToMany(mappedBy: 'organization', targetEntity: Project::class, orphanRemoval: true)]
    private Collection $projects;

    #[ORM\Column(nullable: true)]
    private ?array $countries = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $mission = null;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoUrl = null;

    public function __construct(?int $id = null)
    {
        if ($id) {
            $this->id = $id;
        }
        $this->projects = new ArrayCollection();

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEin(): ?string
    {
        return $this->ein;
    }

    public function setEin(?string $ein): static
    {
        $this->ein = $ein;

        return $this;
    }

    public function getTotalProjects(): ?int
    {
        return $this->totalProjects;
    }

    public function setTotalProjects(?int $totalProjects): static
    {
        $this->totalProjects = $totalProjects;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setOrganization($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getOrganization() === $this) {
                $project->setOrganization(null);
            }
        }

        return $this;
    }

    public function getCountries(): ?array
    {
        return $this->countries;
    }

    public function setCountries(?array $countries): static
    {
        $this->countries = $countries;

        return $this;
    }

    public function getMission(): ?string
    {
        return $this->mission;
    }

    public function setMission(?string $mission): static
    {
        $this->mission = $mission;

        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): static
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getLogoUrl(): ?string
    {
        return $this->logoUrl;
    }

    public function setLogoUrl(?string $logoUrl): static
    {
        $this->logoUrl = $logoUrl;

        return $this;
    }
}
