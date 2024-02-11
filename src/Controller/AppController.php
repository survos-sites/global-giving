<?php

namespace App\Controller;

use Composer\Factory;
use Composer\IO\NullIO;
use Survos\GlobalGivingBundle\Service\GlobalGivingService;
use Symfony\Bridge\Twig\Attribute\Template;
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

    #[Route('/gg-bundle-browse', name: 'gg_bundle_browse')]
    #[Template('app/bundles.html.twig')]
    public function localBundleBrowse()
    {
        $composer = Factory::create(new NullIO(), './../composer.json', false);
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        return [
            'packages' => $localRepo->getPackages()
        ];

        foreach ($localRepo->getPackages() as $package) {
            dd($package, $localRepo);
            echo $package->getName() . PHP_EOL;
            echo $package->getVersion() . PHP_EOL;
            echo $package->getType() . PHP_EOL;
            // ...
        }

    }
}
