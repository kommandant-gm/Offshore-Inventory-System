<?php

namespace Tests\Feature;

use App\Jobs\SendAssetSignatureEmail;
use App\Models\{Asset, AssetAssignment, Branch, EmailActivityLog, MajorEquipment, MiriRentalItem, User};
use App\Services\BranchContext;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail, Storage};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $user;
    }

    public function test_branch_context_reuses_queries_but_refreshes_permissions_and_session_selection(): void
    {
        $user = $this->staff();
        $context = app(BranchContext::class);
        $branch = $context->branch($user);
        $this->assertTrue($context->canEdit($user, $branch->id));
        DB::enableQueryLog(); DB::flushQueryLog();
        for ($i = 0; $i < 20; $i++) {
            $this->assertSame($branch->id, $context->id($user));
            $this->assertSame($branch->id, $context->branch($user)->id);
            $this->assertTrue($context->canEdit($user, $branch->id));
        }
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        $user->branches()->updateExistingPivot($branch->id, ['access_level' => 'read']);
        $context->forget();
        $this->assertFalse($context->canEdit($user, $branch->id));
        $admin = User::factory()->create(['role' => 'admin']);
        $kl = Branch::where('code', 'KL-IT')->value('id');
        $context->set($admin, $kl);
        $this->assertSame($kl, $context->id($admin));
        $this->assertSame($branch->id, $context->id($user));
    }

    public function test_movement_pages_and_searches_both_sources_without_leaking_other_branches(): void
    {
        $this->staff();
        $branch = Branch::where('code', 'MIRI')->value('id');
        for ($i = 0; $i < 32; $i++) MajorEquipment::create(['branch_id' => $branch, 'tag_no' => 'EQ-'.$i, 'description' => 'Winch', 'current_location' => 'Miri', 'issue_out_cog_date' => '2026-09-01']);
        MiriRentalItem::create(['branch_id' => $branch, 'description' => 'Find me rental', 'current_location' => 'Marine', 'rental_due_date' => today()->addDays(5)]);
        MajorEquipment::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'description' => 'Secret']);
        $this->get(route('major-equipment.movement'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('summary.total', 33)->where('summary.due_soon', 1)->has('records.data', 10)->where('records.total', 33)->where('records.last_page', 4));
        $this->get(route('major-equipment.movement', ['page' => 4]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('records.data', 3));
        $this->get(route('major-equipment.movement', ['search' => 'Find me', 'type' => 'Rental', 'location' => 'Marine']))->assertOk()->assertInertia(fn (Assert $p) => $p->where('records.total', 1)->where('records.data.0.type', 'Rental')->where('summary.total', 33));
        $this->get(route('major-equipment.movement', ['per_page' => 1000]))->assertSessionHasErrors('per_page');
    }

    public function test_cog_search_is_bounded_branch_scoped_and_authorized(): void
    {
        $user = $this->staff();
        $branch = Branch::where('code', 'MIRI')->value('id');
        for ($i = 0; $i < 30; $i++) MajorEquipment::create(['branch_id' => $branch, 'tag_no' => 'TAG-'.$i, 'description' => 'Cargo']);
        MajorEquipment::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'tag_no' => 'SECRET']);
        $this->get(route('miri-cogs.create'))->assertOk()->assertInertia(fn (Assert $p) => $p->missing('items'));
        $first = $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'search' => 'Cargo']))->assertOk()->assertJsonCount(25, 'items')->assertJsonPath('has_more', true);
        $cursor = $first->json('next_after_id');
        $this->assertSame($first->json('items.24.id'), $cursor);
        $next = $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'search' => 'Cargo', 'after_id' => $cursor]))
            ->assertOk()->assertJsonCount(5, 'items')->assertJsonPath('has_more', false)->assertJsonPath('items.0.identifier', 'TAG-25');
        $this->assertCount(30, array_unique(array_merge(array_column($first->json('items'), 'id'), array_column($next->json('items'), 'id'))));
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'after_id' => $next->json('next_after_id')]))
            ->assertOk()->assertJsonCount(0, 'items')->assertJsonPath('has_more', false)->assertJsonPath('next_after_id', null);
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'search' => 'TAG-29', 'after_id' => $cursor]))->assertJsonCount(1, 'items');
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'after_id' => -1]))->assertUnprocessable();
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'search' => 'TAG-29']))->assertJsonCount(1, 'items');
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'search' => 'SECRET']))->assertJsonCount(0, 'items');
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $this->getJson(route('miri-cogs.items', ['type' => 'Rental']))->assertForbidden();
    }

    public function test_dashboards_only_query_the_selected_dataset_and_rental_aggregates_are_correct(): void
    {
        $this->staff();
        $branch = Branch::where('code', 'MIRI')->value('id');
        foreach ([['On Hire', today()->addDays(5)], ['', today()->subDay()], ['Returned to Supplier', today()->subDay()]] as [$status, $due]) {
            MiriRentalItem::create(['branch_id' => $branch, 'description' => 'Rental', 'supplier' => ' Supplier ', 'status' => $status, 'rental_due_date' => $due]);
        }
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->get(route('major-equipment.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('rentalDashboard', null));
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($q) => str_contains($q['query'], 'miri_rental_items')));
        DB::flushQueryLog();
        $this->get(route('major-equipment.dashboard', ['view' => 'rentals']))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->missing('summary')->where('rentalDashboard.summary.total', 3)->where('rentalDashboard.summary.overdue', 1)
            ->where('rentalDashboard.summary.due_30_days', 1)->where('rentalDashboard.suppliers.0.label', 'Supplier')->has('rentalDashboard.upcoming', 1));
        $this->assertFalse(collect(DB::getQueryLog())->contains(fn ($q) => str_contains($q['query'], 'miri_inventory_items')));
        DB::disableQueryLog();
    }

    public function test_certificate_preview_is_small_private_and_permission_checked_before_revalidation(): void
    {
        $user = $this->staff(); Storage::fake('certificates');
        $item = MajorEquipment::create(['branch_id' => Branch::where('code', 'MIRI')->value('id'), 'description' => 'Cargo']);
        $file = \Illuminate\Http\UploadedFile::fake()->image('certificate.png', 1200, 800);
        $path = $file->store('test', 'certificates');
        $cert = $item->certificates()->create(['branch_id' => $item->branch_id, 'certificate_type' => 'TEST']);
        $cert->forceFill(['image_path' => $path, 'image_mime' => 'image/png'])->save();
        $url = route('major-equipment.certificates.image', ['equipment' => $item->id, 'certificate' => $cert->id, 'preview' => 1]);
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $size = getimagesize(Storage::disk('certificates')->path($path.'.preview.jpg'));
        $this->assertSame(480, $size[0]);
        $this->assertSame(320, $size[1]);
        $this->withHeader('If-None-Match', $response->headers->get('ETag'))->get($url)->assertStatus(304);
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        $this->get($url)->assertForbidden();
    }

    public function test_settings_eager_loads_memberships_for_all_employees(): void
    {
        $admin = User::factory()->create(['username' => 'codex', 'role' => 'admin', 'directory_active' => true]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $admin->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        foreach (User::factory()->count(30)->create(['directory_active' => true]) as $user) {
            $user->branches()->attach($branch, ['access_level' => 'read']);
        }
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->actingAs($admin)->get(route('settings.index'))->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn ($row) => str_contains($row['query'], 'branch_user'));
        $this->assertLessThanOrEqual(3, $queries->count(), $queries->pluck('query')->implode("\n"));
        DB::disableQueryLog();
    }

    public function test_failed_background_import_rolls_back_and_status_is_owner_only(): void
    {
        $user = $this->staff();
        Storage::fake('local');
        $path = 'miri-imports/test.csv';
        Storage::disk('local')->put($path, 'test');
        $id = (string) \Illuminate\Support\Str::uuid();
        $branch = Branch::where('code', 'MIRI')->value('id');
        DB::table('miri_import_tasks')->insert(['id' => $id, 'branch_id' => $branch, 'user_id' => $user->id,
            'inventory_type' => 'cargo', 'filename' => 'test.csv', 'file_path' => $path, 'status' => 'queued',
            'created_at' => now(), 'updated_at' => now()]);
        $service = \Mockery::mock(\App\Services\MiriEquipmentCsvService::class);
        $service->shouldReceive('import')->once()->andReturnUsing(function () use ($branch) {
            MajorEquipment::create(['branch_id' => $branch, 'tag_no' => 'ROLLBACK']);
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'Invalid test CSV.']);
        });
        $job = new \App\Jobs\ImportMiriEquipment($id);
        try { $job->handle($service); $this->fail('Expected import validation failure.'); }
        catch (\Illuminate\Validation\ValidationException) {}
        $this->assertSame(0, MajorEquipment::count());
        $this->assertSame('failed', DB::table('miri_import_tasks')->value('status'));
        Storage::disk('local')->assertMissing($path);
        $job->handle($service); // Terminal tasks must not import again.
        $this->get(route('major-equipment.import.status', $id))->assertOk();
        $this->staff();
        $this->get(route('major-equipment.import.status', $id))->assertNotFound();
    }

    public function test_supervisor_notification_is_queued_with_pending_and_final_activity(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $notification = new \App\Notifications\SupervisorWorkflowNotification('Performance test', 'Test only', [], null, 'Open');
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $notification);
        $this->assertTrue($notification->afterCommit);
        app(\App\Services\SupervisorNotificationService::class)->send($notification, additionalRecipients: ['test@example.com']);
        $log = EmailActivityLog::where('recipient', 'test@example.com')->firstOrFail();
        $this->assertSame('pending', $log->status);
        $notification->activityLogId = $log->id;
        $notifiable = (new \Illuminate\Notifications\AnonymousNotifiable)->route('mail', 'test@example.com');
        \Illuminate\Support\Facades\Event::dispatch(new \Illuminate\Notifications\Events\NotificationSent($notifiable, $notification, 'mail'));
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame(1, EmailActivityLog::where('recipient', 'test@example.com')->count());
        $notification->failed(new \RuntimeException('Later error must not overwrite sent'));
        $this->assertSame('sent', $log->fresh()->status);
    }

    public function test_signature_job_marks_sent_after_delivery_and_skips_repeat_delivery(): void
    {
        Mail::fake();
        $branch = Branch::where('code', 'KL-IT')->value('id');
        $category = \App\Models\Category::withoutGlobalScopes()->create(['branch_id' => $branch, 'code' => 'MAIL', 'name' => 'Mail test', 'type' => 'asset', 'active' => true]);
        $asset = Asset::withoutGlobalScopes()->create(['category_id' => $category->id, 'branch_id' => $branch, 'asset_tag_no' => 'MAIL-1', 'description' => 'Laptop', 'current_status' => 'available']);
        $assignment = AssetAssignment::withoutGlobalScopes()->create(['branch_id' => $branch, 'asset_id' => $asset->id, 'assigned_email' => 'test@example.com', 'assigned_to_name' => 'Test', 'assigned_at' => today(), 'checkout_token' => 'token', 'checkout_status' => 'pending']);
        $log = EmailActivityLog::create(['recipient' => 'test@example.com', 'subject' => 'Test', 'notification_type' => 'AssetCheckoutSignatureMail', 'status' => 'pending', 'action_url' => route('public.asset-checkout.show', 'token')]);
        $job = new SendAssetSignatureEmail($assignment->id, $branch, $log->id, false);
        $job->handle(); $job->handle();
        Mail::assertSent(\App\Mail\AssetCheckoutSignatureMail::class, 1);
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
    }
    public function test_cog_picker_refines_machinery_and_cargo_before_pagination(): void
    {
        $this->staff();
        $branch = Branch::where('code', 'MIRI')->value('id');
        $base = ['branch_id' => $branch, 'inventory_type' => 'cargo', 'description' => 'RUBBISH SKID', 'section_2' => 'DNV', 'current_location' => 'Miri'];
        for ($i = 0; $i < 27; $i++) MajorEquipment::create($base + ['tag_no' => 'SKID-'.$i]);
        MajorEquipment::create(array_replace($base, ['description' => 'CONTAINER']));
        MajorEquipment::create(array_replace($base, ['current_location' => 'Bintulu', 'section_2' => 'OTHER']));
        MajorEquipment::create(array_replace($base, ['inventory_type' => 'machinery', 'description' => 'WINCH', 'section_2' => 'WINCHES']));
        MajorEquipment::create(array_replace($base, ['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'description' => 'SECRET']));
        $filters = ['type' => 'Major equipment', 'inventory_type' => 'cargo', 'description' => 'RUBBISH SKID', 'location' => 'Miri', 'section_2' => 'DNV'];
        $first = $this->getJson(route('miri-cogs.items', $filters))->assertOk()->assertJsonCount(25, 'items')->assertJsonPath('has_more', true)
            ->assertJsonPath('options.description', ['CONTAINER', 'RUBBISH SKID'])
            ->assertJsonPath('options.section_2', ['DNV'])->assertJsonPath('options.location', ['Miri']);
        $this->getJson(route('miri-cogs.items', $filters + ['after_id' => $first->json('next_after_id')]))->assertOk()
            ->assertJsonCount(2, 'items')->assertJsonPath('has_more', false)->assertJsonPath('items.0.identifier', 'SKID-25')
            ->assertJsonPath('options.description', ['CONTAINER', 'RUBBISH SKID']);
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'inventory_type' => 'machinery']))->assertOk()
            ->assertJsonCount(1, 'items')->assertJsonPath('items.0.description', 'WINCH')->assertJsonPath('options.description', ['WINCH']);
        $this->getJson(route('miri-cogs.items', $filters + ['search' => 'SKID-26']))->assertOk()->assertJsonCount(1, 'items');
        $this->getJson(route('miri-cogs.items', array_replace($filters, ['description' => 'WINCH'])))->assertOk()->assertJsonCount(0, 'items');
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'inventory_type' => 'invalid']))->assertUnprocessable();
        MiriRentalItem::create(['branch_id' => $branch, 'description' => 'Rental item']);
        $this->getJson(route('miri-cogs.items', array_replace($filters, ['type' => 'Rental'])))->assertOk()->assertJsonCount(1, 'items');
    }

    public function test_rental_dashboard_groups_projects_and_excludes_completed_rentals_from_alerts(): void
    {
        $this->staff();
        $branch = Branch::where('code', 'MIRI')->value('id');
        foreach ([
            [' SBA ', 'On Hire', -1], ['SBA', 'Issued', -2], ['SBA', 'Off Hire', -3],
            ['SBA', 'Received Backload', -4], ['SBA', 'Returned to Supplier', -5],
            ['SKA', 'On Hire', 5], ['SKA', 'Overdue', null], ['SKA', 'Off Hire', 5],
            ['SSB', 'Issued', 10], [null, '', -1],
        ] as [$project, $status, $days]) {
            MiriRentalItem::create(['branch_id' => $branch, 'project_contract' => $project, 'status' => $status, 'rental_due_date' => $days === null ? null : today()->addDays($days)]);
        }
        MiriRentalItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'project_contract' => 'PRIVATE', 'status' => 'Overdue']);
        $this->get(route('major-equipment.dashboard', ['view' => 'rentals']))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('rentalDashboard.summary.total', 10)->where('rentalDashboard.summary.overdue', 4)
            ->where('rentalDashboard.summary.due_30_days', 2)->has('rentalDashboard.upcoming', 2)
            ->has('rentalDashboard.projects', 4)->missing('rentalDashboard.dueTimeline')
            ->where('rentalDashboard.projects', function ($projects) {
                $rows = collect($projects)->keyBy('project');
                $sba = $rows['SBA'];
                $statuses = collect($sba['status'])->pluck('value', 'label');
                return $sba['total'] === 5 && $sba['on_hire'] === 1 && $sba['off_hire'] === 1 && $sba['overdue'] === 2
                    && $statuses['Returned to Supplier'] === 1 && $statuses['Received Backload'] === 1
                    && $rows['SKA']['overdue'] === 1 && $rows['SSB']['overdue'] === 0 && $rows['Not recorded']['total'] === 1;
            }));
    }

    public function test_rental_location_breakdown_preserves_projects_and_all_locations(): void
    {
        $this->staff();
        $branch = Branch::where('code', 'MIRI')->value('id');
        foreach ([[' SBA ', ' Site A ', 'On Hire'], ['SBA', 'Site A', 'Off Hire'], ['SBA', 'Site A', 'Received Backload'], ['SBA', 'Site A', 'Returned to Supplier'], ['SKA', 'Site A', 'Issued'], [null, null, 'Overdue']] as [$project, $location, $status]) {
            MiriRentalItem::create(['branch_id' => $branch, 'project_contract' => $project, 'current_location' => $location, 'status' => $status, 'rental_due_date' => today()->subDay()]);
        }
        for ($i = 0; $i < 9; $i++) MiriRentalItem::create(['branch_id' => $branch, 'project_contract' => 'SSB', 'current_location' => 'Site '.$i, 'status' => 'On Hire']);
        MiriRentalItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'project_contract' => 'SBA', 'current_location' => 'Site A', 'status' => 'Overdue']);
        $this->get(route('major-equipment.dashboard', ['view' => 'rentals']))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('rentalDashboard.locations', 12)
            ->where('rentalDashboard.locations', function ($locations) {
                $rows = collect($locations);
                $sba = $rows->firstWhere('project', 'SBA');
                $ska = $rows->firstWhere('project', 'SKA');
                $missing = $rows->firstWhere('project', 'Not recorded');
                return $rows->sum('value') === 15 && $sba['label'] === 'Site A' && $sba['value'] === 4
                    && $sba['on_hire'] === 1 && $sba['off_hire'] === 1 && $sba['overdue'] === 1
                    && $ska['value'] === 1 && $ska['overdue'] === 1 && $ska['on_hire'] === 0
                    && $missing['label'] === 'Not specified' && $missing['overdue'] === 1;
            }));
    }

}
