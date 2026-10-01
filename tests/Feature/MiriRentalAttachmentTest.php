<?php

namespace Tests\Feature;

use App\Models\{Branch, MiriRentalItem, User};
use App\Support\AccessMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MiriRentalAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('rental');
    }

    private function staff(bool $edit = true): User
    {
        $user = User::factory()->create(['role' => 'miri', 'directory_active' => true,
            'permissions' => $edit ? AccessMatrix::permissionsForRole('miri') : array_fill_keys(array_keys(AccessMatrix::modules()), 'read')]);
        $user->branches()->attach(Branch::where('code', 'MIRI')->value('id'), ['access_level' => $edit ? 'edit' : 'read', 'is_default' => true]);
        $this->actingAs($user);
        return $user;
    }

    private function pdf(string $name = 'document.pdf'): UploadedFile
    {
        $file = UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        return new UploadedFile($file->getPathname(), $name, null, null, true);
    }

    public function test_create_stores_all_three_pdfs_and_exposes_only_safe_metadata(): void
    {
        $this->staff();
        $uploads = [];
        foreach (MiriRentalItem::ATTACHMENTS as $slot => $label) $uploads[$slot] = $this->pdf($slot.'.pdf');
        $this->post(route('miri-rental.store'), ['description' => 'Rental compressor', 'status' => 'On Hire', 'uploads' => $uploads])
            ->assertRedirect()->assertSessionHasNoErrors();
        $item = MiriRentalItem::firstOrFail();
        $this->assertCount(3, $item->attachments);
        foreach ($item->attachments as $slot => $file) {
            Storage::disk('rental')->assertExists($file['path']);
            $url = route('miri-rental.attachment', ['rental' => $item->id, 'slot' => $slot]);
            $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
            $this->get($url.'?download=1')->assertOk()->assertDownload('rental-'.$item->id.'-'.$slot.'.pdf');
        }
        foreach (['miri-rental.show', 'miri-rental.edit'] as $route) {
            $this->get(route($route, $item))->assertOk()->assertInertia(fn (Assert $p) => $p
                ->has('rental.attachments', 3)->where('rental.attachments.lcn.name', 'lcn.pdf')->missing('rental.attachments.lcn.path'));
        }
    }

    public function test_multipart_edit_adds_and_replaces_pdfs_without_losing_other_slots(): void
    {
        $this->staff();
        $item = MiriRentalItem::create(['status' => 'On Hire']);
        $url = route('miri-rental.update', $item);
        $this->post($url, ['_method' => 'patch', 'status' => 'Issued', 'uploads' => ['lcn' => $this->pdf('first.pdf'), 'bcn' => $this->pdf()]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $original = $item->fresh()->attachments;
        $this->post($url, ['_method' => 'patch', 'status' => 'Issued', 'uploads' => ['lcn' => null, 'bcn' => null, 'offhire' => null]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($original, $item->fresh()->attachments);
        $this->post($url, ['_method' => 'patch', 'status' => 'Off Hire', 'uploads' => ['lcn' => $this->pdf('replacement.pdf'), 'offhire' => $this->pdf()]])
            ->assertRedirect()->assertSessionHasNoErrors();
        $updated = $item->fresh()->attachments;
        $this->assertSame($original['bcn'], $updated['bcn']);
        $this->assertSame('replacement.pdf', $updated['lcn']['name']);
        $this->assertCount(3, $updated);
        Storage::disk('rental')->assertMissing($original['lcn']['path']);
        foreach ($updated as $file) Storage::disk('rental')->assertExists($file['path']);
    }

    public function test_invalid_and_oversized_uploads_are_rejected_without_changing_the_record(): void
    {
        $this->staff();
        $item = MiriRentalItem::create(['status' => 'On Hire']);
        $bad = UploadedFile::fake()->createWithContent('fake.pdf', '<html>not a PDF</html>');
        foreach ([new UploadedFile($bad->getPathname(), 'fake.pdf', null, null, true), UploadedFile::fake()->create('large.pdf', 5121, 'application/pdf')] as $file) {
            $this->post(route('miri-rental.update', $item), ['_method' => 'patch', 'status' => 'Off Hire', 'uploads' => ['lcn' => $file]])
                ->assertSessionHasErrors('uploads.lcn');
        }
        $this->post(route('miri-rental.update', $item), ['_method' => 'patch', 'status' => 'Off Hire', 'uploads' => ['unknown' => $this->pdf()]])
            ->assertSessionHasErrors('uploads');
        $this->assertSame('On Hire', $item->fresh()->status);
        $this->assertEmpty(Storage::disk('rental')->allFiles());
    }

    public function test_readers_can_open_pdfs_but_cannot_upload_and_other_branches_are_blocked(): void
    {
        $this->staff();
        $this->post(route('miri-rental.store'), ['status' => 'On Hire', 'uploads' => ['lcn' => $this->pdf()]])->assertSessionHasNoErrors();
        $item = MiriRentalItem::firstOrFail();
        $this->staff(false);
        $this->get(route('miri-rental.attachment', ['rental' => $item->id, 'slot' => 'lcn']))->assertOk();
        $this->post(route('miri-rental.update', $item), ['_method' => 'patch', 'status' => 'On Hire', 'uploads' => ['lcn' => $this->pdf()]])->assertForbidden();
        foreach (['bcn', 'unknown'] as $slot) $this->get(route('miri-rental.attachment', ['rental' => $item->id, 'slot' => $slot]))->assertNotFound();
        $other = MiriRentalItem::create(['branch_id' => Branch::where('code', 'KL-IT')->value('id')]);
        $other->attachments = $item->attachments;
        $other->save();
        $this->get(route('miri-rental.attachment', ['rental' => $other->id, 'slot' => 'lcn']))->assertNotFound();
        auth()->user()->update(['permissions' => array_fill_keys(array_keys(AccessMatrix::modules()), 'none')]);
        $this->get(route('miri-rental.attachment', ['rental' => $item->id, 'slot' => 'lcn']))->assertForbidden();
    }
}
