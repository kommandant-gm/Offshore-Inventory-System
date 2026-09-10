<?php

namespace Tests\Feature;

use App\Jobs\ImportMiriConstruction;
use App\Models\{AuditLog, Branch, MiriConstructionItem, User};
use App\Services\ConstructionCsvService;
use App\Support\{AccessMatrix, ConstructionFields};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Queue, Storage};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriConstructionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function staff(bool $edit = true): User
    {
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true,
            'permissions' => $edit ? AccessMatrix::permissionsForRole('miri') : array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => $edit ? 'edit' : 'read', 'is_default' => true]);
        $this->actingAs($user);
        return $user;
    }

    private function csv(array $changes = [], int $copies = 1): UploadedFile
    {
        $top = array_fill(0, 355, '');
        foreach ([0 => 'CATEGORY & SECTION', 10 => 'CERTIFICATE', 12 => 'ISSUE OUT TO LOCATION', 15 => 'ISSUE OUT TO PERSONNEL',
            19 => 'CERTIFICATION', 21 => 'MR REQUEST', 22 => 'PURCHASE ORDER', 24 => 'RECEIVE (STOCK-IN)', 27 => 'RECEIVED BACKLOAD', 31 => 'UNFIT & WRITE-OFF'] as $i => $label) $top[$i] = $label;
        $header = array_pad(array_column(ConstructionFields::FIELDS, 'label'), 355, '');
        $row = array_replace(array_fill(0, 355, ''), [0 => 'PPE', 1 => 'COVERALL', 3 => 'Test coverall', 7 => '10', 10 => 'CERT-1', 11 => '25-Dec-25',
            12 => 'Yard 1', 13 => "LOC-COG\n01/01/2026", 14 => '2', 15 => 'Private personnel / IC-123',
            16 => 'PERSON-COG', 17 => '3', 22 => 'Carried Forward', 23 => "DO-1\nDO-2", 24 => 'PC', 25 => '15', 26 => 'R1',
            27 => 'Marine', 28 => 'BACK-COG', 29 => '4', 30 => 'R2', 33 => '12.50', 34 => '125.00'], $changes);
        $handle = fopen('php://temp', 'w+');
        foreach ([$top, $header, ...array_fill(0, $copies, $row)] as $line) fputcsv($handle, $line, ',', '"', '');
        rewind($handle); $bytes = stream_get_contents($handle); fclose($handle);
        return UploadedFile::fake()->createWithContent('Construction.csv', $bytes);
    }

    public function test_grouped_columns_import_without_replaying_stock_and_keep_private_fields_encrypted(): void
    {
        $user = $this->staff(); Queue::fake(); Storage::fake('construction');
        $file = $this->csv();
        $this->postJson(route('construction.import.preview'), ['file' => $file])->assertOk()->assertJsonPath('records', 1)->assertDontSee('IC-123');
        $this->post(route('construction.import.store'), ['file' => $file])->assertRedirect()->assertSessionHasNoErrors();
        Queue::assertPushed(ImportMiriConstruction::class);
        $this->assertDatabaseCount('miri_construction_items', 0);
        $task = DB::table('miri_construction_imports')->first();
        $job = new ImportMiriConstruction($task->id);
        $job->handle(app(ConstructionCsvService::class));
        $job->handle(app(ConstructionCsvService::class));
        $this->assertDatabaseCount('miri_construction_items', 1);
        $item = MiriConstructionItem::firstOrFail();
        foreach (['stock_balance' => '10.000', 'stock_in_qty' => '15.000', 'issue_location_qty' => '2.000', 'issue_personnel_qty' => '3.000', 'backload_qty' => '4.000', 'closing_value' => '125.00', 'unit_price' => '12.50',
            'storage_rack' => 'R1', 'backload_rack' => 'R2', 'issue_personnel_cog' => 'PERSON-COG', 'backload_cog' => 'BACK-COG', 'po_reference' => 'Carried Forward', 'do_reference' => "DO-1\nDO-2"] as $key => $value) $this->assertSame($value, $item->$key);
        $this->assertSame('2025-12-25', $item->certificate_due_date->toDateString());
        $this->assertSame('Private personnel / IC-123', $item->personnel_details);
        $raw = DB::table('miri_construction_items')->first();
        $this->assertStringNotContainsString('IC-123', $raw->personnel_details);
        $this->assertStringNotContainsString('IC-123', $raw->source_values);
        $this->assertStringNotContainsString('IC-123', json_encode(AuditLog::where('module', 'miri_construction')->get()));
        Storage::disk('construction')->assertMissing($task->file_path);
        $this->get(route('construction.import.status', $task->id))->assertOk()->assertInertia(fn (Assert $p) => $p->where('task.status', 'completed'));
        $this->postJson(route('construction.import.preview'), ['file' => $file])->assertJsonPath('already_imported', true);
        $this->post(route('construction.import.store'), ['file' => $file])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('miri_inventory_items', 0);
        $this->assertDatabaseCount('miri_rental_items', 0);
    }

    public function test_register_filters_duplicates_and_edit_resolution_do_not_cross_registers_or_branches(): void
    {
        $this->staff();
        $branch = Branch::where('code', 'MIRI')->value('id');
        foreach ([' DUP-1 ', 'dup-1', null] as $tag) MiriConstructionItem::create(['branch_id' => $branch, 'category' => 'TOOLS', 'tag_no' => $tag]);
        MiriConstructionItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'category' => 'Private', 'tag_no' => 'DUP-1']);
        \App\Models\MajorEquipment::create(['branch_id' => $branch, 'tag_no' => 'DUP-1']);
        $this->get(route('construction.index', ['quality' => 'duplicates']))->assertOk()->assertInertia(fn (Assert $p) => $p->where('records.total', 2)->where('records.data.0.duplicate_count', 2));
        $item = MiriConstructionItem::where('branch_id', $branch)->where('normalized_tag', 'dup-1')->firstOrFail();
        $this->patch(route('construction.update', $item), ['category' => 'TOOLS', 'description' => 'Changed', 'tag_no' => 'UNIQUE', 'stock_balance' => 7, 'unit' => 'PC', 'personnel_details' => 'Secret IC value'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0, MiriConstructionItem::duplicateTag()->count());
        $this->assertStringNotContainsString('Secret IC value', json_encode(AuditLog::where('module', 'miri_construction')->get()));
        $this->get(route('construction.show', $item))->assertOk()->assertInertia(fn (Assert $p) => $p->has('fields', 35)->where('record.stock_balance', '7.000'));
    }

    public function test_readers_cannot_edit_and_never_receive_personnel_or_original_csv_values(): void
    {
        $this->staff();
        $item = MiriConstructionItem::create(['branch_id' => Branch::where('code', 'MIRI')->value('id'), 'category' => 'PPE', 'personnel_details' => 'SECRET-IC', 'source_values' => ['SECRET-IC']]);
        $this->staff(false);
        $this->get(route('construction.show', $item))->assertOk()->assertInertia(fn (Assert $p) => $p->missing('record.personnel_details')->missing('record.original_values'))->assertDontSee('SECRET-IC');
        $this->get(route('construction.index'))->assertOk()->assertDontSee('SECRET-IC');
        $this->get(route('construction.edit', $item))->assertForbidden();
        $this->post(route('construction.store'), ['category' => 'PPE'])->assertForbidden();
        $this->postJson(route('construction.import.preview'), ['file' => $this->csv()])->assertForbidden();
        $other = MiriConstructionItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'category' => 'Private']);
        $this->get(route('construction.show', $other))->assertNotFound();
    }

    public function test_private_attachments_support_images_pdf_replacement_and_removal(): void
    {
        $this->staff(); Storage::fake('construction');
        $this->post(route('construction.store'), ['category' => 'PPE', 'uploads' => ['certificate' => UploadedFile::fake()->image('test.png')]])->assertRedirect()->assertSessionHasNoErrors();
        $item = MiriConstructionItem::firstOrFail();
        $path = $item->attachments['certificate']['path'];
        Storage::disk('construction')->assertExists($path);
        $url = route('construction.attachment', ['construction' => $item->id, 'slot' => 'certificate']);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $pdf = UploadedFile::fake()->createWithContent('test.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $this->patch(route('construction.update', $item), ['category' => 'PPE', 'uploads' => ['certificate' => $pdf]])->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('construction')->assertMissing($path);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $path = $item->fresh()->attachments['certificate']['path'];
        $this->patch(route('construction.update', $item), ['category' => 'PPE', 'remove_attachments' => ['certificate']])->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('construction')->assertMissing($path);
        $this->get($url)->assertNotFound();
        $bad = UploadedFile::fake()->createWithContent('fake.pdf', '<html>not a pdf</html>');
        $realUpload = new UploadedFile($bad->getPathname(), 'fake.pdf', null, null, true);
        $this->patch(route('construction.update', $item), ['category' => 'PPE', 'uploads' => ['certificate' => $realUpload]])->assertSessionHasErrors('uploads.certificate');
        $this->get(route('construction.attachment', ['construction' => $item->id, 'slot' => 'unknown']))->assertNotFound();
    }

    public function test_preview_rejects_shifted_headers_and_extra_data_but_flags_invalid_values(): void
    {
        $this->staff();
        $this->postJson(route('construction.import.preview'), ['file' => $this->csv([355 => 'unexpected'])])->assertUnprocessable();
        $this->postJson(route('construction.import.preview'), ['file' => UploadedFile::fake()->createWithContent('wrong.csv', "CATEGORY,DESCRIPTION\nPPE,test")])->assertUnprocessable();
        $file = $this->csv([7 => '', 11 => '31-Feb-26', 33 => '-5', 34 => 'invalid']);
        $report = app(ConstructionCsvService::class)->preview($file, Branch::where('code', 'MIRI')->value('id'));
        $this->assertSame(1, $report['needs_review']);
        $row = iterator_to_array(app(ConstructionCsvService::class)->rows($file))[0];
        $this->assertNull($row['stock_balance']);
        $this->assertNull($row['certificate_due_date']);
        $this->assertNull($row['unit_price']);
        $this->assertSame('31-Feb-26', $row['source_values'][11]);
        $this->assertCount(3, $row['import_warnings']);
        $this->post(route('construction.import.store'), ['file' => $file])->assertUnprocessable();
    }

    public function test_job_rolls_back_on_failure_and_releases_hash_for_a_reviewed_retry(): void
    {
        $user = $this->staff(); Queue::fake(); Storage::fake('construction');
        $file = $this->csv();
        $this->postJson(route('construction.import.preview'), ['file' => $file])->assertOk();
        $this->post(route('construction.import.store'), ['file' => $file])->assertRedirect();
        $task = DB::table('miri_construction_imports')->first();
        $service = \Mockery::mock(ConstructionCsvService::class);
        $service->shouldReceive('import')->once()->andReturnUsing(function ($file, $branch) {
            MiriConstructionItem::create(['branch_id' => $branch, 'category' => 'ROLLBACK']);
            throw \Illuminate\Validation\ValidationException::withMessages(['file' => 'Bad test row.']);
        });
        try { (new ImportMiriConstruction($task->id))->handle($service); $this->fail('Expected failure'); }
        catch (\Illuminate\Validation\ValidationException) {}
        $this->assertDatabaseCount('miri_construction_items', 0);
        $this->assertSame('failed', DB::table('miri_construction_imports')->value('status'));
        $this->assertNull(DB::table('miri_construction_imports')->value('active_hash'));
        Storage::disk('construction')->assertMissing($task->file_path);
        $this->postJson(route('construction.import.preview'), ['file' => $file])->assertJsonPath('already_imported', false);
        $this->post(route('construction.import.store'), ['file' => $file])->assertRedirect();
        $this->assertDatabaseCount('miri_construction_imports', 2);
        $this->staff();
        $this->get(route('construction.import.status', $task->id))->assertNotFound();
    }

    public function test_review_acknowledgement_requires_a_note_and_sensitive_input_is_not_flashed(): void
    {
        $this->staff();
        $this->post(route('construction.store'), ['category' => 'PPE', 'grouping_reviewed' => '1', 'personnel_details' => 'PRIVATE-VALIDATION'])
            ->assertSessionHasErrors('review_note')->assertSessionMissing('_old_input.personnel_details');
        $this->post(route('construction.store'), ['category' => 'PPE', 'description' => 'Reviewed item', 'stock_balance' => 0, 'unit' => 'PC',
            'grouping_reviewed' => '1', 'review_note' => 'Confirmed as a separate source snapshot.'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $item = MiriConstructionItem::firstOrFail();
        $this->assertSame('0.000', $item->stock_balance);
        $this->assertTrue($item->grouping_reviewed);
        $this->assertFalse($item->needs_review);
    }

    public function test_supplied_construction_csv_is_preserved_in_the_isolated_database(): void
    {
        $path = 'C:/Users/User/Desktop/Construction.csv';
        if (! is_file($path)) $this->markTestSkipped('Supplied CSV is unavailable.');
        $user = $this->staff();
        $branch = Branch::where('code', 'MIRI')->value('id');
        $file = new UploadedFile($path, 'Construction.csv', 'text/csv', null, true);
        $service = app(ConstructionCsvService::class);
        $preview = $service->preview($file, $branch);
        $this->assertSame(1588, $preview['records']);
        $this->assertSame(30, $preview['duplicate_records']);
        $this->assertCount(6, $preview['categories']);
        $result = DB::transaction(fn () => $service->import($file, $branch));
        $this->assertSame(1588, $result['created']);
        $this->assertSame(850, MiriConstructionItem::whereNotNull('tag_no')->count());
        $this->assertSame(677, MiriConstructionItem::whereNotNull('stock_balance')->count());
        $this->assertSame(168, MiriConstructionItem::whereNotNull('certificate_due_date')->count());
        $this->assertSame(30, MiriConstructionItem::duplicateTag()->count());
        $this->assertSame('156.000', MiriConstructionItem::where('source_row', 12)->firstOrFail()->stock_balance);
        $this->assertSame('6.000', MiriConstructionItem::where('source_row', 13)->firstOrFail()->stock_balance);
        $this->assertTrue(MiriConstructionItem::where('source_row', 12)->firstOrFail()->grouping_review_required);
        $this->get(route('construction.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('records.total', 1588)->has('records.data', 25));
        $this->get(route('construction.index', ['category' => 'BLAST GRIT']))->assertOk()->assertInertia(fn (Assert $p) => $p->where('records.total', 13));
        fwrite(STDOUT, "\nConstruction CSV verified (test DB only): ".json_encode($result)."\n");
    }
}
