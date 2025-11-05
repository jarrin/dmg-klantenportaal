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
        header('Location: /views/admin/dashboard.php');
    } else {
        header('Location: /views/customer/dashboard.php');
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
            header('Location: views/admin/dashboard.php');
        } else {
            header('Location: views/customer/dashboard.php');
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="login-page">
    <div class="login-split-layout">
        <!-- Left Side - Info Section (2/3) -->
        <div class="login-info-section">
            <div class="info-content">
                <div class="brand-section">
                    <div class="brand-icon">
                        <img src="./img/DMG_logo-witte-rand-om-blauwe-cirkel.png" alt="">
                    </div>
                    <h1 class="brand-title"><?php echo APP_NAME; ?></h1>
                    <p class="brand-tagline">Beheer uw diensten en ondersteuning op één plek</p>
                </div>

                <div class="features-section">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="feature-text">
                            <h3>Product Beheer</h3>
                            <p>Bekijk en beheer al uw actieve producten en diensten</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="feature-text">
                            <h3>9/5 Ondersteuning</h3>
                            <p>Meld problemen en volg de status van uw tickets</p>
                        </div>
                    </div>

                    <div class="feature-item">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="feature-text">
                            <h3>Veilig & Betrouwbaar</h3>
                            <p>Uw gegevens zijn beschermd met moderne encryptie</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form (1/3) -->
        <div class="login-form-section">
            <div class="login-form-container">
                <div class="login-header">
                    <h2>Welkom terug</h2>
                    <p>Log in op uw account</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <?php echo htmlspecialchars($error); ?>
                            <?php if (!empty($debug)): ?>
                                <br><small style="font-size: 12px;"><?php echo $debug; ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="login-form">
                    <div class="form-group">
                        <label for="email">
                            <i class="fas fa-envelope"></i>
                            E-mailadres
                        </label>
                        <input type="email" id="email" name="email" placeholder="uw.email@voorbeeld.nl" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Wachtwoord
                        </label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-login">
                        <span>Inloggen</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="login-demo-info">
                    <div class="demo-header">
                        <i class="fas fa-info-circle"></i>
                        <strong>Demo Accounts</strong>
                    </div>
                    <div class="demo-accounts">
                        <div class="demo-account">
                            <div class="demo-label">
                                <i class="fas fa-user-shield"></i>
                                Administrator
                            </div>
                            <div class="demo-credentials">
                                <span>admin@dmg.nl</span>
                                <span class="separator">•</span>
                                <span>admin123</span>
                            </div>
                        </div>
                        <div class="demo-account">
                            <div class="demo-label">
                                <i class="fas fa-user"></i>
                                Klant
                            </div>
                            <div class="demo-credentials">
                                <span>demo@example.com</span>
                                <span class="separator">•</span>
                                <span>customer123</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>