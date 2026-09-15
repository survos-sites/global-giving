<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;

final class CatalogSerializerFactory
{
    /** Bulk jobs must not retain every record in the developer profiler. */
    public static function create(): Serializer
    {
        return new Serializer([new ObjectNormalizer(new ClassMetadataFactory(new AttributeLoader()))]);
    }
}
