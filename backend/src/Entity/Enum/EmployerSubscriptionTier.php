<?php

namespace App\Entity\Enum;

enum EmployerSubscriptionTier: string
{
    case Free = 'free';
    case Standard = 'standard';
    case Pro = 'pro';
}
