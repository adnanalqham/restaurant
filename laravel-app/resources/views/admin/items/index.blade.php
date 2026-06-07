@extends('layouts.admin')
@section('title', 'إدارة الأصناف')
@section('content')

<!-- Filters & Actions -->
<div class="card mb-16">
    <div class="card-body" style="padding:14px 20px">
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap">
            <select class="form-control" id="filter-cat" style="width:auto; min-width:180px">
                <option value="">كل الفئات</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name_ar }}</option>
                @endforeach
            </select>
            <div class="search-box" style="flex:1; min-width:200px">
                <input type="text" class="form-control" id="search-input" placeholder="بحث عن صنف...">
            </div>
            <button class="btn btn-primary" onclick="openItemModal()"><i class="fas fa-plus"></i> إضافة صنف</button>
            <button class="btn btn-success" onclick="exportItemsExcel()"><i class="fas fa-file-excel"></i> تصدير إكسل</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
        <div style="display:flex; align-items:center; gap:10px">
            <h3 style="margin:0"><i class="fas fa-utensils"></i> قائمة الأصناف</h3>
            <span id="items-count" class="badge badge-info"></span>
        </div>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-controls" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:15px 15px 0;">
            <div style="font-size:0.9rem">
                عرض 
                <select class="form-control" id="items-limit" style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.9rem">
                    <option value="10">10</option>
                    <option value="20">20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                أسطر
            </div>
        </div>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th onclick="toggleSort()" style="cursor:pointer"># <i id="sort-icon" class="fas fa-sort-numeric-down"></i></th>
                        <th>رقم الصنف</th>
                        <th>الصورة</th>
                        <th>الاسم العربي</th>
                        <th>الاسم الإنجليزي</th>
                        <th>الفئة</th>
                        <th>السعر</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody id="items-tbody">
                    <tr>
                        <td colspan="9" style="text-align:center;padding:30px">
                            <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--primary)"></i>
                            <p style="margin-top:10px">جاري تحميل البيانات...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="pagination-container" style="padding:15px; display:flex; justify-content:center"></div>
    </div>
</div>

