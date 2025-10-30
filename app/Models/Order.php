<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
      use HasFactory;
    protected $fillable = [
        'user_id',
        'customer_name',
        'order_items',
        'order_status',
        'total_amount',
    ];
    protected $table   = 'orders';
    protected $casts = [
        'order_items' => 'array',
        'total_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
