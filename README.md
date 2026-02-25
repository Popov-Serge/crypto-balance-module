# Модуль учёта крипто-баланса пользователя (PHP + Laravel)

Модуль для зачисления и списания крипто-баланса с учётом рисков: идемпотентность, блокировки, аудит.

## Установка в Laravel-проект

1. Скопируйте содержимое в ваш проект:
   - `app/Models/` → в `app/Models/`
   - `app/Services/` → в `app/Services/`
   - `app/Http/Controllers/` → в `app/Http/Controllers/`
   - `database/migrations/` → в `database/migrations/`
   - Маршруты из `routes/api.php` добавьте в ваш `routes/api.php`

2. Выполните миграции:
   ```bash
   php artisan migrate
   ```

3. Убедитесь, что есть модель `App\Models\User` и настроена аутентификация (например, Sanctum для API).

## Риски и защита

| Риск | Решение |
|------|--------|
| Двойное списание/зачисление при повторе запроса | Идемпотентность по `idempotency_key` — повторный запрос с тем же ключом возвращает существующую операцию |
| Гонка при одновременных операциях | Транзакция БД + `lockForUpdate()` по строке баланса |
| Списание при недостатке средств | Проверка доступного баланса (balance − locked_balance) перед списанием |
| Нет истории операций | Каждая операция пишется в `balance_transactions` (аудит) |

## Использование

### Зачисление (credit)

```php
use App\Services\CryptoBalanceService;

$service = app(CryptoBalanceService::class);

$tx = $service->credit(
    user: $user->id,
    amount: '100.50',
    referenceType: 'deposit',
    referenceId: $depositId,
    idempotencyKey: 'deposit-' . $depositId, // опционально
    currency: 'USDT',
    network: 'TRC20',
    description: 'Deposit from wallet'
);
```

### Списание (debit)

```php
$tx = $service->debit(
    user: $user->id,
    amount: '50.25',
    referenceType: 'withdrawal',
    referenceId: $withdrawalId,
    idempotencyKey: 'withdrawal-' . $withdrawalId,
    currency: 'USDT',
    network: 'TRC20',
    description: 'Withdrawal to external wallet'
);
```

При недостатке баланса будет выброшено `InvalidArgumentException`.

## API (если подключены маршруты)

- `GET /api/crypto-balances` — список балансов текущего пользователя (требуется `auth:sanctum`)
- `POST /api/crypto-balances/credit` — зачисление (body: `amount`, опционально `currency`, `network`, `reference_type`, `reference_id`, `idempotency_key`, `description`, `metadata`)
- `POST /api/crypto-balances/debit` — списание (те же поля)

## Структура БД

- **crypto_balances** — баланс по пользователю/валюте/сети (balance, locked_balance).
- **balance_transactions** — история операций (type: credit/debit, amount, balance_before/after, reference_type, idempotency_key).

## Стек

- PHP 8.1+
- Laravel 10+
- MySQL/PostgreSQL (транзакции и row locking)
