<?php

namespace App\Http\Controllers;

use App\Models\AssetAssignment;
use App\Models\ItLicense;
use App\Models\ItPersonLink;
use App\Models\User;
use App\Services\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ItPeopleController extends Controller
{
    public function index(Request $request, BranchContext $branches): Response
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $people = $this->people($branches->id($request->user()));
        $summary = [
            'total' => $people->count(),
            'asset_holders' => $people->where('current_assets', '>', 0)->count(),
            'licence_holders' => $people->where('licences', '>', 0)->count(),
        ];

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = mb_strtolower($search);
            $people = $people->filter(function (array $person) use ($needle) {
                return collect([
                    $person['name'],
                    $person['employee_id'],
                    $person['email'],
                    $person['department'],
                ])->filter()->contains(fn (string $value) => str_contains(mb_strtolower($value), $needle));
            });
        }

        return Inertia::render('ItPeople/Index', [
            'people' => $people->values(),
            'summary' => $summary,
            'filters' => [
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    public function show(Request $request, string $person, BranchContext $branches): Response
    {
        abort_unless($request->user()?->canRead('it_assets'), 403);

        $identity = $this->decodeIdentity($person);
        $branchId = $branches->id($request->user());
        [$profile, $assignmentQuery, $licenceQuery] = $this->resolvePerson($identity, $branchId);

        $assignments = $assignmentQuery
            ->with(['asset.category', 'asset.currentLocation'])
            ->latest('assigned_at')
            ->latest('id')
            ->get();
        $licences = $licenceQuery
            ->latest('updated_at')
            ->get();

        abort_if(! $profile && $assignments->isEmpty() && $licences->isEmpty(), 404);

        $latestAssignment = $assignments->first(fn (AssetAssignment $assignment) => filled($assignment->department));
        $latestLicence = $licences->first(fn (ItLicense $licence) => filled($licence->department));
        $profile ??= [
            'name' => $assignments->first()?->assigned_to_name ?? $licences->first()?->assigned_to,
            'employee_id' => $assignments->first(fn (AssetAssignment $assignment) => filled($assignment->employee_id))?->employee_id,
            'email' => null,
        ];
        $profile['department'] = $profile['department'] ?? $latestAssignment?->department ?? $latestLicence?->department;

        $currentAssets = $assignments
            ->whereNull('returned_at')
            ->map(fn (AssetAssignment $assignment) => [
                'id' => $assignment->asset->id,
                'asset_tag_no' => $assignment->asset->asset_tag_no,
                'description' => $assignment->asset->description,
                'category' => $assignment->asset->category?->name,
                'brand_model' => trim(implode(' ', array_filter([$assignment->asset->brand, $assignment->asset->model]))),
                'serial_no' => $assignment->asset->serial_no,
                'location' => $assignment->asset->currentLocation?->name,
                'assigned_at' => $assignment->assigned_at?->format('Y-m-d'),
            ])
            ->values();

        $history = $assignments
            ->flatMap(function (AssetAssignment $assignment) {
                $events = [[
                    'date' => $assignment->assigned_at?->format('Y-m-d'),
                    'event' => 'Asset assigned',
                    'asset_id' => $assignment->asset->id,
                    'asset_tag_no' => $assignment->asset->asset_tag_no,
                    'description' => $assignment->asset->description,
                    'remarks' => $assignment->remarks,
                ]];

                if ($assignment->returned_at) {
                    $events[] = [
                        'date' => $assignment->returned_at->format('Y-m-d'),
                        'event' => 'Asset returned',
                        'asset_id' => $assignment->asset->id,
                        'asset_tag_no' => $assignment->asset->asset_tag_no,
                        'description' => $assignment->asset->description,
                        'remarks' => null,
                    ];
                }

                return $events;
            })
            ->sortByDesc('date')
            ->values();

        return Inertia::render('ItPeople/Show', [
            'person' => $profile,
            'personToken' => $person,
            'canLink' => $request->user()->canEdit('it_assets'),
            'linkOptions' => $request->user()->canEdit('it_assets')
                ? User::query()->where('directory_active', true)->orderBy('name')->get(['id', 'name', 'username', 'email', 'department', 'job_title'])
                : [],
            'summary' => [
                'current_assets' => $currentAssets->count(),
                'licences' => $licences->count(),
                'history_events' => $history->count(),
            ],
            'currentAssets' => $currentAssets,
            'licences' => $licences->map(fn (ItLicense $licence) => [
                'id' => $licence->id,
                'license_code' => $licence->license_code,
                'software_name' => $licence->software_name,
                'vendor' => $licence->vendor,
                'license_type' => $licence->license_type,
                'expiry_date' => $licence->expiry_date?->format('Y-m-d'),
                'status' => $licence->status(),
            ])->values(),
            'history' => $history,
        ]);
    }

    public function linkAdUser(Request $request, string $person, BranchContext $branches)
    {
        abort_unless($request->user()?->canEdit('it_assets'), 403);

        $identity = $this->decodeIdentity($person);
        abort_unless(str_starts_with($identity, 'n:'), 404);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $user = User::query()->where('directory_active', true)->findOrFail($data['user_id']);

        $existing = ItPersonLink::query()->where('user_id', $user->id)->first();
        if ($existing && $existing->manual_identity !== substr($identity, 2)) {
            return back()->with('error', 'This AD user is already linked to another manual person.');
        }

        ItPersonLink::updateOrCreate(
            ['manual_identity' => substr($identity, 2)],
            ['user_id' => $user->id],
        );

        return back()->with('success', "Linked {$user->name} to this manual person.");
    }

    private function people(?int $branchId): Collection
    {
        $people = [];
        $links = ItPersonLink::query()->with('user')->get()->keyBy('manual_identity');
        $directoryUsers = User::query()
            ->where('directory_active', true)
            ->get(['id', 'name', 'username', 'email', 'department', 'job_title']);
        $usersByName = $directoryUsers->filter(fn (User $user) => filled($user->name))->keyBy(fn (User $user) => $this->normalise($user->name));
        $usersByUsername = $directoryUsers->filter(fn (User $user) => filled($user->username))->keyBy(fn (User $user) => $this->normalise($user->username));
        $usersByEmail = $directoryUsers->filter(fn (User $user) => filled($user->email))->keyBy(fn (User $user) => $this->normalise($user->email));

        $directoryUserFor = function (?string $name, ?string $employeeId = null, ?string $email = null) use ($links, $usersByName, $usersByUsername, $usersByEmail): ?User {
            return $links->get($this->normalise($name))?->user
                ?: $usersByUsername->get($this->normalise($employeeId))
                ?: $usersByEmail->get($this->normalise($email))
                ?: $usersByName->get($this->normalise($name));
        };

        $assignments = AssetAssignment::query()
            ->latest('assigned_at')
            ->latest('id')
            ->get();

        foreach ($assignments as $assignment) {
            $directoryUser = $directoryUserFor($assignment->assigned_to_name, $assignment->employee_id, $assignment->assigned_email);
            $identity = $directoryUser ? 'u:'.$directoryUser->id : 'n:'.$this->normalise($assignment->assigned_to_name);
            $people[$identity] ??= $this->personRow(
                $identity,
                $directoryUser?->name ?: $assignment->assigned_to_name,
                $directoryUser?->username ?: $assignment->employee_id,
                $directoryUser?->email ?: $assignment->assigned_email,
                $directoryUser?->department ?: $assignment->department,
                $directoryUser?->job_title,
            );
            $people[$identity]['employee_id'] ??= $directoryUser?->username ?: $assignment->employee_id;
            $people[$identity]['email'] ??= $directoryUser?->email ?: $assignment->assigned_email;
            $people[$identity]['department'] ??= $directoryUser?->department ?: $assignment->department;
            $people[$identity]['assignment_history']++;
            if (! $assignment->returned_at) {
                $people[$identity]['current_assets']++;
            }
        }

        foreach (ItLicense::query()->whereNotNull('assigned_to')->where('assigned_to', '<>', '')->latest('updated_at')->get() as $licence) {
            $directoryUser = $directoryUserFor($licence->assigned_to);
            $identity = $directoryUser ? 'u:'.$directoryUser->id : 'n:'.$this->normalise($licence->assigned_to);
            $people[$identity] ??= $this->personRow(
                $identity,
                $directoryUser?->name ?: $licence->assigned_to,
                $directoryUser?->username,
                $directoryUser?->email,
                $directoryUser?->department ?: $licence->department,
                $directoryUser?->job_title,
            );
            $people[$identity]['email'] ??= $directoryUser?->email;
            $people[$identity]['department'] ??= $directoryUser?->department ?: $licence->department;
            $people[$identity]['licences']++;
        }

        return collect($people)
            ->map(function (array $person) {
                $person['token'] = $this->encodeIdentity($person['identity']);
                unset($person['identity']);

                return $person;
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function resolvePerson(string $identity, ?int $branchId): array
    {
        $assignmentQuery = AssetAssignment::query();
        $licenceQuery = ItLicense::query();
        $profile = null;

        if (str_starts_with($identity, 'u:')) {
            $userId = filter_var(substr($identity, 2), FILTER_VALIDATE_INT);
            abort_unless($userId, 404);
            $user = User::query()
                ->when($branchId, fn ($query) => $query->whereHas('branches', fn ($branch) => $branch->where('branches.id', $branchId)))
                ->findOrFail($userId);
            $profile = [
                'name' => $user->name,
                'employee_id' => $user->username,
                'email' => $user->email,
                'department' => $user->department,
                'job_title' => $user->job_title,
            ];
            $aliases = ItPersonLink::query()
                ->where('user_id', $user->id)
                ->pluck('manual_identity')
                ->push($this->normalise($user->name))
                ->filter()
                ->unique()
                ->values();
            $assignmentQuery->where(function ($query) use ($user, $aliases) {
                $query->whereRaw('LOWER(employee_id) = ?', [$this->normalise($user->username)])
                    ->orWhereRaw('LOWER(assigned_email) = ?', [$this->normalise($user->email)]);
                foreach ($aliases as $alias) {
                    $query->orWhereRaw('LOWER(assigned_to_name) = ?', [$alias]);
                }
            });
            $licenceQuery->where(function ($query) use ($aliases) {
                foreach ($aliases as $alias) {
                    $query->orWhereRaw('LOWER(assigned_to) = ?', [$alias]);
                }
            });
        } elseif (str_starts_with($identity, 'n:')) {
            $name = trim(substr($identity, 2));
            abort_if($name === '', 404);
            $linkedUser = ItPersonLink::query()->with('user')->where('manual_identity', $name)->first()?->user;
            if ($linkedUser?->directory_active) {
                $profile = [
                    'name' => $linkedUser->name,
                    'employee_id' => $linkedUser->username,
                    'email' => $linkedUser->email,
                    'department' => $linkedUser->department,
                    'job_title' => $linkedUser->job_title,
                    'linked_user_id' => $linkedUser->id,
                ];
            }
            $assignmentQuery->whereRaw('LOWER(assigned_to_name) = ?', [$name]);
            $licenceQuery->whereRaw('LOWER(assigned_to) = ?', [$name]);
        } else {
            abort(404);
        }

        return [$profile, $assignmentQuery, $licenceQuery];
    }

    private function personRow(string $identity, string $name, ?string $employeeId = null, ?string $email = null, ?string $department = null, ?string $jobTitle = null): array
    {
        return [
            'identity' => $identity,
            'name' => $name,
            'employee_id' => $employeeId,
            'email' => $email,
            'department' => $department,
            'job_title' => $jobTitle,
            'current_assets' => 0,
            'licences' => 0,
            'assignment_history' => 0,
        ];
    }

    private function normalise(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function encodeIdentity(string $identity): string
    {
        return rtrim(strtr(base64_encode($identity), '+/', '-_'), '=');
    }

    private function decodeIdentity(string $token): string
    {
        $padding = strlen($token) % 4;
        $encoded = strtr($token, '-_', '+/');
        if ($padding) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $identity = base64_decode($encoded, true);

        abort_if($identity === false, 404);

        return $identity;
    }
}
