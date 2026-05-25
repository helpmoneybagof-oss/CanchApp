<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Services\TimeSlotService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(private TimeSlotService $slotService) {}

    /**
     * Vista principal del calendario.
     * Si hay una sola cancha activa, la usa directamente.
     * Si hay varias, envía la lista para que el cliente elija.
     */
    public function index(Request $request): Response
    {
        $today = Carbon::today()->toDateString();
        $courts = Court::active()->orderBy('name')->get(['id', 'name', 'type', 'price_per_hour', 'surface', 'capacity', 'start_hour', 'end_hour', 'image', 'description']);

        // Cancha seleccionada (por query param o la primera activa)
        $courtId = $request->input('court_id', $courts->first()?->id);
        $selectedCourt = $courts->firstWhere('id', $courtId) ?? $courts->first();

        $slots = $selectedCourt
            ? $this->slotService->getSlotsForDate($today, $selectedCourt)
            : [];

        return Inertia::render('client/Calendar', [
            'initialSlots' => $slots,
            'today' => $today,
            'courts' => $courts->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'type' => $c->type,
                'price_per_hour' => (float) $c->price_per_hour,
                'surface' => $c->surface,
                'capacity' => $c->capacity,
                'start_hour' => $c->start_hour,
                'end_hour' => $c->end_hour,
                'image_url' => $c->image_url,
                'description' => $c->description,
            ]),
            'selected_court_id' => $selectedCourt?->id,
        ]);
    }

    /**
     * API: retorna slots de un rango de fechas para una cancha.
     */
    public function slots(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date|after_or_equal:today',
            'to' => 'required|date|after_or_equal:from',
            'court_id' => 'required|exists:courts,id',
        ]);

        // Limitar el rango máximo a 31 días para evitar generación masiva de slots
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);
        if ($from->diffInDays($to) > 31) {
            abort(422, 'El rango máximo permitido es de 31 días.');
        }

        $court = Court::findOrFail($request->court_id);
        $slots = $this->slotService->getSlotsForDateRange($request->from, $request->to, $court);

        return response()->json($slots);
    }

    /**
     * API: retorna slots de un día específico para una cancha.
     */
    public function slotsForDay(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'court_id' => 'required|exists:courts,id',
        ]);

        $court = Court::findOrFail($request->court_id);
        $slots = $this->slotService->getSlotsForDate($request->date, $court);

        return response()->json($slots);
    }
}
