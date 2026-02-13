<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Alias;
use App\Entity\User;
use App\Repository\AliasRepository;
use App\Service\AliasGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/aliases')]
#[IsGranted('ROLE_USER')]
class AliasController extends AbstractController
{
    public function __construct(
        private readonly AliasGenerator $aliasGenerator,
        private readonly AliasRepository $aliasRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $aliasDomain = 'hapisheets.com',
    ) {
    }

    #[Route('/new', name: 'app_alias_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in to create an alias.');
        }

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!$this->isCsrfTokenValid('create_alias', $token)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $localPart = $this->aliasGenerator->generate();
            $alias = new Alias();
            $alias->setUser($user);
            $alias->setLocalPart($localPart);

            $this->entityManager->persist($alias);
            $this->entityManager->flush();

            $this->addFlash('success', $localPart . '@' . $this->aliasDomain);

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('alias/new.html.twig', [
            'alias_domain' => $this->aliasDomain,
        ]);
    }

    #[Route('/{id}/disable', name: 'app_alias_disable', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function disable(int $id, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('You must be logged in.');
        }

        $alias = $this->aliasRepository->find($id);
        if ($alias === null || $alias->getUser()?->getId() !== $user->getId()) {
            throw new NotFoundHttpException('Alias not found.');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('disable_alias', $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $alias->setEnabled(false);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Alias %s@%s has been disabled.', $alias->getLocalPart(), $this->aliasDomain));

        $referer = $request->headers->get('Referer');
        if ($referer && $request->getHost() === parse_url($referer, PHP_URL_HOST)) {
            return $this->redirect($referer, Response::HTTP_SEE_OTHER);
        }
        return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
    }
}
