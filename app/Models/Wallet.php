<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


#[Fillable(['user_id', 'currency', 'balance', 'ledger_balance', 'is_active', 'version',])]
class Wallet extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'balance'        => 'decimal:8',
            'ledger_balance' => 'decimal:8',
            'is_active'      => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
