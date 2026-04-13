<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use App\Helpers\MoneyMath;
use App\Exceptions\ErrorHandler;

class WalletService
{
    /**
     * Get a user's wallet or create one if it doesn't exist.
     */
    public function getOrCreateWallet(User $user, string $currency = 'NGN'): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id, 'currency' => strtoupper($currency)],
            ['balance' => 0, 'ledger_balance' => 0, 'is_active' => true]
        );
    }

    public function applyTransaction(Wallet $wallet, string $type, string $amount): Wallet
    {
        return DB::transaction(function () use ($wallet, $type, $amount) {
            // Acquire a row-level exclusive lock before reading the balance.
            // Any concurrent transaction attempting to lock this row will block
            // until this transaction commits or rolls back.
            $locked = Wallet::where('id', $wallet->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
 
            if (! $locked) {
                throw new ErrorHandler("Your {$wallet->currency} wallet not found or inactive.", "not found", 404);
            }

            $balance = MoneyMath::of($locked->balance);
            $delta   = MoneyMath::of($amount);
 
            if ($type === 'debit') {
                if ($balance->isLessThan($delta)) {
                    throw new ErrorHandler(
                        "Insufficient balance. Available: {$balance->toDecimal()}, Required: {$delta->toDecimal()}", "insufficient-fund", 402
                    );
                }
                $newBalance       = $balance->subtract($delta);
                $newLedgerBalance = MoneyMath::of($locked->ledger_balance)->subtract($delta);
            } else {
                $newBalance       = $balance->add($delta);
                $newLedgerBalance = MoneyMath::of($locked->ledger_balance)->add($delta);
            }
 
            $locked->balance        = $newBalance->toDecimal();
            $locked->ledger_balance = $newLedgerBalance->toDecimal();
            $locked->save();
 
            return $locked;
        });
    }
}