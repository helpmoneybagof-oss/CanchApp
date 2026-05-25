<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CourtController extends Controller
{
    public function index(): Response
    {
        $courts = Court::withCount(['reservations', 'timeSlots'])
            ->orderBy('name')
            ->get()
            ->map(fn (Court $c) => [
                'id'               => $c->id,
                'name'             => $c->name,
                'type'             => $c->type,
                'price_per_hour'   => (float) $c->price_per_hour,
                'description'      => $c->description,
                'image_url'        => $c->image_url,
                'capacity'         => $c->capacity,
                'surface'          => $c->surface,
                'start_hour'       => $c->start_hour,
                'end_hour'         => $c->end_hour,
                'active'           => $c->active,
                'schedule_label'   => $c->schedule_label,
                'reservations_count' => $c->reservations_count,
            ]);

        return Inertia::render('admin/Courts', [
            'courts' => $courts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'type'           => 'nullable|string|max:50',
            'price_per_hour' => 'required|numeric|min:0',
            'description'    => 'nullable|string|max:500',
            'capacity'       => 'nullable|integer|min:1',
            'surface'        => 'nullable|string|max:50',
            'start_hour'     => 'required|integer|min:0|max:23',
            'end_hour'       => 'required|integer|min:1|max:24|gt:start_hour',
            'active'         => 'boolean',
            'image'          => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('courts', 'public');
        } else {
            unset($data['image']);
        }

        Court::create($data);

        return redirect()->route('admin.courts.index')
            ->with('flash', ['type' => 'success', 'message' => 'Cancha creada exitosamente.']);
    }

    public function update(Request $request, Court $court): RedirectResponse
    {
        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'type'           => 'nullable|string|max:50',
            'price_per_hour' => 'required|numeric|min:0',
            'description'    => 'nullable|string|max:500',
            'capacity'       => 'nullable|integer|min:1',
            'surface'        => 'nullable|string|max:50',
            'start_hour'     => 'required|integer|min:0|max:23',
            'end_hour'       => 'required|integer|min:1|max:24|gt:start_hour',
            'active'         => 'boolean',
            'image'          => 'nullable|image|max:2048',
            'remove_image'   => 'nullable|boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($court->image) {
                Storage::disk('public')->delete($court->image);
            }
            $data['image'] = $request->file('image')->store('courts', 'public');
        } elseif ($request->boolean('remove_image')) {
            if ($court->image) {
                Storage::disk('public')->delete($court->image);
            }
            $data['image'] = null;
        } else {
            unset($data['image']);
        }
        unset($data['remove_image']);

        $court->update($data);

        return redirect()->route('admin.courts.index')
            ->with('flash', ['type' => 'success', 'message' => 'Cancha actualizada exitosamente.']);
    }

    public function destroy(Court $court): RedirectResponse
    {
        if ($court->reservations()->whereIn('status', ['pending', 'confirmed'])->exists()) {
            return redirect()->back()
                ->withErrors(['message' => 'No se puede eliminar una cancha con reservas activas.']);
        }

        if ($court->image) {
            Storage::disk('public')->delete($court->image);
        }

        $court->delete();

        return redirect()->route('admin.courts.index')
            ->with('flash', ['type' => 'success', 'message' => 'Cancha eliminada.']);
    }

    public function toggleActive(Court $court): RedirectResponse
    {
        $court->update(['active' => !$court->active]);

        return redirect()->back()
            ->with('flash', ['type' => 'success', 'message' => 'Cancha ' . ($court->active ? 'activada' : 'desactivada') . '.']);
    }
}
