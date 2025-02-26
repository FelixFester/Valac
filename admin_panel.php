<?php

// Start the session
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

// Hashed password for moderator access
define('MODERATOR_PASSWORD_HASH', 'enter_here_md5_hash');

// Check if the user is logged in as a moderator
if (isset($_POST['password'])) {
    if (password_verify($_POST['password'], MODERATOR_PASSWORD_HASH)) {
        $_SESSION['is_moderator'] = true;
        // Redirect to remove any query parameters (e.g., ?logout=1)
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    } else {
        echo '<p style="color: red;">Wrong password.</p>';
    }
} elseif (isset($_GET['logout'])) {
    unset($_SESSION['is_moderator']);
    // Redirect to remove the ?logout=1 query parameter
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Redirect to login if not logged in
if (!isset($_SESSION['is_moderator'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>...</title>
    </head>
    <body>
        <form method="post" action="">
            <label for="password">Password:</label>
            <input type="password" name="password" id="password" required>
            <button type="submit">login</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Setup SQLite3 database
$db = new SQLite3('posts.db');

// Handle post deletion (soft delete)
if (isset($_POST['delete_post_id'])) {
    $post_id = filter_input(INPUT_POST, 'delete_post_id', FILTER_SANITIZE_NUMBER_INT);

    // Fetch the media file path before deleting the post
    $stmt = $db->prepare('SELECT media FROM posts WHERE id = :id');
    $stmt->bindValue(':id', $post_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $post = $result->fetchArray(SQLITE3_ASSOC);

    // Delete the media file if it exists
    if (!empty($post['media']) && file_exists($post['media'])) {
        unlink($post['media']); // Delete the file from the server
    }

    // Update the post to mark it as deleted
    $stmt = $db->prepare('UPDATE posts SET title = :title, message = :message, media = :media WHERE id = :id');
    $stmt->bindValue(':title', 'Post Deleted By Admin', SQLITE3_TEXT);
    $stmt->bindValue(':message', 'Post Deleted By Admin', SQLITE3_TEXT);
    $stmt->bindValue(':media', '', SQLITE3_TEXT); // Clear the media field
    $stmt->bindValue(':id', $post_id, SQLITE3_INTEGER);
    $stmt->execute();
}

// Handle post deletion (hard delete - completely remove the post)
if (isset($_POST['delete_post_completely_id'])) {
    $post_id = filter_input(INPUT_POST, 'delete_post_completely_id', FILTER_SANITIZE_NUMBER_INT);

    // Fetch the media file path before deleting the post
    $stmt = $db->prepare('SELECT media FROM posts WHERE id = :id');
    $stmt->bindValue(':id', $post_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $post = $result->fetchArray(SQLITE3_ASSOC);

    // Delete the media file if it exists
    if (!empty($post['media']) && file_exists($post['media'])) {
        unlink($post['media']); // Delete the file from the server
    }

    // Delete the post from the database
    $stmt = $db->prepare('DELETE FROM posts WHERE id = :id');
    $stmt->bindValue(':id', $post_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Delete associated replies (optional, if you want to clean up replies as well)
    $stmt = $db->prepare('DELETE FROM replies WHERE post_id = :post_id');
    $stmt->bindValue(':post_id', $post_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Update the sequence number for posts in sqlite_sequence
    $db->exec('UPDATE sqlite_sequence SET seq = seq - 1 WHERE name = "posts"');
}

// Handle reply deletion (soft delete)
if (isset($_POST['delete_reply_id'])) {
    $reply_id = filter_input(INPUT_POST, 'delete_reply_id', FILTER_SANITIZE_NUMBER_INT);
    $stmt = $db->prepare('UPDATE replies SET message = :message WHERE id = :id');
    $stmt->bindValue(':message', 'Reply Deleted By Admin', SQLITE3_TEXT);
    $stmt->bindValue(':id', $reply_id, SQLITE3_INTEGER);
    $stmt->execute();
}

// Handle reply deletion (hard delete - completely remove the reply)
if (isset($_POST['delete_reply_completely_id'])) {
    $reply_id = filter_input(INPUT_POST, 'delete_reply_completely_id', FILTER_SANITIZE_NUMBER_INT);

    // Delete the reply from the database
    $stmt = $db->prepare('DELETE FROM replies WHERE id = :id');
    $stmt->bindValue(':id', $reply_id, SQLITE3_INTEGER);
    $stmt->execute();

    // Update the sequence number for replies in sqlite_sequence
    $db->exec('UPDATE sqlite_sequence SET seq = seq - 1 WHERE name = "replies"');
}

// Fetch posts
$posts = $db->query('SELECT * FROM posts ORDER BY updated_at DESC');

// Fetch replies for a post
function fetchReplies($post_id, $db) {
    $stmt = $db->prepare('SELECT * FROM replies WHERE post_id = :post_id ORDER BY id ASC');
    $stmt->bindValue(':post_id', $post_id, SQLITE3_INTEGER);
    return $stmt->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
<style>
    /* Base reset */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    html {
        font-size: 62.5%; /* 1rem = 10px */
        scroll-behavior: smooth;
    }

    body {
        background-color: #000000;
        font-family: 'Courier New', Courier, monospace;
        color: #A4FF00;
        line-height: 1.6;
        font-size: 1.6rem; /* 16px */
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* Message board container */
    .message-board {
        width: 95%;
        max-width: 1200px;
        margin: 2rem auto;
        padding: 2rem;
        background: #0A0A0A;
        border-radius: 8px;
        border: 6px double #A4FF00;
        box-shadow: 0 0 10px rgba(164, 255, 0, 0.5);
    }

    /* Form container */
    .form-container {
        display: flex;
        justify-content: center;
        margin-bottom: 2rem;
    }

    .form-container form {
        width: 100%;
        max-width: 600px;
        display: none;
        background: #000000;
        padding: 2rem;
        border-radius: 8px;
        border: 6px double #A4FF00;
        box-shadow: 0 0 10px rgba(164, 255, 0, 0.5);
        color: #A4FF00;
    }

    /* Post grid */
    .post-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 2rem;
        padding: 2rem 0;
    }

    .post {
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: #1A1A1A;
        border-radius: 5px;
        border: 3px solid #A4FF00;
        position: relative;
    }

    /* Media styling */
    .post-media {
        max-width: 100%;
        height: auto;
        cursor: pointer;
        object-fit: contain;
        border: 2px solid #A4FF00;
    }

    .post-media.expanded {
        max-width: 100%;
        width: auto;
        height: auto;
    }

    /* Form elements */
    form input[type="text"],
    form textarea,
    form input[type="file"],
    form button {
        width: 100%;
        margin-bottom: 1rem;
        padding: 1rem;
        font-size: 1.4rem;
        background: #000000;
        color: #A4FF00;
        border: 2px solid #A4FF00;
    }

    form textarea {
        height: 15rem;
        resize: vertical;
    }

    form button {
        background: #A4FF00;
        color: #000000;
        font-weight: bold;
        border-radius: 5px;
        cursor: pointer;
    }

    form button:hover {
        background: #80FF00;
    }

    /* Buttons */
    .toggle-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
        margin-bottom: 2rem;
    }

    .toggle-buttons button,
    .pagination a,
    .reply-button {
        padding: 1rem 2rem;
        font-size: 1.4rem;
        min-width: 120px;
        background: #A4FF00;
        color: #000000;
        text-decoration: none;
        border: 2px solid #A4FF00;
        border-radius: 5px;
        cursor: pointer;
    }

    .toggle-buttons button:hover,
    .pagination a:hover,
    .reply-button:hover {
        background: #80FF00;
    }

    .toggle-buttons .close-button {
        background: #A4FF00;
        display: none;
    }

    /* Links */
    a {
        color: #A4FF00;
        text-decoration: none;
    }

    a:hover {
        background: #A4FF00;
        color: #000000;
    }

    /* Pagination */
    .pagination {
        text-align: center;
        margin-top: 2rem;
    }

    .pagination a.active {
        background: #80FF00;
        color: #000000;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .message-board {
            width: 100%;
            border-radius: 0;
            border-left: none;
            border-right: none;
            margin: 0 auto;
            padding: 1rem;
        }

        .post {
            margin-bottom: 1.5rem;
            padding: 1rem;
        }

        .post-grid {
            grid-template-columns: 1fr;
        }

        form textarea {
            height: 10rem;
        }

        .reply-button {
            position: relative;
            top: auto;
            right: auto;
            margin-top: 1rem;
            display: block;
        }
    }

    @media (max-width: 480px) {
        html {
            font-size: 55%;
        }

        .toggle-buttons button,
        .pagination a {
            width: 100%;
            margin: 0.5rem 0;
        }

        .form-container form {
            padding: 1rem;
        }
    }
</style>
</head>
<body>
    <h1>Underground</h1>
    <a href="?logout=1">Logout</a>
    <div class="message-board">
        <?php
        while ($post = $posts->fetchArray(SQLITE3_ASSOC)) {
            echo '<div class="post">';
            echo '<h2>' . htmlentities($post['title']) . '</h2>';
            echo '<p>' . nl2br(htmlentities($post['message'])) . '</p>';
            if (!empty($post['media'])) {
                echo '<p><strong>Media:</strong> <a href="' . htmlentities($post['media']) . '" target="_blank">View Media</a></p>';
            }
            echo '<form method="post" action="" onsubmit="return confirm(\'Are you sure you want to delete this post?\');">';
            echo '<input type="hidden" name="delete_post_id" value="' . $post['id'] . '">';
            echo '<button type="submit" class="delete-button">Delete Post</button>';
            echo '</form>';
            echo '<form method="post" action="" onsubmit="return confirm(\'Are you sure you want to delete this post completely?\');">';
            echo '<input type="hidden" name="delete_post_completely_id" value="' . $post['id'] . '">';
            echo '<button type="submit" class="delete-completely-button">Delete Post Completely</button>';
            echo '</form>';
            echo '</div>';

            $replies = fetchReplies($post['id'], $db);
            while ($reply = $replies->fetchArray(SQLITE3_ASSOC)) {
                echo '<div class="reply">';
                echo '<p><strong>Reply ' . $reply['id'] . ':</strong> ' . nl2br(htmlentities($reply['message'])) . '</p>';
                echo '<form method="post" action="" onsubmit="return confirm(\'Are you sure you want to delete this reply?\');">';
                echo '<input type="hidden" name="delete_reply_id" value="' . $reply['id'] . '">';
                echo '<button type="submit" class="delete-button">Delete Reply</button>';
                echo '</form>';
                echo '<form method="post" action="" onsubmit="return confirm(\'Are you sure you want to delete this reply completely?\');">';
                echo '<input type="hidden" name="delete_reply_completely_id" value="' . $reply['id'] . '">';
                echo '<button type="submit" class="delete-completely-button">Delete Reply Completely</button>';
                echo '</form>';
                echo '</div>';
            }
        }
        ?>
    </div>
</body>
</html>
