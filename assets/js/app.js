// ============================================
// Restaurant POS System - Shared JS (ES5 Compatible)
// ============================================

// Toast Notifications
var gAudioCtx = null;
function playNotificationSound(isHeavy) {
  if (isHeavy === undefined) isHeavy = true;
  var enabled = localStorage.getItem('pos_notifications_enabled') === 'true';
  if (!enabled) return;

  try {
    var AudioContext = window.AudioContext || window.webkitAudioContext;
    if (!AudioContext) return;
    if (!gAudioCtx) gAudioCtx = new AudioContext();
    if (gAudioCtx.state === 'suspended') gAudioCtx.resume();

    var now = gAudioCtx.currentTime;
    var playSharpBeep = function (freq, start, duration) {
      var osc = gAudioCtx.createOscillator();
      var gain = gAudioCtx.createGain();
      osc.type = isHeavy ? 'square' : 'sine';
      osc.frequency.setValueAtTime(freq, start);
      gain.gain.setValueAtTime(0, start);
      gain.gain.linearRampToValueAtTime(0.6, start + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.01, start + duration);
      osc.connect(gain);
      gain.connect(gAudioCtx.destination);
      osc.start(start);
      osc.stop(start + duration);
    };

    if (isHeavy) {
      playSharpBeep(1000, now, 0.2);
      playSharpBeep(1000, now + 0.3, 0.2);
      playSharpBeep(1000, now + 0.6, 0.4);
    } else {
      playSharpBeep(880, now, 0.3);
      playSharpBeep(698, now + 0.3, 0.5);
    }
  } catch (e) { console.error('Audio error:', e); }
}

function toggleNotifications() {
  var current = localStorage.getItem('pos_notifications_enabled') === 'true';
  var newState = !current;
  localStorage.setItem('pos_notifications_enabled', newState);

  if (newState) {
    playNotificationSound(false);
    showToast('\u0D41\uD83D\uDD14 \u062a\u0645 \u062a\u0641\u0639\u064a\u0644 \u0627\u0644\u062a\u0646\u0628\u064a\u0647\u0627\u062a \u0627\u0644\u0635\u0648\u062a\u064a\u0629', 'success');
  } else {
    showToast('\uD83D\uDD15 \u062a\u0645 \u0643\u062a\u0645 \u0627\u0644\u062a\u0646\u0628\u064a\u0647\u0627\u062a \u0627\u0644\u0635\u0648\u062a\u064a\u0629', 'warning');
  }

  updateSoundFabUI(newState);
}

function updateSoundFabUI(enabled) {
  var fab = document.getElementById('sound-fab');
  if (!fab) return;

  var icon = fab.querySelector('i');
  if (enabled) {
    fab.style.background = 'var(--primary)';
    fab.style.color = '#fff';
    fab.style.borderColor = 'var(--primary)';
    if (icon) icon.className = 'fas fa-bell';
    fab.title = '\u0625\u0644\u063a\u0627\u0621 \u0627\u0644\u062a\u0646\u0628\u064a\u0647\u0627\u062a \u0627\u0644\u0635\u0648\u062a\u064a\u0629';
  } else {
    fab.style.background = '#fef4f3';
    fab.style.color = '#e74c3c';
    fab.style.borderColor = '#fadbd8';
    if (icon) icon.className = 'fas fa-bell-slash';
    fab.title = '\u062a\u0641\u0639\u064a\u0644 \u0627\u0644\u062a\u0646\u0628\u064a\u0647\u0627\u062a \u0627\u0644\u0635\u0648\u062a\u064a\u0629';
  }
}

// Ensure first click context unlock
function unlockAudio() {
  toggleNotifications();
}

