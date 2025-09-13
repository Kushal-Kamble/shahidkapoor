  <?php  
  // admin/newsletter_add.php
  session_start();
  if (empty($_SESSION['admin_logged_in'])) { 
      header('Location: login.php'); 
      exit; 
  }

  require_once "../config.php";
  require_once "../inc/mailer.php";

  $msg = "";

  // Fetch categories
  $cats = $conn->query("SELECT id,name FROM categories ORDER BY name ASC");

  // Handle form submit
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $title = trim($_POST['title'] ?? '');
      $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : NULL;
      $subcategory = trim($_POST['subcategory'] ?? '');
      $editor_content = $_POST['editor_content'] ?? '';
      $image = trim($_POST['image'] ?? '');
      $video = trim($_POST['video'] ?? '');
      $send_type = $_POST['send_type'] ?? 'schedule'; // now | schedule
      $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : NULL;
      $post_id = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : NULL;

      // Pack links JSON (store post link if selected)
      $links = null;
      if ($post_id) {
          $post_url = rtrim($BASE_URL, '/')."/public/post.php?id=".$post_id;
          $links = json_encode(['post_id'=>$post_id,'post_url'=>$post_url], JSON_UNESCAPED_SLASHES);
      }

      // Default values
      $sent_status = 'pending';
      $scheduled_val = ($send_type === 'schedule' && $scheduled_at) ? $scheduled_at : NULL;

      // Insert Newsletter
      $stmt = $conn->prepare("INSERT INTO newsletter_master
          (title, category_id, subcategory, editor_content, image, video, links, scheduled_at, sent_status)
          VALUES (?,?,?,?,?,?,?,?,?)");

      $stmt->bind_param(
          "sisssssss",
          $title, $category_id, $subcategory, $editor_content, $image, $video, $links, $scheduled_val, $sent_status
      );

      if ($stmt->execute()) {
          $nid = $stmt->insert_id;

          if ($send_type === 'now') {
              // Send immediately
              require_once __DIR__."/newsletter_send_helper.php";
              $ok = send_newsletter_now($nid, $conn, $BASE_URL);
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
    <title>Create Newsletter</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
    <style>
      body { background:#f6f8fb; }
      .card { border-radius:16px; }
      option[disabled] { color:#999; font-style:italic; }
    </style>
  </head>
  <body>
  <?php include 'sidebar.php'; ?> 

  <div class="content">
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="m-0">📰 Create Newsletter</h3>
      <a href="newsletters.php" class="btn text-white" style="background:#fd5402;"><i class="bi bi-list-ul"></i> All Newsletters</a>
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
              <select name="category_id" id="categorySelect" class="form-select" required>
                <option value="">— Select —</option>
                <?php while($c=$cats->fetch_assoc()): ?>
                  <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold">Subcategory</label>
              <select name="subcategory" id="subcategorySelect" class="form-select">
                <option value="">— Select —</option>
              </select>
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
              <select name="post_id" id="postSelect" class="form-select">
                <option value="">— None —</option>
              </select>
              <small class="text-muted">“Read More” link is post pe redirect karega.</small>
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
            <button class="btn text-white"  style="background:#fd5402;"><i class="bi bi-send"></i> Send</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  </div>

  <script>
    CKEDITOR.replace('editor');

    // schedule toggle
    const sendType = document.getElementById('sendType');
    const scheduleWrap = document.getElementById('scheduleWrap');
    function toggleSchedule(){
      scheduleWrap.style.display = (sendType.value === 'schedule') ? 'block' : 'none';
    }
    sendType.addEventListener('change', toggleSchedule);
    toggleSchedule();

    // fetch subcategories + posts dynamically
    const catSel = document.getElementById('categorySelect');
    const subcatSel = document.getElementById('subcategorySelect');
    const postSel = document.getElementById('postSelect');

    catSel.addEventListener('change', () => {
      const cid = catSel.value;
      subcatSel.innerHTML = '<option value="">— Select —</option>';
      postSel.innerHTML = '<option value="">— None —</option>';
      if (!cid) return;

      // load subcategories
      // load subcategories
  fetch('get_subcategories.php?cat_id=' + cid)
    .then(r => r.json())
    .then(data => {
      const today = new Date().toISOString().split('T')[0];
      data.forEach(sc => {
        const opt = document.createElement('option');
        opt.value = sc.name;

        let label = sc.name;
        if (sc.is_used == 1) {
          label += " (✅ Used)";
          opt.disabled = true;
        } else {
          if (sc.created_date === today) {
            label += " (🆕 New)";
          } else {
            label += " (" + sc.created_date + ")";
          }
        }

        opt.textContent = label;
        subcatSel.appendChild(opt);
      });
    });


      // load posts with status
      fetch('get_posts_by_category.php?cat_id=' + cid)
        .then(r => r.json())
        .then(data => {
          const today = new Date().toISOString().split('T')[0];
          data.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;

            let label = p.title;
            if (p.is_used == 1) {
              label += " (✅ Used)";
              opt.disabled = true;
            } else {
              if (p.post_date === today) {
                label += " (🆕 New)";
              } else {
                label += " (" + p.post_date + ")";
              }
            }

            opt.textContent = label;
            postSel.appendChild(opt);
          });
        });
    });
  </script>

  <?php include "../inc/footer.php"; ?>
  </body>
  </html>
