<?php
require_once __DIR__ . '/_layout.php';
adminHeader('إدارة الأصناف', 'items');
?>

<!-- Filters -->
<div class="card mb-16">
  <div class="card-body" style="padding:14px 20px">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <select class="form-control" id="filter-cat" style="width:auto;min-width:180px">
        <option value="">كل الفئات</option>
      </select>
      <div class="search-box">
        <span class="search-icon"><i class="fas fa-search"></i></span>
        <input type="text" class="form-control" id="search-input" placeholder="بحث عن صنف...">
      </div>
      <button class="btn btn-danger" id="bulk-delete-btn" style="display:none"
        onclick="executeBulkDelete('/api/items.php', 'row-checkbox', 'loadAll')"><i class="fas fa-trash"></i> حذف
        المحدّد (<span id="selected-count">0</span>)</button>
      <button class="btn btn-primary" onclick="openItemModal()"><i class="fas fa-plus"></i> إضافة صنف</button>
      <a href="bulk_import.php" class="btn btn-success"><i class="fas fa-bolt"></i> إضافة سريعة بالجملة</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header" style="display:flex; justify-content:space-between; align-items:center">
    <div style="display:flex; align-items:center; gap:10px">
      <h3 style="margin:0"><i class="fas fa-utensils"></i> قائمة الأصناف</h3>
      <span id="items-count" class="badge badge-info"></span>
    </div>
    <button class="btn btn-success btn-sm" onclick="exportItemsExcel()" title="استخراج سريع للأصناف"><i class="fas fa-file-excel"></i> تصدير إكسل</button>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-controls" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:10px 15px 0;">
      <div style="font-size:0.9rem">
        عرض 
        <select class="form-control" style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.9rem" onchange="currentLimit=parseInt(this.value); currentPage=1; renderItems();">
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        أسطر
      </div>
    </div>
    <div class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th style="width:40px"><input type="checkbox" id="select-all" onclick="toggleSelectAll(this)"></th>
            <th onclick="toggleSort()" style="cursor:pointer; white-space:nowrap" title="تغيير الترتيب"># <i id="sort-icon" class="fas fa-sort-numeric-down"></i></th>
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
            <td colspan="8" style="text-align:center;padding:30px">
              <div class="spinner"></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div id="pagination-container"></div>
  </div>
</div>

