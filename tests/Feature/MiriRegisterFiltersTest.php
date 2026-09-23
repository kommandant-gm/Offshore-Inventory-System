<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MajorEquipment;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriRegisterFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_dropdowns_follow_search_other_filters_and_inventory_type(): void
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        $fields = [
            'category' => ['category', 'categoryOptions'],
            'section_1' => ['section_1', 'section1Options'],
            'section_2' => ['section_2', 'section2Options'],
            'location' => ['current_location', 'locationOptions'],
            'issue_out_location' => ['issue_out_location', 'issueOutLocationOptions'],
            'status' => ['status', 'statusOptions'],
        ];
        foreach (['cargo', 'machinery'] as $type) {
            $record = ['branch_id' => $branch->id, 'inventory_type' => $type, 'description' => 'RUBBISH SKID', 'tag_no' => $type.'-1'];
            foreach ($fields as [$column]) $record[$column] = $type.' A';
            MajorEquipment::create($record);
            foreach ($fields as [$column]) MajorEquipment::create(array_replace($record, [$column => $type.' B', 'tag_no' => $type.'-'.$column]));
            $other = array_replace($record, ['description' => 'CONTAINER', 'tag_no' => $type.'-other']);
            foreach ($fields as [$column]) $other[$column] = 'Unrelated';
            MajorEquipment::create($other);
            MajorEquipment::create(array_replace($other, ['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'description' => 'RUBBISH SKID']));
        }
        foreach (['cargo', 'machinery'] as $type) {
            $selection = ['inventory_type' => $type, 'search' => 'rubbish skid'];
            $response = $this->get(route('major-equipment.index', $selection));
            $response->assertOk()->assertInertia(function (Assert $p) use ($type, $fields) {
                $p->where('equipment.total', 7)->where('summary.total', 7);
                foreach ($fields as [, $prop]) $p->where($prop, [$type.' A', $type.' B']);
            });
            foreach ($fields as $key => [$column, $prop]) $selection[$key] = $type.' A';
            $this->get(route('major-equipment.index', $selection))->assertOk()->assertInertia(function (Assert $p) use ($type, $fields) {
                $p->where('equipment.total', 1)->where('summary.total', 1);
                foreach ($fields as [, $prop]) $p->where($prop, [$type.' A', $type.' B']);
            });
            foreach ($fields as $key => [$column, $prop]) {
                $this->get(route('major-equipment.index', array_replace($selection, [$key => $type.' B'])))->assertOk()
                    ->assertInertia(function (Assert $p) use ($type, $fields, $key, $prop) {
                        $p->where('equipment.total', 1)->where($prop, [$type.' A', $type.' B']);
                        foreach ($fields as $otherKey => [, $otherProp]) {
                            if ($otherKey !== $key) $p->where($otherProp, [$type.' A']);
                        }
                    });
            }
            $this->get(route('major-equipment.index', array_replace($selection, ['search' => 'no such equipment'])))->assertOk()
                ->assertInertia(function (Assert $p) use ($fields, $type) {
                    $p->where('equipment.total', 0)->where('summary.total', 0)->where('filters.status', $type.' A');
                    foreach ($fields as [, $prop]) $p->has($prop, 0);
                });
        }
    }

    public function test_quality_filters_narrow_options_and_counts_follow_search(): void
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        $record = ['branch_id' => $branch->id, 'inventory_type' => 'cargo', 'category' => 'MAJOR EQUIPMENT', 'description' => 'RUBBISH SKID', 'tag_no' => 'UNIQUE', 'current_location' => 'Miri', 'status' => 'Standby'];
        MajorEquipment::create($record);
        MajorEquipment::create(array_replace($record, ['tag_no' => null, 'status' => 'Under Repair', 'import_warnings' => ['Review source']]));
        MajorEquipment::create(array_replace($record, ['tag_no' => 'DUP', 'status' => 'In Use']));
        MajorEquipment::create(array_replace($record, ['inventory_type' => 'machinery', 'tag_no' => 'DUP']));
        MajorEquipment::create(array_replace($record, ['description' => 'CONTAINER', 'tag_no' => null, 'status' => 'Damaged', 'import_warnings' => ['Other warning']]));
        foreach (['missing' => 'Under Repair', 'warnings' => 'Under Repair', 'duplicates' => 'In Use'] as $quality => $status) {
            $this->get(route('major-equipment.index', ['inventory_type' => 'cargo', 'search' => 'rubbish skid', 'quality' => $quality]))
                ->assertOk()->assertInertia(fn (Assert $p) => $p->where('equipment.total', 1)->where('statusOptions', [$status])
                    ->where('summary.total', 3)->where('summary.missing_details', 1)->where('summary.warnings', 1)->where('summary.duplicates', 1));
        }
    }
    public function test_rental_construction_and_paint_options_follow_search_and_other_filters(): void
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        foreach ([
            [\App\Models\MiriRentalItem::class, 'miri-rental.index', 'rentals', 'section2Options', 'locationOptions'],
            [\App\Models\MiriConstructionItem::class, 'construction.index', 'records', 'options.section_2', 'options.location'],
            [\App\Models\MiriPaintItem::class, 'paint.index', 'records', 'options.section_2', 'options.location'],
        ] as [$model, $route, $records, $types, $locations]) {
            $base = ['branch_id' => $branch->id, 'category' => 'Category', 'section_1' => 'Section', 'section_2' => 'A', 'description' => 'MATCH ITEM', 'current_location' => 'Miri'];
            if ($route === 'miri-rental.index') $base += ['supplier' => 'Supplier A', 'status' => 'On Hire', 'rental_due_date' => today()->addDays(3)->toDateString()];
            $model::create($base);
            $model::create(array_replace($base, ['section_2' => 'B']));
            $model::create(array_replace($base, ['current_location' => 'Bintulu']));
            $model::create(array_replace($base, ['description' => 'UNRELATED', 'section_2' => 'Unrelated', 'current_location' => 'Unrelated']));
            $model::create(array_replace($base, ['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'section_2' => 'Private']));
            $this->get(route($route, ['search' => 'MATCH ITEM', 'section_2' => 'A', 'location' => 'Miri']))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->where($records.'.total', 1)->where($types, ['A', 'B'])->where($locations, ['Bintulu', 'Miri'])->where('summary.total', 1));
            $this->get(route($route, ['search' => 'MATCH ITEM', 'section_2' => 'B', 'location' => 'Miri']))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->where($records.'.total', 1)->where($types, ['A', 'B'])->where($locations, ['Miri']));
            $this->get(route($route, ['search' => 'NOT FOUND', 'section_2' => 'A']))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->where($records.'.total', 0)->has($types, 0)->has($locations, 0)->where('filters.section_2', 'A'));
        }
        $this->get(route('miri-rental.index', ['search' => 'MATCH ITEM']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('statusOptions', ['On Hire'])->where('supplierOptions', ['Supplier A'])->where('dueOptions', ['due_7']));
        $this->get(route('miri-rental.index', ['search' => 'MATCH ITEM', 'due_status' => 'overdue']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('rentals.total', 0)->has('statusOptions', 0)->where('dueOptions', ['due_7']));
        $this->get(route('construction.index', ['search' => 'NOT FOUND']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('qualityOptions', 0));
        $this->get(route('paint.index', ['search' => 'NOT FOUND']))->assertOk()->assertInertia(fn (Assert $p) => $p->has('qualityOptions', 0));
    }

    public function test_cargo_description_filter_is_exact_and_options_follow_other_filters(): void
    {
        $branch = Branch::where('code', 'MIRI')->firstOrFail();
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        $base = ['branch_id' => $branch->id, 'inventory_type' => 'cargo', 'category' => 'MAJOR EQUIPMENT', 'current_location' => 'Miri'];
        foreach ([['RUBBISH SKID', 'Miri'], ['RUBBISH SKID LARGE', 'Bintulu'], ['CONTAINER', 'Miri'], ['', 'Miri']] as [$description, $location]) {
            MajorEquipment::create(array_replace($base, ['description' => $description, 'current_location' => $location]));
        }
        MajorEquipment::create(array_replace($base, ['inventory_type' => 'machinery', 'description' => 'MACHINE']));
        MajorEquipment::create(array_replace($base, ['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'description' => 'PRIVATE']));
        $url = route('major-equipment.index');
        $this->get($url.'?inventory_type=cargo&description=RUBBISH%20SKID')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.total', 1)->where('summary.total', 1)->where('equipment.data.0.description', 'RUBBISH SKID')
            ->where('filters.description', 'RUBBISH SKID')->where('locationOptions', ['Miri'])
            ->where('descriptionOptions', ['CONTAINER', 'RUBBISH SKID', 'RUBBISH SKID LARGE']));
        $this->get($url.'?inventory_type=cargo&search=skid&location=Miri')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.total', 1)->where('descriptionOptions', ['RUBBISH SKID']));
        $this->get($url.'?inventory_type=cargo&description=RUBBISH%20SKID&location=Bintulu')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.total', 0)->where('filters.description', 'RUBBISH SKID')->where('descriptionOptions', ['RUBBISH SKID LARGE']));
        $this->get($url.'?inventory_type=machinery&description=RUBBISH%20SKID')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.total', 1)->where('filters.description', '')->has('descriptionOptions', 0));
    }

}
