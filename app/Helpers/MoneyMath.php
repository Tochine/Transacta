<?php

namespace App\Helpers;

use InvalidArgumentException;

final class MoneyMath
{
    private const SCALE = 8;
    private const FACTOR = 100_000_000; // 10^8
 
    /** Internal representation: integer units at SCALE decimal places */
    private readonly int $units;
 
    private function __construct(int $units)
    {
        $this->units = $units;
    }

    public static function of(string|int|float $amount): self
    {
        if (is_float($amount)) {
            // Convert float → string first to avoid float imprecision bleeding in
            $amount = number_format($amount, self::SCALE, '.', '');
        }
 
        $amount = (string) $amount;
 
        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid money value: {$amount}");
        }
 
        // Split on decimal point
        $parts    = explode('.', $amount);
        $whole    = $parts[0];
        $fraction = isset($parts[1]) ? str_pad(substr($parts[1], 0, self::SCALE), self::SCALE, '0') : str_repeat('0', self::SCALE);
 
        $negative = str_starts_with($whole, '-');
        $whole    = ltrim($whole, '-');
 
        $units = ((int) $whole * self::FACTOR) + (int) $fraction;
 
        return new self($negative ? -$units : $units);
    }

    public static function zero(): self
    {
        return new self(0);
    }
 
    // Arithmetic 
 
    public function add(self $other): self
    {
        return new self($this->units + $other->units);
    }
 
    public function subtract(self $other): self
    {
        return new self($this->units - $other->units);
    }
 
    public function abs(): self
    {
        return new self(abs($this->units));
    }

       // Comparison

    public function compare(self $other): int
    {
        return $this->units <=> $other->units;
    }
 
    public function isGreaterThan(self $other): bool
    {
        return $this->units > $other->units;
    }
 
    public function isGreaterThanOrEqual(self $other): bool
    {
        return $this->units >= $other->units;
    }
 
    public function isLessThan(self $other): bool
    {
        return $this->units < $other->units;
    }
 
    public function isNegative(): bool
    {
        return $this->units < 0;
    }
 
    public function isZero(): bool
    {
        return $this->units === 0;
    }
 
    public function equals(self $other): bool
    {
        return $this->units === $other->units;
    }
 
    // Conversion
 
    /**
     * Return a decimal string with SCALE decimal places.
     * Suitable for storing in the database.
     */
    public function toDecimal(): string
    {
        $negative = $this->units < 0;
        $abs      = abs($this->units);
 
        $whole    = intdiv($abs, self::FACTOR);
        $fraction = str_pad((string) ($abs % self::FACTOR), self::SCALE, '0', STR_PAD_LEFT);
 
        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }
 
    /**
     * Return a float — only use for display/logging
     */
    public function toFloat(): float
    {
        return $this->units / self::FACTOR;
    }
 
    public function __toString(): string
    {
        return $this->toDecimal();
    }
       
}