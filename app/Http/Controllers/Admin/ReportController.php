<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\ReservationItem;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->input('from', Carbon::now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   Carbon::now()->toDateString());

        // Validar rango
        $fromDate = Carbon::parse($from);
        $toDate   = Carbon::parse($to);
        if ($fromDate->gt($toDate)) {
            $fromDate = $toDate->copy()->startOfMonth();
            $from     = $fromDate->toDateString();
        }

        // ── Resumen general ──
        $totalReservations = Reservation::whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();

        $totalIncome = Reservation::whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('total_price');

        $courtIncome = Reservation::whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('court_price');

        $consumablesIncome = Reservation::whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('consumables_price');

        $pendingPayments = Reservation::whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->where('payment_status', 'unpaid')
            ->count();

        $cancelledReservations = Reservation::whereBetween('date', [$from, $to])
            ->where('status', 'cancelled')
            ->count();

        // ── Ingresos por día (para gráfica de línea) ──
        $period = CarbonPeriod::create($from, $to);
        $dailyIncome = [];
        foreach ($period as $date) {
            $d       = $date->toDateString();
            $income  = Reservation::forDate($d)
                ->whereIn('status', ['confirmed', 'completed'])
                ->sum('total_price');
            $count   = Reservation::forDate($d)
                ->whereIn('status', ['confirmed', 'completed'])
                ->count();
            $dailyIncome[] = [
                'date'   => $date->format('d/m'),
                'income' => (float) $income,
                'count'  => $count,
            ];
        }

        // ── Reservas por día de la semana ──
        $byWeekday = [];
        $days      = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        foreach ($days as $i => $day) {
            $byWeekday[] = [
                'day'   => $day,
                'count' => Reservation::whereBetween('date', [$from, $to])
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->whereRaw('DAYOFWEEK(date) = ?', [$i + 1])
                    ->count(),
            ];
        }

        // ── Top consumibles más vendidos ──
        $topProducts = ReservationItem::select('product_id')
            ->selectRaw('SUM(quantity) as total_qty')
            ->selectRaw('SUM(subtotal) as total_revenue')
            ->whereHas('reservation', fn ($q) => $q
                ->whereBetween('date', [$from, $to])
                ->whereIn('status', ['confirmed', 'completed'])
            )
            ->with('product:id,name')
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(fn ($item) => [
                'name'          => $item->product?->name ?? 'Desconocido',
                'total_qty'     => (int) $item->total_qty,
                'total_revenue' => (float) $item->total_revenue,
            ]);

        // ── Últimas reservas del período ──
        $recentReservations = Reservation::with('user')
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed', 'cancelled'])
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->limit(10)
            ->get()
            ->map(fn (Reservation $r) => [
                'id'                => $r->id,
                'user_name'         => $r->user->name,
                'date'              => $r->date_formatted,
                'start_time'        => $r->start_time_formatted,
                'end_time'          => $r->end_time_formatted,
                'status'            => $r->status,
                'payment_status'    => $r->payment_status,
                'total_price'       => (float) $r->total_price,
                'confirmation_code' => $r->confirmation_code,
            ]);

        return Inertia::render('admin/Reports', [
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => [
                'total_reservations'    => $totalReservations,
                'total_income'          => (float) $totalIncome,
                'court_income'          => (float) $courtIncome,
                'consumables_income'    => (float) $consumablesIncome,
                'pending_payments'      => $pendingPayments,
                'cancelled_reservations' => $cancelledReservations,
            ],
            'daily_income'        => $dailyIncome,
            'by_weekday'          => $byWeekday,
            'top_products'        => $topProducts,
            'recent_reservations' => $recentReservations,
        ]);
    }
}
