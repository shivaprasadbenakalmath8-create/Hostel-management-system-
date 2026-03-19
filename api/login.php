<?php
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$role = $data['role'] ?? '';

if (empty($username) || empty($password) || empty($role)) {
    Response::error('All fields are required');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Simple authentication (in production, use password hashing)
    $query = "SELECT u.*, 
              CASE 
                  WHEN u.role = 'student' THEN s.full_name
                  WHEN u.role = 'staff' THEN st.full_name
                  ELSE 'Administrator'
              END as display_name
              FROM users u
              LEFT JOIN students s ON u.user_id = s.user_id
              LEFT JOIN staff st ON u.user_id = st.user_id
              WHERE u.username = :username 
              AND u.password = :password 
              AND u.role = :role";
    
    $stmt = oci_parse($conn, $query);
    oci_bind_by_name($stmt, ':username', $username);
    oci_bind_by_name($stmt, ':password', $password);
    oci_bind_by_name($stmt, ':role', $role);
    
    oci_execute($stmt);
    $user = oci_fetch_assoc($stmt);
    
    if ($user) {
        $_SESSION['user_id'] = $user['USER_ID'];
        $_SESSION['username'] = $user['USERNAME'];
        $_SESSION['role'] = $user['ROLE'];
        $_SESSION['display_name'] = $user['DISPLAY_NAME'];
        
        unset($user['PASSWORD']);
        Response::success('Login successful', $user);
    } else {
        Response::error('Invalid credentials');
    }
    
} catch (Exception $e) {
    Response::error('Login failed: ' . $e->getMessage(), 500);
} finally {
    if (isset($db)) {
        $db->closeConnection();
    }
}
?>
