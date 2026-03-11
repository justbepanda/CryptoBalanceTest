<?php

namespace App\Http\Controllers;

use App\Jobs\ConfirmDepositJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Приход средств
 */
class DepositController extends Controller
{
    /**
     * Вебхук на подтверждение транзакции
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        $txHash = $request->input('tx_hash');

        if (!$txHash) {
            return response()->json(['error' => 'tx_hash required'], 422);
        }

        ConfirmDepositJob::dispatch($txHash)->onQueue('crypto-deposits');

        return response()->json(['status' => 'queued']);
    }
}
