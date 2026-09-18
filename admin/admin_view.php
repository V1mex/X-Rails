<?php
require_once '../functions.php';
check_role('admin');
$conn = db_connect();

$tariff = [];
$error = '';

if (!$conn) {
    die('Помилка підключення до бази даних'); // Або $error = '...'
} else {
    // Отримання тарифів
    $result = $conn->query('SELECT cargo_type, price_per_km_ton FROM tariff');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tariff[] = $row;
        }
        $result->free();
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <link rel="stylesheet" href="/styles.css">
    <meta charset="UTF-8">
    <title>X-Rails - Перегляд тарифів (адміністратор)</title>
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
            <table class="tariff-table">
                <thead>
                    <tr>
                        <th>Тип вантажу</th>
                        <th>Ціна за 1 км/тон (грн)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tariff)): ?>
                        <tr>
                            <td colspan="2">Тарифи відсутні</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tariff as $tariff): ?>
                            <tr>
                                <td><?php echo e($tariff['cargo_type']); ?></td>
                                <td><?php echo e($tariff['price_per_km_ton']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="action-group">
                <a href="admin_edit.php" class="action-btn">Перейти до редагування</a>
            </div>
        </div>
    </main>

    <footer>
        <p>© 2025 X-Rails. Для внутрішнього використання.</p>
    </footer>
</body>
</html>
