<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'order_number',
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
     * Generate unique order number.
     */
    public static function generateOrderNumber()
    {
        $date = date('Ymd');
        $prefix = 'TRX-' . $date . '-';

        $lastTransaction = self::where('order_number', 'LIKE', $prefix . '%')
            ->orderBy('order_number', 'desc')
            ->first();

        if ($lastTransaction) {
            $lastNumber = (int) substr($lastTransaction->order_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}
