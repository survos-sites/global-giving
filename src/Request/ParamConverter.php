<?php

namespace App\Request;

use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\Song;
use App\Entity\Theme;
use App\Entity\Video;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ParamConverter implements ValueResolverInterface
{
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        // get the argument type (e.g. BookingId)
        $argumentType = $argument->getType();

        switch ($argumentType) {
            case Organization::class:
            case Project::class:
                $repository = $this->entityManager->getRepository($argumentType);
                $value = $request->attributes->get('songId');
                $song = $repository->findOneBy(['id' => $value]);
                return [$song];
            case Theme::class:
                $repository = $this->entityManager->getRepository($argumentType);
                $value = $request->attributes->get('videoId');
                if (!is_string($value)) {
                    return [];
                }
                // Try to find video by its uniqueParameters.  Inspect the class to get this
                return [$repository->findOneBy(['youtubeId' => $value])];

        }

        return [];
    }

    public function __construct(
        private EntityManagerInterface $entityManager
    )
    {
    }


}
