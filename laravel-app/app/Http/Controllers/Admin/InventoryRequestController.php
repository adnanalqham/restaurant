<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class InventoryRequestController extends Controller
{
    public function index(\Illuminate\Http\Request $request)
    {
        $query = \Illuminate\Support\Facades\DB::table('inv_requests')
            ->join('users', 'inv_requests.requester_id', '=', 'users.id')
            ->select('inv_requests.*', 'users.name as requester_name')
            ->orderByDesc('inv_requests.created_at');

        if ($request->filled('status')) {
            $query->where('inv_requests.status', $request->status);
        }

        $requests = $query->paginate(20)->withQueryString();

        // Get items for these requests
        $reqIds = $requests->pluck('id');
        $items = \Illuminate\Support\Facades\DB::table('inv_request_items')
            ->join('inv_items', 'inv_request_items.item_id', '=', 'inv_items.id')
            ->whereIn('request_id', $reqIds)
            ->select('inv_request_items.*', 'inv_items.name as item_name', 'inv_items.unit', 'inv_items.current_stock')
            ->get()
            ->groupBy('request_id');

        foreach ($requests as $req) {
            $req->items = $items->get($req->id) ?? collect([]);
        }

        return view('admin.inventory_requests.index', compact('requests'));
    }

    public function updateStatus(\Illuminate\Http\Request $request, int $id)
    {
        $req = \Illuminate\Support\Facades\DB::table('inv_requests')->find($id);
        if (!$req) return back()->with('error', 'الطلب غير موجود');

        $status = $request->status;
        $updateData = [
            'status' => $status,
            'updated_at' => now(),
            'notes' => $request->notes ?? $req->notes
        ];

        if ($status === 'approved') {
            $updateData['coordinator_id'] = auth()->id();
        } elseif ($status === 'issued') {
            $updateData['warehouse_manager_id'] = auth()->id();
            
            // Deduct from stock based on issued quantity.
            // For simplicity in this controller update, we just mark as issued.
            // Ideally, the issued quantities would be sent in the request and updated per item.
            // We assume the requested qty is fully issued.
            $items = \Illuminate\Support\Facades\DB::table('inv_request_items')->where('request_id', $id)->get();
            foreach($items as $item) {
                \Illuminate\Support\Facades\DB::table('inv_items')->where('id', $item->item_id)->decrement('current_stock', $item->requested_qty);
                \Illuminate\Support\Facades\DB::table('inv_request_items')->where('id', $item->id)->update(['issued_qty' => $item->requested_qty]);
            }
            
        } elseif ($status === 'rejected') {
            $updateData['rejection_reason'] = $request->rejection_reason;
        }

        \Illuminate\Support\Facades\DB::table('inv_requests')->where('id', $id)->update($updateData);

        return back()->with('success', 'تم تحديث حالة الطلب');
    }
}
