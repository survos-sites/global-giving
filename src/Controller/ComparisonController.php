<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ComparisonService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ComparisonController extends AbstractController
{
    public function __construct(private readonly ComparisonService $lab, #[Autowire('%kernel.environment%')] private readonly string $environment) {}

    #[Route('/lab/compare', name: 'lab_compare', methods: ['GET', 'POST'])]
    public function compare(Request $request): Response
    {
        // Local research tooling: enable public evaluation only after adding user accounts.
        if ($this->environment === 'prod') { throw $this->createNotFoundException(); }
        $session = $request->getSession();
        if (!$session->has('labToken')) { $session->set('labToken', bin2hex(random_bytes(24))); }
        $token = $session->get('labToken');
        if ($request->isMethod('POST') && !hash_equals($token, $request->request->getString('token'))) { throw $this->createAccessDeniedException('Invalid form token.'); }
        $query = trim($request->query->getString('q'));
        $mode = $request->query->getString('mode', 'hybrid');
        $country = strtoupper(trim($request->query->getString('country')));
        if (!in_array($mode, ['keyword', 'vector', 'hybrid'], true) || mb_strlen($query) > 300 || ($country !== '' && !preg_match('/^[A-Z]{2,3}$/D', $country))) { return new Response('Invalid search parameters.', 400); }
        $run = null; $runId = $request->query->getString('run'); $error = null; $manifest = null;
        try {
            $manifest = $this->lab->manifest();
            if ($request->isMethod('POST')) {
                if ($request->request->getString('action') === 'judge') {
                    $runId = $request->request->getString('run');
                    $this->lab->judge($runId, $request->request->getInt('project'), $request->request->getInt('grade', -1));
                } else {
                    $run = $session->get('labRun');
                    if (!is_array($run)) { throw new \InvalidArgumentException('Search before saving a comparison.'); }
                    $runId = $this->lab->saveRun($run);
                }
                return $this->redirectToRoute('lab_compare', ['run' => $runId]);
            }
            if ($runId !== '') {
                $run = $this->lab->run($runId);
                if ($run === []) { throw new \InvalidArgumentException('Saved comparison not found.'); }
                $query = $run['query']; $mode = $run['mode']; $country = $run['country'];
            } elseif ($query !== '') { $run = $this->lab->compare($query, $mode, $country); $session->set('labRun', $run); }
        } catch (\RuntimeException|\InvalidArgumentException $e) { $error = $e->getMessage(); }
        $shared = [];
        if ($run && empty($run['results']['meili']['error']) && empty($run['results']['elastic']['error'])) {
            $meiliRanks = array_flip(array_column($run['results']['meili']['hits'], 'id'));
            foreach ($run['results']['elastic']['hits'] as $rank => $hit) {
                if (isset($meiliRanks[$hit['id']])) { $shared[$hit['id']] = ['meili' => $meiliRanks[$hit['id']] + 1, 'elastic' => $rank + 1]; }
            }
        }
        return $this->render('lab/compare.html.twig', ['shared' => $shared, 'query' => $query, 'mode' => $mode, 'country' => $country, 'run' => $run, 'runId' => $runId, 'manifest' => $manifest, 'error' => $error, 'token' => $token, 'saved' => $this->lab->saved(), 'grades' => $run ? $this->lab->judgments($run) : []]);
    }
}
