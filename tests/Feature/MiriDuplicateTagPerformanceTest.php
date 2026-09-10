<?php

namespace Tests\Feature;

use App\Models\{Branch, MajorEquipment, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriDuplicateTagPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): int
    {
        $branch = Branch::where('code', 'MIRI')->value('id');
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $branch;
    }

    public function test_database_maintains_normalization_for_model_and_bulk_writes_without_changing_originals(): void
    {
        $branch = $this->staff();
        $item = MajorEquipment::create(['branch_id' => $branch, 'tag_no' => ' ABC-123 ']);
        $this->assertSame(' ABC-123 ', $item->fresh()->tag_no);
        $this->assertSame('abc-123', $item->fresh()->normalized_tag);
        $this->assertArrayNotHasKey('normalized_tag', $item->fresh()->toArray());
        $item->update(['tag_no' => ' NEW / 123 ']);
        $this->assertSame('new / 123', $item->fresh()->normalized_tag);
        MajorEquipment::whereKey($item->id)->update(['tag_no' => ' BULK-1 ']);
        $this->assertSame('bulk-1', $item->fresh()->normalized_tag);
        DB::table('miri_inventory_items')->where('id', $item->id)->update(['tag_no' => ' Direct-1 ']);
        $this->assertSame('direct-1', $item->fresh()->normalized_tag);
        foreach ([null, '', '   '] as $blank) {
            $item->update(['tag_no' => $blank]);
            $this->assertNull($item->fresh()->normalized_tag);
        }
    }

    public function test_duplicates_span_machinery_and_cargo_but_not_branches_and_keep_record_counts(): void
    {
        $branch = $this->staff();
        $ids = [];
        foreach ([['machinery', ' Tag-1 '], ['cargo', 'TAG-1'], ['cargo', 'tag-1'], ['cargo', 'TAG1'], ['machinery', 'TAG -1'], ['cargo', ''], ['cargo', null]] as [$type, $tag]) {
            $ids[] = MajorEquipment::create(['branch_id' => $branch, 'inventory_type' => $type, 'tag_no' => $tag, 'category' => 'MAJOR EQUIPMENT'])->id;
        }
        MajorEquipment::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'tag_no' => 'tag-1']);
        $this->assertSame(3, MajorEquipment::duplicateTag()->count());
        $this->assertSame(2, MajorEquipment::where('inventory_type', 'cargo')->duplicateTag()->count());
        $counts = MajorEquipment::withDuplicateCount()->get()->keyBy('id');
        $this->assertEquals(3, $counts[$ids[0]]->duplicate_count);
        $this->assertEquals(1, $counts[$ids[3]]->duplicate_count);
        $this->assertEquals(0, $counts[$ids[5]]->duplicate_count);
        $this->get(route('major-equipment.index', ['inventory_type' => 'cargo', 'quality' => 'duplicates']))->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('equipment.total', 2)->where('summary.duplicates', 2)->where('equipment.data.0.duplicate_count', 3));
        $this->get(route('major-equipment.show', $ids[0]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('duplicates', 2));
        MajorEquipment::whereIn('id', [$ids[1], $ids[2]])->update(['tag_no' => null]);
        $this->assertSame(0, MajorEquipment::duplicateTag()->count());
        $this->get(route('major-equipment.show', $ids[0]))->assertOk()->assertInertia(fn (Assert $p) => $p->has('duplicates', 0));
        $this->assertSame(7, MajorEquipment::count());
        $otherBranch = Branch::where('code', 'KL-IT')->value('id');
        MajorEquipment::create(['branch_id' => $otherBranch, 'tag_no' => ' TAG-1 ']);
        $this->assertSame(0, MajorEquipment::duplicateTag()->count());
        $this->assertSame(2, MajorEquipment::withoutGlobalScopes()->where('miri_inventory_items.branch_id', $otherBranch)->duplicateTag()->count());
    }

    public function test_grouped_query_matches_old_results_on_a_large_fixture_and_uses_the_index(): void
    {
        $branch = $this->staff();
        $rows = [];
        for ($i = 0; $i < 2582; $i++) {
            $rows[] = ['branch_id' => $branch, 'inventory_type' => $i % 2 ? 'cargo' : 'machinery', 'tag_no' => $i < 3 ? ' SAME-TAG ' : 'UNIQUE-'.$i];
        }
        foreach (array_chunk($rows, 250) as $chunk) DB::table('miri_inventory_items')->insert($chunk);
        $start = microtime(true);
        $old = MajorEquipment::query()->whereRaw("TRIM(COALESCE(miri_inventory_items.tag_no, '')) <> ''")
            ->whereRaw('(SELECT COUNT(*) FROM miri_inventory_items AS peers WHERE peers.branch_id = miri_inventory_items.branch_id AND LOWER(TRIM(peers.tag_no)) = LOWER(TRIM(miri_inventory_items.tag_no))) > 1')->count();
        $oldMs = (microtime(true) - $start) * 1000;
        $start = microtime(true);
        $new = MajorEquipment::duplicateTag()->count();
        $newMs = (microtime(true) - $start) * 1000;
        $this->assertSame(3, $new);
        $this->assertSame($old, $new);
        $query = MajorEquipment::duplicateTag()->select('miri_inventory_items.id')->toBase();
        $this->assertStringNotContainsString('LOWER(TRIM(', $query->toSql());
        if (DB::getDriverName() === 'mysql') {
            $plan = DB::select('EXPLAIN '.$query->toSql(), $query->getBindings());
            $this->assertTrue(collect($plan)->contains(fn ($step) => ($step->key ?? null) === 'miri_branch_normalized_tag_index'), json_encode($plan));
            $this->assertStringNotContainsString('DEPENDENT SUBQUERY', json_encode($plan));
        }
        fwrite(STDOUT, sprintf("\nMiri duplicate count benchmark (isolated test DB, 2582 rows): old %.1f ms; grouped %.1f ms.\n", $oldMs, $newMs));
    }
}
