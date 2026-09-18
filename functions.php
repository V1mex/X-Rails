<?php
// Імпортує config.php, стартує сесію, реалізує допоміжні функції

require_once 'config.php';

// Запускаємо сесію тут, щоб вона була доступна на всіх сторінках
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Функція підключення до бази даних
 * @return mysqli|null Об'єкт з'єднання mysqli або null у разі помилки
 */
function db_connect() {
    static $conn; // Зберігаємо з'єднання, щоб не підключатися повторно

    if ($conn === null) {
        try {
            $conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
            if ($conn->connect_error) {
                return null;
            }
            $conn->set_charset('utf8');
        } catch (Exception $e) {
            return null;
        }
    }
    return $conn;
}

/**
 * Перевіряє, чи авторизований користувач і чи має він потрібну роль
 * @param string $role Роль для перевірки ('manager' або 'admin')
 */
function check_role($role) {
    if (!isset($_SESSION['username']) || $_SESSION['role'] !== $role) {
        // Якщо роль не та - перенаправляємо на сторінку входу
        header('Location: /index.php'); // Завжди повертаємо в корінь
        exit;
    }
}

/**
 * Допоміжна функція для безпечного виведення даних в HTML
 * @param string|null $data Дані для виведення
 * @return string Відфільтровані дані
 */
function e($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// --- ЕЛЕМЕНТИ ФУНКЦІОНАЛЬНОГО ПРОГРАМУВАННЯ ---

/**
 * Чиста функція для розрахунку вартості контракту.
 * Не має побічних ефектів, результат залежить виключно від вхідних аргументів.
 * * @param float $distance Відстань у км
 * @param float $price_per_ton_km Тариф (ціна за 1 тонну на 1 км)
 * @param int $weight Вага вантажу в тоннах
 * @return float Розрахована вартість
 */
function calculate_contract_cost(float $distance, float $price_per_ton_km, int $weight): float {
    return $distance * $price_per_ton_km * $weight;
}

/**
 * Декларативна функція для генерації HTML-опцій (<option>) для випадаючих списків.
 * Використовує функцію вищого порядку array_map замість імперативних циклів foreach.
 * * @param array $items Масив даних для списку
 * @param callable $valueFn Функція зворотного виклику для отримання значення (value)
 * @param callable $labelFn Функція зворотного виклику для отримання підпису (label)
 * @param mixed $selected Значення, яке має бути обраним за замовчуванням
 * @return string Згенерований HTML-код опцій
 */
function render_options(array $items, callable $valueFn, callable $labelFn, $selected = null): string {
    // array_map застосовує функцію до кожного елемента масиву, повертаючи новий масив рядків
    $options = array_map(function ($item) use ($valueFn, $labelFn, $selected) {
        $val = $valueFn($item);
        $lab = $labelFn($item);
        // Сувора перевірка типів приводиться до рядка для коректного порівняння у HTML
        $isSelected = ((string)$val === (string)$selected) ? 'selected' : '';
        return sprintf('<option value="%s" %s>%s</option>', e($val), $isSelected, e($lab));
    }, $items);

    return implode('', $options);
}
?>
