<?php

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_notifications_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $limit = $this->boundedInt($request->query->get('limit'), 50, 1, 100);
        $cursor = $this->boundedInt($request->query->get('cursor'), 0, 0, 1000000);
        $notifications = $this->notificationRepository->findBy(
            ['user' => $user, 'isDeleted' => false],
            ['createdAt' => 'DESC'],
            $limit + 1,
            $cursor
        );
        $hasMore = count($notifications) > $limit;
        $notifications = array_slice($notifications, 0, $limit);

        return $this->json([
            'notifications' => array_map(
                fn (Notification $notification): array => $this->serializeNotification($notification),
                $notifications
            ),
            'unreadCount' => count(array_filter(
                $notifications,
                static fn (Notification $notification): bool => !$notification->isRead()
            )),
            'nextCursor' => $hasMore ? $cursor + $limit : null,
            'hasMore' => $hasMore,
        ]);
    }

    #[Route('/{id}/read', name: 'api_notifications_read', methods: ['POST'])]
    public function markAsRead(string $id): JsonResponse
    {
        $notification = $this->findOwnedNotification($id);
        $notification->setIsRead(true);
        $this->entityManager->flush();

        return $this->json(['notification' => $this->serializeNotification($notification)]);
    }

    #[Route('/read-all', name: 'api_notifications_read_all', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $notifications = $this->notificationRepository->findBy([
            'user' => $user,
            'isRead' => false,
            'isDeleted' => false,
        ]);

        foreach ($notifications as $notification) {
            if ($notification instanceof Notification) {
                $notification->setIsRead(true);
            }
        }

        $this->entityManager->flush();

        return $this->json(['updated' => count($notifications)]);
    }

    private function findOwnedNotification(string $id): Notification
    {
        $user = $this->authenticatedUser();
        $notification = $this->notificationRepository->find($id);

        if (!$notification instanceof Notification
            || $notification->isDeleted()
            || $notification->getUser()->getId()?->toString() !== $user->getId()?->toString()
        ) {
            throw $this->createNotFoundException('Notification introuvable.');
        }

        return $notification;
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        return $user;
    }

    private function boundedInt(mixed $value, int $default, int $min, int $max): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $parsed) {
            $parsed = $default;
        }

        return max($min, min($max, (int) $parsed));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeNotification(Notification $notification): array
    {
        return [
            'id' => (string) $notification->getId(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'isRead' => $notification->isRead(),
            'data' => $notification->getData(),
            'createdAt' => $notification->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
