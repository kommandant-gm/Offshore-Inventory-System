<?php

namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScaffoldingRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_workbook_dropdowns_and_initial_stock_with_paired_cog_rows(): void
    {
        $this->withoutVite();
        $this->editor();
        $this->get(route('scaffolding.create'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->has('scaffoldingOptions.subcategory', 5)->has('scaffoldingOptions.description', 27)
            ->has('scaffoldingOptions.contract', 5)
            ->where('scaffoldingOptions.descriptionsBySubcategory.SCAFFOLD TUBE.0', 'SCAFFOLD TUBE ODD SIZE'));
        $row = ['destination' => 'Project A', 'mr_reference' => 'MR1', 'quantity' => 20, 'reference' => 'EXTERNAL-1', 'document_date' => '2026-10-30'];
        $payload = ['category' => 'SCAFFOLD SET', 'section_2' => 'SCAFFOLD TUBE', 'description' => 'SCAFFOLD TUBE 6FT',
            'unit' => 'PC', 'current_location' => 'LABUAN', 'scaffolding_contract' => 'SHELL', 'scaffold_total' => 100,
            'scaffold_issues' => [$row, [...$row, 'quantity' => 15, 'reference' => 'EXTERNAL-2']]];
        $invalid = $payload;
        $invalid['scaffold_issues'][1]['quantity'] = 90;
        $this->post(route('scaffolding.store'), $invalid)->assertSessionHasErrors('stock');
        $this->assertDatabaseCount('miri_inventory_items', 0);
        $this->assertDatabaseCount('scaffolding_stock_accounts', 0);
        $this->assertDatabaseCount('scaffolding_stock_movements', 0);
        $this->post(route('scaffolding.store'), [...$payload, 'scaffold_issues' => array_fill(0, 11, $row)])->assertSessionHasErrors('scaffold_issues');
        $this->post(route('scaffolding.store'), [...$payload, 'scaffold_total' => null])->assertSessionHasErrors('scaffold_total');
        $this->post(route('scaffolding.store'), $payload)->assertSessionHasNoErrors();
        $item = MajorEquipment::firstOrFail();
        $this->assertTrue($item->isScaffolding());
        $this->assertDatabaseHas('scaffolding_stock_accounts', ['equipment_id' => $item->id, 'yard' => 'LABUAN', 'opening_quantity' => 100, 'balance' => 65]);
        $this->assertDatabaseHas('scaffolding_stock_movements', ['reference' => 'EXTERNAL-1', 'balance_after' => 80, 'mr_reference' => 'MR1']);
        $this->assertDatabaseHas('scaffolding_stock_movements', ['reference' => 'EXTERNAL-2', 'balance_after' => 65, 'contract' => 'SHELL']);
        $this->patch(route('major-equipment.update', $item), [...$payload, 'section_1' => 'SCAFFOLDING SET', 'remarks' => 'Metadata edit'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('scaffolding_stock_movements', 3);
        $this->assertDatabaseHas('scaffolding_stock_accounts', ['equipment_id' => $item->id, 'balance' => 65]);
    }

    private function editor(): Branch
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create(['role' => 'miri', 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $branch;
    }

    private function item(Branch $branch, array $attributes = []): MajorEquipment
    {
        return MajorEquipment::create(array_replace([
            'branch_id' => $branch->id, 'inventory_type' => 'machinery', 'category' => 'MAJOR EQUIPMENT',
            'section_1' => 'SCAFFOLDING SET', 'section_2' => 'BASE PLATE', 'description' => 'BASE PLATE',
            'current_location' => 'Miri', 'company' => 'FTSB', 'status' => 'Standby',
        ], $attributes));
    }

    public function test_existing_scaffolding_is_separated_without_recreating_records(): void
    {
        $branch = $this->editor();
        $scaffold = $this->item($branch);
        $this->item($branch, ['section_1' => ' scaffolding set ', 'description' => 'COUPLER']);
        $this->item($branch, ['section_1' => 'Machinery', 'description' => 'GENERATOR']);
        $this->item($branch, ['section_1' => null, 'description' => 'UNCLASSIFIED']);
        $this->item($branch, ['inventory_type' => 'cargo', 'section_1' => 'CARGO SET']);
        $this->item(Branch::where('code', 'KL-IT')->firstOrFail());
        $certificate = $scaffold->certificates()->create(['branch_id' => $branch->id, 'certificate_type' => 'Inspection', 'certificate_no' => 'KEEP-ME']);

        $this->get(route('scaffolding.index'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('MajorEquipment/Index')->where('register', 'scaffolding')
            ->where('equipment.total', 2)->where('summary.total', 2)->where('summary.standby', 2));
        $this->get(route('major-equipment.index'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.total', 2)->where('summary.total', 2)->where('tabCounts.machinery', 2)
            ->where('tabCounts.cargo', 1)->where('section1Options', ['Machinery']));
        $this->get(route('major-equipment.index', ['section_1' => 'SCAFFOLDING SET']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('equipment.total', 0));
        $this->get(route('major-equipment.show', $scaffold))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('register', 'scaffolding')->where('equipment.id', $scaffold->id)
            ->where('equipment.certificates.0.id', $certificate->id));
        $this->assertDatabaseCount('miri_inventory_items', 6);
        $this->get(route('major-equipment.dashboard'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('summary.total', 3)->has('expiring', 0));
    }

    public function test_live_filters_stay_inside_scaffolding_and_respect_company(): void
    {
        $branch = $this->editor();
        $this->item($branch);
        $this->item($branch, ['company' => 'DESB', 'section_2' => 'COUPLER']);
        $this->item($branch, ['section_1' => 'Machinery', 'section_2' => 'PRIVATE OPTION']);
        $this->getJson(route('scaffolding.index', ['filter_options' => 1, 'company' => 'FTSB']))
            ->assertOk()->assertJsonPath('section2Options', ['BASE PLATE'])
            ->assertJsonPath('qualityCounts.missing_details', 1);
        $this->get(route('scaffolding.index', ['company' => 'DESB', 'search' => 'BASE']))
            ->assertOk()->assertInertia(fn (Assert $p) => $p->where('equipment.total', 1));
    }

    public function test_scaffolding_creation_and_spreadsheet_preserve_register_boundaries(): void
    {
        $branch = $this->editor();
        $this->get(route('scaffolding.create'))->assertOk()->assertInertia(fn (Assert $p) => $p->where('register', 'scaffolding'));
        $this->post(route('scaffolding.store'), ['category' => 'MAJOR EQUIPMENT', 'description' => 'NEW PLATE'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $scaffold = MajorEquipment::where('description', 'NEW PLATE')->firstOrFail();
        $this->assertTrue($scaffold->isScaffolding());
        $payload = ['inventory_type' => 'machinery', 'rows' => [
            ['id' => $scaffold->id, 'changes' => ['description' => 'EDITED PLATE'], 'original' => ['description' => 'NEW PLATE']],
        ]];
        $this->patchJson(route('major-equipment.spreadsheet.update'), $payload)->assertNotFound();
        $this->patchJson(route('scaffolding.spreadsheet.update'), $payload)->assertOk();
        $this->assertSame('EDITED PLATE', $scaffold->fresh()->description);
        $this->patch(route('miri-company.assign'), ['register' => 'scaffolding', 'ids' => [$scaffold->id], 'company' => 'DESB'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('DESB', $scaffold->fresh()->company);
        $machine = $this->item($branch, ['section_1' => 'Machinery']);
        $payload['rows'][0]['id'] = $machine->id;
        $this->patchJson(route('scaffolding.spreadsheet.update'), $payload)->assertNotFound();
    }

    public function test_newly_classified_items_move_automatically_and_remain_available_to_issue_notes(): void
    {
        $branch = $this->editor();
        $item = $this->item($branch, ['section_1' => 'Machinery']);
        $this->patch(route('major-equipment.update', $item), [
            'category' => 'MAJOR EQUIPMENT', 'section_1' => 'SCAFFOLDING SET', 'description' => 'RECLASSIFIED',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('scaffolding.index'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.total', 1)->where('equipment.data.0.id', $item->id));
        $this->assertSame($item->id, app(\App\Services\MiriCogSource::class)->query('Major equipment', $branch->id)->firstOrFail()->id);
    }

    public function test_permissions_and_branch_switching_apply_to_the_new_register(): void
    {
        $this->get(route('scaffolding.index'))->assertRedirect(route('login'));
        $miri = $this->editor();
        $user = auth()->user();
        $user->permissions = array_replace($user->permissions, ['assets' => AccessMatrix::READ]);
        $user->save();
        $this->get(route('scaffolding.index'))->assertOk();
        $this->get(route('scaffolding.create'))->assertForbidden();
        $this->post(route('scaffolding.store'), ['category' => 'MAJOR EQUIPMENT'])->assertForbidden();
        $this->patch(route('branches.activate'), ['branch_id' => $miri->id, 'destination_route' => 'scaffolding.index'])
            ->assertRedirect(route('scaffolding.index'));
        $it = User::factory()->create(['role' => 'it', 'permissions' => AccessMatrix::permissionsForRole('it')]);
        $kl = Branch::where('code', 'KL-IT')->firstOrFail();
        $it->branches()->attach($kl, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($it)->withSession(['branch_id' => $kl->id])->get(route('scaffolding.index'))->assertNotFound();
    }
}
