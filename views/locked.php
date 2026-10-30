<?php
/** @var array $tenant  @var array $license  @var array $user  @var array $module  @var string $code */
$owned = has_module($license, $code);
render('page_head', ['title' => $module['label'] . ' — ' . $license['store_name']]);
?>
<body>
  <?php render('app_header', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'activePage' => $module['key']]); ?>

  <main class="page-layout">
    <section class="users-panel locked-panel">
      <?php if ($owned): ?>
        <div class="locked-icon">🚧</div>
        <h2><?php echo e($module['label']); ?> segera hadir</h2>
        <p class="users-sub">Modul ini sudah termasuk paket Anda. Fiturnya masih dalam pengembangan dan akan aktif otomatis setelah rilis, tanpa perlu membeli ulang.</p>
      <?php else: ?>
        <div class="locked-icon">🔒</div>
        <h2>Modul <?php echo e($module['label']); ?> tidak termasuk paket Anda</h2>
        <p class="users-sub">
          Paket lisensi toko ini hanya mencakup:
          <strong><?php echo e(implode(', ', array_map(function ($c) { return APP_MODULES[$c]['label'] ?? $c; }, $license['modules'])) ?: 'tidak ada modul'); ?></strong>.
          <?php if ($user['role'] === 'admin'): ?>
            Upgrade paket untuk membuka fitur ini.
          <?php else: ?>
            Hubungi admin toko untuk upgrade paket.
          <?php endif; ?>
        </p>
        <?php if ($user['role'] === 'admin'): ?>
          <a class="btn btn-primary" href="<?php echo e(showcase_url()); ?>" target="_blank" rel="noopener">Lihat paket di Software Showcase</a>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
