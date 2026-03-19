<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    Response::error('Unauthorized', 401);
}

class StudentAPI {
    private $conn;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT s.*, u.username, u.email 
                  FROM students s
                  JOIN users u ON s.user_id = u.user_id
                  ORDER BY s.student_id";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $students = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $students[] = $row;
        }
        
        Response::success("Students retrieved successfully", $students);
    }
    
    public function getOne($id) {
        $query = "SELECT s.*, u.username, u.email 
                  FROM students s
                  JOIN users u ON s.user_id = u.user_id
                  WHERE s.student_id = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);
        
        $student = oci_fetch_assoc($stmt);
        if ($student) {
            Response::success("Student found", $student);
        } else {
            Response::error("Student not found", 404);
        }
    }
    
    public function create($data) {
        // Start transaction
        oci_execute(oci_parse($this->conn, "BEGIN TRANSACTION"), OCI_NO_AUTO_COMMIT);
        
        try {
            // Create user account first
            $userQuery = "INSERT INTO users (user_id, username, password, email, role) 
                          VALUES (seq_users.NEXTVAL, :username, :password, :email, 'student') 
                          RETURNING user_id INTO :user_id";
            
            $userStmt = oci_parse($this->conn, $userQuery);
            $username = $data['reg_number']; // Use registration number as username
            $password = 'student123'; // Default password
            $email = $data['email'] ?? '';
            
            oci_bind_by_name($userStmt, ':username', $username);
            oci_bind_by_name($userStmt, ':password', $password);
            oci_bind_by_name($userStmt, ':email', $email);
            oci_bind_by_name($userStmt, ':user_id', $userId, 32);
            
            oci_execute($userStmt, OCI_NO_AUTO_COMMIT);
            
            // Create student record
            $studentQuery = "INSERT INTO students (student_id, user_id, reg_number, full_name, course, 
                            year_of_study, phone, address, parent_name, parent_phone) 
                            VALUES (seq_students.NEXTVAL, :user_id, :reg_number, :full_name, :course, 
                            :year, :phone, :address, :parent_name, :parent_phone)";
            
            $studentStmt = oci_parse($this->conn, $studentQuery);
            oci_bind_by_name($studentStmt, ':user_id', $userId);
            oci_bind_by_name($studentStmt, ':reg_number', $data['reg_number']);
            oci_bind_by_name($studentStmt, ':full_name', $data['full_name']);
            oci_bind_by_name($studentStmt, ':course', $data['course']);
            oci_bind_by_name($studentStmt, ':year', $data['year_of_study']);
            oci_bind_by_name($studentStmt, ':phone', $data['phone']);
            oci_bind_by_name($studentStmt, ':address', $data['address']);
            oci_bind_by_name($studentStmt, ':parent_name', $data['parent_name']);
            oci_bind_by_name($studentStmt, ':parent_phone', $data['parent_phone']);
            
            oci_execute($studentStmt, OCI_NO_AUTO_COMMIT);
            
            // Commit transaction
            oci_commit($this->conn);
            
            Response::success("Student created successfully");
            
        } catch (Exception $e) {
            oci_rollback($this->conn);
            Response::error("Failed to create student: " . $e->getMessage());
        }
    }
    
    public function update($id, $data) {
        $query = "UPDATE students SET 
                  full_name = :full_name,
                  course = :course,
                  year_of_study = :year,
                  phone = :phone,
                  address = :address,
                  parent_name = :parent_name,
                  parent_phone = :parent_phone
                  WHERE student_id = :id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_bind_by_name($stmt, ':full_name', $data['full_name']);
        oci_bind_by_name($stmt, ':course', $data['course']);
        oci_bind_by_name($stmt, ':year', $data['year_of_study']);
        oci_bind_by_name($stmt, ':phone', $data['phone']);
        oci_bind_by_name($stmt, ':address', $data['address']);
        oci_bind_by_name($stmt, ':parent_name', $data['parent_name']);
        oci_bind_by_name($stmt, ':parent_phone', $data['parent_phone']);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Student updated successfully");
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to update student: " . $error['message']);
        }
    }
    
    public function delete($id) {
        // Check if student has allocations
        $checkQuery = "SELECT COUNT(*) as count FROM allocations WHERE student_id = :id AND status = 'active'";
        $checkStmt = oci_parse($this->conn, $checkQuery);
        oci_bind_by_name($checkStmt, ':id', $id);
        oci_execute($checkStmt);
        $row = oci_fetch_assoc($checkStmt);
        
        if ($row['COUNT'] > 0) {
            Response::error("Cannot delete student with active allocations");
        }
        
        // Get user_id before deleting student
        $userQuery = "SELECT user_id FROM students WHERE student_id = :id";
        $userStmt = oci_parse($this->conn, $userQuery);
        oci_bind_by_name($userStmt, ':id', $id);
        oci_execute($userStmt);
        $userRow = oci_fetch_assoc($userStmt);
        $userId = $userRow['USER_ID'];
        
        // Start transaction
        oci_execute(oci_parse($this->conn, "BEGIN TRANSACTION"), OCI_NO_AUTO_COMMIT);
        
        try {
            // Delete student
            $query = "DELETE FROM students WHERE student_id = :id";
            $stmt = oci_parse($this->conn, $query);
            oci_bind_by_name($stmt, ':id', $id);
            oci_execute($stmt, OCI_NO_AUTO_COMMIT);
            
            // Delete user
            $userQuery = "DELETE FROM users WHERE user_id = :user_id";
            $userStmt = oci_parse($this->conn, $userQuery);
            oci_bind_by_name($userStmt, ':user_id', $userId);
            oci_execute($userStmt, OCI_NO_AUTO_COMMIT);
            
            oci_commit($this->conn);
            Response::success("Student deleted successfully");
            
        } catch (Exception $e) {
            oci_rollback($this->conn);
            Response::error("Failed to delete student: " . $e->getMessage());
        }
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}

// Handle requests
$api = new StudentAPI();
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
