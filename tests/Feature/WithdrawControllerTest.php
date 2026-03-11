<?php


namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use Tests\TestCase;

/**
 * Вывод средств
 */
class WithdrawControllerTest extends TestCase
{
    /**
     * @return void
     */
    public function test_withdraw_success(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'currency' => 'BTC',
            'balance' => '2.000000000000000000'
        ]);

        $response = $this->postJson('api/withdraw', [
            'user_id' => $user->id,
            'currency' => 'BTC',
            'amount' => '1.5'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'transaction_id'
            ]);

        $this->assertDatabaseHas('wallets', [
            'id' => $wallet->id,
            'balance' => '0.500000000000000000'
        ]);
    }

    /**
     * @return void
     */
    public function test_withdraw_fails_if_not_enough_balance(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'currency' => 'BTC',
            'balance' => '1.0'
        ]);

        $response = $this->postJson('api/withdraw', [
            'user_id' => $user->id,
            'currency' => 'BTC',
            'amount' => '1.5'
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Кошелек не найден или недостаточно средств.']);
    }

    /**
     * Тест валидации
     */
    public function test_withdraw_validation_fails(): void
    {
        $response = $this->postJson('api/withdraw', [
            'user_id' => null,
            'currency' => '',
            'amount' => 'abc'
        ]);

        $response->assertStatus(422);
    }
}
