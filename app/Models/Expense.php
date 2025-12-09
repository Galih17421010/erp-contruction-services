<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $fillable = [
        'project_id', 'employee_id', 'expense_number', 'expense_date', 'category',
        'description', 'amount', 'receipt_file', 'status', 'approved_by', 'approved_at',
        'notes'
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime'
    ];

    public function project(){
        return $this->belongsTo(Project::class);
    }

    public function employee(){
        return $this->belongsTo(Employee::class);
    }

    public function approvedBy(){
        return $this->belongsTo(Employee::class, 'approved_by');
    }

    public function scopePending($query){
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query){
        return $query->where('status', 'approved');
    }
}
