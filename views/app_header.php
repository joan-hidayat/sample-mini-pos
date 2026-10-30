<?php
/** @var array $tenant  @var array $license  @var array $user  @var string $activePage */
$flash = flash();
?>
  <header class="app-header">
    <div class="brand">
      <div class="brand-icon">⚡</div>
      <div>
        <h1 class="brand-name"><?php echo e($license['store_name']); ?></h1>
        <span class="brand-sub">Mini POS Express • <?php echo e($tenant['domain']); ?></span>
      </div>
    </div>
    <nav class="app-nav" aria-label="Menu utama">
      <?php foreach (APP_MODULES as $code => $module):
        if ($module['admin'] && $user['role'] !== 'admin') continue;
        $owned = has_module($license, $code); ?>
        <a href="<?php echo e(page_url($module['page'])); ?>"
           class="<?php echo trim(($activePage === $module['key'] ? 'active ' : '') . ($owned ? '' : 'locked')); ?>"
           <?php echo $owned ? '' : 'title="Tidak termasuk paket Anda — upgrade untuk membuka"'; ?>>
          <?php echo e($module['label']); ?><?php if (!$owned): ?> <span class="nav-lock" aria-label="terkunci">🔒</span><?php elseif (!$module['ready']): ?> <span class="nav-soon">segera</span><?php endif; ?>
        </a>
      <?php endforeach; ?>
      <?php if ($user['role'] === 'admin'): ?>
        <?php if (has_module($license, 'KASIR')): ?>
          <a href="<?php echo e(page_url('produk.php')); ?>" class="<?php echo $activePage === 'produk' ? 'active' : ''; ?>">Produk</a>
        <?php endif; ?>
        <a href="<?php echo e(page_url('users.php')); ?>" class="<?php echo $activePage === 'users' ? 'active' : ''; ?>">Kelola User</a>
      <?php endif; ?>
    </nav>
    <div class="header-status">
      <div id="licenseBadge" class="license-badge <?php echo !empty($license['offline']) ? 'unverified' : 'active'; ?>">
        <span class="badge-dot"></span>
        <span id="licenseStatusText"><?php echo !empty($license['offline']) ? 'Mode tenggang (LMD offline)' : 'Lisensi aktif'; ?></span>
      </div>
      <div class="user-chip" title="<?php echo e($user['email']); ?>">
        <span class="user-name"><?php echo e($user['name']); ?></span>
        <span class="user-role"><?php echo e(UserRepository::ROLES[$user['role']]); ?></span>
      </div>
      <form method="post" action="<?php echo e(page_url('logout.php')); ?>" class="logout-form">
        <?php echo csrf_field(); ?>
        <button type="submit" class="btn-logout">Keluar</button>
      </form>
    </div>
  </header>
  <?php if ($flash): ?>
    <div class="app-flash <?php echo e($flash['type']); ?>" role="status"><?php echo e($flash['message']); ?></div>
  <?php endif; ?>