<!-- Modal -->
<div class="modal-backdrop hidden" id="item-modal">
  <div class="modal" style="max-width:680px">
    <div class="modal-header">
      <h3 id="item-modal-title">إضافة صنف</h3>
      <button class="modal-close" onclick="closeItemModal()">✕</button>
    </div>
    <div class="modal-body">
      <form id="item-form" enctype="multipart/form-data">
        <input type="hidden" id="item-id">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">رقم الصنف</label>
            <input type="text" class="form-control" id="item-number" placeholder="مثال: A101">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">الاسم العربي *</label>
            <input type="text" class="form-control" id="item-name-ar" required placeholder="مثال: برجر لحم">
          </div>
          <div class="form-group">
            <label class="form-label">الاسم الإنجليزي *</label>
            <input type="text" class="form-control" id="item-name-en" required placeholder="Beef Burger">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">الفئة *</label>
            <select class="form-control" id="item-cat" required></select>
          </div>
          <div class="form-group">
            <label class="form-label">السعر (ريال) *</label>
            <input type="number" class="form-control" id="item-price" min="0" step="0.01" required placeholder="0.00">
          </div>
        </div>
        <div class="form-group mb-16">
          <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" id="item-has-sizes" onclick="toggleSizesUI()">
            الصنف يحتوي على أحجام متعددة (مثل: الصغير، الكبير)
          </label>
        </div>
        <div id="sizes-container"
          style="display:none; background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid #ddd">
          <h4 style="margin-top:0;font-size:0.9rem;margin-bottom:10px">قائمة الأحجام والمقاسات</h4>
          <div id="sizes-list"></div>
          <button type="button" class="btn btn-sm btn-secondary mt-12" onclick="addSizeRow()"><i
              class="fas fa-plus"></i> إضافة مقاس جديد</button>
        </div>
        <div class="form-group mb-16">
          <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer">
            <input type="checkbox" id="item-has-addons" onclick="toggleAddonsUI()">
            الصنف يحتوي على إضافات اختيارية (مثل: إكسترا جبن، صوص)
          </label>
        </div>
        <div id="addons-container"
          style="display:none; background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:15px; border:1px solid #ddd">
          <h4 style="margin-top:0;font-size:0.9rem;margin-bottom:10px">قائمة الإضافات الاختيارية</h4>
          <div id="addons-list"></div>
          <button type="button" class="btn btn-sm btn-secondary mt-12" onclick="addAddonRow()"><i
              class="fas fa-plus"></i> إضافة خيار جديد</button>
        </div>
        <div class="form-group">
          <label class="form-label">المكونات / الوصف (عربي)</label>
          <textarea class="form-control" id="item-desc-ar" rows="2"
            placeholder="لحم بقري، خس، طماطم، جبن..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">المكونات / الوصف (إنجليزي)</label>
          <textarea class="form-control" id="item-desc-en" rows="2"
            placeholder="Beef patty, lettuce, tomato, cheese..."></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">الصورة</label>
            <input type="file" class="form-control" id="item-image" accept="image/*" onchange="previewImage(this)">
            <img id="img-preview" class="img-preview" alt="معاينة الصورة">
          </div>
          <div class="form-group">
            <label class="form-label">الترتيب</label>
            <input type="number" class="form-control" id="item-sort" value="0" min="0">
            <div class="form-group mt-12" id="avail-group" style="display:none">
              <label class="form-label">الحالة</label>
              <select class="form-control" id="item-avail">
                <option value="1">متاح</option>
                <option value="0">غير متاح</option>
              </select>
            </div>
          </div>
        </div>

        <!-- ── Inventory Ingredients (Optional) ──────────────────────────────── -->
        <div style="border:1px solid var(--border);border-radius:10px;padding:14px;margin-bottom:10px;background:var(--bg-body)">
          <div style="display:flex;justify-content:space-between;align-items:center;cursor:pointer" onclick="toggleIngSection()">
            <label style="cursor:pointer;font-weight:700;color:var(--primary)"><i class="fas fa-boxes"></i> مكونات الصنف (للمخزون) — <small style="color:var(--text-muted)">اختياري</small></label>
            <i id="ing-toggle-icon" class="fas fa-chevron-down" style="color:var(--text-muted)"></i>
          </div>
          <div id="item-ings-section" style="display:none;margin-top:12px">
            <div id="item-ings-list"></div>
            <button type="button" class="btn btn-sm btn-success mt-8" onclick="addIngRow()">
              <i class="fas fa-plus"></i> إضافة مكون
            </button>
            <p style="font-size:.8rem;color:var(--text-muted);margin-top:8px"><i class="fas fa-info-circle"></i> الكميات بوحدة المكون المحددة مسبقاً</p>
          </div>
        </div>

      </form>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" onclick="closeItemModal()">إلغاء</button>
      <button type="button" class="btn btn-primary" onclick="saveItem()"><i class="fas fa-save"></i> حفظ</button>
    </div>
  </div>
</div>