{{-- Add/Edit Modal --}}
<div class="modal-backdrop hidden" id="item-modal">
    <div class="modal" style="max-width:720px">
        <div class="modal-header">
            <h3 id="modal-title">إضافة صنف</h3>
            <button class="btn-close" onclick="closeItemModal()">✕</button>
        </div>
        <div class="modal-body">
            <form id="item-form" method="POST" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="_method" id="form-method" value="POST">
                <input type="hidden" id="item-id">
                
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">الاسم العربي *</label>
                        <input type="text" name="name_ar" id="i-name-ar" class="form-control" required placeholder="مثال: برجر لحم">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الاسم الإنجليزي *</label>
                        <input type="text" name="name_en" id="i-name-en" class="form-control" required placeholder="Beef Burger">
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">الفئة *</label>
                        <select name="category_id" id="i-cat" class="form-control" required>
                            <option value="">اختر الفئة...</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name_ar }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">رقم الصنف</label>
                        <input type="text" name="item_number" id="i-number" class="form-control" placeholder="مثال: A101">
                    </div>
                </div>

                <div class="form-group" id="price-group">
                    <label class="form-label">السعر (ريال) *</label>
                    <input type="number" name="price" id="i-price" class="form-control" step="0.01" min="0" placeholder="0.00">
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                        <input type="checkbox" name="has_sizes" id="i-has-sizes" onchange="toggleSizesUI()">
                        <span>الصنف يحتوي على أحجام متعددة (مثال: صغير، كبير)</span>
                    </label>
                </div>
                <div id="sizes-container" style="display:none; background:#f9f9f9; padding:15px; border-radius:12px; margin-bottom:15px; border:1px solid #ddd">
                    <div id="sizes-list"></div>
                    <button type="button" class="btn btn-sm btn-outline" onclick="addSizeRow()"><i class="fas fa-plus"></i> إضافة مقاس جديد</button>
                    <input type="hidden" name="sizes" id="sizes-json">
                </div>

                <div class="form-group">
                    <label style="display:flex; align-items:center; gap:8px; cursor:pointer">
                        <input type="checkbox" name="has_addons" id="i-has-addons" onchange="toggleAddonsUI()">
                        <span>الصنف يحتوي على إضافات اختيارية (مثال: إكسترا جبن)</span>
                    </label>
                </div>
                <div id="addons-container" style="display:none; background:#f9f9f9; padding:15px; border-radius:12px; margin-bottom:15px; border:1px solid #ddd">
                    <div id="addons-list"></div>
                    <button type="button" class="btn btn-sm btn-outline" onclick="addAddonRow()"><i class="fas fa-plus"></i> إضافة خيار جديد</button>
                    <input type="hidden" name="addons" id="addons-json">
                </div>

                <div class="form-group">
                    <label class="form-label">الوصف (عربي)</label>
                    <textarea name="description_ar" id="i-desc-ar" class="form-control" rows="2" placeholder="لحم بقري، خس، جبن..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">الوصف (إنجليزي)</label>
                    <textarea name="description_en" id="i-desc-en" class="form-control" rows="2" placeholder="Beef patty, lettuce..."></textarea>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">الصورة</label>
                        <input type="file" name="image" id="item-image" class="form-control" onchange="previewImage(this)">
                        <img id="img-preview" src="" style="display:none; width:100px; margin-top:10px; border-radius:8px; border:1px solid var(--border)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الترتيب</label>
                        <input type="number" name="sort_order" id="i-sort" class="form-control" value="0">
                        <div id="avail-group" style="display:none; margin-top:10px">
                            <label class="form-label">الحالة</label>
                            <select name="is_available" id="i-avail" class="form-control">
                                <option value="1">متاح</option>
                                <option value="0">غير متاح</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- ── Inventory Ingredients Section ── --}}
                <div style="border:1px solid var(--border);border-radius:12px;padding:14px;margin-bottom:10px;background:#fcfcfc">
                    <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleIngSection()">
                        <label style="cursor:pointer;font-weight:700;color:var(--primary)"><i class="fas fa-boxes"></i> مكونات الصنف (للمخزون) — <small style="color:var(--text-muted)">اختياري</small></label>
                        <i id="ing-toggle-icon" class="fas fa-chevron-down" style="color:var(--text-muted)"></i>
                    </div>
                    <div id="item-ings-section" style="display:none;margin-top:12px">
                        <div id="item-ings-list"></div>
                        <button type="button" class="btn btn-sm btn-success mt-8" onclick="addIngRow()">
                            <i class="fas fa-plus"></i> إضافة مكون
                        </button>
                        <input type="hidden" name="item_ingredients" id="ings-json">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline" onclick="closeItemModal()">إلغاء</button>
            <button class="btn btn-primary" onclick="submitForm()"><i class="fas fa-save"></i> حفظ</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
    let allItems = @json($items);
    let currentPage = 1;
    let currentLimit = 10;
    let sortAsc = false;

    function renderItems() {
        const catId = document.getElementById('filter-cat').value;
        const q = document.getElementById('search-input').value.toLowerCase();
        
        let items = allItems;
        if (catId) items = items.filter(i => i.category_id == catId);
        if (q) items = items.filter(i => i.name_ar.toLowerCase().includes(q) || i.name_en.toLowerCase().includes(q));

        document.getElementById('items-count').textContent = items.length + ' صنف';
        const tbody = document.getElementById('items-tbody');

        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:40px;color:var(--text-muted)"><i class="fas fa-utensils fa-2x"></i><p>لا توجد أصناف تطابق البحث</p></td></tr>';
            document.getElementById('pagination-container').innerHTML = '';
            return;
        }

        // Sort
        items.sort((a,b) => sortAsc ? a.id - b.id : b.id - a.id);

        // Paginate
        const totalPages = Math.ceil(items.length / currentLimit);
        if (currentPage > totalPages) currentPage = totalPages || 1;
        
        const start = (currentPage - 1) * currentLimit;
        const end = start + currentLimit;
        const paginated = items.slice(start, end);

        tbody.innerHTML = paginated.map((item, i) => `
            <tr>
                <td>${start + i + 1}</td>
                <td><span class="badge badge-secondary">${item.item_number || '-'}</span></td>
                <td>
                    ${item.image 
                        ? `<img src="/restaurant/uploads/${item.image}" style="width:50px;height:40px;object-fit:cover;border-radius:6px">`
                        : '<i class="fas fa-utensils" style="font-size:1.2rem; color:var(--text-muted)"></i>'}
                </td>
                <td><strong>${item.name_ar}</strong></td>
                <td>${item.name_en}</td>
                <td><small>${item.cat_name || 'بدون فئة'}</small></td>
                <td style="color:var(--primary); font-weight:700">
                    ${item.has_sizes ? '<span class="badge badge-info"><i class="fas fa-layer-group"></i> متعدد الأحجام</span>' : parseFloat(item.price).toFixed(2) + ' ريال'}
                </td>
                <td><span class="badge badge-${item.is_available ? 'success' : 'danger'}">${item.is_available ? 'متاح' : 'غير متاح'}</span></td>
                <td style="display:flex; gap:6px">
                    <button class="btn btn-info btn-sm" onclick="editItem(${JSON.stringify(item).replace(/"/g, '&quot;')})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-danger btn-sm" onclick="deleteItem(${item.id})"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        `).join('');

        renderPagination(items.length);
    }

    function renderPagination(totalItems) {
        const totalPages = Math.ceil(totalItems / currentLimit);
        const container = document.getElementById('pagination-container');
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';
        
        // Previous Arrow
        html += `<button class="btn btn-sm btn-outline" onclick="setPage(${Math.max(1, currentPage - 1)})" ${currentPage === 1 ? 'disabled' : ''} style="margin:0 2px"><i class="fas fa-chevron-right"></i></button>`;

        const maxVisible = 5;
        let start = Math.max(1, currentPage - 2);
        let end = Math.min(totalPages, start + maxVisible - 1);
        
        if (end - start < maxVisible - 1) {
            start = Math.max(1, end - maxVisible + 1);
        }

        if (start > 1) {
            html += `<button class="btn btn-sm btn-outline" onclick="setPage(1)">1</button>`;
            if (start > 2) html += `<span style="padding:0 5px">...</span>`;
        }

        for (let i = start; i <= end; i++) {
            html += `<button class="btn btn-sm ${i === currentPage ? 'btn-primary' : 'btn-outline'}" onclick="setPage(${i})" style="margin:0 2px">${i}</button>`;
        }

        if (end < totalPages) {
            if (end < totalPages - 1) html += `<span style="padding:0 5px">...</span>`;
            html += `<button class="btn btn-sm btn-outline" onclick="setPage(${totalPages})">${totalPages}</button>`;
        }

        // Next Arrow
        html += `<button class="btn btn-sm btn-outline" onclick="setPage(${Math.min(totalPages, currentPage + 1)})" ${currentPage === totalPages ? 'disabled' : ''} style="margin:0 2px"><i class="fas fa-chevron-left"></i></button>`;

        container.innerHTML = html;
    }

    function setPage(p) {
        currentPage = p;
        renderItems();
    }

    function toggleSort() {
        sortAsc = !sortAsc;
        document.getElementById('sort-icon').className = sortAsc ? 'fas fa-sort-numeric-up' : 'fas fa-sort-numeric-down';
        renderItems();
    }

    // Modal Logic
    function openItemModal() {
        document.getElementById('modal-title').textContent = 'إضافة صنف جديد';
        document.getElementById('form-method').value = 'POST';
        document.getElementById('item-form').action = "{{ route('admin.items.store') }}";
        document.getElementById('item-form').reset();
        document.getElementById('img-preview').style.display = 'none';
        document.getElementById('sizes-list').innerHTML = '';
        document.getElementById('addons-list').innerHTML = '';
        document.getElementById('item-ings-list').innerHTML = '';
        toggleSizesUI();
        toggleAddonsUI();
        document.getElementById('item-modal').classList.remove('hidden');
    }

    function closeItemModal() {
        document.getElementById('item-modal').classList.add('hidden');
    }

    function editItem(item) {
        document.getElementById('modal-title').textContent = 'تعديل الصنف';
        document.getElementById('form-method').value = 'PUT';
        document.getElementById('item-form').action = `/restaurant/laravel-app/public/admin/items/${item.id}`;
        
        document.getElementById('i-name-ar').value = item.name_ar;
        document.getElementById('i-name-en').value = item.name_en;
        document.getElementById('i-cat').value = item.category_id;
        document.getElementById('i-number').value = item.item_number || '';
        document.getElementById('i-price').value = item.price;
        document.getElementById('i-desc-ar').value = item.description_ar || '';
        document.getElementById('i-desc-en').value = item.description_en || '';
        document.getElementById('i-sort').value = item.sort_order;
        document.getElementById('i-avail').value = item.is_available ? '1' : '0';
        document.getElementById('avail-group').style.display = 'block';

        if (item.image) {
            let img = document.getElementById('img-preview');
            img.src = `/restaurant/uploads/${item.image}`;
            img.style.display = 'block';
        } else {
            document.getElementById('img-preview').style.display = 'none';
        }

        document.getElementById('i-has-sizes').checked = !!item.has_sizes;
        toggleSizesUI();
        document.getElementById('sizes-list').innerHTML = '';
        if (item.has_sizes && item.sizes) {
            const sizes = JSON.parse(item.sizes);
            sizes.forEach(s => addSizeRow(s.name_ar, s.name_en, s.price));
        }

        document.getElementById('i-has-addons').checked = !!item.has_addons;
        toggleAddonsUI();
        document.getElementById('addons-list').innerHTML = '';
        if (item.has_addons && item.addons) {
            const addons = JSON.parse(item.addons);
            addons.forEach(a => addAddonRow(a.name_ar, a.name_en, a.price));
        }

        // Load Ingredients via API
        document.getElementById('item-ings-list').innerHTML = '<div style="padding:10px;text-align:center"><i class="fas fa-spinner fa-spin"></i> جاري التحميل...</div>';
        fetch(`/restaurant/laravel-app/public/api/items/${item.id}/ingredients`)
            .then(r => r.json())
            .then(res => {
                document.getElementById('item-ings-list').innerHTML = '';
                if (res.success && res.data.length) {
                    res.data.forEach(ing => addIngRow(ing.ingredient_id, ing.size_name, ing.quantity_per_portion, ing.notes));
                    document.getElementById('item-ings-section').style.display = 'block';
                    document.getElementById('ing-toggle-icon').className = 'fas fa-chevron-up';
                }
            });

        document.getElementById('item-modal').classList.remove('hidden');
    }

    function toggleSizesUI() {
        const has = document.getElementById('i-has-sizes').checked;
        document.getElementById('sizes-container').style.display = has ? 'block' : 'none';
        document.getElementById('price-group').style.display = has ? 'none' : 'block';
        document.getElementById('i-price').required = !has;
    }

    function addSizeRow(nameAr='', nameEn='', price='') {
        const div = document.createElement('div');
        div.className = 'size-row';
        div.style.cssText = 'display:grid; grid-template-columns:1fr 1fr 100px 40px; gap:8px; margin-bottom:8px; align-items:center';
        div.innerHTML = `
            <input type="text" class="form-control s-name-ar" placeholder="عربي (كبير)" value="${nameAr}" required>
            <input type="text" class="form-control s-name-en" placeholder="English (Large)" value="${nameEn}" required>
            <input type="number" class="form-control s-price" placeholder="السعر" step="0.01" value="${price}" required>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        `;
        document.getElementById('sizes-list').appendChild(div);
    }

    function toggleAddonsUI() {
        const has = document.getElementById('i-has-addons').checked;
        document.getElementById('addons-container').style.display = has ? 'block' : 'none';
    }

    function addAddonRow(nameAr='', nameEn='', price='') {
        const div = document.createElement('div');
        div.className = 'addon-row';
        div.style.cssText = 'display:grid; grid-template-columns:1fr 1fr 100px 40px; gap:8px; margin-bottom:8px; align-items:center';
        div.innerHTML = `
            <input type="text" class="form-control a-name-ar" placeholder="عربي (إكسترا)" value="${nameAr}" required>
            <input type="text" class="form-control a-name-en" placeholder="English (Extra)" value="${nameEn}" required>
            <input type="number" class="form-control a-price" placeholder="السعر" step="0.01" value="${price}" required>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        `;
        document.getElementById('addons-list').appendChild(div);
    }

    function toggleIngSection() {
        const sec = document.getElementById('item-ings-section');
        const icon = document.getElementById('ing-toggle-icon');
        const isHidden = sec.style.display === 'none';
        sec.style.display = isHidden ? 'block' : 'none';
        icon.className = isHidden ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
    }

    function addIngRow(ingId='', sizeName='', qty='', notes='') {
        const div = document.createElement('div');
        div.className = 'ing-row';
        div.style.cssText = 'display:grid; grid-template-columns:1fr 1fr 80px 100px 40px; gap:8px; margin-bottom:8px; align-items:center';
        
        let options = '<option value="">-- اختر المكون --</option>';
        @foreach($ingredients as $ing)
        options += `<option value="{{ $ing->id }}" ${ingId == {{ $ing->id }} ? 'selected' : ''}>{{ $ing->name }} ({{ $ing->unit }})</option>`;
        @endforeach

        div.innerHTML = `
            <select class="form-control ing-id" required>${options}</select>
            <input type="text" class="form-control ing-size" placeholder="الحجم" value="${sizeName}">
            <input type="number" class="form-control ing-qty" placeholder="الكمية" step="0.001" value="${qty}" required>
            <input type="text" class="form-control ing-notes" placeholder="ملاحظات" value="${notes}">
            <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
        `;
        document.getElementById('item-ings-list').appendChild(div);
    }

    function submitForm() {
        const form = document.getElementById('item-form');
        
        // Collect sizes
        if (document.getElementById('i-has-sizes').checked) {
            const sizes = [];
            document.querySelectorAll('.size-row').forEach(row => {
                sizes.push({
                    name_ar: row.querySelector('.s-name-ar').value,
                    name_en: row.querySelector('.s-name-en').value,
                    price: row.querySelector('.s-price').value
                });
            });
            document.getElementById('sizes-json').value = JSON.stringify(sizes);
        }
        
        // Collect addons
        if (document.getElementById('i-has-addons').checked) {
            const addons = [];
            document.querySelectorAll('.addon-row').forEach(row => {
                addons.push({
                    name_ar: row.querySelector('.a-name-ar').value,
                    name_en: row.querySelector('.a-name-en').value,
                    price: row.querySelector('.a-price').value
                });
            });
            document.getElementById('addons-json').value = JSON.stringify(addons);
        }

        // Collect Ingredients
        const ings = [];
        document.querySelectorAll('.ing-row').forEach(row => {
            const id = row.querySelector('.ing-id').value;
            if (id) {
                ings.push({
                    ingredient_id: id,
                    size_name: row.querySelector('.ing-size').value,
                    quantity_per_portion: row.querySelector('.ing-qty').value,
                    notes: row.querySelector('.ing-notes').value
                });
            }
        });
        document.getElementById('ings-json').value = JSON.stringify(ings);

        form.submit();
    }

    function deleteItem(id) {
        if (confirm('هل أنت متأكد من حذف هذا الصنف؟')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/restaurant/laravel-app/public/admin/items/${id}`;
            form.innerHTML = `@csrf @method('DELETE')`;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                const img = document.getElementById('img-preview');
                img.src = e.target.result;
                img.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function exportItemsExcel() {
        const table = document.querySelector('table');
        const wb = XLSX.utils.table_to_book(table);
        XLSX.writeFile(wb, 'Restaurant_Items_' + new Date().toISOString().split('T')[0] + '.xlsx');
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        renderItems();
        document.getElementById('filter-cat').addEventListener('change', () => { currentPage = 1; renderItems(); });
        document.getElementById('search-input').addEventListener('keyup', () => { currentPage = 1; renderItems(); });
        document.getElementById('items-limit').addEventListener('change', (e) => {
            currentLimit = parseInt(e.target.value);
            currentPage = 1;
            renderItems();
        });
    });
</script>
@endpush
