<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', -10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        if ('json' === $request->getPreferredFormat() || str_starts_with($request->getPathInfo(), '/api')) {
            $event->setResponse(new Response(json_encode([
                'message' => 'La ressource demandée est introuvable.',
            ], JSON_THROW_ON_ERROR), Response::HTTP_NOT_FOUND, [
                'Content-Type' => 'application/json',
            ]));

            return;
        }

        $event->setResponse(new Response($this->twig->render('security/not_found.html.twig', [
            'requestedPath' => $request->getPathInfo(),
            'homeUrl' => $this->urlGenerator->generate('home'),
            'recruiterUrl' => $this->urlGenerator->generate('recruiter_entry'),
        ]), Response::HTTP_NOT_FOUND));
    }
}
