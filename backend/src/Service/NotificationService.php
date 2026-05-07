<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public function notify(User $user, string $type, string $title, string $message, ?array $data = null): Notification
    {
        $notification = (new Notification())
            ->setUser($user)
            ->setType($type)
            ->setTitle($title)
            ->setMessage($message)
            ->setData($data);

        $this->entityManager->persist($notification);

        return $notification;
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }
}
