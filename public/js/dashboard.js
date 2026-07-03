/* ======================================================
   CloudVM Pro — Dashboard & Admin JavaScript
   ====================================================== */

// ===== Chart.js Helpers =====
window.CloudVMCharts = {
  defaults: {
    fontFamily: "'Outfit', sans-serif",
    gridColor: getComputedStyle(document.documentElement).getPropertyValue('--border') || 'rgba(0,0,0,0.06)',
    textColor: getComputedStyle(document.documentElement).getPropertyValue('--text-3') || '#888'
  },

  createLineChart(ctx, labels, datasets, options = {}) {
    return new Chart(ctx, {
      type: 'line',
      data: { labels, datasets: datasets.map(d => ({
        tension: 0.4, fill: true, borderWidth: 2, pointRadius: 0,
        pointHoverRadius: 5, ...d
      })) },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 }, color: this.defaults.textColor } },
          y: { grid: { color: this.defaults.gridColor }, ticks: { font: { size: 11 }, color: this.defaults.textColor } }
        },
        ...options
      }
    });
  },

  createDoughnutChart(ctx, labels, data, colors) {
    return new Chart(ctx, {
      type: 'doughnut',
      data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }] },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '75%',
        plugins: { legend: { position: 'bottom', labels: { font: { size: 12 }, padding: 16 } } }
      }
    });
  },

  createBarChart(ctx, labels, datasets) {
    return new Chart(ctx, {
      type: 'bar',
      data: { labels, datasets: datasets.map(d => ({ borderRadius: 6, ...d })) },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: datasets.length > 1 } },
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 }, color: this.defaults.textColor } },
          y: { grid: { color: this.defaults.gridColor }, ticks: { font: { size: 11 }, color: this.defaults.textColor } }
        }
      }
    });
  }
};

// ===== VM Operations =====
window.VMOperations = {
  async perform(vmId, action, label) {
    if (['stop', 'reset', 'reinstall'].includes(action)) {
      const confirmed = await this.confirm(
        `确认${label}？`,
        action === 'stop' ? '虚拟机将被关闭，正在运行的服务会中断。' :
        action === 'reset' ? '这将重置 root 密码，当前密码将失效。' :
        '重装系统将清除系统盘所有数据，数据盘保留。请提前备份重要数据！'
      );
      if (!confirmed) return;
    }

    const btn = document.querySelector(`[data-vm="${vmId}"][data-action="${action}"]`);
    if (btn) { btn.disabled = true; btn.innerHTML = '<div class="spinner" style="width:14px;height:14px"></div>'; }

    try {
      // Simulate API call
      await new Promise(r => setTimeout(r, 1500));
      window.showNotification('操作成功', `虚拟机 #${vmId} ${label} 指令已发送`, 'success');

      // Update status indicators
      if (action === 'start') this.updateStatus(vmId, 'running');
      if (action === 'stop') this.updateStatus(vmId, 'stopped');
    } catch (e) {
      window.showNotification('操作失败', '请稍后重试或联系支持', 'error');
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = label; }
    }
  },

  updateStatus(vmId, status) {
    const dot = document.querySelector(`[data-vm-status="${vmId}"]`);
    if (dot) {
      dot.className = `vm-status-dot ${status}`;
    }
  },

  confirm(title, msg) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.className = 'modal-overlay open';
      overlay.innerHTML = `
        <div class="modal" style="max-width:380px">
          <div class="modal-header"><h3>${title}</h3></div>
          <div class="modal-body"><p style="color:var(--text-2);font-size:14px;line-height:1.6">${msg}</p></div>
          <div class="modal-footer">
            <button class="btn-sm default" id="confirmCancel">取消</button>
            <button class="btn-sm danger" id="confirmOk">确认执行</button>
          </div>
        </div>`;
      document.body.appendChild(overlay);
      document.getElementById('confirmCancel').onclick = () => { overlay.remove(); resolve(false); };
      document.getElementById('confirmOk').onclick = () => { overlay.remove(); resolve(true); };
    });
  },

  openVNC(vmId) {
    window.open(`/vnc.html?vm=${vmId}`, '_blank', 'width=1024,height=700,menubar=no,toolbar=no');
  },

  openSSH(vmId) {
    window.open(`/ssh.html?vm=${vmId}`, '_blank', 'width=900,height=600,menubar=no,toolbar=no');
  }
};

// ===== Sidebar Mobile Toggle =====
window.toggleSidebar = function() {
  const sidebar = document.querySelector('.sidebar, .admin-sidebar');
  sidebar?.classList.toggle('open');
};

// ===== Modal Manager =====
window.ModalManager = {
  open(id) {
    document.getElementById(id)?.classList.add('open');
  },
  close(id) {
    document.getElementById(id)?.classList.remove('open');
  },
  closeAll() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
  }
};

// Close modal on overlay click
document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
});

// ===== Tab System =====
window.switchTab = function(tabGroup, tabId) {
  document.querySelectorAll(`[data-tab-group="${tabGroup}"]`).forEach(el => {
    el.classList.toggle('active', el.dataset.tab === tabId);
  });
  document.querySelectorAll(`[data-panel-group="${tabGroup}"]`).forEach(el => {
    el.classList.toggle('active', el.dataset.panel === tabId);
  });
};

// ===== Config Nav =====
window.switchConfig = function(panelId) {
  document.querySelectorAll('.config-nav-item').forEach(el => {
    el.classList.toggle('active', el.dataset.panel === panelId);
  });
  document.querySelectorAll('.config-panel').forEach(el => {
    el.classList.toggle('active', el.id === panelId);
  });
};

// ===== Table Search =====
window.filterTable = function(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;

  input.addEventListener('input', () => {
    const query = input.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
    });
  });
};

// ===== Form Validation =====
window.validateForm = function(formId) {
  const form = document.getElementById(formId);
  if (!form) return false;
  let valid = true;

  form.querySelectorAll('[data-required]').forEach(input => {
    const error = input.parentElement.querySelector('.form-error');
    if (!input.value.trim()) {
      input.style.borderColor = 'var(--red)';
      if (error) error.classList.add('show');
      valid = false;
    } else {
      input.style.borderColor = '';
      if (error) error.classList.remove('show');
    }
  });

  return valid;
};

// ===== Copy to Clipboard =====
window.copyToClipboard = function(text, label = '已复制') {
  navigator.clipboard.writeText(text).then(() => {
    window.showNotification('复制成功', label, 'success');
  });
};

// ===== Usage Bar Animation =====
window.animateUsageBars = function() {
  document.querySelectorAll('.vm-usage-fill, .node-usage-fill').forEach(bar => {
    const pct = bar.dataset.pct || '0';
    bar.style.width = '0%';
    setTimeout(() => { bar.style.width = pct + '%'; bar.style.transition = 'width 0.8s cubic-bezier(0.16,1,0.3,1)'; }, 100);

    // Color coding
    const p = parseInt(pct);
    if (p > 85) bar.style.background = 'var(--red)';
    else if (p > 65) bar.style.background = 'var(--orange)';
  });
};

// ===== Countdown Timer =====
window.startCountdown = function(seconds, btnEl, originalText) {
  let remaining = seconds;
  btnEl.disabled = true;
  const interval = setInterval(() => {
    remaining--;
    btnEl.textContent = `重发 (${remaining}s)`;
    if (remaining <= 0) {
      clearInterval(interval);
      btnEl.disabled = false;
      btnEl.textContent = originalText;
    }
  }, 1000);
};

// ===== INIT =====
document.addEventListener('DOMContentLoaded', () => {
  animateUsageBars();
  SidebarActive?.init?.();
});
