<?php

namespace App\Support;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Str;

class ItAssetAssistant
{
    public function respond(string $message, ?User $user = null): array
    {
        if (! $user?->canRead('it_assets')) return ['intent'=>'forbidden','answer'=>'You do not have permission to view KL IT assets.'];
        $question=trim($message); $value=Str::lower($question); $query=Asset::query()->with(['currentLocation','currentAssignment']);

        if (Str::contains($value,['how many','count','total assets'])) {
            if (Str::contains($value,['available','unassigned'])) $query->where('current_status',AssetStatus::Available->value);
            elseif (Str::contains($value,['repair'])) $query->where('current_status',AssetStatus::UnderRepair->value);
            elseif (Str::contains($value,['assigned','deployed'])) $query->where('current_status',AssetStatus::Deployed->value);
            if (preg_match('/windows\s*(\d+)/i',$question,$m)) $query->where('operating_system','like',"%Windows {$m[1]}%");
            $count=$query->count(); return ['intent'=>'count_assets','answer'=>"KL IT: {$count} matching asset(s) found."];
        }

        if (preg_match('/(?:older than|more than)\s*(\d+)\s*year/i',$question,$m)) {
            $year=now()->year-(int)$m[1]; $assets=$query->where('purchase_year','<=',$year)->orderBy('purchase_year')->limit(20)->get();
            return $this->listResponse($assets,"KL IT: {$assets->count()} asset(s) are older than {$m[1]} years.",'age');
        }

        if (Str::contains($value,['under repair','repairs','repair assets'])) {
            $assets=$query->where('current_status',AssetStatus::UnderRepair->value)->limit(20)->get();
            return $this->listResponse($assets,"KL IT: {$assets->count()} asset(s) are currently under repair.",'repairs');
        }

        if (preg_match('/windows\s*(\d+)/i',$question,$m)) {
            $assets=$query->where('operating_system','like',"%Windows {$m[1]}%")->limit(20)->get();
            return $this->listResponse($assets,"KL IT: {$assets->count()} asset(s) run Windows {$m[1]}.",'operating_system');
        }

        $search=$this->searchTerm($question);
        $assets=$query->where(function($q)use($search){$q->where('asset_tag_no','like',"%{$search}%")->orWhere('serial_no','like',"%{$search}%")->orWhere('model','like',"%{$search}%")->orWhere('description','like',"%{$search}%")->orWhereHas('currentAssignment',fn($a)=>$a->where('assigned_to_name','like',"%{$search}%")->orWhere('department','like',"%{$search}%"));})->limit(20)->get();
        if ($assets->isEmpty()) return ['intent'=>'search','answer'=>'KL IT: I could not find a matching asset. Try an exact asset tag, serial number, employee, department, model, or operating system.'];
        if ($assets->count()>1) return $this->listResponse($assets,"KL IT: I found {$assets->count()} matching assets.",'search');
        $asset=$assets->first(); $holder=$asset->currentAssignment?->assigned_to_name ?: 'unassigned'; $location=$asset->currentLocation?->name ?: 'no location recorded';
        return ['intent'=>'asset_summary','answer'=>"KL IT: {$asset->asset_tag_no} is {$asset->current_status->value}, assigned to {$holder}, at {$location}. Serial: ".($asset->serial_no ?: 'not recorded').'. OS: '.($asset->operating_system ?: 'not recorded').'.','item'=>$this->payload($asset)];
    }

    private function listResponse($assets,string $answer,string $intent): array { return ['intent'=>$intent,'answer'=>$answer,'items'=>$assets->map(fn($a)=>$this->payload($a))->values()]; }
    private function payload(Asset $asset): array { return ['id'=>$asset->id,'item_code'=>$asset->asset_tag_no,'description'=>$asset->model ?: $asset->description,'current_stock'=>$asset->current_status->value,'uom'=>'','current_location'=>$asset->currentAssignment?->assigned_to_name ?? $asset->currentLocation?->name ?? 'Unassigned','href'=>route('it-assets.show',$asset)]; }
    private function searchTerm(string $question): string { $value=trim($question," \t\n\r\0\x0B?."); return trim((string)preg_replace('/^(where is|who (?:has|holds)|show|find|tell me about|asset|serial|department)\s+/i','',$value)); }
}
