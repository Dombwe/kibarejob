<?php

namespace App\Service;

use App\Entity\CandidateProfile;
use App\Entity\JobOffer;

class ScoreCacheService
{
    private bool $redisAvailable = true;

    public function __construct(
        private readonly mixed $redis,
        private readonly MatchingService $matchingService,
    ) {
    }

    public function getScore(CandidateProfile $candidate, JobOffer $offer): int
    {
        $key = $this->scoreKey($candidate, $offer);
        $cached = null;

        if ($this->redisAvailable) {
            try {
                $cached = $this->redis->get($key);
            } catch (\Throwable) {
                $this->redisAvailable = false;
            }
        }

        if (false !== $cached && null !== $cached) {
            return (int) $cached;
        }

        $score = $this->matchingService->calculateMatchScore($candidate, $offer);
        $this->setScore($candidate, $offer, $score);

        return $score;
    }

    public function setScore(CandidateProfile $candidate, JobOffer $offer, int $score, int $ttl = 21600): void
    {
        if (!$this->redisAvailable) {
            return;
        }

        try {
            $this->redis->setex($this->scoreKey($candidate, $offer), $ttl, (string) max(0, min(100, $score)));
        } catch (\Throwable) {
            $this->redisAvailable = false;
        }
    }

    public function invalidateCandidate(CandidateProfile $candidate): void
    {
        $this->deleteByPattern(sprintf('score:%s:*', $candidate->getUser()->getId()));
    }

    public function invalidateOffer(JobOffer $offer): void
    {
        $this->deleteByPattern(sprintf('score:*:%s', $offer->getId()));
    }

    private function scoreKey(CandidateProfile $candidate, JobOffer $offer): string
    {
        return sprintf('score:%s:%s', $candidate->getUser()->getId(), $offer->getId());
    }

    private function deleteByPattern(string $pattern): void
    {
        if (!$this->redisAvailable) {
            return;
        }

        $iterator = null;
        try {
            do {
                $keys = $this->redis->scan($iterator, $pattern, 100);
                if (false !== $keys && [] !== $keys) {
                    foreach ($keys as $key) {
                        $this->redis->del($key);
                    }
                }
            } while ($iterator > 0);
        } catch (\Throwable) {
            $this->redisAvailable = false;
        }
    }
}