function showToast(message, type, duration) {
  if (type === undefined) type = 'info';
  if (duration === undefined) duration = 3500;

  var container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    document.body.appendChild(container);
  }

  var icons = {
    success: '<i class="fas fa-check-circle"></i>',
    danger: '<i class="fas fa-exclamation-circle"></i>',
    warning: '<i class="fas fa-exclamation-triangle"></i>',
    info: '<i class="fas fa-info-circle"></i>'
  };
  var toast = document.createElement('div');
  toast.className = 'toast ' + type;
  toast.innerHTML = '<span>' + (icons[type] || '<i class="fas fa-bell"></i>') + '</span> <span>' + message + '</span>';
  container.appendChild(toast);

  setTimeout(function () {
    toast.style.animation = 'none';
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all .3s ease';
    setTimeout(function () { toast.remove(); }, 300);
  }, duration);
}

// Confirm dialog
function confirmAction(message) {
  return window.confirm(message);
}

// API Helper
async function apiCall(url, method, body) {
  if (method === undefined) method = 'GET';
  if (body === undefined) body = null;

  var basePath = window.POS_BASE_PATH || '/';
  if (url.indexOf('/') === 0) {
    url = (basePath + url.substring(1)).replace(/\/\/+/g, '/');
  }

  var options = {
    method: method,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  };
  if (body && method !== 'GET') {
    if (body instanceof FormData) {
      options.body = body;
    } else {
      options.headers['Content-Type'] = 'application/json; charset=utf-8';
      options.body = JSON.stringify(body);
    }
  }

  var timeout = new Promise(function (_, reject) {
    setTimeout(function () { reject(new Error('TIMEOUT')); }, 30000);
  });

  try {
    var res = await Promise.race([fetch(url, options), timeout]);
    var text = await res.text();
    try {
      return JSON.parse(text);
    } catch (parseErr) {
      console.error('JSON Parse Error. Server output:', text);
      var msg = text.substring(0, 100).replace(/<[^>]*>/g, '');
      alert('\u062e\u0637\u0623 \u0641\u064a \u0627\u0633\u062a\u062c\u0627\u0628\u0629 \u0627\u0644\u062e\u0627\u062f\u0645:\n' + msg);
      return { success: false, message: 'SERVER ERROR (' + res.status + '): \u0627\u0633\u062a\u062c\u0627\u0628\u0629 \u063a\u064a\u0631 \u0635\u0627\u0644\u062d\u0629' };
    }
  } catch (err) {
    console.error('Fetch Error:', err);
    var errMsg = err.message === 'TIMEOUT' ? '\u0627\u0644\u0634\u0628\u0643\u0629 \u0636\u0639\u064a\u0641\u0629 \u0623\u0648 \u0627\u0644\u062e\u0627\u062f\u0645 \u0645\u0634\u063a\u0648\u0644\u060c \u064a\u0631\u062c\u0649 \u0625\u0639\u0627\u062f\u0629 \u0627\u0644\u0645\u062d\u0627\u0648\u0644\u0629 (\u0627\u0646\u062a\u0647\u0649 \u0627\u0644\u0648\u0642\u062a)' : '\u062e\u0637\u0623 \u0641\u064a \u0627\u0644\u0627\u062a\u0635\u0627\u0644 \u0628\u0627\u0644\u062e\u0627\u062f\u0645';
    return { success: false, message: errMsg };
  }
}

// Format price
function formatPrice(amount, currency) {
  if (currency === undefined) currency = '\u0631\u064a\u0627\u0644';
  return parseFloat(amount || 0).toFixed(2) + ' ' + currency;
}

// Format datetime
function formatDate(dateStr) {
  if (!dateStr) return '-';
  var d = new Date(dateStr);
  return d.toLocaleString('ar-SA', { hour12: true });
}

