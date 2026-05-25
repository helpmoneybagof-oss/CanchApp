<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'court_id',
        'date',
        'start_time',
        'end_time',
        'status',
        'price',
        'block_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'price' => 'decimal:2',
        ];
    }

    // Relaciones
    public function court(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function reservations(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'reservation_time_slots');
    }

    // Scopes
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }

    public function scopeForDateRange($query, string $from, string $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopeForCourt($query, int $courtId)
    {
        return $query->where('court_id', $courtId);
    }

    // Helpers
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    public function isPreReserved(): bool
    {
        return $this->status === 'pre_reserved';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    public function isBookable(): bool
    {
        return in_array($this->status, ['available', 'pre_reserved']);
    }

    public function getStartTimeFormattedAttribute(): string
    {
        return \Carbon\Carbon::parse($this->start_time)->format('g:i A');
    }

    public function getEndTimeFormattedAttribute(): string
    {
        return \Carbon\Carbon::parse($this->end_time)->format('g:i A');
    }
}
