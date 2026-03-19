<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    Response::error('Unauthorized', 401);
}

class AllocationAPI {
    private $conn;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT a.*, 
                         s.full_name as student_name, s.reg_number,
                         r.room_number, h.hostel_name
                  FROM allocations a
                  JOIN students s ON a.student_id = s.student_id
                  JOIN rooms r ON a.room_id = r.room_id
                  JOIN hostels h ON r.hostel_id = h.hostel_id
                  ORDER BY a.allocation_date DESC";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $allocations = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $allocations[] = $row;
        }
        
        Response::success("Allocations retrieved successfully", $allocations);
    }
    
    public function getByStudent($studentId) {
        $query = "SELECT a.*, r.room_number, h.hostel_name
                  FROM allocations a
                  JOIN rooms r ON a.room_id = r.room_id
                  JOIN hostels h ON r.hostel_id = h.hostel_id
                  WHERE a.student_id = :student_id
                  ORDER BY a.allocation_date DESC";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':student_id', $studentId);
        oci_execute($stmt);
        
        $allocations = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $allocations[] = $row;
        }
        
        Response::success("Allocations retrieved successfully", $allocations);
    }
    
    public function getActive() {
        $query = "SELECT a.*, 
                         s.full_name as student_name, s.reg_number,
                         r.room_number, h.hostel_name
                  FROM allocations a
                  JOIN students s ON a.student_id = s.student_id
                  JOIN rooms r ON a.room_id = r.room_id
                  JOIN hostels h ON r.hostel_id = h.hostel_id
                  WHERE a.status = 'active'
                  ORDER BY a.allocation_date DESC";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $allocations = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $allocations[] = $row;
        }
        
        Response::success("Active allocations retrieved", $allocations);
    }
    
    public function create($data) {
        // Check if student already has active allocation
        $checkStudent = "SELECT COUNT(*) as count FROM allocations 
                         WHERE student_id = :student_id AND status = 'active'";
        $stmtStudent = oci_parse($this->conn, $checkStudent);
        oci_bind_by_name($stmtStudent, ':student_id', $data['student_id']);
        oci_execute($stmtStudent);
        $rowStudent = oci_fetch_assoc($stmtStudent);
        
        if ($rowStudent['COUNT'] > 0) {
            Response::error("Student already has an active allocation");
        }
        
        // Check room availability
        $checkRoom = "SELECT capacity, occupancy, status FROM rooms WHERE room_id = :room_id";
        $stmtRoom = oci_parse($this->conn, $checkRoom);
        oci_bind_by_name($stmtRoom, ':room_id', $data['room_id']);
        oci_execute($stmtRoom);
        $room = oci_fetch_assoc($stmtRoom);
        
        if (!$room) {
            Response::error("Room not found");
        }
        
        if ($room['STATUS'] != 'available') {
            Response::error("Room is not available");
        }
        
        if ($room['OCCUPANCY'] >= $room['CAPACITY']) {
            Response::error("Room is already full");
        }
        
        // Start transaction
        oci_execute(oci_parse($this->conn, "BEGIN TRANSACTION"), OCI_NO_AUTO_COMMIT);
        
        try {
            // Create allocation
            $query = "INSERT INTO allocations (allocation_id, student_id, room_id, end_date, status) 
                      VALUES (seq_allocations.NEXTVAL, :student_id, :room_id, :end_date, 'active')";
            $stmt = oci_parse($this->conn, $query);
            oci_bind_by_name($stmt, ':student_id', $data['student_id']);
            oci_bind_by_name($stmt, ':room_id', $data['room_id']);
            oci_bind_by_name($stmt, ':end_date', $data['end_date']);
            oci_execute($stmt, OCI_NO_AUTO_COMMIT);
            
            // Update room occupancy
            $newOccupancy = $room['OCCUPANCY'] + 1;
            $updateRoom = "UPDATE rooms SET occupancy = :occupancy, 
                           status = CASE WHEN :occupancy >= capacity THEN 'occupied' ELSE 'available' END
                           WHERE room_id = :room_id";
            $stmtUpdate = oci_parse($this->conn, $updateRoom);
            oci_bind_by_name($stmtUpdate, ':occupancy', $newOccupancy);
            oci_bind_by_name($stmtUpdate, ':room_id', $data['room_id']);
            oci_execute($stmtUpdate, OCI_NO_AUTO_COMMIT);
            
            oci_commit($this->conn);
            Response::success("Room allocated successfully");
            
        } catch (Exception $e) {
            oci_rollback($this->conn);
            Response::error("Failed to allocate room: " . $e->getMessage());
        }
    }
    
    public function deallocate($id) {
        // Get allocation details
        $getQuery = "SELECT a.*, r.hostel_id, r.room_id 
                     FROM allocations a
                     JOIN rooms r ON a.room_id = r.room_id
                     WHERE a.allocation_id = :id AND a.status = 'active'";
        $getStmt = oci_parse($this->conn, $getQuery);
        oci_bind_by_name($getStmt, ':id', $id);
        oci_execute($getStmt);
        $allocation = oci_fetch_assoc($getStmt);
        
        if (!$allocation) {
            Response::error("Active allocation not found");
        }
        
        // Start transaction
        oci_execute(oci_parse($this->conn, "BEGIN TRANSACTION"), OCI_NO_AUTO_COMMIT);
        
        try {
            // Update allocation status
            $updateAlloc = "UPDATE allocations SET status = 'ended', end_date = SYSDATE 
                            WHERE allocation_id = :id";
            $stmtAlloc = oci_parse($this->conn, $updateAlloc);
            oci_bind_by_name($stmtAlloc, ':id', $id);
            oci_execute($stmtAlloc, OCI_NO_AUTO_COMMIT);
            
            // Decrease room occupancy
            $updateRoom = "UPDATE rooms SET occupancy = occupancy - 1,
                           status = 'available'
                           WHERE room_id = :room_id AND occupancy > 0";
            $stmtRoom = oci_parse($this->conn, $updateRoom);
            oci_bind_by_name($stmtRoom, ':room_id', $allocation['ROOM_ID']);
            oci_execute($stmtRoom, OCI_NO_AUTO_COMMIT);
            
            oci_commit($this->conn);
            Response::success("Room deallocated successfully");
            
        } catch (Exception $e) {
            oci_rollback($this->conn);
            Response::error("Failed to deallocate room: " . $e->getMessage());
        }
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}

// Handle requests
$api = new AllocationAPI();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['student_id'])) {
            $api->getByStudent($_GET['student_id']);
        } elseif (isset($_GET['active'])) {
            $api->getActive();
        } else {
            $api->getAll();
        }
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $api->create($data);
        break;
        
    case 'DELETE':
        if (isset($_GET['id'])) {
            $api->deallocate($_GET['id']);
        }
        break;
        
    default:
        Response::error("Method not allowed", 405);
}
?>