// Status labels
var statusLabels = {
  pending: { label: '\u0645\u0639\u0644\u0651\u0642', class: 'badge-pending' },
  sent_to_cashier: { label: '\u0623\u064f\u0631\u0633\u0644 \u0644\u0644\u0643\u0627\u0634\u064a\u0631', class: 'badge-sent' },
  confirmed: { label: '\u0628\u062f\u0623 \u0627\u0644\u062a\u062d\u0636\u064a\u0631', class: 'badge-confirmed' },
  in_progress: { label: '\u0642\u064a\u062f \u0627\u0644\u062a\u062d\u0636\u064a\u0631', class: 'badge-in_progress' },
  ready: { label: '\u062c\u0627\u0647\u0632 \u0644\u0644\u0627\u0633\u062a\u0644\u0627\u0645', class: 'badge-ready' },
  paid: { label: '\u0645\u062f\u0641\u0648\u0639', class: 'badge-paid' },
  delivered: { label: '\u062a\u0645 \u0627\u0644\u062a\u0633\u0644\u064a\u0645', class: 'badge-delivered' },
  cancelled: { label: '\u0645\u0644\u063a\u064a', class: 'badge-cancelled' },
  rejected: { label: '\u0645\u0631\u0641\u0648\u0636', class: 'badge-danger' },
  refunded: { label: '\u0645\u0631\u062a\u062c\u0639 \u0643\u0627\u0645\u0644', class: 'badge-danger' },
  partially_refunded: { label: '\u0645\u0631\u062a\u062c\u0639 \u062c\u0632\u0626\u064a', class: 'badge-warning' },
};

function statusBadge(status) {
  if (!status || status.trim() === '') {
    return '<span class="badge" style="background:var(--danger);color:#fff">\u0645\u0641\u0642\u0648\u062f</span>';
  }
  var s = statusLabels[status];
  if (!s) {
    return '<span class="badge" style="background:#555;color:#fff">' + status + '</span>';
  }
  return '<span class="badge ' + s.class + '">' + s.label + '</span>';
}

// ============================================
// SSE Client - Real-time updates
// ============================================
var sseLastId = 0;
var sseConnection = null;
var sseHandlers = {};

function initSSE() {
  if (sseConnection && sseConnection.readyState !== 2) return;
  if (sseConnection) sseConnection.close();

  var basePath = window.POS_BASE_PATH || '/';
  var apiPath = (basePath + '/api/sse.php').replace(/\/+/g, '/');
  var url = apiPath + '?last_id=' + sseLastId;
  sseConnection = new EventSource(url);

  sseConnection.onopen = function () { console.log('SSE Connected'); };
  sseConnection.onerror = function (e) {
    console.warn('SSE error, reconnecting in 3s...');
    sseConnection.close();
    setTimeout(initSSE, 3000);
  };

  // Handle reconnect event
  sseConnection.addEventListener('reconnect', function (e) {
    var data = JSON.parse(e.data || '{}');
    if (data.last_id) sseLastId = data.last_id;
    sseConnection.close();
    setTimeout(initSSE, 500);
  });

  // Ping
  sseConnection.addEventListener('ping', function (e) {
    console.log('SSE ping', e.data);
  });

  // Register a handler for each event type
  var events = [
    'new_order', 'order_status_changed', 'order_deleted',
    'item_status_changed', 'station_order',
    'new_message', 'category_updated', 'menu_updated'
  ];

  for (var i = 0; i < events.length; i++) {
    (function (evt) {
      sseConnection.addEventListener(evt, function (e) {
        try {
          var data = JSON.parse(e.data);
          if (e.lastEventId) sseLastId = parseInt(e.lastEventId);
          if (sseHandlers[evt]) sseHandlers[evt](data);
          if (sseHandlers['*']) sseHandlers['*'](evt, data);
        } catch (err) {
          console.error('SSE parse error', err);
        }
      });
    })(events[i]);
  }
}

function onSSE(eventType, handler) {
  sseHandlers[eventType] = handler;
}

// ============================================
// Messaging (Chat)
// ============================================
var chatOpen = false;
var chatMessages = [];
var unreadCount = 0;
var currentUser = window.POS_USER || {};

