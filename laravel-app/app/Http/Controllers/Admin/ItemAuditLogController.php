<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemAuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('item_audit_log')
            ->leftJoin('items','item_audit_log.item_id','=','items.id')
            ->leftJoin('users','item_audit_log.user_id','=','users.id')
            ->select('item_audit_log.*','items.name_ar as item_name','users.name as user_name')
            ->orderByDesc('item_audit_log.created_at');

        if ($request->filled('action_type')) $query->where('item_audit_log.action_type', $request->action_type);
        if ($request->filled('date'))        $query->whereDate('item_audit_log.created_at', $request->date);
        if ($request->filled('field'))       $query->where('item_audit_log.field_name', $request->field);

        $limit = $request->get('limit', 50);
        $logs = $query->paginate($limit)->withQueryString();

        return view('admin.item_audit_logs.index', compact('logs'));
    }
}
