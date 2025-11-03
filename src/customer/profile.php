<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/User.php';

$auth = new Auth();
$auth->requireCustomer();

$userModel = new User();
$userId = $auth->getCurrentUserId();
$user = $userModel->getById($userId);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $data = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'postal_code' => trim($_POST['postal_code'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'phone' => trim($_POST['phone'] ?? '')
        ];
        
        if (empty($data['first_name']) || empty($data['last_name'])) {
            $error = 'Voornaam en achternaam zijn verplicht';
        } else {
            if ($userModel->update($userId, $data)) {
                $success = 'Profiel succesvol bijgewerkt';
                $user = $userModel->getById($userId);
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            } else {
                $error = 'Er is een fout opgetreden bij het bijwerken van uw profiel';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Vul alle wachtwoordvelden in';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'De nieuwe wachtwoorden komen niet overeen';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Het wachtwoord moet minimaal 6 tekens lang zijn';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'Het huidige wachtwoord is onjuist';
        } else {
            $hashedPassword = $auth->hashPassword($newPassword);
            if ($userModel->updatePassword($userId, $hashedPassword)) {
                $success = 'Wachtwoord succesvol gewijzigd';
            } else {
                $error = 'Er is een fout opgetreden bij het wijzigen van het wachtwoord';
            }
        }
    }
}
$pageTitle = 'Mijn Profiel - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
    
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
                    
                    <div class="form-row">
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
                    
                    <div class="form-row">
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
                    
                    <div class="form-group">
                        <label for="current_password">Huidig wachtwoord *</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password">Nieuw wachtwoord *</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <small>Minimaal 6 tekens</small>
                    </div>
                    
                    <div class="form-group">
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