<?php
/** @var array $tenant  @var array $license  @var array $user  @var array $products */
$storeTitle = $license['store_name'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($storeTitle); ?> — Mini POS Express</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=20261102_t43">
  <script>
    window.__CLIENT_CONFIG__ = {
      tenant: <?php echo json_encode($tenant['domain']); ?>,
      storeTitle: <?php echo json_encode($storeTitle); ?>,
      cashierName: <?php echo json_encode($user['name']); ?>,
      csrfToken: <?php echo json_encode(csrf_token()); ?>,
      // Modul STOK (T-28): stok ditampilkan & dibatasi; tanpa modul ini stok diabaikan
      trackStock: <?php echo json_encode(has_module($license, 'STOK')); ?>,
      products: <?php echo json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>
    };
  </script>
</head>
<body>

  <?php render('app_header', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'activePage' => 'pos']); ?>

  <!-- MAIN CASHIER APP -->
  <main class="main-layout">
    <!-- LEFT: MENU PRODUK -->
    <section class="menu-section">
      <div class="section-top">
        <h2>Katalog Menu Kasir</h2>
        <span class="item-count" id="productCount"></span>
      </div>

      <div class="product-grid" id="productGrid">
        <!-- Rendered by JS -->
      </div>
    </section>

    <!-- RIGHT: ORDER SUMMARY & CART -->
    <aside class="cart-section">
      <div class="cart-header">
        <h2>Keranjang Pesanan</h2>
        <button id="btnClearCart" class="btn-clear">Kosongkan</button>
      </div>

      <div class="cart-items" id="cartItemsList">
        <div class="cart-empty">
          <div style="font-size: 32px; margin-bottom: 8px;">🛒</div>
          <p>Belum ada produk dipilih</p>
          <small>Klik menu di sebelah kiri untuk menambah transaksi</small>
        </div>
      </div>

      <div class="cart-summary">
        <div class="summary-row">
          <span>Subtotal</span>
          <span id="subtotalVal">Rp 0</span>
        </div>
        <div class="summary-row">
          <span>Pajak PPN (11%)</span>
          <span id="taxVal">Rp 0</span>
        </div>
        <div class="summary-row total">
          <span>Total Pembayaran</span>
          <span id="totalVal">Rp 0</span>
        </div>

        <button id="btnCheckout" class="btn btn-success btn-block" disabled>
          💳 Bayar & Cetak Struk
        </button>

        <div class="telemetry-box mt-3">
          <button id="btnTestValidate" class="btn btn-outline-info btn-block btn-sm">
            📡 Kirim Heartbeat Telemetri ke LMD
          </button>
          <small id="telemetryStatus" class="telemetry-text">Lisensi terverifikasi oleh Central SaaS</small>
        </div>
      </div>
    </aside>
  </main>

  <!-- MODAL NOTA STRUK -->
  <div id="receiptModal" class="modal-overlay hidden">
    <div class="receipt-card">
      <div class="receipt-header">
        <h3 id="receiptStoreName"><?php echo e($storeTitle); ?></h3>
        <p id="receiptDate">23/10/2026</p>
        <p class="receipt-divider">================================</p>
      </div>
      <div class="receipt-body" id="receiptItems"></div>
      <div class="receipt-footer">
        <p class="receipt-divider">================================</p>
        <div class="receipt-total-row">
          <span>TOTAL:</span>
          <strong id="receiptTotal">Rp 0</strong>
        </div>
        <p class="receipt-note">Terima kasih atas kunjungan Anda!</p>
        <p id="receiptLicenseCode" class="receipt-license"></p>
        <button id="btnCloseReceipt" class="btn btn-primary btn-block mt-3">Selesai & Transaksi Baru</button>
      </div>
    </div>
  </div>

  <script src="app.js?v=20261030_t28"></script>
</body>
</html>