function initChat() {
  var fab = document.getElementById('chat-fab');
  var panel = document.getElementById('chat-panel');
  var closeBtn = document.getElementById('chat-close');
  var sendBtn = document.getElementById('chat-send');
  var input = document.getElementById('chat-input');

  if (!fab) return;

  fab.addEventListener('click', toggleChat);
  if (closeBtn) closeBtn.addEventListener('click', function () { toggleChat(false); });
  if (sendBtn) sendBtn.addEventListener('click', sendChatMessage);
  if (input) input.addEventListener('keypress', function (e) { if (e.key === 'Enter') sendChatMessage(); });

  onSSE('new_message', function (data) {
    if (data.sender_id != currentUser.id) {
      appendChatMessage(data);
    }
    if (!chatOpen) {
      updateUnreadBadge(unreadCount + 1);
      showToast('\u0631\u0633\u0627\u0644\u0629 \u062c\u062f\u064a\u062f\u0629 \u0645\u0646 ' + data.sender_name, 'info');
    }
  });
}

async function loadMessages() {
  var res = await apiCall('/api/messages.php');
  if (res.success) {
    var container = document.getElementById('chat-messages');
    if (!container) return;
    container.innerHTML = '';
    var msgs = res.data || [];
    for (var i = 0; i < msgs.length; i++) {
      appendChatMessage(msgs[i]);
    }
  }
}

function appendChatMessage(msg) {
  var container = document.getElementById('chat-messages');
  if (!container) return;

  var isMine = msg.sender_id == currentUser.id;
  var div = document.createElement('div');
  div.className = 'chat-msg ' + (isMine ? 'mine' : 'other');
  div.innerHTML = (!isMine ? '<div class="sender">' + msg.sender_name + '</div>' : '') +
    '<div>' + escapeHtml(msg.message) + '</div>';
  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
}

async function sendChatMessage() {
  var input = document.getElementById('chat-input');
  if (!input || !input.value.trim()) return;

  var msg = input.value.trim();
  input.value = '';

  appendChatMessage({
    sender_id: currentUser.id,
    sender_name: currentUser.name,
    message: msg,
    is_optimistic: true
  });

  var res = await apiCall('/api/messages.php', 'POST', { message: msg });
  if (!res.success) {
    showToast(res.message, 'danger');
  }
}

function toggleChat(forceState) {
  var panel = document.getElementById('chat-panel');
  var fab = document.getElementById('chat-fab');
  if (!panel) return;

  chatOpen = forceState !== undefined ? forceState : !chatOpen;
  panel.classList.toggle('hidden', !chatOpen);
  if (fab) fab.style.display = chatOpen ? 'none' : 'flex';

  if (chatOpen) {
    updateUnreadBadge(0);
    var container = document.getElementById('chat-messages');
    if (container && container.children.length === 0) {
      loadMessages();
    }
    if (container) container.scrollTop = container.scrollHeight;
  }
}

function updateUnreadBadge(count) {
  unreadCount = count;
  var badge = document.querySelector('.chat-fab .unread-dot');
  if (!badge) return;
  if (unreadCount > 0) {
    badge.style.display = 'flex';
    badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
  } else {
    badge.style.display = 'none';
  }
}

// ============================================
// Excel Export (SheetJS)
// ============================================
function exportToExcel(data, filename) {
  if (filename === undefined) filename = '\u062a\u0642\u0631\u064a\u0631';
  if (typeof XLSX === 'undefined') {
    showToast('\u064a\u062a\u0645 \u062a\u062d\u0645\u064a\u0644 \u0645\u0643\u062a\u0628\u0629 Excel...', 'warning');
    var script = document.createElement('script');
    script.src = 'https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js';
    script.onload = function () { doExport(data, filename); };
    document.head.appendChild(script);
    return;
  }
  doExport(data, filename);
}

