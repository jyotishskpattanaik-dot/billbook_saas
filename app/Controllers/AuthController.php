<?php
namespace App\Controllers;

use App\Models\User;

class AuthController {
    public function login($username, $password) {
        $userModel = new User();
        $user = $userModel->findByUsername($username);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION["username"] = $user['username'];
            $_SESSION["usertype"] = $user['usertype'];

            if ($user['usertype'] === 'admin') {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../user/index.php");
            }
            exit;
        }

        return "Invalid username or password.";
    }

    public function register($username, $password, $fullname, $email, $mobile, $usertype = 'user', $actsession = null) {
        $userModel = new User();

        if ($userModel->findByUsername($username)) {
            return "❌ Username already taken.";
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $result = $userModel->createUser($username, $hash, $fullname, $email, $mobile, $usertype, $actsession);

        return $result ? "✅ Registration successful. You can now log in." : "❌ Registration failed.";
    }
}
