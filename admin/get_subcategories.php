<?php
require_once "../config.php";
$cat_id = intval($_GET['cat_id'] ?? 0);

$subcats = [];
if ($cat_id > 0) {
    // Distinct subcategories from posts
    $sql = "SELECT subcategory, DATE(created_at) as created_date
            FROM posts
            WHERE category_id=$cat_id AND subcategory<>'' 
            GROUP BY subcategory
            ORDER BY subcategory ASC";
    $res = $conn->query($sql);

    while ($row = $res->fetch_assoc()) {
        $subcatName = $row['subcategory'];
        $created = $row['created_date'];

        // Check if already used in newsletter_master
        $used = 0;
        $check = $conn->query("SELECT id FROM newsletter_master 
                               WHERE category_id=$cat_id AND subcategory='".$conn->real_escape_string($subcatName)."' 
                               LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $used = 1;
        }

        $subcats[] = [
            'name' => $subcatName,
            'is_used' => $used,
            'created_date' => $created
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($subcats);
