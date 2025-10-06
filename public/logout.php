<?php
session_start();

// ✅ Explicitly clear important session values
unset($_SESSION['user_id']);
unset($_SESSION['user_name']);
unset($_SESSION['user_email']);
unset($_SESSION['user_module']);
unset($_SESSION['module_db']);
unset($_SESSION['company_id']);
unset($_SESSION['user_role']);
unset($_SESSION['financial_year_id']);
unset($_SESSION['available_years']);
unset($_SESSION['pending_user_id']);
unset($_SESSION['pending_modules']);
unset($_SESSION['temp_user_id']);
unset($_SESSION['temp_user_name']);

// Remove all remaining session variables
session_unset();

// Destroy the session
session_destroy();

// Redirect back to login page with a message
header("Location: login.php?logged_out=1");
exit;
