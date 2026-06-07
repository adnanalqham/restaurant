<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // For the initial load, we fetch pending/confirmed orders
        // The rest is handled via API and SSE
        return view('cashier.dashboard');
    }
}
