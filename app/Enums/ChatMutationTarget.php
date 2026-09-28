<?php

namespace App\Enums;

/** The protected business resource selected by a mutation request. */
enum ChatMutationTarget: string
{
    case Order = 'order';
    case Payment = 'payment';
}
