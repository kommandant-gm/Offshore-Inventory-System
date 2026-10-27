<?php

namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, MiriRentalItem, MiriConstructionItem, MiriPaintItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiriCogItemSearchTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): int
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create(['role' => 'miri', 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $branch->id;
    }

    public function test_tag_serial_company_and_register_filters_combine_before_pagination(): void
    {
        $branch = $this->staff();
        $base = ['branch_id' => $branch, 'company' => 'FTSB', 'inventory_type' => 'machinery', 'section_1' => 'Machinery',
            'section_2' => 'COMPRESSOR', 'description' => 'AIR COMPRESSOR', 'current_location' => 'Bintulu', 'unit' => 'UNIT', 'model_brand' => 'AIRMAN'];
        for ($i = 0; $i < 30; $i++) MajorEquipment::create($base + ['tag_no' => 'TAG-'.$i, 'serial_no' => 'SERIAL-'.$i]);
        MajorEquipment::create(array_replace($base, ['company' => 'DESB', 'tag_no' => 'TAG-29', 'serial_no' => 'SERIAL-29']));
        MajorEquipment::create(array_replace($base, ['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'tag_no' => 'TAG-29', 'serial_no' => 'SERIAL-29']));
        $scaffold = MajorEquipment::create(array_replace($base, ['section_1' => ' scaffolding set ', 'description' => 'BASE PLATE']));
        $params = ['type' => 'Major equipment', 'company' => 'FTSB', 'inventory_type' => 'machinery', 'tag_no' => 'TAG-29', 'serial_no' => 'SERIAL-29', 'section_2' => 'COMPRESSOR', 'location' => 'Bintulu', 'description' => 'AIR COMPRESSOR'];
        $this->getJson(route('miri-cogs.items', $params))->assertOk()->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.identifier', 'TAG-29')->assertJsonPath('items.0.serial_no', 'SERIAL-29')
            ->assertJsonPath('items.0.unit', 'UNIT')->assertJsonPath('items.0.size_model', 'AIRMAN')
            ->assertJsonPath('options.description', ['AIR COMPRESSOR'])->assertJsonPath('has_more', false);
        $this->getJson(route('miri-cogs.items', array_replace($params, ['serial_no' => 'SERIAL-28'])))->assertOk()->assertJsonCount(0, 'items');
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'inventory_type' => 'scaffolding']))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.id', $scaffold->id);
        $this->getJson(route('miri-cogs.items', ['type' => 'Major equipment', 'inventory_type' => 'machinery', 'search' => 'BASE PLATE']))
            ->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_other_registers_support_description_subcategory_location_and_identifiers(): void
    {
        $branch = $this->staff();
        foreach (['Rental' => MiriRentalItem::class, 'Construction' => MiriConstructionItem::class, 'Paint' => MiriPaintItem::class] as $type => $model) {
            $identifier = match ($type) { 'Rental' => 'serial_tag_equipment_no', 'Paint' => 'batch_no', default => 'tag_no' };
            $base = ['branch_id' => $branch, 'description' => 'MATCH', 'section_2' => 'GROUP', 'current_location' => 'Miri', $identifier => 'REF-42'];
            $model::create($base);
            $model::create(array_replace($base, ['description' => 'OTHER', 'current_location' => 'Labuan']));
            $params = ['type' => $type, 'description' => 'MATCH', 'section_2' => 'GROUP', 'location' => 'Miri', 'tag_no' => '42'];
            $this->getJson(route('miri-cogs.items', $params))->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('options.location', ['Miri']);
            $this->getJson(route('miri-cogs.items', array_replace($params, ['tag_no' => 'MISSING'])))->assertOk()->assertJsonCount(0, 'items');
        }
    }
}
