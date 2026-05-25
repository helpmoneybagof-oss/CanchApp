<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
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
        $to = $request->input('to', Carbon::now()->toDateString());

        // Validar rango
        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        if ($fromDate->gt($toDate)) {
            $fromDate = $toDate->copy()->startOfMonth();
            $from = $fromDate->toDateString();
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
            $d = $date->toDateString();
            $income = Reservation::forDate($d)
                ->whereIn('status', ['confirmed', 'completed'])
                ->sum('total_price');
            $count = Reservation::forDate($d)
                ->whereIn('status', ['confirmed', 'completed'])
                ->count();
            $dailyIncome[] = [
                'date' => $date->format('d/m'),
                'income' => (float) $income,
                'count' => $count,
            ];
        }

        // ── Reservas por día de la semana ──
        $byWeekday = [];
        $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        foreach ($days as $i => $day) {
            $byWeekday[] = [
                'day' => $day,
                'count' => Reservation::whereBetween('date', [$from, $to])
                    ->whereIn('status', ['confirmed', 'completed'])
                    ->whereRaw('DAYOFWEEK(date) = ?', [$i + 1])
                    ->count(),
            ];
        }

        // ── Top clientes que más reservan ──
        $topClients = Reservation::select('user_id')
            ->selectRaw('COUNT(*) as total_reservations')
            ->selectRaw('SUM(total_price) as total_spent')
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->with('user:id,name,email')
            ->groupBy('user_id')
            ->orderByDesc('total_reservations')
            ->limit(5)
            ->get()
            ->map(fn (Reservation $r) => [
                'name' => $r->user?->name ?? 'Desconocido',
                'email' => $r->user?->email ?? '',
                'total_reservations' => (int) $r->total_reservations,
                'total_spent' => (float) $r->total_spent,
            ]);

        // ── Distribución por hora del día (horas pico) ──
        $hourlyRows = Reservation::whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->selectRaw('HOUR(start_time) as hour, COUNT(*) as c')
            ->groupBy('hour')
            ->pluck('c', 'hour')
            ->toArray();

        $hourlyDistribution = [];
        for ($h = 0; $h < 24; $h++) {
            $hourlyDistribution[] = [
                'hour' => $h,
                'label' => sprintf('%d%s', $h % 12 === 0 ? 12 : $h % 12, $h < 12 ? 'a' : 'p'),
                'count' => (int) ($hourlyRows[$h] ?? 0),
            ];
        }

        // ── Breakdown de estado de pago ──
        $paymentBreakdownRows = Reservation::whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed'])
            ->selectRaw('payment_status, COUNT(*) as c')
            ->groupBy('payment_status')
            ->pluck('c', 'payment_status')
            ->toArray();

        $paymentBreakdown = [
            ['key' => 'paid',            'label' => 'Pagadas',         'count' => (int) ($paymentBreakdownRows['paid'] ?? 0)],
            ['key' => 'payment_review',  'label' => 'En revisión',     'count' => (int) ($paymentBreakdownRows['payment_review'] ?? 0)],
            ['key' => 'pending_payment', 'label' => 'Pago pendiente',  'count' => (int) ($paymentBreakdownRows['pending_payment'] ?? 0)],
            ['key' => 'unpaid',          'label' => 'Sin pagar',       'count' => (int) ($paymentBreakdownRows['unpaid'] ?? 0)],
            ['key' => 'rejected',        'label' => 'Rechazadas',      'count' => (int) ($paymentBreakdownRows['rejected'] ?? 0)],
        ];

        // ── Comparativa con período anterior (mismo tamaño) ──
        $rangeDays = $fromDate->diffInDays($toDate) + 1;
        $prevToDate = $fromDate->copy()->subDay();
        $prevFromDate = $prevToDate->copy()->subDays($rangeDays - 1);
        $prevIncome = (float) Reservation::whereBetween('date', [$prevFromDate->toDateString(), $prevToDate->toDateString()])
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('total_price');
        $prevReservations = (int) Reservation::whereBetween('date', [$prevFromDate->toDateString(), $prevToDate->toDateString()])
            ->whereIn('status', ['confirmed', 'completed'])
            ->count();

        $incomeChange = $prevIncome > 0 ? round((($totalIncome - $prevIncome) / $prevIncome) * 100, 1) : null;
        $reservationsChange = $prevReservations > 0 ? round((($totalReservations - $prevReservations) / $prevReservations) * 100, 1) : null;

        // ── Últimas reservas del período ──
        $recentReservations = Reservation::with('user')
            ->whereBetween('date', [$from, $to])
            ->whereIn('status', ['confirmed', 'completed', 'cancelled'])
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->limit(10)
            ->get()
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'user_name' => $r->user->name,
                'date' => $r->date_formatted,
                'start_time' => $r->start_time_formatted,
                'end_time' => $r->end_time_formatted,
                'status' => $r->status,
                'payment_status' => $r->payment_status,
                'total_price' => (float) $r->total_price,
                'confirmation_code' => $r->confirmation_code,
            ]);

        return Inertia::render('admin/Reports', [
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => [
                'total_reservations' => $totalReservations,
                'total_income' => (float) $totalIncome,
                'court_income' => (float) $courtIncome,
                'pending_payments' => $pendingPayments,
                'cancelled_reservations' => $cancelledReservations,
            ],
            'comparison' => [
                'prev_income' => $prevIncome,
                'prev_reservations' => $prevReservations,
                'income_change' => $incomeChange,
                'reservations_change' => $reservationsChange,
            ],
            'daily_income' => $dailyIncome,
            'by_weekday' => $byWeekday,
            'hourly_distribution' => $hourlyDistribution,
            'payment_breakdown' => $paymentBreakdown,
            'top_clients' => $topClients,
            'recent_reservations' => $recentReservations,
        ]);
    }
}
