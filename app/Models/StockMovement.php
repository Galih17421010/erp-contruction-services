<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_id',
        'project_id',
        'movement_type',
        'quantity',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2'
    ];

    public function inventory(){
        return $this->belongsTo(Inventory::class);
    }

    public function project(){
        return $this->belongsTo(Project::class);
    }

    public function createdBy(){
        return $this->belongsTo(Employee::class, 'created_by');
    }

    public function scopeIn($query){
        return $query->where('movement_type', 'in');
    }

    public function scopeOut($query){
        return $query->where('movement_type', 'out');
    }

    public function scopeAdjustment($query){
        return $query->where('movement_type', 'adjustment');
    }
}
