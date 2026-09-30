<?php

namespace App\Http\Controllers;

use App\Models\Import;
use App\Models\RollCallVote;
use App\Models\StagedVoteRecord;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'importCount' => Import::count(),
            'pendingCount' => StagedVoteRecord::where('status', 'pending')->count(),
            'confirmedCount' => RollCallVote::whereNotNull('confirmed_at')->count(),
        ]);
    }
}
