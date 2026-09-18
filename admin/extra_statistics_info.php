<?php
require_once '../functions.php';
check_role('admin');
$conn = db_connect();

// ... (змінні без змін) ...
$error = '';
$report_type = $_POST['report_type'] ?? '';
$report_data = [];
$report_title = '';

$report_titles = [
    'max_weight' => 'Контракт із найбільшою вагою вантажу',
    'arrived_contracts' => 'Контракти, які прибули на кінцеву станцію',
    'status_count' => 'Кількість контрактів за статусом',
    'popular_cargo' => 'Найпопулярніший тип вантажу',
    'busy_stations' => 'Станції з найбільшою кількістю відправлень',
    'avg_duration' => 'Середня тривалість перевезень'
];
if (isset($report_titles[$report_type])) $report_title = $report_titles[$report_type];

$status_translations = [
    'arrived' => 'Прибув',
    'in_transit' => 'В дорозі',
    'new' => 'Новий'
];

if (!$conn) {
    $error = 'Помилка підключення до бази даних';
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $report_type) {
        $result = null; 
        
        if ($report_type === 'max_weight') {
            // JOIN з tariff для отримання назви cargo_type
            $query = '
            SELECT c.id, tr.cargo_type, c.weight, c.status, c.date_created, c.customer_phone 
            FROM contract c
            JOIN tariff tr ON c.tariff_id = tr.id
            ORDER BY c.weight DESC LIMIT 1';
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) $report_data = $result->fetch_assoc();
            else $error = 'Контракти відсутні';
        
        } elseif ($report_type === 'arrived_contracts') {
            // JOIN з tariff
            $query = '
            SELECT c.id, tr.cargo_type, c.weight, c.status, c.date_created, c.customer_phone, s.name AS dest_station
            FROM contract c
            JOIN tariff tr ON c.tariff_id = tr.id
            LEFT JOIN station s ON c.end_station_id = s.id
            WHERE c.status = "arrived"
            ';
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) $report_data[] = $row;
            } else $error = 'Контракти зі статусом "arrived" відсутні';
        
        } elseif ($report_type === 'status_count') {
            // ... (без змін, тут JOIN не треба) ...
            $query = 'SELECT status, COUNT(*) AS count FROM contract GROUP BY status';
            $result = $conn->query($query);
            $raw_data = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) $raw_data[] = $row;
            } else $error = 'Контракти відсутні';
            
            // ФП: Трансформація (array_map)
            $translator_function = function($row) use ($status_translations) {
                $raw_status = $row['status'];
                $translated_status = $status_translations[$raw_status] ?? $raw_status;
                return ['status_translated' => $translated_status, 'count' => $row['count']];
            };
            $report_data = array_map($translator_function, $raw_data);
        
        } elseif ($report_type === 'popular_cargo') {
            // JOIN з tariff, групуємо за назвою тарифу
            $query = '
            SELECT tr.cargo_type, COUNT(*) AS count
            FROM contract c
            JOIN tariff tr ON c.tariff_id = tr.id
            GROUP BY tr.cargo_type
            ORDER BY count DESC
            LIMIT 1
            ';
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) $report_data = $result->fetch_assoc();
            else $error = 'Контракти відсутні';

        } elseif ($report_type === 'busy_stations') {
		$query = '
        		SELECT s.name, COUNT(c.id) AS departure_count 
       			FROM station s 
		        JOIN contract c ON s.id = c.start_station_id 
		        GROUP BY s.id, s.name 
		        HAVING departure_count = (
		            SELECT COUNT(*) as max_cnt 
		            FROM contract 
		            GROUP BY start_station_id 
		            ORDER BY max_cnt DESC 
		            LIMIT 1
		        )';
		$result = $conn->query($query);
		if ($result && $result->num_rows > 0) {
		    while ($row = $result->fetch_assoc()) $report_data[] = $row;
		} else {
		    $error = 'Станції з відправленнями відсутні';
		}
        } elseif ($report_type === 'avg_duration') {
             // ... (без змін) ...
            $query = 'SELECT AVG(DATEDIFF(a.arrival_datetime, t.departure_datetime)) AS avg_days FROM transportation t JOIN arrival a ON t.contract_id = a.contract_id JOIN contract c ON t.contract_id = c.id WHERE c.status = "arrived"';
            $result = $conn->query($query);
            if ($result && $result->num_rows > 0) $report_data = $result->fetch_assoc();
            else $error = 'Дані про перевезення відсутні';
        }
        if ($result && is_object($result)) $result->free();
    }
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
<link rel="stylesheet" href="/styles.css">
<meta charset="UTF-8">
<title>X-Rails - Статистична інформація</title>
</head>
<body>
<header><h1>Додаткова статистична інформація</h1></header>
<main>
<div class="stats-container">
<nav class="nav-links"><a href="index.php" class="nav-btn">На головну</a><a href="/logout.php" class="nav-btn logout-btn">Вийти</a></nav>
<?php if ($error): ?><div class="toast-message error visible" id="toast"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="stats-list">
<form action="extra_statistics_info.php" method="post"><input type="hidden" name="report_type" value="max_weight"><button type="submit" class="stats-btn">Контракт із найбільшою вагою вантажу</button></form>
<form action="extra_statistics_info.php" method="post"><input type="hidden" name="report_type" value="avg_duration"><button type="submit" class="stats-btn">Середня тривалість перевезень</button></form>
<form action="extra_statistics_info.php" method="post"><input type="hidden" name="report_type" value="status_count"><button type="submit" class="stats-btn">Кількість контрактів за статусом</button></form>
<form action="extra_statistics_info.php" method="post"><input type="hidden" name="report_type" value="popular_cargo"><button type="submit" class="stats-btn">Найпопулярніший тип вантажу</button></form>
<form action="extra_statistics_info.php" method="post"><input type="hidden" name="report_type" value="busy_stations"><button type="submit" class="stats-btn">Станції з найбільшою кількістю відправлень</button></form>
<form action="extra_statistics_info.php" method="post"><input type="hidden" name="report_type" value="arrived_contracts"><button type="submit" class="stats-btn">Контракти, які прибули на кінцеву станцію</button></form>
</div>
</div>

