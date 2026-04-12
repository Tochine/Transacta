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

class TransactionService
{
    public function __construct(private WalletService $walletService) {}

    public function createTransaction(User $user, array $data): Transaction
    {
        $ref = Transaction::generateUniqueReference();

        if (! empty($ref)) {
            $this->ensureUniqueReference($ref);
        }



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
                'reference'       => $ref,
                'type'            => $data['type'],
                'status'          => 'completed',
                'amount'          => $data['amount'],
                'currency'        => $data['currency'] ?? 'USD',
                'balance_before'  => $balanceBefore,
                'balance_after'   => $updatedWallet->balance,
                'description'     => $data['description'] ?? null,
                'metadata'        => $data['metadata'] ?? null,
                'channel'         => $data['channel'] ?? 'api',
                'processed_at'    => now(),
            ]);
 
            return $transaction;
        }, 3);

        return $transaction->refresh();
    }

    private function ensureUniqueReference(string $reference): void
    {
        if (Transaction::where('reference', $reference)->exists()) {
            throw new DuplicateTransactionException("Reference '{$reference}' already exists.");
        }
    }
}