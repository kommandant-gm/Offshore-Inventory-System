<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Category;
use App\Models\User;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ItAssetQrCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_generate_print_page_and_regenerate_public_asset_token(): void
    {
        [$user, $asset] = $this->editorAndAsset();

        $this->actingAs($user)
            ->get(route('it-assets.qr-code.show', $asset))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItAssets/QrCode')
                ->where('asset.public_url', null));

        $this->post(route('it-assets.qr-code.store', $asset))->assertRedirect();
        $oldToken = $asset->refresh()->public_token;
        $this->assertNotNull($oldToken);
        $this->assertSame(48, strlen($oldToken));
        $asset->assignments()->create([
            'assigned_to_name' => 'Public Custodian',
            'department' => 'IT',
            'assigned_at' => '2026-07-21',
            'assigned_by' => $user->id,
        ]);

        auth()->logout();
        $this->get(route('public.it-assets.show', $oldToken))
            ->assertOk()
            ->assertSee('KL-QR-001')
            ->assertSee('Test Laptop')
            ->assertSee('SN-PRIVATE-001')
            ->assertSee('Public Custodian')
            ->assertSee('Department')
            ->assertDontSee('Internal confidential note')
            ->assertDontSee('4999.00');

        $this->actingAs($user)->post(route('it-assets.qr-code.regenerate', $asset))->assertRedirect();
        $newToken = $asset->refresh()->public_token;
        $this->assertNotSame($oldToken, $newToken);

        auth()->logout();
        $this->get(route('public.it-assets.show', $oldToken))->assertNotFound();
        $this->get(route('public.it-assets.show', $newToken))->assertOk();
    }

    public function test_read_only_user_cannot_manage_asset_qr_code(): void
    {
        [, $asset, $branch] = $this->editorAndAsset();
        $viewer = User::factory()->create([
            'role' => 'viewer',
            'permissions' => AccessMatrix::permissionsForRole('viewer'),
        ]);
        $viewer->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);

        $this->actingAs($viewer)->get(route('it-assets.qr-code.show', $asset))->assertForbidden();
        $this->post(route('it-assets.qr-code.store', $asset))->assertForbidden();
    }

    private function editorAndAsset(): array
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $user = User::factory()->create();
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $category = Category::create(['code' => 'QR-TEST', 'name' => 'Laptop', 'type' => 'asset', 'active' => true]);
        $asset = Asset::withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'asset_tag_no' => 'KL-QR-001',
            'description' => 'Test Laptop',
            'category_id' => $category->id,
            'serial_no' => 'SN-PRIVATE-001',
            'current_status' => 'available',
            'current_condition' => 'good',
            'acquisition_cost' => 4999,
            'remarks' => 'Internal confidential note',
            'active' => true,
        ]);

        return [$user, $asset, $branch];
    }
}
