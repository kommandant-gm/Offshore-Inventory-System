<?php
namespace Tests\Feature;

use App\Models\{AuditLog, Branch, MiriCog, MiriPaintItem, User};
use App\Services\{MiriCogDocument, MiriCogEditing};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriCogEditingTest extends TestCase
{
    use RefreshDatabase;
    private function note(): MiriCog
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        $paint = MiriPaintItem::create(['branch_id' => $branch, 'category' => 'PAINT', 'description' => 'Original paint', 'balance_litres' => 10]);
        $this->post(route('miri-cogs.store'), ['movement_type' => 'Issue out', 'document_date' => '2026-09-23', 'issued_by_name' => 'Storekeeper',
            'items' => [['item_type' => 'Paint', 'item_id' => $paint->id, 'quantity' => 2, 'unit' => 'LTR']],
        ])->assertSessionHasNoErrors();
        return MiriCog::latest('id')->firstOrFail();
    }
    private function payload(MiriCog $cog): array
    {
        return ['edit_token' => app(MiriCogEditing::class)->token($cog->fresh()), 'document_date' => '2026-09-24', 'issued_by_name' => 'Corrected issuer',
            'receiver_name' => 'Corrected receiver', 'to_location' => 'LBN', 'remarks' => 'Corrected reference',
            'items' => $cog->items()->get()->map(fn ($item) => ['id' => $item->id, 'description' => 'Corrected paint description', 'mr_reference' => 'MR-CORRECTED'])->all()];
    }
    public function test_draft_edit_updates_document_and_pdf_without_reposting_stock(): void
    {
        $cog = $this->note();
        $number = $cog->cog_no;
        $this->get(route('miri-cogs.edit', $cog))->assertOk()->assertInertia(fn (Assert $p) => $p->component('MiriCog/Edit')->has('cog.items', 1)->has('editToken'));
        $data = $this->payload($cog);
        $this->patch(route('miri-cogs.update', $cog), $data)->assertSessionHasNoErrors()->assertRedirect(route('miri-cogs.show', $cog));
        $cog->refresh();
        $this->assertSame($number, $cog->cog_no);
        $this->assertSame('Corrected receiver', $cog->receiver_name);
        $this->assertSame('MR-CORRECTED', $cog->items->first()->mr_reference);
        $this->assertSame('2.000', $cog->items->first()->quantity);
        $this->assertSame('8.000', MiriPaintItem::findOrFail($cog->items->first()->item_id)->balance_litres);
        $this->assertDatabaseCount('miri_paint_stock_movements', 1);
        $this->assertSame(1, AuditLog::where('module', 'miri_cogs')->where('event', 'updated')->count());
        $document = app(MiriCogDocument::class)->data($cog);
        $this->assertStringContainsString('Corrected paint description', json_encode($document));
        $this->patch(route('miri-cogs.update', $cog), $data)->assertSessionHasErrors('note');
    }
    public function test_locked_notes_cannot_be_edited_even_with_an_old_form(): void
    {
        $cog = $this->note();
        $data = $this->payload($cog);
        foreach ([['status' => 'signed', 'signature' => 'signature'], ['status' => 'confirmed', 'signature' => null],
            ['status' => 'cancelled'], ['status' => 'draft', 'construction_stock_confirmed_at' => now()]] as $state) {
            $cog->update($state);
            $this->get(route('miri-cogs.edit', $cog))->assertStatus(409);
            $this->patch(route('miri-cogs.update', $cog), $data)->assertSessionHasErrors('note');
        }
        $this->assertSame('Storekeeper', $cog->fresh()->issued_by_name);
    }
    public function test_stock_fields_and_foreign_lines_cannot_be_modified(): void
    {
        $cog = $this->note();
        foreach (['quantity' => 9, 'unit' => 'CAN', 'item_id' => 999, 'construction_destination_id' => 1] as $key => $value) {
            $data = $this->payload($cog); $data['items'][0][$key] = $value;
            $this->patch(route('miri-cogs.update', $cog), $data)->assertSessionHasErrors('items.0.'.$key);
        }
        $data = $this->payload($cog); $data['movement_type'] = 'Received backload';
        $this->patch(route('miri-cogs.update', $cog), $data)->assertSessionHasErrors('movement_type');
        $data = $this->payload($cog); $data['items'][0]['id'] = 999;
        $this->patch(route('miri-cogs.update', $cog), $data)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('miri_paint_stock_movements', 1);
    }
    public function test_editor_permissions_branch_scope_and_list_editability(): void
    {
        $cog = $this->note();
        $this->get(route('miri-cogs.index'))->assertInertia(fn (Assert $p) => $p->where('cogs.data.0.editable', true));
        $data = $this->payload($cog);
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $this->get(route('miri-cogs.edit', $cog))->assertForbidden();
        $this->patch(route('miri-cogs.update', $cog), $data)->assertForbidden();
        auth()->user()->update(['permissions' => AccessMatrix::permissionsForRole('miri')]);
        $cog->update(['branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        $this->get(route('miri-cogs.edit', $cog))->assertNotFound();
        $this->patch(route('miri-cogs.update', $cog), $data)->assertNotFound();
    }
}
