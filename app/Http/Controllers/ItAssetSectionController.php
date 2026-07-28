<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\ItLicense;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ItAssetSectionController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $this->authorizeRead($request);

        $assets = Asset::query()
            ->with(['category:id,name', 'currentLocation:id,name', 'currentAssignment'])
            ->get();

        $licenses = ItLicense::query()
            ->orderBy('software_name')
            ->orderBy('license_code')
            ->get();

        $status = $assets
            ->countBy(fn (Asset $asset) => $asset->current_status->value)
            ->map(fn (int $total, string $value) => [
                'label' => str($value)->replace('_', ' ')->title()->toString(),
                'value' => $total,
            ])->values();

        $breakdown = fn (callable $label, int $limit = 8) => $assets
            ->countBy(fn (Asset $asset) => $label($asset) ?: 'Not specified')
            ->sortDesc()
            ->take($limit)
            ->map(fn (int $total, string $name) => ['label' => $name, 'value' => $total])
            ->values();

        $currentYear = now()->year;
        $ageBands = [
            'Under 2 years' => 0,
            '2–4 years' => 0,
            '5–7 years' => 0,
            '8+ years' => 0,
            'Unknown' => 0,
        ];
        foreach ($assets as $asset) {
            $year = $asset->purchase_year ?: (is_numeric($asset->year) ? (int) $asset->year : null);
            $age = $year ? max(0, $currentYear - $year) : null;
            $band = $age === null ? 'Unknown'
                : ($age < 2 ? 'Under 2 years' : ($age < 5 ? '2–4 years' : ($age < 8 ? '5–7 years' : '8+ years')));
            $ageBands[$band]++;
        }

        $ageLabels = array_keys($ageBands);
        $assetAgeBand = function (Asset $asset) use ($currentYear, $ageLabels): string {
            $year = $asset->purchase_year ?: (is_numeric($asset->year) ? (int) $asset->year : null);
            $age = $year ? max(0, $currentYear - $year) : null;

            return $age === null ? $ageLabels[4]
                : ($age < 2 ? $ageLabels[0] : ($age < 5 ? $ageLabels[1] : ($age < 8 ? $ageLabels[2] : $ageLabels[3])));
        };

        return Inertia::render('ItAssets/Section', [
            'title' => 'IT Dashboard', 'description' => 'KL IT asset overview and lifecycle status.',
            'stats' => [
                ['label' => 'Total assets', 'value' => Asset::count()],
                ['label' => 'Assigned', 'value' => Asset::where('current_status', AssetStatus::Deployed->value)->count()],
                ['label' => 'Available', 'value' => Asset::where('current_status', AssetStatus::Available->value)->count()],
                ['label' => 'Under repair', 'value' => Asset::where('current_status', AssetStatus::UnderRepair->value)->count()],
            ],
            'charts' => [
                'status' => $status,
                'categories' => $breakdown(fn (Asset $asset) => $asset->category?->name),
                'locations' => $breakdown(fn (Asset $asset) => $asset->currentLocation?->name),
                'departments' => $assets
                    ->filter(fn (Asset $asset) => $asset->currentAssignment !== null)
                    ->countBy(fn (Asset $asset) => trim((string) $asset->currentAssignment?->department) ?: 'Unspecified')
                    ->sortDesc()
                    ->map(fn (int $total, string $name) => ['label' => $name, 'value' => $total])
                    ->values(),
                'conditions' => $breakdown(fn (Asset $asset) => $asset->current_condition?->value
                    ? str($asset->current_condition->value)->replace('_', ' ')->title()->toString()
                    : null),
                'age' => collect($ageBands)->map(fn (int $total, string $label) => [
                    'label' => $label, 'value' => $total,
                ])->values(),
                'purchaseYears' => $assets
                    ->filter(fn (Asset $asset) => $asset->purchase_year)
                    ->countBy('purchase_year')
                    ->sortKeys()
                    ->map(fn (int $total, int|string $year) => ['label' => (string) $year, 'value' => $total])
                    ->values(),
            ],
            'dashboardAssets' => $assets->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'assetTag' => $asset->asset_tag_no,
                'detail' => $asset->model ?: $asset->description,
                'status' => str($asset->current_status->value)->replace('_', ' ')->title()->toString(),
                'category' => $asset->category?->name ?: 'Not specified',
                'location' => $asset->currentLocation?->name ?: 'Not specified',
                'holder' => $asset->currentAssignment?->assigned_to_name,
                'department' => $asset->currentAssignment
                    ? (trim((string) $asset->currentAssignment->department) ?: 'Unspecified')
                    : null,
                'condition' => $asset->current_condition?->value
                    ? str($asset->current_condition->value)->replace('_', ' ')->title()->toString()
                    : 'Not specified',
                'age' => $assetAgeBand($asset),
                'purchaseYear' => $asset->purchase_year ? (string) $asset->purchase_year : null,
            ])->values(),
            'licenseDashboard' => $this->licenseDashboard($licenses),
        ]);
    }

    private function licenseDashboard(Collection $licenses): array
    {
        $statusLabels = [
            'active' => 'Active',
            'expiring_soon' => 'Expiring soon',
            'expired' => 'Expired',
            'inactive' => 'Inactive',
        ];
        $statusTotals = $licenses->countBy(fn (ItLicense $license) => $license->status());
        $totalSeats = (int) $licenses->where('active', true)->sum('seats_total');
        $assignedSeats = (int) $licenses->where('active', true)->sum('seats_assigned');
        $today = today();
        $monthStart = $today->copy()->startOfMonth();

        $expiryTimeline = collect(range(0, 11))->map(function (int $offset) use ($licenses, $monthStart) {
            $start = $monthStart->copy()->addMonths($offset);
            $end = $start->copy()->endOfMonth();
            $expiring = $licenses->filter(fn (ItLicense $license) => $license->active
                && $license->expiry_date?->betweenIncluded($start, $end));

            return [
                'label' => $start->format('M'),
                'full_label' => $start->format('M Y'),
                'value' => $expiring->count(),
                'cost' => round((float) $expiring->sum('renewal_cost'), 2),
            ];
        });

        $seatUtilisation = $licenses
            ->where('active', true)
            ->groupBy('software_name')
            ->map(function (Collection $items, string $software) {
                $purchased = (int) $items->sum('seats_total');
                $assigned = (int) $items->sum('seats_assigned');

                return [
                    'label' => $software,
                    'assigned' => $assigned,
                    'available' => max(0, $purchased - $assigned),
                    'total' => $purchased,
                    'percent' => $purchased > 0 ? (int) round(($assigned / $purchased) * 100) : 0,
                ];
            })
            ->sortByDesc('assigned')
            ->take(8)
            ->values();

        $renewalCostByVendor = $licenses
            ->where('active', true)
            ->filter(fn (ItLicense $license) => (float) $license->renewal_cost > 0)
            ->groupBy(fn (ItLicense $license) => trim((string) $license->vendor) ?: 'Other vendors')
            ->map(fn (Collection $items, string $vendor) => [
                'label' => $vendor,
                'value' => round((float) $items->sum('renewal_cost'), 2),
            ])
            ->sortByDesc('value')
            ->take(6)
            ->values();

        return [
            'summary' => [
                'total_licenses' => $licenses->count(),
                'total_seats' => $totalSeats,
                'assigned_seats' => $assignedSeats,
                'available_seats' => max(0, $totalSeats - $assignedSeats),
                'expiring_soon' => (int) ($statusTotals['expiring_soon'] ?? 0),
                'expired' => (int) ($statusTotals['expired'] ?? 0),
                'renewal_cost' => round((float) $licenses->where('active', true)->sum('renewal_cost'), 2),
            ],
            'status' => collect($statusLabels)->map(fn (string $label, string $key) => [
                'label' => $label,
                'value' => (int) ($statusTotals[$key] ?? 0),
            ])->values(),
            'seat_utilisation' => $seatUtilisation,
            'expiry_timeline' => $expiryTimeline,
            'renewal_cost_by_vendor' => $renewalCostByVendor,
            'licenses' => $licenses->map(fn (ItLicense $license) => [
                'id' => $license->id,
                'code' => $license->license_code,
                'software' => $license->software_name,
                'license_key_reference' => $this->licenseKeyReference($license),
                'vendor' => trim((string) $license->vendor) ?: 'Other vendors',
                'status' => $statusLabels[$license->status()],
                'active' => $license->active,
                'seats_total' => $license->seats_total,
                'seats_assigned' => $license->seats_assigned,
                'seats_available' => max(0, $license->seats_total - $license->seats_assigned),
                'expiry_date' => $license->expiry_date?->format('Y-m-d'),
                'expiry_month' => $license->expiry_date?->format('M Y'),
                'days_until_expiry' => $license->expiry_date
                    ? (int) $today->diffInDays($license->expiry_date, false)
                    : null,
                'renewal_cost' => round((float) $license->renewal_cost, 2),
                'auto_renew' => $license->auto_renew,
            ])->values(),
            'upcoming_renewals' => $licenses
                ->filter(fn (ItLicense $license) => $license->active && $license->expiry_date?->gte($today))
                ->sortBy('expiry_date')
                ->take(8)
                ->map(fn (ItLicense $license) => [
                    'id' => $license->id,
                    'code' => $license->license_code,
                    'software' => $license->software_name,
                    'vendor' => $license->vendor ?: 'Not specified',
                    'expiry_date' => $license->expiry_date?->format('Y-m-d'),
                    'days_until_expiry' => (int) $today->diffInDays($license->expiry_date, false),
                    'renewal_cost' => round((float) $license->renewal_cost, 2),
                    'auto_renew' => $license->auto_renew,
                    'status' => $license->status(),
                ])->values(),
        ];
    }

    private function licenseKeyReference(ItLicense $license): string
    {
        if (blank($license->license_key)) {
            return $license->license_code.' - no key recorded';
        }

        return $license->license_code.' - key ending '.mb_substr($license->license_key, -4);
    }

    public function repairs(Request $request): Response
    {
        $this->authorizeRead($request);

        return Inertia::render('ItAssets/Section', [
            'title' => 'Repairs', 'description' => 'IT assets currently recorded as under repair.',
            'repairMode' => true,
            'rows' => Asset::query()
                ->where('current_status', AssetStatus::UnderRepair->value)
                ->with(['movements' => fn ($query) => $query->where('movement_type', 'send_for_repair')])
                ->orderBy('asset_tag_no')->get()->map(function (Asset $asset) {
                    $repair = $asset->movements->first();

                    return [
                        'asset_tag' => $asset->asset_tag_no,
                        'asset_id' => $asset->id,
                        'detail' => $asset->model ?: $asset->description,
                        'repair_date' => $repair?->movement_date?->format('Y-m-d'),
                        'handled_by' => $repair?->handled_by,
                        'reference_no' => $repair?->reference_no,
                        'remarks' => $repair?->remarks,
                    ];
                }),
            'repairableAssets' => Asset::query()
                ->whereIn('current_status', [
                    AssetStatus::Available->value,
                    AssetStatus::Damaged->value,
                    AssetStatus::InspectionHold->value,
                ])
                ->whereDoesntHave('currentAssignment')
                ->orderBy('asset_tag_no')
                ->get(['id', 'asset_tag_no', 'description', 'model'])
                ->map(fn (Asset $asset) => [
                    'id' => $asset->id,
                    'label' => $asset->asset_tag_no.' - '.($asset->model ?: $asset->description),
                ]),
        ]);
    }

    private function authorizeRead(Request $request): void
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);
    }
}
