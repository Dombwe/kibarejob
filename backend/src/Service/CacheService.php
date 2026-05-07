<?php

namespace App\Service;

use App\Entity\JobOffer;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class CacheService
{
    private const DAILY_FREE_SWIPE_LIMIT = 10;

    public function __construct(
        private readonly mixed $redis,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $defaultTtl = 120,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFeed(string|int $userId, int $page): ?array
    {
        $cached = $this->redis->get($this->feedKey($userId, $page));

        return $cached ? json_decode((string) $cached, true) : null;
    }

    /**
     * @param array<string, mixed> $feed
     */
    public function setFeed(string|int $userId, int $page, array $feed, ?int $ttl = null): void
    {
        $this->redis->setex($this->feedKey($userId, $page), $ttl ?? $this->defaultTtl, json_encode($feed, JSON_THROW_ON_ERROR));
    }

    public function invalidateFeed(string|int|null $userId = null): void
    {
        $pattern = null === $userId ? 'feed:*' : sprintf('feed:%s:*', $userId);
        foreach ($this->scanKeys($pattern) as $key) {
            $this->redis->del($key);
        }
    }

    /**
     * @return array<int, array{skill: string, count: int}>
     */
    public function getTopSkills(): array
    {
        $key = 'stats:top_skills';
        $cached = $this->redis->get($key);
        if ($cached) {
            return json_decode((string) $cached, true);
        }

        $offers = $this->entityManager->getRepository(JobOffer::class)->findBy(['isDeleted' => false]);
        $skills = [];

        foreach ($offers as $offer) {
            foreach ($offer->getRequiredSkills() as $skill) {
                $normalized = trim((string) $skill);
                if ('' === $normalized) {
                    continue;
                }
                $skills[$normalized] = ($skills[$normalized] ?? 0) + 1;
            }
        }

        arsort($skills);
        $topSkills = array_map(
            static fn (string $skill, int $count): array => ['skill' => $skill, 'count' => $count],
            array_keys(array_slice($skills, 0, 20, true)),
            array_values(array_slice($skills, 0, 20, true)),
        );

        $this->redis->setex($key, 3600, json_encode($topSkills, JSON_THROW_ON_ERROR));

        return $topSkills;
    }

    /**
     * Cache les quotas quotidiens pour eviter trop de requetes DB.
     *
     * @return array{used: int, remaining: int, limit: int, reset_at: string}
     */
    public function getDailyQuota(string|int $userId): array
    {
        $key = "user:{$userId}:quota";
        $cached = $this->redis->get($key);

        if ($cached) {
            return json_decode((string) $cached, true);
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        $used = $user instanceof User ? $user->getSwipesUsedToday() : 0;
        $remaining = max(0, self::DAILY_FREE_SWIPE_LIMIT - $used);

        $data = [
            'used' => $used,
            'remaining' => $remaining,
            'limit' => self::DAILY_FREE_SWIPE_LIMIT,
            'reset_at' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d 00:00:00'),
        ];

        $this->redis->setex($key, 3600, json_encode($data, JSON_THROW_ON_ERROR));

        return $data;
    }

    public function incrementDailyQuota(string|int $userId): void
    {
        $key = "user:{$userId}:quota";
        $usedKey = $key . ':used_today';

        $this->redis->incr($usedKey);
        $this->redis->expire($usedKey, 3600);
        $this->redis->del($key);
    }

    private function feedKey(string|int $userId, int $page): string
    {
        return sprintf('feed:%s:%d', $userId, max(1, $page));
    }

    /**
     * @return string[]
     */
    private function scanKeys(string $pattern): array
    {
        $iterator = null;
        $keys = [];

        do {
            $result = $this->redis->scan($iterator, $pattern, 100);
            if (false !== $result) {
                $keys = [...$keys, ...$result];
            }
        } while ($iterator > 0);

        return $keys;
    }
}
