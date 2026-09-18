<?php

require_once '../functions.php';
check_role('manager');
$conn = db_connect();

// Ініціалізація змінних
$error = '';
$tariff = [];

if (!$conn) {
    $error = 'Помилка підключення до бази даних';
} else {
    // Завантаження тарифів
    $result = $conn->query('SELECT cargo_type, price_per_km_ton FROM tariff');
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $tariff[] = $row;
        }
    } else {
        $error = 'Тарифи відсутні';
    }
    if ($result) {
        $result->free();
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <link rel="stylesheet" href="/styles.css">
    <meta charset="UTF-8">
    <title>X-Rails - Перегляд поточних тарифів</title>
</head>
<body>
    <header>
        <h1>Перегляд поточних тарифів</h1>
    </header>
    
    <main>
        <div class="tariff-container">
            <nav class="nav-links">
                <a href="index.php" class="nav-btn">На головну</a>
                <a href="/logout.php" class="nav-btn logout-btn">Вийти</a>
            </nav>
            <?php if ($error): ?>
                <div class="toast-message error visible" id="toast"><?php echo e($error); ?></div>
            <?php endif; ?>
            <table class="tariff-table">
                <thead>
                    <tr>
                        <th>Тип вантажу</th>
                        <th>Ціна за 1 км/тон (грн)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tariff as $tariff): ?>
                        <tr>
                            <td><?php echo e($tariff['cargo_type']); ?></td>
                            <td><?php echo e($tariff['price_per_km_ton']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
    
    <footer>
        <p>© 2025 X-Rails. Для внутрішнього використання.</p>
    </footer>

    <script>
        // Приховування спливаючого повідомлення через 3 секунди
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toast');
            if (toast) {
                setTimeout(() => {
                    toast.classList.remove('visible');
                    toast.style.opacity = '0';
                }, 3000);
            }
        });
    </script>
</body>
</html>
