<?php
namespace App\Http\Controllers;

use App\Models\{MajorEquipment, MiriRentalItem, MiriConstructionItem, MiriPaintItem, Branch};
use App\Services\{BranchContext, AuditLogger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MiriCompanyController extends Controller
{
    public function assign(Request $request, AuditLogger $audit)
    {
        $branch = app(BranchContext::class)->branch($request->user());
        abort_unless($branch?->code === 'MIRI', 404);
        abort_unless($request->user()->canEdit('assets'), 403);
        $data = $request->validate([
            'register' => ['required', 'in:machinery,cargo,rental,construction,paint'],
            'ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'company' => ['required', 'in:DESB,FTSB,unassigned'],
        ]);
        $class = match ($data['register']) {
            'machinery', 'cargo' => MajorEquipment::class, 'rental' => MiriRentalItem::class,
            'construction' => MiriConstructionItem::class, 'paint' => MiriPaintItem::class,
        };
        $changed = DB::transaction(function () use ($class, $data, $branch, $request, $audit) {
            Branch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $rows = $class::query()->where('branch_id', $branch->id)->whereIn('id', $data['ids'])
                ->when(in_array($data['register'], ['machinery', 'cargo']), fn ($q) => $q->where('inventory_type', $data['register']))
                ->orderBy('id')->lockForUpdate()->get();
            if ($rows->count() !== count($data['ids'])) throw ValidationException::withMessages(['ids' => 'Some selected items are unavailable in this register. Refresh and select again.']);
            $company = $data['company'] === 'unassigned' ? null : $data['company'];
            $changed = 0;
            foreach ($rows as $item) {
                if ($item->company === $company) continue;
                $before = $item->company;
                // Ownership-only update: do not recalculate stock or review flags.
                $class::query()->whereKey($item->id)->update(['company' => $company]);
                $audit->record('miri_inventory', 'company_assigned', 'Assigned company for '.$data['register'].' item #'.$item->id,
                    $item, before: ['company' => $before], after: ['company' => $company], user: $request->user(), request: $request);
                $changed++;
            }
            return $changed;
        });
        return back()->with('success', "Company updated for {$changed} items.");
    }
}
