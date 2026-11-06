<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'connect.php';

// ---------------------------------------------------------------------
// SIGN-UP LOGIC
// ---------------------------------------------------------------------
if (isset($_POST['signUp'])) {
    $firstName  = trim($_POST['fName']);
    $middleName = trim($_POST['mName']);
    $lastName   = trim($_POST['lName']);
    $userName   = trim($_POST['uName']);
    $conNumber  = trim($_POST['conNumber']);
    $addStreet  = trim($_POST['addStreet']);
    $addBrgy    = trim($_POST['addBrgy']);
    $addCity    = trim($_POST['addCity']);
    $addProv    = trim($_POST['addProv']);
    $addPost    = trim($_POST['addPost']);
    $pWord      = $_POST['pWord'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if ($pWord !== $confirmPassword) {
        $_SESSION['signup_error'] = 'Passwords do not match.';
        header("Location: sign-up.php");
        exit();
    }

    if (strlen($pWord) < 8) {
        $_SESSION['signup_error'] = 'Password must be at least 8 characters.';
        header("Location: sign-up.php");
        exit();
    }

    // 1. Check if username exists (Matches user_id and username column)
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    if ($checkStmt === false) {
        die("SELECT Prepare Error: " . $conn->error);
    }

    $checkStmt->bind_param("s", $userName);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows > 0) {
        $checkStmt->close();
        $_SESSION['signup_error'] = 'Username already exists. Please choose another username.';
        header("Location: sign-up.php");
        exit();
    } else {
        $checkStmt->close();

        // 2. Hash password securely
        $pWordHash = password_hash($pWord,PASSWORD_DEFAULT);

        // 3. Insert user record with exact database column names
        $insertQuery = "INSERT INTO users 
            (first_name, middle_name, last_name, username, contact_no, street, barangay, city, province, postal, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $insertStmt = $conn->prepare($insertQuery);
        if ($insertStmt === false) {
            die("INSERT Prepare Error: " . $conn->error);
        }

        $insertStmt->bind_param(
            "sssssssssss", 
            $firstName, $middleName, $lastName, $userName, 
            $conNumber, $addStreet, $addBrgy, $addCity, 
            $addProv, $addPost, $pWordHash
        );

        if ($insertStmt->execute()) {
            $insertStmt->close();
            header("Location: login.php");
            exit();
        } else {
            echo "Execution Error: " . $insertStmt->error;
        }
    }
}

/// ---------------------------------------------------------------------
// LOG-IN LOGIC
// ---------------------------------------------------------------------
if (isset($_POST['logIn'])) {
    $userName = trim($_POST['uName'] ?? '');
    $pWord    = $_POST['pWord'] ?? '';

    $loginStmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    if ($loginStmt === false) {
        die("LOGIN Prepare Error: " . $conn->error);
    }

    $loginStmt->bind_param("s", $userName);
    $loginStmt->execute();
    $result = $loginStmt->get_result();

    $row = $result->fetch_assoc();
    $loginStmt->close();

    if ($row && password_verify($pWord, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['position'] = $row['position'] ?? null;

        session_write_close();
        header(($row['position'] ?? null) == 2
            ? "Location: admin-users.html"
            : "Location: cstmr-prod.php");
        exit();
    }

    header("Location: login.php?error=invalid");
    exit();
}