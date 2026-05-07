<?php

namespace App\Entity\Enum;

enum DocumentRequestStatus: string
{
    case Pending = 'pending';
    case Provided = 'provided';
    case Expired = 'expired';
}
