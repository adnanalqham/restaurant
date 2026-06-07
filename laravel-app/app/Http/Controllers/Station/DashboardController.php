<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Allowed categories for this station user
        $allowedCats = DB::table('user_category_permissions')
            ->where('user_id', auth()->id())
            ->pluck('category_id')
            ->toArray();
            
        return view('station.dashboard', compact('allowedCats'));
    }
}
