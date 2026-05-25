<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Events\ReservationCancelled as ReservationCancelledEvent;
use App\Events\ReservationCreated as ReservationCreatedEvent;
use App\Jobs\SendReservationCancelled;
use App\Jobs\SendReservationConfirmed;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\TimeSlot;
use App\Models\User;
use App\Notifications\ReservationCreatedNotification;
use App\Notifications\AdminReservationCancelledNotification;
use App\Services\PushNotificationService;
use App\Services\NotificationService;
use App\Services\TimeSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function __construct(private TimeSlotService $slotService) {}

    /**
     * Listado de reservas activas del cliente.
     */
    public function index(Request $request): Response
    {
        $reservations = Reservation::with('court')
            ->where('user_id', $request->user()->id)
            ->upcoming()
            ->get()
            ->map(fn (Reservation $r) => [
                'id'               => $r->id,
                'date'             => $r->date_formatted,
                'date_raw'         => $r->date->format('Y-m-d'),
                'start_time'       => $r->start_time_formatted,
                'end_time'         => $r->end_time_formatted,
                'duration_hours'   => $r->duration_hours,
                'status'           => $r->status,
                'payment_status'   => $r->payment_status,
                'total_price'      => (float) $r->total_price,
                'confirmation_code' => $r->confirmation_code,
                'can_cancel'       => $r->canBeCancelledByClient(),
                'court_name'       => $r->court?->name,
            ]);

        return Inertia::render('client/Reservations', [
            'reservations' => $reservations,
        ]);
    }

    /**
     * Historial de reservas pasadas y canceladas.
     */
    public function history(Request $request): Response
    {
        $reservations = Reservation::with('court')
            ->where('user_id', $request->user()->id)
            ->past()
            ->get()
            ->map(fn (Reservation $r) => [
                'id'               => $r->id,
                'date'             => $r->date_formatted,
                'start_time'       => $r->start_time_formatted,
                'end_time'         => $r->end_time_formatted,
                'duration_hours'   => $r->duration_hours,
                'status'           => $r->status,
                'payment_status'   => $r->payment_status,
                'total_price'      => (float) $r->total_price,
                'confirmation_code' => $r->confirmation_code,
                'court_name'       => $r->court?->name,
            ]);

        return Inertia::render('client/ReservationHistory', [
            'reservations' => $reservations,
        ]);
    }

    /**
     * Vista de checkout con resumen de la reserva.
     * Lee slot_ids del carrito de sesión (flujo principal).
     */
    public function checkout(Request $request): Response
    {
        // Leer del carrito de sesión (flujo normal desde calendario → catálogo → checkout)
        $cart    = $request->session()->get('cart', ['slot_ids' => [], 'items' => []]);
        $slotIds = $cart['slot_ids'] ?? [];

        if (empty($slotIds)) {
            return Inertia::render('client/Checkout', [
                'slots'             => [],
                'items'             => [],
                'court_name'        => null,
                'court_price'       => 0,
                'consumables_price' => 0,
                'total_price'       => 0,
            ]);
        }

        $slots           = TimeSlot::with('court')->whereIn('id', $slotIds)->orderBy('start_time')->get();
        $totalCourtPrice = $slots->sum(fn ($s) => (float) $s->price);
        $courtName       = $slots->first()?->court?->name ?? 'Cancha';

        // Cargar items del carrito desde la sesión
        $cart            = $request->session()->get('cart', ['slot_ids' => [], 'items' => []]);
        $cartItems       = $cart['items'] ?? [];
        $productIds      = array_keys($cartItems);
        $products        = $productIds
            ? \App\Models\Product::whereIn('id', $productIds)->active()->get()->keyBy('id')
            : collect();

        $items = [];
        $consumablesPrice = 0;
        foreach ($cartItems as $productId => $qty) {
            $product = $products->get($productId);
            if (!$product) continue;
            $subtotal          = (float) $product->price * $qty;
            $consumablesPrice += $subtotal;
            $items[] = [
                'id'        => $product->id,
                'name'      => $product->name,
                'price'     => (float) $product->price,
                'quantity'  => $qty,
                'subtotal'  => $subtotal,
                'image_url' => $product->image_url,
            ];
        }

        return Inertia::render('client/Checkout', [
            'slots' => $slots->map(fn ($s) => [
                'id'              => $s->id,
                'date'            => $s->date->format('d/m/Y'),
                'start_formatted' => $s->start_time_formatted,
                'end_formatted'   => $s->end_time_formatted,
                'price'           => (float) $s->price,
            ]),
            'court_name'        => $courtName,
            'items'             => $items,
            'court_price'       => $totalCourtPrice,
            'consumables_price' => $consumablesPrice,
            'total_price'       => $totalCourtPrice + $consumablesPrice,
        ]);
    }

    /**
     * Confirmar y crear la reserva.
     * Lee slot_ids del carrito de sesión.
     */
    public function store(Request $request): mixed
    {
        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        // Leer slot_ids del carrito de sesión
        $cart    = $request->session()->get('cart', ['slot_ids' => [], 'items' => []]);
        $slotIds = $cart['slot_ids'] ?? [];

        if (empty($slotIds)) {
            return response()->json(['message' => 'No hay horarios seleccionados.'], 422);
        }

        $user = $request->user();

        // Verificar límite de 5 reservas activas
        $activeCount = Reservation::where('user_id', $user->id)->active()->count();
        if ($activeCount >= 5) {
            return response()->json([
                'message' => 'No puedes tener más de 5 reservas activas al mismo tiempo.',
            ], 422);
        }

        // Verificar disponibilidad en tiempo real
        if (! $this->slotService->areSlotsAvailable($slotIds)) {
            return response()->json([
                'message' => 'Uno o más horarios seleccionados ya no están disponibles. Por favor selecciona otro horario.',
            ], 422);
        }

        $slots      = TimeSlot::with('court')->whereIn('id', $slotIds)->orderBy('start_time')->get();
        $courtPrice = $slots->sum(fn ($s) => (float) $s->price);

        // Leer items del carrito desde sesión
        $cart         = $request->session()->get('cart', ['slot_ids' => [], 'items' => []]);
        $cartItems    = $cart['items'] ?? [];
        $productIds   = array_keys($cartItems);
        $products     = $productIds
            ? Product::whereIn('id', $productIds)->active()->get()->keyBy('id')
            : collect();

        $consumablesPrice = 0;
        $itemsToCreate    = [];
        foreach ($cartItems as $productId => $qty) {
            $product = $products->get($productId);
            if (!$product) continue;
            $subtotal          = (float) $product->price * $qty;
            $consumablesPrice += $subtotal;
            $itemsToCreate[]   = [
                'product_id' => $product->id,
                'quantity'   => $qty,
                'unit_price' => (float) $product->price,
                'subtotal'   => $subtotal,
            ];
        }

        $totalPrice = $courtPrice + $consumablesPrice;

        try {
            DB::beginTransaction();

            // Crear la reserva
            $reservation = Reservation::create([
                'user_id'           => $user->id,
                'court_id'          => $slots->first()->court_id,
                'date'              => $slots->first()->date->format('Y-m-d'),
                'start_time'        => $slots->first()->start_time,
                'end_time'          => $slots->last()->end_time,
                'duration_hours'    => $slots->count(),
                'status'            => 'confirmed',
                'payment_status'    => 'unpaid',
                'court_price'       => $courtPrice,
                'consumables_price' => $consumablesPrice,
                'total_price'       => $totalPrice,
                'notes'             => $request->notes,
            ]);

            // Asociar los slots
            $reservation->timeSlots()->attach($slotIds);

            // Guardar items de consumibles
            foreach ($itemsToCreate as $item) {
                $reservation->items()->create($item);
                // Descontar stock
                Product::where('id', $item['product_id'])
                    ->decrement('stock', $item['quantity']);
            }

            // Marcar slots como pre-reservados (pago pendiente)
            $this->slotService->markAsPreReserved($slotIds);

            DB::commit();

            // Limpiar carrito de la sesión
            $request->session()->forget('cart');

            // Enviar correos de confirmación (async)
            SendReservationConfirmed::dispatch($reservation);

            // Broadcast en tiempo real
            $reservation->load('user');
            broadcast(new ReservationCreatedEvent($reservation))->toOthers();

            // Notificación DB + Push a todos los admins
            try {
                $notif = app(NotificationService::class);
                $push  = app(PushNotificationService::class);

                $notif->notifyAdmins(new ReservationCreatedNotification($reservation));

                $courtName = $reservation->court?->name ?? 'Cancha';
                $push->sendToAdmins(
                    title: '📅 Nueva reserva',
                    body:  "{$reservation->user->name} reservó {$courtName} el {$reservation->date_formatted}",
                    data:  ['url' => "/admin/reservations/{$reservation->id}"],
                );

            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Notif/Push admin error (nueva reserva #{$reservation->id}): {$e->getMessage()}");
            }

            return redirect()->route('reservations.payment', $reservation)
                ->with('flash', [
                    'type'              => 'success',
                    'message'           => '¡Reserva confirmada! Ahora completa tu pago con Nequi.',
                    'confirmation_code' => $reservation->confirmation_code,
                ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ocurrió un error al procesar tu reserva. Intenta nuevamente.',
            ], 500);
        }
    }

    /**
     * Detalle de una reserva del cliente.
     */
    public function show(Request $request, Reservation $reservation): Response
    {
        // Solo el dueño puede ver su reserva
        if ($reservation->user_id !== $request->user()->id) {
            abort(403);
        }

        $reservation->load(['court', 'items.product']);

        return Inertia::render('client/ReservationDetail', [
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
                'can_cancel'        => $reservation->canBeCancelledByClient(),
                'can_reschedule'    => $reservation->canBeRescheduledByClient(),
                'court_id'          => $reservation->court_id,
                'created_at'        => $reservation->created_at->format('d/m/Y'),
                'items'             => $reservation->items->map(fn ($item) => [
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
     * Cancelar reserva propia del cliente.
     */
    public function cancel(Request $request, Reservation $reservation): \Illuminate\Http\RedirectResponse
    {
        // Verificar que la reserva pertenece al cliente
        if ($reservation->user_id !== $request->user()->id) {
            return redirect()->back()->withErrors(['message' => 'No autorizado.']);
        }

        if (! $reservation->canBeCancelledByClient()) {
            return redirect()->back()->withErrors([
                'message' => 'Esta reserva no puede cancelarse. Debe hacerse con al menos 2 horas de anticipación.',
            ]);
        }

        try {
            DB::beginTransaction();

            $slotIds = $reservation->timeSlots()->pluck('time_slots.id')->toArray();

            // Cancelar primero, luego liberar slots
            // (releaseSlots cuenta reservas activas — si cancelamos primero, el conteo es correcto)
            $reservation->update([
                'status'              => 'cancelled',
                'cancellation_reason' => 'Cancelado por el cliente',
            ]);

            $this->slotService->releaseSlots($slotIds);

            DB::commit();

            // Enviar correos de cancelación (async)
            SendReservationCancelled::dispatch($reservation);

            // Broadcast en tiempo real
            broadcast(new ReservationCancelledEvent($reservation))->toOthers();

            // Notificación DB + Push a admins
            try {
                $reservation->load(['user', 'court']);
                $notif = app(NotificationService::class);
                $push  = app(PushNotificationService::class);

                $notif->notifyAdmins(new AdminReservationCancelledNotification($reservation));
                $push->sendToAdmins(
                    title: '❌ Reserva cancelada',
                    body:  "{$reservation->user->name} canceló la reserva #{$reservation->confirmation_code}.",
                    data:  ['url' => "/admin/reservations/{$reservation->id}"],
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Notif/Push admin error (cancelación cliente reserva #{$reservation->id}): {$e->getMessage()}");
            }

            return redirect()->route('reservations.index');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['message' => 'Error al cancelar la reserva.']);
        }
    }

    /**
     * Reprogramar reserva del cliente: cambia los slots por unos nuevos
     * consecutivos en la misma cancha, manteniendo la duración original.
     * Requiere al menos 3h antes del inicio del partido.
     */
    public function reschedule(Request $request, Reservation $reservation): \Illuminate\Http\RedirectResponse
    {
        if ($reservation->user_id !== $request->user()->id) {
            return redirect()->back()->withErrors(['message' => 'No autorizado.']);
        }

        if (! $reservation->canBeRescheduledByClient()) {
            return redirect()->back()->withErrors([
                'message' => 'No puedes reprogramar esta reserva. Debe hacerse con al menos 3 horas de anticipación al partido.',
            ]);
        }

        $request->validate([
            'slot_id' => 'required|integer|exists:time_slots,id',
        ]);

        $newStartSlot = TimeSlot::find($request->slot_id);
        if (! $newStartSlot || ! $newStartSlot->isBookable()) {
            return redirect()->back()->withErrors(['message' => 'El horario seleccionado ya no está disponible.']);
        }

        // Debe ser de la misma cancha
        if ($newStartSlot->court_id !== $reservation->court_id) {
            return redirect()->back()->withErrors([
                'message' => 'Solo puedes reprogramar dentro de la misma cancha.',
            ]);
        }

        $duration = (int) $reservation->duration_hours;

        // Buscar N slots consecutivos disponibles desde el seleccionado
        $candidateSlots = TimeSlot::where('court_id', $newStartSlot->court_id)
            ->where('date', $newStartSlot->date->format('Y-m-d'))
            ->whereIn('status', ['available', 'pre_reserved'])
            ->where('start_time', '>=', $newStartSlot->start_time)
            ->orderBy('start_time')
            ->limit($duration)
            ->get();

        if ($candidateSlots->count() < $duration) {
            return redirect()->back()->withErrors([
                'message' => "Tu reserva original es de {$duration} horas. No hay suficientes horarios consecutivos disponibles desde el seleccionado.",
            ]);
        }

        // Verificar continuidad
        for ($i = 1; $i < $candidateSlots->count(); $i++) {
            if ($candidateSlots[$i]->start_time !== $candidateSlots[$i - 1]->end_time) {
                return redirect()->back()->withErrors([
                    'message' => 'Los horarios disponibles no son consecutivos.',
                ]);
            }
        }

        try {
            DB::beginTransaction();

            $oldSlotIds = $reservation->timeSlots()->pluck('time_slots.id')->toArray();
            $newSlotIds = $candidateSlots->pluck('id')->toArray();

            $reservation->timeSlots()->detach($oldSlotIds);
            $reservation->timeSlots()->attach($newSlotIds);

            $reservation->update([
                'date'       => $newStartSlot->date->format('Y-m-d'),
                'start_time' => $candidateSlots->first()->start_time,
                'end_time'   => $candidateSlots->last()->end_time,
            ]);

            // Liberar slots antiguos y ocupar los nuevos según estado de pago
            $this->slotService->releaseSlots($oldSlotIds);
            if ($reservation->isPaid()) {
                $this->slotService->markAsReserved($newSlotIds, $reservation->id);
            } else {
                $this->slotService->markAsPreReserved($newSlotIds);
            }

            DB::commit();

            return redirect()->back()->with('flash', [
                'type'    => 'success',
                'message' => 'Reserva reprogramada con éxito.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->withErrors([
                'message' => 'Error al reprogramar la reserva.',
            ]);
        }
    }
}
