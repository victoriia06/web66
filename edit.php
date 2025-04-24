<?php
// Аутентификация (аналогично admin.php)
session_start();

// Подключение к БД (аналогично admin.php)

// Получаем ID пользователя для редактирования (ИСПРАВЛЕНО)
$userId = $_GET['id'] ?? null;
if (!$userId) die("ID пользователя не указан");

// Получаем данные пользователя (ИСПРАВЛЕНО)
$userData = $db->prepare("
    SELECT u.id, u.login, a.* 
    FROM users u 
    JOIN applications a ON u.application_id = a.id 
    WHERE u.id = ?
")->execute([$userId])->fetch();

if (!$userData) die("Пользователь не найден");

// Получаем языки пользователя
$userLanguages = $db->prepare("
    SELECT pl.name 
    FROM application_languages al
    JOIN programming_languages pl ON al.language_id = pl.id
    WHERE al.application_id = ?
")->execute([$userData['id']])->fetchAll(PDO::FETCH_COLUMN);

// Обработка формы редактирования
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // ... (ваша логика обработки формы)
}
?>

<!-- HTML-форма редактирования -->
