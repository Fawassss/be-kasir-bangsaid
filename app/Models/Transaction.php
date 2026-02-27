<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'order_number',
        'customer_name',
        'cashier_id',
        'payment_method',
        'total_price',
        'cash_received',
        'change_amount',
    ];

    protected $casts = [
        'total_price' => 'integer',
        'cash_received' => 'integer',
        'change_amount' => 'integer',
    ];

    /**
     * Get the cashier that created the transaction.
     */
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    /**
     * Get the items for the transaction.
     */
    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    /**
     * Generate unique order number with random alphanumeric characters.
     * Format: TRX-YYYYMMDD-XXXXXXXX (8 random alphanumeric characters)
     */
    public static function generateOrderNumber()
    {
        $date = date('Ymd');
        $prefix = 'TRX-' . $date . '-';

        // Try to generate a unique order number (max 10 attempts)
        $maxAttempts = 10;
        $attempt = 0;

        do {
            // Generate 8 random alphanumeric characters (uppercase letters and numbers)
            $randomString = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8));
            $orderNumber = $prefix . $randomString;

            // Check if this order number already exists
            $exists = self::where('order_number', $orderNumber)->exists();

            $attempt++;

            if (!$exists) {
                return $orderNumber;
            }

        } while ($attempt < $maxAttempts);

        // Fallback: use timestamp + random if all attempts failed (extremely unlikely)
        $fallbackString = strtoupper(substr(md5(microtime()), 0, 8));
        return $prefix . $fallbackString;
    }
}
