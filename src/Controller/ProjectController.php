<?php

namespace App\Controller;

use Survos\GlobalGivingBundle\Service\GlobalGivingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProjectController extends AbstractController
{
    #[Route('/project/{projectId}', name: 'project_show', options: ['expose' => true])]
    public function project(string $projectId, GlobalGivingService $globalGivingService): Response
    {
        $data = $globalGivingService->getProject($projectId);

        return $this->render('project/show.html.twig', [
            'project' => $data
        ]);
    }
}
