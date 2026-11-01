<?php
/** @var array $tenant  @var array $license  @var array $user  @var int $activeUsers */
$sub = $license['subscription'] ?? [];
$fmt = function ($d) { return $d ? date('d M Y', strtotime($d)) : '-'; };
$cycles = ['monthly' => 'Bulanan', 'quarterly' => '3 bulan', 'semi_annual' => '6 bulan', 'annual' => 'Tahunan', 'one_time' => 'Sekali bayar'];
$daysLeft = !empty($license['expires_at']) ? (int) floor((strtotime($license['expires_at']) - time()) / 86400) : null;
$showcase = showcase_url();
render('page_head', ['title' => 'Langganan — ' . $license['store_name']]);
?>
<body>
  <?php render('app_header', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'activePage' => 'langganan']); ?>

  <main class="page-layout">
    <section class="users-panel">
      <div class="section-top">
        <div>
          <h2>Langganan</h2>
          <p class="users-sub">Informasi lisensi toko ini dari Central SaaS.</p>
        </div>
        <?php if ($daysLeft !== null): ?>
          <div class="quota-meter <?php echo $daysLeft <= 7 ? 'full' : ''; ?>">
            <strong><?php echo max(0, $daysLeft); ?></strong>
            <span>hari tersisa</span>
          </div>
        <?php endif; ?>
      </div>

      <div class="stat-row">
        <div class="stat-tile"><span>No. pesanan</span><strong class="mono-lg"><?php echo e($sub['subscription_no'] ?? '-'); ?></strong></div>
        <div class="stat-tile"><span>Paket</span><strong><?php echo e(($sub['package_name'] ?? '-') . (isset($sub['billing_cycle']) ? ' · ' . ($cycles[$sub['billing_cycle']] ?? $sub['billing_cycle']) : '')); ?></strong></div>
        <div class="stat-tile"><span>Masa aktif</span><strong><?php echo e($fmt($sub['start_date'] ?? null) . ' – ' . $fmt($license['expires_at'] ?? null)); ?></strong></div>
        <div class="stat-tile"><span>User aktif</span><strong><?php echo (int) $activeUsers; ?> / <?php echo (int) $license['max_users']; ?></strong></div>
      </div>
    </section>

    <section class="users-panel">
      <h2>Fitur dalam paket</h2>
      <div class="module-list">
        <?php foreach (APP_MODULES as $code => $module): $owned = has_module($license, $code); ?>
          <span class="module-chip <?php echo $owned ? '' : 'off'; ?>"><?php echo $owned ? '✓' : '✕'; ?> <?php echo e($module['label']); ?><?php echo $owned && !$module['ready'] ? ' (segera hadir)' : ''; ?></span>
        <?php endforeach; ?>
      </div>
      <p class="users-sub" style="margin-top:10px;">License key: <span class="mono"><?php echo e($license['license_key']); ?></span> · Domain: <span class="mono"><?php echo e($tenant['domain']); ?></span></p>
    </section>

    <section class="users-panel">
      <h2>Dokumen & bantuan</h2>
      <div class="link-row">
        <?php if (!empty($sub['receipt_url'])): ?>
          <a class="btn btn-sm btn-primary" href="<?php echo e($sub['receipt_url']); ?>" target="_blank" rel="noopener">🧾 Bukti pembayaran</a>
        <?php endif; ?>
        <?php if (!empty($sub['subscription_no'])): ?>
          <a class="btn btn-sm btn-ghost" href="<?php echo e($showcase . '?order=' . rawurlencode($sub['subscription_no'])); ?>" target="_blank" rel="noopener">🔎 Cek Pesanan di Showcase</a>
        <?php endif; ?>
        <a class="btn btn-sm btn-ghost" href="<?php echo e($showcase); ?>" target="_blank" rel="noopener">⬆ Upgrade / perpanjang</a>
      </div>
      <?php if (empty($sub)): ?>
        <p class="users-sub" style="margin-top:10px;">Info langganan belum tersedia. Muat ulang halaman setelah aplikasi tersambung ke Central SaaS.</p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
