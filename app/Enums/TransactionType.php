<?php

namespace App\Enums;

/**
 * Тип транзакции
 */
enum TransactionType: string
{
    case DEPOSIT = 'deposit';
    case WITHDRAW = 'withdraw';
    case FEE = 'fee';
}
