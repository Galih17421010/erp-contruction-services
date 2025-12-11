<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'notes'
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2'
    ];

    public function invoice(){
        return $this->belongsTo(Invoice::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($payment) {
            $invoice = $payment->invoice;
            $invoice->paid_amount += $payment->amount;

            if ($invoice->paid_amount >= $invoice->toal_amount) {
                $invoice->status = 'paid';
                $invoice->paid_at = now();
            } elseif ($invoice->paid_amount > 0) {
                $invoice->status = 'partial';
            }

            $invoice->save();
        });

        static::deleted(function ($payment) {
            $invoice = $payment->invoice;
            $invoice->paid_amount -= $payment->amount;

            if ($invoice->paid_amount <= 0) {
                $invoice->status = 'sent';
                $invoice->paid_at = null;
            } elseif ($invoice->paid_amount < $invoice->total_amount) {
                $invoice->status = 'partial';
                $invoice->paid_at = null;
            }

            $invoice->save();
        });
    }
}
