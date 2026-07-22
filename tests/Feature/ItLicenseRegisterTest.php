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
                ->where('summary.seats_available', 5)
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
