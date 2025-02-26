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

// Create posts table if it doesn't exist
$db->exec('CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    media TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

// Create replies table if it doesn't exist
$db->exec('CREATE TABLE IF NOT EXISTS replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    FOREIGN KEY(post_id) REFERENCES posts(id)
)');

// Create trigger to update timestamp on post update
$db->exec('CREATE TRIGGER IF NOT EXISTS update_timestamp
           AFTER UPDATE ON posts
           FOR EACH ROW
           WHEN NEW.updated_at <= OLD.updated_at
           BEGIN
               UPDATE posts SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
           END');

$uploadsDir = 'uploads/';

if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

function getUniqueFilename($directory, $filename) {
    $path_info = pathinfo($filename);
    $basename = $path_info['filename'];
    $extension = isset($path_info['extension']) ? '.' . $path_info['extension'] : '';
    $newFilename = $basename . $extension;
    $counter = 1;
    
    while (file_exists($directory . $newFilename)) {
        $newFilename = $basename . '-' . $counter . $extension;
        $counter++;
    }
    
    return $newFilename;
}

function getReplyCount($post_id) {
    global $db;
    $result = $db->query("SELECT COUNT(*) as count FROM replies WHERE post_id = $post_id");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    return $row['count'];
}

function renderPost($id, $title, $message, $mediaPath) {
    $replyCount = getReplyCount($id);
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
            <p style="word-wrap: break-word; overflow-wrap: break-word;">' . nl2br(htmlspecialchars($decodedMessage)) . '</p>
            <a class="reply-button" href="reply.php?post_id=' . $id . '">[reply-' . $replyCount . ']</a>
        </div>
    ';
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
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $captcha = filter_input(INPUT_POST, 'captcha', FILTER_SANITIZE_STRING);
    $csrf_token_post = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    if (!validateCsrfToken($csrf_token_post)) {
        die('Invalid CSRF token');
    }

    if (empty($title) || empty($message)) {
        die('Title and message are required.');
    }

    // Initialize failed attempts counter if not set
    if (!isset($_SESSION['captcha_failed_attempts'])) {
        $_SESSION['captcha_failed_attempts'] = 0;
    }

    // Validate CAPTCHA
    if (empty($captcha) || $captcha !== $_SESSION['captcha_text']) {
        $_SESSION['captcha_failed_attempts']++;

        // Display different messages based on the number of failed attempts
        if ($_SESSION['captcha_failed_attempts'] >= 3) {
            die('Too many failed CAPTCHA attempts. Please try again later.');
        } else {
            die('Invalid CAPTCHA. Please try again.');
        }
    }

    // Reset failed attempts counter on successful CAPTCHA
    $_SESSION['captcha_failed_attempts'] = 0;

    // Handle file upload
    $media = $_FILES['media'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/webm', 'video/mp4'];
    if ($media['size'] > 0 && !in_array($media['type'], $allowed_types)) {
        die('Invalid file type');
    }

    if ($media['size'] > 5 * 1024 * 1024) {
        die('File too large');
    }

    $mediaPath = '';
    if ($media['size'] > 0) {
        $uniqueFilename = getUniqueFilename($uploadsDir, basename($media['name']));
        $mediaPath = $uploadsDir . $uniqueFilename;
        move_uploaded_file($media['tmp_name'], $mediaPath);
    }

    // Insert post into the database
    $stmt = $db->prepare('INSERT INTO posts (title, message, media) VALUES (:title, :message, :media)');
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);
    $stmt->bindValue(':media', $mediaPath, SQLITE3_TEXT);
    $result = $stmt->execute();

    if ($result) {
        echo "<p>Post inserted successfully.</p>";
    } else {
        echo "<p>Failed to insert post.</p>";
    }

    echo renderPost($db->lastInsertRowID(), $title, $message, $mediaPath);
    exit;
}

// Pagination
$postsPerPage = 10;
$totalPosts = $db->querySingle('SELECT COUNT(*) FROM posts');
$totalPages = ceil($totalPosts / $postsPerPage);
$page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_NUMBER_INT) ?: 1;
$offset = ($page - 1) * $postsPerPage;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="manifest" href="/manifest.json">
    <title>Your Board</title>
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
<br>
<center><h1><b>FLChan</b></h1>
<div id="randomText"></div><script src="scripts/randomstuff.js"></script><br>
Enter text.<br>
<script src="scripts/yummycooki.js"></script>
   <br>
   <!-- Theme Switcher -->
<div class="theme-switcher">
    <label for="themeSelect"><b>Themes:</b></label>
    <select id="themeSelect">
        <?php foreach ($themes as $theme): ?>
            <?php if (pathinfo($theme, PATHINFO_EXTENSION) === 'css'): ?>
                <option value="<?php echo htmlspecialchars($theme); ?>"><?php echo pathinfo($theme, PATHINFO_FILENAME); ?></option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>  <br> <a href="#" onclick="clearCookies(); return false;">Clear Cookies</a> // <a href="#">Your link</a><br><br></center>
    <div class="message-board">
        <div class="toggle-buttons">
            <button class="new-post-button">[NEW POST]</button>
            <button class="close-button">[X]</button>
        </div>
        <div class="form-container">
            <form id="postForm" enctype="multipart/form-data" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="text" name="title" placeholder="Title (or enter name)" maxlength="20" required><br>
                <textarea name="message" placeholder="Message" maxlength="100000" required></textarea><br>
                <input type="file" name="media" accept="image/jpeg, image/png, image/gif, image/webp, video/webm, video/mp4"><br>
                <img src="simple_captcha.php" alt="CAPTCHA Image"><br>
                <input type="text" name="captcha" placeholder="Are you robot?" required><br>
                <button type="submit">Post</button>
            </form>
        </div>
        <div id="posts">
            <?php
            $result = $db->query("SELECT * FROM posts ORDER BY updated_at DESC LIMIT $postsPerPage OFFSET $offset");
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                echo renderPost($row['id'], $row['title'], $row['message'], $row['media']);
            }
            ?>
        </div>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?php echo $i; ?>" class="<?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.new-post-button').click(function() {
                $(this).hide();
                $('.close-button').show();
                $('.form-container form').slideDown();
            });

            $('.close-button').click(function() {
                $(this).hide();
                $('.new-post-button').show();
                $('.form-container form').slideUp();
            });

            $('#postForm').on('submit', function(e) {
                e.preventDefault();
                var formData = new FormData(this);
                $.ajax({
                    type: 'POST',
                    url: '',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        $('#posts').prepend(response);
                        $('#postForm')[0].reset();
                        $('.close-button').hide();
                        $('.new-post-button').show();
                        $('.form-container form').slideUp();
                    }
                });
            });

            $(document).on('click', '.post-media', function() {
                $(this).toggleClass('expanded');
            });
        });
    </script>
   <br>
(A place for additional links and all this stuff that you can use or not)
 <br>
</div>
</body>
</html>
