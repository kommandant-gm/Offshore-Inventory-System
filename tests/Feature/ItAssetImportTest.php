<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Location;
use App\Models\User;
use App\Services\ItAssetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItAssetImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_csv_dry_run_and_import_create_deployed_asset_assignment(): void
    {
        $kl=Branch::where('code','KL-IT')->firstOrFail();
        $user=User::factory()->create(); $user->branches()->attach($kl,['access_level'=>'edit','is_default'=>true]);
        $this->actingAs($user);
        Location::create(['branch_id'=>$kl->id,'code'=>'KL','name'=>'KL Office','type'=>'yard','active'=>true]);
        $path=tempnam(sys_get_temp_dir(),'kl-assets-').'.csv';
        file_put_contents($path, "No,Asset Tag,Serial,Model,Category,Status,Checked Out To,Location,Operating System,Year of Purchase,Asset's Age,Department\n1,DESBKL/LT/2022/001,1X8TNL3,DELL LATITUDE 3420,Laptop,ACTIVE,TEST USER,KL,Windows 11,2022,4,PROJECT\n");
        $service=app(ItAssetImportService::class); $preview=$service->analyse($path);
        $this->assertSame(1,$preview['ready']); $this->assertSame(0,$preview['rejected_count']);
        $service->import($path,$user->id); @unlink($path);
        $asset=Asset::with('currentAssignment')->firstOrFail();
        $this->assertSame('DESBKL/LT/2022/001',$asset->asset_tag_no);
        $this->assertSame('deployed',$asset->current_status->value);
        $this->assertSame('TEST USER',$asset->currentAssignment->assigned_to_name);
        $this->assertSame('PROJECT',$asset->currentAssignment->department);
    }
}
