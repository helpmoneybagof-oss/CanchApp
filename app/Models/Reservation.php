<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'court_id',
        'date',
        'start_time',
        'end_time',
        'duration_hours',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'payment_proof',
        'payment_expires_at',
        'court_price',
        'consumables_price',
        'total_price',
        'cancellation_reason',
        'notes',
        'confirmation_code',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'court_price' => 'decimal:2',
            'consumables_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'payment_expires_at' => 'datetime',
        ];
    }

    // Generar código de confirmación automáticamente
    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            if (empty($reservation->confirmation_code)) {
                $reservation->confirmation_code = strtoupper(Str::random(8));
            }
        });
    }

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function timeSlots(): BelongsToMany
    {
        return $this->belongsToMany(TimeSlot::class, 'reservation_time_slots');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\ReservationItem::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'confirmed'])
            ->where('date', '>=', now()->toDateString());
    }

    public function scopeForDate($query, string $date)
    {
        return $query->where('date', $date);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', now()->toDateString())
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('date')
            ->orderBy('start_time');
    }

    public function scopePast($query)
    {
        return $query->where(function ($q) {
            $q->where('date', '<', now()->toDateString())
                ->orWhere('status', 'cancelled');
        })->orderByDesc('date');
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPendingPayment(): bool
    {
        return $this->payment_status === 'pending_payment';
    }

    public function isInPaymentReview(): bool
    {
        return $this->payment_status === 'payment_review';
    }

    public function isPaymentRejected(): bool
    {
        return $this->payment_status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->payment_expires_at !== null
            && $this->payment_expires_at->isPast()
            && in_array($this->payment_status, ['unpaid', 'pending_payment']);
    }

    public function canBeCancelledByClient(): bool
    {
        if ($this->isCancelled() || $this->isCompleted()) {
            return false;
        }

        // Solo puede cancelar si no ha pagado
        return ! $this->isPaid();
    }

    public function canBeRescheduledByClient(): bool
    {
        if ($this->isCancelled() || $this->isCompleted()) {
            return false;
        }

        // Debe quedar al menos 3h antes del inicio del partido
        $startsAt = \Carbon\Carbon::parse(
            $this->date->format('Y-m-d').' '.$this->start_time,
            'America/Bogota'
        );

        return now('America/Bogota')->diffInMinutes($startsAt, false) >= 180;
    }

    public function getStartTimeFormattedAttribute(): string
    {
        return \Carbon\Carbon::parse($this->start_time)->format('g:i A');
    }

    public function getEndTimeFormattedAttribute(): string
    {
        return \Carbon\Carbon::parse($this->end_time)->format('g:i A');
    }

    public function getDateFormattedAttribute(): string
    {
        return $this->date->format('d/m/Y');
    }
}
