<?php 
session_start();
require_once __DIR__ . '/../config/db_connect.php';
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_id_input = trim($_POST['student_id'] ?? '');
    $password_input = trim($_POST['password'] ?? '');

    if ($student_id_input === '' || $password_input === '') {
        header("Location: http://localhost/GCST_Track_System/pages/sign_in.html?error=invalid");
        exit();
    }

    $stmt = $conn->prepare(
        "SELECT last_name, first_name, middle_name, password, status, student_id 
        FROM users 
        WHERE student_id = ?"
    );
    $stmt->bind_param("s", $student_id_input);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($last_name, $first_name, $middle_name, $hashed_password, $status, $student_id);
        $stmt->fetch();

        $normalizedStatus = strtolower(trim($status));
        if ($normalizedStatus === 'pending') {
            header("Location: http://localhost/GCST_Track_System/pages/sign_in.html?error=pending");
            exit();
        }

        if ($normalizedStatus === 'rejected') {
            header("Location: http://localhost/GCST_Track_System/pages/sign_in.html?error=rejected");
            exit();
        }

        if ($normalizedStatus === 'suspended') {
            header("Location: http://localhost/GCST_Track_System/pages/sign_in.html?error=suspended");
            exit();
        }

        if ($hashed_password && password_verify($password_input, $hashed_password)) {
            $full_name = $first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name;

            $_SESSION['user_name'] = $full_name;
            $_SESSION['student_id'] = $student_id;
            $_SESSION['role'] = 'student';
            header("Location: http://localhost/GCST_Track_System/pages/user/InUser_home.html");
            exit();
        } else {
            header("Location: http://localhost/GCST_Track_System/pages/sign_in.html?error=invalid");
            exit();
        }
    } else {
        header("Location: http://localhost/GCST_Track_System/pages/sign_in.html?error=invalid");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>
