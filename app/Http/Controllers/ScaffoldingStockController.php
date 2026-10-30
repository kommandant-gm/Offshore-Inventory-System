<?php

namespace App\Http\Controllers;

use App\Models\MajorEquipment;
use App\Services\{BranchContext, ScaffoldingStockLedger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScaffoldingStockController extends Controller
{
    public static function options(): array
    {
        $branch = app(BranchContext::class)->id();
        $columns = ['subcategory' => 'section_2', 'description' => 'description', 'contract' => 'scaffolding_contract'];
        $result = [];
        foreach ($columns as $kind => $column) {
            $result[$kind] = MajorEquipment::scaffolding()->whereNotNull($column)->pluck($column)
                ->merge(\App\Support\ScaffoldingOptions::defaults($kind))
                ->merge(DB::table('scaffolding_options')->where('branch_id', $branch)->where('kind', $kind)->pluck('value'))
                ->filter(fn ($value) => filled($value))->unique()->sort()->values();
        }
        $result['contract'] = $result['contract']->merge(DB::table('scaffolding_stock_movements as m')
            ->join('scaffolding_stock_accounts as a', 'a.id', '=', 'm.account_id')->where('a.branch_id', $branch)->pluck('m.contract'))
            ->filter(fn ($value) => filled($value))->unique()->sort()->values();
        $result['descriptionsBySubcategory'] = \App\Support\ScaffoldingOptions::DESCRIPTIONS;
        return $result;
    }

    public static function data(MajorEquipment $item): array
    {
        $ledger = app(ScaffoldingStockLedger::class);
        $accounts = DB::table('scaffolding_stock_accounts')->where('equipment_id', $item->id)->get()->keyBy('yard');
        return [
            'yards' => collect(ScaffoldingStockLedger::YARDS)->map(fn ($yard) => [
                'yard' => $yard, 'account' => $accounts->get($yard), 'token' => $ledger->token($accounts->get($yard)),
            ])->values(),
            'movements' => DB::table('scaffolding_stock_movements as m')->join('scaffolding_stock_accounts as a', 'a.id', '=', 'm.account_id')
                ->leftJoin('miri_cog_items as line', 'line.id', '=', 'm.cog_item_id')
                ->where('a.equipment_id', $item->id)->orderByDesc('m.id')
                ->select('m.*', 'a.yard', 'a.unit', 'line.miri_cog_id')->paginate(30, ['*'], 'stock_page')->withQueryString(),
            'options' => self::options(),
        ];
    }

    private function authorizeStock(Request $request): int
    {
        $branch = app(BranchContext::class)->branch($request->user());
        abort_unless($branch?->code === 'MIRI', 404);
        abort_unless($request->user()?->canEdit('assets'), 403);
        return $branch->id;
    }

    public function option(Request $request)
    {
        $branch = $this->authorizeStock($request);
        $data = $request->validate(['kind' => ['required', 'in:subcategory,description,contract'], 'value' => ['required', 'string', 'max:255']]);
        $value = trim($data['value']);
        abort_if($value === '', 422);
        DB::table('scaffolding_options')->insertOrIgnore(['branch_id' => $branch, 'kind' => $data['kind'], 'value' => $value]);
        return back()->with('success', 'Scaffolding option added.');
    }

    public function store(Request $request, MajorEquipment $equipment, ScaffoldingStockLedger $ledger)
    {
        $branch = $this->authorizeStock($request);
        abort_unless($equipment->branch_id === $branch && $equipment->isScaffolding(), 404);
        $rules = [
            'kind' => ['required', 'in:opening,receipt,issue,backload,writeoff,correction'],
            'yard' => ['required', 'in:BINTULU,LABUAN'], 'stock_token' => ['required', 'string', 'size:64'],
            'request_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
        ];
        $rowRules = [
            'quantity' => ['required', 'numeric', 'min:'.(in_array($request->input('kind'), ['opening', 'correction']) ? '0' : '0.001'), 'max:999999999.999', 'decimal:0,3'],
            'destination' => [$request->input('kind') === 'issue' ? 'required' : 'nullable', 'string', 'max:255'],
            'contract' => ['nullable', 'string', 'max:255'], 'mr_reference' => ['nullable', 'string', 'max:255'],
            'reference' => [in_array($request->input('kind'), ['issue', 'backload']) ? 'required' : 'nullable', 'string', 'max:255'],
            'document_date' => ['required', 'date_format:Y-m-d'], 'note' => ['required', 'string', 'max:1000'],
        ];
        if ($request->input('kind') === 'issue') {
            $rules['rows'] = ['required', 'array', 'min:1', 'max:10'];
            foreach ($rowRules as $key => $rule) $rules['rows.*.'.$key] = $rule;
        } else $rules += $rowRules;
        $ledger->manual($equipment->id, $branch, $request->validate($rules), $request->user()->id);
        return back()->with('success', 'Scaffolding stock movement confirmed.');
    }
}
