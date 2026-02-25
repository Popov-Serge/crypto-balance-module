<?php

namespace App\Http\Controllers;

use App\Models\CryptoBalance;
use App\Services\CryptoBalanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CryptoBalanceController extends Controller
{
    public function __construct(
        private CryptoBalanceService $balanceService
    ) {}

    /**
     * Список крипто-балансов текущего пользователя.
     */
    public function index(): JsonResponse
    {
        $balances = CryptoBalance::where('user_id', Auth::id())
            ->get()
            ->map(fn (CryptoBalance $b) => [
                'id' => $b->id,
                'currency' => $b->currency,
                'network' => $b->network,
                'balance' => (string) $b->balance,
                'locked_balance' => (string) $b->locked_balance,
                'available' => (string) $b->available_balance,
            ]);

        return response()->json(['data' => $balances]);
    }

    /**
     * Зачисление на баланс (например, депозит).
     */
    public function credit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.00000001',
            'reference_type' => 'nullable|string|in:deposit,payment,refund',
            'reference_id' => 'nullable|integer',
            'idempotency_key' => 'nullable|string|max:64',
            'currency' => 'nullable|string|max:20',
            'network' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $tx = $this->balanceService->credit(
                user: Auth::id(),
                amount: (string) $validated['amount'],
                referenceType: $validated['reference_type'] ?? 'deposit',
                referenceId: $validated['reference_id'] ?? null,
                idempotencyKey: $validated['idempotency_key'] ?? null,
                currency: $validated['currency'] ?? 'USDT',
                network: $validated['network'] ?? null,
                description: $validated['description'] ?? null,
                metadata: $request->has('metadata') ? $request->input('metadata') : null
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Credited successfully',
            'transaction' => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (string) $tx->amount,
                'balance_after' => (string) $tx->balance_after,
            ],
        ], 201);
    }

    /**
     * Списание с баланса (вывод, платёж, комиссия).
     */
    public function debit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.00000001',
            'reference_type' => 'nullable|string|in:withdrawal,payment,fee',
            'reference_id' => 'nullable|integer',
            'idempotency_key' => 'nullable|string|max:64',
            'currency' => 'nullable|string|max:20',
            'network' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $tx = $this->balanceService->debit(
                user: Auth::id(),
                amount: (string) $validated['amount'],
                referenceType: $validated['reference_type'] ?? 'withdrawal',
                referenceId: $validated['reference_id'] ?? null,
                idempotencyKey: $validated['idempotency_key'] ?? null,
                currency: $validated['currency'] ?? 'USDT',
                network: $validated['network'] ?? null,
                description: $validated['description'] ?? null,
                metadata: $request->has('metadata') ? $request->input('metadata') : null
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['amount' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Debited successfully',
            'transaction' => [
                'id' => $tx->id,
                'type' => $tx->type,
                'amount' => (string) $tx->amount,
                'balance_after' => (string) $tx->balance_after,
            ],
        ], 200);
    }
}
