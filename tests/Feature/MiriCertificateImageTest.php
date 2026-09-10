<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\MajorEquipment;
use App\Models\MajorEquipmentCertificate;
use App\Models\User;
use App\Services\MiriCertificateService;
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MiriCertificateImageTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        Storage::fake('certificates');
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true, 'permissions' => AccessMatrix::permissionsForRole('miri')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->firstOrFail(), ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($user);
        return $user;
    }

    private function item(string $type = 'cargo'): MajorEquipment
    {
        return MajorEquipment::create(['category' => 'MAJOR EQUIPMENT', 'inventory_type' => $type, 'tag_no' => 'TEST']);
    }

    private function data(array $certificates = [], array $extra = []): array
    {
        return ['_method' => 'patch', 'category' => 'MAJOR EQUIPMENT', 'inventory_type' => 'cargo', 'certificates' => $certificates, ...$extra];
    }

    public function test_imported_certificate_can_receive_image_and_keep_id_and_image_on_later_edits(): void
    {
        $user = $this->staff();
        $item = $this->item();
        $cert = $item->certificates()->create(['branch_id' => $item->branch_id, 'certificate_type' => 'PADEYE MPI', 'raw_value' => 'Original import']);
        $this->post(route('major-equipment.update', $item), $this->data([['id' => $cert->id, 'certificate_type' => 'PADEYE MPI', 'image' => UploadedFile::fake()->image('certificate.png')]]))->assertSessionHasNoErrors()->assertRedirect();
        $cert->refresh(); $path = $cert->image_path;
        Storage::disk('certificates')->assertExists($path);
        $this->assertSame($user->id, $cert->image_uploaded_by);
        $this->assertNotNull($cert->image_uploaded_at);
        $this->assertSame('certificate.png', $cert->image_name);
        $this->assertArrayNotHasKey('image_path', $cert->toArray());
        $this->post(route('major-equipment.update', $item), $this->data([['id' => $cert->id, 'certificate_type' => 'PADEYE MPI', 'certificate_no' => 'UPDATED']]))->assertSessionHasNoErrors();
        $this->assertSame($path, $cert->fresh()->image_path);
        $this->assertSame('UPDATED', $cert->fresh()->certificate_no);
        $this->assertSame(1, $item->certificates()->count());
        $url = route('major-equipment.certificates.image', [$item, $cert->id]);
        $this->get($url)->assertOk()->assertHeader('content-type', 'image/png')->assertHeader('x-content-type-options', 'nosniff');
        $response = $this->get($url.'?download=1')->assertOk();
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->post(route('major-equipment.update', $item), $this->data([], ['description' => 'Unrelated edit']))->assertSessionHasNoErrors();
        $this->assertSame($path, $cert->fresh()->image_path);
    }

    public function test_replace_remove_image_and_remove_certificate_cleanup_files(): void
    {
        $this->staff(); $item = $this->item();
        $row = ['certificate_type' => 'PADEYE MPI', 'image' => UploadedFile::fake()->image('first.jpg')];
        $this->post(route('major-equipment.update', $item), $this->data([$row]))->assertSessionHasNoErrors();
        $cert = $item->certificates()->firstOrFail(); $old = $cert->image_path;
        $row['id'] = $cert->id; $row['image'] = UploadedFile::fake()->image('replacement.png');
        $this->post(route('major-equipment.update', $item), $this->data([$row]))->assertSessionHasNoErrors();
        Storage::disk('certificates')->assertMissing($old);
        $path = $cert->fresh()->image_path; Storage::disk('certificates')->assertExists($path);
        $this->post(route('major-equipment.update', $item), $this->data([['id' => $cert->id, 'certificate_type' => 'PADEYE MPI', 'remove_image' => true]]))->assertSessionHasNoErrors();
        Storage::disk('certificates')->assertMissing($path);
        $this->assertNull($cert->fresh()->image_path);
        $row['image'] = UploadedFile::fake()->image('third.png');
        $this->post(route('major-equipment.update', $item), $this->data([$row]))->assertSessionHasNoErrors();
        $path = $cert->fresh()->image_path;
        $this->post(route('major-equipment.update', $item), $this->data([], ['removed_certificate_ids' => [$cert->id]]))->assertSessionHasNoErrors();
        $this->assertNull($cert->fresh()); Storage::disk('certificates')->assertMissing($path);
    }

    public function test_images_and_certificate_ids_are_protected_by_item_and_branch_permissions(): void
    {
        $user = $this->staff(); $item = $this->item(); $other = $this->item();
        $this->post(route('major-equipment.update', $item), $this->data([['certificate_type' => 'MPI', 'image' => UploadedFile::fake()->image('private.png')]]))->assertSessionHasNoErrors();
        $cert = $item->certificates()->firstOrFail();
        $this->post(route('major-equipment.update', $other), $this->data([['id' => $cert->id, 'certificate_type' => 'MPI']]))->assertSessionHasErrors('certificates.0.id');
        $this->post(route('major-equipment.update', $other), $this->data([], ['removed_certificate_ids' => [$cert->id]]))->assertSessionHasErrors('removed_certificate_ids.0');
        $this->get(route('major-equipment.certificates.image', [$other, $cert->id]))->assertNotFound();
        $url = route('major-equipment.certificates.image', [$item, $cert->id]);
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        $this->get($url)->assertForbidden();
        $klUser = User::factory()->create(['role' => 'it', 'directory_active' => true]);
        $kl = Branch::where('code', 'KL-IT')->firstOrFail();
        $klUser->branches()->attach($kl, ['access_level' => 'edit', 'is_default' => true]);
        $this->actingAs($klUser)->withSession(['branch_id' => $kl->id])->get($url)->assertNotFound();
        auth()->logout(); $this->get($url)->assertRedirect(route('login'));
    }

    public function test_rejects_non_images_and_oversized_uploads_and_supports_machinery_registration(): void
    {
        $this->staff(); $item = $this->item();
        foreach ([UploadedFile::fake()->createWithContent('bad.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'), UploadedFile::fake()->image('big.jpg')->size(5121)] as $file) {
            $this->post(route('major-equipment.update', $item), $this->data([['certificate_type' => 'MPI', 'image' => $file]]))->assertSessionHasErrors('certificates.0.image');
        }
        $this->assertSame([], Storage::disk('certificates')->allFiles());
        $data = $this->data([['certificate_type' => 'SERVICE RELIEF VALVE', 'image' => UploadedFile::fake()->image('new.jpg')]], ['inventory_type' => 'machinery']);
        unset($data['_method']);
        $this->post(route('major-equipment.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, MajorEquipmentCertificate::whereNotNull('image_path')->count());
    }

    public function test_database_failure_cleans_new_files_and_preserves_original(): void
    {
        $user = $this->staff(); $item = $this->item();
        $service = app(MiriCertificateService::class);
        $service->save($item, [], [['certificate_type' => 'MPI', 'image' => UploadedFile::fake()->image('original.jpg')]], [], $user->id);
        $cert = $item->certificates()->firstOrFail(); $original = $cert->image_path;
        MajorEquipmentCertificate::updating(function ($record) { if ($record->isDirty('image_path')) throw new \RuntimeException('Simulated database failure'); });
        try {
            $service->save($item, [], [['id' => $cert->id, 'certificate_type' => 'MPI', 'image' => UploadedFile::fake()->image('replacement.jpg')]], [], $user->id);
            $this->fail('Expected save failure');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated database failure', $error->getMessage());
        } finally {
            MajorEquipmentCertificate::flushEventListeners();
        }
        $this->assertSame($original, $cert->fresh()->image_path);
        $this->assertSame([$original], Storage::disk('certificates')->allFiles());
    }

    public function test_pdf_can_replace_image_and_is_served_privately_as_pdf(): void
    {
        $user = $this->staff(); $item = $this->item();
        $service = app(MiriCertificateService::class);
        $service->save($item, [], [['certificate_type' => 'MPI', 'image' => UploadedFile::fake()->image('original.jpg')]], [], $user->id);
        $cert = $item->certificates()->firstOrFail(); $old = $cert->image_path;
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h1>Certificate</h1>')->output();
        $pdfSource = UploadedFile::fake()->createWithContent('certificate.pdf', $pdf);
        $file = new UploadedFile($pdfSource->getRealPath(), 'certificate.pdf', 'application/pdf', null, true);
        $this->post(route('major-equipment.update', $item), $this->data([['id' => $cert->id, 'certificate_type' => 'MPI', 'image' => $file]]))->assertSessionHasNoErrors()->assertRedirect();
        $cert->refresh();
        $this->assertSame('application/pdf', $cert->image_mime);
        Storage::disk('certificates')->assertMissing($old);
        Storage::disk('certificates')->assertExists($cert->image_path);
        $url = route('major-equipment.certificates.image', [$item, $cert->id]);
        $this->get($url)->assertOk()->assertHeader('content-type', 'application/pdf')->assertHeader('x-content-type-options', 'nosniff');
        $response = $this->get($url.'?download=1')->assertOk();
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
        $fakePdf = UploadedFile::fake()->createWithContent('fake.pdf', '<html>Not a PDF</html>');
        $spoofed = new UploadedFile($fakePdf->getRealPath(), 'fake.pdf', 'application/pdf', null, true);
        $this->post(route('major-equipment.update', $item), $this->data([['id' => $cert->id, 'certificate_type' => 'MPI', 'image' => $spoofed]]))->assertSessionHasErrors('certificates.0.image');
        $user->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        $this->get($url)->assertForbidden();
    }
}
