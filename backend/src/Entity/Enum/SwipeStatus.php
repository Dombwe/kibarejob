<?php

namespace App\Entity\Enum;

enum SwipeStatus: string
{
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Interview = 'interview';
    case Rejected = 'rejected';
    case Hired = 'hired';
}
