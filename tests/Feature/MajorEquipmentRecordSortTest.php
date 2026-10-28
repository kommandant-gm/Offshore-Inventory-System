<?php

namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MajorEquipmentRecordSortTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_number_sort_is_numeric_and_applies_before_pagination_with_filters(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true,
            'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        $ids = [];
        for ($i = 0; $i < 27; $i++) {
            $ids[] = MajorEquipment::create(['inventory_type' => 'machinery', 'company' => 'FTSB',
                'description' => 'Item '.(27 - $i), 'section_1' => 'Machinery', 'tag_no' => 'TAG-'.(27 - $i)])->id;
        }
        MajorEquipment::create(['inventory_type' => 'machinery', 'company' => 'DESB']);
        MajorEquipment::create(['inventory_type' => 'cargo', 'company' => 'FTSB']);
        MajorEquipment::create(['inventory_type' => 'machinery', 'company' => 'FTSB',
            'branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        foreach (['record_asc' => $ids, 'record_desc' => array_reverse($ids)] as $sort => $ordered) {
            foreach ([1, 2] as $page) {
                $this->get(route('major-equipment.index', ['inventory_type' => 'machinery', 'company' => 'FTSB', 'sort' => $sort, 'page' => $page]))
                    ->assertOk()->assertInertia(fn (Assert $p) => $p->where('filters.sort', $sort)
                        ->where('equipment.total', 27)->where('summary.total', 27)
                        ->where('equipment.data', fn ($rows) => collect($rows)->pluck('id')->all() === array_slice($ordered, ($page - 1) * 25, 25)));
            }
        }
        $this->getJson(route('major-equipment.index', ['sort' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('sort');
    }
}
