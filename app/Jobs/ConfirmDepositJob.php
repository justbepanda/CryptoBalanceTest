<?php

namespace App\Jobs;

use App\Services\BalanceService;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ConfirmDepositJob implements ShouldQueue
{
    use Queueable;

    public string $txHash;

    /**
     * @param string $txHash
     */
    public function __construct(string $txHash)
    {
        $this->txHash = $txHash;
    }

    /**
     * @param BalanceService $service
     * @return void
     * @throws Throwable
     */
    public function handle(BalanceService $service): void
    {
        \Log::info("ConfirmDepositJob started for txHash: {$this->txHash}");
        $service->confirmDeposit($this->txHash);
    }

    /**
     * @return WithoutOverlapping[]
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->txHash)];
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {

    }
}
