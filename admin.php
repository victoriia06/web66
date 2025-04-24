<?php
// Аутентификация (оставляем без изменений)
$correct_login = 'admin';
$correct_password_hash = md5('123');

if (!isset($_SERVER['PHP_AUTH_USER']) || 
    !isset($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != $correct_login ||
    md5($_SERVER['PHP_AUTH_PW']) != $correct_password_hash) {
    
    header('WWW-Authenticate: Basic realm="Админ-зона"');
    header('HTTP/1.0 401 Unauthorized');
    die('<h1>Доступ запрещен</h1><p>Неверные учетные данные</p>');
}

// Подключение к БД
$db_config = [
    'host' => 'localhost',
    'dbname' => 'u70422',
    'user' => 'u70422',
    'pass' => '4545635'
];

try {
    $db = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']}", 
        $db_config['user'], 
        $db_config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Обработка удаления (ИСПРАВЛЕНО)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    try {
        $db->beginTransaction();
        
        // 1. Получаем ID заявки
        $stmt = $db->prepare("SELECT application_id FROM users WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        $appId = $stmt->fetchColumn();
        
        if ($appId) {
            // 2. Удаляем языки
            $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
            $stmt->execute([$appId]);
            
            // 3. Удаляем пользователя
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$_POST['delete_id']]);
            
            // 4. Удаляем заявку
            $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
            $stmt->execute([$appId]);
        }
        
        $db->commit();
        header("Location: admin.php?deleted=1");
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        die("Ошибка при удалении: " . $e->getMessage());
    }
}

// Получение пользователей (ИСПРАВЛЕНО)
$users = $db->query("
    SELECT u.id, u.login, a.* 
    FROM users u 
    JOIN applications a ON u.application_id = a.id
    ORDER BY a.fio
")->fetchAll();

// Получение статистики по языкам (ИСПРАВЛЕНО)
$language_stats = $db->query("
    SELECT pl.name, COUNT(al.application_id) as count
    FROM programming_languages pl
    LEFT JOIN application_languages al ON pl.id = al.language_id
    GROUP BY pl.name
    ORDER BY count DESC
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <!-- Стили остаются без изменений -->
</head>
<body>
    <div class="container">
        <h1>Панель администратора</h1>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">
                Пользователь успешно удален
            </div>
        <?php endif; ?>
        
        <h2>Список пользователей</h2>
        <table>
            <!-- Таблица пользователей -->
        </table>
        
        <div class="stats">
            <h2>Статистика по языкам программирования</h2>
            <?php if (count($language_stats) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Язык</th>
                            <th>Количество пользователей</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($language_stats as $stat): ?>
                        <tr>
                            <td><?= htmlspecialchars($stat['name']) ?></td>
                            <td><?= htmlspecialchars($stat['count']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Нет данных о языках программирования</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
