<?php
/** @var array $tenant  @var array $license  @var array $user  @var array $items */
render('page_head', ['title' => 'Notifikasi — ' . $license['store_name']]);
$channel = app_config('notify')['channel'] ?? 'mailbox';
?>
<body>
  <?php render('app_header', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'activePage' => 'notifikasi']); ?>

  <main class="page-layout">
    <section class="users-panel">
      <div class="section-top">
        <div>
          <h2>Notifikasi Akun</h2>
          <p class="users-sub">
            Email otomatis ke user toko: akun dibuat, password diganti, akun dinonaktifkan/diaktifkan, akun dikunci.
            <?php if ($channel === 'mailbox'): ?>Mode kotak surat lokal — tidak dikirim keluar, dibaca di sini.<?php endif; ?>
            Notifikasi pesanan & pembayaran dikirim oleh Central SaaS.
          </p>
        </div>
      </div>
      <?php if (!$items): ?>
        <p class="users-sub">Belum ada notifikasi.</p>
      <?php endif; ?>
      <div class="notif-list">
        <?php foreach ($items as $n): ?>
          <details class="notif-item">
            <summary>
              <span class="notif-subject"><?php echo e($n['subject']); ?></span>
              <span class="cell-sub"><?php echo e($n['recipient']); ?> · <?php echo e(date('d M Y H:i', strtotime($n['created_at']))); ?> · <?php echo e($n['status']); ?></span>
            </summary>
            <pre class="notif-body"><?php echo e($n['body']); ?></pre>
          </details>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
