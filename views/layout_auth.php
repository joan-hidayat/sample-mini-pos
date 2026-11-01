<?php
/** @var string $title  @var string $content  @var array|null $license */
$storeName = $license['store_name'] ?? 'Mini POS Express';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo e($title); ?> — <?php echo e($storeName); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=20261102_t43">
</head>
<body class="auth-body">
  <main class="auth-shell">
    <div class="auth-brand">
      <div class="brand-icon">⚡</div>
      <div>
        <div class="brand-name"><?php echo e($storeName); ?></div>
        <div class="brand-sub">Mini POS Express<?php if (!empty($_SESSION['tenant'])): ?> · <?php echo e($_SESSION['tenant']); ?><?php endif; ?></div>
      </div>
    </div>
    <section class="auth-card">
      <?php $flash = flash(); if ($flash): ?>
        <div class="auth-alert <?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
      <?php endif; ?>
      <?php echo $content; ?>
    </section>
    <p class="auth-footer">Lisensi dikelola oleh Central SaaS LMD</p>
  </main>
</body>
</html>
