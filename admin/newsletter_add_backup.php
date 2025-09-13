<?php
// admin/newsletter_add.php
session_start();
if (empty($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }
require_once "../config.php";
require_once "../inc/mailer.php";

$msg = "";

// fetch categories, components, posts (published)
$cats = $conn->query("SELECT id,name FROM categories ORDER BY name");
$components = $conn->query("SELECT id,title,category_id FROM component_master ORDER BY title");
$posts = $conn->query("SELECT id,title FROM posts WHERE status='published' ORDER BY id DESC");

// handle submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : NULL;
    $subcategory = trim($_POST['subcategory'] ?? '');
    $editor_content = $_POST['editor_content'] ?? '';
    $image = trim($_POST['image'] ?? '');
    $video = trim($_POST['video'] ?? '');
    $sel_components = $_POST['component_ids'] ?? []; // array
    $send_type = $_POST['send_type'] ?? 'schedule'; // now|schedule
    $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : NULL;
    $post_id = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : NULL;

    // pack links JSON (we’ll store post link here)
    $links = null;
    if ($post_id) {
        $post_url = rtrim($BASE_URL, '/')."/public/post.php?id=".$post_id;
        $links = json_encode(['post_id'=>$post_id,'post_url'=>$post_url], JSON_UNESCAPED_SLASHES);
    }

    $component_ids_json = json_encode(array_map('intval',$sel_components));

    // default: pending, schedule
    $sent_status = 'pending';
    $scheduled_val = NULL;

    if ($send_type === 'schedule') {
        $scheduled_val = $scheduled_at ?: NULL;
    }

    $stmt = $conn->prepare("INSERT INTO newsletter_master
        (title, category_id, subcategory, editor_content, image, video, links, component_ids, used_in, scheduled_at, sent_status)
        VALUES (?,?,?,?,?,?,?,?,0,?,?)");
    $stmt->bind_param(
        "sissssssss",
        $title, $category_id, $subcategory, $editor_content, $image, $video, $links, $component_ids_json, $scheduled_val, $sent_status
    );

    if ($stmt->execute()) {
        $nid = $stmt->insert_id;

        // mark components used
        if (!empty($sel_components)) {
            $ids_in = implode(",", array_map('intval',$sel_components));
            $conn->query("UPDATE component_master SET used_in=1 WHERE id IN ($ids_in)");
        }

        if ($send_type === 'now') {
            // send immediately
            require_once __DIR__."/newsletter_send_helper.php";
            $ok = send_newsletter_now($nid, $conn, $BASE_URL);
            if ($ok) {
                $msg = "<div class='alert alert-success'>✅ Newsletter created & sent!</div>";
            } else {
                $msg = "<div class='alert alert-warning'>Newsletter created but sending encountered errors. Check logs.</div>";
            }
        } else {
            $msg = "<div class='alert alert-success'>✅ Newsletter scheduled!</div>";
        }
    } else {
        $msg = "<div class='alert alert-danger'>❌ Error: ".$stmt->error."</div>";
    }
}
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Create Newsletter</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
  <style>
    body{background:#f6f8fb}
    .card{border-radius:16px}
    .tag{font-size:12px;border:1px solid #ddd;border-radius:20px;padding:2px 8px;margin-right:6px;background:#fff}
  </style>
</head>
<body>
<?php include 'sidebar.php'; ?> 

<div class="content">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="m-0">📰 Create Newsletter</h3>
    <a href="newsletters.php" class="btn btn-outline-secondary"><i class="bi bi-list-ul"></i> All Newsletters</a>
  </div>

  <?= $msg ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Title</label>
            <input name="title" class="form-control" required>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Category</label>
            <select name="category_id" id="categorySelect" class="form-select">
              <option value="">— Select —</option>
              <?php while($c=$cats->fetch_assoc()): ?>
                <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Subcategory</label>
            <input name="subcategory" class="form-control">
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Components (multi-select)</label>
            <select name="component_ids[]" id="componentsSelect" class="form-select" multiple size="6">
              <?php
              // re-run pointer
              $components->data_seek(0);
              while($cm=$components->fetch_assoc()): ?>
                <option 
                  data-cat="<?= (int)$cm['category_id'] ?>" 
                  value="<?= (int)$cm['id'] ?>">
                  [Cat#<?= (int)$cm['category_id'] ?>] <?= htmlspecialchars($cm['title']) ?>
                </option>
              <?php endwhile; ?>
            </select>
            <small class="text-muted">Category select karte hi components auto-filter ho jayenge.</small>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Hero Image URL (optional)</label>
            <input name="image" class="form-control" placeholder="https://...">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Video URL (optional)</label>
            <input name="video" class="form-control" placeholder="https://...">
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Read More → Post (optional)</label>
            <select name="post_id" class="form-select">
              <option value="">— None —</option>
              <?php while($p=$posts->fetch_assoc()): ?>
                <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
              <?php endwhile; ?>
            </select>
            <small class="text-muted">Email me “Read More” link isi post pe jayega.</small>
          </div>

          <div class="col-12">
            <label class="form-label fw-semibold">Newsletter Content (CKEditor)</label>
            <textarea name="editor_content" id="editor" rows="8" class="form-control"></textarea>
          </div>

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
        </div>

        <div class="text-end mt-4">
          <button class="btn btn-primary px-4"><i class="bi bi-send"></i> Save</button>
        </div>
      </form>
    </div>
  </div>
</div>
</div>
<script>
  CKEDITOR.replace('editor');

  // filter components by category
  const catSel = document.getElementById('categorySelect');
  const compSel = document.getElementById('componentsSelect');
  function filterOptions(){
    const c = catSel.value;
    [...compSel.options].forEach(o=>{
      if(!c) { o.hidden = false; return; }
      o.hidden = (o.getAttribute('data-cat') !== c);
    });
  }
  catSel.addEventListener('change', filterOptions);
  filterOptions();

  // schedule toggle
  const sendType = document.getElementById('sendType');
  const scheduleWrap = document.getElementById('scheduleWrap');
  function toggleSchedule(){
    scheduleWrap.style.display = (sendType.value === 'schedule') ? 'block' : 'none';
  }
  sendType.addEventListener('change', toggleSchedule);
  toggleSchedule();
</script>
<?php include "../inc/footer.php"; ?>
</body>
</html>
 