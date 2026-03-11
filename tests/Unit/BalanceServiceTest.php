<?php

namespace Tests\Unit;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Exception;
use Throwable;

class BalanceServiceTest extends TestCase
{
    private BalanceService $service;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BalanceService();
    }

    /**
     * Создание предварительного депозита
     */
    public function test_create_pending_deposit(): void
    {
        $user = User::factory()->create();
        $txHash = 'tx_pending';
        $amount = '1.234567890123456789';

        $transaction = $this->service->createPendingDeposit(
            $user->id,
            'BTC',
            $amount,
            $txHash
        );

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'user_id' => $user->id,
            'currency' => 'BTC',
            'tx_hash' => $txHash,
            'type' => TransactionType::DEPOSIT->value,
            'status' => TransactionStatus::PENDING->value,
        ]);

        $this->assertTrue(bccomp($transaction->amount, $amount, 18) === 0);
    }

    /**
     * Подтверждения депозита
     */
    public function test_confirm_deposit(): void
    {
        $user = User::factory()->create();
        $txHash = 'tx_confirm';
        $amount = '2.500000000000000000';

        $this->service->createPendingDeposit($user->id, 'ETH', $amount, $txHash);
        $this->service->confirmDeposit($txHash);

        $transaction = Transaction::where('tx_hash', $txHash)->first();
        $wallet = Wallet::where('user_id', $user->id)->where('currency', 'ETH')->first();

        $this->assertEquals(TransactionStatus::CONFIRMED->value, $transaction->status->value);
        $this->assertEquals($wallet->id, $transaction->wallet_id);
        $this->assertTrue(bccomp($wallet->balance, $amount, 18) === 0);
    }

    /**
     * Негативный кейс с двойным подтверждением депозита
     */
    public function test_confirm_deposit_double_spend(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $user = User::factory()->create();
        $txHash = 'tx_double';
        $amount = '1.000000000000000000';

        $this->service->createPendingDeposit($user->id, 'BTC', $amount, $txHash);
        $this->service->confirmDeposit($txHash);

        $this->service->confirmDeposit($txHash);
    }

    /**
     * Списания средств
     */
    public function test_with_draw(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'BTC',
            'balance' => '10.000000000000000000',
        ]);

        $amount = '3.500000000000000000';
        $transaction = $this->service->withdraw($user->id, 'BTC', $amount);

        $transaction->refresh();
        $wallet->refresh();

        $this->assertTrue(bccomp($transaction->amount, $amount, 18) === 0);
        $this->assertEquals(TransactionStatus::PENDING->value, $transaction->status->value);
        $this->assertTrue(bccomp($wallet->balance, '6.500000000000000000', 18) === 0);
    }

    /**
     * Негативный кейс списания средств (баланс меньше списания)
     */
    public function test_withdraw_not_enough_balance(): void
    {
        $this->expectException(Exception::class);

        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'BTC',
            'balance' => '1.000000000000000000',
        ]);

        $this->service->withdraw($user->id, 'BTC', '2.000000000000000000');
    }

    /**
     * Откат неудачного вывода
     */
    public function test_rollback_withdraw(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'ETH',
            'balance' => '5.000000000000000000',
        ]);

        $amount = '2.000000000000000000';
        $transaction = $this->service->withdraw($user->id, 'ETH', $amount);

        $wallet->refresh();
        $this->assertTrue(bccomp($wallet->balance, '3.000000000000000000', 18) === 0);

        $this->service->rollbackWithdraw($transaction->id);

        $wallet->refresh();
        $transaction->refresh();

        $this->assertTrue(bccomp($wallet->balance, '5.000000000000000000', 18) === 0);
        $this->assertEquals(TransactionStatus::FAILED->value, $transaction->status->value);
    }

    /**
     * Откат несуществующего вывода (негатив)
     */
    public function test_rollback_withdraw_no_transaction(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->service->rollbackWithdraw(999999);
    }

    /**
     * Два параллельных подтверждения одного депозита не пройдут
     */
    public function test_confirm_deposit_race_condition(): void
    {
        $user = User::factory()->create();
        $txHash = 'tx_race';
        $amount = '1.000000000000000000';

        $this->service->createPendingDeposit($user->id, 'BTC', $amount, $txHash);

        $exceptions = [];

        DB::beginTransaction();
        try {
            $this->service->confirmDeposit($txHash);
        } catch (Throwable $e) {
            $exceptions[] = $e;
        }
        DB::commit();

        DB::beginTransaction();
        try {
            $this->service->confirmDeposit($txHash);
        } catch (Throwable $e) {
            $exceptions[] = $e;
        }
        DB::commit();

        $this->assertCount(1, $exceptions);

        $this->assertEquals('1.000000000000000000', $user->wallets()->first()->balance);
    }
}
