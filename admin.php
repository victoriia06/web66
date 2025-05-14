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
        SELECT pl.name, COUNT(al.application_id) as user_count 
        FROM programming_languages pl 
        LEFT JOIN application_languages al ON pl.id = al.language_id 
        GROUP BY pl.id 
        ORDER BY user_count DESC, pl.name
    ")->fetchAll();
    
    // Получение всех заявок с языками
    $applications = $db->query("
        SELECT a.*, u.id as user_id, u.login, GROUP_CONCAT(pl.name SEPARATOR ', ') as languages 
        FROM applications a 
        JOIN users u ON a.id = u.application_id
        LEFT JOIN application_languages al ON a.id = al.application_id 
        LEFT JOIN programming_languages pl ON al.language_id = pl.id 
        GROUP BY a.id 
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
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1, h2 {
            color: #2c3e50;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #3498db;
            color: white;
        }
        
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        
        tr:hover {
            background-color: #e9e9e9;
        }
        
        .actions {
            white-space: nowrap;
        }
        
        .btn {
            display: inline-block;
            padding: 6px 12px;
            margin: 2px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        
        .btn-edit {
            background-color: #f39c12;
        }
        
        .btn-edit:hover {
            background-color: #e67e22;
        }
        
        .btn-delete {
            background-color: #e74c3c;
        }
        
        .btn-delete:hover {
            background-color: #c0392b;
        }
        
        .stats {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-info {
            background-color: #d9edf7;
            color: #31708f;
            border: 1px solid #bce8f1;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
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
