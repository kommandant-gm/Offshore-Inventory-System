<?php

namespace App\Actions\Cogs;

use App\Models\Cog;
use App\Models\InventoryTransaction;

class CreateCogFromMovementAction
{
    public function __construct(
        private readonly CreateCogAction $createCogAction,
    ) {
    }

    public function persist(InventoryTransaction $transaction, array $cogData, ?int $userId = null): Cog
    {
        $transaction->loadMissing('item', 'sourceLocation', 'location');

        $item = $transaction->item;
        $exLocation = $cogData['ex_location']
            ?? $transaction->sourceLocation?->name
            ?? $transaction->location?->name;

        $cog = $this->createCogAction->persist([
            'document_date' => $cogData['document_date'] ?? $transaction->transaction_date->format('Y-m-d'),
            'consignee' => $cogData['consignee'] ?? null,
            'shipper' => $cogData['shipper'] ?? 'Dayang Enterprise Sdn. Bhd.',
            'destination' => $cogData['destination'] ?? null,
            'vessel' => $cogData['vessel'] ?? null,
            'department' => $cogData['department'] ?? null,
            'receiver_name' => $cogData['receiver_name'] ?? null,
            'receiver_email' => $cogData['receiver_email'] ?? null,
            'cc_emails' => $cogData['cc_emails'] ?? [],
            'receiver_designation' => $cogData['receiver_designation'] ?? null,
            'issued_by_name' => $cogData['issued_by_name'] ?? null,
            'issued_by_designation' => $cogData['issued_by_designation'] ?? null,
            'checked_by_name' => $cogData['checked_by_name'] ?? null,
            'checked_by_designation' => $cogData['checked_by_designation'] ?? null,
            'remarks' => $cogData['remarks'] ?? $transaction->remarks,
            'items' => [[
                'inventory_item_id' => $item?->id,
                'qty' => $transaction->quantity,
                'unit' => $item?->uom,
                'part_no' => $item?->item_code,
                'full_description' => $item?->description ?? 'Stock item movement item',
                'measurement_cu_metre' => $cogData['measurement_cu_metre'] ?? null,
                'gross_weight_kg' => $cogData['gross_weight_kg'] ?? null,
                'po_no' => $cogData['po_no'] ?? $transaction->po_no,
                'ex_location' => $exLocation,
                'serial_no' => $cogData['serial_no'] ?? null,
                'unit_price' => $transaction->unit_cost,
                'total_amount' => $transaction->total_value,
                'remarks' => $cogData['item_remarks'] ?? $transaction->remarks,
            ]],
        ], $userId);

        $transaction->forceFill([
            'cog_id' => $cog->id,
            'cog_issued_out' => $transaction->cog_issued_out ?: $cog->cog_no,
        ])->save();

        return $cog;
    }
}
