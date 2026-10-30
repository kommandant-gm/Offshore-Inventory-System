<?php

namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScaffoldingSheetTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): MajorEquipment
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        $this->post(route('scaffolding.store'), ['category' => 'SCAFFOLD SET', 'section_2' => 'SCAFFOLD TUBE',
            'description' => 'SCAFFOLD TUBE 6FT', 'unit' => 'PC', 'current_location' => 'LABUAN', 'scaffolding_contract' => 'SHELL',
            'scaffold_total' => 100, 'scaffold_issues' => [
                ['destination' => 'Project A', 'mr_reference' => 'MR-A', 'quantity' => 20, 'reference' => 'EXT-A', 'document_date' => '2026-10-30'],
                ['destination' => 'Project B', 'mr_reference' => 'MR-B', 'quantity' => 15, 'reference' => 'EXT-B', 'document_date' => '2026-10-31'],
            ]])->assertSessionHasNoErrors();
        return MajorEquipment::firstOrFail();
    }

    private function save(MajorEquipment $item, array $changes, array $original)
    {
        return $this->patchJson(route('scaffolding.spreadsheet.update'), ['inventory_type' => 'machinery', 'rows' => [
            ['id' => $item->id, 'changes' => $changes, 'original' => $original],
        ]]);
    }

    public function test_both_views_receive_verified_stock_and_paired_issue_data_and_dropdowns(): void
    {
        $item = $this->scaffold();
        $this->get(route('scaffolding.index'))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.data.0.id', $item->id)
            ->where('equipment.data.0.labuan_opening', 100)
            ->where('equipment.data.0.labuan_issued', 35)
            ->where('equipment.data.0.labuan_balance', 65)
            ->where('equipment.data.0.bintulu_balance', 'Unverified')
            ->where('equipment.data.0.issue_1_reference', 'EXT-A')
            ->where('equipment.data.0.issue_1_balance_after', 80)
            ->where('equipment.data.0.issue_2_reference', 'EXT-B')
            ->where('equipment.data.0.issue_2_mr_reference', 'MR-B')
            ->where('equipment.data.0.scaffold_issues.1.balance_after', 65)
            ->where('sheetColumns', function ($columns) {
                $columns = collect($columns)->keyBy('key');
                return $columns['labuan_balance']['readonly'] && $columns['issue_10_reference']['readonly']
                    && $columns['description']['dependsOn'] === 'section_2'
                    && in_array('SHELL', $columns['scaffolding_contract']['options'])
                    && ! $columns->has('section_1');
            }));
    }

    public function test_excel_metadata_updates_details_but_stock_and_history_cannot_be_overwritten(): void
    {
        $item = $this->scaffold();
        $this->save($item, ['scaffolding_contract' => 'SBA', 'description' => 'SCAFFOLD TUBE 10FT'], ['scaffolding_contract' => 'SHELL', 'description' => 'SCAFFOLD TUBE 6FT'])->assertOk();
        $this->get(route('major-equipment.edit', $item))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.scaffolding_contract', 'SBA')->where('equipment.description', 'SCAFFOLD TUBE 10FT'));
        foreach (['labuan_balance' => '999', 'quantity' => '999', 'issue_1_reference' => 'REWRITTEN', 'section_1' => 'Machinery'] as $key => $value) {
            $this->save($item, [$key => $value], [$key => ''])->assertUnprocessable();
        }
        $this->save($item, ['unit' => 'SET'], ['unit' => 'PC'])->assertUnprocessable()->assertJsonValidationErrors('rows.0.changes.unit');
        $this->save($item, ['description' => 'WOODEN LADDER 6FT'], ['description' => 'SCAFFOLD TUBE 10FT'])->assertUnprocessable();
        $this->save($item, ['section_2' => 'LADDERS'], ['section_2' => 'SCAFFOLD TUBE'])->assertUnprocessable();
        $this->save($item, ['current_location' => 'ANYWHERE'], ['current_location' => 'LABUAN'])->assertUnprocessable();
        $this->save($item, ['scaffolding_contract' => 'SHELL'], ['scaffolding_contract' => 'OLD VALUE'])->assertUnprocessable();
        $this->assertDatabaseHas('scaffolding_stock_accounts', ['equipment_id' => $item->id, 'balance' => 65]);
        $this->assertDatabaseHas('scaffolding_stock_movements', ['reference' => 'EXT-A', 'balance_after' => 80]);
    }

    public function test_issue_filters_and_search_find_confirmed_cog_entries(): void
    {
        $this->scaffold();
        foreach ([['search' => 'EXT-B'], ['search' => 'MR-B'], ['issue_out_location' => 'Project B']] as $filters) {
            $this->get(route('scaffolding.index', $filters))->assertOk()->assertInertia(fn (Assert $p) => $p->where('equipment.total', 1));
        }
        $this->getJson(route('scaffolding.index', ['filter_options' => 1]))->assertOk()->assertJsonPath('issueOutLocationOptions', ['Project A', 'Project B']);
    }
}
