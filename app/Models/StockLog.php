<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockLog extends Model
{
    protected $fillable = [
        'product_id',
        'type',
        'quantity',
        'description',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public $timestamps = false;

    /**
     * Get the product that owns the stock log.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
