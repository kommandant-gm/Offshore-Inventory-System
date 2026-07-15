<?php

namespace App\Http\Middleware;

use App\Models\Cog;
use App\Models\InventoryTransaction;
use App\Support\AssistantPrompts;
use App\Support\StockAnomalyAgent;
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
                            'it_assets_read' => $request->user()->canRead('it_assets'),
                            'it_assets_edit' => $request->user()->canEdit('it_assets'),
                        ],
                        'active_branch' => $branchContext->branch($request->user())?->only(['id', 'code', 'name']),
                        'branches' => $request->user()->branches()->orderBy('name')->get()->map(fn ($branch) => [
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

        if ($user?->canRead('movements')) {
            $movementCountToday = InventoryTransaction::query()
                ->whereDate('transaction_date', today())
                ->count();

            if ($movementCountToday > 0) {
                $items->push([
                    'id' => 'movements-today',
                    'title' => "{$movementCountToday} movement(s) logged today",
                    'description' => 'Review today\'s stock movements.',
                    'href' => route('asset-movements.index'),
                    'tone' => 'success',
                ]);
            }
        }

        if ($user?->canRead('cogs')) {
            $pendingApprovals = Cog::query()
                ->where('status', 'pending_approval')
                ->count();

            if ($pendingApprovals > 0) {
                $items->push([
                    'id' => 'pending-cogs',
                    'title' => "{$pendingApprovals} COG approval(s) pending",
                    'description' => 'Open COG control to review pending approvals.',
                    'href' => route('cogs.index'),
                    'tone' => 'warning',
                ]);
            }
        }

        if ($user?->canRead('anomalies')) {
            $anomalySummary = app(StockAnomalyAgent::class)->report()['summary'];
            $anomalyCount = (int) ($anomalySummary['total'] ?? 0);

            if ($anomalyCount > 0) {
            $items->push([
                'id' => 'anomalies-review',
                'title' => "{$anomalyCount} stock anomaly alert(s)",
                'description' => 'Review flagged inventory exceptions that need action.',
                'href' => route('anomalies.index'),
                'tone' => 'neutral',
            ]);
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
