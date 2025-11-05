<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../controllers/customer/ProfileController.php';

$auth = new Auth();
$auth->requireCustomer();

$userId = $auth->getCurrentUserId();

$controller = new ProfileController($auth, $userId);

// Handle POST requests
$result = $controller->handlePost();
$success = $result['success'];
$error = $result['error'];

// Update user if profile was updated
if ($result['user']) {
    $user = $result['user'];
} else {
    $user = $controller->show();
}

$pageTitle = 'Mijn Profiel - ' . APP_NAME;
?>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<div class="container">
    <h1>Mijn Profiel</h1>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="dashboard-grid">
        <div class="dashboard-section">
            <h2>Persoonlijke Gegevens</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label for="first_name">Voornaam *</label>
                    <input type="text" id="first_name" name="first_name"
                        value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="last_name">Achternaam *</label>
                    <input type="text" id="last_name" name="last_name"
                        value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">E-mailadres</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <small>E-mailadres kan niet worden gewijzigd. Neem contact op met de beheerder.</small>
                </div>

                <div class="form-group">
                    <label for="company_name">Bedrijfsnaam</label>
                    <input type="text" id="company_name" name="company_name"
                        value="<?php echo htmlspecialchars($user['company_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="address">Adres</label>
                    <input type="text" id="address" name="address"
                        value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="postal_code">Postcode</label>
                    <input type="text" id="postal_code" name="postal_code"
                        value="<?php echo htmlspecialchars($user['postal_code'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="city">Plaats</label>
                    <input type="text" id="city" name="city"
                        value="<?php echo htmlspecialchars($user['city'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Telefoonnummer</label>
                    <input type="tel" id="phone" name="phone"
                        value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn btn-primary full-width">Opslaan</button>
            </form>
        </div>

        <div class="dashboard-section">
            <h2>Wachtwoord Wijzigen</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">

                <div class="form-group full-width">
                    <label for="current_password">Huidig wachtwoord *</label>
                    <input type="password" id="current_password" name="current_password" required>
                </div>

                <div class="form-group full-width">
                    <label for="new_password">Nieuw wachtwoord *</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <small>Minimaal 6 tekens</small>
                </div>

                <div class="form-group full-width">
                    <label for="confirm_password">Bevestig nieuw wachtwoord *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <button type="submit" class="btn btn-primary full-width">Wachtwoord wijzigen</button>
            </form>
        </div>
    </div>

    <div class="dashboard-section" style="margin-top: 20px;">
        <h2>Account Informatie</h2>
        <div class="detail-row">
            <span class="label">Account aangemaakt op:</span>
            <span class="value"><?php echo date('d-m-Y H:i', strtotime($user['created_at'])); ?></span>
        </div>
        <div class="detail-row">
            <span class="label">Laatste login:</span>
            <span class="value">
                <?php echo $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : 'Nooit'; ?>
            </span>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</div>