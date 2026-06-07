<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class CategoryApiController extends Controller
{
    public function index()
    {
        $cats = DB::table('categories')->orderBy('sort_order')->get();
        return response()->json(['success' => true, 'data' => $cats]);
    }
}
