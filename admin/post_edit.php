<?php
include "../config.php"; // DB connection

// Agar URL me post_id nahi mila
if (!isset($_GET['id'])) {
    echo "<div class='alert alert-danger'>Invalid Post ID</div>";
    exit;
}

$post_id = intval($_GET['id']);

// Pehle existing post data fetch karo
$stmt = $conn->prepare("SELECT * FROM posts WHERE id = ?");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    echo "<div class='alert alert-danger'>Post not found!</div>";
    exit;
}

// Agar form submit hua hai (Update Post)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = $_POST['category_id'];
    $component_id = $_POST['component_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];

    $stmt = $conn->prepare("UPDATE posts SET category_id=?, component_id=?, title=?, description=? WHERE id=?");
    $stmt->bind_param("iissi", $category_id, $component_id, $title, $description, $post_id);

    if ($stmt->execute()) {
        echo "<script>alert('✅ Post updated successfully!'); window.location='posts.php';</script>";
    } else {
        echo "<div class='alert alert-danger'>Error updating post.</div>";
    }
}

// Category list
$categories = $conn->query("SELECT * FROM categories");

// Component list (⚡ FIX: component_master)
$components = $conn->query("SELECT * FROM component_master");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Post</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
</head>

<body class="bg-light">
    <div class="container mt-4">
        <h2 class="mb-4">✏️ Edit Post</h2>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Category</label>
                <select name="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php while ($row = $categories->fetch_assoc()) { ?>
                        <option value="<?= $row['id']; ?>" <?= ($row['id'] == $post['category_id']) ? 'selected' : ''; ?>>
                            <?= $row['name']; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Component</label>
                <select name="component_id" class="form-control" required>
                    <option value="">-- Select Component --</option>
                    <?php while ($row = $components->fetch_assoc()) { ?>
                        <option value="<?= $row['id']; ?>" <?= ($row['id'] == $post['component_id']) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($row['title']); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" 
                       value="<?= htmlspecialchars($post['title']); ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" id="editor" rows="6" required><?= htmlspecialchars($post['description']); ?></textarea>
            </div>

            <button type="submit" class="btn btn-success">Update Post</button>
            <a href="posts.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>

    <!-- CKEditor -->
    <script src="https://cdn.ckeditor.com/4.17.1/standard/ckeditor.js"></script>
    <script>
        CKEDITOR.replace('editor');
    </script>
</body>
</html>
