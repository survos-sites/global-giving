<?php

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use Symfony\Bridge\Twig\Attribute\Template;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/browse')]
class BrowseController extends AbstractController
{
    #[Route('/projects', name: 'browse_projects')]
    #[Template('browse/projects.html.twig')]
    public function projects(): array
    {
        return [
            'class' => Project::class,
            'apiRoute' => 'meili-projects'
        ];
    }

    #[Route('/orgs', name: 'browse_orgs')]
    #[Template('browse/orgs.html.twig')]
    public function orgs(): array
    {
        return [
            'class' => Organization::class,
            'apiRoute' => 'meili-orgs'
        ];
    }

}
