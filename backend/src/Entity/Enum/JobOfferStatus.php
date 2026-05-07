<?php

namespace App\Entity\Enum;

enum JobOfferStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Draft = 'draft';
}
