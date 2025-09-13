<?php
require_once "../config.php";

$cat_id = intval($_GET['cat_id'] ?? 0);
$posts = [];

if ($cat_id > 0) {
    $sql = "SELECT id, title, post_date 
            FROM posts 
            WHERE category_id=$cat_id AND status='published'
            ORDER BY id DESC";
    $res = $conn->query($sql);

    while ($row = $res->fetch_assoc()) {
        // check if post already used in any newsletter
        $pid = (int)$row['id'];
        $usedQ = $conn->query("SELECT id FROM newsletter_master 
                               WHERE JSON_EXTRACT(links, '$.post_id')=$pid LIMIT 1");
        $is_used = $usedQ && $usedQ->num_rows > 0 ? 1 : 0;

        $posts[] = [
            'id' => $pid,
            'title' => $row['title'],
            'post_date' => $row['post_date'],
            'is_used' => $is_used
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($posts);
