<?php
namespace App\Services;

use App\Models\{Branch, MiriCog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MiriCogEditing
{
    public const GROUPS = [
        ['title' => 'Document details', 'fields' => [
            ['document_date', 'Document date', 'date'], ['consignee_name', 'Consignee name', 'text'],
            ['consignee_department', 'Consignee department', 'text'], ['from_department', 'From department / company', 'text'],
            ['from_location', 'From location', 'text'], ['to_location', 'To location', 'text'],
            ['copy_to', 'Copy to', 'text'], ['destination', 'Destination', 'text'], ['receiver_email', 'Receiver email', 'email'],
        ]],
        ['title' => 'Issued and checked by', 'fields' => [['issued_by_name', 'Name', 'text'], ['issued_designation', 'Designation', 'text'], ['issued_date', 'Date', 'date']]],
        ['title' => 'Verified by', 'fields' => [['verified_by_name', 'Name', 'text'], ['verified_designation', 'Designation', 'text'], ['verified_date', 'Date', 'date']]],
        ['title' => 'Received by', 'fields' => [['receiver_name', 'Name', 'text'], ['receiver_designation', 'Designation', 'text'], ['received_date', 'Date', 'date']]],
    ];
    public const LINE_FIELDS = [['description', 'Full description'], ['size_model', 'Size / Model'], ['identifier', 'Tagging / Equipment No.'],
        ['serial_no', 'Serial No.'], ['mr_reference', 'Material Requisition No.'], ['remarks', 'Remarks']];

    public function editable(MiriCog $cog): bool
    {
        return $cog->status === 'draft' && ! $cog->signature && ! $cog->construction_stock_confirmed_at;
    }
    public function token(MiriCog $cog): string
    {
        return hash('sha256', json_encode([$cog->getAttributes(), $cog->items()->orderBy('id')->get()->map(fn ($line) => $line->getAttributes())->all()]));
    }
    private function rules(): array
    {
        $rules = ['edit_token' => ['required', 'string', 'size:64'], 'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.id' => ['required', 'integer', 'distinct']];
        foreach (self::GROUPS as $group) foreach ($group['fields'] as [$key, $label, $type]) {
            $rules[$key] = [in_array($key, ['document_date', 'issued_by_name']) ? 'required' : 'nullable',
                ...($type === 'date' ? ['date_format:Y-m-d'] : [$type === 'email' ? 'email' : 'string', 'max:255'])];
        }
        foreach (self::LINE_FIELDS as [$key]) $rules['items.*.'.$key] = ['nullable', 'string', 'max:'.(in_array($key, ['mr_reference', 'remarks']) ? 1000 : 255)];
        foreach (['movement_type', 'cog_no', 'status', 'signature', 'construction_stock_confirmed_at', 'construction_stock_workflow', 'created_by', 'branch_id'] as $key) $rules[$key] = ['prohibited'];
        foreach (['item_type', 'item_id', 'quantity', 'unit', 'construction_destination_id', 'current_location', 'batch_no'] as $key) $rules['items.*.'.$key] = ['prohibited'];
        return $rules;
    }
    public function update(MiriCog $cog, Request $request): void
    {
        $data = $request->validate($this->rules());
        DB::transaction(function () use ($cog, $request, $data) {
            Branch::whereKey($cog->branch_id)->lockForUpdate()->firstOrFail();
            $locked = MiriCog::whereKey($cog->id)->lockForUpdate()->firstOrFail();
            if (! $this->editable($locked)) throw ValidationException::withMessages(['note' => 'Only unsigned, unconfirmed draft notes can be edited.']);
            if (! hash_equals($this->token($locked), $data['edit_token'])) throw ValidationException::withMessages(['note' => 'This note changed after you opened it. Reload the edit page before saving.']);
            $lines = $locked->items()->orderBy('id')->lockForUpdate()->get();
            if ($lines->pluck('id')->sort()->values()->all() !== collect($data['items'])->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                throw ValidationException::withMessages(['items' => 'Keep the original items on this note. Cancel an unfulfilled draft and create a new note to change items or quantities.']);
            }
            $headerKeys = ['remarks', ...collect(self::GROUPS)->flatMap(fn ($group) => array_column($group['fields'], 0))->all()];
            $before = ['document' => $locked->only($headerKeys),
                'items' => $lines->map(fn ($line) => $line->only(['id', ...array_column(self::LINE_FIELDS, 0)]))->all()];
            foreach ($data['items'] as $row) {
                $line = $lines->firstWhere('id', $row['id']);
                if ($line->item_type === 'Paint' && filled($row['identifier'] ?? null)) throw ValidationException::withMessages(['items' => 'Paint uses its recorded batch number, not an equipment tag.']);
                $line->update(collect($row)->only(array_column(self::LINE_FIELDS, 0))->all());
            }
            $locked->fill(collect($data)->only($headerKeys)->all());
            $locked->updated_by = $request->user()->id;
            $locked->save();
            app(AuditLogger::class)->record('miri_cogs', 'updated', "Edited Internal Issue Note {$locked->cog_no}.", $locked,
                before: $before, after: ['document' => $locked->only(array_keys($before['document'])),
                    'items' => $lines->map(fn ($line) => $line->only(['id', ...array_column(self::LINE_FIELDS, 0)]))->all()],
                user: $request->user(), request: $request);
        });
    }
}
