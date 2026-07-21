<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\View\View;

class PublicAssetController extends Controller
{
    public function __invoke(string $publicToken): View
    {
        $asset = Asset::withoutGlobalScopes()
            ->with(['category:id,name', 'currentLocation:id,name'])
            ->where('public_token', $publicToken)
            ->firstOrFail();

        return view('it-assets.public', ['asset' => $asset]);
    }
}
