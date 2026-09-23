<?php

namespace App\Services;

use App\Models\MiriConstructionItem;

class PpeInventoryReport extends ConstructionInventoryReport
{
    protected function matches(MiriConstructionItem $item): bool
    {
        return strtoupper(trim($item->category ?? '')) === 'PPE';
    }

    protected function scopeNote(): string
    {
        return 'Includes Bintulu construction records in the PPE category, regardless of Section 1.';
    }

    protected function reportLabel(): string
    {
        return 'PPE';
    }
}
