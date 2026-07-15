<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\Location;
use App\Models\User;
use App\Services\ItAssetImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;
use Inertia\Testing\AssertableInertia as Assert;

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
        $this->get(route('it-assets.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('assets.data.0.asset_tag_no','DESBKL/LT/2022/001'));
    }

    public function test_kl_branch_chatbot_queries_it_assets_instead_of_miri_stock(): void
    {
        $kl=Branch::where('code','KL-IT')->firstOrFail();
        $user=User::factory()->create(); $user->branches()->attach($kl,['access_level'=>'edit','is_default'=>true]);
        $this->actingAs($user);
        $category=\App\Models\Category::create(['code'=>'LAPTOP','name'=>'Laptop','type'=>'asset','active'=>true]);
        Asset::create(['branch_id'=>$kl->id,'asset_tag_no'=>'DESBKL/LT/2022/001','description'=>'Dell laptop','category_id'=>$category->id,'model'=>'DELL LATITUDE 3420','serial_no'=>'1X8TNL3','operating_system'=>'Windows 11','purchase_year'=>2022,'current_status'=>'available','current_condition'=>'good','active'=>true]);

        $this->postJson(route('assistant.query'),['message'=>'Where is DESBKL/LT/2022/001?'])
            ->assertOk()->assertJsonPath('intent','asset_summary')->assertJsonPath('item.item_code','DESBKL/LT/2022/001')
            ->assertJsonPath('answer',fn($answer)=>str_contains($answer,'KL IT:'));
    }

    public function test_xlsx_shared_string_headers_are_detected(): void
    {
        $path=tempnam(sys_get_temp_dir(),'kl-xlsx-').'.xlsx'; $zip=new ZipArchive(); $zip->open($path,ZipArchive::CREATE);
        $strings=['No','Asset Tag','Serial','Model','Category','Status','Checked Out To','Location','Operating System','Year of Purchase',"Asset's Age",'Department','1','DESBKL/LT/2022/001','1X8TNL3','DELL LATITUDE 3420','Laptop','ACTIVE','TEST USER','KL','Windows 11','2022','4','PROJECT'];
        $shared='<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('',array_map(fn($s)=>'<si><t>'.htmlspecialchars($s,ENT_XML1).'</t></si>',$strings)).'</sst>';
        $cells=fn($start,$end,$row)=>implode('',array_map(function($i)use($row,$start){$column=chr(65+($i-$start));return '<c r="'.$column.$row.'" t="s"><v>'.$i.'</v></c>';},range($start,$end)));
        $sheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1">'.$cells(0,11,1).'</row><row r="2">'.$cells(12,23,2).'</row></sheetData></worksheet>';
        $zip->addFromString('xl/sharedStrings.xml',$shared); $zip->addFromString('xl/worksheets/sheet1.xml',$sheet); $zip->close();
        $report=app(ItAssetImportService::class)->analyse($path); @unlink($path);
        $this->assertSame(1,$report['ready'],json_encode($report)); $this->assertSame(0,$report['rejected_count']);
    }
}
