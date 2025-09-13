<?php 
// admin/newsletter_send_helper.php
require_once __DIR__."/../inc/mailer.php";

function send_newsletter_now(int $newsletter_id, mysqli $conn, string $BASE_URL): bool {
    // load newsletter
    $stmt = $conn->prepare("SELECT * FROM newsletter_master WHERE id=?");
    $stmt->bind_param("i", $newsletter_id);
    $stmt->execute();
    $nl = $stmt->get_result()->fetch_assoc();
    if (!$nl) return false;

    // decode links for post link
    $post_url = null;
    if (!empty($nl['links'])) {
        $links = json_decode($nl['links'], true);
        if (!empty($links['post_url'])) $post_url = $links['post_url'];
    }

    // prepare short snippet
    $plain = trim(strip_tags($nl['editor_content'] ?? ''));
    $snippet = mb_substr($plain, 0, 180).(mb_strlen($plain) > 180 ? '…' : '');

    // hero image
    $hero = !empty($nl['image']) 
        ? "<img src=\"".htmlspecialchars($nl['image'])."\" style=\"width:100%;border-radius:10px;margin:10px 0\">" 
        : "";

    // CTA
    $cta  = $post_url 
        ? "<div style=\"text-align:center;margin:16px 0\">
                <a href=\"".htmlspecialchars($post_url)."\" style=\"background:#007bff;color:#fff;padding:12px 18px;border-radius:6px;text-decoration:none;display:inline-block\">
                Read more</a>
           </div>" 
        : "";

    // Build HTML template
    $html_template = "
      <!DOCTYPE html>
      <html>
      <head>
        <meta charset='UTF-8'>
      </head>
      <body style='font-family:Arial,sans-serif;background:#f5f7fb;padding:20px;margin:0'>
        <div style='max-width:640px;margin:auto;background:#fff;border-radius:12px;padding:20px'>
          <h2 style='margin:0 0 6px 0;color:#333'>".htmlspecialchars($nl['title'])."</h2>
          <div style='color:#666;font-size:13px;margin-bottom:8px'>Newsletter</div>
          {$hero}
          <div style='color:#333;line-height:1.6;font-size:14px'>".$nl['editor_content']."</div>
          {$cta}
          <hr style='margin:18px 0;border:none;border-top:1px solid #eee'>
          <p style='color:#555;font-size:14px;margin:0'>{$snippet}</p>
          <div style='color:#999;font-size:12px;margin-top:16px;text-align:center'>
            You are receiving this because you subscribed.<br>
            Unsubscribe: <a href='".rtrim($BASE_URL,'/')."/public/unsubscribe.php?email={email}' style='color:#666'>Unsubscribe</a>
          </div>
        </div>
      </body>
      </html>";

    // Plain-text version (AltBody)
    $alt_template = strip_tags(
        str_replace(
            ["<br>", "<br/>", "<br />"], "\n", 
            $nl['editor_content']
        )
    );
    if ($post_url) {
        $alt_template .= "\n\nRead more: ".$post_url;
    }

    // get subscribers
    $subs = $conn->query("SELECT name,email FROM subscribers WHERE status=1");

    $okCount=0; 
    $failCount=0;

    while($s = $subs->fetch_assoc()){
        $email = $s['email'];
        $name = $s['name'] ?? '';

        // personalize unsubscribe
        $html = str_replace('{email}', urlencode($email), $html_template);
        $alt  = str_replace('{email}', urlencode($email), $alt_template);

        $ok = sendMailHTML($email, $name, $nl['title'], $html, $alt);

        // log
        $lg = $conn->prepare("INSERT INTO newsletter_logs (newsletter_id, subscriber_email, status, error_msg) VALUES (?, ?, ?, ?)");
        if ($ok) {
            $status='sent'; 
            $err=NULL; 
            $okCount++;
        } else {
            $status='failed'; 
            $err=$conn->real_escape_string("mailer_error"); 
            $failCount++;
        }
        $lg->bind_param("isss", $newsletter_id, $email, $status, $err);
        $lg->execute();

        usleep(150000); // throttle
    }

    // update newsletter status
    $new_status = ($failCount>0 && $okCount===0) ? 'failed' : 'sent';
    $u = $conn->prepare("UPDATE newsletter_master SET sent_status=? WHERE id=?");
    $u->bind_param("si", $new_status, $newsletter_id);
    $u->execute();

    return $okCount>0;
}
