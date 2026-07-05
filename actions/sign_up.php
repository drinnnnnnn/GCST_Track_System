<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();

if (!isset($conn) || $conn->connect_error) {
    header('Location: ../pages/superadmin/sign_up.html?status=error&show=register');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id     = trim(filter_input(INPUT_POST, 'student_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $last_name      = trim(filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $first_name     = trim(filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $middle_name    = trim(filter_input(INPUT_POST, 'middle_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $email          = trim(filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?? '');
    $password_raw   = $_POST['password'] ?? '';
    $confirm_pass   = $_POST['confirm_password'] ?? '';
    $sex            = trim(filter_input(INPUT_POST, 'sex', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $course         = trim(filter_input(INPUT_POST, 'course', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $department     = trim(filter_input(INPUT_POST, 'department', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $year_level     = trim(filter_input(INPUT_POST, 'year_level', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $contact_number = trim(filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $address        = trim(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $is_pwd         = isset($_POST['is_pwd']) && $_POST['is_pwd'] == '1' ? 1 : 0;
    $status         = 'pending';

    if ($student_id === '' || $last_name === '' || $first_name === '' || $email === '' || $password_raw === '' || $sex === '' || $course === '' || $department === '' || $year_level === '' || $contact_number === '' || $address === '') {
        header('Location: ../pages/superadmin/sign_up.html?status=invalid&show=register');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: ../pages/superadmin/sign_up.html?status=invalid_email&show=register');
        exit();
    }

    if ($password_raw !== $confirm_pass) {
        header('Location: ../pages/superadmin/sign_up.html?status=nomatch&show=register');
        exit();
    }

    if (strlen($password_raw) < 8 || !preg_match('/[!@#$%^&*]/', $password_raw)) {
        header('Location: ../pages/superadmin/sign_up.html?status=weak_password&show=register');
        exit();
    }

    if (!preg_match('/^\d{11}$/', $contact_number)) {
        header('Location: ../pages/superadmin/sign_up.html?status=invalid&show=register');
        exit();
    }

    // --- File Upload Logic ---
    $upload_dir = __DIR__ . '/../uploads/proofs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $max_file_size = 5 * 1024 * 1024; // 5MB limit
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $allowed_mimes = [
        'application/pdf',
        'image/jpeg',
        'image/png'
    ];
    $proof_paths = [
        'school_id_pic' => '',
        'reg_form' => '',
        'payment_scheme' => '',
        'pwd_front' => '',
        'pwd_back' => ''
    ];

    $upload_error = null;
    $upload_file = function ($key, $allowedExts, $allowedMimesList, $isRequired = true) use (&$proof_paths, &$student_id, &$upload_dir, &$max_file_size, &$upload_error) {
        if (!isset($_FILES[$key]) || !is_uploaded_file($_FILES[$key]['tmp_name'])) {
            if ($isRequired) {
                $upload_error = 'upload_failed';
                return false;
            }
            return true;
        }

        if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            $upload_error = 'upload_failed';
            return false;
        }

        if ($_FILES[$key]['size'] > $max_file_size) {
            $upload_error = 'too_large';
            return false;
        }

        $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $upload_error = 'invalid_file_type';
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = $finfo ? finfo_file($finfo, $_FILES[$key]['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }

        if ($mime_type !== '' && !in_array($mime_type, $allowedMimesList, true)) {
            $upload_error = 'invalid_file_type';
            return false;
        }

        $new_filename = strtoupper($key) . '_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $student_id) . '_' . time() . '.' . $ext;
        $destination = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES[$key]['tmp_name'], $destination)) {
            $proof_paths[$key] = 'uploads/proofs/' . $new_filename;
            return true;
        }

        $upload_error = 'upload_failed';
        return false;
    };

    foreach (['school_id_pic', 'reg_form', 'payment_scheme'] as $key) {
        if (!$upload_file($key, $allowed_extensions, $allowed_mimes, true)) {
            header('Location: ../pages/superadmin/sign_up.html?status=' . $upload_error . '&show=register');
            exit();
        }
    }

    if ($is_pwd) {
        $pwd_allowed_ext = ['jpg', 'jpeg', 'png'];
        $pwd_allowed_mimes = ['image/jpeg', 'image/png'];
        foreach (['pwd_front', 'pwd_back'] as $key) {
            if (!$upload_file($key, $pwd_allowed_ext, $pwd_allowed_mimes, true)) {
                header('Location: ../pages/superadmin/sign_up.html?status=' . $upload_error . '&show=register');
                exit();
            }
        }
    }

    $check_stmt = $conn->prepare('SELECT 1 FROM users WHERE student_id = ? OR email = ?');
    $check_stmt->bind_param('ss', $student_id, $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $check_stmt->close();
        header('Location: ../pages/superadmin/sign_up.html?status=exists&show=register');
        exit();
    }

    // --- Database Schema Check (Self-Healing) ---
    // Ensure all required registration columns exist in 'users' table to prevent SQL exceptions
    $required_columns = [
        'middle_name'    => "VARCHAR(255) AFTER `first_name` ",
        'password_hash'  => "VARCHAR(255) AFTER `email` ",
        'sex'            => "VARCHAR(50) AFTER `password_hash` ",
        'course'         => "VARCHAR(255) AFTER `sex` ",
        'department'     => "VARCHAR(255) AFTER `course` ",
        'year_level'     => "VARCHAR(100) AFTER `department` ",
        'contact_number' => "VARCHAR(20) AFTER `year_level` ",
        'address'        => "TEXT AFTER `contact_number` ",
        'status'         => "VARCHAR(50) DEFAULT 'pending' AFTER `address` ",
        'is_pwd'         => "TINYINT(1) DEFAULT 0 AFTER `status` ",
        'school_id_pic'  => "VARCHAR(255) AFTER `is_pwd` ",
        'reg_form'       => "VARCHAR(255) AFTER `school_id_pic` ",
        'payment_scheme' => "VARCHAR(255) AFTER `reg_form` ",
        'pwd_front'      => "VARCHAR(255) AFTER `payment_scheme` ",
        'pwd_back'       => "VARCHAR(255) AFTER `pwd_front` "
    ];

    foreach ($required_columns as $col => $definition) {
        $check = $conn->query("SHOW COLUMNS FROM `users` LIKE '$col'");
        if ($check && $check->num_rows === 0) {
            $conn->query("ALTER TABLE `users` ADD COLUMN `$col` $definition");
        }
    }

    $hashed_password = password_hash($password_raw, PASSWORD_DEFAULT);
    $stmt = $conn->prepare(
        'INSERT INTO users 
         (student_id, last_name, first_name, middle_name, email, password_hash, sex, course, department, year_level, contact_number, address, status, is_pwd, school_id_pic, reg_form, payment_scheme, pwd_front, pwd_back) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $bind_types = str_repeat('s', 13) . 'i' . str_repeat('s', 5);
    $stmt->bind_param(
        $bind_types,
        $student_id,
        $last_name,
        $first_name,
        $middle_name,
        $email,
        $hashed_password,
        $sex,
        $course,
        $department,
        $year_level,
        $contact_number,
        $address,
        $status,
        $is_pwd,
        $proof_paths['school_id_pic'],
        $proof_paths['reg_form'],
        $proof_paths['payment_scheme'],
        $proof_paths['pwd_front'],
        $proof_paths['pwd_back']
    );

    if ($stmt->execute()) {
        updateMemberCount($conn);
        $stmt->close();
        $check_stmt->close();
        header('Location: ../pages/superadmin/sign_up.html?status=success&show=register');
        exit();
    }

    $stmt->close();
    $check_stmt->close();
}

function updateMemberCount($conn) {
    $result = $conn->query('SELECT COUNT(*) AS cnt FROM users');
    $row = $result->fetch_assoc();
    $total = (int) $row['cnt'];
    $conn->query('INSERT INTO count_items (total_members) SELECT ' . $total . ' WHERE NOT EXISTS (SELECT 1 FROM count_items)');
    $conn->query('UPDATE count_items SET total_members = ' . $total);
}

$conn->close();
?>