<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
<script>
  let allItems = [], allCats = [];
  let currentPage = 1;
  let currentLimit = 10;
  let sortAsc = false;

  function toggleSort() {
    sortAsc = !sortAsc;
    document.getElementById('sort-icon').className = sortAsc ? 'fas fa-sort-numeric-up' : 'fas fa-sort-numeric-down';
    renderItems();
  }

  async function loadAll(deletedIds = null) {
    if (deletedIds && Array.isArray(deletedIds) && deletedIds.length) {
      allItems = allItems.filter(i => !deletedIds.includes(i.id.toString()));
      renderItems();
      return;
    }
    const [iRes, cRes] = await Promise.all([
      apiCall('/api/items.php?all=1'),
      apiCall('/api/categories.php?all=1')
    ]);

    if (cRes.success) {
      allCats = cRes.data;
      const fCat = document.getElementById('filter-cat');
      const iCat = document.getElementById('item-cat');

      // Only populate fCat if it's empty to preserve current selection during reloads
      if (fCat.options.length <= 1) {
        fCat.innerHTML = '<option value="">كل الفئات</option>' + allCats.map(c => `<option value="${c.id}">${c.name_ar}</option>`).join('');
      }
      iCat.innerHTML = '<option value="">اختر الفئة</option>' + allCats.map(c => `<option value="${c.id}">${c.name_ar}</option>`).join('');
    }

    if (iRes.success) {
      allItems = iRes.data;
      renderItems();
    }
  }

  function renderItems() {
    const catId = document.getElementById('filter-cat').value;
    const q = document.getElementById('search-input').value.toLowerCase();
    let items = allItems;
    if (catId) items = items.filter(i => i.category_id == catId);
    if (q) items = items.filter(i => i.name_ar.includes(q) || i.name_en.toLowerCase().includes(q));

    document.getElementById('items-count').textContent = items.length + ' صنف';
    const tbody = document.getElementById('items-tbody');

    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="9"><div class="empty-state"><span class="icon"><i class="fas fa-utensils"></i></span><p>لا توجد أصناف</p></div></td></tr>';
      document.getElementById('pagination-container').innerHTML = '';
      return;
    }

    items.sort((a,b) => sortAsc ? a.id - b.id : b.id - a.id);

    const paginatedItems = paginateData(items, currentPage, currentLimit);

    // Uncheck select-all when page changes
    const selectAllBtn = document.getElementById('select-all');
    if (selectAllBtn) selectAllBtn.checked = false;
    updateBulkDeleteBtn();

    tbody.innerHTML = paginatedItems.map((item, i) => `
    <tr>
      <td><input type="checkbox" class="row-checkbox" value="${item.id}" onclick="updateBulkDeleteBtn()"></td>
      <td>${(currentPage - 1) * currentLimit + i + 1}</td>
      <td><span class="badge badge-secondary">${item.item_number || '-'}</span></td>
      <td>${item.image
        ? `<img src="<?= BASE_PATH ?>uploads/${item.image}" style="width:50px;height:40px;object-fit:cover;border-radius:6px">`
        : '<span style="font-size:1.2rem"><i class="fas fa-utensils"></i></span>'}</td>
      <td><strong>${item.name_ar}</strong></td>
      <td>${item.name_en}</td>
      <td><small>${item.cat_name_ar}</small></td>
      <td style="color:var(--primary);font-weight:700">
        ${item.has_sizes == 1 ? '<span class="badge badge-info"><i class="fas fa-layer-group"></i> متعدد الأحجام</span>' : parseFloat(item.price).toFixed(2) + ' ريال'}
      </td>
      <td><span class="badge ${item.is_available == '1' ? 'badge-confirmed' : 'badge-cancelled'}">${item.is_available == '1' ? 'متاح' : 'غير متاح'}</span></td>
      <td>
        <button class="btn btn-warning btn-sm" onclick="editItem(${item.id})"><i class="fas fa-edit"></i></button>
        <button class="btn btn-danger btn-sm" onclick="deleteItem(${item.id},'${item.name_ar}')"><i class="fas fa-trash"></i></button>
      </td>
    </tr>
  `).join('');

    renderPagination(items.length, currentLimit, currentPage, 'pagination-container', 'setPage');
  }

  function setPage(p) {
    currentPage = p;
    renderItems();
  }

  function openItemModal(isEdit = false) {
    document.getElementById('item-modal-title').textContent = isEdit ? 'تعديل صنف' : 'إضافة صنف';
    document.getElementById('avail-group').style.display = isEdit ? 'block' : 'none';
    document.getElementById('item-modal').classList.remove('hidden');
  }

  function closeItemModal() {
    document.getElementById('item-modal').classList.add('hidden');
    document.getElementById('item-id').value = '';
    document.getElementById('item-form').reset();
    document.getElementById('img-preview').className = 'img-preview';
    document.getElementById('sizes-list').innerHTML = '';
    document.getElementById('item-has-sizes').checked = false;
    toggleSizesUI();
    document.getElementById('addons-list').innerHTML = '';
    document.getElementById('item-has-addons').checked = false;
    toggleAddonsUI();
    // Reset ingredients section
    document.getElementById('item-ings-list').innerHTML = '';
    document.getElementById('item-ings-section').style.display = 'none';
    document.getElementById('ing-toggle-icon').className = 'fas fa-chevron-down';
  }

  function toggleAddonsUI() {
    const hasAddons = document.getElementById('item-has-addons').checked;
    document.getElementById('addons-container').style.display = hasAddons ? 'block' : 'none';
  }

  function addAddonRow(name_ar = '', name_en = '', price = '') {
    const list = document.getElementById('addons-list');
    const div = document.createElement('div');
    div.className = 'addon-row';
    div.style.cssText = 'display:flex;gap:10px;margin-bottom:8px;align-items:center';
    div.innerHTML = `
    <input type="text" class="form-control addon-ar" placeholder="عربي (إكسترا جبن)" value="${name_ar}" required>
    <input type="text" class="form-control addon-en" placeholder="إنجليزي (Extra Cheese)" value="${name_en}" required>
    <input type="number" step="0.01" class="form-control addon-price" placeholder="السعر" value="${price}" required style="width:100px">
    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()" style="padding:6px 12px"><i class="fas fa-times"></i></button>
  `;
    list.appendChild(div);
  }

  function toggleSizesUI() {
    const hasSizes = document.getElementById('item-has-sizes').checked;
    document.getElementById('sizes-container').style.display = hasSizes ? 'block' : 'none';
    const priceGroup = document.getElementById('item-price').parentElement;
    if (hasSizes) {
      priceGroup.style.opacity = '0.5';
      document.getElementById('item-price').required = false;
    } else {
      priceGroup.style.opacity = '1';
      document.getElementById('item-price').required = true;
    }
  }

  function addSizeRow(name_ar = '', name_en = '', price = '') {
    const list = document.getElementById('sizes-list');
    const div = document.createElement('div');
    div.className = 'size-row';
    div.style.cssText = 'display:flex;gap:10px;margin-bottom:8px;align-items:center';
    div.innerHTML = `
    <input type="text" class="form-control size-ar" placeholder="عربي (كبير)" value="${name_ar}" required>
    <input type="text" class="form-control size-en" placeholder="إنجليزي (Large)" value="${name_en}" required>
    <input type="number" step="0.01" class="form-control size-price" placeholder="السعر" value="${price}" required style="width:100px">
    <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()" style="padding:6px 12px"><i class="fas fa-times"></i></button>
  `;
    list.appendChild(div);
  }

  function editItem(id) {
    const item = allItems.find(i => i.id == id);
    if (!item) return;
    document.getElementById('item-id').value = item.id;
    document.getElementById('item-name-ar').value = item.name_ar;
    document.getElementById('item-name-en').value = item.name_en;
    document.getElementById('item-number').value = item.item_number || '';
    document.getElementById('item-cat').value = item.category_id;
    document.getElementById('item-price').value = item.price;
    document.getElementById('item-desc-ar').value = item.description_ar || '';
    document.getElementById('item-desc-en').value = item.description_en || '';
    document.getElementById('item-sort').value = item.sort_order;
    document.getElementById('item-avail').value = item.is_available;
    const hasSizes = item.has_sizes == 1;
    document.getElementById('item-has-sizes').checked = hasSizes;
    toggleSizesUI();
    document.getElementById('sizes-list').innerHTML = '';
    if (hasSizes && item.sizes) {
      try {
        const sizesArr = typeof item.sizes === 'string' ? JSON.parse(item.sizes) : item.sizes;
        sizesArr.forEach(s => addSizeRow(s.name_ar, s.name_en, s.price));
      } catch (e) { }
    }

    const hasAddons = item.has_addons == 1;
    document.getElementById('item-has-addons').checked = hasAddons;
    toggleAddonsUI();
    document.getElementById('addons-list').innerHTML = '';
    if (hasAddons && item.addons) {
      try {
        const addonsArr = typeof item.addons === 'string' ? JSON.parse(item.addons) : item.addons;
        addonsArr.forEach(a => addAddonRow(a.name_ar, a.name_en, a.price));
      } catch (e) { }
    }

    if (item.image) {
      const prev = document.getElementById('img-preview');
      const basePath = window.POS_BASE_PATH || '/';
      prev.src = (`${basePath}/uploads/` + item.image).replace(/\/+/g, '/');
      prev.className = 'img-preview show';
    }
    openItemModal(true);
    // Load existing ingredients for this item
    window.loadItemIngredients(item.id);
  }

  async function saveItem() {
    const id = document.getElementById('item-id').value;
    const fd = new FormData();
    if (id) { fd.append('id', id); }

    const hasSizes = document.getElementById('item-has-sizes').checked;
    let price = document.getElementById('item-price').value || 0;

    fd.append('has_sizes', hasSizes ? 1 : 0);
    if (hasSizes) {
      const rows = document.querySelectorAll('.size-row');
      const sizes = [];
      rows.forEach(r => {
        sizes.push({
          name_ar: r.querySelector('.size-ar').value.trim(),
          name_en: r.querySelector('.size-en').value.trim(),
          price: parseFloat(r.querySelector('.size-price').value) || 0
        });
      });
      if (sizes.length === 0) {
        showToast('يرجى إضافة مقاس واحد على الأقل', 'danger');
        return;
      }
      if (sizes.some(s => !s.name_ar || !s.name_en)) {
        showToast('يرجى تعبئة أسماء المقاسات', 'danger');
        return;
      }
      fd.append('sizes', JSON.stringify(sizes));
      price = sizes[0].price; // Fallback price for items array consistency
    }

    const hasAddons = document.getElementById('item-has-addons').checked;
    fd.append('has_addons', hasAddons ? 1 : 0);
    if (hasAddons) {
      const aRows = document.querySelectorAll('.addon-row');
      const addons = [];
      aRows.forEach(r => {
        addons.push({
          name_ar: r.querySelector('.addon-ar').value.trim(),
          name_en: r.querySelector('.addon-en').value.trim(),
          price: parseFloat(r.querySelector('.addon-price').value) || 0
        });
      });
      fd.append('addons', JSON.stringify(addons));
    }

    fd.append('category_id', document.getElementById('item-cat').value);
    fd.append('name_ar', document.getElementById('item-name-ar').value);
    fd.append('name_en', document.getElementById('item-name-en').value);
    fd.append('item_number', document.getElementById('item-number').value);
    fd.append('price', price);
    fd.append('description_ar', document.getElementById('item-desc-ar').value);
    fd.append('description_en', document.getElementById('item-desc-en').value);
    fd.append('sort_order', document.getElementById('item-sort').value);
    if (id) fd.append('is_available', document.getElementById('item-avail').value);
    const imgFile = document.getElementById('item-image').files[0];
    if (imgFile) fd.append('image', imgFile);

    const url = id ? '/api/items.php?action=update' : '/api/items.php';
    const data = await apiCall(url, 'POST', fd);
    showToast(data.message, data.success ? 'success' : 'danger');
    if (data.success) {
      // Save ingredient links if any
      const savedItemId = data.data?.id || id;
      if (savedItemId) await window.saveItemIngredients(savedItemId);
      closeItemModal();
      loadAll();
    }
  }

  async function deleteItem(id, name) {
    if (!confirmAction(`هل أنت متأكد من حذف الصنف '${name}'؟`)) return;
    const res = await apiCall('/api/items.php?action=delete', 'POST', { id: id });
    showToast(res.message, res.success ? 'success' : 'danger');
    if (res.success) loadAll([id.toString()]);
  }

  function previewImage(input) {
    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = e => { const img = document.getElementById('img-preview'); img.src = e.target.result; img.className = 'img-preview show'; };
      reader.readAsDataURL(input.files[0]);
    }
  }

  document.addEventListener('DOMContentLoaded', async () => {
    await loadAll();                  // items + categories first
    window.loadAllIngredients();      // ingredients after (no session conflict)
  });
  document.getElementById('filter-cat').addEventListener('change', () => { currentPage = 1; renderItems(); });
  document.getElementById('search-input').addEventListener('input', () => { currentPage = 1; renderItems(); });
  document.getElementById('item-modal').addEventListener('click', e => { if (e.target.id === 'item-modal') closeItemModal(); });
  document.getElementById('item-form').addEventListener('submit', e => { e.preventDefault(); saveItem(); });
  onSSE('menu_updated', loadAll);