function doExport(data, filename) {
  var ws = XLSX.utils.json_to_sheet(data);
  var wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, '\u062a\u0642\u0631\u064a\u0631');

  var range = XLSX.utils.decode_range(ws['!ref']);
  for (var C = range.s.c; C <= range.e.c; ++C) {
    var address = XLSX.utils.encode_cell({ r: 0, c: C });
    if (!ws[address]) continue;
    ws[address].s = { font: { bold: true }, fill: { fgColor: { rgb: 'E67E22' } } };
  }

  XLSX.writeFile(wb, filename + '_' + new Date().toISOString().split('T')[0] + '.xlsx');
  showToast('\u062a\u0645 \u062a\u0635\u062f\u064a\u0631 \u0627\u0644\u0645\u0644\u0641 \u0628\u0646\u062c\u0627\u062d \u2705', 'success');
}

// ============================================
// Print Receipt
// ============================================
function printReceipt(orderId) {
  var basePath = window.POS_BASE_PATH || '/';
  var url = (basePath + '/print_receipt.php?order_id=' + orderId).replace(/\/+/g, '/');
  var win = window.open(url, '_blank', 'width=420,height=600');
  win.focus();
}

// Universal sidebar toggle with persistence
function initSidebarToggle() {
  var btn = document.getElementById('sidebar-toggle');
  var sidebar = document.querySelector('.sidebar');
  var mainContent = document.querySelector('.main-content');
  var closeBtn = document.querySelector('.sidebar-close');
  if (!btn || !sidebar || !mainContent) return;

  var isCollapsed = localStorage.getItem('POS_SIDEBAR_COLLAPSED') === 'true';
  if (isCollapsed) {
    sidebar.classList.add('collapsed');
    sidebar.classList.add('open');
    mainContent.classList.add('expanded');
  }

  var toggleFn = function (e) {
    if (e) e.stopPropagation();
    var collapsed = sidebar.classList.toggle('collapsed');
    sidebar.classList.toggle('open');
    mainContent.classList.toggle('expanded', collapsed);
    localStorage.setItem('POS_SIDEBAR_COLLAPSED', collapsed);
  };

  btn.addEventListener('click', toggleFn);
  if (closeBtn) closeBtn.addEventListener('click', toggleFn);

  document.addEventListener('click', function (e) {
    if (window.innerWidth <= 1280) {
      if (!sidebar.contains(e.target) && !btn.contains(e.target)) {
        sidebar.classList.remove('open');
        sidebar.classList.add('collapsed');
        mainContent.classList.remove('expanded');
      }
    }
  });
}

// Escape HTML
function escapeHtml(str) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(str));
  return d.innerHTML;
}

// Check unread messages count
async function checkUnreadMessages() {
  var res = await apiCall('/api/messages.php?action=unread_count');
  if (res.success && res.data.count > 0) {
    updateUnreadBadge(res.data.count);
  }
}

// ============================================
// Pagination Helpers
// ============================================
function paginateData(data, page, limit) {
  var start = (page - 1) * limit;
  return data.slice(start, start + limit);
}

function renderPagination(totalItems, limit, currentPage, containerId, callback) {
  var container = document.getElementById(containerId);
  if (!container) return;
  container.innerHTML = '';

  var totalPages = Math.ceil(totalItems / limit);
  if (totalPages <= 1) return;

  var wrapper = document.createElement('div');
  wrapper.className = 'pagination-wrapper';
  wrapper.style.cssText = 'display:flex;justify-content:center;gap:8px;margin-top:16px;';

  var createBtn = function (html, page, isDisabled, isCurrent) {
    var btn = document.createElement('button');
    btn.className = 'btn btn-sm ' + (isCurrent ? 'btn-primary' : 'btn-secondary');
    btn.innerHTML = html;
    if (isDisabled) {
      btn.disabled = true;
      btn.style.opacity = '0.5';
      btn.style.cursor = 'not-allowed';
    } else {
      (function (p) {
        btn.onclick = function () { window[callback](p); };
      })(page);
    }
    return btn;
  };

  wrapper.appendChild(createBtn('<i class="fas fa-chevron-right"></i>', currentPage - 1, currentPage === 1, false));

  for (var i = 1; i <= totalPages; i++) {
    if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
      wrapper.appendChild(createBtn(i, i, false, i === currentPage));
    } else if (i === currentPage - 3 || i === currentPage + 3) {
      var span = document.createElement('span');
      span.textContent = '...';
      span.style.alignSelf = 'center';
      wrapper.appendChild(span);
    }
  }

  wrapper.appendChild(createBtn('<i class="fas fa-chevron-left"></i>', currentPage + 1, currentPage === totalPages, false));
  container.appendChild(wrapper);
}

