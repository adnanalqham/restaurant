<?php
require_once __DIR__ . '/_layout.php';
waiterHeader('طلب جديد', 'new_order');
?>
<style>
  .waiter-content-grid {
    display: flex;
    flex: 1;
    height: 100%;
    overflow: hidden;
  }

  .menu-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
    background: var(--bg);
    border-right: 1px solid var(--border);
    overflow: hidden;
  }

  .cart-area {
    width: 360px;
    background: var(--bg-card);
    display: flex;
    flex-direction: column;
    box-shadow: -2px 0 15px rgba(0, 0, 0, 0.08);
    height: 100%;
    overflow: hidden;
    position: relative;
    z-index: 10;
  }

  /* Desktop Menu Scroll */
  .menu-items-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
  }

  /* Cart List Scroll */
  .order-items-list {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    padding: 10px;
  }

  /* Sticky Header for List */
  .order-sidebar-header {
    background: var(--secondary);
    color: #fff;
    padding: 15px;
    font-weight: 700;
    flex-shrink: 0;
  }

  /* Pinned Summary Area */
  .order-footer {
    flex-shrink: 0;
    background: var(--bg-card);
    border-bottom: 2px solid var(--border);
    padding: 15px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    order: -1; /* Keep it at the top below header */
  }

  .menu-top-actions {
    padding: 12px 16px;
    background: var(--bg-card);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 12px;
  }

  /* Info Button — visible on all screens */
  .info-btn {
    width: 26px;
    height: 26px;
    background: rgba(0,0,0,0.06);
    color: #888;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
    border: 1px solid rgba(0,0,0,0.1);
    font-size: 0.8rem;
    flex-shrink: 0;
    z-index: 6;
  }
  .info-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }

  /* Stock badge inside item card */
  .stock-qty-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: .72rem;
    font-weight: 700;
    margin-top: 5px;
    border: 1px solid currentColor;
    border-radius: 6px;
    padding: 1px 7px;
    opacity: 0.9;
  }

  /* Combo Badge Style */
  .badge-combo { background: linear-gradient(135deg, #ffd700, #ffcc00); color: #000; font-weight: 700; }

  @media (max-width: 992px) {
    .waiter-content-grid { flex-direction: column; overflow-y: auto; }
    .menu-area { flex: none; height: auto; overflow: visible; }
    .cart-area { width: 100%; height: auto; flex: none; border-right: none; border-top: 1px solid var(--border); overflow: visible; }
    
    .order-footer {
      position: fixed;
      bottom: 0; left: 0; right: 0;
      z-index: 1000;
      border-top: 2px solid var(--primary);
      border-bottom: none;
      box-shadow: 0 -5px 20px rgba(0,0,0,0.15);
      padding: 12px;
      order: 100;
      background: #fff;
    }
    .menu-item-qty { position: absolute; top: -10px; right: -10px; background: var(--primary); color: #fff; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; font-weight: 700; border: 3px solid #f8f9fa; z-index: 10; }
  }
</style>

<div class="waiter-content-grid">
  <!-- Right (Main): Menu Section -->
  <div class="menu-area">
    <div class="menu-top-actions">
      <div class="search-box" style="flex:1;max-width:300px">
        <span class="search-icon"><i class="fas fa-search"></i></span>
        <input type="text" id="menu-search" class="form-control" placeholder="البحث في المنيو...">
      </div>
      <div id="cat-tabs" class="cat-tabs" style="margin: 0; padding: 0; border: none">
        <button class="cat-tab active" data-id="all" onclick="filterCat('all', this)"><i class="fas fa-globe"></i>
          الكل</button>
      </div>
    </div>

    <div class="menu-items-scroll">
      <div class="menu-grid" id="menu-grid">
        <div class="spinner" style="grid-column:1/-1"></div>
      </div>
    </div>
  </div>

  <!-- Left: Order Sidebar (Cart) -->
  <!-- Left: Order Sidebar (Cart) -->
  <aside class="cart-area">
    <div id="append-notice" style="display:none; background:var(--warning); color:var(--text); padding:10px 15px; font-size:0.85rem; font-weight:700; border-bottom:1px solid rgba(0,0,0,0.05)">
      <i class="fas fa-plus-circle"></i> إضافة أصناف للطلب <span id="append-order-num"></span>
      <div id="append-table-info" style="font-weight:400; font-size:0.75rem; margin-top:2px"></div>
    </div>
    <div class="order-sidebar-header">
      <i class="fas fa-shopping-basket"></i> <span id="sidebar-title">السلّة الحالية</span>
    </div>

    <!-- Pinned Summary & Actions -->
    <div class="order-footer">
      <div class="order-total-row grand" style="margin-bottom:12px">
        <span>الإجمالي:</span>
        <span id="cart-subtotal">0.00 ريال</span>
      </div>

      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px">
        <div class="form-group" id="table-number-group" style="margin:0">
          <label class="form-label" style="font-size:0.75rem">رقم الطاولة <span style="color:var(--danger)">*</span></label>
          <input type="number" inputmode="numeric" pattern="[0-9]*" id="table-number" class="form-control" placeholder="مثال: 5" style="padding:8px 10px; font-size:1rem">
        </div>
        <div class="form-group" id="pay-method-group" style="margin:0">
          <label class="form-label" style="font-size:0.75rem">طريقة الدفع</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
            <label class="pay-opt-label" id="pay-cash-label" style="cursor:pointer;padding:8px 4px;border:2px solid var(--primary);border-radius:10px;text-align:center;font-size:0.75rem;background:rgba(191,166,61,0.08);display:flex;flex-direction:column;align-items:center;gap:2px">
              <input type="radio" name="pay-method" id="pay-cash" value="cash" checked onchange="togglePayMethod()" style="display:none">
              <i class="fas fa-money-bill-wave" style="font-size:1rem"></i> نقد
            </label>
            <label class="pay-opt-label" id="pay-wallet-label" style="cursor:pointer;padding:8px 4px;border:2px solid var(--border);border-radius:10px;text-align:center;font-size:0.75rem;display:flex;flex-direction:column;align-items:center;gap:2px">
              <input type="radio" name="pay-method" id="pay-wallet" value="wallet" onchange="togglePayMethod()" style="display:none">
              <i class="fas fa-wallet" style="font-size:1rem"></i> محفظة
            </label>
          </div>
        </div>
      </div>

      <!-- Wallet selection (hidden by default) -->
      <div class="form-group" id="wallet-selector" style="display:none;margin-bottom:12px">
        <select class="form-control" id="wallet-select" style="padding:8px 10px; font-size:0.9rem">
          <option value="">-- اختر المحفظة --</option>
        </select>
        <div id="wallet-info" style="margin-top:5px;padding:8px;background:var(--bg);border-radius:8px;font-size:0.8rem;display:none;border:1px solid var(--border)">
          <i class="fas fa-info-circle" style="color:var(--primary)"></i> <strong id="wallet-info-name"></strong> — <code id="wallet-info-num"></code>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:14px">
        <textarea id="order-notes" class="form-control" rows="1" placeholder="ملاحظات الطلب (إختياري)..." style="padding:8px 12px; font-size:0.9rem; resize:none"></textarea>
      </div>

      <!-- Cashier Assignment (REQUIRED) -->
      <div class="form-group" id="cashier-assignment-group" style="margin-bottom:12px; background: rgba(191,166,61,0.07); border-radius:10px; padding:10px; border:1.5px solid var(--primary)">
        <label class="form-label" style="font-size:0.8rem; color:var(--primary); font-weight:700">
          <i class="fas fa-cash-register"></i> الكاشير المسؤول <span style="color:var(--danger)">*</span>
        </label>
        <div id="cashier-options" style="display:grid; grid-template-columns: 1fr 1fr; gap:6px;">
          <div class="spinner" style="grid-column:1/-1;height:24px"></div>
        </div>
        <div id="no-cashiers-msg" style="display:none;margin-top:6px;font-size:0.8rem;color:var(--danger)">
          <i class="fas fa-exclamation-triangle"></i> لا يوجد كاشير نشط حالياً
        </div>
      </div>

      <div style="display:flex;gap:8px;align-items:center">
        <button class="btn btn-primary btn-block btn-lg" onclick="sendToCashier()" style="flex:1;padding:12px;font-size:1.1rem;border-radius:12px">
          <i class="fas fa-paper-plane"></i> إرسال الطلب
        </button>
        <button onclick="clearCart()" title="مسح السلة" style="flex-shrink:0;width:46px;height:46px;border-radius:12px;border:2px solid var(--danger);background:rgba(231,76,60,0.08);color:var(--danger);font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .2s" onmouseover="this.style.background='var(--danger)';this.style.color='#fff'" onmouseout="this.style.background='rgba(231,76,60,0.08)';this.style.color='var(--danger)'">
          <i class="fas fa-trash-alt"></i>
        </button>
      </div>
    </div>

    <!-- Scrollable Items List -->
    <div class="order-items-list" id="cart-list">
      <div class="empty-state" style="padding:40px 20px">
        <span class="icon"><i class="fas fa-cart-plus"></i></span>
        <p>اختر الأصناف للبدء</p>
      </div>
    </div>

    <!-- Bottom Action (Fixed) -->
    <div style="padding: 12px; border-top: 1px solid var(--border); background: #fdfdfd; flex-shrink:0">
      <button class="btn btn-outline btn-danger btn-block btn-sm" onclick="clearCart()" style="border-radius:10px">
        <i class="fas fa-trash-alt"></i> مسح السلة
      </button>
    </div>
  </aside>
</div>


<!-- Item Notes Modal -->
<div class="modal-backdrop hidden" id="item-note-modal">
  <div class="modal" style="max-width:400px">
    <div class="modal-header">
      <h3>📝 ملاحظة للصنف</h3>
      <button class="modal-close" onclick="closeNoteModal()">✕</button>
    </div>
    <div class="modal-body">
      <p id="note-item-name" style="font-weight:700;margin-bottom:12px"></p>
      <textarea id="note-input" class="form-control" rows="3" placeholder="مثال: بدون بصل، حار جداً..."></textarea>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeNoteModal()">إلغاء</button>
      <button class="btn btn-primary" onclick="saveItemNote()">✅ تأكيد</button>
    </div>
  </div>
</div>

<!-- Options Selection Modal -->
<div class="modal-backdrop hidden" id="options-modal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h3 id="options-modal-title">تخصيص الصنف</h3>
      <button class="modal-close" onclick="closeOptionsModal()">✕</button>
    </div>
    <div class="modal-body" style="max-height:65vh;overflow-y:auto;padding-bottom:0">
      <div id="options-sizes-section" style="display:none;margin-bottom:20px">
        <h4 style="margin-bottom:10px;border-bottom:1px solid #eee;padding-bottom:5px">اختيار الحجم <span
            style="color:var(--danger)">*</span></h4>
        <div id="options-sizes-list" style="display:flex;flex-direction:column;gap:8px"></div>
      </div>
      <div id="options-addons-section" style="display:none;margin-bottom:20px">
        <h4 style="margin-bottom:10px;border-bottom:1px solid #eee;padding-bottom:5px">إضافات اختيارية</h4>
        <div id="options-addons-list" style="display:flex;flex-direction:column;gap:8px"></div>
      </div>
    </div>
    <div class="modal-footer" style="padding-top:10px;border-top:1px solid #eee;margin-top:10px;display:flex;justify-content:space-between;align-items:center">
      <div>السعر: <span style="font-weight:700;color:var(--primary)" id="options-total-price">0.00</span> ريال</div>
      <div style="display:flex;gap:8px">
        <button class="btn btn-secondary" onclick="closeOptionsModal()">إلغاء</button>
        <button class="btn btn-primary" onclick="confirmOptionsSelection()">إضافة للسلة</button>
      </div>
    </div>
  </div>
</div>

<!-- Item Information Modal -->
<div class="modal-backdrop hidden" id="item-info-modal">
  <div class="modal" style="max-width:450px">
    <div class="modal-header">
      <h3 id="info-modal-title">📦 مكونات الطلب</h3>
      <button class="modal-close" onclick="closeItemInfoModal()">✕</button>
    </div>
    <div class="modal-body">
        <div id="info-modal-content" style="line-height:1.6; color:var(--text-main)"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-primary btn-block" onclick="closeItemInfoModal()">فهمت</button>
    </div>
  </div>
</div>
</div>

</div>


<script>
  let allItems = [], allCats = [], cart = {}, pendingNoteItemId = null;
  let currentModalItem = null;
  let currentCatId = 'all';
  let allWallets = [];
  let allCashiers = [];
  let stockEnabled = <?= ($settings['enable_stock_tracking'] ?? '0') === '1' ? 'true' : 'false' ?>;
  let stockMap = {}; // item_id -> stock_qty
  <?php
    // canViewStock: admin, stock_management, OR show_stock permission
    $userPerms = !empty($user['permissions']) ? json_decode($user['permissions'], true) : [];
    $canViewStock = $user['role'] === 'admin' ||
        (is_array($userPerms) && (in_array('stock_management', $userPerms) || in_array('show_stock', $userPerms)));
  ?>
  let canViewStock = <?= $canViewStock ? 'true' : 'false' ?>; // إذا يملك صلاحية عرض الرصيد

  // -------------------------------------
  // LocalStorage Persistence
  // -------------------------------------
  function saveCart() {
    localStorage.setItem('waiter_cart', JSON.stringify(cart));
    localStorage.setItem('waiter_table', document.getElementById('table-number').value || '');
    localStorage.setItem('waiter_notes', document.getElementById('order-notes').value || '');
  }

  function loadCart() {
    try {
      const savedCart = localStorage.getItem('waiter_cart');
      if (savedCart) {
        cart = JSON.parse(savedCart);
        updateCartUI();
      }
      const savedTable = localStorage.getItem('waiter_table');
      if (savedTable) document.getElementById('table-number').value = savedTable;
      
      const savedNotes = localStorage.getItem('waiter_notes');
      if (savedNotes) document.getElementById('order-notes').value = savedNotes;
    } catch(e) {
      console.warn('Failed to parse saved cart');
    }
  }

  // Auto-save form inputs
  document.getElementById('table-number').addEventListener('input', saveCart);
  document.getElementById('order-notes').addEventListener('input', saveCart);


  // Load active cashiers for assignment
  async function loadCashiers() {
    var res = await apiCall('/api/users.php?role=cashier&active=1');
    var container = document.getElementById('cashier-options');
    
    if (!res.success || !res.data || !res.data.length) {
      document.getElementById('no-cashiers-msg').style.display = 'block';
      container.innerHTML = '';
      return;
    }
    allCashiers = res.data;
    container.innerHTML = '';
    
    // Try to load last selected cashier from localStorage
    const savedCashierId = localStorage.getItem('waiter_cashier');
    let hasChecked = false;

    allCashiers.forEach(function(c, i) {
      var isChecked = false;
      if (allCashiers.length === 1) isChecked = true;
      else if (savedCashierId == c.id) isChecked = true;
      // If no saved cashier, and current user IS in the list, auto-select them
      else if (!savedCashierId && window.POS_USER && window.POS_USER.id == c.id) isChecked = true;
      else if (i === 0 && !savedCashierId && !allCashiers.some(x => x.id == window.POS_USER.id)) isChecked = true; // Auto select first if self not in list
      
      if (isChecked) hasChecked = true;
      
      var opt = document.createElement('label');
      opt.className = 'pay-opt-label';
      opt.id = 'cashier-lbl-' + c.id;
      opt.style.cssText = `cursor:pointer;padding:8px 4px;border:2px solid ${isChecked ? 'var(--primary)' : 'var(--border)'};border-radius:10px;text-align:center;font-size:0.85rem;background:${isChecked ? 'rgba(191,166,61,0.08)' : 'none'};display:flex;flex-direction:column;align-items:center;gap:2px;margin:0`;
      
      opt.innerHTML = `
        <input type="radio" name="assigned-cashier" value="${c.id}" ${isChecked ? 'checked' : ''} onchange="onCashierChange()" style="display:none">
        <i class="fas fa-user-tie" style="font-size:1.1rem;margin-bottom:2px"></i> ${c.name}
      `;
      container.appendChild(opt);
    });
    
    if (hasChecked) localStorage.setItem('waiter_cashier', document.querySelector('input[name="assigned-cashier"]:checked').value);
  }

  function onCashierChange() {
    var radios = document.querySelectorAll('input[name="assigned-cashier"]');
    radios.forEach(r => {
      var lbl = document.getElementById('cashier-lbl-' + r.value);
      if(r.checked) {
         lbl.style.borderColor = 'var(--primary)';
         lbl.style.background = 'rgba(191,166,61,0.08)';
         localStorage.setItem('waiter_cashier', r.value);
      } else {
         lbl.style.borderColor = 'var(--border)';
         lbl.style.background = 'none';
      }
    });
  }

  function togglePayMethod() {
    var isWallet = document.getElementById('pay-wallet').checked;
    var isCash = document.getElementById('pay-cash').checked;

    document.getElementById('wallet-selector').style.display = isWallet ? 'block' : 'none';

    if (!isWallet) document.getElementById('wallet-info').style.display = 'none';

    // Styling helper
    var labels = {
      'cash': document.getElementById('pay-cash-label'),
      'wallet': document.getElementById('pay-wallet-label')
    };

    for (var key in labels) {
      if (labels[key]) {
        var active = (key === 'cash' && isCash) || (key === 'wallet' && isWallet);
        labels[key].style.borderColor = active ? 'var(--primary)' : 'var(--border)';
        labels[key].style.background = active ? 'rgba(191,166,61,.08)' : 'none';
      }
    }
  }

  function onWalletChange() {
    var sel = document.getElementById('wallet-select');
    var id = sel.value;
    var info = document.getElementById('wallet-info');
    if (!id) { info.style.display = 'none'; return; }
    var w = allWallets.find(function (x) { return x.id == id; });
    if (w) {
      document.getElementById('wallet-info-name').textContent = w.name;
      document.getElementById('wallet-info-num').textContent = w.account_number;
      info.style.display = 'block';
    }
  }

  async function loadWalletsForSelection() {
    var res = await apiCall('/api/wallets.php');
    if (!res.success) return;
    allWallets = res.data;
    var sel = document.getElementById('wallet-select');
    sel.innerHTML = '<option value="">-- اختر المحفظة --</option>';
    allWallets.forEach(function (w) {
      var opt = document.createElement('option');
      opt.value = w.id;
      opt.textContent = w.name + ' — ' + w.account_number;
      sel.appendChild(opt);
    });
    sel.addEventListener('change', onWalletChange);
  }


  async function loadMenu() {
    const grid = document.getElementById('menu-grid');
    const tabs = document.getElementById('cat-tabs');

    try {
      const cRes = await apiCall('/api/categories.php');
      if (cRes.success) {
        allCats = cRes.data;
        const allTab = tabs.querySelector('[data-id="all"]');
        tabs.innerHTML = '';
        if (allTab) tabs.appendChild(allTab);

        // Add special "Offers" category tab
        const offersTab = document.createElement('button');
        offersTab.className = 'cat-tab';
        offersTab.dataset.id = 'offers';
        offersTab.innerHTML = '<i class="fas fa-gift" style="color: gold;"></i> العروض';
        offersTab.onclick = () => filterCat('offers', offersTab);
        tabs.appendChild(offersTab);

        allCats.forEach(c => {
          const btn = document.createElement('button');
          btn.className = 'cat-tab';
          btn.dataset.id = c.id;
          const iconHtml = (c.icon && typeof c.icon === 'string' && c.icon.includes('fa-'))
            ? `<i class="${c.icon}"></i>`
            : (c.icon || '<i class="fas fa-tag"></i>');
          btn.innerHTML = `${iconHtml} ${c.name_ar}`;
          btn.onclick = () => filterCat(c.id, btn);
          tabs.appendChild(btn);
        });
      }

      // Fetch Items
      const iRes = await apiCall('/api/items.php');
      // Fetch Combos
      const comboRes = await apiCall('/api/offers.php?type=combos');
      
      if (iRes.success) {
        allItems = iRes.data;
        
        // Inject combos into allItems as pseudo-items
        if (comboRes.success && comboRes.data.combos) {
            comboRes.data.combos.forEach(combo => {
                if(combo.is_active != 1) return;
                allItems.push({
                    id: 'combo_' + combo.id,
                    is_combo: true,
                    combo_data: combo,
                    category_id: 'offers',
                    name_ar: combo.name_ar,
                    name_en: 'Combo',
                    price: parseFloat(combo.price),
                    discounted_price: parseFloat(combo.price),
                    is_available: 1,
                    has_sizes: 0,
                    has_addons: 0,
                    image: null
                });
            });
        }
        
        loadCart(); // Load saved cart state before rendering icons

        // Load stock data if:
        // - Global stock tracking is enabled (for blocking)
        // - OR user has view permission (for badge display)
        if (stockEnabled || canViewStock) {
          try {
            const sRes = await apiCall('/api/item_stock.php?action=list');
            if (sRes.success) {
              stockMap = {};
              sRes.data.forEach(r => { stockMap[r.id] = parseFloat(r.stock_qty); });
            }
          } catch(e) { console.warn('Stock load failed', e); }
        }

        renderAllItems();
      } else {
        throw new Error(iRes.message || 'فشل تحميل الأصناف');
      }

    } catch (err) {
      console.error('loadMenu error:', err);
      grid.innerHTML = `<div class="alert alert-danger" style="grid-column:1/-1">
      ❌ حدث خطأ أثناء التحميل: ${err.message || 'خطأ في الاتصال'}<br>
      <button class="btn btn-sm btn-primary mt-12" onclick="loadMenu()">🔄 إعادة المحاولة</button>
    </div>`;
    }
  }

  // Render all items once into the DOM — category switching just toggles display
  function renderAllItems() {
    const grid = document.getElementById('menu-grid');
    if (!allItems.length) {
      grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><span class="icon"><i class="fas fa-utensils"></i></span><p>لا توجد أصناف</p></div>';
      return;
    }

    grid.innerHTML = allItems.map(item => {
      let totalQty = 0;
      for (const k in cart) { 
        if (!item.is_combo && cart[k].item && cart[k].item.id == item.id) totalQty += cart[k].qty; 
        if (item.is_combo && cart[k].combo && cart[k].combo.id == item.combo_data.id) totalQty += cart[k].qty;
      }

      const img = item.image
        ? `<img src="<?= BASE_PATH ?>uploads/${item.image}" alt="${item.name_ar}" loading="lazy">`
        : `<div class="menu-item-placeholder"><span><i class="${item.is_combo ? 'fas fa-gift' : 'fas fa-utensils'}"></i></span></div>`;
        
      let priceHtml = '';
      if (item.discounted_price < item.price) {
          priceHtml = `
            <span style="text-decoration: line-through; color: var(--text-muted); font-size: 0.8rem; margin-left: 5px;">${parseFloat(item.price).toFixed(2)}</span>
            <span style="color: var(--danger); font-weight: bold;">${parseFloat(item.discounted_price).toFixed(2)} ريال</span>
          `;
      } else {
          priceHtml = `${item.has_sizes == 1 ? 'يبدأ من ' : ''}${parseFloat(item.price).toFixed(2)} ريال`;
      }

      let discountBadge = '';
      if (item.discounted_price < item.price && item.discount_label) {
          discountBadge = `<div style="position:absolute;top:5px;left:5px;background:var(--danger);color:#fff;padding:2px 6px;border-radius:4px;font-size:0.7rem;z-index:2">${item.discount_label}</div>`;
      } else if (item.is_combo) {
          discountBadge = `<div style="position:absolute;top:5px;left:5px;background:gold;color:#000;padding:2px 6px;border-radius:4px;font-size:0.7rem;z-index:2">باقة عرض</div>`;
      }

      // stockBlocked: block ONLY when global stock tracking is ON AND qty=0
      const _isStockBlocked = stockEnabled && !item.is_combo && stockMap[item.id] !== undefined && stockMap[item.id] === 0;
      // Badge shows ONLY when stock tracking is ON AND user has canViewStock permission
      const _hasStockData   = stockEnabled && canViewStock && !item.is_combo && stockMap[item.id] !== undefined;

      return `
      <div class="menu-item ${totalQty > 0 ? 'selected' : ''} ${item.is_available == '0' ? 'unavailable' : ''} ${_isStockBlocked ? 'unavailable' : ''}"
           id="menu-item-${item.id}"
           data-cat="${item.category_id}"
           onclick="${item.is_available == '0' ? '' : (_isStockBlocked ? '' : (item.is_combo ? 'handleComboClick(' + item.combo_data.id + ')' : 'handleItemClick(' + item.id + ')'))}"
           style="${_isStockBlocked ? 'cursor:not-allowed' : ''}">
        ${discountBadge}
        ${item.has_sizes == 1 || item.has_addons == 1 ? '<div style="position:absolute;top:5px;right:5px;background:rgba(0,0,0,0.6);color:#fff;padding:2px 6px;border-radius:4px;font-size:0.7rem;z-index:2">قابل للتخصيص</div>' : ''}
        ${_isStockBlocked ? '<div style="position:absolute;inset:0;background:rgba(0,0,0,0.55);z-index:5;display:flex;align-items:center;justify-content:center;border-radius:inherit"><span style="color:#fff;font-weight:700;font-size:.85rem;background:rgba(220,38,38,.85);padding:4px 10px;border-radius:6px">نفد الرصيد</span></div>' : ''}
        ${img}
        ${totalQty > 0 ? `<div class="menu-item-qty">${totalQty}</div>` : ''}
        <div class="menu-item-info">
          <div style="display:flex; justify-content:space-between; align-items:flex-start">
            <div class="menu-item-name">${item.name_ar}</div>
            <div class="info-btn" onclick="showItemInfo('${item.id}', event)" title="عرض المكونات">
                <i class="fas fa-info-circle"></i>
            </div>
          </div>
          <small style="color:var(--text-muted);font-size:.75rem">${item.name_en}</small>
          <div class="menu-item-price">${priceHtml}</div>
          ${item.description_ar ? `<div style="font-size:.72rem;color:var(--text-muted);margin-top:2px">${item.description_ar.substring(0, 40)}</div>` : ''}
          ${_hasStockData ? (() => {
            const qty = stockMap[item.id];
            const col  = qty === 0 ? '#dc2626' : qty <= 3 ? '#d97706' : '#059669';
            const icon = qty === 0 ? 'fa-times-circle' : qty <= 3 ? 'fa-exclamation-triangle' : 'fa-layer-group';
            return `<div class="stock-qty-badge" style="color:${col}"><i class="fas ${icon}"></i> رصيد: ${qty}</div>`;
          })() : ''}
        </div>
      </div>`;
    }).join('');

    applyFilter();
  }

  // Instant: just show/hide — no DOM re-creation
  function filterCat(catId, btn) {
    document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    currentCatId = catId;
    applyFilter();
  }

  function applyFilter() {
    const q = document.getElementById('menu-search').value.toLowerCase();
    document.querySelectorAll('[data-cat]').forEach(el => {
      const catMatch = currentCatId === 'all' || el.dataset.cat == currentCatId;
      const nameEl = el.querySelector('.menu-item-name');
      const smallEl = el.querySelector('small');
      const nameText = nameEl ? nameEl.textContent.toLowerCase() : '';
      const smallText = smallEl ? smallEl.textContent.toLowerCase() : '';

      const nameMatch = !q || nameText.includes(q) || smallText.includes(q);
      el.style.display = (catMatch && nameMatch) ? '' : 'none';
    });
  }

  function handleItemClick(itemId) {
    const item = allItems.find(i => i.id == itemId);
    if (!item) return;

    if (item.has_sizes == 1 || item.has_addons == 1) {
      let sizesArr = [], addonsArr = [];
      try { if (item.sizes) sizesArr = typeof item.sizes === 'string' ? JSON.parse(item.sizes) : item.sizes; } catch (e) { }
      try { if (item.addons) addonsArr = typeof item.addons === 'string' ? JSON.parse(item.addons) : item.addons; } catch (e) { }

      if (sizesArr.length > 0 || addonsArr.length > 0) {
        openOptionsModal(item, sizesArr, addonsArr);
        return;
      }
    }

    addToCart(itemId);
  }

  function handleComboClick(comboId) {
    const combo = allItems.find(i => i.is_combo && i.combo_data.id == comboId);
    if (!combo) return;
    addToCart(combo.id);
  }

  function showItemInfo(itemId, event) {
    if (event) event.stopPropagation();
    const item = allItems.find(i => i.id == itemId);
    if (!item) return;

    const titleEl = document.getElementById('info-modal-title');
    const contentEl = document.getElementById('info-modal-content');
    
    titleEl.innerHTML = `<i class="fas ${item.is_combo ? 'fa-gift' : 'fa-info-circle'}" style="color:var(--primary)"></i> تفاصيل: ${item.name_ar}`;

    if (item.is_combo) {
        let itemsHtml = '<ul style="padding-right:20px; margin:0">';
        item.combo_data.items.forEach(ci => {
            itemsHtml += `<li><strong style="color:var(--primary)">${ci.quantity}x</strong> ${ci.item_name}</li>`;
        });
        itemsHtml += '</ul>';
        contentEl.innerHTML = `
            <div style="margin-bottom:10px; font-weight:700">هذه الباقة تحتوي على:</div>
            ${itemsHtml}
            ${item.combo_data.description ? `<div style="margin-top:15px; font-size:0.9rem; color:var(--text-muted)">${item.combo_data.description}</div>` : ''}
        `;
    } else {
        contentEl.innerHTML = `
            <div style="font-weight:700; margin-bottom:8px">مكونات الصنف / الوصف:</div>
            <div style="white-space: pre-wrap;">${item.description_ar || 'لا يوجد وصف متاح لهذا الصنف.'}</div>
        `;
    }

    document.getElementById('item-info-modal').classList.remove('hidden');
  }

  function closeItemInfoModal() {
    document.getElementById('item-info-modal').classList.add('hidden');
  }

  function openOptionsModal(item, sizesArr, addonsArr) {
    currentModalItem = item;
    document.getElementById('options-modal-title').textContent = item.name_ar + ' - تخصيص الطلب';

    const sizesSec = document.getElementById('options-sizes-section');
    const sizesList = document.getElementById('options-sizes-list');
    const addonsSec = document.getElementById('options-addons-section');
    const addonsList = document.getElementById('options-addons-list');

    if (item.has_sizes == 1 && sizesArr.length > 0) {
      sizesSec.style.display = 'block';
      sizesList.innerHTML = sizesArr.map((s, i) => `
      <label style="display:flex;justify-content:space-between;align-items:center;padding:10px;border:1px solid #ddd;border-radius:6px;cursor:pointer">
        <div style="display:flex;align-items:center;gap:10px">
          <input type="radio" name="modal_size" value="${i}" onchange="calculateOptionsTotal()" ${i === 0 ? 'checked' : ''} style="width:18px;height:18px">
          <span style="font-weight:600">${s.name_ar}</span>
        </div>
        <span style="font-weight:700;color:var(--primary)">${parseFloat(s.price).toFixed(2)}</span>
      </label>
    `).join('');
    } else {
      sizesSec.style.display = 'none';
      sizesList.innerHTML = '';
    }

    if (item.has_addons == 1 && addonsArr.length > 0) {
      addonsSec.style.display = 'block';
      addonsList.innerHTML = addonsArr.map((a, i) => `
      <label style="display:flex;justify-content:space-between;align-items:center;padding:10px;border:1px solid #ddd;border-radius:6px;cursor:pointer">
        <div style="display:flex;align-items:center;gap:10px">
          <input type="checkbox" name="modal_addon" value="${i}" onchange="calculateOptionsTotal()" style="width:18px;height:18px">
          <span style="font-weight:600">${a.name_ar}</span>
        </div>
        <span style="font-weight:700;color:var(--primary)">+${parseFloat(a.price).toFixed(2)}</span>
      </label>
    `).join('');
    } else {
      addonsSec.style.display = 'none';
      addonsList.innerHTML = '';
    }

    calculateOptionsTotal();
    document.getElementById('options-modal').classList.remove('hidden');
  }

  function calculateOptionsTotal() {
    if (!currentModalItem) return;
    // Use discounted_price if available, otherwise base price
    let total = parseFloat(currentModalItem.discounted_price || currentModalItem.price) || 0;

    if (currentModalItem.has_sizes == 1) {
      const checkedSize = document.querySelector('input[name="modal_size"]:checked');
      if (checkedSize) {
        let sizesArr = typeof currentModalItem.sizes === 'string' ? JSON.parse(currentModalItem.sizes) : currentModalItem.sizes;
        total = parseFloat(sizesArr[checkedSize.value].price);
      }
    }

    if (currentModalItem.has_addons == 1) {
      const checkedAddons = document.querySelectorAll('input[name="modal_addon"]:checked');
      let addonsArr = typeof currentModalItem.addons === 'string' ? JSON.parse(currentModalItem.addons) : currentModalItem.addons;
      checkedAddons.forEach(cb => {
        total += parseFloat(addonsArr[cb.value].price);
      });
    }

    document.getElementById('options-total-price').textContent = total.toFixed(2);
  }

  function closeOptionsModal() {
    document.getElementById('options-modal').classList.add('hidden');
    currentModalItem = null;
  }

  function confirmOptionsSelection() {
    if (!currentModalItem) return;

    let sizeNameAr = null, sizeNameEn = null, basePrice = parseFloat(currentModalItem.discounted_price || currentModalItem.price);

    if (currentModalItem.has_sizes == 1) {
      const checkedSize = document.querySelector('input[name="modal_size"]:checked');
      if (!checkedSize) {
        showToast('يرجى اختيار الحجم من قائمة الأحجام', 'warning');
        return;
      }
      let sizesArr = typeof currentModalItem.sizes === 'string' ? JSON.parse(currentModalItem.sizes) : currentModalItem.sizes;
      const s = sizesArr[checkedSize.value];
      sizeNameAr = s.name_ar;
      sizeNameEn = s.name_en;
      basePrice = parseFloat(s.price);
    }

    let selectedAddons = [];
    if (currentModalItem.has_addons == 1) {
      const checkedAddons = document.querySelectorAll('input[name="modal_addon"]:checked');
      let addonsArr = typeof currentModalItem.addons === 'string' ? JSON.parse(currentModalItem.addons) : currentModalItem.addons;
      checkedAddons.forEach(cb => {
        const a = addonsArr[cb.value];
        selectedAddons.push({
          name_ar: a.name_ar,
          name_en: a.name_en,
          price: parseFloat(a.price)
        });
      });
    }

    addToCart(currentModalItem.id, sizeNameAr, sizeNameEn, basePrice, selectedAddons);
    closeOptionsModal();
  }

  function addToCart(itemId, sizeNameAr = null, sizeNameEn = null, sizePrice = null, selectedAddons = []) {
    const item = allItems.find(i => i.id == itemId);
    if (!item) return;

    let cartKey = itemId.toString();
    if (sizeNameAr) cartKey += '_sz:' + sizeNameAr;
    if (selectedAddons.length > 0) {
      const sortedAddons = [...selectedAddons].sort((a, b) => a.name_ar.localeCompare(b.name_ar));
      cartKey += '_ad:' + sortedAddons.map(a => a.name_ar).join(',');
    }

    if (!cart[cartKey]) {
      const cartItem = JSON.parse(JSON.stringify(item));
      let nameSuffixAr = [];
      let nameSuffixEn = [];

      // Set base price to discounted_price if applicable
      if (!sizeNameAr) {
        cartItem.price = parseFloat(item.discounted_price || item.price).toFixed(2);
      }

      if (sizeNameAr && sizePrice !== null) {
        // If a size is picked, we currently use the fixed size price. 
        // Note: For advanced logic, we could apply percentage discounts here too.
        cartItem.price = sizePrice;
        cartItem.size_name = sizeNameAr;
        nameSuffixAr.push(sizeNameAr);
        nameSuffixEn.push(sizeNameEn || sizeNameAr);
      }

      if (selectedAddons.length > 0) {
        cartItem.addons = selectedAddons;
        let addonSum = 0;
        let addonNamesAr = [];
        let addonNamesEn = [];
        selectedAddons.forEach(a => {
          addonSum += a.price;
          addonNamesAr.push(a.name_ar);
          addonNamesEn.push(a.name_en || a.name_ar);
        });
        cartItem.price = (parseFloat(cartItem.price) + addonSum).toFixed(2);

        nameSuffixAr.push('+ ' + addonNamesAr.join(' + '));
        nameSuffixEn.push('+ ' + addonNamesEn.join(' + '));
      } else {
        cartItem.addons = [];
      }

      if (nameSuffixAr.length > 0) {
        cartItem.name_ar += ' (' + nameSuffixAr.join(' ') + ')';
        cartItem.name_en += ' (' + nameSuffixEn.join(' ') + ')';
      }

      cart[cartKey] = { item: cartItem, qty: 0, notes: '', is_combo: !!item.is_combo, combo: item.combo_data || null };
    }

    cart[cartKey].qty++;

    // Stock check: block ONLY when global stock tracking is enabled
    if (stockEnabled && !item.is_combo && stockMap[itemId] !== undefined) {
      let totalInCart = 0;
      for (const k in cart) {
        if (cart[k].item && cart[k].item.id == itemId) totalInCart += cart[k].qty;
      }
      if (totalInCart > stockMap[itemId]) {
        cart[cartKey].qty--;
        if (cart[cartKey].qty <= 0) delete cart[cartKey];
        showToast(`⚠️ الكمية المطلوبة أكبر من الرصيد المتوفر (${stockMap[itemId]})`, 'warning');
        return;
      }
    }

    updateCartUI();
    renderMenuQty(itemId);
  }

  function incrementCartQty(cartKey) {
    if (!cart[cartKey]) return;
    const itemId = cart[cartKey].item.id;

    // Stock check before incrementing — ONLY when global stock tracking is enabled
    if (stockEnabled && !cart[cartKey].is_combo && stockMap[itemId] !== undefined) {
      let totalInCart = 0;
      for (const k in cart) {
        if (cart[k].item && cart[k].item.id == itemId) totalInCart += cart[k].qty;
      }
      if (totalInCart >= stockMap[itemId]) {
        showToast(`⚠️ وصلت للحد الأقصى من الرصيد (${stockMap[itemId]})`, 'warning');
        return;
      }
    }

    cart[cartKey].qty++;
    updateCartUI();
    renderMenuQty(itemId);
  }

  function decrementCartQty(cartKey) {
    if (!cart[cartKey]) return;
    cart[cartKey].qty--;
    const itemId = cart[cartKey].item.id;
    if (cart[cartKey].qty <= 0) delete cart[cartKey];
    updateCartUI();
    renderMenuQty(itemId);
  }

  function renderMenuQty(itemId) {
    const el = document.getElementById('menu-item-' + itemId);
    if (!el) return;
    let totalQty = 0;
    for (const k in cart) { 
        if (cart[k].item && cart[k].item.id == itemId) totalQty += cart[k].qty; 
    }

    el.classList.toggle('selected', totalQty > 0);
    const badge = el.querySelector('.menu-item-qty');
    if (totalQty > 0) {
      if (badge) badge.textContent = totalQty;
      else el.insertAdjacentHTML('afterbegin', `<div class="menu-item-qty">${totalQty}</div>`);
    } else {
      if (badge) badge.remove();
    }
  }

  function updateCartUI() {
    const list = document.getElementById('cart-list');
    const items = Object.values(cart);
    if (!items.length) {
      list.innerHTML = '<div class="empty-state" style="padding:40px 20px"><span class="icon"><i class="fas fa-shopping-basket"></i></span><p>اختر الأصناف</p></div>';
      document.getElementById('cart-subtotal').textContent = '0.00 ريال';
      return;
    }

    let total = 0;
    list.innerHTML = Object.entries(cart).map(([key, c]) => {
      const sub = c.item.price * c.qty;
      total += sub;
      return `
      <div class="order-item-row">
        <div>
          <div class="order-item-name">${c.item.name_ar}</div>
          ${c.notes ? `<small style="color:var(--text-muted)">📝 ${c.notes}</small>` : ''}
        </div>
        <div class="qty-control">
          <button class="qty-btn remove" onclick="decrementCartQty('${key}')">−</button>
          <span style="font-weight:700;min-width:20px;text-align:center">${c.qty}</span>
          <button class="qty-btn" onclick="incrementCartQty('${key}')">+</button>
        </div>
        <div class="order-item-price">${parseFloat(sub).toFixed(2)}</div>
        <button style="background:none;border:none;cursor:pointer;font-size:.9rem;color:var(--text-muted)" onclick="openNoteModal('${key}')"><i class="fas fa-sticky-note"></i></button>
      </div>`;
    }).join('');

    document.getElementById('cart-subtotal').textContent = total.toFixed(2) + ' ريال';
    saveCart();
  }

  function openNoteModal(cartKey) {
    pendingNoteItemId = cartKey;
    const item = cart[cartKey];
    if (!item) return;
    document.getElementById('note-item-name').textContent = item.item.name_ar;
    document.getElementById('note-input').value = item.notes || '';
    document.getElementById('item-note-modal').classList.remove('hidden');
  }

  function closeNoteModal() {
    document.getElementById('item-note-modal').classList.add('hidden');
    pendingNoteItemId = null;
  }

  function saveItemNote() {
    if (!pendingNoteItemId || !cart[pendingNoteItemId]) return;
    cart[pendingNoteItemId].notes = document.getElementById('note-input').value.trim();
    closeNoteModal();
    updateCartUI();
  }

  // --- Append Mode Logic ---
  let appendToOrderId = new URLSearchParams(window.location.search).get('append_to');
  
  async function initAppendMode() {
    if (!appendToOrderId) return;
    
    const notice = document.getElementById('append-notice');
    if (notice) notice.style.display = 'block';
    
    document.getElementById('sidebar-title').textContent = 'إضافة أصناف إضافية';
    
    // Hide irrelevant fields in append mode
    const toHide = ['table-number-group', 'pay-method-group', 'cashier-assignment-group', 'wallet-selector'];
    toHide.forEach(id => {
      const el = document.getElementById(id);
      if (el) el.style.display = 'none';
    });

    const res = await apiCall(`/api/orders.php?action=single&id=${appendToOrderId}`);
    if (res.success && res.data) {
      const o = res.data;
      if (['paid', 'delivered', 'cancelled'].includes(o.status)) {
        if (window.POS_USER && !['cashier', 'admin'].includes(window.POS_USER.role)) {
          showToast('عذراً، لا يمكن إضافة أصناف لهذا الطلب لأنه مكتمل أو مسدد أو ملغي.', 'danger', 5000);
          setTimeout(() => { window.location.href = './orders.php'; }, 2000);
          return;
        }
      }
      document.getElementById('append-order-num').textContent = '#' + o.order_number;
      document.getElementById('append-table-info').textContent = 'طاولة: ' + o.table_number + ' | ' + o.waiter_name;
      document.getElementById('table-number').value = o.table_number;
    }
  }

  async function sendToCashier() {
    var items = Object.values(cart);
    if (!items.length) { showToast('الطلب فارغ! أضف أصنافاً أولاً', 'warning'); return; }

    let payload = {};
    let endpoint = '/api/orders.php';

    if (appendToOrderId) {
        // APPEND MODE: Only send order_id and items
        payload = {
            order_id: parseInt(appendToOrderId),
            items: items.map(c => ({
                item_id: c.item.id,
                is_combo: !!c.is_combo,
                combo_id: c.combo ? c.combo.id : null,
                quantity: c.qty,
                notes: c.notes,
                size_name: c.item.size_name || null,
                addons: (function() {
                    let rawAddons = c.item.addons;
                    if (typeof rawAddons === 'string') { try { rawAddons = JSON.parse(rawAddons); } catch(e) { rawAddons = []; } }
                    if (!Array.isArray(rawAddons) || rawAddons.length === 0) return null;
                    return rawAddons.filter(a => a && a.name_ar).map(a => a.name_ar);
                })()
            }))
        };
        endpoint += '?action=append_to_order';
    } else {
        // NORMAL CREATE MODE
        var tableNum = document.getElementById('table-number').value.trim();
        if (!tableNum) { showToast('يرجى إدخال رقم الطاولة أولاً', 'warning'); document.getElementById('table-number').focus(); return; }

        var checkedCashier = document.querySelector('input[name="assigned-cashier"]:checked');
        var assignedCashierId = checkedCashier ? checkedCashier.value : '';
        if (!assignedCashierId) { showToast('يرجى اختيار الكاشير المسؤول عن الطلب', 'warning'); return; }

        var notes = document.getElementById('order-notes').value.trim();
        var payMethod = document.querySelector('input[name="pay-method"]:checked').value;
        var walletId = '';
        var walletName = '';

        if (payMethod === 'wallet') {
          walletId = document.getElementById('wallet-select').value;
          if (!walletId) { showToast('يرجى اختيار المحفظة', 'warning'); return; }
          var w = allWallets.find(function (x) { return x.id == walletId; });
          if (w) walletName = w.name + ' (' + w.account_number + ')';
        }

        payload = {
          table_number: tableNum,
          notes: notes,
          payment_method: payMethod,
          wallet_id: walletId || null,
          wallet_name: walletName || null,
          assigned_cashier_id: parseInt(assignedCashierId),
          items: items.map(function (c) {
            return {
              item_id: c.item.id,
              is_combo: !!c.is_combo,
              combo_id: c.combo ? c.combo.id : null,
              quantity: c.qty,
              notes: c.notes,
              unit_price: c.item.price, // Ensure the discounted price is sent correctly
              size_name: c.item.size_name || null,
              addons: (function() {
                var rawAddons = c.item.addons;
                if (typeof rawAddons === 'string') { try { rawAddons = JSON.parse(rawAddons); } catch(e) { rawAddons = []; } }
                if (!Array.isArray(rawAddons) || rawAddons.length === 0) return null;
                return rawAddons.filter(a => a && a.name_ar).map(a => a.name_ar);
              })()
            };
          })
        };
    }

    const res = await apiCall(endpoint, 'POST', payload);
    
    if (res.success) {
      if (appendToOrderId) {
        showToast('✅ تمت إضافة الأصناف للطلب بنجاح!', 'success');
      } else if (res.data && res.data.order_id) {
        const printBtn = `<button class="btn btn-sm btn-info" onclick="printReceipt(${res.data.order_id})" style="margin-right:10px; padding:2px 8px; border-radius:15px; font-size:0.8rem;"><i class="fas fa-print"></i> طباعة الفاتورة</button>`;
        showToast(res.message + ' ' + printBtn, 'success', 8000);
      } else {
        showToast(res.message, 'success');
      }
      
      clearCart();
      if (!appendToOrderId) {
        document.getElementById('table-number').value = '';
        document.getElementById('order-notes').value = '';
        document.getElementById('pay-cash').checked = true;
        togglePayMethod();
      }
      saveCart(); // Clear local storage for next order
    } else {
      showToast(res.message, 'danger');
    }
  }

  function clearCart() {
    cart = {};
    updateCartUI();
    document.querySelectorAll('.menu-item').forEach(el => {
      el.classList.remove('selected');
      const badge = el.querySelector('.menu-item-qty');
      if (badge) badge.remove();
    });
  }

  document.getElementById('menu-search').addEventListener('input', applyFilter);
  document.getElementById('item-note-modal').addEventListener('click', function (e) { if (e.target.id === 'item-note-modal') closeNoteModal(); });

  // Wait for app.js (apiCall) to be ready, then start loading
  (function waitForApp() {
    if (typeof apiCall === 'function') {
      loadMenu();
      loadWalletsForSelection();
      loadCashiers();
      initAppendMode();
      if (typeof onSSE === 'function') onSSE('menu_updated', loadMenu);
    } else {
      setTimeout(waitForApp, 50);
    }
  })();
</script>
<?php waiterFooter(); ?>