<?php

namespace App\Entity\Enum;

enum UserSubscriptionTier: string
{
    case Free = 'free';
    case Premium = 'premium';
}
