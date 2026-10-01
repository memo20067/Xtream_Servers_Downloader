<?php
// profile.php - User Profile Customization & Avatar Upload

require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if (empty($email) || empty($phone)) {
            $error = 'Email and Mobile Phone Number are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address format.';
        } else {
            $db = getDBConnection();
            $stmt = $db->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$email, $phone, $_SESSION['user_id']]);
            $success = 'Profile details updated successfully!';
            $currentUser = getCurrentUser();
        }
    } elseif ($action === 'upload_avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['avatar']['tmp_name'];
            $fileName = $_FILES['avatar']['name'];
            $fileSize = $_FILES['avatar']['size'];
            $fileType = $_FILES['avatar']['type'];
            $fileNameCmps = explode(".", $fileName);
            $fileExtension = strtolower(end($fileNameCmps));

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadFileDir = __DIR__ . '/uploads/avatars/';
                if (!is_dir($uploadFileDir)) {
                    mkdir($uploadFileDir, 0777, true);
                }

                $newFileName = md5(time() . $_SESSION['user_id']) . '.' . $fileExtension;
                $dest_path = $uploadFileDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $avatarRelativePath = 'uploads/avatars/' . $newFileName;
                    $db = getDBConnection();
                    $stmt = $db->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$avatarRelativePath, $_SESSION['user_id']]);
                    $success = 'Profile avatar updated successfully!';
                    $currentUser = getCurrentUser();
                } else {
                    $error = 'There was an error moving the uploaded file to the server directory.';
                }
            } else {
                $error = 'Upload failed. Allowed image formats: ' . implode(', ', $allowedExtensions);
            }
        } else {
            $error = 'Please select a valid image file to upload.';
        }
    }
}

$avatarSrc = !empty($currentUser['avatar']) ? htmlspecialchars($currentUser['avatar']) : 'https://via.placeholder.com/150?text=Avatar';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Xtream IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3 shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-arrow-left-circle me-2"></i>Back to Dashboard
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="navbar-text text-white fw-bold">
                <?= htmlspecialchars($currentUser['username']) ?>
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<div class="container py-5" style="max-width: 800px;">
    <h3 class="mb-4"><i class="bi bi-person-bounding-box me-2 text-primary"></i>User Profile Settings</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Avatar Picture Card -->
        <div class="col-md-4 text-center">
            <div class="card bg-secondary text-white p-3 border-0 shadow">
                <div class="mb-3">
                    <img src="<?= $avatarSrc ?>" alt="Avatar" class="rounded-circle img-thumbnail bg-dark" style="width: 140px; height: 140px; object-fit: cover;">
                </div>
                <h5 class="mb-1"><?= htmlspecialchars($currentUser['username']) ?></h5>
                <p class="text-white-50 small mb-3"><?= htmlspecialchars($currentUser['role']) ?></p>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_avatar">
                    <div class="mb-2">
                        <input type="file" name="avatar" class="form-control form-control-sm bg-dark text-light border-secondary" accept="image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-upload me-1"></i>Upload Avatar</button>
                </form>
            </div>
        </div>

        <!-- Details Card -->
        <div class="col-md-8">
            <div class="card bg-secondary text-white p-4 border-0 shadow">
                <h5 class="mb-3 text-info"><i class="bi bi-pencil-square me-2"></i>Personal Details</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($currentUser['username']) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile Phone Number <span class="text-warning">* (Mandatory)</span></label>
                        <input type="tel" name="phone" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($currentUser['phone']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subscription Status</label>
                        <div>
                            <?php if (hasPaidSubscription()): ?>
                                <span class="badge bg-success p-2"><i class="bi bi-star-fill me-1"></i>Active Paid Subscription</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark p-2 me-2"><i class="bi bi-x-circle me-1"></i>Free User</span>
                                <a href="subscriptions.php" class="btn btn-outline-info btn-sm"><i class="bi bi-gem me-1"></i>Upgrade Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success mt-2"><i class="bi bi-save me-1"></i>Save Profile Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
