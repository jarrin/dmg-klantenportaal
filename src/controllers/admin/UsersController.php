<?php
class UsersController
{
    private $db;
    private $userModel;
    private $auth;

    public function __construct($auth)
    {
        require_once __DIR__ . '/../../config/Database.php';
        require_once __DIR__ . '/../../classes/User.php';
        require_once __DIR__ . '/../../classes/Validator.php';
        require_once __DIR__ . '/../../classes/Paginator.php';

        $this->db = Database::getInstance()->getConnection();
        $this->userModel = new User();
        $this->auth = $auth;
    }

    public function handlePost()
    {
        $result = ['success' => '', 'error' => ''];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $result = $this->createUser();
        } elseif ($action === 'delete') {
            $result = $this->deleteUser();
        }

        return $result;
    }

    private function createUser()
    {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            return ['success' => '', 'error' => 'Vul alle verplichte velden in'];
        }

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

        if (!Validator::validateUser($validateData, false)) {
            return ['success' => '', 'error' => Validator::getFirstError()];
        }
        
        if ($this->userModel->emailExists($email)) {
            return ['success' => '', 'error' => 'Dit e-mailadres is al in gebruik'];
        }

        $data = [
            'email' => $email,
            'password' => $this->auth->hashPassword($password),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company_name' => $companyName,
            'address' => $address,
            'postal_code' => $postalCode,
            'city' => $city,
            'phone' => $phone,
            'role' => 'customer'
        ];

        if ($this->userModel->create($data)) {
            return ['success' => 'Gebruiker succesvol aangemaakt', 'error' => ''];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het aanmaken van de gebruiker'];
    }

    private function deleteUser()
    {
        $userId = $_POST['user_id'] ?? 0;
        
        if ($this->userModel->delete($userId)) {
            return ['success' => 'Gebruiker succesvol verwijderd', 'error' => ''];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het verwijderen van de gebruiker'];
    }

    public function index()
    {
        $data = [];
        
        // Pagination setup
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['per_page']) ? max(5, min(100, (int)$_GET['per_page'])) : 15;

        // Count total users
        $countQuery = "SELECT COUNT(*) FROM users WHERE role = 'customer'";
        $data['paginator'] = Paginator::fromQuery($this->db, $countQuery, [], $perPage, $page);

        // Get users with pagination
        $stmt = $this->db->prepare("SELECT * FROM users WHERE role = 'customer' ORDER BY created_at DESC " . $data['paginator']->getLimitClause());
        $stmt->execute();
        $data['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['perPage'] = $perPage;

        return $data;
    }
}
