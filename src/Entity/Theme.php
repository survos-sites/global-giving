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
#[EntityMeta(icon: 'tabler:tags', group: 'GlobalGiving')]
#[RouteIdentity(field: 'code')]
#[ApiResource(operations: [new Get(), new GetCollection()])]
final class Theme implements RouteParametersInterface
{
    use RouteIdentityTrait;
    public function __construct(#[ORM\Id, ORM\Column(length: 32)] #[Field(sortable: true)] public readonly string $code) {}
    #[ORM\Column(length: 255)] #[Field(searchable: true, sortable: true)] public string $label = '';
}
