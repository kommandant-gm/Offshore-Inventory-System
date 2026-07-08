<?php

namespace App\Http\Controllers;

use App\Enums\LocationType;
use App\Http\Requests\StoreLocationRequest;
use App\Http\Requests\UpdateLocationRequest;
use App\Models\Location;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class LocationController extends Controller
{
    public function index(): Response
    {
        abort_unless(request()->user()?->canRead('locations'), 403);

        $locations = Location::query()
            ->with('parent')
            ->latest()
            ->get();

        return Inertia::render('Shared/Locations/Index', [
            'locations' => $locations->map(fn (Location $location) => [
                'id' => $location->id,
                'code' => $location->code,
                'name' => $location->name,
                'type' => $location->type->value,
                'parent_id' => $location->parent_id,
                'parent_name' => $location->parent?->name,
                'active' => $location->active,
            ]),
            'locationOptions' => $locations->map(fn (Location $location) => [
                'value' => $location->id,
                'label' => "{$location->code} - {$location->name}",
            ]),
            'typeOptions' => LocationType::options(),
        ]);
    }

    public function store(StoreLocationRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $location = Location::create($request->validated());
        $auditLogger->record(
            module: 'locations',
            event: 'created',
            summary: "Created location {$location->code}.",
            auditable: $location,
            after: $location->only(['code', 'name', 'type', 'parent_id', 'active']),
            user: $request->user(),
            request: $request,
        );

        return back()->with('success', 'Location created.');
    }

    public function update(UpdateLocationRequest $request, Location $location, AuditLogger $auditLogger): RedirectResponse
    {
        $before = $location->only(['code', 'name', 'type', 'parent_id', 'active']);
        $location->update($request->validated());
        $auditLogger->record(
            module: 'locations',
            event: 'updated',
            summary: "Updated location {$location->code}.",
            auditable: $location,
            before: $before,
            after: $location->fresh()->only(['code', 'name', 'type', 'parent_id', 'active']),
            user: $request->user(),
            request: $request,
        );

        return back()->with('success', 'Location updated.');
    }
}
