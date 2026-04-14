<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditTransactionType: string
{
    case Grant        = 'grant';
    case Reserve      = 'reserve';
    case Confirm      = 'confirm';
    case Refund       = 'refund';
    case Topup        = 'topup';
    case ManualAdjust = 'manual_adjust';
}
