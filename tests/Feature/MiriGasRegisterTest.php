<?php

namespace Tests\Feature;

use App\Models\{Branch, MiriRentalItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriGasRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true,
            'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
    }

    public function test_registers_partition_suppliers_including_case_whitespace_and_missing_values(): void
    {
        foreach (['BIG', 'LGM', ' big ', 'lgm', 'OTHER', null, '', 'BIG OTHER'] as $supplier) {
            MiriRentalItem::create(['supplier' => $supplier, 'status' => 'On Hire']);
        }
        MiriRentalItem::create(['supplier' => 'BIG', 'branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        foreach (['miri-gas.index' => true, 'miri-rental.index' => false] as $route => $gas) {
            $this->get(route($route))->assertOk()->assertInertia(fn (Assert $p) => $p
                ->component('MiriRental/Index')->where('isGas', $gas)->where('rentals.total', 4)
                ->where('summary.total', 4)->where('summary.on_hire', 4)
                ->where('rentals.data', fn ($rows) => collect($rows)->every(fn ($row) =>
                    in_array(strtoupper(trim((string) $row['supplier'])), ['BIG', 'LGM'], true) === $gas)));
        }
    }

    public function test_filters_options_and_pagination_cannot_leak_between_registers(): void
    {
        for ($i = 0; $i < 26; $i++) MiriRentalItem::create(['supplier' => 'BIG', 'status' => 'On Hire', 'current_location' => 'Gas yard']);
        MiriRentalItem::create(['supplier' => 'OTHER', 'status' => 'Issued', 'current_location' => 'Rental yard']);
        $this->get(route('miri-gas.index', ['page' => 2]))->assertInertia(fn (Assert $p) => $p
            ->where('rentals.total', 26)->has('rentals.data', 1)->where('rentals.current_page', 2));
        $this->getJson(route('miri-gas.index', ['filter_options' => 1]))->assertOk()
            ->assertJsonPath('supplierOptions', ['BIG'])->assertJsonPath('locationOptions', ['Gas yard']);
        $this->getJson(route('miri-rental.index', ['filter_options' => 1]))->assertOk()
            ->assertJsonPath('supplierOptions', ['OTHER'])->assertJsonPath('locationOptions', ['Rental yard']);
        $this->get(route('miri-rental.index', ['supplier' => 'BIG']))->assertInertia(fn (Assert $p) => $p->where('rentals.total', 0));
        $this->get(route('miri-gas.index', ['search' => 'OTHER']))->assertInertia(fn (Assert $p) => $p->where('rentals.total', 0));
    }

    public function test_existing_records_keep_identity_and_follow_supplier_edits(): void
    {
        $item = MiriRentalItem::create(['supplier' => 'BIG', 'status' => 'On Hire', 'issue_out_cog_no' => 'COG-123']);
        foreach (['show', 'edit'] as $action) {
            $this->get(route('miri-rental.'.$action, $item))->assertInertia(fn (Assert $p) => $p
                ->where('isGas', true)->where('rental.id', $item->id)->where('rental.issue_out_cog_no', 'COG-123'));
        }
        $this->get(route('miri-gas.create'))->assertInertia(fn (Assert $p) => $p->where('isGas', true));
        $this->get(route('miri-gas.import'))->assertInertia(fn (Assert $p) => $p->where('isGas', true));
        $this->patch(route('miri-rental.update', $item), ['supplier' => 'OTHER', 'status' => 'On Hire'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('COG-123', $item->fresh()->issue_out_cog_no);
        $this->get(route('miri-gas.index'))->assertInertia(fn (Assert $p) => $p->where('rentals.total', 0));
        $this->get(route('miri-rental.index'))->assertInertia(fn (Assert $p) => $p->where('rentals.total', 1));
    }

    public function test_gas_register_respects_read_and_edit_permissions(): void
    {
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $this->get(route('miri-gas.index'))->assertOk();
        $this->get(route('miri-gas.create'))->assertForbidden();
        $this->get(route('miri-gas.import'))->assertForbidden();
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        $this->get(route('miri-gas.index'))->assertForbidden();
    }
}
