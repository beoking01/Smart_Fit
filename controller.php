<?php
require_once 'db_config.php';  // Thêm kết nối database
require_once 'auth.php';

// Xử lý đăng nhập, đăng ký, đăng xuất nếu có (giữ nguyên phần này nếu đã có)
// ...

// Xác định tab đang active
$activeTab = $_GET['tab'] ?? 'calculator';

// Nếu không có ?tab thì chuyển hướng về ?tab=home
if (!isset($_GET['tab'])) {
    header('Location: ?tab=home');
    exit();
}

// Kiểm tra trạng thái đăng nhập (giả sử đã có biến $isLoggedIn)
$isLoggedIn = isset($_SESSION['user_id']);

// Biến lưu trạng thái form
$formSubmitted = false;
$modelError = false;
$modelErrorMessage = '';
$caloriesBurnt = null;
$mealSuggestion = [];

// Thêm biến thống kê và lịch sử calories
$totalWorkouts = 0;
$totalCaloriesBurnt = 0;
$avgCaloriesPerWorkout = 0;
$caloriesHistory = [];

// Xử lý cập nhật và lấy thông tin user_profile
$profile = [];
if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];
    // Nếu submit form cập nhật thông tin cá nhân
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        $gender = trim($_POST['gender'] ?? '');
        $age = intval($_POST['age'] ?? 0);
        $height = floatval($_POST['height'] ?? 0);
        $weight = floatval($_POST['weight'] ?? 0);
        $goal = trim($_POST['goal'] ?? '');
        // Kiểm tra đã có profile chưa
        $stmt = $pdo->prepare('SELECT id FROM user_profile WHERE user_id = ?');
        $stmt->execute([$userId]);
        if ($stmt->fetch()) {
            // Update
            $sql = 'UPDATE user_profile SET full_name=?, gender=?, age=?, height=?, weight=?, goal=?, updated_at=NOW() WHERE user_id=?';
            $pdo->prepare($sql)->execute([$full_name, $gender, $age, $height, $weight, $goal, $userId]);
        } else {
            // Insert
            $sql = 'INSERT INTO user_profile (user_id, full_name, gender, age, height, weight, goal) VALUES (?, ?, ?, ?, ?, ?, ?)';
            $pdo->prepare($sql)->execute([$userId, $full_name, $gender, $age, $height, $weight, $goal]);
        }
    }
    // Luôn lấy dữ liệu profile mới nhất
    $stmt = $pdo->prepare('SELECT * FROM user_profile WHERE user_id = ?');
    $stmt->execute([$userId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Lấy thống kê từ database
    $stats = getUserStats($pdo, $_SESSION['user_id']);
    $totalWorkouts = $stats['totalWorkouts'];
    $totalCaloriesBurnt = $stats['totalCaloriesBurnt'];
    $avgCaloriesPerWorkout = $stats['avgCaloriesPerWorkout'];

    // Lấy lịch sử calories cho biểu đồ
    $workouts = getUserWorkouts($pdo, $_SESSION['user_id']);
    foreach ($workouts as $workout) {
        $caloriesHistory[] = [
            'time' => $workout['created_at'],
            'calories' => $workout['calories']
        ];
    }
} else {
    // Nếu chưa đăng nhập, lấy từ session
    if (!empty($_SESSION['history'])) {
        $totalWorkouts = count($_SESSION['history']);
        $totalCaloriesBurnt = array_sum(array_column($_SESSION['history'], 'calories'));
        $avgCaloriesPerWorkout = $totalWorkouts > 0 ? round($totalCaloriesBurnt / $totalWorkouts) : 0;
        foreach ($_SESSION['history'] as $entry) {
            $caloriesHistory[] = [
                'time' => $entry['time'],
                'calories' => $entry['calories']
            ];
        }
    }
}

// Xử lý form tính calories
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_calorie_form'])) {
    $formSubmitted = true;

    // Lấy dữ liệu từ form
    $gender = $_POST['gender'] ?? '';
    $goal = $_POST['goal'] ?? '';
    $age = floatval($_POST['age'] ?? 0);
    $height = floatval($_POST['height'] ?? 0);
    $weight = floatval($_POST['weight'] ?? 0);
    $duration = floatval($_POST['duration'] ?? 0);
    $heartRate = floatval($_POST['heartRate'] ?? 0);
    $bodyTemp = floatval($_POST['bodyTemp'] ?? 0);

    // Gọi API Flask để dự đoán calories
    $apiUrl = 'http://127.0.0.1:5000/predict';
    $postData = [
        'gender' => $gender,
        'age' => $age,
        'height' => $height,
        'weight' => $weight,
        'duration' => $duration,
        'heart_rate' => $heartRate,
        'body_temp' => $bodyTemp
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        $caloriesBurnt = $result['calories'] ?? null;
    } else {
        $modelError = true;
        $modelErrorMessage = 'Không thể kết nối tới AI backend hoặc dữ liệu không hợp lệ.';
    }

    // Nếu dự đoán thành công, gọi hàm recommend_menu (PHP) để lấy thực đơn
    if (!$modelError && $caloriesBurnt !== null) {
        $mealSuggestion = recommend_menu($goal, $caloriesBurnt);

        // Lưu vào session
        $_SESSION['history'][] = [
            'time' => date('Y-m-d H:i:s'),
            'age' => $age,
            'height' => $height,
            'weight' => $weight,
            'duration' => $duration,
            'heartRate' => $heartRate,
            'bodyTemp' => $bodyTemp,
            'calories' => $caloriesBurnt,
            'goal' => $goal,
            'meal' => $mealSuggestion
        ];

        // Lưu vào database nếu đã đăng nhập
        if ($isLoggedIn) {
            try {
                $sql = "INSERT INTO workout_history (user_id, age, height, weight, duration, heart_rate, body_temp, calories, goal, burned_calories, menu) 
                        VALUES (:user_id, :age, :height, :weight, :duration, :heart_rate, :body_temp, :calories, :goal, :burned_calories, :menu)";
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    ':user_id' => $_SESSION['user_id'],
                    ':age' => $age,
                    ':height' => $height,
                    ':weight' => $weight,
                    ':duration' => $duration,
                    ':heart_rate' => $heartRate,
                    ':body_temp' => $bodyTemp,
                    ':calories' => $caloriesBurnt,
                    ':goal' => $goal,
                    ':burned_calories' => $caloriesBurnt,
                    ':menu' => json_encode($mealSuggestion, JSON_UNESCAPED_UNICODE)
                ]);
                
                if (!$result) {
                    error_log("Failed to save workout data to database");
                }
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
            }
        }
    }
}

