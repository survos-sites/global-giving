<?php

namespace App\Controller;

use Survos\GlobalGivingBundle\Service\GlobalGivingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

class AppController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function home(GlobalGivingService $globalGivingService): Response
    {

//        $ids = $globalGivingService->getAllProjectsIds()['project'];
        $data = $globalGivingService->getFeaturedProjects();
        return $this->render('app/index.html.twig', [
            'projects' => $data['project']
        ]);
    }

    #[Cache('1 week')]
    public function fetchData()
    {

    }

    public function ggApiCalls()
    {
        return [
            ''
        ];

    }
}
