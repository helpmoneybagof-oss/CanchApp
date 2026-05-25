<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $today = Carbon::today()->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $reservationsToday = Reservation::forDate($today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $reservationsPending = Reservation::where('payment_status', 'unpaid')
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();

        $incomeMonth = Reservation::whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereIn('status', ['confirmed', 'completed'])
            ->sum('total_price');

        // Próximas reservas del día
        $upcomingToday = Reservation::with('user')
            ->forDate($today)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderBy('start_time')
            ->get()
            ->map(fn(Reservation $r) => [
                'id'           => $r->id,
                'user_name'    => $r->user->name,
                'start_time'   => $r->start_time_formatted,
                'end_time'     => $r->end_time_formatted,
                'status'       => $r->status,
                'payment_status' => $r->payment_status,
                'total_price'  => (float) $r->total_price,
                'confirmation_code' => $r->confirmation_code,
            ]);

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'reservations_today'    => $reservationsToday,
                'reservations_pending'  => $reservationsPending,
                'income_month'          => (float) $incomeMonth,
            ],
            'upcoming_today' => $upcomingToday,
        ]);
    }
}
