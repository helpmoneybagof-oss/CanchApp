<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'price_per_hour',
        'description',
        'image',
        'capacity',
        'surface',
        'start_hour',
        'end_hour',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price_per_hour' => 'decimal:2',
            'capacity'       => 'integer',
            'start_hour'     => 'integer',
            'end_hour'       => 'integer',
            'active'         => 'boolean',
        ];
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Etiqueta del horario de disponibilidad, ej: "5:00 PM – 11:00 PM"
     */
    public function getScheduleLabelAttribute(): string
    {
        $fmt = fn(int $h) => \Carbon\Carbon::createFromTime($h)->format('g:i A');
        return $fmt($this->start_hour) . ' – ' . $fmt($this->end_hour);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) return null;
        return asset('storage/' . $this->image);
    }
}
