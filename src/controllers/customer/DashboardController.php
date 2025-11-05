<?php
class CustomerDashboardController
{
    private $productModel;
    private $ticketModel;
    private $userId;

    public function __construct($userId)
    {
        require_once __DIR__ . '/../../classes/Product.php';
        require_once __DIR__ . '/../../classes/Ticket.php';

        $this->productModel = new Product();
        $this->ticketModel = new Ticket();
        $this->userId = $userId;
    }

    public function index()
    {
        $data = [];
        
        $data['products'] = $this->productModel->getByUserId($this->userId);
        $data['tickets'] = $this->ticketModel->getByUserId($this->userId);

        // Count statistics
        $data['totalProducts'] = count($data['products']);
        $data['activeProducts'] = count(array_filter($data['products'], fn($p) => $p['status'] === 'active'));
        $data['openTickets'] = count(array_filter($data['tickets'], fn($t) => $t['status'] !== 'closed'));

        return $data;
    }
}
