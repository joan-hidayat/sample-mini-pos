// MINI POS EXPRESS - SMART CLIENT APPLICATION (PROTECTED RUNTIME)
// Produk & transaksi milik server (api.php, T-28): browser hanya mengirim id + qty, harga/total dihitung ulang di server.

const formatRp = (n) => `Rp ${Number(n).toLocaleString('id-ID')}`;
const escapeHtml = (s) => String(s).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

const posApp = {
  config: {},
  products: [],
  cart: [],
  busy: false,

  init() {
    this.config = window.__CLIENT_CONFIG__ || {};
    this.products = this.config.products || [];
    this.renderProducts();
    this.bindEvents();
    this.updateCartUI();
  },

  async sendHeartbeat() {
    const statusEl = document.getElementById('telemetryStatus');
    statusEl.textContent = 'Mengirim heartbeat telemetri ke Central SaaS...';

    try {
      // Divalidasi dari server Mini POS (request ke LMD ditandatangani instance secret, T-22)
      const resp = await fetch(`heartbeat.php?tenant=${encodeURIComponent(this.config.tenant)}`, {
        headers: { 'Accept': 'application/json' }
      });
      if (!resp.headers.get('content-type')?.includes('application/json')) {
        window.location.reload();  // lisensi ditolak: server mengirim halaman penjelasan
        return;
      }

      const res = await resp.json();
      if (res.valid) {
        statusEl.innerHTML = res.offline
          ? '🟡 Mode tenggang: Central SaaS tidak terjangkau'
          : `🟢 Terhubung & Valid! Status: <strong>${escapeHtml(res.application || 'Active')}</strong>`;
      } else {
        statusEl.textContent = `🔴 Lisensi tidak valid: ${res.message}`;
      }
    } catch (e) {
      statusEl.textContent = 'Koneksi heartbeat gagal: ' + e.message;
    }
  },

  // Sisa stok yang masih bisa ditambahkan ke keranjang; Infinity bila modul STOK tidak aktif
  available(prodId) {
    if (!this.config.trackStock) return Infinity;
    const product = this.products.find(p => p.id === prodId);
    const inCart = this.cart.find(c => c.id === prodId)?.qty || 0;
    return product ? product.stock - inCart : 0;
  },

  renderProducts() {
    const grid = document.getElementById('productGrid');
    if (!grid) return;
    const count = document.getElementById('productCount');
    if (count) count.textContent = `${this.products.length} Menu Tersedia`;

    grid.innerHTML = this.products.map(p => {
      const soldOut = this.config.trackStock && p.stock <= 0;
      const stockInfo = this.config.trackStock
        ? `<span class="product-stock ${p.stock <= p.min_stock ? 'low' : ''}">${soldOut ? 'Stok habis' : `Stok: ${p.stock}`}</span>`
        : '';
      return `
        <div class="product-card ${soldOut ? 'sold-out' : ''}" onclick="posApp.addToCart(${p.id})">
          <div>
            <div class="product-emoji">${escapeHtml(p.emoji || '🛍️')}</div>
            <h3 class="product-title">${escapeHtml(p.name)}</h3>
            <span class="product-category">${escapeHtml(p.category)}</span>
            ${stockInfo}
          </div>
          <div class="product-price">${formatRp(p.price)}</div>
        </div>
      `;
    }).join('');
  },

  addToCart(prodId) {
    const item = this.products.find(p => p.id === prodId);
    if (!item || this.available(prodId) <= 0) return;

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
    if (delta > 0 && this.available(prodId) <= 0) return;

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
          <div style="font-size: 32px; margin-bottom: 8px;">🛒</div>
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

    if (btnCheckout) btnCheckout.disabled = this.busy;
    let subtotal = 0;

    list.innerHTML = this.cart.map(c => {
      subtotal += c.price * c.qty;
      return `
        <div class="cart-item">
          <div class="item-info">
            <strong>${escapeHtml(c.emoji || '')} ${escapeHtml(c.name)}</strong>
            <span>${formatRp(c.price)} x ${c.qty}</span>
          </div>
          <div class="item-controls">
            <button class="qty-btn" onclick="posApp.changeQty(${c.id}, -1)">-</button>
            <span style="font-size:13px; font-weight:700;">${c.qty}</span>
            <button class="qty-btn" onclick="posApp.changeQty(${c.id}, 1)" ${this.available(c.id) <= 0 ? 'disabled' : ''}>+</button>
          </div>
        </div>
      `;
    }).join('');

    // Perkiraan untuk tampilan; angka final (struk & laporan) dari server
    const tax = Math.round(subtotal * 0.11);
    if (subtotalEl) subtotalEl.textContent = formatRp(subtotal);
    if (taxEl) taxEl.textContent = formatRp(tax);
    if (totalEl) totalEl.textContent = formatRp(subtotal + tax);
  },

  async checkout() {
    if (this.cart.length === 0 || this.busy) return;
    this.busy = true;
    this.updateCartUI();

    try {
      const resp = await fetch(`api.php?tenant=${encodeURIComponent(this.config.tenant)}&action=checkout`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': this.config.csrfToken },
        body: JSON.stringify({ items: this.cart.map(c => ({ id: c.id, qty: c.qty })) })
      });
      if (!resp.headers.get('content-type')?.includes('application/json')) {
        window.location.reload();  // lisensi ditolak / sesi habis: server mengirim halaman penjelasan
        return;
      }
      const res = await resp.json();
      if (!res.ok) {
        if (resp.status === 401) window.location.reload();
        alert(res.message || 'Transaksi gagal disimpan.');
        return;
      }

      // Stok terbaru dari server (modul STOK)
      Object.entries(res.sale.stock || {}).forEach(([id, stock]) => {
        const product = this.products.find(p => p.id === Number(id));
        if (product) product.stock = stock;
      });
      this.renderProducts();
      this.showReceipt(res.sale);
    } catch (e) {
      alert('Koneksi ke server Mini POS gagal: ' + e.message);
    } finally {
      this.busy = false;
      this.updateCartUI();
    }
  },

  showReceipt(sale) {
    document.getElementById('receiptStoreName').textContent = this.config.storeTitle || 'Mini POS Express';
    document.getElementById('receiptDate').textContent = `${sale.invoice} • ${new Date(sale.created_at.replace(' ', 'T')).toLocaleString('id-ID')}`;
    document.getElementById('receiptLicenseCode').textContent = `Kasir: ${this.config.cashierName}`;
    document.getElementById('receiptItems').innerHTML = sale.items.map(i => `
      <div class="receipt-item-row">
        <span>${escapeHtml(i.product_name)} x${i.qty}</span>
        <span>${Number(i.line_total).toLocaleString('id-ID')}</span>
      </div>
    `).join('') + `
      <div class="receipt-item-row" style="color:#6b7280; font-size:11px; margin-top:4px;">
        <span>PPN (11%)</span>
        <span>${Number(sale.tax).toLocaleString('id-ID')}</span>
      </div>
    `;
    document.getElementById('receiptTotal').textContent = formatRp(sale.total);
    document.getElementById('receiptModal').classList.remove('hidden');
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
