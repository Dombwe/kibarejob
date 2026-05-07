<?php

namespace App\Message;

final class GenerateApplicationJob
{
    public function __construct(private readonly string $swipeId)
    {
    }

    public function getSwipeId(): string
    {
        return $this->swipeId;
    }
}
