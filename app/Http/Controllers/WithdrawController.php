<?php

namespace App\Http\Controllers;

use App\Services\BalanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Exception;
use Throwable;

/**
 * Вывод средств
 */
class WithdrawController extends Controller
{
    /**
     * @param BalanceService $service
     */
    public function __construct(
        private readonly BalanceService $service
    )
    {
    }

    /**
     * Запрос на вывод средств
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function withdraw(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'currency' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        try {
            $transaction = $this->service->withdraw(
                $request->user_id,
                $request->currency,
                $request->amount
            );
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'pending',
            'transaction_id' => $transaction->id,
        ]);
    }
}
