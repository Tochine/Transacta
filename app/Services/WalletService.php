<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;

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
}