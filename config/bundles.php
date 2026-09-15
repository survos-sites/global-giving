<?php
declare(strict_types=1);

return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => ['all' => true],
    ApiPlatform\Symfony\Bundle\ApiPlatformBundle::class => ['all' => true],
    EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle::class => ['all' => true],
    Symfony\UX\StimulusBundle\StimulusBundle::class => ['all' => true],
    Symfony\UX\TwigComponent\TwigComponentBundle::class => ['all' => true],
    Symfony\UX\Icons\UXIconsBundle::class => ['all' => true],
    Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    Knp\Bundle\MenuBundle\KnpMenuBundle::class => ['all' => true],
    Survos\Kit\SurvosKitBundle::class => ['all' => true],
    Survos\AtlasBundle\SurvosAtlasBundle::class => ['all' => true],
    Survos\FieldBundle\SurvosFieldBundle::class => ['all' => true],
    Survos\JsonlBundle\SurvosJsonlBundle::class => ['all' => true],
    Survos\JsTwigBundle\SurvosJsTwigBundle::class => ['all' => true],
    Survos\MeiliBundle\SurvosMeiliBundle::class => ['all' => true],
    Survos\ApiGridBundle\SurvosApiGridBundle::class => ['all' => true],
    Survos\TablerBundle\SurvosTablerBundle::class => ['all' => true],
    Survos\GlobalGivingBundle\SurvosGlobalGivingBundle::class => ['all' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
];
