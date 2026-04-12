<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;


#[Fillable(['user_id', 'currency', 'balance', 'ledger_balance', 'is_active'])]
class Wallet extends Model
{
    use SoftDeletes, HasUuids;

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

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
