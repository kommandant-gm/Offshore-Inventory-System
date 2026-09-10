<?php

namespace App\Services;

use App\Models\MajorEquipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MiriCertificateService
{
    public function save(MajorEquipment $equipment, array $data, array $certificates, array $removedIds, int $userId): MajorEquipment
    {
        $newFiles = [];
        $obsoleteFiles = [];
        try {
            DB::transaction(function () use ($equipment, $data, $certificates, $removedIds, $userId, &$newFiles, &$obsoleteFiles) {
                if ($equipment->exists) MajorEquipment::query()->whereKey($equipment->id)->lockForUpdate()->firstOrFail();
                $equipment->fill($data)->save();
                foreach ($removedIds as $id) {
                    if (in_array((int) $id, array_map('intval', array_column($certificates, 'id')), true)) {
                        throw ValidationException::withMessages(['certificates' => 'A certificate cannot be saved and removed at the same time.']);
                    }
                    $certificate = $equipment->certificates()->whereKey($id)->firstOrFail();
                    if ($certificate->image_path) $obsoleteFiles[] = $certificate->image_path;
                    $certificate->delete();
                }
                foreach ($certificates as $index => $row) {
                    $certificate = ! empty($row['id']) ? $equipment->certificates()->whereKey($row['id'])->firstOrFail() : $equipment->certificates()->make(['branch_id' => $equipment->branch_id]);
                    $certificate->fill(collect($row)->only(['certificate_type', 'certificate_no', 'issue_date', 'expiry_date', 'raw_value'])->all());
                    $certificate->save();
                    $file = $row['image'] ?? null;
                    if ($file && ($row['remove_image'] ?? false)) throw ValidationException::withMessages(["certificates.{$index}.image" => 'Choose either a replacement image or removal.']);
                    if ($file || ($row['remove_image'] ?? false)) {
                        if ($certificate->image_path) $obsoleteFiles[] = $certificate->image_path;
                        $metadata = ['image_path' => null, 'image_name' => null, 'image_mime' => null, 'image_size' => null, 'image_uploaded_by' => null, 'image_uploaded_at' => null];
                        if ($file) {
                            $path = $file->store($equipment->branch_id.'/'.$certificate->id, 'certificates');
                            if (! $path) throw new \RuntimeException('Unable to store certificate image.');
                            $newFiles[] = $path;
                            $metadata = ['image_path' => $path, 'image_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 255), 'image_mime' => $file->getMimeType(), 'image_size' => $file->getSize(), 'image_uploaded_by' => $userId, 'image_uploaded_at' => now()];
                        }
                        $certificate->forceFill($metadata)->save();
                    }
                }
            });
        } catch (\Throwable $error) {
            $this->cleanup($newFiles);
            throw $error;
        }
        // Old files are removed only after the database commit succeeds.
        $this->cleanup($obsoleteFiles);
        return $equipment;
    }

    private function cleanup(array $paths): void
    {
        foreach (array_unique($paths) as $path) {
            try {
                if (! Storage::disk('certificates')->delete($path)) Log::warning('Certificate image cleanup failed', ['path' => $path]);
            } catch (\Throwable $error) {
                Log::warning('Certificate image cleanup failed', ['path' => $path, 'error' => $error->getMessage()]);
            }
        }
    }
}
