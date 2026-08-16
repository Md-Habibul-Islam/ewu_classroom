<?php
include('db.php');
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error_msg = "";
$success_msg = "";

if (isset($_POST['register'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email formatting! Use name@domain.com structure.";
    } else {
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $error_msg = "Registration Failed. Email already registered!";
        } else {
            $reg_stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $reg_stmt->bind_param("ssss", $name, $email, $password, $role);

            if ($reg_stmt->execute()) {
                $success_msg = "Account created successfully! You can log in now.";
            } else {
                $error_msg = "Database processing error.";
            }
            $reg_stmt->close();
        }
        $check_stmt->close();
    }
}


if (isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $login_stmt = $conn->prepare("SELECT user_id, name, role FROM users WHERE email = ? AND password = ?");
    $login_stmt->bind_param("ss", $email, $password);
    $login_stmt->execute();
    $result = $login_stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['role']    = $user['role'];
        
        $login_stmt->close();
        header("Location: dashboard.php");
        exit();
    } else {
        $error_msg = "Invalid email or password combination.";
    }
    $login_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EWU Classroom Portal - Welcome</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef2f7; display: flex; height: 100vh; justify-content: center; align-items: center; margin: 0; }
        .auth-container { background: white; padding: 35px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 380px; }
        .auth-container h2 { text-align: center; color: #007bff; margin-top: 0; margin-bottom: 25px; }
        input, select { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007bff; color: white; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 1em; margin-top: 10px; }
        button:hover { background: #0056b3; }
        .toggle-link { text-align: center; margin-top: 20px; font-size: 0.9em; color: #555; }
        .toggle-link span { color: #007bff; cursor: pointer; font-weight: bold; }
        .alert { padding: 10px; border-radius: 4px; font-size: 0.9em; margin-bottom: 15px; text-align: center; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    </style>
</head>
<body>

<div class="auth-container">
    <h2>EWU CLASSROOM</h2>

    <?php if(!empty($error_msg)): ?>
        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
    <?php endif; ?>
    <?php if(!empty($success_msg)): ?>
        <div class="alert alert-success"><?php echo $success_msg; ?></div>
    <?php endif; ?>

    <div id="login-box">
        <form action="index.php" method="POST">
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" name="login">Log In</button>
        </form>
        <div class="toggle-link">Don't have an account? <span onclick="toggleAuth()">Register here</span></div>
    </div>

    <div id="register-box" style="display: none;">
        <form action="index.php" method="POST">
            <input type="text" name="name" placeholder="Full Name" required>
            <input type="email" name="email" placeholder="Email Address" required>
            <input type="password" name="password" placeholder="Choose Password" required>
            <select name="role" required>
                <option value="" disabled selected>Select User Persona</option>
                <option value="student">Student</option>
                <option value="teacher">Teacher</option>
            </select>
            <button type="submit" name="register">Sign Up Account</button>
        </form>
        <div class="toggle-link">Already registered? <span onclick="toggleAuth()">Log in here</span></div>
    </div>
</div>

<script>
    function toggleAuth() {
        var loginBox = document.getElementById('login-box');
        var registerBox = document.getElementById('register-box');
        
        if (loginBox.style.display === 'none') {
            loginBox.style.display = 'block';
            registerBox.style.display = 'none';
        } else {
            loginBox.style.display = 'none';
            registerBox.style.display = 'block';
        }
    }
</script>

</body>
</html>