<?php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../inc/mailer.php';

// Count total active subscribers
$totalSubs = $conn->query("SELECT COUNT(*) AS total FROM subscribers WHERE status=1")->fetch_assoc()['total'];

$statusMsg = '';
$sent = $failed = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $link = trim($_POST['link'] ?? '');

    $res = $conn->query("SELECT name,email FROM subscribers WHERE status=1");
    while ($row = $res->fetch_assoc()) {
        $unsubscribe = $BASE_URL . '/public/unsubscribe.php?email=' . urlencode($row['email']);

        // ✅ Modern Newsletter Design
        $html = "
        <div style='background:#273c75; padding:30px; border-radius:12px'>
        <div style='font-family: Arial, sans-serif; background:#ffbe76; padding:20px; border-radius:12px; max-width:700px; margin:auto;'>
          
          <!-- Header -->
          <div style='text-align:center; margin-bottom:20px;'>
            <h2 style='color:#fff; margin:0; font-size:22px;'>".htmlspecialchars($title)."</h2>
            <p style='color:#000; font-size:14px; margin-top:6px;'>Stay informed with today’s key market movements and trends.</p>
          </div>
          
          <!-- Image -->
          ".($image ? "<img src='".htmlspecialchars($image)."' style='width:100%; border-radius:10px; margin:15px 0;'>" : "")."
          
          <!-- Market Analysis -->
          <div style='background:#ffffff; border-radius:10px; padding:15px; margin-bottom:15px; box-shadow:0 2px 6px rgba(0,0,0,0.05);'>
            <h3 style='margin:0 0 10px 0; color:#1b5e20; font-size:18px;'>Indian Market Analysis</h3>
            <table width='100%' style='font-size:14px;'>
              <tr>
                <td style='color:#2e7d32; font-weight:bold;'>NIFTY 50</td>
                <td style='color:#2e7d32;'>+0.8%</td>
              </tr>
              <tr>
                <td style='color:#2e7d32; font-weight:bold;'>SENSEX</td>
                <td style='color:#2e7d32;'>+1.2%</td>
              </tr>
              <tr>
                <td style='color:#c62828; font-weight:bold;'>Bank NIFTY</td>
                <td style='color:#c62828;'>-0.3%</td>
              </tr>
            </table>
            
            <ul style='margin:12px 0 0 20px; padding:0; color:#444; font-size:14px;'>
              <li>IT sector leads gains on strong Q3 results</li>
              <li>Auto stocks mixed as festive season sales data awaited</li>
              <li>FII inflows continue for third consecutive week</li>
              <li>Rupee strengthens against dollar</li>
            </ul>
          </div>

          
          
          <!-- Sector Performance -->
          <div style='background:#e8f5e9; border-radius:10px; padding:15px; font-size:14px; margin-bottom:15px;'>
            <h4 style='margin:0 0 10px 0; color:#1b5e20;'>Sector Performance</h4>
            <p style='margin:4px 0; color:#2e7d32;'>Information Technology +2.1%</p>
            <p style='margin:4px 0; color:#1565c0;'>Pharmaceuticals +1.5%</p>
            <p style='margin:4px 0; color:#c62828;'>Banking & Finance -0.6%</p>
          </div>
          
          <!-- CTA Button -->
          ".($link ? "<div style='text-align:center; margin:20px 0;'>
            <a href='".htmlspecialchars($link)."' style='background:#fff; color:#000; text-decoration:none; padding:12px 20px; border-radius:8px; font-size:14px; display:inline-block;'>
              Read the full newsletter
            </a>
          </div>" : "")."
          
          <!-- Extra Content (Admin panel se diya hua) -->
          ".($content ? "<div style='background:#ffffff; border-radius:10px; padding:15px; margin-bottom:15px; box-shadow:0 2px 6px rgba(0,0,0,0.05); margin-top:15px; font-size:14px; color:#444;'>".$content."</div>" : "")."
          
          <!-- Footer -->
          <hr style='margin:20px 0; border:none; border-top:1px solid #ddd;'>
          <p style='font-size:12px; color:#666; text-align:center;'>
            If you wish to unsubscribe <a href='".$unsubscribe."' style='color:#1565c0;'>click here</a>
          </p>
        </div>
        </div>
        ";

        $ok = sendMailHTML($row['email'], $row['name'], $title, $html);
        if ($ok) $sent++; else $failed++;
        usleep(150000); // throttle
    }
    $statusMsg = "<div class='alert alert-success'>Done. Sent: $sent, Failed: $failed</div>";
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Send Newsletter</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars($BASE_URL . '/assets/css/styles.css') ?>">
</head>
<body>
<?php include __DIR__ . '/../inc/header.php'; ?>
<div class="container py-5">

  <!-- Header Card -->
  <div class="card shadow-sm border-1 mb-4 rounded-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
      
      <!-- Left: Title -->
      <div>
        <h3 class="mb-1" style="color:var(--brand1)">📨 Send Newsletter</h3>
        <small class="text-muted">Create and send a newsletter to all subscribers</small>
      </div>

      <!-- Center: Subscribers -->
      <div class="mx-auto text-center">
        <h5 class="mb-0 fw-bold" style="color:var(--brand4)">
          👥 Total Subscribers: 
          <span style="color:var(--brand1)"><?= $totalSubs ?></span>
        </h5>
      </div>

      <!-- Right: User + Logout -->
      <div class="text-end">
        <span class="me-2 text-muted small">
          👤 <?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin') ?>
        </span>
        <a href="logout.php" class="btn btn-sm btn-outline-danger">
          Logout
        </a>
      </div>

    </div>
  </div>

  <?= $statusMsg ?>

  <!-- Newsletter Form -->
  <div class="card shadow-lg border-0 rounded-3">
    <div class="card-body p-4">
      <form method="post" class="needs-validation" novalidate>
        
        <div class="mb-3">
          <label class="form-label fw-bold">Title</label>
          <input name="title" class="form-control form-control-lg" required placeholder="Enter newsletter title">
        </div>

        <div class="mb-3">
          <label class="form-label fw-bold">HTML Content</label>
          <textarea name="content" rows="6" class="form-control" placeholder="Paste or write HTML"></textarea>
          <small class="text-muted">💡 You can use inline HTML/CSS for rich formatting.</small>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-bold">Image URL</label>
            <input name="image" class="form-control" placeholder="https://...">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-bold">CTA Link</label>
            <input name="link" class="form-control" placeholder="https://...">
          </div>
        </div>

        <div class="text-center mt-4">
          <button class="btn btn-lg btn-primary px-4">
            🚀 Send to all subscribers
          </button>
        </div>
      </form>
    </div>
  </div>

</div>

</body>
</html>
