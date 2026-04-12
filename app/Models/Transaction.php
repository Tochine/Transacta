<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

#[Fillable(['user_id',
    'wallet_id',
    'reference',
    'type',
    'status',
    'amount',
    'currency',
    'balance_before',
    'balance_after',
    'description',
    'metadata',
    'channel',
    'parent_transaction_id',
    'processed_at'  
])]
class Transaction extends Model
{
    use HasFactory, SoftDeletes, HasUuids;
    protected function casts(): array
    {
        return [
            'amount'         => 'decimal:8',
            'balance_before' => 'decimal:8',
            'balance_after'  => 'decimal:8',
            'metadata'       => 'array',
            'processed_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public static function generateUniqueReference(int $maxAttempts = 5): string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $reference = static::generateReference();
 
            if (! static::where('reference', $reference)->exists()) {
                return $reference;
            }
        }

        throw new \RuntimeException(
            "Failed to generate a unique transaction reference after {$maxAttempts} attempts."
        );
    }

    public static function generateReference(): string
    {
        $datePart   = now()->format('Ymd');
        $randomPart = static::randomAlphanumeric(10);
 
        return "TXN-{$datePart}-{$randomPart}";
    }

    private static function randomAlphanumeric(int $length): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max      = strlen($alphabet) - 1;
        $bytes    = random_bytes($length);
        $result   = '';
 
        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[ord($bytes[$i]) % ($max + 1)];
        }
 
        return $result;
    }
}
