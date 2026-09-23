<?php
namespace App\Models\Concerns;

trait HasInventoryCompany
{
    public function scopeCompanyFilter($query, ?string $company)
    {
        if ($company === 'unassigned') return $query->whereNull($query->qualifyColumn('company'));
        if (in_array($company, ['DESB', 'FTSB'], true)) return $query->where($query->qualifyColumn('company'), $company);
        return $query;
    }
}
