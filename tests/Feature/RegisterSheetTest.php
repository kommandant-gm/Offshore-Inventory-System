<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Branch, MiriRentalItem, MiriConstructionItem, MiriPaintItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegisterSheetTest extends TestCase
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

    private function records(): array
    {
        return [
            'rental' => MiriRentalItem::create(['description' => 'Original', 'status' => 'On Hire']),
            'construction' => MiriConstructionItem::create(['category' => 'PPE', 'description' => 'Original']),
            'paint' => MiriPaintItem::create(['category' => 'PAINT', 'description' => 'Original', 'date_status' => 'not_recorded']),
        ];
    }

    private function saveRows(string $register, array $rows)
    {
        return $this->patchJson(route('register.spreadsheet.update', $register), ['rows' => $rows]);
    }

    public function test_registers_expose_columns_and_save_cells_with_audit(): void
    {
        $this->staff();
        foreach ($this->records() as $register => $item) {
            $route = $register === 'rental' ? 'miri-rental' : $register;
            $this->get(route($route.'.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('sheetColumns')->where('sheetColumns.0.key', 'company'));
            $this->saveRows($register, [['id' => $item->id, 'changes' => ['description' => 'Updated', 'company' => 'DESB'], 'original' => ['description' => 'Original', 'company' => '']]])->assertOk();
            $this->assertSame('Updated', $item->fresh()->description);
            $this->assertSame('DESB', $item->fresh()->company);
            $this->assertTrue(AuditLog::where('auditable_type', $item->getMorphClass())->where('event', 'updated')->exists());
        }
    }

    public function test_stale_cells_roll_back_other_rows_and_invalid_fields_are_rejected(): void
    {
        $this->staff();
        foreach ($this->records() as $register => $item) {
            $second = $item->replicate(); $second->save();
            $this->saveRows($register, [
                ['id' => $item->id, 'changes' => ['description' => 'Updated'], 'original' => ['description' => 'Original']],
                ['id' => $second->id, 'changes' => ['description' => 'Updated'], 'original' => ['description' => 'Stale']],
            ])->assertUnprocessable()->assertJsonValidationErrors('rows.1.changes.description');
            $this->assertSame('Original', $item->fresh()->description);
            foreach (['branch_id' => 999, 'company' => 'INVALID'] as $key => $value) {
                $this->saveRows($register, [['id' => $item->id, 'changes' => [$key => $value], 'original' => [$key => '']]])->assertUnprocessable();
            }
        }
    }

    public function test_readers_and_cross_branch_records_cannot_be_updated(): void
    {
        $this->staff(false);
        foreach ($this->records() as $register => $item) {
            $row = ['id' => $item->id, 'changes' => ['description' => 'Updated'], 'original' => ['description' => 'Original']];
            $this->saveRows($register, [$row])->assertForbidden();
        }
        $this->staff();
        foreach ($this->records() as $register => $item) {
            $item->branch_id = Branch::where('code', 'KL-IT')->value('id'); $item->save();
            $this->saveRows($register, [['id' => $item->id, 'changes' => ['description' => 'Updated'], 'original' => ['description' => 'Original']]])->assertNotFound();
        }
    }

    public function test_stock_controls_and_paint_date_fields_cannot_be_bypassed(): void
    {
        $this->staff(); $items = $this->records();
        $paint = $items['paint'];
        foreach (['balance_cans', 'opening_litres', 'original_date', 'date_status', 'best_before_date'] as $key) {
            $this->saveRows('paint', [['id' => $paint->id, 'changes' => [$key => '1'], 'original' => [$key => '']]])->assertUnprocessable();
        }
        $construction = $items['construction'];
        $construction->forceFill(['stock_initialized_at' => now(), 'stock_balance' => 5, 'unit' => 'PCS'])->save();
        $this->saveRows('construction', [['id' => $construction->id, 'changes' => ['stock_balance' => '10'], 'original' => ['stock_balance' => '5.000']]])
            ->assertUnprocessable()->assertJsonValidationErrors('rows.0.changes.stock_balance');
        $this->assertSame('5.000', $construction->fresh()->stock_balance);
    }
}
