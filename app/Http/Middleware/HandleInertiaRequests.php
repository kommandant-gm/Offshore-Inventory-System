<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'username' => $request->user()->username,
                        'email' => $request->user()->email,
                        'role' => $request->user()->role,
                        'permissions' => $request->user()->resolvedPermissions(),
                        'can' => [
                            'assistant_read' => $request->user()->canRead('assistant'),
                            'anomalies_read' => $request->user()->canRead('anomalies'),
                            'settings_read' => $request->user()->canRead('settings'),
                            'settings_edit' => $request->user()->canEdit('settings'),
                        ],
                    ]
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
