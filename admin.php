<?php

/**
 * Задача 6. Реализовать вход администратора с использованием
 * HTTP-авторизации для просмотра и удаления результатов.
 **/

// HTTP-аутентификация
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
  header('HTTP/1.1 401 Не авторизован');
  header('WWW-Authenticate: Basic realm="Панель администратора"');
  print('<h1>401 Требуется авторизация</h1>');
  exit();
}

// Подключение к базе данных
$user = 'u70422';
$pass = '4545635';
$dbname = 'u70422';

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Обработка действий (удаление или редактирование)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['delete_id'])) {
        // Удаление пользователя
        try {
            $db->beginTransaction();
            
            // Получаем ID заявки из таблицы users
            $stmt = $db->prepare("SELECT application_id FROM users WHERE id = ?");
            $stmt->execute([$_POST['delete_id']]);
            $appId = $stmt->fetchColumn();
            
            if ($appId) {
                // Удаляем из application_languages
                $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
                $stmt->execute([$appId]);
                
                // Удаляем из users
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_POST['delete_id']]);
                
                // Удаляем из applications
                $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
                $stmt->execute([$appId]);
            }
            
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            die('Ошибка при удалении пользователя: ' . $e->getMessage());
        }
    } elseif (!empty($_POST['edit_id'])) {
        // Редактирование пользователя - перенаправляем на форму редактирования
        header("Location: edit.php?id=" . $_POST['edit_id']);
        exit();
    }
}

// Получение всех данных пользователей
$users = [];
try {
    $stmt = $db->query("
        SELECT u.id, u.login, a.* 
        FROM users u 
        JOIN applications a ON u.application_id = a.id
        ORDER BY a.fio
    ");
    $users = $stmt->fetchAll();
    
    // Получаем языки программирования для каждого пользователя
    foreach ($users as &$user) {
        $stmt = $db->prepare("
            SELECT pl.name 
            FROM application_languages al 
            JOIN programming_languages pl ON al.language_id = pl.id 
            WHERE al.application_id = ?
        ");
        $stmt->execute([$user['id']]);
        $user['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($user); // разрываем ссылку
} catch (PDOException $e) {
    die('Ошибка при получении данных пользователей: ' . $e->getMessage());
}

// Получение статистики по языкам
$languageStats = [];
try {
    $stmt = $db->query("
        SELECT pl.name, COUNT(al.application_id) as user_count 
        FROM application_languages al 
        JOIN programming_languages pl ON al.language_id = pl.id 
        GROUP BY pl.name 
        ORDER BY user_count DESC, pl.name
    ");
    $languageStats = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Ошибка при получении статистики по языкам: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <style>
        /* Стили остаются без изменений */
    </style>
</head>
<body>
    <h1>Панель администратора</h1>
    <p>Вы вошли как администратор.</p>
    
    <h2>Данные пользователей</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Дата рождения</th>
                <th>Пол</th>
                <th>Языки программирования</th>
                <th>Биография</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['login']) ?></td>
                <td><?= htmlspecialchars($user['fio']) ?></td>
                <td><?= htmlspecialchars($user['tel']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['birth_date']) ?></td>
                <td><?= htmlspecialchars($user['gender'] == 'male' ? 'Мужской' : 'Женский') ?></td>
                <td><?= implode(', ', array_map('htmlspecialchars', $user['languages'])) ?></td>
                <td><?= nl2br(htmlspecialchars($user['bio'])) ?></td>
                <td>
                    <form class="action-form" method="POST" action="admin.php">
                        <input type="hidden" name="edit_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="edit-btn">Редактировать</button>
                    </form>
                    <form class="action-form" method="POST" action="admin.php" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
                        <input type="hidden" name="delete_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="delete-btn">Удалить</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="stats">
        <h2>Статистика по языкам программирования</h2>
        <table>
            <thead>
                <tr>
                    <th>Язык программирования</th>
                    <th>Количество пользователей</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($languageStats as $stat): ?>
                <tr>
                    <td><?= htmlspecialchars($stat['name']) ?></td>
                    <td><?= htmlspecialchars($stat['user_count']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
