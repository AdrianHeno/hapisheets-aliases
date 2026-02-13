<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AliasRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly AliasRepository $aliasRepository,
        private readonly string $aliasDomain = 'hapisheets.com',
    ) {
    }

    #[Route('', name: 'app_home', methods: ['GET'])]
    #[Route('/dashboard', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in to view the dashboard.');
        }

        $aliases = $this->aliasRepository->findByUserOrderByCreatedAtDesc($user);

        return $this->render('dashboard/index.html.twig', [
            'aliases' => $aliases,
            'alias_domain' => $this->aliasDomain,
        ]);
    }
}
