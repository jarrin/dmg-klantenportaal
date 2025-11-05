<?php
// Determine user role and set navigation items
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';
$userName = $_SESSION['user_name'] ?? 'Gebruiker';
$userEmail = $_SESSION['user_email'] ?? '';

// Get current page for active state (safe fallback if REQUEST_URI is not set)
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

function navActive($linkPath, $currentPath) {
    // normalize paths and return 'active' if linkPath appears anywhere in currentPath
    $cp = rtrim($currentPath, '/');
    $lp = rtrim($linkPath, '/');
    if ($lp === '') return '';
    return strpos($cp, $lp) !== false ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/custom-components.css">
</head>
<body>
    <div class="layout-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar <?php echo $isAdmin ? 'sidebar-admin' : 'sidebar-customer'; ?>">
            <div class="sidebar-header">
                <h2 class="sidebar-logo"><?php echo APP_NAME; ?></h2>
                <p class="sidebar-role"><?php echo $isAdmin ? 'Admin Panel' : 'Klantportaal'; ?></p>
            </div>
            
            <nav class="sidebar-nav">
                <?php if ($isAdmin): ?>
                    <a href="/views/admin/dashboard.php" class="nav-item <?php echo navActive('/admin/dashboard.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-chart-line"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="/views/admin/users.php" class="nav-item <?php echo navActive('/admin/users.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-users"></i>
                        <span class="nav-text">Gebruikers</span>
                    </a>
                    <a href="/views/admin/products.php" class="nav-item <?php echo navActive('/admin/products.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-box"></i>
                        <span class="nav-text">Producten</span>
                    </a>
                    <a href="/views/admin/tickets.php" class="nav-item <?php echo navActive('/admin/tickets.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-ticket-alt"></i>
                        <span class="nav-text">Tickets</span>
                    </a>
                <?php else: ?>
                    <a href="/views/customer/dashboard.php" class="nav-item <?php echo navActive('/customer/dashboard.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-home"></i>
                        <span class="nav-text">Dashboard</span>
                    </a>
                    <a href="/views/customer/products.php" class="nav-item <?php echo navActive('/customer/products.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-box"></i>
                        <span class="nav-text">Mijn Producten</span>
                    </a>
                    <a href="/views/customer/tickets.php" class="nav-item <?php echo navActive('/customer/tickets.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-ticket-alt"></i>
                        <span class="nav-text">Tickets</span>
                    </a>
                    <a href="/views/customer/payment-preferences.php" class="nav-item <?php echo navActive('/customer/payment-preferences.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-credit-card"></i>
                        <span class="nav-text">Betalingen</span>
                    </a>
                    <a href="/views/customer/profile.php" class="nav-item <?php echo navActive('/customer/profile.php', $currentPath); ?>">
                        <i class="nav-icon fas fa-user"></i>
                        <span class="nav-text">Profiel</span>
                    </a>
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <a href="/logout.php" class="nav-item nav-logout">
                    <i class="nav-icon fas fa-sign-out-alt"></i>
                    <span class="nav-text">Uitloggen</span>
                </a>
            </div>
        </aside>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <!-- Welcome Header -->
            <header class="welcome-header">
                <div class="welcome-info">
                    <h1 class="welcome-title">Welkom terug, <?php echo htmlspecialchars($userName); ?>!</h1>
                    <p class="welcome-subtitle"><?php echo htmlspecialchars($userEmail); ?></p>
                </div>
                <div class="header-actions">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($userName, 0, 1)); ?>
                    </div>
                </div>
            </header>
            
            <!-- Page Content -->
            <div class="page-content">