</script>

<!-- ── Ingredient helpers — defined globally to work with onclick ── -->
<script>
  window._allIngs = [];
  window._unitLabels = {gram:'جرام', kg:'كيلو', piece:'حبة', liter:'لتر', ml:'مل', cup:'كوب', tablespoon:'ملعقة', other:'أخرى'};

  window.loadAllIngredients = async function() {
    try {
      const res = await apiCall('/api/ingredients.php');
      if (res && res.success) window._allIngs = res.data || [];
    } catch(e) { window._allIngs = []; }
  };

  window.toggleIngSection = function() {
    const sec  = document.getElementById('item-ings-section');
    const icon = document.getElementById('ing-toggle-icon');
    if (!sec) return;
    const open = sec.style.display === 'none';
    sec.style.display = open ? 'block' : 'none';
    if (icon) icon.className = open ? 'fas fa-chevron-up' : 'fas fa-chevron-down';
  };

  window.populateSizeOptions = function(sel, currentVal) {
    const sizes = Array.from(document.querySelectorAll('.size-ar')).map(function(i){ return i.value.trim(); }).filter(Boolean);
    let opts = '<option value="">لكل الأحجام / أساسي</option>';
    let found = false;
    sizes.forEach(function(sz) {
      opts += '<option value="' + sz + '">' + sz + '</option>';
      if (sz === currentVal) found = true;
    });
    if (currentVal && !found) {
      opts += '<option value="' + currentVal + '">' + currentVal + ' (حجم محذوف/معدل)</option>';
    }
    sel.innerHTML = opts;
    sel.value = currentVal || '';
  };

  window.addIngRow = function(ingId, qty, note, sizeName) {
    ingId = ingId || '';
    qty   = qty   || '';
    note  = note  || '';
    sizeName = sizeName || '';
    const list = document.getElementById('item-ings-list');
    if (!list) { alert('خطأ: العنصر item-ings-list غير موجود'); return; }

    const opts = (window._allIngs || []).map(function(i) {
      return '<option value="' + i.id + '" data-unit="' + i.unit + '" ' + (i.id == ingId ? 'selected' : '') + '>'
           + (i.ingredient_number ? '[' + i.ingredient_number + '] ' : '') + i.name + '</option>';
    }).join('');

    const selIng  = (window._allIngs || []).find(function(i) { return i.id == ingId; });
    const unitLbl = selIng ? (window._unitLabels[selIng.unit] || selIng.unit) : '';

    const div = document.createElement('div');
    div.className = 'ing-item-row';
    div.style.cssText = 'display:flex;gap:8px;margin-bottom:8px;align-items:center;flex-wrap:wrap';
    div.innerHTML =
      '<select class="form-control ing-id-sel" style="flex:2;min-width:160px" onchange="window.updateIngUnit(this)">'
      + '<option value="">اختر المكون...</option>' + opts + '</select>'
      + '<span class="ing-unit-lbl badge badge-info" style="min-width:44px;text-align:center">' + (unitLbl||'—') + '</span>'
      + '<input type="number" class="form-control ing-qty-inp" min="0" step="0.001" value="' + qty + '" placeholder="الكمية" style="width:90px">'
      + '<select class="form-control ing-size-inp" onfocus="window.populateSizeOptions(this, this.value)" style="width:120px"></select>'
      + '<input type="text" class="form-control ing-note-inp" value="' + note + '" placeholder="ملاحظة" style="width:90px">'
      + '<button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>';
    list.appendChild(div);
    
    // Initial fetch of sizes for this newly added row
    const sizeSel = div.querySelector('.ing-size-inp');
    if (sizeSel) window.populateSizeOptions(sizeSel, sizeName);
  };

  window.updateIngUnit = function(sel) {
    const unit = (sel.options[sel.selectedIndex] && sel.options[sel.selectedIndex].dataset.unit) || '';
    const lbl  = sel.closest('.ing-item-row') && sel.closest('.ing-item-row').querySelector('.ing-unit-lbl');
    if (lbl) lbl.textContent = window._unitLabels[unit] || unit || '—';
  };

  window.loadItemIngredients = async function(itemId) {
    if (!itemId) return;
    const res = await apiCall('/api/ingredients.php?action=for_item&item_id=' + itemId);
    if (!res || !res.success || !res.data || !res.data.length) return;
    document.getElementById('item-ings-section').style.display = 'block';
    const icon = document.getElementById('ing-toggle-icon');
    if (icon) icon.className = 'fas fa-chevron-up';
    res.data.forEach(function(r) { window.addIngRow(r.ingredient_id, r.quantity_per_portion, r.notes||'', r.size_name||''); });
  };

  window.saveItemIngredients = async function(itemId) {
    const rows = document.querySelectorAll('.ing-item-row');
    const ingredients = [];
    rows.forEach(function(r) {
      const ingId = r.querySelector('.ing-id-sel') && r.querySelector('.ing-id-sel').value;
      const qty   = parseFloat(r.querySelector('.ing-qty-inp') && r.querySelector('.ing-qty-inp').value) || 0;
      const sizeVal = (r.querySelector('.ing-size-inp') && r.querySelector('.ing-size-inp').value) || '';
      if (ingId && qty > 0) ingredients.push({ingredient_id:parseInt(ingId), quantity_per_portion:qty, notes:note.trim(), size_name:sizeVal.trim()});
    });
    await apiCall('/api/ingredients.php?action=save_item_ingredients','POST',{item_id:parseInt(itemId), ingredients:ingredients});
  };
