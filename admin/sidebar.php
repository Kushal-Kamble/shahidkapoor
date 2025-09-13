<?php 
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Pehle full name, agar nahi mila to username, fir default "Admin"
$adminName = $_SESSION['admin_name'] ?? ($_SESSION['admin_username'] ?? 'Admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
  <!-- Toastify -->
  <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

  <style>

    body { background: #f5f7fb; font-family: 'Segoe UI', sans-serif; }
        .content {
            margin-left: 260px;
            padding: 25px;
        }
        h1.dashboard-title {
            font-weight: 600;
            border-bottom: 3px solid #f5945c;
            display: inline-block;
            padding-bottom: 6px;
        }
        .card {
            border-radius: 18px;
            border: none;
        }
        .stat-card {
            color: #fff;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 28px;
        }
        /* Custom gradients */
        .bg-subscribers { background: linear-gradient(135deg,#f5945c,#fec76f); }
        .bg-posts { background: linear-gradient(135deg,#2a9d8f,#38b000); }
        .bg-components { background: linear-gradient(135deg,#f6aa1c,#ef233c); }
        .bg-newsletters { background: linear-gradient(135deg,#0077b6,#00b4d8); }

        /* Latest lists */
        .list-group-item {
            border: none;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        .list-group-item:hover {
            background: #f8f9fa;
        }

    
    body {
    margin: 0;
    padding: 0;
    font-family: "Segoe UI", sans-serif;
    background: #f5f7fb;
  }

  .sidebar {
    width: 240px;
    height: 100vh;
    position: fixed;
    top: 56px; /* navbar ke niche se start */
    left: 0;
    background: #212529;
    padding-top: 20px;
    box-shadow: 2px 0 12px rgba(0,0,0,0.3);
    transition: all 0.3s ease;
    overflow-x: hidden;
    z-index: 999;
  }
  .sidebar.collapsed {
    width: 70px;
  }
  .sidebar a {
    color: #adb5bd;
    display: flex;
    align-items: center;
    padding: 14px 20px;
    text-decoration: none;
    font-size: 15px;
    border-left: 4px solid transparent;
    transition: all 0.3s;
  }
  .sidebar a:hover {
    background: #343a40;
    color: #fff;
    border-left: 4px solid #f5945c;
  }
  .sidebar a.active {
    background: #2c3035;
    color: #fff;
    font-weight: bold;
    border-left: 4px solid #f5945c;
  }
  .sidebar i {
    font-size: 18px;
    margin-right: 12px;
    transition: margin 0.3s;
  }
  .sidebar.collapsed a span {
    display: none;
  }
  .sidebar.collapsed a i {
    margin-right: 0;
    text-align: center;
    width: 100%;
  }
  .content {
    margin-left: 240px;
    margin-top: 70px;
    padding: 20px;
    transition: margin-left 0.3s;
  }
  .sidebar.collapsed ~ .content {
    margin-left: 90px;
  }
  .toggle-btn {
    position: fixed;
    top: 12px;
    left: 15px;
    font-size: 22px;
    background: #f5945c;
    border: none;
    color: #fff;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
    z-index: 1200;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    transition: background 0.3s, transform 0.2s;
  }
  .toggle-btn:hover {
    background: #e85c1f;
    transform: rotate(90deg);
  }

  /* Tooltip styling */
  .sidebar-tooltip {
    position: fixed;
    background: #000;
    color: #fff;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    z-index: 2000;
    pointer-events: none;
  }
  .sidebar a.active {
  background: #fff;
  color: #000;
  font-weight: bold;
  border-left: 4px solid #f5945c;
}

/* CKEditor insecure warning ko hide karna */
.cke_notifications_area {
    display: none !important;
}


  </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg shadow-sm fixed-top" style="background: linear-gradient(90deg, #f5945c, #fec76f);">
  <div class="container-fluid">
    <!-- Toggle Button -->
    <button class="btn btn-sm text-white me-2" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>

    <!-- Logo -->
    <a class="navbar-brand d-flex align-items-center bg-white rounded-2 p-1" 
       href="<?= htmlspecialchars($BASE_URL . '/public/index.php') ?>">
      <img src="../assets/logo-mit-school-of-distance-education.png" alt="Logo" style="height:40px;">
    </a>

    <!-- Navbar Items -->
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <?php if (!empty($_SESSION['admin_logged_in'])): ?>    
          <li class="nav-item me-2">
            <a href="#"  
            
               class="btn btn-success btn-sm">
                <i class="bi bi-person-circle me-1"></i> Admin <?= htmlspecialchars($adminName) ?>
            </a>
          </li>
          <li class="nav-item me-2">
            <a href="<?= htmlspecialchars($BASE_URL . '/admin/posts.php') ?>"  
            
               class="btn btn-light btn-sm">
              <i class="bi bi-envelope-paper-fill me-1"></i>  Admin Panel
            </a>
          </li>
          <li class="nav-item">
            <a href="<?= htmlspecialchars($BASE_URL . '/admin/logout.php') ?>" 
               class="btn btn-danger btn-sm">
              <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <button class="btn btn-light btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#subscribeModal">
              <i class="bi bi-bell-fill me-1 text-warning"></i> Subscribe
            </button>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Sidebar -->
<?php 
  $current_page = basename($_SERVER['PHP_SELF']); 
?>

<div class="sidebar" id="sidebar">
  <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
    <i class="bi bi-speedometer2"></i><span> Dashboard</span>
  </a>

  <a href="posts.php" class="<?= $current_page == 'posts.php' ? 'active' : '' ?>">
    <i class="bi bi-file-earmark-text"></i><span> Posts</span>
  </a>

  <a href="categories.php" class="<?= $current_page == 'categories.php' ? 'active' : '' ?>">
    <i class="bi bi-tags"></i><span> Categories</span>
  </a>

  <a href="newsletter_add.php" class="<?= $current_page == 'newsletter_add.php' ? 'active' : '' ?>">
    <i class="bi bi-envelope-plus"></i><span> Send Newsletter</span>
  </a>

  <a href="component_add.php" class="<?= $current_page == 'component_add.php' ? 'active' : '' ?>">
    <i class="bi bi-plus-square"></i><span> Add Component</span>
  </a>

  <a href="post_list.php" class="<?= $current_page == 'post_list.php' ? 'active' : '' ?>">
    <i class="bi bi-card-list"></i><span> Post List</span>
  </a>

  <a href="newsletters.php" class="<?= $current_page == 'newsletters.php' ? 'active' : '' ?>">
    <i class="bi bi-envelope-paper"></i><span> All Newsletters</span>
  </a>

  <a href="components.php" class="<?= $current_page == 'components.php' ? 'active' : '' ?>">
    <i class="bi bi-puzzle"></i><span> All Components</span>
  </a>

  <a href="subscribers.php" class="<?= $current_page == 'subscribers.php' ? 'active' : '' ?>">
    <i class="bi bi-people"></i><span> Subscribers</span>
  </a>
</div>





<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const sidebar = document.getElementById("sidebar");
  const content = document.querySelector(".content");
  const toggleBtn = document.getElementById("sidebarToggle");

  toggleBtn.addEventListener("click", function() {
    sidebar.classList.toggle("collapsed");
    content.classList.toggle("collapsed");
  });

  // Tooltip for collapsed sidebar
  const links = document.querySelectorAll(".sidebar a");
  links.forEach(link => {
    link.addEventListener("mouseenter", () => {
      if(sidebar.classList.contains("collapsed")){
        let tooltip = document.createElement("div");
        tooltip.innerText = link.innerText.trim();
        tooltip.className = "sidebar-tooltip";
        tooltip.style.left = "80px";
        tooltip.style.top = link.getBoundingClientRect().top + "px";
        document.body.appendChild(tooltip);
        link.onmouseleave = () => tooltip.remove();
      }
    });
  });
</script>

<!-- Toastify Welcome Notification (only once per session) -->
<!-- Toastify Welcome Notification (only once per session) -->
<?php if (!empty($_SESSION['admin_logged_in']) && empty($_SESSION['toast_shown'])): ?>
  <script>
    document.addEventListener("DOMContentLoaded", function () {
      Toastify({
        text: "👋 Welcome back, <?= htmlspecialchars($adminName); ?>!",
        duration: 4000,
        close: true,
        gravity: "top",
        position: "center",
        backgroundColor: "#15bb0fff",
      }).showToast();
    });
  </script>
  <?php $_SESSION['toast_shown'] = true; ?>
<?php endif; ?>


</body>
</html>
