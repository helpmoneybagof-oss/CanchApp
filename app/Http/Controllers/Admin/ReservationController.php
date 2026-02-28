<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Events\PaymentApproved;
use App\Events\PaymentRejected;
use App\Events\ReservationCancelled as ReservationCancelledEvent;
use App\Events\ReservationCreated as ReservationCreatedEvent;
use App\Jobs\SendReservationCancelled;
use App\Jobs\SendReservationConfirmed;
use App\Models\Court;
use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\PaymentApprovedNotification;
use App\Notifications\PaymentRejectedNotification;
use App\Notifications\ReservationCancelledNotification;
use App\Notifications\ReservationCreatedNotification;
use App\Notifications\PreReservationCancelledNotification;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use App\Services\TimeSlotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function __construct(private TimeSlotService $slotService) {}

    /**
     * Detalle de una reserva (admin).
     */
    public function show(Reservation $reservation): Response
    {
        $reservation->load(['court', 'user', 'items.product']);

        return Inertia::render('admin/ReservationDetail', [
            'reservation' => [
                'id'                => $reservation->id,
                'confirmation_code' => $reservation->confirmation_code,
                'date'              => $reservation->date_formatted,
                'start_time'        => $reservation->start_time_formatted,
                'end_time'          => $reservation->end_time_formatted,
                'duration_hours'    => $reservation->duration_hours,
                'status'            => $reservation->status,
                'payment_status'    => $reservation->payment_status,
                'court_price'       => (float) $reservation->court_price,
                'consumables_price' => (float) $reservation->consumables_price,
                'total_price'       => (float) $reservation->total_price,
                'notes'             => $reservation->notes,
                'court_name'        => $reservation->court?->name,
                'court_type'        => $reservation->court?->type,
                'court_surface'     => $reservation->court?->surface,
                'created_at'        => $reservation->created_at->format('d/m/Y'),
                'user' => [
                    'id'    => $reservation->user->id,
                    'name'  => $reservation->user->name,
                    'email' => $reservation->user->email,
                    'phone' => $reservation->user->phone,
                ],
                'items' => $reservation->items->map(fn ($item) => [
                    'id'         => $item->id,
                    'name'       => $item->product?->name ?? 'Producto eliminado',
                    'quantity'   => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'subtotal'   => (float) $item->subtotal,
                    'image_url'  => $item->product?->image_url,
                ]),
            ],
        ]);
    }

    /**
     * Listado de todas las reservas con filtros.
     */
    public function index(Request $request): Response
    {
        $query = Reservation::with('user')
            ->orderByDesc('date')
            ->orderByDesc('start_time');

        if ($request->filled('date')) {
            $query->forDate($request->date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $reservations = $query->paginate(20)->through(fn (Reservation $r) => [
            'id'                => $r->id,
            'user'              => ['id' => $r->user->id, 'name' => $r->user->name, 'email' => $r->user->email],
            'date'              => $r->date_formatted,
            'date_raw'          => $r->date->format('Y-m-d'),
            'start_time'        => $r->start_time_formatted,
            'end_time'          => $r->end_time_formatted,
            'duration_hours'    => $r->duration_hours,
            'status'            => $r->status,
            'payment_status'    => $r->payment_status,
            'total_price'       => (float) $r->total_price,
            'confirmation_code' => $r->confirmation_code,
        ]);

        // Usuarios clientes activos para el modal de creación manual
        $clients = \App\Models\User::where('role', 'client')
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone']);

        // Canchas activas para el modal de creación manual
        $courts = Court::active()->orderBy('name')->get(['id', 'name', 'type', 'price_per_hour']);

        return Inertia::render('admin/Reservations', [
            'reservations' => $reservations,
            'filters'      => $request->only(['date', 'status', 'payment_status']),
            'clients'      => $clients,
            'courts'       => $courts,
        ]);
    }

    /**
     * Vista del calendario admin con todas las reservas.
     */
    public function calendar(Request $request): Response
    {
        $today  = Carbon::today()->toDateString();
        $courts = Court::active()->orderBy('name')->get(['id', 'name', 'type', 'price_per_hour']);

        $courtId       = $request->input('court_id', $courts->first()?->id);
        $selectedCourt = $courts->firstWhere('id', $courtId) ?? $courts->first();

        return Inertia::render('admin/Calendar', [
            'today'             => $today,
            'courts'            => $courts,
            'selected_court_id' => $selectedCourt?->id,
        ]);
    }

    /**
     * API: slots para el calendario admin.
     */
    public function calendarSlots(Request $request): JsonResponse
    {
        $request->validate([
            'from'     => 'required|date',
            'to'       => 'required|date|after_or_equal:from',
            'court_id' => 'required|exists:courts,id',
        ]);

        $court = Court::findOrFail($request->court_id);
        $slots = $this->slotService->getSlotsForDateRange($request->from, $request->to, $court);

        return response()->json($slots);
    }

    /**
     * Crear reserva manual (admin en nombre de un cliente).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'user_id'    => 'required|exists:users,id',
            'slot_ids'   => 'required|array|min:1',
            'slot_ids.*' => 'integer|exists:time_slots,id',
            'notes'      => 'nullable|string|max:500',
        ]);

        if (! $this->slotService->areSlotsAvailable($request->slot_ids)) {
            return response()->json([
                'message' => 'Uno o más horarios ya no están disponibles.',
            ], 422);
        }

        $slots      = TimeSlot::with('court')->whereIn('id', $request->slot_ids)->orderBy('start_time')->get();
        $firstSlot  = $slots->first();
        $court      = $firstSlot->court;
        $courtPrice = $slots->count() * (float) ($court?->price_per_hour ?? 0);

        try {
            DB::beginTransaction();

            $reservation = Reservation::create([
                'user_id'           => $request->user_id,
                'court_id'          => $court?->id,
                'date'              => $firstSlot->date->format('Y-m-d'),
                'start_time'        => $firstSlot->start_time,
                'end_time'          => $slots->last()->end_time,
                'duration_hours'    => $slots->count(),
                'status'            => 'confirmed',
                'payment_status'    => 'unpaid',
                'court_price'       => $courtPrice,
                'consumables_price' => 0,
                'total_price'       => $courtPrice,
                'notes'             => $request->notes,
            ]);

            $reservation->timeSlots()->attach($request->slot_ids);
            // Las reservas creadas por el admin se marcan como pre_reserved (admin puede marcar pagado después)
            $this->slotService->markAsPreReserved($request->slot_ids);

            DB::commit();

            // Enviar correos de confirmación (async)
            SendReservationConfirmed::dispatch($reservation);

            // Broadcast en tiempo real
            $reservation->load('user');
            broadcast(new ReservationCreatedEvent($reservation))->toOthers();

            return response()->json([
                'message'           => 'Reserva creada exitosamente.',
                'reservation_id'    => $reservation->id,
                'confirmation_code' => $reservation->confirmation_code,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al crear la reserva.'], 500);
        }
    }

    /**
     * Cancelar cualquier reserva (admin).
     */
    public function cancel(Request $request, Reservation $reservation, PushNotificationService $push, NotificationService $notif): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        if ($reservation->isCancelled()) {
            return redirect()->back()->withErrors(['message' => 'Esta reserva ya está cancelada.']);
        }

        try {
            DB::beginTransaction();

            $slotIds = $reservation->timeSlots()->pluck('time_slots.id')->toArray();
            $this->slotService->releaseSlots($slotIds);

            $reservation->update([
                'status'              => 'cancelled',
                'cancellation_reason' => $request->reason ?? 'Cancelado por el administrador',
            ]);

            DB::commit();

            $reservation->load('user');

            // Enviar correos de cancelación (async)
            SendReservationCancelled::dispatch($reservation);

            // Broadcast en tiempo real
            broadcast(new ReservationCancelledEvent($reservation))->toOthers();

            // Notificación DB + Push al cliente
            try {
                $notif->notifyUser($reservation->user, new ReservationCancelledNotification($reservation));
                $push->sendToUser(
                    userId: $reservation->user_id,
                    title:  '❌ Reserva cancelada',
                    body:   "Tu reserva #{$reservation->confirmation_code} fue cancelada por el administrador.",
                    data:   ['url' => '/reservations'],
                );
            } catch (\Throwable $e) {
                Log::warning("Notif/Push cliente error (cancelación admin reserva #{$reservation->id}): {$e->getMessage()}");
            }

            return redirect()->back();
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['message' => 'Error al cancelar la reserva.']);
        }
    }

    /**
     * Marcar reserva como pagada.
     */
    public function markAsPaid(Reservation $reservation, NotificationService $notif, PushNotificationService $push, TimeSlotService $slotService): RedirectResponse
    {
        $reservation->load('user');
        $reservation->update([
            'payment_status' => 'paid',
            'status'         => 'confirmed',
        ]);

        // Marcar slots como reservados definitivamente y cancelar otras pre-reservas del mismo slot
        $slotIds = $reservation->timeSlots()->pluck('time_slots.id')->toArray();
        $cancelledReservationIds = $slotService->markAsReserved($slotIds, $reservation->id);

        // Broadcast en tiempo real
        broadcast(new PaymentApproved($reservation))->toOthers();

        // Notificar a clientes cuyas pre-reservas fueron canceladas (excluyendo al que pagó)
        if (! empty($cancelledReservationIds)) {
            try {
                $losers = Reservation::with('user')
                    ->whereIn('id', $cancelledReservationIds)
                    ->where('id', '!=', $reservation->id)
                    ->get();

                foreach ($losers as $loser) {
                    if (! $loser->user) continue;

                    $notif->notifyUser($loser->user, new PreReservationCancelledNotification($loser));
                    $push->sendToUser(
                        userId: $loser->user_id,
                        title:  '⏳ Pre-reserva liberada',
                        body:   'Otro usuario completó el pago primero y se liberó tu pre-reserva. Puedes intentar reservar otro horario.',
                        data:   ['url' => '/reservations'],
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("Notif/Push losers error (markAsPaid reserva #{$reservation->id}): {$e->getMessage()}");
            }
        }

        // Notificación DB + Push al cliente
        try {
            $notif->notifyUser($reservation->user, new PaymentApprovedNotification($reservation));
            $push->sendToUser(
                userId: $reservation->user_id,
                title:  '✅ Pago aprobado',
                body:   "Tu pago para la reserva #{$reservation->confirmation_code} fue aprobado. ¡Nos vemos en la cancha!",
                data:   ['url' => "/reservations/{$reservation->id}"],
            );
        } catch (\Throwable $e) {
            Log::warning("Notif/Push cliente error (markAsPaid reserva #{$reservation->id}): {$e->getMessage()}");
        }

        return redirect()->back();
    }

    /**
     * Listado de pagos pendientes de revisión.
     * Incluye también reservas canceladas que tenían comprobante subido.
     */
    public function pendingPayments(): Response
    {
        $reservations = Reservation::with('user')
            ->where('payment_status', 'payment_review')
            ->whereNotNull('payment_proof')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Reservation $r) => [
                'id'                 => $r->id,
                'confirmation_code'  => $r->confirmation_code,
                'date'               => $r->date_formatted,
                'start_time'         => $r->start_time_formatted,
                'end_time'           => $r->end_time_formatted,
                'total_price'        => (float) $r->total_price,
                'payment_method'     => $r->payment_method,
                'payment_reference'  => $r->payment_reference,
                'payment_proof_url'  => $r->payment_proof
                    ? Storage::url($r->payment_proof)
                    : null,
                'submitted_at'       => $r->updated_at->format('d/m/Y H:i'),
                'is_cancelled'       => $r->status === 'cancelled',
                'user' => [
                    'name'  => $r->user->name,
                    'email' => $r->user->email,
                    'phone' => $r->user->phone,
                ],
            ]);

        // Reservas canceladas que aún tienen comprobante (para que admin las descarte)
        $cancelledWithProof = Reservation::with('user')
            ->where('status', 'cancelled')
            ->whereNotNull('payment_proof')
            ->whereIn('payment_status', ['payment_review', 'unpaid', 'pending_payment'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Reservation $r) => [
                'id'                 => $r->id,
                'confirmation_code'  => $r->confirmation_code,
                'date'               => $r->date_formatted,
                'start_time'         => $r->start_time_formatted,
                'end_time'           => $r->end_time_formatted,
                'total_price'        => (float) $r->total_price,
                'payment_method'     => $r->payment_method,
                'payment_reference'  => $r->payment_reference,
                'payment_proof_url'  => $r->payment_proof
                    ? Storage::url($r->payment_proof)
                    : null,
                'submitted_at'       => $r->updated_at->format('d/m/Y H:i'),
                'is_cancelled'       => true,
                'user' => [
                    'name'  => $r->user->name,
                    'email' => $r->user->email,
                    'phone' => $r->user->phone,
                ],
            ]);

        // Combinar: primero las activas (por revisar), luego las canceladas
        $all = $reservations->concat($cancelledWithProof)->unique('id')->values();

        return Inertia::render('admin/PendingPayments', [
            'reservations' => $all,
        ]);
    }

    /**
     * Descartar comprobante de una reserva cancelada (limpia el proof y la saca de la lista).
     */
    public function dismissPayment(Reservation $reservation): RedirectResponse
    {
        // Solo permitir descartar si la reserva está cancelada
        if ($reservation->status !== 'cancelled') {
            return redirect()->back()->withErrors(['message' => 'Solo se pueden descartar comprobantes de reservas canceladas.']);
        }

        // Eliminar el archivo de comprobante si existe
        if ($reservation->payment_proof) {
            Storage::delete($reservation->payment_proof);
        }

        $reservation->update([
            'payment_proof'     => null,
            'payment_reference' => null,
        ]);

        return redirect()->back()->with('flash', [
            'type'    => 'success',
            'message' => 'Comprobante descartado.',
        ]);
    }

    /**
     * Aprobar pago de una reserva.
     */
    public function approvePayment(Reservation $reservation, PushNotificationService $push, TimeSlotService $slotService, NotificationService $notif): RedirectResponse
    {
        $reservation->load('user');
        $reservation->update([
            'payment_status' => 'paid',
            'status'         => 'confirmed',
        ]);

        // Marcar los slots como reservados definitivamente y cancelar otras pre-reservas
        $slotIds = $reservation->timeSlots()->pluck('time_slots.id')->toArray();
        $cancelledReservationIds = $slotService->markAsReserved($slotIds, $reservation->id);

        // Broadcast en tiempo real
        broadcast(new PaymentApproved($reservation))->toOthers();

        // Notificar a clientes cuyas pre-reservas fueron canceladas por este pago (DB + Push)
        if (! empty($cancelledReservationIds)) {
            try {
                $losers = Reservation::with('user')
                    ->whereIn('id', $cancelledReservationIds)
                    ->where('id', '!=', $reservation->id)
                    ->get();

                foreach ($losers as $loser) {
                    if (! $loser->user) continue;

                    $notif->notifyUser($loser->user, new PreReservationCancelledNotification($loser));
                    $push->sendToUser(
                        userId: $loser->user_id,
                        title:  '⏳ Pre-reserva liberada',
                        body:   'Otro usuario completó el pago primero y se liberó tu pre-reserva. Puedes intentar reservar otro horario.',
                        data:   ['url' => '/reservations'],
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Notif/Push losers error (aprobación reserva #{$reservation->id}): {$e->getMessage()}");
            }
        }

        // Notificación DB + Push al cliente
        try {
            $notif->notifyUser($reservation->user, new PaymentApprovedNotification($reservation));
            $push->sendToUser(
                userId: $reservation->user_id,
                title:  '✅ Pago aprobado',
                body:   "Tu pago para la reserva #{$reservation->confirmation_code} fue aprobado. ¡Nos vemos en la cancha!",
                data:   ['url' => "/reservations/{$reservation->id}"],
            );
        } catch (\Throwable $e) {
            Log::warning("Notif/Push cliente error (aprobación reserva #{$reservation->id}): {$e->getMessage()}");
        }

        return redirect()->back()->with('flash', [
            'type'    => 'success',
            'message' => 'Pago aprobado exitosamente.',
        ]);
    }

    /**
     * Rechazar pago de una reserva.
     */
    public function rejectPayment(Request $request, Reservation $reservation, PushNotificationService $push, NotificationService $notif): RedirectResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $reservation->load('user');
        $reservation->update([
            'payment_status'     => 'rejected',
            'payment_proof'      => null,
            'payment_reference'  => null,
        ]);

        // Broadcast en tiempo real
        broadcast(new PaymentRejected($reservation))->toOthers();

        // Notificación DB + Push al cliente
        try {
            $notif->notifyUser($reservation->user, new PaymentRejectedNotification($reservation));
            $push->sendToUser(
                userId: $reservation->user_id,
                title:  '❌ Comprobante rechazado',
                body:   "Tu comprobante para la reserva #{$reservation->confirmation_code} fue rechazado. Por favor sube uno nuevo.",
                data:   ['url' => "/reservations/{$reservation->id}/payment"],
            );
        } catch (\Throwable $e) {
            Log::warning("Notif/Push cliente error (rechazo reserva #{$reservation->id}): {$e->getMessage()}");
        }

        return redirect()->back()->with('flash', [
            'type'    => 'error',
            'message' => 'Pago rechazado.',
        ]);
    }

    /**
     * Bloquear slots (admin).
     */
    public function blockSlots(Request $request): JsonResponse
    {
        $request->validate([
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'integer|exists:time_slots,id',
            'reason'   => 'nullable|string|max:255',
        ]);

        $this->slotService->blockSlots($request->slot_ids, $request->reason ?? '');

        return response()->json(['message' => 'Horarios bloqueados exitosamente.']);
    }

    /**
     * Desbloquear slots (admin).
     */
    public function unblockSlots(Request $request): JsonResponse
    {
        $request->validate([
            'slot_ids' => 'required|array|min:1',
            'slot_ids.*' => 'integer|exists:time_slots,id',
        ]);

        $this->slotService->releaseSlots($request->slot_ids);

        return response()->json(['message' => 'Horarios desbloqueados exitosamente.']);
    }
}
