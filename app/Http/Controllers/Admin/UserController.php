<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $query = User::where('role', 'client')->orderBy('name');

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                    ->orWhere('email', 'like', '%'.$request->search.'%')
                    ->orWhere('phone', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        $users = $query->withCount([
            'reservations as total_reservations',
            'reservations as active_reservations' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed']),
        ])->paginate(20)->through(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'phone' => $u->phone,
            'active' => $u->active,
            'created_at' => $u->created_at->format('d/m/Y'),
            'total_reservations' => $u->total_reservations,
            'active_reservations' => $u->active_reservations,
        ]);

        return Inertia::render('admin/Users', [
            'users' => $users,
            'filters' => $request->only(['search', 'active']),
        ]);
    }

    public function show(User $user): Response
    {
        $reservations = Reservation::where('user_id', $user->id)
            ->orderByDesc('date')
            ->get()
            ->map(fn (Reservation $r) => [
                'id' => $r->id,
                'date' => $r->date_formatted,
                'start_time' => $r->start_time_formatted,
                'end_time' => $r->end_time_formatted,
                'status' => $r->status,
                'payment_status' => $r->payment_status,
                'total_price' => (float) $r->total_price,
                'confirmation_code' => $r->confirmation_code,
            ]);

        return Inertia::render('admin/UserDetail', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'active' => $user->active,
                'created_at' => $user->created_at->format('d/m/Y'),
            ],
            'reservations' => $reservations,
        ]);
    }

    public function toggleActive(User $user): RedirectResponse
    {
        // No desactivar admins
        if ($user->isAdmin()) {
            return redirect()->back()->withErrors(['message' => 'No se puede deshabilitar a un administrador.']);
        }

        $user->update(['active' => ! $user->active]);

        return redirect()->back()->with('flash', [
            'type' => 'success',
            'message' => $user->active ? 'Usuario activado.' : 'Usuario desactivado.',
        ]);
    }
}
