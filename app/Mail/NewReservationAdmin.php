<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewReservationAdmin extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Nueva reserva #'.$this->reservation->confirmation_code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin.new-reservation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
