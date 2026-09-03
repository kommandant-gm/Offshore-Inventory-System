<?php

namespace App\Http\Middleware;

use App\Models\Asset;
use App\Models\KemamanInventoryItem;
use App\Models\MajorEquipmentCertificate;
use App\Support\AssistantPrompts;
use Illuminate\Http\Request;
use App\Services\BranchContext;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $branchContext = app(BranchContext::class);
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'username' => $request->user()->username,
                        'email' => $request->user()->email,
                        'role' => $request->user()->role,
                        'permissions' => $request->user()->resolvedPermissions(),
                        'can' => [
                            'assistant_read' => $request->user()->canRead('assistant'),
                            'anomalies_read' => $request->user()->canRead('anomalies'),
                            'movements_read' => $request->user()->canRead('movements'),
                            'movements_edit' => $request->user()->canEdit('movements'),
                            'settings_read' => $request->user()->canRead('settings'),
                            'settings_edit' => $request->user()->canEdit('settings'),
                            'superadmin' => $request->user()->isSuperAdmin(),
                            'it_assets_read' => $request->user()->canRead('it_assets'),
                            'it_assets_edit' => $request->user()->canEdit('it_assets'),
                            'assets_edit' => $request->user()->canEdit('assets'),
                        ],
                        'active_branch' => $branchContext->branch($request->user())?->only(['id', 'code', 'name']),
                        'branches' => $request->user()->branches()
                            ->whereIn('branches.id', $branchContext->accessibleIds($request->user()))
                            ->orderBy('name')->get()->map(fn ($branch) => [
                            'id' => $branch->id,
                            'code' => $branch->code,
                            'name' => $branch->name,
                            'access_level' => $branch->pivot->access_level,
                        ]),
                    ]
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'checkout_success' => fn () => $request->session()->get('checkout_success'),
                'checkout_error' => fn () => $request->session()->get('checkout_error'),
            ],
            'ui' => $request->user() ? [
                'notifications' => fn () => $this->notifications($request),
                'assistant_prompts' => fn () => $request->user()->canRead('assistant')
                    ? AssistantPrompts::forBranch($branchContext->branch($request->user())?->code)
                    : [],
                'assistant_context' => fn () => $this->assistantContext($branchContext->branch($request->user())?->code),
            ] : null,
        ];
    }

    private function notifications(Request $request): array
    {
        $user = $request->user();
        $items = collect();
        $branchCode = app(BranchContext::class)->branch($user)?->code;

        if ($branchCode === 'MIRI' && $user?->canRead('assets')) {
            $expiring = MajorEquipmentCertificate::query()->whereNotNull('expiry_date')
                ->whereBetween('expiry_date', [today(), today()->addDays(30)])->count();
            if ($expiring > 0) {
                $items->push(['id' => 'miri-certificates-expiring', 'title' => "{$expiring} certificate(s) expiring soon", 'description' => 'Review Miri equipment certificates within 30 days.', 'href' => route('major-equipment.index'), 'tone' => 'warning']);
            }
        } elseif ($branchCode === 'KL-IT' && $user?->canRead('it_assets')) {
            $repairCount = Asset::query()->where('current_status', 'under_repair')->count();
            if ($repairCount > 0) {
                $items->push(['id' => 'it-repairs', 'title' => "{$repairCount} IT asset(s) under repair", 'description' => 'Review the current IT repair queue.', 'href' => route('it-assets.repairs'), 'tone' => 'warning']);
            }
        } elseif ($branchCode === 'KEMAMAN' && $user?->canRead('assets')) {
            $expiring = KemamanInventoryItem::query()->whereNotNull('test_expiry_date')
                ->whereBetween('test_expiry_date', [today(), today()->addDays(30)])->count();
            if ($expiring > 0) {
                $items->push(['id' => 'kemaman-certificates-expiring', 'title' => "{$expiring} certificate(s) expiring soon", 'description' => 'Review Kemaman equipment certification dates.', 'href' => route('kemaman-inventory.dashboard'), 'tone' => 'warning']);
            }
        }

        return [
            'items' => $items->take(5)->values()->all(),
            'unread_count' => $items->count(),
        ];
    }

    private function assistantContext(?string $code): array
    {
        $kl=$code==='KL-IT'; return ['branch_code'=>$code,'title'=>$kl?'KL IT Asset Assistant':'Miri Inventory Assistant','subtitle'=>$kl?'Live IT asset answers':'Live inventory answers','intro'=>$kl?'Ask about asset tags, serial numbers, assignments, operating systems, age, or repairs.':'Ask about item location, current stock, last movement, or stock anomalies.','placeholder'=>$kl?'Ask about an IT asset...':'Ask about a stock item...'];
    }
}
