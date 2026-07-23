<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ItLicense;
use App\Models\User;
use App\Services\ItLicenseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ItLicenseImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_preview_and_import_licence_spreadsheet_rows(): void
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $user = User::factory()->create();
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);

        ItLicense::create([
            'license_code' => 'LIC-000005',
            'software_name' => 'Existing licence',
            'license_type' => 'perpetual',
            'seats_total' => 1,
            'seats_assigned' => 0,
            'active' => true,
        ]);

        $csv = implode("\n", [
            'No,User ID,Checked Out To,License,License Type,Has License,License Key (if available),Category,Expiry Date',
            '1,EMP004,NUR WAHIDAH,MICROSOFT OFFICE 365,Subscription,Yes,,OFFICE PRODUCTIVITY APPLICATION,2026-12-31',
            '2,,,ADOBE ACROBAT PRO 2015,Perpetual,No,1118-TEST-KEY,PDF EDITOR,',
        ]);

        $this->actingAs($user)
            ->post(route('it-licenses.import.preview'), [
                'file' => UploadedFile::fake()->createWithContent('software-licences.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('it_license_import_report.ready', 2)
            ->assertSessionHas('it_license_import_report.assigned', 1)
            ->assertSessionHas('it_license_import_report.available', 1);

        $this->actingAs($user)
            ->get(route('it-licenses.import.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ItLicenses/Import')
                ->where('report.ready', 2)
                ->where('report.rejected_count', 0));

        $this->actingAs($user)
            ->post(route('it-licenses.import.store'))
            ->assertRedirect(route('it-licenses.index'))
            ->assertSessionHas('success', 'Imported 2 IT licences; 0 rows rejected.');

        $assigned = ItLicense::where('license_code', 'LIC-000006')->firstOrFail();
        $this->assertSame('MICROSOFT OFFICE 365', $assigned->software_name);
        $this->assertSame('Microsoft', $assigned->vendor);
        $this->assertSame('subscription', $assigned->license_type);
        $this->assertSame(1, $assigned->seats_assigned);
        $this->assertSame('NUR WAHIDAH', $assigned->assigned_to);
        $this->assertSame('2026-12-31', $assigned->expiry_date?->format('Y-m-d'));
        $this->assertStringContainsString('User ID: EMP004', $assigned->remarks);

        $available = ItLicense::where('license_code', 'LIC-000007')->firstOrFail();
        $this->assertSame(0, $available->seats_assigned);
        $this->assertNull($available->assigned_to);
        $this->assertSame('1118-TEST-KEY', $available->license_key);
        $this->assertNotSame(
            '1118-TEST-KEY',
            DB::table('it_licenses')->where('id', $available->id)->value('license_key')
        );
    }

    public function test_xlsx_excel_serial_expiry_date_is_converted(): void
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $user = User::factory()->create();
        $user->branches()->attach($branch, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);

        $path = tempnam(sys_get_temp_dir(), 'licences-').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);

        $strings = [
            'No',
            'User ID',
            'Checked Out To',
            'License',
            'License Type',
            'Has License',
            'License Key (if available)',
            'Category',
            'Expiry Date',
            '1',
            'EMP013',
            'TEST USER',
            'AUTOCAD LT',
            'Subscription',
            'Yes',
            '',
            'TECHNICAL DRAWINGS',
        ];
        $shared = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .implode('', array_map(fn ($value) => '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>', $strings))
            .'</sst>';
        $headerCells = implode('', array_map(
            fn ($index) => '<c r="'.chr(65 + $index).'1" t="s"><v>'.$index.'</v></c>',
            range(0, 8)
        ));
        $dataCells = implode('', array_map(
            fn ($index) => '<c r="'.chr(65 + $index - 9).'2" t="s"><v>'.$index.'</v></c>',
            range(9, 16)
        )).'<c r="I2"><v>46344</v></c>';
        $sheet = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1">'.$headerCells.'</row><row r="2">'.$dataCells.'</row></sheetData></worksheet>';

        $zip->addFromString('xl/sharedStrings.xml', $shared);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        $report = app(ItLicenseImportService::class)->analyse($path);
        @unlink($path);

        $this->assertSame(1, $report['ready'], json_encode($report));
        $this->assertSame('2026-11-18', $report['valid'][0]['data']['expiry_date']);
    }

    public function test_read_only_user_cannot_access_licence_import(): void
    {
        $branch = Branch::where('code', 'KL-IT')->firstOrFail();
        $user = User::factory()->create();
        $user->branches()->attach($branch, ['access_level' => 'read', 'is_default' => true]);

        $this->actingAs($user)
            ->get(route('it-licenses.import.create'))
            ->assertForbidden();
    }
}
