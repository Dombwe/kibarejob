<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if ('json' === $request->getPreferredFormat()) {
            return new Response(json_encode([
                'message' => 'Vous n’avez pas les droits requis pour accéder à cette ressource.',
            ], JSON_THROW_ON_ERROR), Response::HTTP_FORBIDDEN, [
                'Content-Type' => 'application/json',
            ]);
        }

        if (!$request->isXmlHttpRequest()) {
            return new Response($this->twig->render('security/access_denied.html.twig', [
                'requestedPath' => $request->getPathInfo(),
                'homeUrl' => $this->urlGenerator->generate('home'),
                'recruiterUrl' => $this->urlGenerator->generate('recruiter_entry'),
                'logoutUrl' => $this->urlGenerator->generate('app_logout'),
            ]), Response::HTTP_FORBIDDEN);
        }

        return new RedirectResponse($this->urlGenerator->generate('recruiter_entry'));
    }
}
