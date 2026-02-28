<?php

namespace App\Http\Controllers\Client;

use App\Events\PaymentProofSubmitted;
use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Setting;
use App\Notifications\PaymentProofSubmittedNotification;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Página de pago Nequi: muestra el número y formulario para subir comprobante.
     */
    public function show(Request $request, Reservation $reservation): Response
    {
        // Solo el dueño puede pagar su reserva
        if ($reservation->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($reservation->isPaid()) {
            return redirect()->route('reservations.show', $reservation);
        }

        $nequiNumber    = Setting::getValue('nequi_number', '');
        $expiryMinutes  = (int) Setting::getValue('payment_expiry_minutes', 30);

        return Inertia::render('client/Payment', [
            'reservation' => [
                'id'                 => $reservation->id,
                'confirmation_code'  => $reservation->confirmation_code,
                'total_price'        => (float) $reservation->total_price,
                'payment_status'     => $reservation->payment_status,
                'payment_expires_at' => $reservation->payment_expires_at?->toISOString(),
                'payment_proof'      => $reservation->payment_proof
                    ? Storage::url($reservation->payment_proof)
                    : null,
            ],
            'nequi_number'   => $nequiNumber,
            'expiry_minutes' => $expiryMinutes,
        ]);
    }

    /**
     * El cliente sube el comprobante de pago Nequi.
     */
    public function uploadProof(Request $request, Reservation $reservation, PushNotificationService $push, NotificationService $notif): RedirectResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($reservation->isPaid() || $reservation->isInPaymentReview()) {
            return redirect()->back()->with('flash', [
                'type'    => 'error',
                'message' => 'Este pago ya fue enviado y está en revisión.',
            ]);
        }

        $request->validate([
            'proof'     => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'reference' => 'nullable|string|max:100',
        ]);

        // Eliminar comprobante anterior si existe
        if ($reservation->payment_proof) {
            Storage::disk('public')->delete($reservation->payment_proof);
        }

        $path = $request->file('proof')->store('payment-proofs', 'public');

        $expiryMinutes = (int) Setting::getValue('payment_expiry_minutes', 30);

        $reservation->update([
            'payment_method'     => 'nequi',
            'payment_reference'  => $request->reference,
            'payment_proof'      => $path,
            'payment_status'     => 'payment_review',
            'payment_expires_at' => now()->addMinutes($expiryMinutes),
        ]);

        // Broadcast en tiempo real al admin
        broadcast(new PaymentProofSubmitted($reservation))->toOthers();

        // Notificación DB + Push a admins: nuevo comprobante para revisar
        try {
            $reservation->load('user');
            $notif->notifyAdmins(new PaymentProofSubmittedNotification($reservation));
            $push->sendToAdmins(
                title: '💳 Nuevo comprobante de pago',
                body:  "{$reservation->user->name} subió el comprobante de la reserva #{$reservation->confirmation_code}.",
                data:  ['url' => '/admin/payments/pending'],
            );
        } catch (\Throwable $e) {
            Log::warning("Notif/Push admin error (comprobante reserva #{$reservation->id}): {$e->getMessage()}");
        }

        return redirect()->route('reservations.show', $reservation)
            ->with('flash', [
                'type'    => 'success',
                'message' => '¡Comprobante enviado! El administrador revisará tu pago pronto.',
            ]);
    }
}
