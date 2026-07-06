<?php

namespace App\Http\Controllers;

use App\Support\StockAnomalyAgent;
use Inertia\Inertia;
use Inertia\Response;

class StockAnomalyController extends Controller
{
    public function index(StockAnomalyAgent $agent): Response
    {
        abort_unless(request()->user()?->canRead('anomalies'), 403);

        return Inertia::render('Anomalies/Index', [
            'report' => $agent->report(),
        ]);
    }
}
