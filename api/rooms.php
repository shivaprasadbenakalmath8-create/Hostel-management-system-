<?php
require_once '../config/config.php';

if (!isset($_SESSION['user_id'])) {
    Response::error('Unauthorized', 401);
}

class RoomAPI {
    private $conn;
    private $db;
    
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT r.*, h.hostel_name 
                  FROM rooms r
                  JOIN hostels h ON r.hostel_id = h.hostel_id
                  ORDER BY r.room_id";
        $stmt = oci_parse($this->conn, $query);
        oci_execute($stmt);
        
        $rooms = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $rooms[] = $row;
        }
        
        Response::success("Rooms retrieved successfully", $rooms);
    }
    
    public function getByHostel($hostelId) {
        $query = "SELECT * FROM rooms WHERE hostel_id = :hostel_id ORDER BY room_number";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':hostel_id', $hostelId);
        oci_execute($stmt);
        
        $rooms = [];
        while ($row = oci_fetch_assoc($stmt)) {
            $rooms[] = $row;
        }
        
        Response::success("Rooms retrieved successfully", $rooms);
    }
    
    public function getOne($id) {
        $query = "SELECT r.*, h.hostel_name 
                  FROM rooms r
                  JOIN hostels h ON r.hostel_id = h.hostel_id
                  WHERE r.room_id = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_execute($stmt);
        
        $room = oci_fetch_assoc($stmt);
        if ($room) {
            Response::success("Room found", $room);
        } else {
            Response::error("Room not found", 404);
        }
    }
    
    public function create($data) {
        // Check if room number already exists in the hostel
        $checkQuery = "SELECT COUNT(*) as count FROM rooms 
                       WHERE hostel_id = :hostel_id AND room_number = :room_number";
        $checkStmt = oci_parse($this->conn, $checkQuery);
        oci_bind_by_name($checkStmt, ':hostel_id', $data['hostel_id']);
        oci_bind_by_name($checkStmt, ':room_number', $data['room_number']);
        oci_execute($checkStmt);
        $row = oci_fetch_assoc($checkStmt);
        
        if ($row['COUNT'] > 0) {
            Response::error("Room number already exists in this hostel");
        }
        
        $query = "INSERT INTO rooms (room_id, hostel_id, room_number, floor, capacity, rent_amount, status) 
                  VALUES (seq_rooms.NEXTVAL, :hostel_id, :room_number, :floor, :capacity, :rent_amount, 'available')";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':hostel_id', $data['hostel_id']);
        oci_bind_by_name($stmt, ':room_number', $data['room_number']);
        oci_bind_by_name($stmt, ':floor', $data['floor']);
        oci_bind_by_name($stmt, ':capacity', $data['capacity']);
        oci_bind_by_name($stmt, ':rent_amount', $data['rent_amount']);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Room created successfully");
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to create room: " . $error['message']);
        }
    }
    
    public function update($id, $data) {
        $query = "UPDATE rooms SET 
                  room_number = :room_number,
                  floor = :floor,
                  capacity = :capacity,
                  rent_amount = :rent_amount,
                  status = :status
                  WHERE room_id = :id";
        
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        oci_bind_by_name($stmt, ':room_number', $data['room_number']);
        oci_bind_by_name($stmt, ':floor', $data['floor']);
        oci_bind_by_name($stmt, ':capacity', $data['capacity']);
        oci_bind_by_name($stmt, ':rent_amount', $data['rent_amount']);
        oci_bind_by_name($stmt, ':status', $data['status']);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Room updated successfully");
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to update room: " . $error['message']);
        }
    }
    
    public function delete($id) {
        // Check if room has active allocations
        $checkQuery = "SELECT COUNT(*) as count FROM allocations 
                       WHERE room_id = :id AND status = 'active'";
        $checkStmt = oci_parse($this->conn, $checkQuery);
        oci_bind_by_name($checkStmt, ':id', $id);
        oci_execute($checkStmt);
        $row = oci_fetch_assoc($checkStmt);
        
        if ($row['COUNT'] > 0) {
            Response::error("Cannot delete room with active allocations");
        }
        
        $query = "DELETE FROM rooms WHERE room_id = :id";
        $stmt = oci_parse($this->conn, $query);
        oci_bind_by_name($stmt, ':id', $id);
        
        $result = oci_execute($stmt);
        
        if ($result) {
            Response::success("Room deleted successfully");
        } else {
            $error = oci_error($stmt);
            Response::error("Failed to delete room: " . $error['message']);
        }
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db->closeConnection();
        }
    }
}

// Handle requests
$api = new RoomAPI();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $api->getOne($_GET['id']);
        } elseif (isset($_GET['hostel_id'])) {
            $api->getByHostel($_GET['hostel_id']);
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
