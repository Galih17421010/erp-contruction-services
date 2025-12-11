<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'project_id',
        'date',
        'clock_in',
        'clock_out',
        'work_hours',
        'status',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'work_hours' => 'decimal:2'
    ];

    public function employee(){
        return $this->belongsTo(Employee::class);
    }

    public function project(){
        return $this->belongsTo(Project::class);
    }

    public function calculateWorkHours(){
        if ($this->clock_in && $this->clock_out) {
            $diff = $this->clock_out->diffInMinutes($this->clock_in);
            $this->work_hours = round($diff / 60, 2);
            $this->save();
        }
    }

    public function scopePresent($query){
        return $query->where('status', 'present');
    }

    public function scopeAbsent($query){
        return $query->where('status', 'absent');
    }

    public function scopeThisMonth($query){
        return $query->whereMonth('date', now()->month())
                    ->whereYear('date', now()->year());
    }

    public function scopeToday($query){
        return $query->whereDate('date', today());
    }

    public function getIsLateAttribute(){
        if (!$this->clock_in) return false;

        // Asumsi jam kerja dimulai pukul 8
        $workerStart = $this->clock_in->copy()->setTime(8, 0, 0);
        return $this->clock_in->gt($workerStart);
    }

    public function getIsOvertimeAttribute(){
        return $this->work_hours > 8;
    }
}
