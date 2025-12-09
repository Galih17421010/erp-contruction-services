<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'name', 'company_name', 'email', 'phone', 'mobile', 'address', 'city', 'province',
        'postal_code', 'tax_number', 'contact_person', 'notes', 'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public function projects(){
        return $this->hasMany(Project::class);
    }

    public function quotations(){
        return $this->hasMany(Quotation::class);
    }

    public function invoices(){
        return $this->hasMany(Invoice::class); 
    }

    public function scopeActive($query){
        return $query->where('status', 'active');
    }

    public function getTotalProjectsAttribute(){
        return $this->projects()->count();
    }

    public function getTotalRevenueAttribute(){
        return $this->invoices()->where('status', 'paid')->sum('total_amount');
    }
}
