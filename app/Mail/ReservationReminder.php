<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationReminder extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $hoursLabel  Ej: "24 horas" o "2 horas"
     */
    public function __construct(
        public Reservation $reservation,
        public string $hoursLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio: tu reserva es en '.$this->hoursLabel.' (#'.$this->reservation->confirmation_code.')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
