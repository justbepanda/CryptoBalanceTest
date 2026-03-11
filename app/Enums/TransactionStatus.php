<?php

namespace App\Enums;

/**
 * Статус транзакции
 */
enum TransactionStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case FAILED = 'failed';
}