// ============================================
// Bulk Selection & Delete Helpers
// ============================================
function toggleSelectAll(sourceCheckbox, checkboxClass) {
  if (checkboxClass === undefined) checkboxClass = 'row-checkbox';
  var checkboxes = document.querySelectorAll('.' + checkboxClass);
  for (var i = 0; i < checkboxes.length; i++) {
    if (!checkboxes[i].disabled) checkboxes[i].checked = sourceCheckbox.checked;
  }
  updateBulkDeleteBtn(checkboxClass);
}

function updateBulkDeleteBtn(checkboxClass, btnId, countId) {
  if (checkboxClass === undefined) checkboxClass = 'row-checkbox';
  if (btnId === undefined) btnId = 'bulk-delete-btn';
  if (countId === undefined) countId = 'selected-count';

  var checkedItems = document.querySelectorAll('.' + checkboxClass + ':checked');
  var checkedCount = checkedItems.length;
  var btn = document.getElementById(btnId);
  if (btn) {
    btn.style.display = checkedCount > 0 ? 'inline-flex' : 'none';
    var countSpan = document.getElementById(countId);
    if (countSpan) countSpan.textContent = checkedCount;
  }
  var selectAll = document.getElementById('select-all');
  if (selectAll) {
    var totalCheckboxes = document.querySelectorAll('.' + checkboxClass).length;
    selectAll.checked = (totalCheckboxes > 0 && checkedCount === totalCheckboxes);
    selectAll.indeterminate = (checkedCount > 0 && checkedCount < totalCheckboxes);
  }
}

async function executeBulkDelete(endpoint, checkboxClass, reloadCallback) {
  if (checkboxClass === undefined) checkboxClass = 'row-checkbox';
  var checkboxes = document.querySelectorAll('.' + checkboxClass + ':checked');
  var ids = [];
  for (var i = 0; i < checkboxes.length; i++) ids.push(checkboxes[i].value);
  if (!ids.length) return;
  if (!confirm('\u0647\u0644 \u0623\u0646\u062a \u0645\u062a\u0623\u0643\u062f \u0645\u0646 \u062d\u0630\u0641 ' + ids.length + ' \u0639\u0646\u0635\u0631\u061f \u0644\u0627 \u064a\u0645\u0643\u0646 \u0627\u0644\u062a\u0631\u0627\u062c\u0639 \u0639\u0646 \u0647\u0630\u0627 \u0627\u0644\u0625\u062c\u0631\u0627\u0621.')) return;

  var btn = document.getElementById('bulk-delete-btn');
  var originalHtml = btn ? btn.innerHTML : '';
  if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner" style="width:14px;height:14px;border-width:2px;border-top-color:#fff;display:inline-block;margin-left:5px"></div> \u062c\u0627\u0631\u064a \u0627\u0644\u062d\u0630\u0641...'; }

  var successCount = 0, failCount = 0;
  var successIds = [];
  for (var j = 0; j < ids.length; j++) {
    var res = await apiCall(endpoint + '?action=delete', 'POST', { id: parseInt(ids[j]) });
    if (res && res.success) {
      successCount++;
      successIds.push(ids[j].toString());
    } else {
      failCount++;
    }
  }

  if (btn) { btn.innerHTML = originalHtml; btn.disabled = false; btn.style.display = 'none'; }
  var selectAll = document.getElementById('select-all');
  if (selectAll) selectAll.checked = false;

  showToast(
    '\u062a\u0645 \u062d\u0630\u0641 ' + successCount + ' \u0639\u0646\u0635\u0631' + (failCount > 0 ? '\u060c \u0641\u0634\u0644 \u062d\u0630\u0641 ' + failCount : ''),
    successCount > 0 ? 'success' : 'danger'
  );

  if (typeof reloadCallback === 'function') {
    reloadCallback(successIds);
  } else if (typeof window[reloadCallback] === 'function') {
    window[reloadCallback](successIds);
  }
}

