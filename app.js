// MINI POS EXPRESS - SMART CLIENT APPLICATION (PROTECTED RUNTIME)
const STORAGE_LICENSE_KEY = 'minipos_license_data';
const STORAGE_CART_KEY = 'minipos_cart';

const PRODUCTS_CATALOG = [
  { id: 1, name: 'Kopi Espresso Blend', category: 'Minuman', price: 18000, emoji: 'â˜•' },
  { id: 2, name: 'Matcha Latte Premium', category: 'Minuman', price: 24000, emoji: 'ðŸµ' },
  { id: 3, name: 'Croissant Coklat', category: 'Pastry', price: 22000, emoji: 'ðŸ¥' },
  { id: 4, name: 'Sandwich Daging Asap', category: 'Makanan', price: 28000, emoji: 'ðŸ¥ª' },
  { id: 5, name: 'Air Mineral Prima 600ml', category: 'Minuman', price: 6000, emoji: 'ðŸ’§' },
  { id: 6, name: 'Donat Glaze Vanilla', category: 'Camilan', price: 12000, emoji: 'ðŸ©' }
];

const posApp = {
  license: null,
  cart: [],

  init() {
    this.checkLicenseAndGuard();
    this.renderProducts();
    this.bindEvents();
    this.updateCartUI();
  },

  checkLicenseAndGuard() {
    const urlParams = new URLSearchParams(window.location.search);
    const urlTenant = urlParams.get('tenant') || (window.__CLIENT_CONFIG__ && window.__CLIENT_CONFIG__.tenant);
    const urlLic = urlParams.get('lic') || (window.__CLIENT_CONFIG__ && window.__CLIENT_CONFIG__.lic);

    const host = window.location.hostname || 'localhost';
    const port = window.location.port ? ':' + window.location.port : ':8080';
    const showcaseUrl = `${window.location.protocol}//${host}${port}/software-showcase/?notice=unauthorized_direct_access`;

    // 1. GATEKEEPER CHECK: Jika parameter URL tidak ada, tendang langsung ke Showcase
    if (!urlTenant || !urlLic) {
      window.location.replace(showcaseUrl);
      return;
    }

    this.license = {
      license_key: urlLic,
      store_name: (window.__CLIENT_CONFIG__ && window.__CLIENT_CONFIG__.storeTitle) 
        ? window.__CLIENT_CONFIG__.storeTitle 
        : urlTenant.replace(/\.pos\.local$/, '').toUpperCase(),
      instance_domain: urlTenant,
      status: 'active'
    };

    localStorage.setItem(STORAGE_LICENSE_KEY, JSON.stringify(this.license));

    const badge = document.getElementById('licenseBadge');
    const badgeText = document.getElementById('licenseStatusText');
    if (badge && badgeText) {
      badge.className = 'license-badge active';
      badgeText.textContent = `âš¡ Aktif (${this.license.license_key.substring(0, 16)}...)`;
    }

    // Lakukan validasi background pertama kali ke Central SaaS
    this.verifyWithLmd(urlLic, urlTenant);
  },

  async verifyWithLmd(lic, domain) {
    const host = window.location.hostname || 'localhost';
    const port = window.location.port ? ':' + window.location.port : ':8080';
    const apiUrl = `${window.location.protocol}//${host}${port}/License-Management-Dashboard/api/licenses/validate`;

    try {
      const resp = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          license_key: lic,
          instance_key: 'INST-STORE-' + lic.substring(lic.length - 8),
          domain: domain
        })
      });

      const res = await resp.json();
      const isValid = (res.valid === true) || (res.data && res.data.valid === true);
      if (!isValid) {
        alert('Lisensi Anda tidak valid atau telah ditangguhkan oleh Central SaaS. Anda akan dialihkan ke Software Showcase.');
        window.location.replace(`${window.location.protocol}//${host}${port}/software-showcase/?notice=invalid_license`);
      }
    } catch (e) {
      console.warn('Background LMD validation warning:', e);
    }
  },

  async sendHeartbeat() {
    if (!this.license || !this.license.license_key) {
      alert('Lisensi belum aktif!');
      return;
    }

    const statusEl = document.getElementById('telemetryStatus');
    statusEl.textContent = 'Mengirim heartbeat telemetri ke Central SaaS...';

    const host = window.location.hostname || 'localhost';
    const port = window.location.port ? ':' + window.location.port : ':8080';
    const apiUrl = `${window.location.protocol}//${host}${port}/License-Management-Dashboard/api/licenses/validate`;

    try {
      const resp = await fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          license_key: this.license.license_key,
          instance_key: 'INST-STORE-' + this.license.license_key.substring(this.license.license_key.length - 8),
          domain: this.license.instance_domain || (window.location.hostname || 'localhost')
        })
      });

      const res = await resp.json();
      const isValid = (res.valid === true) || (res.data && res.data.valid === true);
      if (isValid) {
        statusEl.innerHTML = `ðŸŸ¢ Terhubung & Valid! Status: <strong>${res.data.application || 'Active'}</strong>`;
      } else {
        statusEl.innerHTML = `ðŸ”´ Lisensi tidak valid: ${res.message}`;
      }
    } catch (e) {
      statusEl.textContent = 'Koneksi heartbeat gagal: ' + e.message;
    }
  },

  renderProducts() {
    const grid = document.getElementById('productGrid');
    if (!grid) return;
    grid.innerHTML = PRODUCTS_CATALOG.map(p => `
      <div class="product-card" onclick="posApp.addToCart(${p.id})">
        <div>
          <div class="product-emoji">${p.emoji}</div>
          <h3 class="product-title">${p.name}</h3>
          <span class="product-category">${p.category}</span>
        </div>
        <div class="product-price">Rp ${p.price.toLocaleString('id-ID')}</div>
      </div>
    `).join('');
  },

  addToCart(prodId) {
    const item = PRODUCTS_CATALOG.find(p => p.id === prodId);
    if (!item) return;

    const existing = this.cart.find(c => c.id === prodId);
    if (existing) {
      existing.qty++;
    } else {
      this.cart.push({ ...item, qty: 1 });
    }
    this.updateCartUI();
  },

  changeQty(prodId, delta) {
    const idx = this.cart.findIndex(c => c.id === prodId);
    if (idx === -1) return;

    this.cart[idx].qty += delta;
    if (this.cart[idx].qty <= 0) {
      this.cart.splice(idx, 1);
    }
    this.updateCartUI();
  },

  clearCart() {
    this.cart = [];
    this.updateCartUI();
  },

  updateCartUI() {
    const list = document.getElementById('cartItemsList');
    const btnCheckout = document.getElementById('btnCheckout');
    const subtotalEl = document.getElementById('subtotalVal');
    const taxEl = document.getElementById('taxVal');
    const totalEl = document.getElementById('totalVal');

    if (!list) return;

    if (this.cart.length === 0) {
      list.innerHTML = `
        <div class="cart-empty">
          <div style="font-size: 32px; margin-bottom: 8px;">ðŸ›’</div>
          <p>Belum ada produk dipilih</p>
          <small>Klik menu di sebelah kiri untuk menambah transaksi</small>
        </div>
      `;
      if (btnCheckout) btnCheckout.disabled = true;
      if (subtotalEl) subtotalEl.textContent = 'Rp 0';
      if (taxEl) taxEl.textContent = 'Rp 0';
      if (totalEl) totalEl.textContent = 'Rp 0';
      return;
    }

    if (btnCheckout) btnCheckout.disabled = false;
    let subtotal = 0;

    list.innerHTML = this.cart.map(c => {
      const itemTotal = c.price * c.qty;
      subtotal += itemTotal;
      return `
        <div class="cart-item">
          <div class="item-info">
            <strong>${c.emoji} ${c.name}</strong>
            <span>Rp ${c.price.toLocaleString('id-ID')} x ${c.qty}</span>
          </div>
          <div class="item-controls">
            <button class="qty-btn" onclick="posApp.changeQty(${c.id}, -1)">-</button>
            <span style="font-size:13px; font-weight:700;">${c.qty}</span>
            <button class="qty-btn" onclick="posApp.changeQty(${c.id}, 1)">+</button>
          </div>
        </div>
      `;
    }).join('');

    const tax = Math.round(subtotal * 0.11);
    const total = subtotal + tax;

    if (subtotalEl) subtotalEl.textContent = `Rp ${subtotal.toLocaleString('id-ID')}`;
    if (taxEl) taxEl.textContent = `Rp ${tax.toLocaleString('id-ID')}`;
    if (totalEl) totalEl.textContent = `Rp ${total.toLocaleString('id-ID')}`;
  },

  checkout() {
    if (this.cart.length === 0) return;

    const receiptModal = document.getElementById('receiptModal');
    const receiptItems = document.getElementById('receiptItems');
    const receiptTotal = document.getElementById('receiptTotal');
    const receiptStoreName = document.getElementById('receiptStoreName');
    const receiptLicense = document.getElementById('receiptLicenseCode');
    const receiptDate = document.getElementById('receiptDate');

    if (receiptStoreName) receiptStoreName.textContent = (this.license && this.license.store_name) ? this.license.store_name : 'Mini POS Express';
    if (receiptDate) receiptDate.textContent = new Date().toLocaleString('id-ID');
    if (receiptLicense) receiptLicense.textContent = 'Lisensi: ' + (this.license ? this.license.license_key : 'UNLICENSED');

    let subtotal = 0;
    if (receiptItems) {
      receiptItems.innerHTML = this.cart.map(c => {
        const lineTotal = c.price * c.qty;
        subtotal += lineTotal;
        return `
          <div class="receipt-item-row">
            <span>${c.name} x${c.qty}</span>
            <span>${lineTotal.toLocaleString('id-ID')}</span>
          </div>
        `;
      }).join('');

      const tax = Math.round(subtotal * 0.11);
      const grandTotal = subtotal + tax;

      receiptItems.innerHTML += `
        <div class="receipt-item-row" style="color:#6b7280; font-size:11px; margin-top:4px;">
          <span>PPN (11%)</span>
          <span>${tax.toLocaleString('id-ID')}</span>
        </div>
      `;

      if (receiptTotal) receiptTotal.textContent = `Rp ${grandTotal.toLocaleString('id-ID')}`;
    }

    if (receiptModal) receiptModal.classList.remove('hidden');

    // Kirim telemetri heartbeat ke LMD
    this.sendHeartbeat();
  },

  bindEvents() {
    const btnClear = document.getElementById('btnClearCart');
    if (btnClear) btnClear.addEventListener('click', () => this.clearCart());

    const btnCheckout = document.getElementById('btnCheckout');
    if (btnCheckout) btnCheckout.addEventListener('click', () => this.checkout());

    const btnCloseReceipt = document.getElementById('btnCloseReceipt');
    if (btnCloseReceipt) btnCloseReceipt.addEventListener('click', () => {
      document.getElementById('receiptModal').classList.add('hidden');
      this.clearCart();
    });

    const btnTestValidate = document.getElementById('btnTestValidate');
    if (btnTestValidate) btnTestValidate.addEventListener('click', () => this.sendHeartbeat());
  }
};

document.addEventListener('DOMContentLoaded', () => posApp.init());


