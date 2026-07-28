<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ItLicense;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItLicenseRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_register_view_filter_and_update_an_it_license(): void
    {
        [$user] = $this->userWithAccess('edit');

        $this->actingAs($user)->post(route('it-licenses.store'), [
            'license_code' => 'LIC-M365-001',
            'software_name' => 'Microsoft 365 Business',
            'vendor' => 'Microsoft',
            'license_type' => 'subscription',
            'license_key' => 'SECRET-KEY-1234',
            'seats_total' => 25,
            'seats_assigned' => 20,
            'assigned_to' => 'IT Administrator',
            'department' => 'IT',
            'purchase_date' => '2026-01-01',
            'expiry_date' => today()->addDays(20)->toDateString(),
            'auto_renew' => true,
            'renewal_cost' => 5000,
            'supplier' => 'Software Supplier',
            'purchase_reference' => 'PO-1001',
            'active' => true,
            'remarks' => 'Annual corporate subscription',
        ])->assertRedirect();

        $license = ItLicense::firstOrFail();
        $this->assertSame('SECRET-KEY-1234', $license->license_key);
        $this->assertStringNotContainsString('SECRET-KEY-1234', (string) $license->getRawOriginal('license_key'));
        $this->assertSame('expiring_soon', $license->status());

        $this->get(route('it-licenses.index', ['status' => 'expiring_soon']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItLicenses/Index')
                ->where('summary.total', 1)
                ->where('summary.users_assigned', 20)
                ->where('assignmentChart.0.software_name', 'Microsoft 365 Business')
                ->where('assignmentChart.0.users_assigned', 20)
                ->where('assignmentChart.0.total_licenses', 25)
                ->where('licenses.data.0.assigned_to', 'IT Administrator')
                ->has('licenses.data', 1));

        $this->get(route('it-licenses.show', $license))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItLicenses/Show')
                ->where('license.license_key_masked', '•••••••••••1234'));

        $this->patch(route('it-licenses.update', $license), [
            'license_code' => 'LIC-M365-001',
            'software_name' => 'Microsoft 365 Business Premium',
            'vendor' => 'Microsoft',
            'license_type' => 'subscription',
            'license_key' => 'SECRET-KEY-1234',
            'seats_total' => 30,
            'seats_assigned' => 21,
            'assigned_to' => 'IT Administrator',
            'department' => 'IT',
            'purchase_date' => '2026-01-01',
            'expiry_date' => today()->addYear()->toDateString(),
            'auto_renew' => true,
            'renewal_cost' => 6000,
            'supplier' => 'Software Supplier',
            'purchase_reference' => 'PO-1001',
            'active' => true,
            'remarks' => 'Renewed',
        ])->assertRedirect(route('it-licenses.show', $license));

        $this->assertSame('Microsoft 365 Business Premium', $license->refresh()->software_name);
        $this->assertSame('active', $license->status());
    }

    public function test_seat_assignment_cannot_exceed_purchased_seats(): void
    {
        [$user] = $this->userWithAccess('edit');

        $this->actingAs($user)->post(route('it-licenses.store'), [
            'license_code' => 'LIC-INVALID-001',
            'software_name' => 'Invalid Licence',
            'license_type' => 'subscription',
            'seats_total' => 5,
            'seats_assigned' => 6,
            'auto_renew' => false,
            'active' => true,
        ])->assertSessionHasErrors('seats_assigned');
    }

    public function test_it_dashboard_includes_live_licence_analytics(): void
    {
        [$user, $branch] = $this->userWithAccess('read');

        ItLicense::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'license_code' => 'LIC-M365-DASH',
            'software_name' => 'Microsoft 365',
            'vendor' => 'Microsoft',
            'license_type' => 'subscription',
            'license_key' => 'DASHBOARD-KEY-1234',
            'seats_total' => 20,
            'seats_assigned' => 15,
            'expiry_date' => today()->addDays(20),
            'renewal_cost' => 2400,
            'auto_renew' => true,
            'active' => true,
        ]);
        ItLicense::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'license_code' => 'LIC-ADOBE-DASH',
            'software_name' => 'Adobe Creative Cloud',
            'vendor' => 'Adobe',
            'license_type' => 'subscription',
            'seats_total' => 10,
            'seats_assigned' => 4,
            'expiry_date' => today()->subDay(),
            'renewal_cost' => 1800,
            'active' => true,
        ]);

        $this->actingAs($user)->get(route('it-assets.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItAssets/Section')
                ->where('licenseDashboard.summary.total_licenses', 2)
                ->where('licenseDashboard.summary.total_seats', 30)
                ->where('licenseDashboard.summary.assigned_seats', 19)
                ->where('licenseDashboard.summary.available_seats', 11)
                ->where('licenseDashboard.summary.expiring_soon', 1)
                ->where('licenseDashboard.summary.expired', 1)
                ->where('licenseDashboard.seat_utilisation.0.label', 'Microsoft 365')
                ->where('licenseDashboard.seat_utilisation.0.percent', 75)
                ->where('licenseDashboard.licenses.1.software', 'Microsoft 365')
                ->where('licenseDashboard.licenses.1.status', 'Expiring soon')
                ->where('licenseDashboard.licenses.1.seats_available', 5)
                ->where('licenseDashboard.licenses.1.license_key_reference', 'LIC-M365-DASH - key ending 1234')
                ->where('licenseDashboard.upcoming_renewals.0.code', 'LIC-M365-DASH')
                ->has('licenseDashboard.expiry_timeline', 12));
    }

    public function test_read_only_user_can_view_but_cannot_manage_it_licenses(): void
    {
        [$viewer, $branch] = $this->userWithAccess('read');
        $license = ItLicense::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'license_code' => 'LIC-VIEW-001',
            'software_name' => 'Viewer Licence',
            'license_type' => 'perpetual',
            'seats_total' => 1,
            'seats_assigned' => 0,
            'active' => true,
        ]);

        $this->actingAs($viewer)->get(route('it-licenses.index'))->assertOk();
        $this->get(route('it-licenses.show', $license))->assertOk();
        $this->get(route('it-licenses.create'))->assertForbidden();
        $this->get(route('it-licenses.edit', $license))->assertForbidden();
    }

    private function userWithAccess(string $level): array
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $role = $level === 'read' ? 'viewer' : 'supervisor';
        $user = User::factory()->create([
            'role' => $role,
            'permissions' => AccessMatrix::permissionsForRole($role),
        ]);
        $user->branches()->attach($branch, ['access_level' => $level, 'is_default' => true]);

        return [$user, $branch];
    }
}