// Hàm lọc thực đơn từ CSV
function recommend_menu($goal, $burned_calories, $tolerance = 0.1) {
    $ratios = [
        'giảm_mỡ' => 0.5,
        'giảm_cân' => 0.6,
        'giữ_cân' => 0.85,
        'tăng_cơ' => 1.1,
        'tăng_cân' => 1.2
    ];

    if (!isset($ratios[$goal])) return [];

    $target = $burned_calories * $ratios[$goal];
    $min = $target * (1 - $tolerance);
    $max = $target * (1 + $tolerance);

    $results = [];
    $csvPath = __DIR__ . '/ml_service/menu_labeled.csv';
    if (!file_exists($csvPath)) return [];

    if (($handle = fopen($csvPath, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            $row_assoc = array_combine($header, $row);
            if (
                $row_assoc['goal'] === $goal &&
                floatval($row_assoc['total_calories']) >= $min &&
                floatval($row_assoc['total_calories']) <= $max
            ) {
                $results[] = [
                    'name' => $row_assoc['menu'],
                    'protein' => $row_assoc['total_protein'],
                    'carbs' => $row_assoc['total_carb'],
                    'fat' => $row_assoc['total_fat'],
                    'calories' => $row_assoc['total_calories']
                ];
            }
        }
        fclose($handle);
    }
    return array_slice($results, 0, 5);
}
?>