<div class="modal <?php echo !empty($report_data) ? 'active' : ''; ?>" id="reportModal">
<div class="modal-content">
<span class="modal-close" onclick="closeModal()">×</span>
<h2><?php echo e($report_title); ?></h2>
<table class="tariff-table">
<?php if (!empty($report_data)): ?>

    <?php if ($report_type === 'max_weight'): ?>
        <thead><tr><th>ID</th><th>Тип вантажу</th><th>Вага</th><th>Статус</th><th>Дата</th><th>Телефон</th></tr></thead>
        <tbody><tr>
            <td><?php echo e($report_data['id']); ?></td>
            <td><?php echo e($report_data['cargo_type']); ?></td>
            <td><?php echo e($report_data['weight']); ?></td>
            <td><?php echo e($status_translations[$report_data['status']] ?? $report_data['status']); ?></td> 
            <td><?php echo e($report_data['date_created']); ?></td>
            <td><?php echo e($report_data['customer_phone']); ?></td>
        </tr></tbody>

    <?php elseif ($report_type === 'arrived_contracts'): ?>
        <thead><tr><th>ID</th><th>Тип вантажу</th><th>Вага</th><th>Статус</th><th>Дата</th><th>Телефон</th><th>Станція</th></tr></thead>
        <tbody>
            <?php foreach ($report_data as $row): ?>
            <tr>
                <td><?php echo e($row['id']); ?></td>
                <td><?php echo e($row['cargo_type']); ?></td>
                <td><?php echo e($row['weight']); ?></td>
                <td><?php echo e($status_translations[$row['status']] ?? $row['status']); ?></td> 
                <td><?php echo e($row['date_created']); ?></td>
                <td><?php echo e($row['customer_phone']); ?></td>
                <td><?php echo e($row['dest_station']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>

    <?php elseif ($report_type === 'status_count'): ?>
        <thead><tr><th>Статус</th><th>Кількість</th></tr></thead>
        <tbody>
            <?php foreach ($report_data as $row): ?>
            <tr><td><?php echo e($row['status_translated']); ?></td><td><?php echo e($row['count']); ?></td></tr>
            <?php endforeach; ?>
        </tbody>

    <?php elseif ($report_type === 'popular_cargo'): ?>
        <thead><tr><th>Тип вантажу</th><th>Кількість</th></tr></thead>
        <tbody><tr><td><?php echo e($report_data['cargo_type']); ?></td><td><?php echo e($report_data['count']); ?></td></tr></tbody>

    <?php elseif ($report_type === 'busy_stations'): ?>
        <thead><tr><th>Станція</th><th>Кількість</th></tr></thead>
        <tbody>
            <?php foreach ($report_data as $row): ?>
            <tr><td><?php echo e($row['name']); ?></td><td><?php echo e($row['departure_count']); ?></td></tr>
            <?php endforeach; ?>
        </tbody>

    <?php elseif ($report_type === 'avg_duration'): ?>
        <thead><tr><th>Середня тривалість (дні)</th></tr></thead>
        <tbody><tr><td><?php echo e(number_format($report_data['avg_days'], 2)); ?></td></tr></tbody>
    <?php endif; ?>
<?php endif; ?>
</table>
</div>
</div>
</main>
<footer><p>© 2025 X-Rails. Для внутрішнього використання.</p></footer>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.getElementById('toast');
    if (toast) { setTimeout(() => { toast.classList.remove('visible'); toast.style.opacity = '0'; }, 3000); }
    const modal = document.getElementById('reportModal');
    if (modal) { modal.addEventListener('click', function(event) { if (event.target === modal) closeModal(); }); }
});
function closeModal() { const modal = document.getElementById('reportModal'); if (modal) modal.classList.remove('active'); }
</script>
</body>
</html>
