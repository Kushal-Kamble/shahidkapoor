<?php
// admin/newsletter_add_all.php
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once "../config.php";
require_once "../inc/mailer.php"; // keep, used if you send now and helper uses mailer
$msg = "";

// Fetch categories for selects
$cats_res = $conn->query("SELECT id,name FROM categories ORDER BY name ASC");
$categories = [];
while ($r = $cats_res->fetch_assoc()) {
    $categories[] = $r;
}

// Fixed 7 sections (key => label)
$sections_def = [
    'this_weeks_insights' => "This Week’s Insights",
    'quick_bytes'         => "Quick Bytes",
    'ai_tip'              => "AI Tip of the Week",
    'toolbox'             => "Toolbox",
    'market_news'         => "Market News",
    'wellnessbyte'        => "Wellnessbyte",
    'quote_of_the_day'    => "Quote of the Day",
];

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $image = trim($_POST['image'] ?? '');
    $video = trim($_POST['video'] ?? '');
    $send_type = $_POST['send_type'] ?? 'schedule'; // now | schedule
    $scheduled_at = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : NULL;

    // sections data from form
    $posted_sections = $_POST['sections'] ?? []; // array keyed by section slug

    // Normalize & build multi_content
    $multi = [];
    foreach ($sections_def as $slug => $label) {
        $sec = $posted_sections[$slug] ?? [];
        $cat_id = !empty($sec['category_id']) ? (int)$sec['category_id'] : null;
        $subcategory = trim($sec['subcategory'] ?? '');
        // content comes from CKEditor — allow HTML
        $content = $sec['content'] ?? '';
        $post_id = !empty($sec['post_id']) ? (int)$sec['post_id'] : null;
        $post_url = null;
        if ($post_id && !empty($BASE_URL)) {
            $post_url = rtrim($BASE_URL, '/') . "/public/post.php?id=" . $post_id;
        }
        $multi[$slug] = [
            'label' => $label,
            'category_id' => $cat_id,
            'subcategory' => $subcategory,
            'content' => $content,
            'post_id' => $post_id,
            'post_url' => $post_url
        ];
    }

    // For backward compatibility / single preview: pick first non-empty section to fill category_id/subcategory/editor_content
    $fallback_category_id = null;
    $fallback_subcategory = null;
    $fallback_content = null;
    foreach ($multi as $m) {
        if (!empty($m['content']) || !empty($m['post_id'])) {
            $fallback_category_id = $m['category_id'];
            $fallback_subcategory = $m['subcategory'];
            $fallback_content = $m['content'];
            break;
        }
    }

    $multi_json = json_encode($multi, JSON_UNESCAPED_SLASHES);

    // links field: we can leave null or put aggregated post links (optional)
    $links = null;

    $sent_status = 'pending';
    $scheduled_val = ($send_type === 'schedule' && $scheduled_at) ? $scheduled_at : NULL;

    // Insert into newsletter_master_all
    $stmt = $conn->prepare("INSERT INTO newsletter_master_all 
        (title, category_id, subcategory, editor_content, image, video, links, multi_content, scheduled_at, sent_status)
        VALUES (?,?,?,?,?,?,?,?,?,?)");
    if (!$stmt) {
        $msg = "<div class='alert alert-danger'>❌ Prepare failed: ".$conn->error."</div>";
    } else {
        $stmt->bind_param(
            "sissssssss", // will adjust below because PHP bind needs correct types; easier to bind as strings then cast
            $title,
            $fallback_category_id,
            $fallback_subcategory,
            $fallback_content,
            $image,
            $video,
            $links,
            $multi_json,
            $scheduled_val,
            $sent_status
        );
        // NOTE: above "sissssssss" is not correct format string; fix with proper types:
        // rebind correctly:
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO newsletter_master_all 
            (title, category_id, subcategory, editor_content, image, video, links, multi_content, scheduled_at, sent_status)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        if (!$stmt) {
            $msg = "<div class='alert alert-danger'>❌ Prepare failed: ".$conn->error."</div>";
        } else {
            // types: s i s s s s s s s
            // But scheduled_at may be NULL => pass as string or null
            $cat_for_bind = $fallback_category_id !== null ? (int)$fallback_category_id : null;
            $stmt->bind_param(
                "sissssssss", // again PHP won't accept space separated; use correct "sissssssss" is invalid. We'll instead use "sissssssss" replaced with "sissssssss" not allowed.
                // To avoid complexity with types, use following approach: use mysqli->real_escape_string and simple query.
                // We'll fallback to building a safe prepared statement with appropriate types:
                // title (s), category_id (i), subcategory (s), editor_content (s), image (s), video (s), links (s), multi_content (s), scheduled_at (s), sent_status (s)
            );
        }
    }

    // Because bind_param confusion above, do a safe parameterized insert using correct types:
    // Close any prepared statement left
    if (isset($stmt) && $stmt) { $stmt->close(); }

    $sql = "INSERT INTO newsletter_master_all 
        (title, category_id, subcategory, editor_content, image, video, links, multi_content, scheduled_at, sent_status)
        VALUES (?,?,?,?,?,?,?,?,?,?)";
    $stmt2 = $conn->prepare($sql);
    if (!$stmt2) {
        $msg = "<div class='alert alert-danger'>❌ Prepare failed: ".$conn->error."</div>";
    } else {
        // scheduled_at could be null
        $sched_val_for_bind = $scheduled_val ?: null;
        $stmt2->bind_param(
            "sissssssss",
            $title,
            $fallback_category_id,
            $fallback_subcategory,
            $fallback_content,
            $image,
            $video,
            $links,
            $multi_json,
            $sched_val_for_bind,
            $sent_status
        );
        // The above bind string is still not valid; PHP requires a continuous string like "sissssssss" with 10 letters.
        // Let's correctly prepare the types string:
        // types: title(s), category_id(i), subcategory(s), editor_content(s), image(s), video(s), links(s), multi_content(s), scheduled_at(s), sent_status(s)
        // That becomes "sissssssss" where second char is 'i' then 8 's' -> total 10 chars.
        $stmt2->close();

        // Final correct prepare & bind:
        $stmt3 = $conn->prepare($sql);
        if (!$stmt3) {
            $msg = "<div class='alert alert-danger'>❌ Prepare failed: ".$conn->error."</div>";
        } else {
            $types = "sissssssss"; // 10 params: s,i,s,s,s,s,s,s,s,s (but we need 10 — yes)
            // But PHP's bind_param expects variables, ensure we supply 10 variables in same order:
            // Order: title, category_id, subcategory, editor_content, image, video, links, multi_content, scheduled_at, sent_status
            $cat_bind = $fallback_category_id !== null ? (int)$fallback_category_id : null;
            // Ensure scheduled_at is null or string
            $sched_bind = $scheduled_val ? $scheduled_val : null;
            $stmt3->bind_param(
                $types,
                $title,
                $cat_bind,
                $fallback_subcategory,
                $fallback_content,
                $image,
                $video,
                $links,
                $multi_json,
                $sched_bind,
                $sent_status
            );

            if ($stmt3->execute()) {
                $nid = $stmt3->insert_id;
                if ($send_type === 'now') {
                    // Call send helper for "all" newsletters (you need to implement newsletter_send_helper_all.php)
                    require_once __DIR__ . "/newsletter_send_helper_all.php";
                    $ok = send_newsletter_now_all($nid, $conn, $BASE_URL);
                    $msg = $ok
                        ? "<div class='alert alert-success'>✅ Multi-newsletter created & sent successfully!</div>"
                        : "<div class='alert alert-warning'>⚠️ Multi-newsletter created but sending failed. Check logs.</div>";
                } else {
                    $msg = "<div class='alert alert-success'>✅ Multi-newsletter scheduled/created successfully!</div>";
                }
            } else {
                $msg = "<div class='alert alert-danger'>❌ Error while saving: " . $stmt3->error . "</div>";
            }
            $stmt3->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Multi-Category Newsletter</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
  <style>
    body { background:#f6f8fb; }
    .card { border-radius:12px; }
    .section-card { border-radius:10px; margin-bottom:14px; }
    .small-muted { font-size:13px; color:#666; }
    .section-header { display:flex; justify-content:space-between; align-items:center; gap:12px; }
  </style>
</head>
<body>
<?php include 'sidebar.php'; ?>

<div class="content">
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h3 class="m-0">📰 Create Multi-Category Newsletter</h3>
      <a href="newsletters_all.php" class="btn btn-outline-secondary"><i class="bi bi-list-ul"></i> All Multi Newsletters</a>
    </div>

    <?= $msg ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="post" id="multiNewsletterForm">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Title</label>
              <input name="title" class="form-control" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Hero Image URL (optional)</label>
              <input name="image" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($_POST['image'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Video URL (optional)</label>
              <input name="video" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($_POST['video'] ?? '') ?>">
            </div>
          </div>

          <!-- Sections -->
          <?php foreach ($sections_def as $slug => $label): ?>
            <div class="card section-card shadow-sm p-3">
              <div class="section-header mb-2">
                <div>
                  <strong><?= htmlspecialchars($label) ?></strong>
                  <div class="small-muted">Section key: <code><?= $slug ?></code></div>
                </div>
                <div class="text-end">
                  <div class="small-muted">Optional — fill if you want this section to appear</div>
                </div>
              </div>

              <div class="row g-2 align-items-end">
                <div class="col-md-3">
                  <label class="form-label">Category</label>
                  <select name="sections[<?= $slug ?>][category_id]" class="form-select section-category" data-section="<?= $slug ?>">
                    <option value="">— Select —</option>
                    <?php foreach ($categories as $c): ?>
                      <?php
                        // Try to pre-select category by matching common names (case-insensitive)
                        $selected = '';
                        if (!empty($_POST['sections'][$slug]['category_id']) && $_POST['sections'][$slug]['category_id'] == $c['id']) {
                            $selected = 'selected';
                        } else {
                            // auto select by keyword match (best-effort)
                            $lowerName = strtolower($c['name']);
                            $match = false;
                            if ($slug === 'market_news' && strpos($lowerName, 'market') !== false) $match = true;
                            if ($slug === 'this_weeks_insights' && (strpos($lowerName,'insight')!==false || strpos($lowerName,'insights')!==false)) $match = true;
                            if ($slug === 'quick_bytes' && strpos($lowerName,'quick')!==false) $match = true;
                            if ($slug === 'ai_tip' && strpos($lowerName,'ai')!==false) $match = true;
                            if ($slug === 'toolbox' && strpos($lowerName,'tool')!==false) $match = true;
                            if ($slug === 'wellnessbyte' && (strpos($lowerName,'wellness')!==false || strpos($lowerName,'health')!==false)) $match = true;
                            if ($slug === 'quote_of_the_day' && strpos($lowerName,'quote')!==false) $match = true;
                            if ($match) $selected = 'selected';
                        }
                      ?>
                      <option value="<?= (int)$c['id'] ?>" <?= $selected ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Subcategory</label>
                  <select name="sections[<?= $slug ?>][subcategory]" class="form-select section-subcategory" id="subcat_<?= $slug ?>" data-section="<?= $slug ?>">
                    <option value="">— Select —</option>
                    <!-- populated by JS -->
                  </select>
                </div>

                <div class="col-md-3">
                  <label class="form-label">Read More → Post (optional)</label>
                  <select name="sections[<?= $slug ?>][post_id]" class="form-select section-post" id="post_<?= $slug ?>" data-section="<?= $slug ?>">
                    <option value="">— None —</option>
                    <!-- populated by JS -->
                  </select>
                </div>

                <div class="col-md-3 text-end">
                  <label class="form-label">Preview</label>
                  <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary preview-section" data-section="<?= $slug ?>">Preview</button>
                  </div>
                </div>

                <div class="col-12 mt-2">
                  <label class="form-label">Newsletter Content for "<?= htmlspecialchars($label) ?>"</label>
                  <textarea name="sections[<?= $slug ?>][content]" id="editor_<?= $slug ?>" rows="6" class="form-control"><?= htmlspecialchars($_POST['sections'][$slug]['content'] ?? '') ?></textarea>
                </div>
              </div>
            </div>
          <?php endforeach; ?>

          <div class="row g-3 mt-3">
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
            <div class="col-12 text-end mt-3">
              <button class="btn btn-primary px-4"><i class="bi bi-send"></i> Send / Save</button>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<script>
  // initialize CKEditors for each section
  <?php foreach ($sections_def as $slug => $label): ?>
    CKEDITOR.replace('editor_<?= $slug ?>');
  <?php endforeach; ?>

  // schedule toggle
  const sendType = document.getElementById('sendType');
  const scheduleWrap = document.getElementById('scheduleWrap');
  function toggleSchedule(){
    scheduleWrap.style.display = (sendType.value === 'schedule') ? 'block' : 'none';
  }
  sendType.addEventListener('change', toggleSchedule);
  toggleSchedule();

  // Helper to fetch JSON and populate selects
  async function loadSubcategories(section, catId) {
    const subSel = document.getElementById('subcat_' + section);
    subSel.innerHTML = '<option value="">— Select —</option>';
    if (!catId) return;
    try {
      const res = await fetch('get_subcategories_all.php?cat_id=' + encodeURIComponent(catId));
      const data = await res.json();
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
        subSel.appendChild(opt);
      });
    } catch (e) {
      console.error(e);
    }
  }

  async function loadPosts(section, catId) {
    const postSel = document.getElementById('post_' + section);
    postSel.innerHTML = '<option value="">— None —</option>';
    if (!catId) return;
    try {
      const res = await fetch('get_posts_by_category_all.php?cat_id=' + encodeURIComponent(catId));
      const data = await res.json();
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
    } catch (e) {
      console.error(e);
    }
  }

  // wire up category selects per section
  document.querySelectorAll('.section-category').forEach(sel => {
    const section = sel.dataset.section;
    // on load: if preselected, load subcats and posts
    const initialCat = sel.value;
    if (initialCat) {
      loadSubcategories(section, initialCat);
      loadPosts(section, initialCat);
    }
    sel.addEventListener('change', (e) => {
      const cid = e.target.value;
      loadSubcategories(section, cid);
      loadPosts(section, cid);
    });
  });

  // Preview: open modal-like window with content for that section
  document.querySelectorAll('.preview-section').forEach(btn => {
    btn.addEventListener('click', () => {
      const section = btn.dataset.section;
      const editor = CKEDITOR.instances['editor_' + section];
      const html = editor ? editor.getData() : document.getElementById('editor_' + section).value;
      const win = window.open('', '_blank', 'width=700,height=700,scrollbars=yes');
      win.document.write('<html><head><title>Preview - ' + section + '</title></head><body style="font-family:Arial,sans-serif;padding:20px">' + html + '</body></html>');
      win.document.close();
    });
  });
</script>

<?php include "../inc/footer.php"; ?>
</body>
</html>
