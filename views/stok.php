<?php
/** @var array $tenant  @var array $license  @var array $user  @var array $products */
$lowCount = count(array_filter($products, function ($p) { return $p['stock'] <= $p['min_stock']; }));
render('page_head', ['title' => 'Stok — ' . $license['store_name']]);
?>
<body>
  <?php render('app_header', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'activePage' => 'stok']); ?>

  <main class="page-layout">
    <section class="users-panel">
      <div class="section-top">
        <div>
          <h2>Stok Produk</h2>
          <p class="users-sub">Stok berkurang otomatis setiap transaksi kasir. Penjualan melebihi stok ditolak.</p>
        </div>
        <div class="quota-meter <?php echo $lowCount ? 'full' : ''; ?>">
          <strong><?php echo $lowCount; ?></strong>
          <span>produk stok menipis</span>
        </div>
      </div>

      <div class="users-table-wrap">
        <table class="users-table">
          <thead>
            <tr><th>Produk</th><th>Harga</th><th>Stok</th><th>Atur stok</th></tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): $low = $p['stock'] <= $p['min_stock']; ?>
              <tr>
                <td>
                  <strong><?php echo e($p['emoji'] . ' ' . $p['name']); ?></strong>
                  <div class="cell-sub"><?php echo e($p['category']); ?></div>
                </td>
                <td class="cell-num"><?php echo e(rupiah($p['price'])); ?></td>
                <td>
                  <span class="status-pill <?php echo $p['stock'] <= 0 ? 'inactive' : ($low ? 'low' : 'active'); ?>">
                    <?php echo $p['stock'] <= 0 ? 'Habis' : (int) $p['stock']; ?>
                  </span>
                  <div class="cell-sub">min. <?php echo (int) $p['min_stock']; ?></div>
                </td>
                <td>
                  <form method="post" class="inline-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="product_id" value="<?php echo (int) $p['id']; ?>">
                    <label class="sr-only" for="stock-<?php echo (int) $p['id']; ?>">Stok <?php echo e($p['name']); ?></label>
                    <input id="stock-<?php echo (int) $p['id']; ?>" class="input-num" type="number" name="stock" min="0" value="<?php echo (int) max(0, $p['stock']); ?>" required>
                    <label class="sr-only" for="min-<?php echo (int) $p['id']; ?>">Stok minimum <?php echo e($p['name']); ?></label>
                    <input id="min-<?php echo (int) $p['id']; ?>" class="input-num" type="number" name="min_stock" min="0" value="<?php echo (int) $p['min_stock']; ?>" required title="Stok minimum">
                    <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
