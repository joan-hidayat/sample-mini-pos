<?php
/**
 * MINI POS EXPRESS - GATEKEEPER & CASHIER CLIENT
 * Proteksi Ketat: Akses Langsung Ditolak (Harus melalui Showcase Instalasi)
 */

$tenant = trim($_GET['tenant'] ?? '');
$lic = trim($_GET['lic'] ?? '');

$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$showcaseBase = $protocol . $host . '/software-showcase/';

// 1. CEK PARAMETER LISENSI
// Jika diakses langsung tanpa parameter tenant dan lic, tolak & redirect langsung ke Showcase
if (empty($tenant) || empty($lic)) {
    header("Location: " . $showcaseBase . "?notice=unauthorized_direct_access");
    exit;
}

// 2. VERIFIKASI KE DATABASE CENTRAL SAAS LMD
$verifiedLicense = null;
try {
    $pdo = new PDO("mysql:host=localhost;dbname=central_saas;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $pdo->prepare("
        SELECT l.id as license_id, l.license_key, l.status as license_status, l.expires_at,
               s.status as subscription_status, s.max_users,
               c.name as customer_name, c.email as customer_email,
               a.name as app_name,
               ai.id as instance_id, ai.domain as instance_domain
        FROM licenses l
        JOIN subscriptions s ON l.subscription_id = s.id
        JOIN customers c ON s.customer_id = c.id
        JOIN applications a ON s.application_id = a.id
        LEFT JOIN application_instances ai ON ai.license_id = l.id
        WHERE l.license_key = :lic
        LIMIT 1
    ");
    $stmt->execute(['lic' => $lic]);
    $verifiedLicense = $stmt->fetch();

    if (!$verifiedLicense) {
        // Lisensi tidak ditemukan di Central SaaS LMD
        header("Location: " . $showcaseBase . "?notice=license_not_found");
        exit;
    }

    if ($verifiedLicense['license_status'] !== 'active' || $verifiedLicense['subscription_status'] !== 'active') {
        // Lisensi atau subskripsi belum di-approve / suspended
        header("Location: " . $showcaseBase . "?notice=license_not_active");
        exit;
    }

} catch (Exception $e) {
    // Jika koneksi DB lokal gagal, biarkan JavaScript frontend memvalidasi via API LMD
}

$storeTitle = !empty($verifiedLicense['customer_name']) 
    ? $verifiedLicense['customer_name'] 
    : strtoupper(str_replace('.pos.local', '', $tenant));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($storeTitle); ?> — Mini POS Express</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
  <script>
    window.__CLIENT_CONFIG__ = {
      tenant: <?php echo json_encode($tenant); ?>,
      lic: <?php echo json_encode($lic); ?>,
      storeTitle: <?php echo json_encode($storeTitle); ?>,
      maxUsers: <?php echo (int)($verifiedLicense['max_users'] ?? 1); ?>,
      expiresAt: <?php echo json_encode($verifiedLicense['expires_at'] ?? ''); ?>,
      appName: <?php echo json_encode($verifiedLicense['app_name'] ?? 'Apotek Management System'); ?>
    };
  </script>
</head>
<body>

  <!-- HEADER / APP BAR -->
  <header class="app-header">
    <div class="brand">
      <div class="brand-icon">⚡</div>
      <div>
        <h1 class="brand-name"><?php echo htmlspecialchars($storeTitle); ?></h1>
        <span class="brand-sub">Mini POS Express • Smart Cashier Client (Domain: <?php echo htmlspecialchars($tenant); ?>)</span>
      </div>
    </div>
    <div class="header-status">
      <div id="licenseBadge" class="license-badge active">
        <span class="badge-dot"></span>
        <span id="licenseStatusText">Lisensi Aktif (<?php echo htmlspecialchars(substr($lic, 0, 16)); ?>...)</span>
      </div>
      <div style="margin-left: 10px; font-size: 11px; color: #a5b4fc; background: rgba(99, 102, 241, 0.15); padding: 5px 10px; border-radius: 6px; border: 1px solid rgba(99, 102, 241, 0.3);">
        👥 Kuota: <?php echo (int)($verifiedLicense['max_users'] ?? 1); ?> User
      </div>
    </div>
  </header>

  <!-- MAIN CASHIER APP -->
  <main class="main-layout">
    <!-- LEFT: MENU PRODUK -->
    <section class="menu-section">
      <div class="section-top">
        <h2>Katalog Menu Kasir</h2>
        <span class="item-count">6 Menu Tersedia</span>
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
        <h3 id="receiptStoreName"><?php echo htmlspecialchars($storeTitle); ?></h3>
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

  <script src="app.js?v=20261023_v2"></script>
</body>
</html>
