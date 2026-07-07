/**
 * PVE Panel - Unified API Client
 * Real API integration for all admin pages
 */
(function () {
  'use strict';

  const API_BASE = '/api';
  const ADMIN_BASE = '/api/admin';

  // ===================== Auth Management =====================
  const Auth = {
    getToken() { return localStorage.getItem('admin_token'); },
    setToken(t) { localStorage.setItem('admin_token', t); },
    getUser() { try { return JSON.parse(localStorage.getItem('admin_user') || 'null'); } catch (e) { return null; } },
    setUser(u) { localStorage.setItem('admin_user', JSON.stringify(u)); },
    isLoggedIn() { return !!this.getToken(); },
    isAdmin() { const u = this.getUser(); return u && u.role === 'admin'; },
    logout() {
      // Try server-side logout
      if (this.getToken()) {
        fetch(API_BASE + '/logout', {
          method: 'POST',
          headers: { 'Authorization': 'Bearer ' + this.getToken(), 'Accept': 'application/json' }
        }).catch(() => {});
      }
      localStorage.removeItem('admin_token');
      localStorage.removeItem('admin_user');
      if (window.location.pathname.indexOf('/views/admin/') !== -1) {
        window.location.href = '/views/auth/login.html';
      }
    },
    guard() {
      if (!this.isLoggedIn()) {
        window.location.href = '/views/auth/login.html?redirect=' + encodeURIComponent(window.location.href);
        return false;
      }
      if (!this.isAdmin()) {
        this.logout();
        return false;
      }
      return true;
    }
  };

  // ===================== HTTP Client =====================
  async function apiRequest(endpoint, options = {}) {
    const token = Auth.getToken();
    const headers = { 'Accept': 'application/json', ...(options.headers || {}) };

    if (token) headers['Authorization'] = 'Bearer ' + token;

    if (!(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }

    const config = { method: 'GET', headers, ...options };
    if (config.body && typeof config.body === 'object' && !(config.body instanceof FormData)) {
      config.body = JSON.stringify(config.body);
    }

    try {
      const response = await fetch(API_BASE + endpoint, config);
      if (response.status === 401) { Auth.logout(); throw new Error('登录已过期，请重新登录'); }
      const data = await response.json();
      return data;
    } catch (err) {
      if (err.message === 'Failed to fetch') {
        return { success: false, message: '网络连接失败，请检查服务器状态' };
      }
      return { success: false, message: err.message };
    }
  }

  function adminApi(endpoint, options = {}) {
    return apiRequest('/admin' + endpoint, options);
  }

  function userApi(endpoint, options = {}) {
    return apiRequest(endpoint, options);
  }

  // Build query string helper
  function qs(params) {
    const filtered = {};
    for (const k in params) {
      if (params[k] !== '' && params[k] !== null && params[k] !== undefined) filtered[k] = params[k];
    }
    const s = new URLSearchParams(filtered).toString();
    return s ? '?' + s : '';
  }

  // ===================== API Methods =====================
  window.API = {
    Auth: Auth,

    // --- Auth ---
    login(email, password) {
      return userApi('/login', { method: 'POST', body: { email, password } });
    },
    me() { return userApi('/me'); },

    // --- Dashboard ---
    dashboard() { return adminApi('/dashboard'); },

    // --- Nodes ---
    getNodes() { return adminApi('/nodes'); },
    getNode(id) { return adminApi('/nodes/' + id); },
    createNode(data) { return adminApi('/nodes', { method: 'POST', body: data }); },
    updateNode(id, data) { return adminApi('/nodes/' + id, { method: 'PUT', body: data }); },
    deleteNode(id) { return adminApi('/nodes/' + id, { method: 'DELETE' }); },
    testNode(id) { return adminApi('/nodes/' + id + '/test', { method: 'POST' }); },
    syncNodeVms(id) { return adminApi('/nodes/' + id + '/sync-vms', { method: 'POST' }); },
    syncNodeTemplates(id) { return adminApi('/nodes/' + id + '/sync-templates', { method: 'POST' }); },
    getNodeTemplates(id) { return adminApi('/nodes/' + id + '/templates'); },
    updateNodeNat(id, data) { return adminApi('/nodes/' + id + '/nat-config', { method: 'PUT', body: data }); },

    // --- Users ---
    getUsers(params) { return adminApi('/users' + qs(params || {})); },
    getUser(id) { return adminApi('/users/' + id); },
    updateUser(id, data) { return adminApi('/users/' + id, { method: 'PUT', body: data }); },
    disableUser(id) { return adminApi('/users/' + id + '/disable', { method: 'POST' }); },
    enableUser(id) { return adminApi('/users/' + id + '/enable', { method: 'POST' }); },
    deleteUser(id) { return adminApi('/users/' + id, { method: 'DELETE' }); },

    // --- Products ---
    getProducts() { return adminApi('/products'); },
    getProduct(id) { return adminApi('/products/' + id); },
    createProduct(data) { return adminApi('/products', { method: 'POST', body: data }); },
    updateProduct(id, data) { return adminApi('/products/' + id, { method: 'PUT', body: data }); },
    deleteProduct(id) { return adminApi('/products/' + id, { method: 'DELETE' }); },
    toggleProductStatus(id) { return adminApi('/products/' + id + '/status', { method: 'PUT' }); },

    // --- VMs ---
    getVms(params) { return adminApi('/vms' + qs(params || {})); },
    getVm(id) { return adminApi('/vms/' + id); },
    updateVm(id, data) { return adminApi('/vms/' + id, { method: 'PUT', body: data }); },
    startVm(id) { return adminApi('/vms/' + id + '/start', { method: 'POST' }); },
    stopVm(id) { return adminApi('/vms/' + id + '/stop', { method: 'POST' }); },
    restartVm(id) { return adminApi('/vms/' + id + '/restart', { method: 'POST' }); },
    suspendVm(id) { return adminApi('/vms/' + id + '/suspend', { method: 'POST' }); },
    unsuspendVm(id) { return adminApi('/vms/' + id + '/unsuspend', { method: 'POST' }); },
    deleteVm(id) { return adminApi('/vms/' + id, { method: 'DELETE' }); },
    batchVm(action, ids) { return adminApi('/vms/batch', { method: 'POST', body: { action, ids } }); },

    // --- Orders ---
    getOrders(params) { return adminApi('/orders' + qs(params || {})); },
    getOrder(id) { return adminApi('/orders/' + id); },
    markPaid(id, data) { return adminApi('/orders/' + id + '/mark-paid', { method: 'POST', body: data || {} }); },
    refundOrder(id) { return adminApi('/orders/' + id + '/refund', { method: 'POST' }); },
    getOrderStats() { return adminApi('/orders-stats'); },

    // --- Coupons ---
    getCoupons() { return adminApi('/coupons'); },
    createCoupon(data) { return adminApi('/coupons', { method: 'POST', body: data }); },
    updateCoupon(id, data) { return adminApi('/coupons/' + id, { method: 'PUT', body: data }); },
    deleteCoupon(id) { return adminApi('/coupons/' + id, { method: 'DELETE' }); },
    toggleCouponStatus(id) { return adminApi('/coupons/' + id + '/status', { method: 'PUT' }); },
    batchCreateCoupons(data) { return adminApi('/coupons/batch', { method: 'POST', body: data }); },

    // --- Announcements ---
    getAnnouncements() { return adminApi('/announcements'); },
    createAnnouncement(data) { return adminApi('/announcements', { method: 'POST', body: data }); },
    updateAnnouncement(id, data) { return adminApi('/announcements/' + id, { method: 'PUT', body: data }); },
    deleteAnnouncement(id) { return adminApi('/announcements/' + id, { method: 'DELETE' }); },
    togglePin(id) { return adminApi('/announcements/' + id + '/pin', { method: 'PUT' }); },
    toggleAnnouncementStatus(id) { return adminApi('/announcements/' + id + '/status', { method: 'PUT' }); },

    // --- Tickets ---
    getTickets() { return adminApi('/tickets'); },
    getTicket(id) { return adminApi('/tickets/' + id); },
    replyTicket(id, data) { return adminApi('/tickets/' + id + '/reply', { method: 'POST', body: data }); },
    updateTicketStatus(id, data) { return adminApi('/tickets/' + id + '/status', { method: 'PUT', body: data }); },
    updateTicketPriority(id, data) { return adminApi('/tickets/' + id + '/priority', { method: 'PUT', body: data }); },

    // --- Verifications ---
    getVerifications() { return adminApi('/verifications'); },
    getVerification(id) { return adminApi('/verifications/' + id); },
    approveVerification(id) { return adminApi('/verifications/' + id + '/approve', { method: 'POST' }); },
    rejectVerification(id) { return adminApi('/verifications/' + id + '/reject', { method: 'POST' }); },

    // --- Backups ---
    getBackups() { return adminApi('/backups'); },
    createBackup(data) { return adminApi('/backups', { method: 'POST', body: data || { type: 'database' } }); },
    restoreBackup(id) { return adminApi('/backups/' + id + '/restore', { method: 'POST' }); },
    deleteBackup(id) { return adminApi('/backups/' + id, { method: 'DELETE' }); },
    downloadBackup(id) { return adminApi('/backups/' + id + '/download'); },
    getBackupSettings() { return adminApi('/backup-settings'); },
    updateBackupSettings(data) { return adminApi('/backup-settings', { method: 'PUT', body: data }); },

    // --- Settings ---
    getSettings(group) { return adminApi('/settings/' + group); },
    updateSettings(group, data) { return adminApi('/settings/' + group, { method: 'PUT', body: data }); },
    testSmtp(data) { return adminApi('/settings/test-smtp', { method: 'POST', body: data }); },
    testEpay(data) { return adminApi('/settings/test-epay', { method: 'POST', body: data }); },

    // --- Email Templates ---
    getEmailTemplates() { return adminApi('/settings/email-templates'); },
    updateEmailTemplate(id, data) { return adminApi('/settings/email-templates/' + id, { method: 'PUT', body: data }); },
    previewEmailTemplate(id) { return adminApi('/settings/email-templates/' + id + '/preview'); },
  };

  // ===================== UI Helpers =====================
  window.UI = {
    notify(msg, type) {
      if (typeof window.showNotification === 'function') {
        return window.showNotification(type === 'error' ? '错误' : '成功', msg, type || 'success');
      }
      const types = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
      alert((types[type] || '') + ' ' + msg);
    },

    loading(el, text) {
      if (typeof el === 'string') el = document.getElementById(el);
      if (!el) return;
      el.innerHTML = '<div class="spinner" style="width:20px;height:20px;margin:0 auto"></div>';
      if (text) el.innerHTML += '<span style="margin-left:8px">' + text + '</span>';
    },

    formatDate(d) {
      if (!d) return '-';
      return new Date(d).toLocaleString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    },

    formatMoney(n) {
      return '¥' + parseFloat(n || 0).toFixed(2);
    },

    statusBadge(status, map) {
      const def = { active: ['success', '正常'], inactive: ['default', '停用'], disabled: ['danger', '禁用'],
        online: ['success', '在线'], offline: ['danger', '离线'], maintenance: ['warning', '维护'],
        running: ['success', '运行中'], stopped: ['default', '已关机'], suspended: ['warning', '已暂停'],
        creating: ['info', '创建中'], deleting: ['danger', '删除中'], error: ['danger', '异常'],
        pending: ['warning', '待处理'], paid: ['success', '已支付'], failed: ['danger', '失败'],
        refunded: ['default', '已退款'],
        unverified: ['default', '未认证'], verified: ['success', '已认证'],
        open: ['warning', '开启'], closed: ['success', '已关闭'],
      };
      const m = map || def;
      const c = m[status] || ['default', status];
      return '<span class="status-badge status-' + c[0] + '">' + c[1] + '</span>';
    }
  };
})();
