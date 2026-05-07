<?php

namespace App\Entity\Enum;

enum SwipeDirection: string
{
    case Like = 'like';
    case Dislike = 'dislike';
    case Superlike = 'superlike';
}
