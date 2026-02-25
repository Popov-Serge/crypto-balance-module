<?php

namespace App\Services;

use App\Models\BalanceTransaction;
use App\Models\CryptoBalance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CryptoBalanceService
{
    private const SCALE = 8;

    /**
     * Зачисление средств на крипто-баланс пользователя.
     *
     * Риски и учёт:
     * - Идемпотентность по idempotency_key (повторный запрос не дублирует операцию)
     * - Блокировка строки (lockForUpdate) для предотвращения гонок
     * - Аудит в balance_transactions
     *
     * @param User|int $user User или user_id
     * @param string $amount Сумма (строка/число для точности)
     * @param string $referenceType deposit, payment, refund и т.д.
     * @param int|null $referenceId ID связанной сущности
     * @param string|null $idempotencyKey Ключ идемпотентности
     * @param string $currency Валюта (USDT и т.д.)
     * @param string|null $network Сеть (ERC20, TRC20 и т.д.)
     * @param string|null $description Описание
     * @param array|null $metadata Доп. данные
     * @return BalanceTransaction
     */
    public function credit(
        User|int $user,
        string $amount,
        string $referenceType = BalanceTransaction::REFERENCE_DEPOSIT,
        ?int $referenceId = null,
        ?string $idempotencyKey = null,
        string $currency = 'USDT',
        ?string $network = null,
        ?string $description = null,
        ?array $metadata = null
    ): BalanceTransaction {
        $amount = $this->normalizeAmount($amount);
        if (bccomp($amount, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('Amount must be positive for credit.');
        }

        $userId = $user instanceof User ? $user->id : (int) $user;

        return DB::transaction(function () use (
            $userId,
            $amount,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            $currency,
            $network,
            $description,
            $metadata
        ) {
            $balance = $this->getOrCreateBalance($userId, $currency, $network);

            if ($idempotencyKey) {
                $existing = BalanceTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $balanceBefore = (string) $balance->balance;
            $balanceAfter  = bcadd($balanceBefore, $amount, self::SCALE);

            $balance->increment('balance', $amount);

            return BalanceTransaction::create([
                'crypto_balance_id' => $balance->id,
                'type' => BalanceTransaction::TYPE_CREDIT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Списание средств с крипто-баланса (вывод, платёж, комиссия).
     *
     * Риски и учёт:
     * - Проверка достаточности доступного баланса (balance - locked_balance)
     * - Блокировка строки для предотвращения двойного списания
     * - Идемпотентность по idempotency_key
     * - Аудит в balance_transactions
     *
     * @param User|int $user
     * @param string $amount
     * @param string $referenceType withdrawal, payment, fee
     * @param int|null $referenceId
     * @param string|null $idempotencyKey
     * @param string $currency
     * @param string|null $network
     * @param string|null $description
     * @param array|null $metadata
     * @return BalanceTransaction
     */
    public function debit(
        User|int $user,
        string $amount,
        string $referenceType = BalanceTransaction::REFERENCE_WITHDRAWAL,
        ?int $referenceId = null,
        ?string $idempotencyKey = null,
        string $currency = 'USDT',
        ?string $network = null,
        ?string $description = null,
        ?array $metadata = null
    ): BalanceTransaction {
        $amount = $this->normalizeAmount($amount);
        if (bccomp($amount, '0', self::SCALE) <= 0) {
            throw new InvalidArgumentException('Amount must be positive for debit.');
        }

        $userId = $user instanceof User ? $user->id : (int) $user;

        return DB::transaction(function () use (
            $userId,
            $amount,
            $referenceType,
            $referenceId,
            $idempotencyKey,
            $currency,
            $network,
            $description,
            $metadata
        ) {
            $balance = CryptoBalance::where('user_id', $userId)
                ->where('currency', $currency)
                ->where('network', $network)
                ->lockForUpdate()
                ->first();

            if (!$balance) {
                throw new InvalidArgumentException('Crypto balance not found. Cannot debit.');
            }

            if ($idempotencyKey) {
                $existing = BalanceTransaction::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $available = bcsub((string) $balance->balance, (string) $balance->locked_balance, self::SCALE);
            if (bccomp($available, $amount, self::SCALE) < 0) {
                throw new InvalidArgumentException(
                    "Insufficient balance. Available: {$available}, required: {$amount}."
                );
            }

            $balanceBefore = (string) $balance->balance;
            $balanceAfter  = bcsub($balanceBefore, $amount, self::SCALE);

            $balance->decrement('balance', $amount);

            return BalanceTransaction::create([
                'crypto_balance_id' => $balance->id,
                'type' => BalanceTransaction::TYPE_DEBIT,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'idempotency_key' => $idempotencyKey,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Получить или создать запись баланса с блокировкой (в рамках уже открытой транзакции).
     */
    protected function getOrCreateBalance(int $userId, string $currency, ?string $network): CryptoBalance
    {
        $balance = CryptoBalance::firstOrCreate(
            [
                'user_id' => $userId,
                'currency' => $currency,
                'network' => $network,
            ],
            ['balance' => 0, 'locked_balance' => 0]
        );

        return CryptoBalance::where('id', $balance->id)->lockForUpdate()->first();
    }

    /**
     * Нормализация суммы: приводит к строке с фиксированной точностью для bcmath.
     */
    protected function normalizeAmount(string $amount): string
    {
        return bcadd($amount, '0', self::SCALE);
    }
}
