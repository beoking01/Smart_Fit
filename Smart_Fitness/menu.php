<?php
session_start();
// Database connection
$host = 'localhost';
$dbname = 'smartfit_db';
$username = 'root';
$password = '12345678';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Lấy userId từ session nếu đã đăng nhập, fallback về 1 nếu chưa đăng nhập
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Lấy mục tiêu từ bảng user_profile nếu có
$user_goal = isset($user['goal']) ? $user['goal'] : '';
// Truy vấn bảng user_profile nếu tồn tại
$stmt_profile = $pdo->prepare("SELECT goal FROM user_profile WHERE user_id = ? LIMIT 1");
$stmt_profile->execute([$userId]);
$profile_row = $stmt_profile->fetch(PDO::FETCH_ASSOC);
if ($profile_row && isset($profile_row['goal']) && $profile_row['goal'] !== '') {
    $user_goal = $profile_row['goal'];
}

// Retrieve menu items matching user's goal
$stmt = $pdo->prepare("SELECT * FROM menus WHERE goal = ?");
$stmt->execute([$user_goal]);
$menus = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Xử lý tìm kiếm menu
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search !== '') {
    $stmt = $pdo->prepare("SELECT * FROM menus WHERE goal = ? AND menu LIKE ?");
    $stmt->execute([$user_goal, '%' . $search . '%']);
    $menus = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy thông tin cá nhân từ bảng user_profile nếu có
$user_profile = null;
$stmt_profile = $pdo->prepare("SELECT * FROM user_profile WHERE user_id = ? LIMIT 1");
$stmt_profile->execute([$userId]);
$user_profile = $stmt_profile->fetch(PDO::FETCH_ASSOC);

// Nếu có user_profile thì lấy thông tin từ đó, fallback về users nếu không có
$display_name = isset($user_profile['full_name']) && $user_profile['full_name'] !== '' ? $user_profile['full_name'] : (isset($user['name']) ? $user['name'] : '');
$display_height = isset($user_profile['height']) && $user_profile['height'] !== '' ? $user_profile['height'] : (isset($user['height']) ? $user['height'] : '');
$display_weight = isset($user_profile['weight']) && $user_profile['weight'] !== '' ? $user_profile['weight'] : (isset($user['weight']) ? $user['weight'] : '');
$display_goal = isset($user_profile['goal']) && $user_profile['goal'] !== '' ? $user_profile['goal'] : $user_goal;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thực Đơn Dinh Dưỡng</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="?tab=home">
                <i class="bi bi-fire me-2 text-primary" style="font-size: 1.75rem;"></i>
                <span class="fw-bold">SmartFit</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="bi bi-house-door me-1"></i> Trang chủ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?tab=calculator">
                            <i class="bi bi-calculator me-1"></i> Tính toán
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?tab=history">
                            <i class="bi bi-clock-history me-1"></i> Lịch sử
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="menu.php">
                            <i class="bi bi-list-check me-1"></i> Thực đơn
                        </a>
                    </li>
                    <?php if (isset($_SESSION['user_id'])): ?>
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

    <div class="container py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="text-center mb-4">
                    <i class="bi bi-heart-pulse text-primary"></i>
                    Thực Đơn Dinh Dưỡng Cá Nhân
                </h1>
            </div>
        </div>

        <!-- User Profile -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-circle"></i>
                            Thông Tin Cá Nhân
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12 col-md-3 mb-2 mb-md-0">
                                <p><strong>Tên:</strong> <?php echo htmlspecialchars($display_name); ?></p>
                            </div>
                            <div class="col-12 col-md-3 mb-2 mb-md-0">
                                <p><strong>Chiều cao:</strong> <?php echo htmlspecialchars($display_height); ?> cm</p>
                            </div>
                            <div class="col-12 col-md-3 mb-2 mb-md-0">
                                <p><strong>Cân nặng:</strong> <?php echo htmlspecialchars($display_weight); ?> kg</p>
                            </div>
                            <div class="col-12 col-md-3">
                                <p>
                                    <strong>Mục tiêu:</strong> 
                                    <span class="badge bg-primary">
                                        <?php 
                                        $goalText = [
                                            'giảm_cân' => 'Giảm cân',
                                            'giữ_cân' => 'Giữ cân',
                                            'tăng_cân' => 'Tăng cân',
                                            'giảm_mỡ' => 'Giảm mỡ',
                                            'tăng_cơ' => 'Tăng cơ'
                                        ];
                                        echo $goalText[$display_goal] ?? htmlspecialchars($display_goal); 
                                        ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Menu Display -->
        <div class="row mb-4">
            <div class="col-12">
                <h3 class="mb-3">
                    <i class="bi bi-list-check text-primary"></i>
                    Thực Đơn Phù Hợp
                </h3>
            </div>
        </div>

        <!-- Search Form -->
        <div class="row mb-4">
            <div class="col-12">
                <form class="row g-2" method="get" action="">
                    <div class="col-12 col-sm-9">
                        <input class="form-control" type="search" name="search" placeholder="Tìm kiếm món ăn..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-12 col-sm-3">
                        <button class="btn btn-outline-primary w-100 btn-sm d-flex align-items-center justify-content-center" type="submit"><i class="bi bi-search"></i> Tìm kiếm</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <?php if (count($menus) > 0): ?>
                <?php foreach ($menus as $menu): ?>
                    <div class="col-12 col-md-6 col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span class="badge bg-primary">
                                    <?php 
                                    $goalText = [
                                        'giảm_cân' => 'Giảm cân',
                                        'giữ_cân' => 'Giữ cân',
                                        'tăng_cân' => 'Tăng cân',
                                        'giảm_mỡ' => 'Giảm mỡ',
                                        'tăng_cơ' => 'Tăng cơ'
                                    ];
                                    echo $goalText[$menu['goal']] ?? $menu['goal']; 
                                    ?>
                                </span>
                                <strong class="text-primary"><?php echo round($menu['total_calories'], 1); ?> kcal</strong>
                            </div>
                            <div class="card-body">
                                <p class="card-text"><?php echo htmlspecialchars($menu['menu']); ?></p>
                                
                                <div class="mb-3">
                                    <div class="macro-bar mb-2">
                                        <div class="d-flex h-100">
                                            <div class="protein-bar" style="width: <?php echo $menu['protein_ratio'] * 100; ?>%"></div>
                                            <div class="fat-bar" style="width: <?php echo $menu['fat_ratio'] * 100; ?>%"></div>
                                            <div class="carb-bar" style="width: <?php echo $menu['carb_ratio'] * 100; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="row text-center small">
                                        <div class="col-4">
                                            <div class="text-danger">
                                                <i class="bi bi-circle-fill"></i>
                                                Protein
                                            </div>
                                            <strong><?php echo round($menu['total_protein'], 1); ?>g</strong>
                                            <div class="text-muted"><?php echo round($menu['protein_ratio'] * 100, 1); ?>%</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-warning">
                                                <i class="bi bi-circle-fill"></i>
                                                Chất béo
                                            </div>
                                            <strong><?php echo round($menu['total_fat'], 1); ?>g</strong>
                                            <div class="text-muted"><?php echo round($menu['fat_ratio'] * 100, 1); ?>%</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-info">
                                                <i class="bi bi-circle-fill"></i>
                                                Carb
                                            </div>
                                            <strong><?php echo round($menu['total_carb'], 1); ?>g</strong>
                                            <div class="text-muted"><?php echo round($menu['carb_ratio'] * 100, 1); ?>%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle"></i>
                        Không tìm thấy thực đơn phù hợp với mục tiêu của bạn.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <footer class="mt-5 text-center text-muted">
            <p>© <?php echo date('Y'); ?> Thực Đơn Dinh Dưỡng</p>
        </footer>
    </div>
</body>
</html>