<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    Response::error('Unauthorized', 401);
}

class StaffAPI {
    private $conn;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT s.*, u.username, u.email 
                  FROM staff s
                  JOIN users u ON s.user_id = u.user_id
                  ORDER BY s.staff_id";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $staff = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $staff[] = $row;
        }
        
        Response::success("Staff retrieved successfully", $staff);
    }
    
    public function getOne($id) {
        $query = "SELECT s.*, u.username, u.email 
                  FROM staff s
                  JOIN users u ON s.user_id = u.user_id
                  WHERE s.staff_id = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);
        
        $staff = oci_fetch_assoc($stmt);
        if ($staff) {
            Response::success("Staff found", $staff);
        } else {
            Response::error("Staff not found", 404);
        }
    }
    
    public function create($data) {
        // Start transaction
        oci_execute(oci_parse($this->conn, "BEGIN TRANSACTION"), OCI_NO_AUTO_COMMIT);
        
        try {
            // Create user account
            $userQuery = "INSERT INTO users (user_id, username, password, email, role) 
                          VALUES (seq_users.NEXTVAL, :username, :password, :email, 'staff') 
                          RETURNING user_id INTO :user_id";
            
            $userStmt = oci_parse($this->conn, $userQuery);
            $username = $data['staff_number'];
            $password = 'staff123'; // Default password
            $email = $data['email'] ?? '';
            
            oci_bind_by_name($userStmt, ':username', $username);
            oci_bind_by_name($userStmt, ':password', $password);
            oci_bind_by_name($userStmt, ':email', $email);
            oci_bind_by_name($userStmt, ':user_id', $userId, 32);
            
            oci_execute($userStmt, OCI_NO_AUTO_COMMIT);
            
            // Create staff record
            $staffQuery = "INSERT INTO staff (staff_id, user_id, staff_number, full_name, 
                            designation, department, phone, email, joining_date, salary) 
                            VALUES (seq_staff.NEXTVAL, :user_id, :staff_number, :full_name, 
                            :designation, :department, :phone, :email, :joining_date, :salary)";
            
            $staffStmt = oci_parse($this->conn, $staffQuery);
            oci_bind_by_name($staffStmt, ':user_id', $userId);
            oci_bind_by_name($staffStmt, ':staff_number', $data['staff_number']);
            oci_bind_by_name($staffStmt, ':full_name', $data['full_name']);
            oci_bind_by_name($staffStmt, ':designation', $data['designation']);
            oci_bind_by_name($staffStmt, ':department', $data['department']);
            oci_bind_by_name($staffStmt, ':phone', $data['phone']);
            oci_bind_by_name($staffStmt, ':email', $email);
            oci_bind_by_name($staffStmt, ':joining_date', $data['joining_date']);
            oci_bind_by_name($staffStmt, ':salary', $data['salary']);
            
            oci_execute($staffStmt, OCI_NO_AUTO_COMMIT);
            
            oci_commit($this->conn);
            Response::success("Staff created successfully");
            
        } catch (Exception $e) {
            oci_rollback($this->conn);
            Response::error("Failed to create staff: " . $e->getMessage());
        }
    }
    
    public function update($id, $data) {
        $query = "UPDATE staff SET 
                  full_name = :full_name,
                  designation = :designation,
                  department = :department,
                  phone = :phone,
                  salary = :salary
                  WHERE staff_id = :id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_bind_by_name($stmt, ':full_name', $data['full_name']);
        oci_bind_by_name($stmt, ':designation', $data['designation']);
        oci_bind_by_name($stmt, ':department', $data['department']);
        oci_bind_by_name($stmt, ':phone', $data['phone']);
        oci_bind_by_name($stmt, ':salary', $data['salary']);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Staff updated successfully");
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to update staff: " . $error['message']);
        }
    }
    
    public function delete($id) {
        // Get user_id before deleting staff
        $userQuery = "SELECT user_id FROM staff WHERE staff_id = :id";
        $userStmt = oci_parse($this->conn, $userQuery);
        oci_bind_by_name($userStmt, ':id', $id);
        oci_execute($userStmt);
        $userRow = oci_fetch_assoc($userStmt);
        $userId = $userRow['USER_ID'];
        
        // Start transaction
        oci_execute(oci_parse($this->conn, "BEGIN TRANSACTION"), OCI_NO_AUTO_COMMIT);
        
        try {
            // Delete staff
            $query = "DELETE FROM staff WHERE staff_id = :id";
            $stmt = oci_parse($this->conn, $query);
            oci_bind_by_name($stmt, ':id', $id);
            oci_execute($stmt, OCI_NO_AUTO_COMMIT);
            
            // Delete user
            $userQuery = "DELETE FROM users WHERE user_id = :user_id";
            $userStmt = oci_parse($this->conn, $userQuery);
            oci_bind_by_name($userStmt, ':user_id', $userId);
            oci_execute($userStmt, OCI_NO_AUTO_COMMIT);
            
            oci_commit($this->conn);
            Response::success("Staff deleted successfully");
            
        } catch (Exception $e) {
            oci_rollback($this->conn);
            Response::error("Failed to delete staff: " . $e->getMessage());
        }
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}

// Handle requests
$api = new StaffAPI();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $api->getOne($_GET['id']);
        } else {
            $api->getAll();
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $api->create($data);
        break;
        
    case 'PUT':
        if (isset($_GET['id'])) {
            $data = json_decode(file_get_contents('php://input'), true);
            $api->update($_GET['id'], $data);
        }
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            $api->delete($_GET['id']);
        }
        break;
        
    default:
        Response::error("Method not allowed", 405);
}
?>
