<?php
/** @var array $license  @var string $email  @var string|null $error */
ob_start();
?>
<h1 class="auth-title">Masuk</h1>
<p class="auth-lead">Masuk dengan akun yang dibuat oleh admin toko.</p>

<?php if ($error): ?><div class="auth-alert error"><?php echo e($error); ?></div><?php endif; ?>
<?php if (!empty($license['offline'])): ?>
  <div class="auth-alert warning">Server lisensi sedang tidak terjangkau. Aplikasi berjalan dalam mode tenggang.</div>
<?php endif; ?>

<form method="post" class="auth-form" novalidate>
  <?php echo csrf_field(); ?>
  <label for="email">Email</label>
  <input id="email" name="email" type="email" autocomplete="username" required autofocus value="<?php echo e($email); ?>">

  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>

  <button type="submit" class="btn btn-primary btn-block">Masuk</button>
</form>
<p class="auth-hint auth-center">Lupa password? Kasir: minta admin toko mengganti password Anda. Admin: hubungi penyedia layanan untuk mendapatkan link reset.</p>
<?php
$content = ob_get_clean();
render('layout_auth', ['title' => 'Masuk', 'content' => $content, 'license' => $license]);
