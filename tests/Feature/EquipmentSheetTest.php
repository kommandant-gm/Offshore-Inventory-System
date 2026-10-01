<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Branch, MajorEquipment, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EquipmentSheetTest extends TestCase
{
    use RefreshDatabase;

    private function staff(bool $edit = true): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true,
            'permissions' => $edit ? AccessMatrix::permissionsForRole('miri') : array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => $edit ? 'edit' : 'read', 'is_default' => true]);
        $this->actingAs($user);
    }

    private function item(array $values = []): MajorEquipment
    {
        return MajorEquipment::create([...['category' => 'MAJOR EQUIPMENT', 'inventory_type' => 'machinery', 'section_1' => 'Machinery', 'description' => 'Compressor', 'tag_no' => '00123', 'status' => 'Standby'], ...$values]);
    }

    private function saveRows(array $rows, string $type = 'machinery')
    {
        return $this->patchJson(route('major-equipment.spreadsheet.update'), ['inventory_type' => $type, 'rows' => $rows]);
    }

    public function test_cells_save_together_and_leave_other_fields_and_certificates_untouched(): void
    {
        $this->staff();
        $first = $this->item(['remarks' => 'Keep this', 'issue_out_cog_date' => '2026-08-10']);
        $second = $this->item();
        $certificate = $first->certificates()->create(['branch_id' => $first->branch_id, 'certificate_type' => 'Inspection', 'certificate_no' => 'CERT-1']);
        $this->saveRows([
            ['id' => $first->id, 'changes' => ['company' => 'DESB', 'tag_no' => '00456', 'issue_out_cog_date' => null], 'original' => ['company' => '', 'tag_no' => '00123', 'issue_out_cog_date' => '2026-08-10']],
            ['id' => $second->id, 'changes' => ['status' => 'In Use'], 'original' => ['status' => 'Standby']],
        ])->assertOk()->assertJsonPath('message', '2 records saved.');
        $this->assertSame('00456', $first->fresh()->tag_no);
        $this->assertSame('00456', $first->fresh()->normalized_tag);
        $this->assertSame('Keep this', $first->fresh()->remarks);
        $this->assertNull($first->fresh()->issue_out_cog_date);
        $this->assertSame('CERT-1', $certificate->fresh()->certificate_no);
        $this->assertSame('In Use', $second->fresh()->status);
        $this->assertSame(2, AuditLog::where('auditable_type', $first->getMorphClass())->where('event', 'updated')->count());
    }

    public function test_invalid_values_and_unknown_fields_reject_the_entire_batch(): void
    {
        $this->staff();
        $first = $this->item(); $second = $this->item();
        foreach ([['company' => 'OTHER'], ['category' => ''], ['quantity' => -1], ['issue_out_cog_date' => 'invalid'], ['branch_id' => 9], ['certificates' => []]] as $changes) {
            $this->saveRows([
                ['id' => $first->id, 'changes' => ['status' => 'In Use'], 'original' => ['status' => 'Standby']],
                ['id' => $second->id, 'changes' => $changes, 'original' => array_fill_keys(array_keys($changes), '')],
            ])->assertUnprocessable();
            $this->assertSame('Standby', $first->fresh()->status);
        }
    }

    public function test_stale_cells_rollback_the_batch_but_unrelated_updates_are_preserved(): void
    {
        $this->staff();
        $first = $this->item(); $second = $this->item(['status' => 'Under Repair']);
        $this->saveRows([
            ['id' => $first->id, 'changes' => ['status' => 'In Use'], 'original' => ['status' => 'Standby']],
            ['id' => $second->id, 'changes' => ['status' => 'In Use'], 'original' => ['status' => 'Standby']],
        ])->assertUnprocessable()->assertJsonValidationErrors('rows.1.changes.status');
        $this->assertSame('Standby', $first->fresh()->status);
        $this->assertSame('Under Repair', $second->fresh()->status);
        $this->assertSame(0, AuditLog::where('event', 'updated')->count());
        $this->saveRows([['id' => $second->id, 'changes' => ['description' => 'New description'], 'original' => ['description' => 'Compressor']]])->assertOk();
        $this->assertSame('Under Repair', $second->fresh()->status);
    }

    public function test_original_cells_and_distinct_row_ids_are_required(): void
    {
        $this->staff(); $item = $this->item();
        $row = ['id' => $item->id, 'changes' => ['status' => 'In Use'], 'original' => ['description' => 'Compressor']];
        $this->saveRows([$row])->assertUnprocessable()->assertJsonValidationErrors('rows.0.original');
        $row['original'] = ['status' => 'Standby'];
        $this->saveRows([$row, $row])->assertUnprocessable();
        unset($row['original']);
        $this->saveRows([$row])->assertUnprocessable();
    }

    public function test_reader_and_other_branch_or_inventory_type_cannot_be_edited(): void
    {
        $this->staff(false); $item = $this->item();
        $row = ['id' => $item->id, 'changes' => ['status' => 'In Use'], 'original' => ['status' => 'Standby']];
        $this->saveRows([$row])->assertForbidden();
        $this->get(route('major-equipment.index'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)->has('sheetColumns')->where('sheetColumns.0.key', 'company'));
        $this->staff();
        $this->saveRows([$row], 'cargo')->assertNotFound();
        $other = $this->item(['branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        $row['id'] = $other->id;
        $this->saveRows([$row])->assertNotFound();
        $this->assertSame('Standby', $other->fresh()->status);
    }

    public function test_cargo_and_source_whitespace_are_handled_without_false_conflicts(): void
    {
        $this->staff(); $item = $this->item(['inventory_type' => 'cargo', 'section_1' => 'CARGO SET', 'description' => '  Cargo basket  ', 'quantity' => '2.00']);
        $this->saveRows([['id' => $item->id, 'changes' => ['description' => 'Basket', 'quantity' => '0', 'section_1' => 'cargo'],
            'original' => ['description' => '  Cargo basket  ', 'quantity' => '2.00', 'section_1' => 'CARGO SET']]], 'cargo')->assertOk();
        $this->assertSame('0.00', $item->fresh()->quantity);
        $this->assertSame('CARGO SET', $item->fresh()->section_1);
    }
}