</script>

<script>
  function exportItemsExcel() {
    if (!allItems.length) {
      showToast('لا توجد أصناف للتصدير', 'warning');
      return;
    }

    let excelData = [];
    allItems.forEach(item => {
      let catName = allCats.find(c => c.id == item.category_id)?.name_ar || '';
      
      if (item.has_sizes == 1 && item.sizes) {
        try {
          const sizesArr = typeof item.sizes === 'string' ? JSON.parse(item.sizes) : item.sizes;
          sizesArr.forEach(s => {
            excelData.push({
              'رقم الصنف': item.item_number || '',
              'الاسم العربي': `${item.name_ar} مباع بـ (${s.name_ar})`,
              'الاسم الإنجليزي': `${item.name_en} (${s.name_en})`,
              'الفئة': catName,
              'السعر': parseFloat(s.price).toFixed(2),
              'الحالة': item.is_available == 1 ? 'متاح' : 'غير متاح',
            });
          });
        } catch (e) {
          excelData.push({
            'رقم الصنف': item.item_number || '',
            'الاسم العربي': item.name_ar,
            'الاسم الإنجليزي': item.name_en,
            'الفئة': catName,
            'السعر': parseFloat(item.price).toFixed(2),
            'الحالة': item.is_available == 1 ? 'متاح' : 'غير متاح',
          });
        }
      } else {
        excelData.push({
          'رقم الصنف': item.item_number || '',
          'الاسم العربي': item.name_ar,
          'الاسم الإنجليزي': item.name_en,
          'الفئة': catName,
          'السعر': parseFloat(item.price).toFixed(2),
          'الحالة': item.is_available == 1 ? 'متاح' : 'غير متاح',
        });
      }
    });

    const ws = XLSX.utils.json_to_sheet(excelData);
    const keys = Object.keys(excelData[0] || {});
    const cols = keys.map(k => ({ wch: Math.max(k.length, 15) }));
    ws['!cols'] = cols;

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'قائمة الأصناف');
    XLSX.writeFile(wb, 'Items_Export_' + new Date().toISOString().split('T')[0] + '.xlsx');
  }
</script>

<?php adminFooter(); ?>