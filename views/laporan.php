<?php
/** @var array $tenant  @var array $license  @var array $user  @var string $from  @var string $to  @var array $report */
$s = $report['summary'];
$presets = [
    'Hari ini' => [date('Y-m-d'), date('Y-m-d')],
    '7 hari' => [date('Y-m-d', strtotime('-6 days')), date('Y-m-d')],
    '30 hari' => [date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
];
$fmtDate = function ($date) { return date('d M Y', strtotime($date)); };
render('page_head', ['title' => 'Laporan — ' . $license['store_name']]);
?>
<body>
  <?php render('app_header', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'activePage' => 'laporan']); ?>

  <main class="page-layout">
    <section class="users-panel">
      <div class="section-top">
        <div>
          <h2>Laporan Penjualan</h2>
          <p class="users-sub"><?php echo e($fmtDate($from)); ?><?php echo $from !== $to ? ' – ' . e($fmtDate($to)) : ''; ?></p>
        </div>
        <form method="get" class="inline-form report-filter">
          <input type="hidden" name="tenant" value="<?php echo e($tenant['domain']); ?>">
          <?php foreach ($presets as $label => [$pFrom, $pTo]): ?>
            <a class="chip <?php echo $from === $pFrom && $to === $pTo ? 'active' : ''; ?>" href="<?php echo e(page_url('laporan.php', ['from' => $pFrom, 'to' => $pTo])); ?>"><?php echo e($label); ?></a>
          <?php endforeach; ?>
          <label class="sr-only" for="from">Dari</label>
          <input id="from" type="date" name="from" value="<?php echo e($from); ?>">
          <label class="sr-only" for="to">Sampai</label>
          <input id="to" type="date" name="to" value="<?php echo e($to); ?>">
          <button type="submit" class="btn btn-sm btn-primary">Tampilkan</button>
        </form>
      </div>

      <div class="stat-row">
        <div class="stat-tile"><span>Omzet (termasuk PPN)</span><strong><?php echo e(rupiah($s['revenue'])); ?></strong></div>
        <div class="stat-tile"><span>Transaksi</span><strong><?php echo (int) $s['transactions']; ?></strong></div>
        <div class="stat-tile"><span>Rata-rata / transaksi</span><strong><?php echo e(rupiah($s['average'])); ?></strong></div>
        <div class="stat-tile"><span>PPN terkumpul</span><strong><?php echo e(rupiah($s['tax'])); ?></strong></div>
      </div>
    </section>

    <?php if (!$s['transactions']): ?>
      <section class="users-panel"><p class="users-sub">Belum ada transaksi pada periode ini.</p></section>
    <?php else: ?>
      <div class="report-grid">
        <section class="users-panel">
          <h2>Produk Terlaris</h2>
          <table class="users-table compact">
            <thead><tr><th>Produk</th><th class="cell-num">Terjual</th><th class="cell-num">Omzet</th></tr></thead>
            <tbody>
              <?php foreach ($report['products'] as $row): ?>
                <tr><td><?php echo e($row['product_name']); ?></td><td class="cell-num"><?php echo (int) $row['qty']; ?></td><td class="cell-num"><?php echo e(rupiah($row['revenue'])); ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>

        <section class="users-panel">
          <h2>Per Kasir</h2>
          <table class="users-table compact">
            <thead><tr><th>Kasir</th><th class="cell-num">Transaksi</th><th class="cell-num">Omzet</th></tr></thead>
            <tbody>
              <?php foreach ($report['cashiers'] as $row): ?>
                <tr><td><?php echo e($row['name']); ?></td><td class="cell-num"><?php echo (int) $row['transactions']; ?></td><td class="cell-num"><?php echo e(rupiah($row['revenue'])); ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>

        <section class="users-panel">
          <h2>Per Hari</h2>
          <table class="users-table compact">
            <thead><tr><th>Tanggal</th><th class="cell-num">Transaksi</th><th class="cell-num">Omzet</th></tr></thead>
            <tbody>
              <?php foreach ($report['daily'] as $row): ?>
                <tr><td><?php echo e($fmtDate($row['day'])); ?></td><td class="cell-num"><?php echo (int) $row['transactions']; ?></td><td class="cell-num"><?php echo e(rupiah($row['revenue'])); ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>

        <section class="users-panel">
          <h2>Transaksi Terakhir</h2>
          <table class="users-table compact">
            <thead><tr><th>No. Transaksi</th><th>Kasir</th><th class="cell-num">Item</th><th class="cell-num">Total</th></tr></thead>
            <tbody>
              <?php foreach ($report['recent'] as $row): ?>
                <tr>
                  <td><span class="mono"><?php echo e($row['invoice']); ?></span><div class="cell-sub"><?php echo e(date('d M H:i', strtotime($row['created_at']))); ?></div></td>
                  <td><?php echo e($row['cashier']); ?></td>
                  <td class="cell-num"><?php echo (int) $row['items']; ?></td>
                  <td class="cell-num"><?php echo e(rupiah($row['total'])); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </section>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
