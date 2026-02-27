<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name'     => config('app.name'),
            'app_name' => Setting::getValue('court_name', config('app.name')),
            'app_address' => Setting::getValue('court_address', ''),
            'app_phone'   => Setting::getValue('contact_phone', ''),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'type'              => $request->session()->get('flash.type'),
                'message'           => $request->session()->get('flash.message'),
                'confirmation_code' => $request->session()->get('flash.confirmation_code'),
            ],
            'reverb' => [
                'key'    => config('broadcasting.connections.reverb.key'),
                'host'   => config('broadcasting.connections.reverb.options.host'),
                'port'   => config('broadcasting.connections.reverb.options.port'),
                'scheme' => config('broadcasting.connections.reverb.options.scheme'),
            ],
        ];
    }
}
