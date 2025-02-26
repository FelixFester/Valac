<?php

session_start([
    'cookie_lifetime' => 86400,
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict',
]);

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

$db = new SQLite3('posts.db');

function renderPost($id, $title, $message, $mediaPath) {
    $mediaTag = '';
    if ($mediaPath) {
        $fileType = mime_content_type($mediaPath);
        if (strstr($fileType, 'video')) {
            $mediaTag = '<video class="post-media" controls width="200" height="200"><source src="' . $mediaPath . '"></video>';
        } else {
            $mediaTag = '<img class="post-media" src="' . $mediaPath . '" alt="media">';
        }
    }

    // Decode HTML entities in title and message
    $decodedTitle = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    $decodedMessage = html_entity_decode($message, ENT_QUOTES, 'UTF-8');

    return '
        <div class="post">
            <hr class="green-hr">
            <div class="post-media-container">' . $mediaTag . '</div>
            <h2>' . htmlspecialchars($decodedTitle) . '</h2>
            <p style="word-wrap: break-word;">' . nl2br(htmlspecialchars($decodedMessage)) . '</p>
        </div>
    ';
}

function renderReply($message, $index) {
    // Decode HTML entities in the reply message
    $decodedMessage = html_entity_decode($message, ENT_QUOTES, 'UTF-8');
    return '<div class="reply"><p><strong>Reply ' . $index . ':</strong> ' . nl2br(htmlspecialchars($decodedMessage)) . '</p></div>';
}

function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return $token === $_SESSION['csrf_token'];
}

$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_id = filter_input(INPUT_POST, 'post_id', FILTER_SANITIZE_NUMBER_INT);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $captcha = filter_input(INPUT_POST, 'captcha', FILTER_SANITIZE_STRING);
    $csrf_token_post = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    if (!validateCsrfToken($csrf_token_post)) {
        die('Invalid CSRF token');
    }

    if (empty($message)) {
        die('Reply message is required.');
    }

    if (empty($captcha) || $captcha !== $_SESSION['captcha_text']) {
        die('Invalid CAPTCHA.');
    }

    $stmt = $db->prepare('INSERT INTO replies (post_id, message) VALUES (:post_id, :message)');
    $stmt->bindValue(':post_id', $post_id, SQLITE3_INTEGER);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);
    $stmt->execute();

    // Bump the original post to the top by updating its row
    $db->exec('UPDATE posts SET id = id WHERE id = ' . $post_id);

    header('Location: reply.php?post_id=' . $post_id);
    exit;
}

$post_id = filter_input(INPUT_GET, 'post_id', FILTER_SANITIZE_NUMBER_INT);
if (empty($post_id)) {
    die('Post ID is required.');
}

$result = $db->query('SELECT * FROM posts WHERE id = ' . $post_id);
$post = $result->fetchArray(SQLITE3_ASSOC);
if (!$post) {
    die('Post not found.');
}

$replies = $db->query('SELECT * FROM replies WHERE post_id = ' . $post_id . ' ORDER BY id ASC');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reply to Post</title>
<?php
$themesDir = "themes/";
$themes = array_diff(scandir($themesDir), array('..', '.'));
?>

<!-- Apply selected theme -->
<link id="themeStylesheet" rel="stylesheet" type="text/css" href="themes/Clara Valac.css">

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const themeSelect = document.getElementById("themeSelect");
        const themeStylesheet = document.getElementById("themeStylesheet");

        // Load theme from cookies
        const savedTheme = document.cookie.replace(/(?:(?:^|.*;\s*)selectedTheme\s*\=\s*([^;]*).*$)|^.*$/, "$1");
        if (savedTheme) {
            themeStylesheet.href = "themes/" + savedTheme;
            themeSelect.value = savedTheme;
        }

        // Change theme and save to cookies
        themeSelect.addEventListener("change", function () {
            const selectedTheme = this.value;
            themeStylesheet.href = "themes/" + selectedTheme;
            document.cookie = "selectedTheme=" + selectedTheme + "; path=/; max-age=31536000"; // 1 year
        });
    });
</script>
</head>
<body>
    <div class="message-board">
        <a class="back-link" href="./">Back to Main Board</a>
        <?php
        echo renderPost($post['id'], $post['title'], $post['message'], $post['media']);
        ?>
        <form method="post">
            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <textarea name="message" placeholder="Reply message" required></textarea><br>
            <img src="simple_captcha.php" alt="CAPTCHA Image"><br>
            <input type="text" name="captcha" placeholder="Enter CAPTCHA" required><br>
            <button type="submit">Post Reply</button>
        </form>
        <?php
        $index = 1;
        while ($reply = $replies->fetchArray(SQLITE3_ASSOC)) {
            echo renderReply($reply['message'], $index++);
        }
        ?>
    </div>
   <br>
</div>
</body>
</html>
