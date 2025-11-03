<?php

class Product {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT p.*, pt.name as type_name 
            FROM products p
            JOIN product_types pt ON p.product_type_id = pt.id
            WHERE p.user_id = ?
            ORDER BY p.expiry_date ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getAll() {
        $stmt = $this->db->query("
            SELECT p.*, pt.name as type_name, 
                   u.first_name, u.last_name, u.email
            FROM products p
            JOIN product_types pt ON p.product_type_id = pt.id
            JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT p.*, pt.name as type_name 
            FROM products p
            JOIN product_types pt ON p.product_type_id = pt.id
            WHERE p.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->db->prepare("
            INSERT INTO products (user_id, product_type_id, name, description, domain_name, 
                                registration_date, expiry_date, duration_months, price, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $data['user_id'],
            $data['product_type_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['domain_name'] ?? null,
            $data['registration_date'],
            $data['expiry_date'],
            $data['duration_months'] ?? 12,
            $data['price'],
            $data['status'] ?? 'active'
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }
        
        $values[] = $id;
        
        $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($values);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function activateProduct($id) {
    $stmt = $this->db->prepare("UPDATE products SET status = 'active' WHERE id = ?");
    return $stmt->execute([$id]);
    }

    
    public function getProductTypes() {
        $stmt = $this->db->query("SELECT * FROM product_types ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function extendProduct($id, $months = 12) {
        $stmt = $this->db->prepare("
            UPDATE products 
            SET expiry_date = DATE_ADD(expiry_date, INTERVAL ? MONTH),
                status = 'active'
            WHERE id = ?
        ");
        return $stmt->execute([$months, $id]);
    }
    
    public function cancelProduct($id) {
        $stmt = $this->db->prepare("UPDATE products SET status = 'cancelled' WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    public function getExpiringProducts($days = 30) {
        $stmt = $this->db->prepare("
            SELECT p.*, pt.name as type_name, u.email, u.first_name, u.last_name
            FROM products p
            JOIN product_types pt ON p.product_type_id = pt.id
            JOIN users u ON p.user_id = u.id
            WHERE p.status = 'active' 
            AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            ORDER BY p.expiry_date ASC
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll();
    }
}
