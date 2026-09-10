<?php

namespace Tests\Feature;

use App\Models\{Branch, MiriConstructionItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriConstructionDashboardTest extends TestCase
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

    public function test_dashboard_aggregates_only_construction_in_active_branch_and_excludes_private_data(): void
    {
        $branch = $this->staff();
        foreach ([0, 10, null] as $i => $balance) {
            MiriConstructionItem::create(['branch_id' => $branch, 'category' => $i ? 'PPE' : 'BLAST GRIT',
                'stock_balance' => $balance, 'unit' => $i ? 'PC' : 'Ton', 'tag_no' => $i < 2 ? 'duplicate' : null,
                'needs_review' => $i === 2, 'personnel_details' => 'PRIVATE-IC', 'source_values' => ['PRIVATE-SOURCE'],
                'certificate_due_date' => $i ? null : '2026-01-01']);
        }
        MiriConstructionItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id'), 'category' => 'PRIVATE-BRANCH']);
        DB::enableQueryLog();
        $this->get(route('major-equipment.dashboard', ['view' => 'construction']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('activeDashboard', 'construction')
                ->where('constructionDashboard.summary.total', 3)
                ->where('constructionDashboard.summary.balance_recorded', 2)
                ->where('constructionDashboard.summary.zero_balance', 1)
                ->where('constructionDashboard.summary.certificate_dates', 1)
                ->where('constructionDashboard.summary.duplicates', 2)
                ->where('constructionDashboard.summary.review', 3)
                ->has('constructionDashboard.categories', 2)
                ->where('constructionDashboard.statuses.0.label', 'Not recorded')
                ->where('constructionDashboard.statuses.0.total', 3)
                ->has('constructionDashboard.recent', 3)->missing('rentalDashboard')->missing('summary'))
            ->assertDontSee('PRIVATE-IC')->assertDontSee('PRIVATE-SOURCE')->assertDontSee('PRIVATE-BRANCH');
        $sql = implode(' ', array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        $this->assertStringNotContainsString('miri_inventory_items', $sql);
        $this->assertStringNotContainsString('miri_rental_items', $sql);
    }

    public function test_empty_dashboard_and_read_only_actions(): void
    {
        $this->staff();
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $this->get(route('major-equipment.dashboard', ['view' => 'construction']))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('constructionDashboard.summary.total', 0)
                ->where('constructionDashboard.summary.zero_balance', 0)->has('constructionDashboard.recent', 0)
                ->where('canEditConstruction', false));
    }
}
