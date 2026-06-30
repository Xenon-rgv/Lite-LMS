<?php
include_once 'config/database.php';

header('Content-Type: application/json');

$departments = [];

$result = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $departments[] = $row;
    }
    echo json_encode(['success' => true, 'departments' => $departments]);
} else {
    echo json_encode(['success' => false, 'message' => 'Could not load departments.']);
}

$conn->close();
?>
