<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(string $key, string $prefix, int $padding = 3, int $minimumNext = 1): string
    {
        $branchId = app(BranchContext::class)->id();
        $scopedKey = $branchId ? "{$branchId}:{$key}" : $key;
        $scopedPrefix = $branchId ? app(BranchContext::class)->branch()?->code.'-'.$prefix : $prefix;
        return DB::transaction(function () use ($scopedKey, $branchId, $scopedPrefix, $padding, $minimumNext) {
            DB::table('document_sequences')->insertOrIgnore([
                'key' => $scopedKey,
                'branch_id' => $branchId,
                'next_number' => $minimumNext,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('document_sequences')
                ->where('key', $scopedKey)
                ->lockForUpdate()
                ->first();

            $number = (int) $sequence->next_number;

            DB::table('document_sequences')->where('key', $scopedKey)->update([
                'next_number' => $number + 1,
                'updated_at' => now(),
            ]);

            return $scopedPrefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
        });
    }

    public function preview(string $key, string $prefix, int $padding = 3): string
    {
        $branchId = app(BranchContext::class)->id();
        $scopedKey = $branchId ? "{$branchId}:{$key}" : $key;
        $prefix = $branchId ? app(BranchContext::class)->branch()?->code.'-'.$prefix : $prefix;
        $number = (int) (DB::table('document_sequences')->where('key', $scopedKey)->value('next_number') ?? 1);

        return $prefix.str_pad((string) $number, $padding, '0', STR_PAD_LEFT);
    }
}
