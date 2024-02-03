<?php

namespace App\Controller;

use Survos\GlobalGivingBundle\Service\GlobalGivingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Annotation\Route;

class AppController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function home(GlobalGivingService $globalGivingService): Response
    {
        $data = $globalGivingService->getFeaturedProjects();
        return $this->render('app/index.html.twig', [
            'data' => $data['projects']
        ]);
    }

    #[Cache('1 week')]
    public function fetchData()
    {

    }
}
