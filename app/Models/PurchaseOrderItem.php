<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'inventory_id',
        'item_name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'subtotal',
        'received_quantity'
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'received_quantity' => 'decimal:2'
    ];

    public function purchaseOrder(){
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function inventory(){
        return $this->belongsTo(Inventory::class);
    }

    public function getRemainingQuantityAttribute(){
        return $this->quantity - ($this->received_quantity ?? 0);
    }

    public function getIsFullyReceivedAttribute(){
        return $this->received_quantity >= $this->quantity;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            $item->subtotal = $item->quantity * $item->unit_price;
        });

        static::updating(function ($item) {
            $item->subtotal = $item->quantity * $item->unit_price;
        });
    }
}
