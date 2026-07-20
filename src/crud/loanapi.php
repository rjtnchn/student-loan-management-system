<?php
/**
 * Simple CRUD API for managing students in a school database.
 * 
 * This API supports the following operations:
 * - GET: Retrieve all students
 * - POST: Add a new student
 * - PUT: Update an existing student
 * - DELETE: Remove a student
 * 
 * The API returns JSON responses and handles CORS for cross-origin requests.
 */
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");

$host = "db";
$user = "root"; 
$pass = "rootpassword"; 
$dbname = "school_db";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass); // Create a new PDO instance for database connection
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set error mode to exception for better error handling
} catch (PDOException $e) {
    // Handle connection error and return a JSON response with an error message
    echo json_with_code(["error" => "Connection failed: " . $e->getMessage()], 500);
    exit;
}

$method = $_SERVER['REQUEST_METHOD']; // Get the HTTP request method

// Handle the request based on the HTTP method
// HTTP methods are used to perform different operations on the server. Each case corresponds to a CRUD operation.
switch ($method) {
    case 'PUT':
        // READ: Get all students
        $data = json_decode(file_get_contents("php://input"), true);
        if(!empty($data['studentId'])){
            $stmt = $pdo->query("SELECT amount, type, status FROM loans WHERE student_id = ? DESC");
            $stmt->execute([
                $data['studentId']
            ]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }else{
            echo json_with_code(["error" => "No student id selected"], 400);
        }
        
        break;

    case 'POST':
        // CREATE: Add a new student
        $data = json_decode(file_get_contents("php://input"), true);
        if (!empty($data['studentId']) && !empty($data['amount']) && !empty($data['type']) && !empty($data['status'])) {
            $stmt = $pdo->prepare("INSERT INTO loans (student_id, amount, loan_type, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['studentId'], 
                $data['amount'], 
                $data['type'],
                $data['status']
            ]);
            echo json_encode(["message" => "Loan added successfully!"]);
        } else {
            echo json_with_code(["error" => "All fields are required"], 400);
        }
        break;
}

/**
 * Helper function to return JSON response with a specific HTTP status code.
 *
 * @param array $data The data to be returned as JSON.
 * @param int $code The HTTP status code to set for the response.
 * @return string JSON encoded data.
 */
function json_with_code($data, $code) {
    http_response_code($code);
    return json_encode($data);
}
?>