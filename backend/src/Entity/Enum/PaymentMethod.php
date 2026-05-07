<?php

namespace App\Entity\Enum;

enum PaymentMethod: string
{
    case OrangeMoney = 'orange_money';
    case MoovMoney = 'moov_money';
    case Card = 'card';
}
