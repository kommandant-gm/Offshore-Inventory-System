<?php

namespace App\Services;

use App\Models\MiriConstructionItem;

class CidbInventoryReport extends ConstructionInventoryReport
{
    protected function matches(MiriConstructionItem $item): bool
    {
        return strtoupper(trim($item->section_1 ?? '')) === 'WQT TRAINING';
    }

    protected function scopeNote(): string
    {
        return 'Includes Bintulu construction records with Section 1 set to WQT TRAINING, regardless of category.';
    }

    protected function reportLabel(): string
    {
        return 'CIDB';
    }
}
