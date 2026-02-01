<?php
/**
 * Common Head Elements
 * Include this file in the <head> section of all pages
 */

// Determine the base path for assets
$basePath = '';
if (strpos($_SERVER['REQUEST_URI'], '/public/') !== false) {
    $basePath = '../';
}
?>
<!-- Favicon -->
<link rel="icon" type="image/png" sizes="32x32" href="<?php echo $basePath; ?>assets/img/logo-transparent.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?php echo $basePath; ?>assets/img/logo-transparent.png">
<link rel="apple-touch-icon" href="<?php echo $basePath; ?>assets/img/logo-transparent.png">
<link rel="shortcut icon" type="image/png" href="<?php echo $basePath; ?>assets/img/logo-transparent.png">

<!-- Meta Tags -->
<meta name="theme-color" content="#1a1a2e">
<meta name="description" content="نظام إدارة محلات فساتين الزفاف - لمسات الأسطورة">
