<?php

namespace App\Services;

use App\Models\MiriConstructionItem;

class ConsumableInventoryReport extends ConstructionInventoryReport
{
    protected function matches(MiriConstructionItem $item): bool
    {
        return strtoupper(trim($item->category ?? '')) === 'CONSUMABLE';
    }

    protected function scopeNote(): string
    {
        return 'Includes Bintulu construction records in the Consumable category, regardless of Section 1.';
    }

    protected function reportLabel(): string
    {
        return 'Consumable';
    }
}
