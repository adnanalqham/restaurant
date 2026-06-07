<?php
require_once __DIR__ . '/_layout.php';
adminHeader('سجل المراقبة والمتابعة', 'activity');
?>

<div class="card mb-16">
  <div class="card-header">
    <h3><i class="fas fa-eye"></i> مراقبة الأنشطة والعمليات</h3>
    <button class="btn btn-primary btn-sm" onclick="loadLogs()"><i class="fas fa-sync"></i> تحديث</button>
  </div>
  <div class="card-body" style="padding:0">
    <div class="table-controls" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:10px 15px 0;">
      <div style="font-size:0.9rem">
        عرض 
        <select class="form-control" style="width:auto; display:inline-block; padding:2px 30px 2px 10px; height:30px; font-size:0.9rem" onchange="currentLimit=parseInt(this.value); loadLogs(1);">
          <option value="10">10</option>
          <option value="20">20</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
        أسطر
      </div>
    </div>
    <div class="table-wrapper">
      <table id="logs-table">
        <thead>
          <tr>
            <th onclick="toggleSort()" style="cursor:pointer; white-space:nowrap" title="تغيير الترتيب">الوقت <i id="sort-icon" class="fas fa-sort-numeric-down"></i></th>
            <th>المستخدم</th>
            <th>العملية</th>
            <th>التفاصيل</th>
          </tr>
        </thead>
        <tbody id="logs-tbody">
          <tr>
            <td colspan="4" style="text-align:center;padding:30px">
              <div class="spinner"></div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div id="pagination-container" class="flex justify-center flex-wrap gap-10 mt-20 mb-30"></div>

<script>
  let currentPage = 1;
  let currentLimit = 10;
  let sortAsc = false;

  function toggleSort() {
    sortAsc = !sortAsc;
    document.getElementById('sort-icon').className = sortAsc ? 'fas fa-sort-numeric-up' : 'fas fa-sort-numeric-down';
    loadLogs(1);
  }

  async function loadLogs(page = 1) {
    currentPage = page;
    const offset = (page - 1) * currentLimit;
    const sortParam = sortAsc ? 'asc' : 'desc';
    
    // Show spinner
    document.getElementById('logs-tbody').innerHTML = '<tr><td colspan="4" style="text-align:center;padding:30px"><div class="spinner"></div></td></tr>';

    const res = await apiCall(`/api/activity.php?limit=${currentLimit}&offset=${offset}&sort=${sortParam}`);
    if (!res.success) return;

    const logs = res.data.logs;
    const tbody = document.getElementById('logs-tbody');
    
    if (logs.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:30px">لا توجد سجلات بعد</td></tr>';
      renderPagination(0, currentLimit, currentPage, 'pagination-container', 'loadLogs');
      return;
    }

    tbody.innerHTML = logs.map(log => `
      <tr>
        <td style="font-size:.85rem; color:var(--text-muted)">${formatDate(log.created_at)}</td>
        <td>
          <div style="font-weight:700">${log.user_name || 'نظام'}</div>
          <small class="badge badge-info" style="font-size:.7rem">${log.user_role || '-'}</small>
        </td>
        <td><span class="badge ${getActionClass(log.action)}">${log.action}</span></td>
        <td style="max-width:300px; font-size:.9rem">${log.details || '-'}</td>
      </tr>
    `).join('');

    renderPagination(res.data.total, currentLimit, currentPage, 'pagination-container', 'loadLogs');
  }

  function getActionClass(action) {
    if (action.includes('حذف')) return 'badge-danger';
    if (action.includes('إلغاء') || action.includes('مرتجع')) return 'badge-warning';
    if (action.includes('إضافة') || action.includes('دفع')) return 'badge-success';
    return 'badge-info';
  }

  document.addEventListener('DOMContentLoaded', () => loadLogs(1));
</script>

<?php adminFooter(); ?>
