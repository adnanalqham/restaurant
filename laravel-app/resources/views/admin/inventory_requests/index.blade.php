@extends('layouts.admin')
@section('title', 'طلبات الصرف من المخزن')

@section('content')
<div class="card mb-20">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-list"></i> طلبات الصرف (العهد)</h3>
    </div>
    <div class="card-body">
        <form method="GET" style="display:flex; gap:10px; margin-bottom: 20px;">
            <select name="status" class="form-control" style="width:200px">
                <option value="">كل الحالات</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>قيد الانتظار</option>
                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>مقبول (في انتظار الصرف)</option>
                <option value="issued" {{ request('status') == 'issued' ? 'selected' : '' }}>تم الصرف</option>
                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>مرفوض</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>ملغي</option>
            </select>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> تصفية</button>
            <a href="{{ url()->current() }}" class="btn btn-secondary">إعادة ضبط</a>
        </form>
    </div>
</div>

<div class="row">
    @forelse($requests as $req)
        <div class="col-md-6 mb-20">
            <div class="card" style="border-top: 4px solid 
                {{ $req->status == 'pending' ? '#f39c12' : 
                   ($req->status == 'approved' ? '#3498db' : 
                   ($req->status == 'issued' ? '#27ae60' : '#e74c3c')) }}">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
                    <div>
                        <h4 style="margin: 0;">طلب رقم #{{ $req->id }}</h4>
                        <small style="color: #888;">بواسطة: {{ $req->requester_name }}</small>
                    </div>
                    <div>
                        <span class="badge badge-{{ $req->status == 'pending' ? 'warning' : ($req->status == 'approved' ? 'info' : ($req->status == 'issued' ? 'success' : 'danger')) }}">
                            {{ $req->status == 'pending' ? 'قيد الانتظار' : ($req->status == 'approved' ? 'مقبول' : ($req->status == 'issued' ? 'تم الصرف' : 'مرفوض/ملغي')) }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 10px;">
                        <i class="far fa-clock"></i> {{ \Carbon\Carbon::parse($req->created_at)->format('Y-m-d H:i') }}
                    </p>
                    
                    <table class="table table-sm table-bordered" style="font-size: 0.9rem; margin-bottom: 15px;">
                        <thead style="background: #f1f1f1;">
                            <tr>
                                <th>الصنف</th>
                                <th style="text-align: center;">الكمية المطلوبة</th>
                                <th style="text-align: center;">المتوفر بالمخزن</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($req->items as $item)
                                <tr>
                                    <td>{{ $item->item_name }}</td>
                                    <td style="text-align: center;"><strong>{{ $item->requested_qty }}</strong> <small>{{ $item->unit }}</small></td>
                                    <td style="text-align: center; color: {{ $item->current_stock < $item->requested_qty ? '#e74c3c' : '#27ae60' }};">
                                        {{ $item->current_stock }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($req->notes)
                        <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.85rem;">
                            <strong>ملاحظات الطلب:</strong> {{ $req->notes }}
                        </div>
                    @endif
                    
                    @if($req->rejection_reason)
                        <div style="background: #f8d7da; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.85rem; color: #721c24;">
                            <strong>سبب الرفض:</strong> {{ $req->rejection_reason }}
                        </div>
                    @endif

                    <div style="border-top: 1px solid #eee; padding-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                        @if($req->status == 'pending')
                            <form action="{{ route('admin.inventory.requests.status', $req->id) }}" method="POST" style="display: inline;">
                                @csrf
                                <input type="hidden" name="status" value="approved">
                                <button type="submit" class="btn btn-info btn-sm"><i class="fas fa-check"></i> قبول مبدئي</button>
                            </form>
                            <button type="button" class="btn btn-danger btn-sm" onclick="rejectRequest({{ $req->id }})"><i class="fas fa-times"></i> رفض</button>
                        @elseif($req->status == 'approved')
                            <form action="{{ route('admin.inventory.requests.status', $req->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('تأكيد صرف المواد؟ سيتم خصمها من المخزون فوراً.');">
                                @csrf
                                <input type="hidden" name="status" value="issued">
                                <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-box-open"></i> تأكيد الصرف (خصم من المخزون)</button>
                            </form>
                            <button type="button" class="btn btn-danger btn-sm" onclick="rejectRequest({{ $req->id }})"><i class="fas fa-times"></i> إلغاء / رفض</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12 text-center" style="padding: 40px; color: #888;">
            <i class="fas fa-inbox fa-3x mb-3" style="opacity: 0.5;"></i>
            <h4>لا توجد طلبات صرف حالياً</h4>
        </div>
    @endforelse
</div>

<div class="mt-3">
    {{ $requests->links() }}
</div>

{{-- Reject Modal --}}
<div class="modal-backdrop hidden" id="reject-modal">
    <div class="modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>سبب الرفض</h3>
            <button class="modal-close" onclick="closeRejectModal()">✕</button>
        </div>
        <form id="reject-form" method="POST">
            @csrf
            <input type="hidden" name="status" value="rejected">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">سبب الرفض (سيظهر لمقدم الطلب)</label>
                    <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">إلغاء</button>
                <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    function rejectRequest(id) {
        document.getElementById('reject-form').action = "{{ url('admin/inventory-requests') }}/" + id + "/status";
        document.getElementById('reject-modal').classList.remove('hidden');
    }
    function closeRejectModal() {
        document.getElementById('reject-modal').classList.add('hidden');
    }
</script>
@endpush
