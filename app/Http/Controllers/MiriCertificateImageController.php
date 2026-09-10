<?php

namespace App\Http\Controllers;

use App\Models\MajorEquipment;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MiriCertificateImageController extends Controller
{
    public function show(Request $request, MajorEquipment $equipment, int $certificate)
    {
        abort_unless(app(BranchContext::class)->branch($request->user())?->code === 'MIRI', 404);
        abort_unless($request->user()?->canRead('assets'), 403);
        $record = $equipment->certificates()->whereKey($certificate)->firstOrFail();
        abort_unless($record->image_path && Storage::disk('certificates')->exists($record->image_path), 404);
        $extension = match ($record->image_mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf', default => abort(404) };
        return Storage::disk('certificates')->response($record->image_path, 'certificate-'.$record->id.'.'.$extension, [
            'Content-Type' => $record->image_mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $request->boolean('download') ? 'attachment' : 'inline');
    }
}
