<?php
// admin/header.php
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Xtream IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-dark text-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3">
    <a class="navbar-brand fw-bold" href="index.php"><i class="bi bi-shield-lock-fill me-2"></i>Admin Dashboard</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
                <a class="nav-link" href="servers.php"><i class="bi bi-hdd-network me-1"></i>Global Servers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php"><i class="bi bi-people me-1"></i>User Management</a>
            </li>
        </ul>
        <div class="d-flex align-items-center">
            <a href="../index.php" class="btn btn-outline-light btn-sm me-2"><i class="bi bi-play-circle me-1"></i>Main Player Dashboard</a>
            <a href="../logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>
<div class="container py-4">
