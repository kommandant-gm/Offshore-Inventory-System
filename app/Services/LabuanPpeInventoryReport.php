<?php

namespace App\Services;

use App\Models\MiriConstructionItem;

class LabuanPpeInventoryReport extends LabuanConsumableInventoryReport
{
    protected function matches(MiriConstructionItem $item): bool
    {
        return strtoupper(trim($item->category ?? '')) === 'PPE';
    }

    protected function reportLabel(): string { return 'Labuan Warehouse PPE'; }

    protected function scopeNote(): string
    {
        return 'Includes only Labuan/LBN construction records in the PPE category, regardless of Section 1.';
    }

    public function generate(int $branch, string $month): array
    {
        $report = parent::generate($branch, $month);
        $report['notes'][3] = 'IN includes receipts, backloads and transfers in. OUT includes issues, transfers out and supplier returns. Other Misc. is the signed total of corrections and write-offs. Opening verification is not a receipt.';
        $report['notes'][5] = 'This template has no price columns. Dates refer to stock posting dates in Malaysia time; multiple movement dates and locations are listed together.';
        $report['notes'][6] = 'Blank means unavailable. Zero means recorded zero. The supplied PPE STORE form has no grand-total row; different units are not added together. Sign-off fields are left blank for review.';
        return $report;
    }
}