// ============================================
// Init on DOM ready
// ============================================
document.addEventListener('DOMContentLoaded', function () {
  setTimeout(function () {
    initSSE();
  }, 3000);

  initChat();
  initSidebarToggle();

  var enabled = localStorage.getItem('pos_notifications_enabled') === 'true';
  updateSoundFabUI(enabled);

  setTimeout(function () {
    checkUnreadMessages();
  }, 3000);

  setInterval(checkUnreadMessages, 60000);
});

/**
 * Global Print Function
 * 1. Flutter Native Bridge (if running in Flutter WebView)
 * 2. NativePrinterBridge with isNative flag
 * 3. Bluetooth print_type → enqueue in print_queue for Print Module app (no popup)
 * 4. Local XAMPP silent print via PowerShell
 * 5. Browser popup fallback
 */
var activePrintJobs = {};
async function printOrder(orderId, type) {
  if (!orderId) return;
  if (type === undefined) type = 'receipt';

  var jobKey = orderId + '_' + type;
  if (activePrintJobs[jobKey]) {
    showToast('⚠️ جاري طباعة هذه الفاتورة بالفعل، يرجى الانتظار...', 'warning');
    return;
  }

  var btn = (typeof event !== 'undefined') && event && event.target
            ? event.target.closest('button') : null;
  var originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الطباعة...';
  }

  activePrintJobs[jobKey] = true;

  // ── Detect base path ────────────────────────────────────────────────────
  var _base = '';
  try {
    var scripts = document.getElementsByTagName('script');
    for (var _i = 0; _i < scripts.length; _i++) {
      if (scripts[_i].src && scripts[_i].src.indexOf('app.js') !== -1) {
        _base = scripts[_i].src
          .replace(/\/assets\/js\/app\.js.*$/, '')
          .replace(window.location.origin, '');
        break;
      }
    }
  } catch(e) { _base = ''; }

  try {
    // ── Case 1: Flutter Native Bridge ───────────────────────────────────
    if (window.PrinterBridge && typeof window.PrinterBridge.postMessage === 'function') {
      var res = await apiCall('/api/print_proxy.php?action=' + type + '&json=1&order_id=' + orderId);
      if (!res.success) throw new Error(res.message || 'فشل جلب بيانات الطباعة');
      window.PrinterBridge.postMessage(JSON.stringify(res.data));
      showToast('✅ جاري الطباعة عبر التطبيق...', 'success');

    // ── Case 2: NativePrinterBridge (Bluetooth SPP class) ───────────────
    } else if (window.PrinterBridge && typeof window.PrinterBridge.print === 'function' && window.PrinterBridge.isNative) {
      var res2 = await apiCall('/api/print_proxy.php?action=' + type + '&json=1&order_id=' + orderId);
      if (!res2.success) throw new Error(res2.message || 'فشل جلب بيانات الطباعة');
      var bridgeRes = await window.PrinterBridge.print(res2.data);
      if (bridgeRes && bridgeRes.success) showToast('✅ تمت الطباعة بنجاح عبر البلوتوث', 'success');

    // ── Case 3: Bluetooth print_type — enqueue for Print Module ─────────
    // The "Print Module With POS" app polls print_queue and prints silently via Bluetooth.
    // No popups, no preview — purely automatic.
    } else if (window.POS_USER && window.POS_USER.print_type === 'bluetooth') {
      try {
        var res3 = await apiCall('/api/print_proxy.php?action=' + type + '&json=1&order_id=' + orderId);
        if (res3 && res3.success) {
          var encodedData = encodeURIComponent(JSON.stringify(res3.data));
          window.location.href = 'shebaprint://print?data=' + encodedData;
          showToast('🖨️ جاري الطباعة الفورية عبر البلوتوث...', 'success');
        } else {
          throw new Error(res3.message || 'تعذر جلب تفاصيل الفاتورة');
        }
      } catch (e) {
        var qRes = await apiCall('/api/print_queue.php?action=enqueue', 'POST', { order_id: orderId });
        if (qRes && qRes.success) {
          showToast('🖨️ تم إرسال الفاتورة لتطبيق الطباعة في الخلفية...', 'success');
        } else {
          showToast('⚠️ تعذّر إرسال أمر الطباعة — تأكد أن تطبيق Print Module مفتوح ويعمل', 'warning');
        }
      }

    // ── Case 4: Local XAMPP silent print (desktop — no dialog) ──────────
    } else {
      var silentOk = false;

      if (type === 'receipt') {
        // Try localhost silent print first
        try {
          var localUrl = 'http://localhost' + _base + '/api/print_direct.php?action=all';
          var ctrl = new AbortController();
          var timer = setTimeout(function(){ ctrl.abort(); }, 4000);

          var localRes = await fetch(localUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId }),
            signal: ctrl.signal,
            credentials: 'include'
          });
          clearTimeout(timer);

          if (localRes.ok) {
            var data = await localRes.json();
            if (data.success || (data.data && (data.data.cashier?.ok || data.data.kitchen?.ok))) {
              showToast('🖨️ تمت الطباعة للكاشير والمطبخ ✅', 'success');
              silentOk = true;
            }
          }
        } catch(localErr) {
          // Local XAMPP not running — fallback to popups
          console.info('Local print unavailable, using popups:', localErr.message);
        }
      }

      // ── Fallback: Single popup ────────────────────────────────────────────
      if (!silentOk) {
        if (type === 'receipt') {
          var cashierUrl = _base + '/print_receipt.php?order_id=' + orderId + '&copy=cashier';

          var popup1 = window.open(cashierUrl, 'pos_cashier_' + orderId,
            'width=500,height=720,top=50,left=50,toolbar=0,menubar=0,scrollbars=0');

          if (popup1) {
            showToast('🖨️ جاري الطباعة...', 'success');
          } else {
            showToast('⚠️ فعّل الـ Popups في المتصفح', 'warning');
            window.open(cashierUrl, '_blank');
          }
        } else {
          var kitchenUrl2 = _base + '/print_receipt.php?order_id=' + orderId + '&copy=kitchen';
          var popup3 = window.open(kitchenUrl2, 'pos_kitchen_' + orderId,
            'width=420,height=600,toolbar=0,menubar=0,scrollbars=0');
          if (!popup3) window.open(kitchenUrl2, '_blank');
          showToast('🍳 تذكرة المطبخ', 'success');
        }
      }
    }
  } catch (err) {
    showToast('\u274C \u062E\u0637\u0623: ' + err.message, 'danger');
    console.error('Print Error:', err);
  } finally {
    // Keep lock and disable button for 2.5 seconds to prevent overlapping print jobs
    setTimeout(function () {
      delete activePrintJobs[jobKey];
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      }
    }, 2500);
  }
}

// Backward compatibility
function printReceipt(orderId) {
  return printOrder(orderId, 'receipt');
}

