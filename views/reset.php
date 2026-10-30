<?php
/** @var array $license  @var array $admins  @var string|null $error */
$minPassword = (int) app_config('password_min_length');
ob_start();
?>
<h1 class="auth-title">Reset Password Admin</h1>
<p class="auth-lead">Link ini diterbitkan oleh penyedia layanan dan hanya bisa dipakai sekali. Pilih akun admin, lalu buat password baru.</p>

<?php if ($error): ?><div class="auth-alert error"><?php echo e($error); ?></div><?php endif; ?>

<?php if (!$admins): ?>
  <div class="auth-alert warning">Tidak ada akun admin aktif di toko ini. Hubungi penyedia layanan.</div>
<?php else: ?>
<form method="post" class="auth-form" novalidate>
  <?php echo csrf_field(); ?>
  <label for="user_id">Akun admin</label>
  <select id="user_id" name="user_id" required>
    <?php foreach ($admins as $admin): ?>
      <option value="<?php echo (int) $admin['id']; ?>"><?php echo e($admin['name'] . ' — ' . $admin['email']); ?></option>
    <?php endforeach; ?>
  </select>

  <label for="password">Password baru</label>
  <input id="password" name="password" type="password" autocomplete="new-password" required minlength="<?php echo $minPassword; ?>">
  <small class="auth-hint">Minimal <?php echo $minPassword; ?> karakter.</small>

  <label for="password_confirm">Ulangi password baru</label>
  <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>

  <button type="submit" class="btn btn-success btn-block">Simpan Password Baru</button>
</form>
<?php endif; ?>
<?php
$content = ob_get_clean();
render('layout_auth', ['title' => 'Reset Password Admin', 'content' => $content, 'license' => $license]);
