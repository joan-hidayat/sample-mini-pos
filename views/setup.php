<?php
/** @var array $license  @var array $old  @var string|null $error  @var bool $hasProof */
ob_start();
?>
<h1 class="auth-title">Buat Akun Admin</h1>
<p class="auth-lead">
  Lisensi <strong><?php echo e($license['app_name']); ?></strong> untuk toko ini sudah aktif.
  Buat akun admin pertama untuk mulai memakai aplikasi. Gunakan email yang Anda daftarkan saat berlangganan.
</p>

<?php if ($error): ?><div class="auth-alert error"><?php echo e($error); ?></div><?php endif; ?>

<form method="post" class="auth-form" novalidate>
  <?php echo csrf_field(); ?>
  <?php if (!$hasProof): ?>
    <label for="license_key">License key</label>
    <input id="license_key" name="license_key" type="text" required autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="LIC-MINIPOS-…">
    <small class="auth-hint">Halaman ini dibuka tanpa link aktivasi. Masukkan license key dari Software Showcase → 🔎 Cek Pesanan (no. pesanan + email pemesan).</small>
  <?php endif; ?>

  <label for="name">Nama lengkap</label>
  <input id="name" name="name" type="text" autocomplete="name" required value="<?php echo e($old['name'] ?? $license['licensee_name']); ?>">

  <label for="email">Email pendaftaran</label>
  <input id="email" name="email" type="email" autocomplete="username" required value="<?php echo e($old['email'] ?? ''); ?>" placeholder="email yang dipakai saat berlangganan">

  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="new-password" required minlength="<?php echo (int) app_config('password_min_length'); ?>">
  <small class="auth-hint">Minimal <?php echo (int) app_config('password_min_length'); ?> karakter.</small>

  <label for="password_confirm">Ulangi password</label>
  <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>

  <button type="submit" class="btn btn-success btn-block">Buat Akun & Masuk</button>
</form>
<?php
$content = ob_get_clean();
render('layout_auth', ['title' => 'Buat Akun Admin', 'content' => $content, 'license' => $license]);
