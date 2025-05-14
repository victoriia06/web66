<?php
// HTTP-аутентификация
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
  header('HTTP/1.1 401 Unauthorized');
  header('WWW-Authenticate: Basic realm="Admin Area"');
  echo '<h1>401 Требуется авторизация</h1>';
  exit();
}

// Подключение к БД
$user = 'u70422';
$pass = '4545635';
$dbname = 'u70422';

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Обработка действий администратора
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!empty($_POST['delete'])) {
            // Удаление пользователя
            $stmt = $db->prepare("DELETE FROM applications WHERE id = 
                                (SELECT application_id FROM users WHERE id = ?)");
            $stmt->execute([$_POST['delete']]);
            
            header("Location: admin.php");
            exit();
        }
    }
    
    // Получение статистики по языкам
    $stats = $db->query("
        SELECT 
            pl.name, 
            COUNT(al.application_id) as user_count 
        FROM programming_languages pl 
        LEFT JOIN application_languages al ON pl.id = al.language_id 
        GROUP BY pl.id, pl.name
        ORDER BY user_count DESC, pl.name
    ")->fetchAll();
    
    // Получение всех заявок с языками
    $applications = $db->query("
        SELECT 
            a.id as app_id,
            a.fio,
            a.tel,
            a.email,
            a.birth_date,
            a.gender,
            a.bio,
            u.id as user_id,
            u.login,
            (
                SELECT GROUP_CONCAT(pl.name SEPARATOR ', ')
                FROM application_languages al
                JOIN programming_languages pl ON al.language_id = pl.id
                WHERE al.application_id = a.id
            ) as languages
        FROM applications a
        JOIN users u ON a.id = u.application_id
        ORDER BY a.id DESC
    ")->fetchAll();
    
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <style>
        /* Стили остаются без изменений */
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Админ-панель</h1>
            <p>Вы вошли как администратор: <strong><?php echo htmlspecialchars($_SERVER['PHP_AUTH_USER']); ?></strong></p>
        </div>
        
        <div class="stats">
            <h2>Статистика по языкам программирования</h2>
            <table>
                <tr>
                    <th>Язык программирования</th>
                    <th>Количество пользователей</th>
                </tr>
                <?php foreach ($stats as $stat): ?>
                <tr>
                    <td><?php echo htmlspecialchars($stat['name']); ?></td>
                    <td><?php echo htmlspecialchars($stat['user_count']); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <h2>Все заявки</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Дата рождения</th>
                <th>Пол</th>
                <th>Языки программирования</th>
                <th>Действия</th>
            </tr>
            <?php foreach ($applications as $app): ?>
            <tr>
                <td><?php echo htmlspecialchars($app['user_id']); ?></td>
                <td><?php echo htmlspecialchars($app['login']); ?></td>
                <td><?php echo htmlspecialchars($app['fio']); ?></td>
                <td><?php echo htmlspecialchars($app['tel']); ?></td>
                <td><?php echo htmlspecialchars($app['email']); ?></td>
                <td><?php echo htmlspecialchars($app['birth_date']); ?></td>
                <td><?php echo $app['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></td>
                <td><?php echo htmlspecialchars($app['languages'] ?? 'Нет данных'); ?></td>
                <td class="actions">
                    <a href="edit.php?id=<?php echo $app['user_id']; ?>" class="btn btn-edit">Редактировать</a>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="delete" value="<?php echo $app['user_id']; ?>">
                        <button type="submit" class="btn btn-delete" onclick="return confirm('Вы уверены, что хотите удалить этого пользователя?')">Удалить</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
