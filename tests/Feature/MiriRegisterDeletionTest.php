<?php
namespace Tests\Feature;

use App\Models\{AuditLog, Branch, MajorEquipment, MiriRentalItem, MiriConstructionItem, MiriPaintItem, MiriCog};
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MiriRegisterDeletionTest extends TestCase
{
    use RefreshDatabase;
    private function staff(): int
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $branch;
    }
    private function registers(): array
    {
        return ['major' => MajorEquipment::class, 'rental' => MiriRentalItem::class, 'construction' => MiriConstructionItem::class, 'paint' => MiriPaintItem::class];
    }
    private function url(string $register, $record): string { return route('miri-register.destroy', ['register' => $register, 'item' => $record->id]); }

    public function test_confirmed_deletion_removes_each_register_item_and_audits_without_private_data(): void
    {
        $branch = $this->staff();
        foreach ($this->registers() as $register => $class) {
            $record = $class::create(['branch_id' => $branch, 'category' => 'TEST', 'description' => 'Delete sample']);
            if ($register === 'major') $record->certificates()->create(['branch_id' => $branch, 'certificate_type' => 'Test', 'certificate_no' => 'CERT-1']);
            if ($register === 'construction') $record->update(['personnel_details' => 'PRIVATE IC', 'source_values' => ['PRIVATE SOURCE']]);
            $this->delete($this->url($register, $record), ['confirmed' => true])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseMissing($record->getTable(), ['id' => $record->id]);
            $log = AuditLog::where('event', 'deleted')->where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->id)->firstOrFail();
            $this->assertSame(auth()->id(), $log->user_id);
            $this->assertStringNotContainsString('PRIVATE', json_encode($log->before));
        }
        $this->assertDatabaseCount('miri_inventory_certificates', 0);
        $cargo = MajorEquipment::create(['branch_id' => $branch, 'inventory_type' => 'cargo', 'description' => 'Cargo item']);
        $this->delete($this->url('major', $cargo), ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('miri_inventory_items', ['id' => $cargo->id]);
    }

    public function test_confirmation_permissions_and_branch_isolation_on_all_registers(): void
    {
        $branch = $this->staff();
        foreach ($this->registers() as $register => $class) {
            $record = $class::create(['branch_id' => $branch, 'description' => 'Keep']);
            $this->delete($this->url($register, $record))->assertSessionHasErrors('confirmed');
            $this->delete($this->url($register, $record), ['confirmed' => false])->assertSessionHasErrors('confirmed');
            $foreign = $class::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'description' => 'Private branch']);
            $this->delete($this->url($register, $foreign), ['confirmed' => true])->assertNotFound();
            auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
            $this->delete($this->url($register, $record), ['confirmed' => true])->assertForbidden();
            auth()->user()->update(['permissions' => AccessMatrix::permissionsForRole('miri')]);
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]);
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_cog_links_and_transfer_destinations_prevent_deletion(): void
    {
        $branch = $this->staff();
        $cog = MiriCog::create(['branch_id' => $branch, 'cog_no' => 'DELETE-TEST', 'movement_type' => 'Issue out', 'document_date' => '2026-09-23', 'status' => 'draft']);
        $types = ['major' => 'Major equipment', 'rental' => 'Rental', 'construction' => 'Construction', 'paint' => 'Paint'];
        foreach ($this->registers() as $register => $class) {
            $record = $class::create(['branch_id' => $branch, 'description' => 'Linked']);
            $line = $cog->items()->create(['branch_id' => $branch, 'item_type' => $types[$register], 'item_id' => $record->id, 'quantity' => 1]);
            $this->delete($this->url($register, $record), ['confirmed' => true])->assertSessionHasErrors('deletion');
            $this->assertDatabaseHas($record->getTable(), ['id' => $record->id]);
            if ($register === 'construction') {
                $destination = $class::create(['branch_id' => $branch, 'description' => 'Destination']);
                $line->update(['construction_destination_id' => $destination->id]);
                $this->delete($this->url($register, $destination), ['confirmed' => true])->assertSessionHasErrors('deletion');
            }
        }
    }

    public function test_stock_history_prevents_deletion_even_without_cog_links(): void
    {
        $branch = $this->staff();
        $construction = MiriConstructionItem::create(['branch_id' => $branch, 'description' => 'Stock record']);
        DB::table('miri_construction_stock_movements')->insert(['branch_id' => $branch, 'construction_item_id' => $construction->id,
            'kind' => 'opening', 'unit' => 'PC', 'quantity' => 0, 'balance_after' => 0, 'location' => 'Yard', 'note' => 'Verified',
            'request_key' => 'test-opening', 'user_id' => auth()->id(), 'created_at' => now()]);
        $this->delete($this->url('construction', $construction), ['confirmed' => true])->assertSessionHasErrors('deletion');
        $paint = MiriPaintItem::create(['branch_id' => $branch, 'description' => 'Paint history']);
        DB::table('miri_paint_stock_months')->insert(['branch_id' => $branch, 'paint_item_id' => $paint->id, 'period' => '2026-09-01', 'created_at' => now()]);
        $this->delete($this->url('paint', $paint), ['confirmed' => true])->assertSessionHasErrors('deletion');
    }
}
