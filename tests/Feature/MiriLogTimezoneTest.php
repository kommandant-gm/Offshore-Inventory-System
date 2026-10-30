<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Branch, User};
use App\Support\AccessMatrix;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriLogTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_times_and_today_use_malaysia_midnight_without_changing_stored_utc(): void
    {
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-29 16:30:00', 'UTC'));
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);
        $this->actingAs($user);

        foreach (['2026-10-29 15:59:59', '2026-10-29 16:00:00', '2026-10-30 15:59:59', '2026-10-30 16:00:00'] as $time) {
            AuditLog::create(['branch_id' => $branch, 'user_id' => $user->id, 'module' => 'miri_inventory', 'event' => 'updated', 'summary' => 'Timezone boundary', 'created_at' => $time]);
        }

        $this->get(route('major-equipment.log'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('MajorEquipment/MiriLog')
            ->where('summary.today', 2)
            ->where('logs.data.0.created_at', '31 Oct 2026, 00:00')
            ->where('logs.data.1.created_at', '30 Oct 2026, 23:59')
            ->where('logs.data.2.created_at', '30 Oct 2026, 00:00')
            ->where('logs.data.3.created_at', '29 Oct 2026, 23:59'));

        $this->assertDatabaseHas('audit_logs', ['summary' => 'Timezone boundary', 'created_at' => '2026-10-29 16:00:00']);
        $this->assertSame('UTC', config('app.timezone'));
    }
}
