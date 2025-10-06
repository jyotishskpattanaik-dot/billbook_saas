<?php
session_start();

// Reset session if requested
if (isset($_GET['reset']) && $_GET['reset'] == 1) {
    session_unset();
    session_destroy();
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_module'])) {
    $module = $_SESSION['user_module'];
    header("Location: ../" . $module . "/dashboard.php");
    exit;
}

// Message placeholders
$alert = '';
if (isset($_GET['registered'])) {
    $alert = "<div class='alert alert-success text-center'>✅ Registration successful! Please log in.</div>";
}
if (isset($_GET['logged_out'])) {
    $alert = "<div class='alert alert-info text-center'>ℹ️ You have been logged out successfully.</div>";
}
if (isset($_GET['expired'])) {
    $alert = "<div class='alert alert-danger text-center'>⏳ Session expired. Please log in again.</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

    <style>
        body, html {
            height: 100%;
            margin: 0;
        }
        body {
            background: url("../assets/img/slider/pharma_retail.jpg") no-repeat center center;
            background-size: cover;
        }
        .overlay {
            background: rgba(0,0,0,0.4); /* Optional dark overlay for contrast */
            height: 100vh;
            width: 100%;
            display: flex;
            justify-content: flex-end; /* Push login card to right */
        }
        .login-panel {
            background: #ffffff;
            width: 100%;
            max-width: 450px;
            height: 100vh; /* full height right panel */
            display: flex;
            flex-direction: column;
            justify-content: center; /* center form vertically */
            padding: 40px;
            box-shadow: -4px 0 15px rgba(0,0,0,0.2);
        }
        .card-header {
            background:  #ae00ffff;
            color: #fff;
            font-size: 1.2rem;
            font-weight: bold;
            text-align: center;
            padding: 15px;
            border-radius: 8px;
        }
        .login-btn {
            background:  #ae00ffff;
            border: none;
            transition: 0.3s;
        }
        .login-btn:hover {
            background:  #ae00ffff;
        }
        .back-btn {
            border: 1px solid  #ae00ffff;;
            transition: 0.3s;
        }
        .back-btn:hover {
            background:  #ae00ffff;
            color: #fff;
        }
        .forgot-link {
            display: block;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .overlay {
                justify-content: center;
            }
            .login-panel {
                max-width: 100%;
                height: auto;
                margin: 20px;
                border-radius: 12px;
            }
        }
    </style>
</head>
<body>

<div class="overlay">
    <div class="login-panel">
        <?= $alert ?>
        <div class="card-header">
            <i class="bi bi-person-circle"></i> User Login
        </div>
        <div class="card-body p-4">
            <form method="post" action="login_process.php">
                <div class="mb-3">
                    <label for="email" class="form-label">📧 Email</label>
                    <input type="email" id="email" name="email" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">🔑 Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn login-btn w-100 text-white">🚀 Login</button>
            </form>

            <div class="text-center">
                <a href="forgot_password.php" class="forgot-link text-decoration-none">❓ Forgot your password?</a>
            </div>

            <div class="text-center mt-3">
                <p>👉 Not registered yet? <a href="register.php">Register here</a></p>
                <a href="../index.php" class="btn back-btn w-100 mt-2">⬅️ Back to Home</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
