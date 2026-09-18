<?php
require_once 'functions.php'; // Автоматично запускає session_start()

// Перевірка, чи користувач уже авторизований
if (isset($_SESSION['username'])) {
    if ($_SESSION['role'] === 'manager') {
        header('Location: manager/index.php'); // Перенаправлення в папку manager
        exit;
    } elseif ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php'); // Перенаправлення в папку admin
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Використання функції для підключення до БД
    $conn = db_connect();

    if (!$conn) {
        $error = 'Помилка підключення до бази даних';
    } else {
        // Перевірка користувачa
        $stmt = $conn->prepare('SELECT username, role FROM user WHERE username = ? AND password = SHA2(?, 256)');
        $stmt->bind_param('ss', $username, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Перенаправлення залежно від ролі
            if ($user['role'] === 'manager') {
                header('Location: manager/index.php');
            } elseif ($user['role'] === 'admin') {
                header('Location: admin/index.php');
            }
            exit;
        } else {
            $error = 'Невірний логін або пароль';
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <link rel="stylesheet" href="/styles.css"> <meta charset="UTF-8">
    <title>X-Rails - Авторизація</title>
</head>
<body>
    <header>
        <h1>X-Rails: Авторизація</h1>
        <h3>Внутрішня система управління залізничними перевезеннями</h3>
    </header>
    
    <main>
        <form action="index.php" method="post" class="login-form">
            <div class="form-group">
                <label for="username">Логін:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <?php if ($error): ?>
                <div class="error-message visible"><?php echo e($error); ?></div>
            <?php endif; ?>
            <div class="form-group submit-group">
                <input type="submit" value="Увійти" class="submit-btn">
            </div>
        </form>
    </main>
    
    <footer>
        <p>© 2025 X-Rails. Для внутрішнього використання.</p>
    </footer>
</body>
</html>
