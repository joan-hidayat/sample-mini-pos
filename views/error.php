<?php
/** @var string $title  @var string $message */
ob_start();
?>
<h1 class="auth-title"><?php echo e($title); ?></h1>
<p class="auth-lead"><?php echo e($message); ?></p>
<a class="btn btn-primary btn-block" href="javascript:location.reload()">Muat Ulang</a>
<?php
$content = ob_get_clean();
render('layout_auth', ['title' => $title, 'content' => $content, 'license' => null]);
