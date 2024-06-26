<?php
// Setup SQLite3 database and uploads directory
$db = new SQLite3('message_board.db');

$db->exec('CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    message TEXT NOT NULL,
    media TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$db->exec('CREATE TABLE IF NOT EXISTS replies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    message TEXT NOT NULL,
    FOREIGN KEY(post_id) REFERENCES posts(id)
)');

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

    return '
        <div class="post">
            <hr class="green-hr">
            <div class="post-media-container">' . $mediaTag . '</div>
            <h2>' . htmlentities($title) . '</h2>
            <p style="word-wrap: break-word; overflow-wrap: break-word;">' . nl2br(htmlentities($message)) . '</p>
            <a class="reply-button" href="reply.php?post_id=' . $id . '">[reply-' . $replyCount . ']</a>
        </div>
    ';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_STRING);
    $media = $_FILES['media'];

    if (empty($title) || empty($message)) {
        die('Title and message are required.');
    }

    $mediaPath = '';
    if ($media['size'] > 0) {
        $uniqueFilename = getUniqueFilename($uploadsDir, basename($media['name']));
        $mediaPath = $uploadsDir . $uniqueFilename;
        move_uploaded_file($media['tmp_name'], $mediaPath);
    }

    $stmt = $db->prepare('INSERT INTO posts (title, message, media) VALUES (:title, :message, :media)');
    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
    $stmt->bindValue(':message', $message, SQLITE3_TEXT);
    $stmt->bindValue(':media', $mediaPath, SQLITE3_TEXT);
    $stmt->execute();

    echo renderPost($db->lastInsertRowID(), $title, $message, $mediaPath);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message Board</title>
    <style>
        /* Add basic styling here */
        body {
            background-color: #B0C4DE; /* Darker blue shade */
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }
        .message-board {
            width: 90%;
            max-width: 1200px;
            margin: auto;
            padding: 20px;
            background: #F0F0F0; /* Light grey background for posts */
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .form-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .form-container form {
            width: 100%;
            max-width: 600px;
            display: none; /* Hide the form initially */
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .post {
            margin-bottom: 20px;
            padding: 10px;
            background: #E0E0E0; /* Grey background for individual posts */
            border-radius: 5px;
            position: relative;
        }
        .green-hr {
            border: 5px solid green;
        }
        .post-media {
            width: 200px;
            height: auto;
            cursor: pointer;
            object-fit: contain;
        }
        .post-media.expanded {
            width: 100%;
            max-width: 100%;
            height: auto;
        }
        .post-media video {
            width: 100%;
            height: auto;
        }
        form input[type="text"], form textarea, form input[type="file"], form button {
            width: 100%;
            margin-bottom: 10px;
        }
        form textarea {
            height: 100px;
        }
        form button {
            padding: 10px;
            background: green;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .toggle-buttons {
            text-align: center;
            margin-bottom: 20px;
        }
        .toggle-buttons button {
            padding: 10px;
            background: green;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .toggle-buttons .close-button {
            background: red;
            display: none; /* Hide the close button initially */
        }
        .reply-button {
            position: absolute;
            top: 10px;
            right: 10px;
            background: blue;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="message-board">
        <div class="toggle-buttons">
            <button class="new-post-button">[NEW POST]</button>
            <button class="close-button">[X]</button>
        </div>
        <div class="form-container">
            <form id="postForm" enctype="multipart/form-data" method="post">
                <input type="text" name="title" placeholder="Title" maxlength="20" required><br>
                <textarea name="message" placeholder="Message" maxlength="100000" required></textarea><br>
                <input type="file" name="media" accept="image/jpeg, image/png, image/gif, image/webp, video/webm, video/mp4"><br>
                <button type="submit">Post</button>
            </form>
        </div>
        <div id="posts">
            <?php
            $result = $db->query('SELECT * FROM posts ORDER BY updated_at DESC');
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                echo renderPost($row['id'], $row['title'], $row['message'], $row['media']);
            }
            ?>
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
</body>
</html>
