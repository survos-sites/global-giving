<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Organization;
use App\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Survos\MeiliBundle\Service\MeiliService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AppController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly MeiliService $meili, #[\Symfony\Component\DependencyInjection\Attribute\Autowire('%env(MEILI_LAB_SEARCH_KEY)%')] private readonly string $labSearchKey) {}

    #[Route('/', name: 'app_homepage')]
    public function home(): Response
    {
        return $this->render('app/home.html.twig', [
            'projectCount' => $this->em->getRepository(Project::class)->count([]),
            'activeCount' => $this->em->getRepository(Project::class)->count(['active' => true]),
            'organizationCount' => $this->em->getRepository(Organization::class)->count([]),
            'projects' => $this->em->getRepository(Project::class)->findBy(['active' => true], ['id' => 'DESC'], 6),
        ]);
    }

    #[Route('/projects', name: 'browse_projects')]
    public function projects(): Response { return $this->search('projects'); }

    #[Route('/organizations', name: 'browse_organizations')]
    public function organizations(): Response { return $this->search('organizations'); }

    #[Route('/lab', name: 'browse_lab')]
    public function lab(): Response { return $this->search('lab'); }

    private function search(string $base): Response
    {
        return $this->render('browse/search.html.twig', [
            'base' => $base, 'serverUrl' => $this->meili->getHost(),
            'apiKey' => $base === 'lab' ? $this->labSearchKey : $this->meili->getPublicApiKey(),
            'indexUid' => $this->meili->uidForRaw($base),
            'templateUrl' => $this->generateUrl('meili_template', ['templateName' => $base === 'lab' ? 'projects' : $base]),
        ]);
    }

    #[Route('/project/{projectId}', name: 'project_show', requirements: ['projectId' => '\d+'])]
    public function project(Project $project): Response { return $this->render('app/project.html.twig', ['project' => $project]); }

    #[Route('/organization/{organizationId}', name: 'organization_show', requirements: ['organizationId' => '\d+'])]
    public function organization(Organization $organization): Response
    {
        return $this->render('app/organization.html.twig', [
            'organization' => $organization,
            'projects' => $this->em->getRepository(Project::class)->findBy(['organization' => $organization], ['active' => 'DESC', 'id' => 'DESC'], 50),
        ]);
    }
}
