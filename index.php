<?php
require_once 'controller.php';

// Định dạng lại thời gian cho biểu đồ chỉ lấy ngày
foreach ($caloriesHistory as &$item) {
    $item['short_time'] = isset($item['time']) ? date('Y-m-d', strtotime($item['time'])) : '';
}
unset($item);

// Ở đầu file index.php, sau session_start()
$prefill = $_SESSION['prefill_calculator'] ?? [];

if (isset($_POST['update_profile'])) {
    $_SESSION['prefill_calculator'] = [
        'gender' => ($_POST['gender'] === 'Nam') ? 'male' : (($_POST['gender'] === 'Nữ') ? 'female' : ''),
        'age' => $_POST['age'] ?? '',
        'height' => $_POST['height'] ?? '',
        'weight' => $_POST['weight'] ?? ''
    ];
}

// Thêm hàm chuyển tên món sang tên file ảnh
function getImageFileName($dishName) {
    // Lấy tên món đầu tiên (trước dấu + nếu có)
    $mainDish = explode('+', $dishName)[0];
    // Bỏ mọi thứ trong ngoặc (kể cả số lượng, đơn vị)
    $mainDish = preg_replace('/\(.*?\)/', '', $mainDish);
    // Chuyển về chữ thường, không dấu
    $mainDish = mb_strtolower($mainDish, 'UTF-8');
    $replacements = [
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y',
        'đ'=>'d',
        'À'=>'a','Á'=>'a','Ạ'=>'a','Ả'=>'a','Ã'=>'a','Â'=>'a','Ầ'=>'a','Ấ'=>'a','Ậ'=>'a','Ẩ'=>'a','Ẫ'=>'a','Ă'=>'a','Ằ'=>'a','Ắ'=>'a','Ặ'=>'a','Ẳ'=>'a','Ẵ'=>'a',
        'È'=>'e','É'=>'e','Ẹ'=>'e','Ẻ'=>'e','Ẽ'=>'e','Ê'=>'e','Ề'=>'e','Ế'=>'e','Ệ'=>'e','Ể'=>'e','Ễ'=>'e',
        'Ì'=>'i','Í'=>'i','Ị'=>'i','Ỉ'=>'i','Ĩ'=>'i',
        'Ò'=>'o','Ó'=>'o','Ọ'=>'o','Ỏ'=>'o','Õ'=>'o','Ô'=>'o','Ồ'=>'o','Ố'=>'o','Ộ'=>'o','Ổ'=>'o','Ỗ'=>'o','Ơ'=>'o','Ờ'=>'o','Ớ'=>'o','Ợ'=>'o','Ở'=>'o','Ỡ'=>'o',
        'Ù'=>'u','Ú'=>'u','Ụ'=>'u','Ủ'=>'u','Ũ'=>'u','Ư'=>'u','Ừ'=>'u','Ứ'=>'u','Ự'=>'u','Ử'=>'u','Ữ'=>'u',
        'Ỳ'=>'y','Ý'=>'y','Ỵ'=>'y','Ỷ'=>'y','Ỹ'=>'y',
        'Đ'=>'d'
    ];
    $mainDish = strtr($mainDish, $replacements);
    // Bỏ mọi ký tự không phải chữ cái, số, dấu cách
    $mainDish = preg_replace('/[^a-z0-9 ]/', '', $mainDish);
    // Chuyển nhiều dấu cách liên tiếp thành một dấu gạch dưới
    $mainDish = preg_replace('/\s+/', '_', trim($mainDish));
    return $mainDish . '.png';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SmartFit Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <!-- CSS cho fullwidth-bg không bị lệch khi có scrollbar -->
    <style>
    .fullwidth-bg {
        width: 100vw;
        position: relative;
        left: 50%;
        right: 50%;
        margin-left: calc(-50vw + 50%);
        margin-right: calc(-50vw + 50%);
        background: #fff;
        margin-bottom: 2.5rem;
        box-sizing: border-box;
    }
    </style>
</head>
<body>
    <!-- Toast Notification -->
    <?php if ($formSubmitted): ?>
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <?php if ($modelError): ?>
                <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                <strong class="me-auto">Lỗi</strong>
                <?php else: ?>
                <i class="bi bi-check-circle-fill text-success me-2"></i>
                <strong class="me-auto">Thành công</strong>
                <?php endif; ?>
                <small>Vừa xong</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">
                <?php if ($modelError): ?>
                <?= $modelErrorMessage ?>
                <?php else: ?>
                Đã tính toán thành công <?= $caloriesBurnt ?> calories và lưu vào lịch sử.
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="?tab=home">
                <i class="bi bi-fire me-2" style="font-size: 1.75rem;"></i>
                <span>SmartFit</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'home' ? 'active' : '' ?>" href="?tab=home">
                            <i class="bi bi-house-door me-1"></i> Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'calculator' ? 'active' : '' ?>" href="?tab=calculator">
                            <i class="bi bi-calculator me-1"></i> Tính toán
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'history' ? 'active' : '' ?>" href="?tab=history">
                            <i class="bi bi-clock-history me-1"></i> Lịch sử
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="menu.php">
                            <i class="bi bi-list-check me-1"></i> Thực đơn
                        </a>
                    </li>
                    <?php if ($isLoggedIn): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Đăng nhập
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="bi bi-person-plus me-1"></i> Đăng ký
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container py-4 flex-grow-1">
        <!-- Navigation Pills (Mobile Friendly) -->
        <div class="row justify-content-center mb-4 d-md-none">
            <div class="col-12">
                <ul class="nav nav-pills nav-justified">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'home' ? 'active' : '' ?>" href="?tab=home">
                            <i class="bi bi-house-door me-md-2"></i>
                            <span>Trang chủ</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'calculator' ? 'active' : '' ?>" href="?tab=calculator">
                            <i class="bi bi-calculator me-md-2"></i>
                            <span>Tính toán</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'history' ? 'active' : '' ?>" href="?tab=history">
                            <i class="bi bi-clock-history me-md-2"></i>
                            <span>Lịch sử</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="tab-content">
            <div class="tab-pane fade <?= $activeTab === 'home' ? 'show active' : '' ?>">
                <!-- Hero Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card" data-aos="fade-up">
                            <div class="card-body p-4 p-md-5 text-center">
                                <h1 class="display-5 fw-bold mb-4">Chào mừng đến với SmartFit</h1>
                                <p class="lead mb-4">Theo dõi hoạt động thể thao và nhận gợi ý dinh dưỡng phù hợp để đạt được mục tiêu sức khỏe của bạn.</p>
                                <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                                    <a href="?tab=calculator" class="btn btn-primary btn-lg px-4 gap-3">
                                        <i class="bi bi-calculator me-2"></i> Bắt đầu tính toán
                                    </a>
                                    <a href="?tab=history" class="btn btn-outline-primary btn-lg px-4">
                                        <i class="bi bi-clock-history me-2"></i> Xem lịch sử
                                    </a>
                                </div>
                                <?php if (!$isLoggedIn): ?>
                                <div class="alert alert-info mt-4" role="alert">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Mẹo:</strong> <a href="register.php" class="alert-link">Đăng ký</a> hoặc <a href="login.php" class="alert-link">đăng nhập</a> để lưu lịch sử tập luyện của bạn.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bố cục 2 cột: Thống kê + Biểu đồ bên trái, Form thông tin cá nhân bên phải -->
                <div class="row mb-4">
                    <!-- Cột trái: Thống kê + Biểu đồ -->
                    <div class="col-lg-8 col-md-7">
                        <div class="mb-4">
                            <h4 class="mb-3">Thống kê của bạn</h4>
                            <div class="row">
                                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                                    <div class="stat-card bg-white text-primary">
                                        <div class="stat-icon">
                                            <i class="bi bi-activity"></i>
                                        </div>
                                        <div class="stat-value"><?= $totalWorkouts > 0 ? $totalWorkouts : 0 ?></div>
                                        <div class="stat-label">Tổng số buổi tập</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                                    <div class="stat-card bg-white text-success">
                                        <div class="stat-icon">
                                            <i class="bi bi-fire"></i>
                                        </div>
                                        <div class="stat-value"><?= $totalCaloriesBurnt > 0 ? $totalCaloriesBurnt : 0 ?></div>
                                        <div class="stat-label">Tổng calories đã đốt</div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
                                    <div class="stat-card bg-white text-info">
                                        <div class="stat-icon">
                                            <i class="bi bi-lightning-charge"></i>
                                        </div>
                                        <div class="stat-value"><?= $avgCaloriesPerWorkout > 0 ? $avgCaloriesPerWorkout : 0 ?></div>
                                        <div class="stat-label">Calories trung bình/buổi tập</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="card" data-aos="fade-up" data-aos-delay="400">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Lịch sử calories</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="caloriesChart" height="125"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="container">             
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var caloriesHistory = <?= json_encode($caloriesHistory) ?>;
                            var chartData = caloriesHistory.length > 0 ? caloriesHistory.map(e => e.calories) : [0];
                            var chartLabels = caloriesHistory.length > 0 ? caloriesHistory.map(e => e.short_time) : [''];
                            var ctx = document.getElementById('caloriesChart').getContext('2d');
                            var chart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: chartLabels,
                                    datasets: [{
                                        label: 'Calories đã đốt',
                                        data: chartData,
                                        borderColor: '#0d6efd',
                                        backgroundColor: 'rgba(13,110,253,0.1)',
                                        fill: true,
                                        tension: 0,
                                        pointRadius: 4,
                                        pointBackgroundColor: '#0d6efd',
                                        pointBorderColor: '#fff',
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: { display: true },
                                    },
                                    scales: {
                                        x: {
                                            title: { display: true, text: 'Thời gian' },
                                            ticks: { autoSkip: true, maxTicksLimit: 10 },
                                            grid: { display: false }
                                        },
                                        y: {
                                            title: { display: true, text: 'Calories' },
                                            beginAtZero: true,
                                            grid: { display: false }
                                        }
                                    }
                                }
                            });
                        });
                        </script>
                    </div>
                    </div>
                    <!-- Cột phải: Form thông tin cá nhân -->
                    <div class="col-lg-4 col-md-5">
                        <div class="card" data-aos="fade-up" data-aos-delay="200">
                            <div class="card-header d-flex align-items-center">
                                <i class="bi bi-person-circle text-primary me-2"></i>
                                <h5 class="mb-0">Thông tin cá nhân</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($isLoggedIn): ?>
                                <div id="profile-view-mode">
                                    <div class="d-flex flex-column align-items-center mb-3">
                                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mb-2" style="width: 80px; height: 80px; font-size: 2.5rem; color: #0d6efd;">
                                            <i class="bi bi-person-circle"></i>
                                        </div>
                                        <h5 class="fw-bold mb-1" style="color: #0d6efd;"></h5>
                                    </div>
                                    <div class="p-3 rounded-4 mb-4" style="background: #f8f9fa; min-width: 260px; max-width: 350px; margin: 0 auto; box-shadow: 0 2px 8px rgba(0,0,0,0.03);">
                                        <div class="mb-2 d-flex">
                                            <span class="fw-semibold text-secondary me-2" style="min-width: 90px;">Họ tên:</span><span class="text-dark" style="text-align:left;"><?= htmlspecialchars($profile['full_name'] ?? '') ?></span>
                                        </div>
                                        <div class="mb-2 d-flex">
                                            <span class="fw-semibold text-secondary me-2" style="min-width: 90px;">Giới tính:</span><span class="text-dark" style="text-align:left;"><?= htmlspecialchars($profile['gender'] ?? '') ?></span>
                                        </div>
                                        <div class="mb-2 d-flex">
                                            <span class="fw-semibold text-secondary me-2" style="min-width: 90px;">Tuổi:</span><span class="text-dark" style="text-align:left;"><?= htmlspecialchars($profile['age'] ?? '') ?></span>
                                        </div>
                                        <div class="mb-2 d-flex">
                                            <span class="fw-semibold text-secondary me-2" style="min-width: 90px;">Chiều cao:</span><span class="text-dark" style="text-align:left;"><?= htmlspecialchars($profile['height'] ?? '') ?> cm</span>
                                        </div>
                                        <div class="mb-2 d-flex">
                                            <span class="fw-semibold text-secondary me-2" style="min-width: 90px;">Cân nặng:</span><span class="text-dark" style="text-align:left;"><?= htmlspecialchars($profile['weight'] ?? '') ?> kg</span>
                                        </div>
                                        <div class="mb-2 d-flex">
                                            <span class="fw-semibold text-secondary me-2" style="min-width: 90px;">Mục tiêu:</span><span class="text-dark" style="text-align:left;"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $profile['goal'] ?? ''))) ?></span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary w-100" id="editProfileBtn"><i class="bi bi-pencil me-2"></i>Chỉnh sửa thông tin</button>
                                </div>
                                <form method="POST" action="" autocomplete="off" id="profile-edit-mode" style="display:none;">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Họ tên</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?= htmlspecialchars($profile['full_name'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="gender" class="form-label">Giới tính</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">Chọn giới tính</option>
                                            <option value="Nam" <?= (isset($profile['gender']) && $profile['gender'] === 'Nam') ? 'selected' : '' ?>>Nam</option>
                                            <option value="Nữ" <?= (isset($profile['gender']) && $profile['gender'] === 'Nữ') ? 'selected' : '' ?>>Nữ</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="age" class="form-label">Tuổi</label>
                                        <input type="number" class="form-control" id="age" name="age" value="<?= htmlspecialchars($_POST['age'] ?? $prefill['age'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="height" class="form-label">Chiều cao (cm)</label>
                                        <input type="number" step="0.1" class="form-control" id="height" name="height" value="<?= htmlspecialchars($profile['height'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="weight" class="form-label">Cân nặng (kg)</label>
                                        <input type="number" step="0.1" class="form-control" id="weight" name="weight" value="<?= htmlspecialchars($profile['weight'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="goal" class="form-label">Mục tiêu cá nhân</label>
                                        <select class="form-select" id="goal" name="goal">
                                            <option value="">Chọn mục tiêu</option>
                                            <option value="giảm_mỡ" <?= (isset($profile['goal']) && $profile['goal'] === 'giảm_mỡ') ? 'selected' : '' ?>>Giảm mỡ</option>
                                            <option value="giảm_cân" <?= (isset($profile['goal']) && $profile['goal'] === 'giảm_cân') ? 'selected' : '' ?>>Giảm cân</option>
                                            <option value="giữ_cân" <?= (isset($profile['goal']) && $profile['goal'] === 'giữ_cân') ? 'selected' : '' ?>>Giữ cân</option>
                                            <option value="tăng_cơ" <?= (isset($profile['goal']) && $profile['goal'] === 'tăng_cơ') ? 'selected' : '' ?>>Tăng cơ</option>
                                            <option value="tăng_cân" <?= (isset($profile['goal']) && $profile['goal'] === 'tăng_cân') ? 'selected' : '' ?>>Tăng cân</option>
                                        </select>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-save me-2"></i>Lưu</button>
                                        <button type="button" class="btn btn-secondary flex-grow-1" id="cancelEditProfileBtn">Hủy</button>
                                    </div>
                                </form>
                                <script>
                                document.getElementById('editProfileBtn').onclick = function() {
                                    document.getElementById('profile-view-mode').style.display = 'none';
                                    document.getElementById('profile-edit-mode').style.display = '';
                                };
                                document.getElementById('cancelEditProfileBtn').onclick = function() {
                                    document.getElementById('profile-edit-mode').style.display = 'none';
                                    document.getElementById('profile-view-mode').style.display = '';
                                };
                                </script>
                                 <?php else: ?>
                                <div class="alert alert-info">Vui lòng đăng nhập để chỉnh sửa thông tin cá nhân.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Có thể bạn chưa biết: Dưỡng chất khuyến nghị dạng thẻ, nền full width, nội dung thẳng hàng container tuyệt đối, không lệch -->
                <div class="container mb-4">
                    <div class="row mb-4">
                        <div class="col-12 col-lg-10 mx-auto">
                            <div class="card border-0 shadow-sm mb-4" style="border-radius: 32px;">
                                <div class="card-header bg-white border-bottom-0 text-start" style="border-radius: 32px 32px 0 0; background: #fff;">
                                    <span class="fw-semibold" style="font-size:1.15rem;"><i class="bi bi-info-circle text-primary me-2"></i>Có thể bạn chưa biết</span>
                                </div>
                                <div class="card-body px-2 px-md-5 py-4">
                                    <div class="row g-4 ms-0">
                                        <div class="col-12 col-md-4">
                                            <div class="card h-100 border-0 shadow-sm text-center" style="border-radius: 22px;">
                                                <div class="card-body">
                                                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:68px;height:68px;border-radius:50%;background:rgba(13,110,253,0.08);">
                                                        <i class="bi bi-egg-fried text-primary" style="font-size:2.3rem;"></i>
                                                    </div>
                                                    <h6 class="fw-bold mb-2" style="font-size:1.13rem;">Protein</h6>
                                                    <div class="mb-2"><span class="badge bg-primary bg-gradient px-3 py-2" style="font-size:1.1rem;">≥ 20% tổng calo</span></div>
                                                    <div class="text-muted small" style="font-size:1.01rem;">Giúp sửa chữa cơ bị tổn thương, thúc đẩy phát triển cơ bắp sau tập.</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="card h-100 border-0 shadow-sm text-center" style="border-radius: 22px;">
                                                <div class="card-body">
                                                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:68px;height:68px;border-radius:50%;background:rgba(255,193,7,0.10);">
                                                        <i class="bi bi-droplet-half text-warning" style="font-size:2.3rem;"></i>
                                                    </div>
                                                    <h6 class="fw-bold mb-2" style="font-size:1.13rem;">Chất béo</h6>
                                                    <div class="mb-2"><span class="badge bg-warning bg-gradient text-dark px-3 py-2" style="font-size:1.1rem;">≤ 35% tổng calo</span></div>
                                                    <div class="text-muted small" style="font-size:1.01rem;">Hạn chế chất béo khó tiêu hóa ngay sau tập, tránh tích mỡ thừa.</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <div class="card h-100 border-0 shadow-sm text-center" style="border-radius: 22px;">
                                                <div class="card-body">
                                                    <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:68px;height:68px;border-radius:50%;background:rgba(102,16,242,0.08);">
                                                        <i class="bi bi-cup-straw text-info" style="font-size:2.3rem;"></i>
                                                    </div>
                                                    <h6 class="fw-bold mb-2" style="font-size:1.13rem;">Carbohydrate</h6>
                                                    <div class="mb-2"><span class="badge bg-info bg-gradient text-dark px-3 py-2" style="font-size:1.1rem;">Linh hoạt</span></div>
                                                    <div class="text-muted small" style="font-size:1.01rem;">Bổ sung tùy cường độ tập: Cardio cần nhiều carb hơn tập tạ.</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Về SmartFit Section đẹp, hiện đại, thẳng hàng container -->
                <div class="container mb-4">
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card border-0 shadow-sm" style="border-radius: 20px; background: linear-gradient(90deg, #f8f9fa 60%, #e9f0ff 100%);">
                                <div class="card-body px-5 py-5">
                                    <div class="text-center mb-2">
                                        <i class="bi bi-fire" style="font-size: 2rem; background: linear-gradient(135deg, #0d6efd, #6610f2); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>
                                    </div>
                                    <h4 class="fw-bold mb-3 text-center" style="font-size: 1.5rem; letter-spacing: -1px; color: #222;">Về SmartFit</h4>
                                    <p class="mb-0 text-muted" style="font-size: 1.18rem; text-align: justify; line-height: 1.7;">
                                        <span class="fw-semibold text-primary">SmartFit</span> sử dụng công nghệ AI tiên tiến để giúp bạn theo 
                                        dõi hoạt động thể thao và nhận gợi ý dinh dưỡng phù hợp. 
                                        Hệ thống của chúng tôi phân tích dữ liệu từ các thông số cá nhân 
                                        và hoạt động của bạn để đưa ra kết quả chính xác nhất, 
                                        đồng hành cùng bạn trên hành trình nâng cao sức khỏe.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calculator Tab -->
            <div class="tab-pane fade <?= $activeTab === 'calculator' ? 'show active' : '' ?>">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card" data-aos="fade-up">
                            <div class="card-header d-flex align-items-center">
                                <i class="bi bi-calculator text-primary me-2"></i>
                                <h5 class="mb-0">Công cụ tính Calories & Gợi ý bữa ăn</h5>
                            </div>
                            <div class="card-body p-4">
                                <form method="POST" class="mb-4">
                                    <input type="hidden" name="submit_calorie_form" value="1">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="gender" name="gender" required>
                                                    <option value="" disabled <?= !isset($_POST['gender']) ? 'selected' : '' ?>>Chọn giới tính</option>
                                                    <option value="male" <?= (($_POST['gender'] ?? $prefill['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Nam</option>
                                                    <option value="female" <?= (($_POST['gender'] ?? $prefill['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Nữ</option>
                                                </select>
                                                <label for="gender">Giới tính</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="goal" name="goal" required>
                                                    <option value="" disabled <?= !isset($_POST['goal']) ? 'selected' : '' ?>>Chọn mục tiêu</option>
                                                    <option value="giảm_mỡ" <?= (($_POST['goal'] ?? $prefill['goal'] ?? '') === 'giảm_mỡ') ? 'selected' : '' ?>>Giảm mỡ</option>
                                                    <option value="giảm_cân" <?= (($_POST['goal'] ?? $prefill['goal'] ?? '') === 'giảm_cân') ? 'selected' : '' ?>>Giảm cân</option>
                                                    <option value="giữ_cân" <?= (($_POST['goal'] ?? $prefill['goal'] ?? '') === 'giữ_cân') ? 'selected' : '' ?>>Giữ cân</option>
                                                    <option value="tăng_cơ" <?= (($_POST['goal'] ?? $prefill['goal'] ?? '') === 'tăng_cơ') ? 'selected' : '' ?>>Tăng cơ</option>
                                                    <option value="tăng_cân" <?= (($_POST['goal'] ?? $prefill['goal'] ?? '') === 'tăng_cân') ? 'selected' : '' ?>>Tăng cân</option>
                                                </select>
                                                <label for="goal">Mục tiêu cá nhân</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" class="form-control" id="age" name="age" placeholder="Tuổi" required value="<?= htmlspecialchars($_POST['age'] ?? $prefill['age'] ?? '') ?>">
                                                <label for="age">Tuổi</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.1" class="form-control" id="height" name="height" placeholder="Chiều cao (cm)" required value="<?= htmlspecialchars($_POST['height'] ?? $prefill['height'] ?? '') ?>">
                                                <label for="height">Chiều cao (cm)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.1" class="form-control" id="weight" name="weight" placeholder="Cân nặng (kg)" required value="<?= htmlspecialchars($_POST['weight'] ?? $prefill['weight'] ?? '') ?>">
                                                <label for="weight">Cân nặng (kg)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.1" class="form-control" id="duration" name="duration" placeholder="Thời gian tập (phút)" required value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>">
                                                <label for="duration">Thời gian tập (phút)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.1" class="form-control" id="heartRate" name="heartRate" placeholder="Nhịp tim (nhịp/phút)" required max="180" value="<?= htmlspecialchars($_POST['heartRate'] ?? '') ?>">
                                                <label for="heartRate">Nhịp tim (nhịp/phút)</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating mb-3">
                                                <input type="number" step="0.1" class="form-control" id="bodyTemp" name="bodyTemp" placeholder="Nhiệt độ cơ thể (°C)" required max="43" value="<?= htmlspecialchars($_POST['bodyTemp'] ?? '') ?>">
                                                <label for="bodyTemp">Nhiệt độ cơ thể (°C)</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-grid mt-4">
                                        <button type="submit" class="btn btn-primary py-3">
                                            <i class="bi bi-robot me-2"></i> Tính toán với AI
                                        </button>
                                    </div>
                                </form>

                                <?php if ($formSubmitted && !$modelError && $caloriesBurnt !== null): ?>
                                <div class="result-box fade-in" data-aos="fade-up">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h4 class="mb-3">Kết quả</h4>
                                            <div class="d-flex align-items-center mb-4">
                                                <i class="bi bi-fire text-primary me-3" style="font-size: 2.5rem;"></i>
                                                <div>
                                                    <div class="calories-display"><?= $caloriesBurnt ?></div>
                                                    <div class="text-muted">calories đã đốt cháy</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h4 class="mb-3">
                                                <i class="bi bi-apple text-primary me-2"></i>
                                                Gợi ý bữa ăn
                                            </h4>
                                            <?php if (!empty($mealSuggestion)): ?>
                                            <div class="menu-list">
                                                <?php foreach($mealSuggestion as $meal): ?>
                                                <div class="menu-card mb-3">
                                                    <div class="d-flex justify-content-center align-items-center mb-2" style="gap: 8px;">
                                                        <?php 
                                                            $dishes = array_map('trim', explode('+', $meal['name']));
                                                            $dishImgs = [];
                                                            foreach ($dishes as $dish) {
                                                                $imgPath = 'images/' . getImageFileName($dish);
                                                                if (!file_exists($imgPath)) $imgPath = 'images/default.png';
                                                                $dishImgs[] = $imgPath;
                                                            }
                                                        ?>
                                                        <?php foreach($dishImgs as $img): ?>
                                                            <img src="<?= $img ?>" alt="Ảnh món" class="img-fluid rounded" style="object-fit:cover; width:100px; height:100px; display:inline-block;">
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div class="card-body py-2 px-3 text-center">
                                                        <h6 class="mb-1">&#8226; <?= htmlspecialchars($meal['name']) ?></h6>
                                                        <div class="small text-muted mb-1"><?= $meal['calories'] ?> kcal</div>
                                                    </div>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php else: ?>
                                                <div class="alert alert-warning">Không tìm thấy thực đơn phù hợp.</div>
                                            <?php endif; ?>
                                            <div class="alert alert-info mt-3" role="alert">
                                                <i class="bi bi-info-circle me-2"></i>
                                                Gợi ý bữa ăn được tính toán dựa trên lượng calories đã đốt cháy và mục tiêu cá nhân.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php elseif ($formSubmitted && $modelError): ?>
                                <div class="alert alert-danger mt-4" role="alert">
                                    <h5 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i> Lỗi kết nối AI</h5>
                                    <p><?= $modelErrorMessage ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Box mẹo tập luyện đặt ngay dưới form, cùng col-lg-8 -->
                        <div class="card shadow-sm mt-4" style="border-radius: 16px;">
                            <div class="card-header bg-white border-bottom-0" style="border-radius: 16px 16px 0 0;">
                                <span class="fw-semibold"><i class="bi bi-lightbulb text-warning me-2"></i>Mẹo tập luyện</span>
                            </div>
                            <div class="card-body p-4">
                                <div class="row text-center g-4">
                                    <div class="col-12 col-md-4 d-flex flex-column align-items-center justify-content-center">
                                        <div class="d-flex align-items-center justify-content-center mb-2" style="gap: 0.5rem;">
                                            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:1rem;line-height:22px;padding:0;">1</span>
                                            <span class="fw-semibold" style="font-size: 1.1rem;">Khởi động đúng cách</span>
                                        </div>
                                        <div class="text-muted small">Dành 5-10 phút khởi động trước khi tập luyện cường độ cao.</div>
                                    </div>
                                    <div class="col-12 col-md-4 d-flex flex-column align-items-center justify-content-center">
                                        <div class="d-flex align-items-center justify-content-center mb-2" style="gap: 0.5rem;">
                                            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:1rem;line-height:22px;padding:0;">2</span>
                                            <span class="fw-semibold" style="font-size: 1.1rem;">Uống đủ nước</span>
                                        </div>
                                        <div class="text-muted small">Bổ sung nước trước, trong và sau khi tập luyện.</div>
                                    </div>
                                    <div class="col-12 col-md-4 d-flex flex-column align-items-center justify-content-center">
                                        <div class="d-flex align-items-center justify-content-center mb-2" style="gap: 0.5rem;">
                                            <span class="badge rounded-circle bg-primary d-flex align-items-center justify-content-center" style="width:24px;height:24px;font-size:1rem;line-height:22px;padding:0;">3</span>
                                            <span class="fw-semibold" style="font-size: 1.1rem;">Nghỉ ngơi hợp lý</span>
                                        </div>
                                        <div class="text-muted small">Cho cơ thể thời gian phục hồi giữa các buổi tập.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Kết thúc box mẹo -->
                    </div>
                </div>
            </div>

            <!-- History Tab -->
            <div class="tab-pane fade <?= $activeTab === 'history' ? 'show active' : '' ?>">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="card" data-aos="fade-up">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-clock-history text-primary me-2"></i>
                                    <h5 class="mb-0">Lịch sử hoạt động</h5>
                                </div>
                                <?php if (count($_SESSION['history'] ?? []) > 0): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="exportBtn">
                                    <i class="bi bi-download me-1"></i> Xuất dữ liệu
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (!$isLoggedIn): ?>
                                <div class="alert alert-warning mb-4" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Lưu ý:</strong> Bạn chưa đăng nhập. Lịch sử của bạn sẽ bị mất khi đóng trình duyệt. 
                                    <a href="login.php" class="alert-link">Đăng nhập</a> hoặc 
                                    <a href="register.php" class="alert-link">đăng ký</a> để lưu lịch sử vĩnh viễn.
                                </div>
                                <?php endif; ?>
                                
                                <?php if (count($_SESSION['history'] ?? []) === 0): ?>
                                    <div class="empty-state">
                                        <div class="empty-state-icon">
                                            <i class="bi bi-inbox"></i>
                                        </div>
                                        <h4>Chưa có dữ liệu lịch sử</h4>
                                        <p class="text-muted mb-4">Bạn chưa có hoạt động nào được ghi lại. Hãy bắt đầu tính toán để theo dõi tiến độ của bạn.</p>
                                        <a href="?tab=calculator" class="btn btn-primary">
                                            <i class="bi bi-calculator me-2"></i> Bắt đầu tính toán
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Thời gian</th>
                                                    <th>Tuổi</th>
                                                    <th>Chiều cao (cm)</th>
                                                    <th>Cân nặng (kg)</th>
                                                    <th>Thời gian tập (phút)</th>
                                                    <th>Nhịp tim</th>
                                                    <th>Nhiệt độ (°C)</th>
                                                    <th>Calories</th>
                                                    <th>Mục tiêu</th>
                                                    <th>Chi tiết</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_reverse($_SESSION['history']) as $index => $entry): ?>
                                                <tr>
                                                    <td><?= isset($entry['time']) ? htmlspecialchars($entry['time']) : 'N/A' ?></td>
                                                    <td><?= isset($entry['age']) ? htmlspecialchars($entry['age']) : 'N/A' ?></td>
                                                    <td><?= isset($entry['height']) ? htmlspecialchars($entry['height']) : 'N/A' ?></td>
                                                    <td><?= isset($entry['weight']) ? htmlspecialchars($entry['weight']) : 'N/A' ?></td>
                                                    <td><?= isset($entry['duration']) ? htmlspecialchars($entry['duration']) : 'N/A' ?></td>
                                                    <td><?= isset($entry['heartRate']) ? htmlspecialchars($entry['heartRate']) : 'N/A' ?></td>
                                                    <td><?= isset($entry['bodyTemp']) ? htmlspecialchars($entry['bodyTemp']) : 'N/A' ?></td>
                                                    <td class="fw-bold text-primary"><?= isset($entry['calories']) ? htmlspecialchars($entry['calories']) : 'N/A' ?></td>
                                                    <td><?= isset($entry['goal']) ? htmlspecialchars($entry['goal']) : 'N/A' ?></td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?= $index ?>" data-bs-backdrop="false">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Detail Modals -->
                                    <?php foreach (array_reverse($_SESSION['history']) as $index => $entry): ?>
                                    <div class="modal fade" id="detailModal<?= $index ?>" tabindex="-1" aria-labelledby="detailModalLabel<?= $index ?>" aria-hidden="true" data-bs-backdrop="false">
                                        <div class="modal-dialog modal-dialog-centered draggable-modal" draggable="true">
                                            <div class="modal-content">
                                                <div class="modal-header draggable-modal-header" style="cursor: move;">
                                                    <h5 class="modal-title" id="detailModalLabel<?= $index ?>">Chi tiết hoạt động</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="d-flex align-items-center mb-4">
                                                        <i class="bi bi-fire text-primary me-3" style="font-size: 2.5rem;"></i>
                                                        <div>
                                                            <div class="calories-display"><?= $entry['calories'] ?></div>
                                                            <div class="text-muted">calories đã đốt cháy</div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-4">
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <div class="text-muted small">Thời gian</div>
                                                                <div><?= htmlspecialchars($entry['time']) ?></div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="text-muted small">Tuổi</div>
                                                                <div><?= htmlspecialchars($entry['age']) ?> tuổi</div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="text-muted small">Chiều cao</div>
                                                                <div><?= htmlspecialchars($entry['height']) ?> cm</div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="text-muted small">Cân nặng</div>
                                                                <div><?= htmlspecialchars($entry['weight']) ?> kg</div>
                                                            </div>
                                                        </div>
                                                        <div class="col-6">
                                                            <div class="mb-3">
                                                                <div class="text-muted small">Thời gian tập</div>
                                                                <div><?= htmlspecialchars($entry['duration']) ?> phút</div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="text-muted small">Nhịp tim</div>
                                                                <div><?= htmlspecialchars($entry['heartRate']) ?> nhịp/phút</div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="text-muted small">Nhiệt độ cơ thể</div>
                                                                <div><?= htmlspecialchars($entry['bodyTemp']) ?> °C</div>
                                                            </div>
                                                            <div class="mb-3">
                                                                <div class="text-muted small">Mục tiêu</div>
                                                                <div><?= htmlspecialchars($entry['goal']) ?></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <h5 class="mb-3">Gợi ý bữa ăn</h5>
                                                    <?php if (!empty($entry['meal'])): ?>
                                                    <div class="menu-list">
                                                        <?php foreach($entry['meal'] as $meal): ?>
                                                        <div class="menu-card mb-3">
                                                            <div class="d-flex justify-content-center align-items-center mb-2" style="gap: 8px;">
                                                                <?php 
                                                                    $dishes = array_map('trim', explode('+', $meal['name']));
                                                                    $dishImgs = [];
                                                                    foreach ($dishes as $dish) {
                                                                        $imgPath = 'images/' . getImageFileName($dish);
                                                                        if (!file_exists($imgPath)) $imgPath = 'images/default.png';
                                                                        $dishImgs[] = $imgPath;
                                                                    }
                                                                ?>
                                                                <?php foreach($dishImgs as $img): ?>
                                                                    <img src="<?= $img ?>" alt="Ảnh món" class="img-fluid rounded" style="object-fit:cover; width:100px; height:100px; display:inline-block;">
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="card-body py-2 px-3 text-center">
                                                                <h6 class="mb-1">&#8226; <?= htmlspecialchars($meal['name']) ?></h6>
                                                                <div class="small text-muted mb-1"><?= $meal['calories'] ?> kcal</div>
                                                            </div>
                                                        </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <?php else: ?>
                                                    <div class="alert alert-warning">Không có thực đơn gợi ý.</div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white py-4 mt-auto border-top">
        <div class="container">
            <div class="row align-items-center justify-content-between">
                <div class="col-md-6 d-flex align-items-center mb-3 mb-md-0">
                    <i class="bi bi-fire text-primary me-2" style="font-size: 1.5rem;"></i>
                    <div>
                        <span class="fw-bold" style="font-size: 1.1rem;">SmartFit</span><br>
                        <span class="text-muted small">Theo dõi hoạt động thể thao và nhận gợi ý dinh dưỡng phù hợp.</span>
                    </div>
                </div>
                <div class="col-md-6 text-md-end text-center">
                    <span class="text-muted small">&copy; 2025 SmartFit . Tất cả quyền được bảo lưu.</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>
    <script>
    // Kéo thả modal chi tiết lịch sử
    (function() {
        let draggedModal = null;
        let offsetX = 0, offsetY = 0;
        let startX = 0, startY = 0;
        
        document.addEventListener('mousedown', function(e) {
            if (e.target.classList.contains('draggable-modal-header')) {
                draggedModal = e.target.closest('.draggable-modal');
                const rect = draggedModal.getBoundingClientRect();
                startX = e.clientX;
                startY = e.clientY;
                offsetX = startX - rect.left;
                offsetY = startY - rect.top;
                draggedModal.style.position = 'fixed';
                draggedModal.style.zIndex = 1056; // Trên modal backdrop
                draggedModal.style.margin = '0';
                document.body.style.userSelect = 'none';
            }
        });
        document.addEventListener('mousemove', function(e) {
            if (draggedModal) {
                let x = e.clientX - offsetX;
                let y = e.clientY - offsetY;
                // Giới hạn không cho modal ra ngoài màn hình
                x = Math.max(0, Math.min(window.innerWidth - draggedModal.offsetWidth, x));
                y = Math.max(0, Math.min(window.innerHeight - draggedModal.offsetHeight, y));
                draggedModal.style.left = x + 'px';
                draggedModal.style.top = y + 'px';
            }
        });
        document.addEventListener('mouseup', function(e) {
            if (draggedModal) {
                draggedModal = null;
                document.body.style.userSelect = '';
            }
        });
        // Reset vị trí modal khi đóng
        document.querySelectorAll('.modal').forEach(function(modal) {
            modal.addEventListener('hidden.bs.modal', function() {
                const dialog = modal.querySelector('.draggable-modal');
                if (dialog) {
                    dialog.style.left = '';
                    dialog.style.top = '';
                    dialog.style.position = '';
                    dialog.style.margin = '';
                }
            });
        });
    })();
    </script>
</body>
</html>