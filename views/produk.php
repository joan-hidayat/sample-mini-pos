<?php
/** @var array $tenant  @var array $license  @var array $user  @var array $products  @var bool $trackStock  @var array $old */
$activeCount = count(array_filter($products, function ($p) { return $p['status'] === 'active'; }));
render('page_head', ['title' => 'Kelola Produk — ' . $license['store_name']]);
?>
<body>
  <?php render('app_header', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'activePage' => 'produk']); ?>

  <main class="users-layout">
    <section class="users-panel">
      <div class="section-top">
        <div>
          <h2>Kelola Produk</h2>
          <p class="users-sub">Produk aktif tampil di kasir. Mengubah harga tidak mengubah transaksi yang sudah tercatat.</p>
        </div>
        <div class="quota-meter">
          <strong><?php echo $activeCount; ?> / <?php echo count($products); ?></strong>
          <span>produk aktif</span>
        </div>
      </div>

      <div class="users-table-wrap">
        <table class="users-table">
          <thead>
            <tr><th>Produk</th><th class="cell-num">Harga</th><th>Status</th><th>Aksi</th></tr>
          </thead>
          <tbody>
            <?php foreach ($products as $p): $id = (int) $p['id']; ?>
              <tr class="<?php echo $p['status'] === 'inactive' ? 'is-inactive' : ''; ?>">
                <td>
                  <strong><?php echo e(($p['emoji'] ? $p['emoji'] . ' ' : '') . $p['name']); ?></strong>
                  <div class="cell-sub"><?php echo e($p['category']); ?><?php echo $trackStock ? ' • stok ' . (int) $p['stock'] : ''; ?></div>
                </td>
                <td class="cell-num"><?php echo e(rupiah($p['price'])); ?></td>
                <td><span class="status-pill <?php echo e($p['status']); ?>"><?php echo $p['status'] === 'active' ? 'Aktif' : 'Nonaktif'; ?></span></td>
                <td>
                  <div class="cell-actions">
                    <details class="row-menu">
                      <summary>Ubah</summary>
                      <form method="post" class="product-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                        <label for="name-<?php echo $id; ?>">Nama</label>
                        <input id="name-<?php echo $id; ?>" name="name" maxlength="150" required value="<?php echo e($p['name']); ?>">
                        <label for="cat-<?php echo $id; ?>">Kategori</label>
                        <input id="cat-<?php echo $id; ?>" name="category" maxlength="80" value="<?php echo e($p['category']); ?>">
                        <label for="emoji-<?php echo $id; ?>">Ikon</label>
                        <input id="emoji-<?php echo $id; ?>" name="emoji" maxlength="16" class="input-emoji" value="<?php echo e($p['emoji']); ?>">
                        <label for="price-<?php echo $id; ?>">Harga (Rp)</label>
                        <input id="price-<?php echo $id; ?>" name="price" type="number" min="1" max="<?php echo ProductRepository::MAX_PRICE; ?>" required value="<?php echo (int) $p['price']; ?>">
                        <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                      </form>
                    </details>
                    <form method="post" class="inline-form">
                      <?php echo csrf_field(); ?>
                      <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                      <?php if ($p['status'] === 'active'): ?>
                        <input type="hidden" name="action" value="deactivate">
                        <button type="submit" class="btn btn-sm btn-ghost-danger">Nonaktifkan</button>
                      <?php else: ?>
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="btn btn-sm btn-ghost">Aktifkan</button>
                      <?php endif; ?>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <aside class="users-panel">
      <h2>Tambah Produk</h2>
      <form method="post" class="auth-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="create">
        <label for="new-name">Nama produk</label>
        <input id="new-name" name="name" maxlength="150" required value="<?php echo e($old['name'] ?? ''); ?>">
        <label for="new-category">Kategori</label>
        <input id="new-category" name="category" maxlength="80" placeholder="Umum" value="<?php echo e($old['category'] ?? ''); ?>">
        <label for="new-emoji">Ikon (1 emoji, opsional)</label>
        <input id="new-emoji" name="emoji" maxlength="16" placeholder="🍞" value="<?php echo e($old['emoji'] ?? ''); ?>">
        <label for="new-price">Harga (Rp, tanpa titik)</label>
        <input id="new-price" name="price" type="number" min="1" max="<?php echo ProductRepository::MAX_PRICE; ?>" required value="<?php echo e($old['price'] ?? ''); ?>">
        <?php if ($trackStock): ?>
          <label for="new-stock">Stok awal</label>
          <input id="new-stock" name="stock" type="number" min="0" required value="<?php echo e($old['stock'] ?? '0'); ?>">
        <?php else: ?>
          <small class="auth-hint">Stok tidak dihitung di paket Anda (modul Stok tersedia di paket Pro).</small>
        <?php endif; ?>
        <button type="submit" class="btn btn-success btn-block">Tambah Produk</button>
      </form>
    </aside>
  </main>
</body>
</html>
