<header class="main-header">
    <div class="header-content">
        <div class="logo">
            <h2><?php echo APP_NAME; ?></h2>
        </div>
        <nav class="main-nav">
            <a href="/customer/dashboard.php">Dashboard</a>
            <a href="/customer/products.php">Producten</a>
            <a href="/customer/tickets.php">Tickets</a>
            <a href="/customer/payment-preferences.php">Betaling</a>
            <a href="/customer/profile.php">Profiel</a>
            <a href="/logout.php" class="btn btn-logout">Uitloggen</a>
        </nav>
        <div class="user-info">
            Welkom, <?php echo htmlspecialchars($_SESSION['user_name']); ?>
        </div>
    </div>
</header>
