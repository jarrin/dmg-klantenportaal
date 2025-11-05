<?php
class ProfileController
{
    private $userModel;
    private $auth;
    private $userId;

    public function __construct($auth, $userId)
    {
        require_once __DIR__ . '/../../classes/User.php';
        require_once __DIR__ . '/../../classes/Validator.php';

        $this->userModel = new User();
        $this->auth = $auth;
        $this->userId = $userId;
    }

    public function handlePost()
    {
        $result = ['success' => '', 'error' => '', 'user' => null];
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $result;
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $result = $this->updateProfile();
        } elseif ($action === 'change_password') {
            $result = $this->changePassword();
        }

        return $result;
    }

    private function updateProfile()
    {
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
            return ['success' => '', 'error' => 'Voornaam en achternaam zijn verplicht', 'user' => null];
        }
        
        if (!Validator::validateUser($data, true)) {
            return ['success' => '', 'error' => Validator::getFirstError(), 'user' => null];
        }

        if ($this->userModel->update($this->userId, $data)) {
            $user = $this->userModel->getById($this->userId);
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            return ['success' => 'Profiel succesvol bijgewerkt', 'error' => '', 'user' => $user];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het bijwerken van uw profiel', 'user' => null];
    }

    private function changePassword()
    {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            return ['success' => '', 'error' => 'Vul alle wachtwoordvelden in', 'user' => null];
        }
        
        if ($newPassword !== $confirmPassword) {
            return ['success' => '', 'error' => 'De nieuwe wachtwoorden komen niet overeen', 'user' => null];
        }
        
        if (strlen($newPassword) < 6) {
            return ['success' => '', 'error' => 'Het wachtwoord moet minimaal 6 tekens lang zijn', 'user' => null];
        }

        $user = $this->userModel->getById($this->userId);
        if (!password_verify($currentPassword, $user['password'])) {
            return ['success' => '', 'error' => 'Het huidige wachtwoord is onjuist', 'user' => null];
        }

        $hashedPassword = $this->auth->hashPassword($newPassword);
        if ($this->userModel->updatePassword($this->userId, $hashedPassword)) {
            return ['success' => 'Wachtwoord succesvol gewijzigd', 'error' => '', 'user' => null];
        }
        
        return ['success' => '', 'error' => 'Er is een fout opgetreden bij het wijzigen van het wachtwoord', 'user' => null];
    }

    public function show()
    {
        return $this->userModel->getById($this->userId);
    }
}
