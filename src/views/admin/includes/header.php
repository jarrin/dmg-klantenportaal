<header class="main-header admin-header">
    <div class="header-content">
        <div class="logo">
            <h2><?php echo APP_NAME; ?> - Admin</h2>
        </div>
        <nav class="main-nav">
            <a href="/views/admin/dashboard.php">Dashboard</a>
            <a href="/admin/users.php">Gebruikers</a>
            <a href="/admin/products.php">Producten</a>
            <a href="/admin/tickets.php">Tickets</a>
            <a href="/logout.php" class="btn btn-logout">Uitloggen</a>
        </nav>
        <div class="user-info">
            Admin: <?php echo htmlspecialchars($_SESSION['user_name']); ?>
        </div>
    </div>
</header>
