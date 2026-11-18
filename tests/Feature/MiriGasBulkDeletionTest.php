<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Branch, MiriCog, MiriRentalItem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriGasBulkDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function login(string $username = 'codex'): User
    {
        $this->withoutVite();
        $user = User::factory()->create(['username' => $username, 'role' => 'admin', 'directory_active' => true]);
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user)->withSession(['branch_id' => $branch]);
        return $user;
    }

    private function confirmed(): array
    {
        return ['confirmed' => true, 'confirmation' => 'DELETE ALL GAS'];
    }

    public function test_only_codex_with_edit_access_sees_and_can_use_bulk_deletion(): void
    {
        $user = $this->login('another-admin');
        $item = MiriRentalItem::create(['supplier' => 'BIG']);
        $this->get(route('miri-gas.index'))->assertInertia(fn (Assert $p) => $p->where('canDeleteAllGas', false));
        $this->delete(route('miri-gas.destroy-all'), $this->confirmed())->assertForbidden();
        $user->update(['username' => 'CoDeX']);
        $this->get(route('miri-gas.index'))->assertInertia(fn (Assert $p) => $p->where('canDeleteAllGas', true));
        $this->get(route('miri-rental.index'))->assertInertia(fn (Assert $p) => $p->where('canDeleteAllGas', false));
        $user->branches()->updateExistingPivot($item->branch_id, ['access_level' => 'read']);
        $this->get(route('miri-gas.index'))->assertInertia(fn (Assert $p) => $p->where('canDeleteAllGas', false));
        $this->delete(route('miri-gas.destroy-all'), $this->confirmed())->assertForbidden();
        $this->assertDatabaseHas('miri_rental_items', ['id' => $item->id]);
    }

    public function test_confirmation_is_required_and_other_branch_context_is_rejected(): void
    {
        $this->login();
        $item = MiriRentalItem::create(['supplier' => 'BIG']);
        $this->delete(route('miri-gas.destroy-all'))->assertSessionHasErrors(['confirmed', 'confirmation']);
        $this->delete(route('miri-gas.destroy-all'), ['confirmed' => true, 'confirmation' => 'delete'])->assertSessionHasErrors('confirmation');
        $this->withSession(['branch_id' => Branch::where('code', 'KL-IT')->value('id')])
            ->delete(route('miri-gas.destroy-all'), $this->confirmed())->assertNotFound();
        $this->assertDatabaseHas('miri_rental_items', ['id' => $item->id]);
    }

    public function test_deletes_all_pages_and_companies_but_preserves_rentals_and_other_branches(): void
    {
        $this->login();
        for ($i = 0; $i < 28; $i++) {
            MiriRentalItem::create(['supplier' => $i % 2 ? ' big ' : 'lgm', 'company' => $i % 2 ? 'DESB' : 'FTSB']);
        }
        foreach (['OTHER', null, '', 'BIG OTHER'] as $supplier) MiriRentalItem::create(['supplier' => $supplier]);
        $foreign = MiriRentalItem::create(['supplier' => 'BIG', 'branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        $this->delete(route('miri-gas.destroy-all', ['supplier' => 'BIG', 'company' => 'DESB', 'page' => 2]), $this->confirmed())
            ->assertRedirect(route('miri-gas.index'))->assertSessionHasNoErrors()->assertSessionHas('success', 'Deleted 28 Gas register records.');
        $this->assertDatabaseCount('miri_rental_items', 5);
        $this->assertDatabaseHas('miri_rental_items', ['id' => $foreign->id]);
        $this->assertSame(28, AuditLog::where('event', 'deleted')->where('auditable_type', MiriRentalItem::class)->count());
    }

    public function test_cog_link_blocks_entire_deletion_without_partial_changes(): void
    {
        $this->login();
        MiriRentalItem::create(['supplier' => 'BIG']);
        $linked = MiriRentalItem::create(['supplier' => 'LGM']);
        $cog = MiriCog::create(['branch_id' => $linked->branch_id, 'cog_no' => 'GAS-TEST', 'movement_type' => 'Issue out', 'document_date' => '2026-11-18', 'status' => 'draft']);
        $cog->items()->create(['branch_id' => $linked->branch_id, 'item_type' => 'Rental', 'item_id' => $linked->id, 'quantity' => 1]);
        $this->delete(route('miri-gas.destroy-all'), $this->confirmed())->assertSessionHasErrors('deletion');
        $this->assertDatabaseCount('miri_rental_items', 2);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
