<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Exceptions\DuplicateTransactionException;
use App\Exceptions\IdempotencyConflictException;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InvalidTransactionStateException;
use App\Models\Transaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\WalletService;
use Illuminate\Support\Facades\Redis;
use App\Exceptions\ErrorHandler;

class TransactionService
{
    public function __construct(private WalletService $walletService) {}

    public function createTransaction(User $user, array $data): Transaction
    {
        $ref = Transaction::generateUniqueReference();

        if (! empty($ref)) {
            $this->ensureUniqueReference($ref);
        }

        $data['reference'] = $ref;

        if (empty($data['idempotency_key'])) {
            throw new ErrorHandler("indempotency key is missing", "no key", 400);
        }

        $existing = $this->findByIdempotencyKey($data['idempotency_key'], $user->id, $data['amount']);
        if ($existing) {
            Log::info('Idempotent transaction replay', [
                'idempotency_key' => $data['idempotency_key'],
                'transaction_ref'  => $ref,
            ]);

            throw new ErrorHandler('Transaction in progress', 'duplicate-transaction', 403);
        }

        Redis::set(
            "idempotency-key:{$data['idempotency_key']}:{$user->id}:{$data['amount']}:{$data['currency']}",
            $data['idempotency_key'], 
            "EX", 86400,
            "NX"
        );

        // Execute within a DB transaction for atomicity 
        $transaction = DB::transaction(function () use ($user, $data) {
            $wallet = $this->walletService->getOrCreateWallet($user, $data['currency'] ?? 'NGN');
 
            // Capture balance snapshot 
            $balanceBefore = $wallet->balance;
 
            // Apply balance change (uses lockForUpdate internally)
            $updatedWallet = $this->walletService->applyTransaction(
                $wallet,
                $data['type'],
                $data['amount']
            );
 
            $transaction = Transaction::create([
                'user_id'         => $user->id,
                'wallet_id'       => $updatedWallet->id,
                'reference'       => $data['reference'],
                'type'            => $data['type'],
                'status'          => 'completed',
                'amount'          => $data['amount'],
                'currency'        => $data['currency'] ?? 'NGN',
                'balance_before'  => $balanceBefore,
                'balance_after'   => $updatedWallet->balance,
                'description'     => $data['description'] ?? null,
                'metadata'        => $data['metadata'] ?? null,
                'channel'         => $data['channel'] ?? 'api',
                'processed_at'    => now(),
            ]);
 
            return $transaction;
        }, 3);

        Redis::del("idempotency-key:{$data['idempotency_key']}:{$user->id}:{$data['amount']}:{$data['currency']}");

        return $transaction->refresh();
    }

    private function ensureUniqueReference(string $reference): void
    {
        if (Transaction::where('reference', $reference)->exists()) {
            throw new DuplicateTransactionException("Reference '{$reference}' already exists.");
        }
    }

    function findByIdempotencyKey(string $key, string $id, string $amount, string $currency): bool
    {
        return Redis::exists("indempotency-key:{$key}:{$id}:{$amount}:{$amount}");
    }
}