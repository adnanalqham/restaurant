<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('activity_log')
            ->leftJoin('users','activity_log.user_id','=','users.id')
            ->select('activity_log.*','users.name as user_name','users.username')
            ->orderByDesc('activity_log.created_at');

        if ($request->filled('user_id'))  $query->where('activity_log.user_id', $request->user_id);
        if ($request->filled('action'))   $query->where('activity_log.action', 'like', '%'.$request->action.'%');
        if ($request->filled('date'))     $query->whereDate('activity_log.created_at', $request->date);

        $limit = $request->get('limit', 50);
        $logs  = $query->paginate($limit)->withQueryString();
        $users = DB::table('users')->orderBy('name')->get();

        return view('admin.activity_log.index', compact('logs','users'));
    }
}
