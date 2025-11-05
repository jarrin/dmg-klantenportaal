<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../classes/Paginator.php';

$auth = new Auth();
$auth->requireAdmin();

$userModel = new User();
$db = Database::getInstance()->getConnection();

$success = '';
$error = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validate required fields first
        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            $error = 'Vul alle verplichte velden in';
        } else {
            // Prepare data for validation
            $validateData = [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'company_name' => $companyName,
                'address' => $address,
                'postal_code' => $postalCode,
                'city' => $city,
                'phone' => $phone
            ];

            // Run validation
            if (!Validator::validateUser($validateData, false)) {
                $error = Validator::getFirstError();
            } elseif ($userModel->emailExists($email)) {
                $error = 'Dit e-mailadres is al in gebruik';
            } else {
                $data = [
                    'email' => $email,
                    'password' => $auth->hashPassword($password),
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'company_name' => $companyName,
                    'address' => $address,
                    'postal_code' => $postalCode,
                    'city' => $city,
                    'phone' => $phone,
                    'role' => 'customer'
                ];

                if ($userModel->create($data)) {
                    $success = 'Gebruiker succesvol aangemaakt';
                } else {
                    $error = 'Er is een fout opgetreden bij het aanmaken van de gebruiker';
                }
            }
        }
    } elseif ($action === 'delete') {
        $userId = $_POST['user_id'] ?? 0;
        if ($userModel->delete($userId)) {
            $success = 'Gebruiker succesvol verwijderd';
        } else {
            $error = 'Er is een fout opgetreden bij het verwijderen van de gebruiker';
        }
    }
}

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 15;

// Count total users
$countQuery = "SELECT COUNT(*) FROM users WHERE role = 'customer'";
$paginator = Paginator::fromQuery($db, $countQuery, [], $perPage, $page);

// Get users with pagination
$stmt = $db->prepare("SELECT * FROM users WHERE role = 'customer' ORDER BY created_at DESC " . $paginator->getLimitClause());
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Gebruikersbeheer - ' . APP_NAME;
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="page-header">
        <h1>Gebruikersbeheer</h1>
        <button class="btn btn-primary" onclick="document.getElementById('newUserForm').style.display='block'">
            Nieuwe gebruiker
        </button>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="table-container">
        <div class="table-header">
            <h2>Gebruikers (<?php echo $paginator->getTotalItems(); ?>)</h2>
            <div class="table-actions">
                <div class="per-page-selector">
                    <label>Toon:</label>
                    <select onchange="window.location.href='?per_page='+this.value">
                        <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="15" <?php echo $perPage == 15 ? 'selected' : ''; ?>>15</option>
                        <option value="25" <?php echo $perPage == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $perPage == 50 ? 'selected' : ''; ?>>50</option>
                    </select>
                </div>
            </div>
        </div>

        <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Naam</th>
                <th>E-mail</th>
                <th>Bedrijf</th>
                <th>Geregistreerd</th>
                <th>Laatste login</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></td>
                    <td><?php echo date('d-m-Y', strtotime($user['created_at'])); ?></td>
                    <td><?php echo $user['last_login'] ? date('d-m-Y H:i', strtotime($user['last_login'])) : 'Nooit'; ?></td>
                    <td>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Weet u zeker dat u deze gebruiker wilt verwijderen?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Verwijderen</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php echo $paginator->render('users.php', ['per_page' => $perPage]); ?>
    </div>

    <!-- New User Form Modal -->
    <div id="newUserForm" style="display: none;" class="form-modal">
        <div class="modal-content">
            <span class="close" onclick="document.getElementById('newUserForm').style.display='none'">&times;</span>
            <h2>Nieuwe Gebruiker Toevoegen</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="create">

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">Voornaam *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>

                    <div class="form-group">
                        <label for="last_name">Achternaam *</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">E-mailadres *</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Wachtwoord *</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="company_name">Bedrijfsnaam</label>
                    <input type="text" id="company_name" name="company_name">
                </div>

                <div class="form-group">
                    <label for="address">Adres</label>
                    <input type="text" id="address" name="address">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code">Postcode</label>
                        <input type="text" id="postal_code" name="postal_code">
                    </div>

                    <div class="form-group">
                        <label for="city">Plaats</label>
                        <input type="text" id="city" name="city">
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Telefoonnummer</label>
                    <input type="tel" id="phone" name="phone">
                </div>

                <button type="submit" class="btn btn-primary">Gebruiker aanmaken</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('newUserForm').style.display='none'">
                    Annuleren
                </button>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>