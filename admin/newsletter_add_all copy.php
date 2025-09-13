<?php
// admin/newsletter_add_all.php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once "../config.php";
require_once "../inc/mailer.php";

$msg = "";

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $video = trim($_POST['video'] ?? '');
    $send_type = $_POST['send_type'] ?? 'schedule';
    $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : NULL;

    // Collect all category data
    $categories = [
        "this_week" => "This Week’s Insights",
        "quick_bytes" => "Quick Bytes",
        "ai_tip" => "AI Tip of the Week",
        "toolbox" => "Toolbox",
        "market_news" => "Market News",
        "wellness" => "Wellnessbyte",
        "quote" => "Quote of the Day"
    ];

    $sections = [];
    foreach ($categories as $key => $label) {
        $sections[$key] = [
            "subcategory" => trim($_POST[$key."_subcategory"] ?? ''),
            "content"     => $_POST[$key."_content"] ?? '',
            "post_id"     => !empty($_POST[$key."_post_id"]) ? (int)$_POST[$key."_post_id"] : NULL
        ];
    }

    // Store JSON of all category sections
    $sections_json = json_encode($sections, JSON_UNESCAPED_SLASHES);

    // Insert newsletter (new table column `sections_json` required)
    $stmt = $conn->prepare("INSERT INTO newsletter_master
        (title, image, video, sections_json, scheduled_at, sent_status)
        VALUES (?,?,?,?,?,?)");

    $sent_status = 'pending';
    $stmt->bind_param("ssssss", $title, $image, $video, $sections_json, $scheduled_at, $sent_status);

    if ($stmt->execute()) {
        $nid = $stmt->insert_id;

        if ($send_type === 'now') {
            require_once __DIR__."/newsletter_send_helper_all.php";
            $ok = send_newsletter_all_now($nid, $conn, $BASE_URL);
            $msg = $ok
                ? "<div class='alert alert-success'>✅ Newsletter created & sent successfully!</div>"
                : "<div class='alert alert-warning'>⚠️ Newsletter created but sending failed. Check logs.</div>";
        } else {
            $msg = "<div class='alert alert-success'>✅ Newsletter scheduled successfully!</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger'>❌ Error: ".$stmt->error."</div>";
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Multi-Category Newsletter</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
  <style>
    body { background:#f6f8fb; }
    .card { border-radius:16px; margin-bottom: 1.5rem; }
    .section-title { font-size:1.1rem; font-weight:600; color:#333; margin-bottom:10px; }
  </style>
</head>
<body>
<?php include 'sidebar.php'; ?> 

<div class="content">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">📰 Create Multi-Category Newsletter</h3>
    <a href="newsletters.php" class="btn btn-outline-secondary">All Newsletters</a>
  </div>

  <?= $msg ?>

  <form method="post">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Title</label>
            <input name="title" class="form-control" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Hero Image URL (optional)</label>
            <input name="image" class="form-control" placeholder="https://...">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Video URL (optional)</label>
            <input name="video" class="form-control" placeholder="https://...">
          </div>
        </div>
      </div>
    </div>

    <?php 
    $categories = [
        "this_week" => "This Week’s Insights",
        "quick_bytes" => "Quick Bytes",
        "ai_tip" => "AI Tip of the Week",
        "toolbox" => "Toolbox",
        "market_news" => "Market News",
        "wellness" => "Wellnessbyte",
        "quote" => "Quote of the Day"
    ];
    foreach ($categories as $key => $label): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="section-title">📌 <?= $label ?></div>
        <div class="row g-3">
            
          <div class="col-md-4">
            <label class="form-label">Subcategory</label>
            <select name="<?= $key ?>_subcategory" class="form-select">
              <option value="">— Select —</option>
              <!-- Load dynamically later -->
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Read More → Post</label>
            <select name="<?= $key ?>_post_id" class="form-select">
              <option value="">— None —</option>
              <!-- Load dynamically later -->
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Content</label>
            <textarea name="<?= $key ?>_content" id="editor_<?= $key ?>" rows="5" class="form-control"></textarea>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="card shadow-sm">
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label fw-semibold">Send Type</label>
          <select name="send_type" id="sendType" class="form-select">
            <option value="now">Send Now</option>
            <option value="schedule">Schedule</option>
          </select>
        </div>
        <div class="col-md-4" id="scheduleWrap">
          <label class="form-label fw-semibold">Scheduled At</label>
          <input type="datetime-local" name="scheduled_at" class="form-control">
        </div>
        <div class="text-end mt-3">
          <button class="btn btn-primary px-4"><i class="bi bi-send"></i> Send</button>
        </div>
      </div>
    </div>
  </form>
</div>
</div>

<script>
  // enable CKEditor for all category content areas
  <?php foreach ($categories as $key => $label): ?>
    CKEDITOR.replace('editor_<?= $key ?>');
  <?php endforeach; ?>

  // toggle schedule
  const sendType = document.getElementById('sendType');
  const scheduleWrap = document.getElementById('scheduleWrap');
  function toggleSchedule(){
    scheduleWrap.style.display = (sendType.value === 'schedule') ? 'block' : 'none';
  }
  sendType.addEventListener('change', toggleSchedule);
  toggleSchedule();

  // ✅ Fetch posts for all categories (by ID mapping)
const categoryMap = {
  "this_week": 1,
  "quick_bytes": 2,
  "ai_tip": 3,
  "toolbox": 4,
  "market_news": 5,
  "wellness": 6,
  "quote": 7
};

Object.keys(categoryMap).forEach(catKey => {
  const postSelect = document.querySelector(`select[name="${catKey}_post_id"]`);
  if (postSelect) {
    fetch(`get_posts_by_category_all.php?cat_id=${categoryMap[catKey]}`)
      .then(res => res.json())
      .then(posts => {
        postSelect.innerHTML = '<option value="">— None —</option>';
        posts.forEach(p => {
          const opt = document.createElement('option');
          opt.value = p.id;
          opt.textContent = p.title + (p.subcategory ? ` [${p.subcategory}]` : '');
          postSelect.appendChild(opt);
        });
      })
      .catch(err => console.error("Posts load error:", err));
  }
});


  

  // ✅ Fetch subcategories for all categories
  fetch('get_subcategories_all.php')
    .then(res => res.json())
    .then(data => {
      Object.keys(data).forEach(catKey => {
        const subcatSelect = document.querySelector(`select[name="${catKey}_subcategory"]`);
        if (subcatSelect) {
          subcatSelect.innerHTML = '<option value="">— Select —</option>';
          data[catKey].forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.textContent = item.name + (item.is_used ? " (used)" : "");
            if (item.is_used) opt.disabled = true;
            subcatSelect.appendChild(opt);
          });
        }
      });
    })
    .catch(err => console.error('Subcategories load error:', err));
</script>

<?php include "../inc/footer.php"; ?>
</body>
</html>
