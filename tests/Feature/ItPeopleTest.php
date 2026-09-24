<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ItLicense;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItPeopleTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_directory_combines_profile_assets_licences_and_history(): void
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $viewer = $this->branchUser($branch, 'Directory Viewer', 'viewer', 'read');
        $holder = $this->branchUser($branch, 'Alex Holder', 'EMP-100', 'read');
        $category = Category::create([
            'code' => 'PEOPLE-TEST',
            'name' => 'People Test',
            'type' => 'asset',
            'active' => true,
        ]);

        $currentAsset = $this->asset($branch, $category, 'KL-PEOPLE-001', 'Current laptop');
        $currentAsset->assignments()->create([
            'branch_id' => $branch->id,
            'assigned_to_name' => $holder->name,
            'employee_id' => $holder->username,
            'department' => 'IT',
            'assigned_at' => '2026-07-01',
        ]);

        $returnedAsset = $this->asset($branch, $category, 'KL-PEOPLE-002', 'Returned laptop');
        $returnedAsset->assignments()->create([
            'branch_id' => $branch->id,
            'assigned_to_name' => $holder->name,
            'employee_id' => $holder->username,
            'department' => 'IT',
            'assigned_at' => '2026-01-10',
            'returned_at' => '2026-06-30',
        ]);

        ItLicense::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'license_code' => 'LIC-PEOPLE-001',
            'software_name' => 'Microsoft 365',
            'license_type' => 'subscription',
            'seats_total' => 1,
            'seats_assigned' => 1,
            'assigned_to' => $holder->name,
            'department' => 'IT',
            'active' => true,
        ]);

        $this->actingAs($viewer)
            ->get(route('it-people.index', ['search' => 'Alex']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItPeople/Index')
                ->has('people', 1)
                ->where('people.0.name', 'Alex Holder')
                ->where('people.0.current_assets', 1)
                ->where('people.0.licences', 1)
                ->where('people.0.assignment_history', 2));

        $token = rtrim(strtr(base64_encode("u:{$holder->id}"), '+/', '-_'), '=');

        $this->get(route('it-people.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItPeople/Show')
                ->where('person.name', 'Alex Holder')
                ->where('person.employee_id', 'EMP-100')
                ->where('person.email', $holder->email)
                ->where('person.department', $holder->department)
                ->where('summary.current_assets', 1)
                ->where('summary.licences', 1)
                ->where('summary.history_events', 3)
                ->where('currentAssets.0.asset_tag_no', 'KL-PEOPLE-001')
                ->where('licences.0.license_code', 'LIC-PEOPLE-001')
                ->has('history', 3));
    }

    public function test_user_without_it_asset_access_cannot_view_people(): void
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $permissions = AccessMatrix::permissionsForRole('viewer');
        $permissions['it_assets'] = AccessMatrix::NONE;
        $user = User::factory()->create([
            'role' => 'technician',
            'directory_active' => true,
            'permissions' => $permissions,
        ]);
        $user->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);

        $this->actingAs($user)->get(route('it-people.index'))->assertForbidden();
    }

    public function test_ad_profile_does_not_offer_manual_linking_and_handles_a_stale_submission(): void
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $editor = $this->branchUser($branch, 'IT Editor', 'editor', 'edit');
        $editor->update(['permissions' => AccessMatrix::permissionsForRole('it')]);
        $holder = $this->branchUser($branch, 'AD Holder', 'ad.holder', 'read');
        $holder->update(['directory_active' => true]);
        $token = rtrim(strtr(base64_encode("u:{$holder->id}"), '+/', '-_'), '=');

        $this->actingAs($editor)->get(route('it-people.show', $token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItPeople/Show')
                ->where('canLink', false)
                ->has('linkOptions', 0));

        $this->post(route('it-people.link-ad', $token), ['user_id' => $holder->id])
            ->assertRedirect(route('it-people.show', $token))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('it_person_links', 0);
    }

    public function test_manual_profile_can_still_be_linked_to_an_active_ad_user(): void
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $editor = $this->branchUser($branch, 'IT Editor', 'editor', 'edit');
        $editor->update(['permissions' => AccessMatrix::permissionsForRole('it')]);
        $holder = $this->branchUser($branch, 'AD Holder', 'ad.holder', 'read');
        $holder->update(['directory_active' => true]);
        ItLicense::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'license_code' => 'LIC-MANUAL',
            'software_name' => 'Manual licence',
            'license_type' => 'subscription',
            'seats_total' => 1,
            'seats_assigned' => 1,
            'assigned_to' => 'Manual Holder',
            'active' => true,
        ]);
        $token = rtrim(strtr(base64_encode('n:manual holder'), '+/', '-_'), '=');
        $url = route('it-people.show', $token);

        $this->actingAs($editor)->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItPeople/Show')
                ->where('canLink', true));

        $this->from($url)->post(route('it-people.link-ad', $token), ['user_id' => $holder->id])
            ->assertRedirect($url)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('it_person_links', [
            'manual_identity' => 'manual holder',
            'user_id' => $holder->id,
        ]);
        $this->get($url)->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItPeople/Show')
                ->where('canLink', true)
                ->where('person.linked_user_id', $holder->id)
                ->where('person.name', $holder->name)
                ->where('summary.licences', 1));
    }

    private function branchUser(Branch $branch, string $name, string $username, string $accessLevel): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'username' => $username,
            'role' => 'technician',
            'directory_active' => true,
            'permissions' => array_replace(AccessMatrix::permissionsForRole('viewer'), ['it_assets' => AccessMatrix::READ]),
        ]);
        $user->branches()->attach($branch, ['access_level' => $accessLevel, 'is_default' => true]);

        return $user;
    }

    private function asset(Branch $branch, Category $category, string $tag, string $description): Asset
    {
        return Asset::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'asset_tag_no' => $tag,
            'description' => $description,
            'category_id' => $category->id,
            'current_status' => 'deployed',
            'current_condition' => 'good',
            'active' => true,
        ]);
    }
}
