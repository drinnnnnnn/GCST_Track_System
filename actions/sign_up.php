<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();

if (!isset($conn) || $conn->connect_error) {
    header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=error&show=register');
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
    $year_section   = trim(filter_input(INPUT_POST, 'year_section', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $contact_number = trim(filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $address        = trim(filter_input(INPUT_POST, 'address', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '');
    $status         = 'Pending';

    if ($student_id === '' || $last_name === '' || $first_name === '' || $email === '' || $password_raw === '' || $sex === '' || $course === '' || $department === '' || $year_section === '') {
        header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=invalid&show=register');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=invalid_email&show=register');
        exit();
    }

    if ($password_raw !== $confirm_pass) {
        header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=nomatch&show=register');
        exit();
    }

    if (strlen($password_raw) < 8) {
        header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=weak_password&show=register');
        exit();
    }

    // --- File Upload Logic ---
    $upload_dir = __DIR__ . '/../uploads/proofs/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $max_file_size = 5 * 1024 * 1024; // 5MB limit
    $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png'];
    $proof_paths = ['school_id_pic' => '', 'reg_form' => '', 'payment_scheme' => ''];
    foreach ($proof_paths as $key => &$path) {
        if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
            if ($_FILES[$key]['size'] > $max_file_size) {
                header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=too_large&show=register');
                exit();
            }

            $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_extensions)) {
                header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=invalid_file_type&show=register');
                exit();
            }

            // Verify actual file content (MIME type) for better security
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES[$key]['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_mimes)) {
                header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=invalid_file_type&show=register');
                exit();
            }

            $new_filename = strtoupper($key) . '_' . $student_id . '_' . time() . '.' . $ext;
            $destination = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES[$key]['tmp_name'], $destination)) {
                $path = 'uploads/proofs/' . $new_filename; // Store relative path
            }
        }
    }

    // Ensure all files were successfully uploaded
    if ($proof_paths['school_id_pic'] === '' || $proof_paths['reg_form'] === '' || $proof_paths['payment_scheme'] === '') {
        header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=upload_failed&show=register');
        exit();
    }

    $check_stmt = $conn->prepare('SELECT 1 FROM users WHERE student_id = ? OR email = ?');
    $check_stmt->bind_param('ss', $student_id, $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $check_stmt->close();
        header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=exists&show=register');
        exit();
    }

    // --- Database Schema Check (Self-Healing) ---
    // Ensure all required registration columns exist in 'users' table to prevent SQL exceptions
    $required_columns = [
        'middle_name'    => "VARCHAR(255) AFTER `first_name` ",
        'sex'            => "VARCHAR(50) AFTER `password` ",
        'course'         => "VARCHAR(255) AFTER `sex` ",
        'department'     => "VARCHAR(255) AFTER `course` ",
        'year_section'   => "VARCHAR(100) AFTER `department` ",
        'contact_number' => "VARCHAR(20) AFTER `year_section` ",
        'address'        => "TEXT AFTER `contact_number` ",
        'status'         => "VARCHAR(50) DEFAULT 'Pending' AFTER `address` ",
        'school_id_pic'  => "VARCHAR(255) AFTER `status` ",
        'reg_form'       => "VARCHAR(255) AFTER `school_id_pic` ",
        'payment_scheme' => "VARCHAR(255) AFTER `reg_form` "
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
         (student_id, last_name, first_name, middle_name, email, password, sex, course, department, year_section, contact_number, address, status, school_id_pic, reg_form, payment_scheme) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'ssssssssssssssss',
        $student_id,
        $last_name,
        $first_name,
        $middle_name,
        $email,
        $hashed_password,
        $sex,
        $course,
        $department,
        $year_section,
        $contact_number,
        $address,
        $status,
        $proof_paths['school_id_pic'],
        $proof_paths['reg_form'],
        $proof_paths['payment_scheme']
    );

    if ($stmt->execute()) {
        updateMemberCount($conn);
        $stmt->close();
        $check_stmt->close();
        header('Location: http://localhost/GCST_Track_System/pages/superadmin/sign_up.html?status=success&show=register');
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