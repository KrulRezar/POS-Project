<?php
// index.php (Login Page)
require_once 'config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // INSECURE QUERY: Fetching plain-text password for comparison
    $sql = "SELECT u.user_id, u.password, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.username = ?";

    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($user_id, $db_password, $role_name);
            if ($stmt->fetch()) {
                // INSECURE: Direct comparison
                if ($password === $db_password) { 
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role_name'] = $role_name;

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "Invalid username or password.";
                }
            }
        } else {
            $error = "Invalid username or password.";
        }
        $stmt->close();
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="icon" type="image/png" href="favicon.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); /* Softer, inviting gradient */
        }
        .login-card {
            backdrop-filter: blur(10px); /* Frosted glass effect */
            background-color: rgba(255, 255, 255, 0.9); /* Slightly transparent white */
            border-radius: 1.5rem; /* More rounded corners */
            padding: 2.5rem; /* Increased padding */
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2); /* Stronger, softer shadow */
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center text-gray-800">

    <div class="login-card w-full max-w-md">
        <h2 class="text-4xl font-extrabold text-center mb-8 text-purple-800">
            <i class="fas fa-store-alt mr-2"></i> POS System
        </h2>
        <p class="text-center text-gray-600 mb-6">Sign in to manage your point of sale.</p>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg relative mb-6" role="alert">
                <strong class="font-semibold">Error:</strong>
                <span class="block sm:inline"><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <form action="index.php" method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username"
                       class="mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500 transition duration-200 ease-in-out">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       class="mt-1 block w-full px-4 py-2.5 border border-gray-300 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500 transition duration-200 ease-in-out">
            </div>

            <div>
                <button type="submit"
                        class="w-full flex justify-center py-3 px-4 rounded-lg shadow-lg text-lg font-semibold text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition duration-200 ease-in-out transform hover:-translate-y-0.5">
                    <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                </button>
            </div>
        </form>

        <p class="mt-8 text-center text-sm text-gray-500">
            Default Users: 
            <span class="font-semibold">admin/password</span>, 
            <span class="font-semibold">manager/password</span>, 
            <span class="font-semibold">cashier/password</span>
        </p>
    </div>

</body>
</html>
