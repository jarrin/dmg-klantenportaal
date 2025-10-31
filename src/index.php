<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/classes/User.php';
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Ticket.php';

$auth = new Auth();

// Auto-redirect based on login status
if ($auth->isLoggedIn()) {
    if ($auth->isAdmin()) {
        header('Location: /admin/dashboard.php');
    } else {
        header('Location: /customer/dashboard.php');
    }
    exit;
}

$error = '';
$debug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Debug info (remove in production)
    if (ENVIRONMENT === 'development') {
        $debug = "Attempting login with email: $email<br>";
    }
    
    if ($auth->login($email, $password)) {
        if ($auth->isAdmin()) {
            header('Location: /admin/dashboard.php');
        } else {
            header('Location: /customer/dashboard.php');
        }
        exit;
    } else {
        $error = 'Ongeldige inloggegevens';
        
        // Additional debug info
        if (ENVIRONMENT === 'development') {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT id, email FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user) {
                    $debug .= "User found in database. ";
                } else {
                    $debug .= "User NOT found in database. ";
                }
            } catch (Exception $e) {
                $debug .= "Database error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <h1><?php echo APP_NAME; ?></h1>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                    <?php if (!empty($debug)): ?>
                        <br><small style="font-size: 12px;"><?php echo $debug; ?></small>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">E-mailadres</label>
                    <input type="email" id="email" name="email" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Wachtwoord</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Inloggen</button>
            </form>
            
            <div class="login-info">
                <p><strong>Demo accounts:</strong></p>
                <p>Admin: admin@dmg.nl / admin123</p>
                <p>Klant: demo@example.com / customer123</p>
            </div>
        </div>
    </div>
</body>
</html>
