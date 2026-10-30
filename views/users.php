<?php
/** @var array $tenant  @var array $license  @var array $user  @var array $users  @var int $activeCount  @var array $old */
$quotaFull = $activeCount >= $license['max_users'];
$minPassword = (int) app_config('password_min_length');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kelola User — <?php echo e($license['store_name']); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=20261030_t28">
</head>
<body>
  <?php render('app_header', ['tenant' => $tenant, 'license' => $license, 'user' => $user, 'activePage' => 'users']); ?>

  <main class="users-layout">
    <section class="users-panel">
      <div class="section-top">
        <div>
          <h2>Kelola User</h2>
          <p class="users-sub">Akun yang boleh masuk ke kasir toko ini.</p>
        </div>
        <div class="quota-meter <?php echo $quotaFull ? 'full' : ''; ?>">
          <strong><?php echo $activeCount; ?> / <?php echo (int) $license['max_users']; ?></strong>
          <span>user aktif (kuota lisensi)</span>
        </div>
      </div>

      <div class="users-table-wrap">
        <table class="users-table">
          <thead>
            <tr><th>Nama</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th>Aksi</th></tr>
          </thead>
          <tbody>
            <?php foreach ($users as $row): $isSelf = (int) $row['id'] === (int) $user['id']; ?>
              <tr class="<?php echo $row['status'] === 'inactive' ? 'is-inactive' : ''; ?>">
                <td>
                  <strong><?php echo e($row['name']); ?><?php if ($isSelf): ?> <span class="tag-self">Anda</span><?php endif; ?></strong>
                  <div class="cell-sub"><?php echo e($row['email']); ?></div>
                </td>
                <td><?php echo e(UserRepository::ROLES[$row['role']]); ?></td>
                <td><span class="status-pill <?php echo e($row['status']); ?>"><?php echo $row['status'] === 'active' ? 'Aktif' : 'Nonaktif'; ?></span></td>
                <td class="cell-sub"><?php echo $row['last_login_at'] ? e(date('d M Y H:i', strtotime($row['last_login_at']))) : 'Belum pernah'; ?></td>
                <td>
                  <div class="cell-actions">
                    <details class="row-menu">
                      <summary>Ganti password</summary>
                      <form method="post" class="inline-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                        <label class="sr-only" for="np-<?php echo (int) $row['id']; ?>">Password baru</label>
                        <input id="np-<?php echo (int) $row['id']; ?>" type="password" name="new_password" minlength="<?php echo $minPassword; ?>" placeholder="Password baru" required autocomplete="new-password">
                        <button type="submit" class="btn btn-sm btn-primary">Simpan</button>
                      </form>
                    </details>
                    <?php if (!$isSelf): ?>
                      <form method="post" class="inline-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                        <?php if ($row['status'] === 'active'): ?>
                          <input type="hidden" name="action" value="deactivate">
                          <button type="submit" class="btn btn-sm btn-ghost-danger">Nonaktifkan</button>
                        <?php else: ?>
                          <input type="hidden" name="action" value="activate">
                          <button type="submit" class="btn btn-sm btn-ghost" <?php echo $quotaFull ? 'disabled title="Kuota penuh"' : ''; ?>>Aktifkan</button>
                        <?php endif; ?>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <aside class="users-panel">
      <h2>Tambah User</h2>
      <?php if ($quotaFull): ?>
        <p class="quota-note">Kuota lisensi penuh (<?php echo (int) $license['max_users']; ?> user aktif). Nonaktifkan user lain atau upgrade paket di Central SaaS untuk menambah user.</p>
      <?php else: ?>
        <form method="post" class="auth-form" novalidate>
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="create">
          <label for="new-name">Nama</label>
          <input id="new-name" name="name" type="text" required value="<?php echo e($old['name'] ?? ''); ?>">
          <label for="new-email">Email</label>
          <input id="new-email" name="email" type="email" required autocomplete="off" value="<?php echo e($old['email'] ?? ''); ?>">
          <label for="new-role">Peran</label>
          <select id="new-role" name="role">
            <?php foreach (UserRepository::ROLES as $value => $label): ?>
              <option value="<?php echo e($value); ?>" <?php echo ($old['role'] ?? 'kasir') === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
            <?php endforeach; ?>
          </select>
          <label for="new-password">Password awal</label>
          <input id="new-password" name="password" type="password" required minlength="<?php echo $minPassword; ?>" autocomplete="new-password">
          <small class="auth-hint">Minimal <?php echo $minPassword; ?> karakter. Sampaikan ke user secara langsung.</small>
          <button type="submit" class="btn btn-success btn-block">Tambah User</button>
        </form>
      <?php endif; ?>
    </aside>
  </main>
</body>
</html>
