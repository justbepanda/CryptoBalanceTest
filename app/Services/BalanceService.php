<?php
namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Exception;
use Throwable;

/**
 * Баланс кошелька
 */
class BalanceService
{
    /**
     * Создать предварительное пополнение
     *
     * @param int $userId
     * @param string $currency
     * @param string $amount
     * @param string $txHash
     * @return Transaction
     */
    public function createPendingDeposit(int $userId, string $currency, string $amount, string $txHash): Transaction
    {
        return Transaction::create([
            'user_id' => $userId,
            'currency' => $currency,
            'amount' => $amount,
            'tx_hash' => $txHash,
            'type' => TransactionType::DEPOSIT,
            'status' => TransactionStatus::PENDING,
        ]);
    }

    /**
     * Подтверждение зачисления
     *
     * @param string $txHash
     * @return void
     * @throws Throwable
     */
    public function confirmDeposit(string $txHash): void
    {
        DB::transaction(function () use ($txHash) {

            $transaction = Transaction::where('tx_hash', $txHash)
                ->where('type', TransactionType::DEPOSIT)
                ->where('status', TransactionStatus::PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = Wallet::where('user_id', $transaction->user_id)
                ->where('currency', $transaction->currency)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id' => $transaction->user_id,
                    'currency' => $transaction->currency,
                    'balance' => '0',
                ]);
            }

            $wallet->balance = bcadd($wallet->balance, $transaction->amount, 18);
            $wallet->save();

            $transaction->update([
                'status' => TransactionStatus::CONFIRMED,
                'wallet_id' => $wallet->id
            ]);
        });
    }


    /**
     * Списание средств
     *
     * @param int $userId
     * @param string $currency
     * @param string $amount
     * @return Transaction
     * @throws Throwable
     */
    public function withdraw(int $userId, string $currency, string $amount): Transaction
    {
        return DB::transaction(function () use ($userId, $currency, $amount) {
            $wallet = Wallet::where('user_id', $userId)
                ->where('currency', $currency)
                ->lockForUpdate()
                ->first();

            if (!$wallet || bccomp($wallet->balance, $amount, 18) === -1) {
                throw new Exception("Кошелек не найден или недостаточно средств.");
            }

            $wallet->balance = bcsub($wallet->balance, $amount, 18);
            $wallet->save();

            return Transaction::create([
                'user_id' => $userId,
                'wallet_id' => $wallet->id,
                'currency' => $currency,
                'type' => TransactionType::WITHDRAW,
                'amount' => $amount,
                'status' => TransactionStatus::PENDING
            ]);
        });
    }

    /**
     * Откат неудачного вывода
     *
     * @param int $transactionId
     * @return void
     * @throws Throwable
     */
    public function rollbackWithdraw(int $transactionId): void
    {
        DB::transaction(function () use ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)
                ->where('type', TransactionType::WITHDRAW)
                ->where('status', TransactionStatus::PENDING)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet = Wallet::where('id', $transaction->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->balance = bcadd($wallet->balance, $transaction->amount, 18);
            $wallet->save();

            $transaction->update(['status' => TransactionStatus::FAILED]);
        });
    }
}
