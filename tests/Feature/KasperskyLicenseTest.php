<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\KasperskyImport;
use App\Models\User;
use App\Services\KasperskyImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class KasperskyLicenseTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $access = 'edit'): void
    {
        $user = User::factory()->create();
        $user->branches()->attach(Branch::where('code', 'KL-IT')->firstOrFail(), ['access_level' => $access, 'is_default' => true]);
        $this->actingAs($user);
    }

    private function upload(string $csv)
    {
        return $this->post(route('kaspersky-licenses.import'), ['file' => UploadedFile::fake()->createWithContent('monthly.csv', $csv)]);
    }

    public function test_monthly_upload_replaces_current_counts_and_preserves_history(): void
    {
        $this->signIn();
        $csv = "No,Licence,Device\n1,Kaspersky Endpoint,\"PC-1\tKL\tinactive\tignored\tignored\"\n2,Kaspersky Endpoint,\n";
        $this->upload($csv)->assertSessionHasNoErrors()->assertRedirect(route('kaspersky-licenses.index'));
        $this->upload($csv)->assertSessionHasNoErrors();
        $this->assertSame(2, KasperskyImport::count());
        $this->get(route('kaspersky-licenses.index'))->assertInertia(fn (Assert $page) => $page
            ->component('ItLicenses/Kaspersky')->where('overview.total', 2)->where('overview.assigned', 1)
            ->where('overview.available', 1)->where('overview.rows.0.device', 'PC-1')->has('history', 2));
        $this->get(route('it-assets.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('kasperskyOverview.total', 2)->where('kasperskyOverview.assigned', 1));
        $this->upload("No,Licence,Device\n1,Kaspersky Endpoint,\n")->assertSessionHasNoErrors();
        $this->assertSame(1, KasperskyImport::overview()['total']);
        $this->assertSame(0, KasperskyImport::overview()['assigned']);
    }

    public function test_invalid_upload_keeps_previous_snapshot(): void
    {
        $this->signIn();
        $this->upload("No,Licence,Device\n1,Kaspersky Endpoint,PC-1")->assertSessionHasNoErrors();
        foreach (["No,Licence,Device\n", "Wrong,Headers\n1,2", "No,Licence,Device\n1,Kaspersky Endpoint,PC-2\n1,Kaspersky Endpoint,PC-3", "No,Licence,Device\n1,Kaspersky Endpoint,PC-2\n2,Kaspersky Endpoint,pc-2"] as $csv) {
            $this->upload($csv)->assertSessionHasErrors('file');
        }
        $this->assertSame(1, KasperskyImport::count());
        $this->assertSame('PC-1', KasperskyImport::overview()['rows'][0]['device']);
    }

    public function test_readers_cannot_upload_and_other_branch_data_is_hidden(): void
    {
        $this->signIn('read');
        $this->get(route('kaspersky-licenses.index'))->assertOk();
        $this->upload("No,Licence,Device\n1,Kaspersky Endpoint,PC-1")->assertForbidden();
        $snapshot = new KasperskyImport(['filename' => 'other.csv', 'rows' => []]);
        $snapshot->branch_id = Branch::where('code', 'MIRI')->firstOrFail()->id;
        $snapshot->save();
        $this->get(route('kaspersky-licenses.index'))->assertInertia(fn (Assert $page) => $page->where('overview.total', 0)->has('history', 0));
    }

    public function test_excel_same_format_is_supported(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kaspersky-');
        unlink($path);
        $path .= '.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([['No', 'Licence', 'Device'], ['1', 'Kaspersky Endpoint', "PC-1\tKL\tactive\t19/5/2027\t18/5/2026"], ['2', 'Kaspersky Endpoint', '']] as $i => $values) {
            $xml .= '<row r="'.($i + 1).'">';
            foreach ($values as $column => $value) {
                $xml .= '<c r="'.chr(65 + $column).($i + 1).'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
        $zip->close();
        try {
            $rows = app(KasperskyImportService::class)->parse($path);
            $this->assertCount(2, $rows);
            $this->assertSame('PC-1', $rows[0]['device']);
            $this->assertSame('', $rows[1]['device']);
        } finally {
            unlink($path);
        }
    }
}
