<?php

namespace Tests\Feature;

use App\Jobs\ConfirmDepositJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Приход средств
 */
class DepositControllerTest extends TestCase
{
    /**
     * Тест на создание задачи транзакции
     */
    public function test_webhook_queues_deposit_job(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/webhook/deposit', [
            'tx_hash' => 'tx_queued'
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'queued']);

        Queue::assertPushed(ConfirmDepositJob::class, function ($job) {
            return $job->txHash === 'tx_queued';
        });
    }

    /**
     * Хэш обязателен
     */
    public function test_webhook_without_tx_hash_returns_error(): void
    {
        $response = $this->postJson('/api/webhook/deposit', []);

        $response->assertStatus(422)
            ->assertJson(['error' => 'tx_hash required']);
    }
}
