<?php

class Ticket {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function getByUserId($userId) {
        $stmt = $this->db->prepare("
            SELECT t.*, 
                   (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
            FROM tickets t
            WHERE t.user_id = ?
            ORDER BY t.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    public function getAll() {
        $stmt = $this->db->query("
            SELECT t.*, 
                   u.first_name, u.last_name, u.email,
                   (SELECT COUNT(*) FROM ticket_messages WHERE ticket_id = t.id) as message_count
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            ORDER BY 
                CASE t.status 
                    WHEN 'new' THEN 1 
                    WHEN 'in_progress' THEN 2 
                    WHEN 'closed' THEN 3 
                END,
                t.created_at DESC
        ");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->db->prepare("
            SELECT t.*, u.first_name, u.last_name, u.email
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($userId, $subject, $message, $priority = 'medium') {
        try {
            $this->db->beginTransaction();
            
            // Create ticket
            $stmt = $this->db->prepare("
                INSERT INTO tickets (user_id, subject, priority, status) 
                VALUES (?, ?, ?, 'new')
            ");
            $stmt->execute([$userId, $subject, $priority]);
            $ticketId = $this->db->lastInsertId();
            
            // Add initial message
            $stmt = $this->db->prepare("
                INSERT INTO ticket_messages (ticket_id, user_id, message, is_staff_reply) 
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$ticketId, $userId, $message]);
            
            $this->db->commit();
            return $ticketId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
    
    public function addMessage($ticketId, $userId, $message, $isStaffReply = false) {
        $stmt = $this->db->prepare("
            INSERT INTO ticket_messages (ticket_id, user_id, message, is_staff_reply) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$ticketId, $userId, $message, $isStaffReply ? 1 : 0]);
    }
    
    public function getMessages($ticketId) {
        $stmt = $this->db->prepare("
            SELECT tm.*, u.first_name, u.last_name, u.role
            FROM ticket_messages tm
            JOIN users u ON tm.user_id = u.id
            WHERE tm.ticket_id = ?
            ORDER BY tm.created_at ASC
        ");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }
    
    public function updateStatus($id, $status) {
        $closedAt = ($status === 'closed') ? 'NOW()' : 'NULL';
        $stmt = $this->db->prepare("
            UPDATE tickets 
            SET status = ?, closed_at = $closedAt
            WHERE id = ?
        ");
        return $stmt->execute([$status, $id]);
    }
    
    public function getStatistics() {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_tickets,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
            FROM tickets
        ");
        return $stmt->fetch();
    }
}
