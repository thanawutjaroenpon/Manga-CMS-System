<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE username=?");
$stmt->execute([$username]);
$row = $stmt->fetch();
if (!$row || $row['role'] != 'admin') {
    echo "<div style='max-width:400px;margin:8em auto;text-align:center;font-size:1.2em;background:#fff;padding:3em 2em;border-radius:17px;box-shadow:0 6px 24px #cabcee29;'>You are not authorized to access this page.<br><a href='index.php' style='color:#4476e3;text-decoration:underline;'>Return to home</a></div>";
    exit();
}

$manga_root = __DIR__ . '/manga';
function getSeries($manga_root) {
    $series = array_filter(glob("$manga_root/*"), 'is_dir');
    sort($series);
    return array_map('basename', $series);
}
function getChapters($series_path) {
    $chapters = array_filter(glob("$series_path/*"), 'is_dir');
    natsort($chapters);
    return array_map('basename', $chapters);
}
function rrmdir($dir) {
    foreach(glob($dir . '/*') as $file) {
        if(is_dir($file)) rrmdir($file);
        else unlink($file);
    }
    rmdir($dir);
}

// --- Chapter Price Management ---
function getChapterPrice($series, $chapter) {
    $file = __DIR__ . "/manga/$series/$chapter/price.txt";
    if (file_exists($file)) return trim(file_get_contents($file));
    return "0";
}
$price_message = '';
if (isset($_POST['set_chapter_price'], $_POST['series'], $_POST['chapter'])) {
    $series = $_POST['series'];
    $chapter = $_POST['chapter'];
    $price = intval($_POST['price']);
    $chapter_dir = __DIR__ . "/manga/$series/$chapter";
    if (is_dir($chapter_dir)) {
        file_put_contents("$chapter_dir/price.txt", $price);
        $price_message = "<span style='color:green;'>Set price $price for $series / Chapter $chapter!</span>";
    } else {
        $price_message = "<span style='color:#b00;'>Chapter directory not found!</span>";
    }
}

// Defaults
$info = '';
$error = '';
$meta_message = '';
$add_series_message = '';
$new_series_created = null;

// Add new series
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_new_series'])) {
    $new_series_name = trim($_POST['new_series_name'] ?? '');
    $new_series_name = preg_replace('/[\/\\\\]/', '', $new_series_name); // Remove slashes
    if (!$new_series_name) {
        $add_series_message = "<span style='color:#b00;'>Series name cannot be empty.</span>";
    } else if (preg_match('/[\/\\\\]/', $new_series_name)) {
        $add_series_message = "<span style='color:#b00;'>Invalid series name.</span>";
    } else if (file_exists("$manga_root/$new_series_name")) {
        $add_series_message = "<span style='color:#b00;'>Series already exists!</span>";
    } else {
        mkdir("$manga_root/$new_series_name", 0777, true);
        file_put_contents("$manga_root/$new_series_name/description.txt", "");
        file_put_contents("$manga_root/$new_series_name/author.txt", "");
        file_put_contents("$manga_root/$new_series_name/status.txt", "");
        file_put_contents("$manga_root/$new_series_name/genre.txt", "");
        $add_series_message = "<span style='color:green;'>Series added!</span>";
        $new_series_created = $new_series_name;
    }
}

// -- Main actions (skip if we just added a new series and will reload anyway)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($new_series_created)) {
    // Rename Series
    if (isset($_POST['rename_series'], $_POST['series'], $_POST['new_series_name'])) {
        $old = "$manga_root/" . $_POST['series'];
        $new = "$manga_root/" . $_POST['new_series_name'];
        if (!file_exists($new) && file_exists($old)) {
            rename($old, $new);
            $info = "Series renamed.";
        } else {
            $error = "Failed to rename. Maybe new name already exists?";
        }
    }
    // Delete Series (with JS confirmation, see below)
    if (isset($_POST['delete_series'], $_POST['series'])) {
        $del = "$manga_root/" . $_POST['series'];
        if (file_exists($del)) {
            rrmdir($del);
            $info = "Series deleted.";
        } else {
            $error = "Series not found.";
        }
    }
    // Rename Chapter
    if (isset($_POST['rename_chapter'], $_POST['series'], $_POST['chapter'], $_POST['new_chapter_name'])) {
        $old = "$manga_root/" . $_POST['series'] . '/' . $_POST['chapter'];
        $new = "$manga_root/" . $_POST['series'] . '/' . $_POST['new_chapter_name'];
        if (!file_exists($new) && file_exists($old)) {
            rename($old, $new);
            $info = "Chapter renamed.";
        } else {
            $error = "Failed to rename chapter. Maybe new name exists?";
        }
    }
    // Delete Chapter
    if (isset($_POST['delete_chapter'], $_POST['series'], $_POST['chapter'])) {
        $del = "$manga_root/" . $_POST['series'] . '/' . $_POST['chapter'];
        if (file_exists($del)) {
            rrmdir($del);
            $info = "Chapter deleted.";
        } else {
            $error = "Chapter not found.";
        }
    }
    // UPLOAD images (existing/new series and chapter)
    if (isset($_FILES['images'])) {
        $series = $_POST['series_select'] ?? '';
        $chapter = trim($_POST['chapter'] ?? '');
        if (!$series || !$chapter) {
            $error = "Series and chapter are required.";
        } else {
            $target_dir = "$manga_root/$series/$chapter";
            if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
            $uploaded = 0;
            foreach ($_FILES['images']['tmp_name'] as $idx => $tmp_name) {
                $orig_name = basename($_FILES['images']['name'][$idx]);
                $target_path = "$target_dir/$orig_name";
                $type = mime_content_type($tmp_name);
                if (in_array($type, ['image/jpeg', 'image/png', 'image/webp'])) {
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $uploaded++;
                    }
                }
            }
            if ($uploaded > 0) {
                $info = "Uploaded $uploaded image(s) to <strong>$series/$chapter</strong>.";
            } else {
                $error = "No valid images uploaded.";
            }
        }
    }
    // Handle Description/Thumbnail/Extra Info Update
    if (isset($_POST['edit_series_meta']) && isset($_POST['series_name'])) {
        $series = $_POST['series_name'];
        $desc = $_POST['description'] ?? '';
        $author = $_POST['author'] ?? '';
        $status = $_POST['status'] ?? '';
        $genre = $_POST['genre'] ?? '';
        file_put_contents("$manga_root/$series/description.txt", $desc);
        file_put_contents("$manga_root/$series/author.txt", $author);
        file_put_contents("$manga_root/$series/status.txt", $status);
        file_put_contents("$manga_root/$series/genre.txt", $genre);

        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['tmp_name']) {
            $infoi = getimagesize($_FILES['thumbnail']['tmp_name']);
            $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
            if ($infoi && isset($allowed[$infoi['mime']])) {
                $ext = $allowed[$infoi['mime']];
                $target = "$manga_root/$series/cover.$ext";
                foreach (glob("$manga_root/$series/cover.*") as $old) unlink($old);
                move_uploaded_file($_FILES['thumbnail']['tmp_name'], $target);
            }
        }
        $meta_message = "Series info updated!";
    }
}

$series_list = getSeries($manga_root);
if ($new_series_created) {
    $current_series = $new_series_created;
    header("Location: admin.php?series=" . urlencode($current_series));
    exit();
} else {
    $current_series = $_GET['series'] ?? ($series_list[0] ?? null);
}
$chapter_list = $current_series ? getChapters("$manga_root/$current_series") : [];
$current_desc = $current_author = $current_status = $current_genre = "";
$current_thumb = "";
if ($current_series && file_exists("$manga_root/$current_series/description.txt"))
    $current_desc = file_get_contents("$manga_root/$current_series/description.txt");
if ($current_series && file_exists("$manga_root/$current_series/author.txt"))
    $current_author = file_get_contents("$manga_root/$current_series/author.txt");
if ($current_series && file_exists("$manga_root/$current_series/status.txt"))
    $current_status = file_get_contents("$manga_root/$current_series/status.txt");
if ($current_series && file_exists("$manga_root/$current_series/genre.txt"))
    $current_genre = file_get_contents("$manga_root/$current_series/genre.txt");
$cover_glob = glob("$manga_root/$current_series/cover.*");
if ($cover_glob) $current_thumb = "manga/" . rawurlencode($current_series) . "/" . basename($cover_glob[0]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>MangaCMS Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
/* your original CSS is unchanged */
body { font-family:sans-serif; background:#f3f4f9; margin:0;}
.topbar {
    width: 100%; background: #fff; border-bottom: 1.5px solid #e0e3eb;
    padding: 0 2vw; height: 62px; display: flex; align-items: center; justify-content: space-between;
    box-sizing: border-box; position: sticky; top: 0; z-index: 10;
}
.topbar-left { display: flex; align-items: center; gap: 1.5rem; }
.logo { font-size: 1.45em; font-weight: 800; color: #202041; display: flex; align-items: center; }
.logo img { height: 1.13em; margin-right: 0.4em; margin-top: -3px; }
.nav-link { color: #3258ae; background: #e8f0ff; font-weight: 600; font-size: 1.08em; border-radius: 8px;
    text-decoration: none; padding: 0.3em 1.1em; margin-left: 1em; transition: background .14s;}
.nav-link:hover { background: #d1e0ff; }
.avatar { width: 34px; height: 34px; border-radius: 50%; background: #ececec; object-fit: cover; margin-left: 0.5em; }
.user-info-badge {
    font-size: 1em; color: #3258ae; margin-left: 1.3em; margin-right: 0.7em; font-weight: 500; letter-spacing: .01em;
    background: #e7eef9; padding: .26em .95em; border-radius: 12px;
}
.container { max-width:970px; margin:2em auto;}
.card { background:#fff; border-radius:15px; box-shadow:0 1px 18px #acabbc24; padding:2.2em 1.8em 2em 1.8em; margin-bottom:2em;}
h2 {margin-top:0;}
.btn {background:#3258ae;color:#fff;font-size:1em;padding:.42em 1.7em;border:none;border-radius:7px;font-weight:600;cursor:pointer;}
.btn:hover{background:#21346c;}
.alert {padding:10px 15px;margin-bottom:18px;border-radius:6px;background:#e8f8f3;color:#2e6652;font-weight:500;}
input[type="text"], select, input[type="file"], textarea {
    border: 1.1px solid #b5c5db;
    border-radius: 7px;
    padding: 0.39em 0.9em;
    font-size: 1em;
    background: #f7f7fc;
    color: #202041;
    margin-bottom: 0.5em;
    width: 100%;
    box-sizing: border-box;
}
input[type="text"]:focus, select:focus, textarea:focus { border: 1.6px solid #3258ae; outline: none; }
.chapter-actions button, .chapter-actions input[type="text"], .chapter-actions input[type="number"] {
    display:inline-block; margin:0 0.2em 0 0;
}
.chapter-actions input[type="text"] {
    width: 90px; font-size: 1em; padding: 5px 9px; margin-right: .35em;
}
.chapter-actions input[type="number"] {
    width: 65px; font-size: 1em; padding: 5px 6px; margin-right: .35em; border-radius: 6px; border:1.1px solid #b5c5db;
}
.chapter-actions button {
    font-size: 1em; border-radius: 9px; border: none; padding: 0.27em 1.1em; font-weight: 600; cursor: pointer;
}
.chapter-actions .rename-btn { background:#ff3147;color:#fff; }
.chapter-actions .delete-btn { background:#23213b;color:#ffd94c; }
.chapter-actions .rename-btn:hover { background:#ba2034; }
.chapter-actions .delete-btn:hover { background:#18161f; color:#fff; }
.chapter-actions .setprice-btn { background:#6857db;color:#fff;}
.chapter-actions .setprice-btn:hover { background:#423293;}
.add-series-form {
    display:flex;align-items:center;gap:0.7em;margin-bottom:1.5em;
    background:#f7f8fd;padding:.9em 1.1em;border-radius:12px;
}
.add-series-form input[type="text"] { width:200px; margin-bottom:0;}
.add-series-form button { background:#27ae60;color:#fff;font-weight:700;border-radius:7px;padding:.43em 1.2em;border:none;font-size:1em;}
.add-series-form button:hover { background:#208d4a; }
.add-series-form label {font-weight:600;margin-right:.7em;}
.dragdrop-wrap {
    border:2px dashed #b4bef5; background:#f7f8fd; border-radius:13px; padding:1.1em 1em; min-height:120px;
    display:flex; align-items:center; justify-content:center; flex-direction:column; gap:1.2em;
    transition:border-color .18s, background .18s;
}
.dragdrop-wrap.dragover { border-color:#3258ae; background:#eaf2fc;}
.dragdrop-btn { background:#3258ae;color:#fff;padding:.43em 1.4em;border-radius:8px;border:none;font-weight:600;font-size:1.05em;cursor:pointer;}
.dragdrop-btn:hover { background:#21346c; }
.dragdrop-files-preview { display:flex; flex-wrap:wrap; gap:13px; margin-top:.6em;}
.dragdrop-thumb { width:68px; height:68px; object-fit:cover; border-radius:9px; border:1.1px solid #c5d0f5;}
.dragdrop-progress { width:100%; height:7px; border-radius:4px; background:#e0e2f3; margin-top:.8em;}
.dragdrop-bar { display:block; height:100%; background:#3258ae; border-radius:4px; transition:width .26s; }
.del-series-modal {
    position:fixed; left:0; top:0; width:100vw; height:100vh; background:rgba(44,44,60,0.29); z-index:1000;
    display:none; align-items:center; justify-content:center;
}
.del-series-modal.active { display:flex; }
.del-series-content {
    background:#fff; border-radius:18px; max-width:350px; padding:2em 1.5em 1.6em 1.5em; text-align:center; box-shadow:0 4px 24px #453dbe22;
}
.del-series-content input {width:180px;text-align:center;}
.del-series-content .warn {color:#e94d3c;font-weight:700;}
.del-series-content button {margin-top:1em;}
@media (max-width: 700px) {.container{padding:0 2vw;}}
</style>
</head>
<body>
<div class="topbar">
  <div class="topbar-left">
    <span class="logo">
      <img src="https://img.icons8.com/color/40/000000/book.png" alt="logo">
      MangaCMS Admin
    </span>
    <a href="index.php" class="nav-link">&#8592; Back to Home</a>
  </div>
  <div class="topbar-right">
    <span class="user-info-badge"><?=htmlspecialchars($username)?> (admin)</span>
    <a href="logout.php" class="nav-link">Logout</a>
    <img src="https://api.dicebear.com/7.x/pixel-art/svg?seed=<?=urlencode($username)?>" alt="Profile" class="avatar">
  </div>
</div>
<div class="container">
    <div class="card" style="margin-bottom:2em;">
        <h2 style="margin-bottom:.7em;">Manage Series & Chapters</h2>
        <!-- Add New Series -->
        <form method="POST" class="add-series-form" autocomplete="off">
            <label for="new_series_name">Add New Series:</label>
            <input type="text" name="new_series_name" id="new_series_name" maxlength="64" placeholder="New series name" required>
            <button type="submit" name="add_new_series">Add</button>
            <?= $add_series_message ?>
        </form>
        <?php if($meta_message) echo "<div class='alert'>$meta_message</div>"; ?>
        <?php if ($info): ?>
            <div class="alert" style="background:#f7f7db;color:#c59407;"><?= $info ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert" style="background:#faeaea;color:#c94d2f;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($price_message): ?>
            <div class="alert" style="background:#e7ffe7;color:#207c41;"><?= $price_message ?></div>
        <?php endif; ?>
        <form method="post" enctype="multipart/form-data" style="margin-bottom:2em;">
            <div style="display:flex;align-items:center;gap:1.2em;flex-wrap:wrap;">
                <label style="flex:1;min-width:180px;">
                    <span style="font-weight:600;">Series:</span>
                    <select name="series_name" onchange="window.location='admin.php?series='+encodeURIComponent(this.value)" style="margin-top:4px;width:100%;">
                        <?php foreach($series_list as $ser): ?>
                            <option value="<?=htmlspecialchars($ser)?>" <?=$ser==$current_series?'selected':''?>><?=htmlspecialchars($ser)?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if($current_thumb): ?>
                    <img src="<?=$current_thumb?>" style="height:75px;border-radius:8px;box-shadow:0 1px 6px #aaa4;">
                <?php endif; ?>
                <!-- Series Delete -->
                <?php if($current_series): ?>
                    <button type="button" onclick="showDelSeries('<?=htmlspecialchars($current_series,ENT_QUOTES)?>')" style="background:#e94d3c;color:#fff;border:none;border-radius:8px;padding:.45em 1.1em;margin-left:1.2em;font-weight:700;cursor:pointer;">Delete Series</button>
                <?php endif; ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:1em 2em;">
                <label style="margin-top:1em;flex:1 1 250px;">Description:
                    <textarea name="description" rows="4" placeholder="Write manga synopsis..."><?=htmlspecialchars($current_desc)?></textarea>
                </label>
                <div style="flex:1 1 180px;">
                    <label>Author:<br>
                        <input type="text" name="author" value="<?=htmlspecialchars($current_author)?>" maxlength="100">
                    </label><br>
                    <label>Status:<br>
                        <select name="status">
                            <option value="Ongoing" <?=$current_status=="Ongoing"?"selected":""?>>Ongoing</option>
                            <option value="Completed" <?=$current_status=="Completed"?"selected":""?>>Completed</option>
                        </select>
                    </label><br>
                    <label>Genres/Tags:<br>
                        <input type="text" name="genre" value="<?=htmlspecialchars($current_genre)?>" maxlength="150" placeholder="comma, separated, genres">
                    </label><br>
                    <label>Thumbnail (cover): 
                        <input type="file" name="thumbnail" accept="image/png,image/jpeg,image/webp" style="margin-top:4px;">
                    </label>
                </div>
            </div>
            <button class="btn" type="submit" name="edit_series_meta" style="margin-top:.8em;">Save Info</button>
        </form>
        <!-- Chapter List + DragDrop Upload -->
        <div style="background:#f7f8fd;border-radius:17px;padding:1.1em 1.3em 1.7em 1.3em;">
            <div style="font-weight:700;font-size:1.13em;margin-bottom:1em;letter-spacing:.01em;">
                Chapters for <span style="color:#3258ae;"><?=htmlspecialchars($current_series)?></span>
            </div>
            <ul style="list-style:none;padding:0;margin:0;">
                <?php foreach($chapter_list as $chap): ?>
                <li style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1em;">
                    <div>
                        <span style="font-weight:600;">Chapter <?=htmlspecialchars($chap)?></span>
                    </div>
                    <form method="post" class="chapter-actions" style="display:flex;align-items:center;gap:.6em;margin:0;">
                        <input type="hidden" name="series" value="<?=htmlspecialchars($current_series)?>">
                        <input type="hidden" name="chapter" value="<?=htmlspecialchars($chap)?>">
                        <input type="text" name="new_chapter_name" placeholder="Rename" >
                        <button name="rename_chapter" class="rename-btn">Rename</button>
                        <button name="delete_chapter" class="delete-btn" onclick="return confirm('Delete this chapter? This cannot be undone.');">Delete</button>
                    </form>
                    <!-- Chapter Price Form (NEW) -->
                    <form method="post" class="chapter-actions" style="display:flex;align-items:center;gap:.3em;margin:0 0 0 1.1em;">
                        <input type="hidden" name="series" value="<?=htmlspecialchars($current_series)?>">
                        <input type="hidden" name="chapter" value="<?=htmlspecialchars($chap)?>">
                        <input type="number" name="price" min="0" value="<?=htmlspecialchars(getChapterPrice($current_series, $chap))?>" class="chapter-price-input" style="width:65px;" required>
                        <button type="submit" name="set_chapter_price" class="setprice-btn">💰 Set Price</button>
                        <span style="font-size:.99em;color:#616186;">(Now: <?=htmlspecialchars(getChapterPrice($current_series, $chap))?>)</span>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <hr style="border:0;border-top:1px solid #dde3ee;margin:1.8em 0 1.3em 0;">
            <!-- DragDrop Upload -->
            <form id="dragdropForm" method="POST" enctype="multipart/form-data" style="margin-bottom:0;">
                <input type="hidden" name="series_select" value="<?=htmlspecialchars($current_series)?>">
                <div style="display:flex;flex-wrap:wrap;gap:1.4em;align-items:end;">
                    <div>
                        <label style="font-weight:600;">Add Chapter:</label><br>
                        <input type="text" name="chapter" id="chapterInput" placeholder="e.g. 1 or 02" required>
                    </div>
                    <div style="flex:1;">
                        <label style="font-weight:600;">Images:</label><br>
                        <div id="dragdrop" class="dragdrop-wrap">
                            <span>Drag &amp; drop images here or <button type="button" class="dragdrop-btn" onclick="document.getElementById('imagesInput').click()">Browse</button></span>
                            <input id="imagesInput" name="images[]" type="file" accept="image/png,image/jpeg,image/webp" multiple style="display:none;">
                            <div class="dragdrop-files-preview" id="dragdropPreview"></div>
                            <div class="dragdrop-progress" id="dragdropProgress" style="display:none;"><span class="dragdrop-bar" style="width:0%;"></span></div>
                        </div>
                    </div>
                    <button type="submit" style="background:#3258ae;color:#fff;padding:0.43em 1.4em;border-radius:8px;border:none;font-weight:600;">Upload Images</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- DELETE SERIES MODAL -->
<div class="del-series-modal" id="delSeriesModal">
  <div class="del-series-content">
    <div style="font-size:1.19em; font-weight:700; margin-bottom:.8em;">
      Delete Series: <span id="delSeriesName" style="color:#b52c2c;"></span>
    </div>
    <div class="warn" style="margin-bottom:.9em;">This will delete ALL chapters & images.<br>Type the name below to confirm:</div>
    <form method="post">
      <input type="hidden" name="series" id="delSeriesInputName">
      <input type="text" id="delSeriesConfirm" placeholder="Series name" autocomplete="off"><br>
      <button type="button" class="btn" onclick="closeDelSeries()" style="background:#eee;color:#233;margin-right:.8em;">Cancel</button>
      <button type="submit" name="delete_series" class="btn" id="delSeriesSubmit" style="background:#e94d3c;" disabled>Delete</button>
    </form>
  </div>
</div>

<script>
// Drag & Drop upload logic
const dragdrop = document.getElementById("dragdrop");
const imagesInput = document.getElementById("imagesInput");
const dragdropPreview = document.getElementById("dragdropPreview");
const dragdropForm = document.getElementById("dragdropForm");
const dragdropProgress = document.getElementById("dragdropProgress");
const dragdropBar = dragdropProgress ? dragdropProgress.querySelector(".dragdrop-bar") : null;

if (dragdrop && imagesInput && dragdropPreview) {
    dragdrop.addEventListener("dragover", e=>{
        e.preventDefault(); dragdrop.classList.add("dragover");
    });
    dragdrop.addEventListener("dragleave", e=>{
        e.preventDefault(); dragdrop.classList.remove("dragover");
    });
    dragdrop.addEventListener("drop", e=>{
        e.preventDefault(); dragdrop.classList.remove("dragover");
        imagesInput.files = e.dataTransfer.files;
        updatePreview();
    });
    imagesInput.addEventListener("change", updatePreview);

    function updatePreview() {
        dragdropPreview.innerHTML = "";
        for(let file of imagesInput.files) {
            if(file.type.startsWith("image/")) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    let img = document.createElement("img");
                    img.src = e.target.result;
                    img.className = "dragdrop-thumb";
                    dragdropPreview.appendChild(img);
                };
                reader.readAsDataURL(file);
            }
        }
    }
    dragdropForm.addEventListener("submit", function(e){
        if(imagesInput.files.length === 0) {
            alert("Please select image files to upload.");
            e.preventDefault();
            return false;
        }
        // (No AJAX submit for now - to keep PHP simple. Can be added for true progress.)
        dragdropProgress.style.display = "block";
        dragdropBar.style.width = "90%";
    });
}

// Delete Series modal logic
let seriesNameToDelete = "";
function showDelSeries(name) {
    seriesNameToDelete = name;
    document.getElementById("delSeriesName").textContent = name;
    document.getElementById("delSeriesInputName").value = name;
    document.getElementById("delSeriesConfirm").value = "";
    document.getElementById("delSeriesSubmit").disabled = true;
    document.getElementById("delSeriesModal").classList.add("active");
    document.getElementById("delSeriesConfirm").focus();
}
function closeDelSeries() {
    document.getElementById("delSeriesModal").classList.remove("active");
}
document.getElementById("delSeriesConfirm")?.addEventListener("input", function(){
    document.getElementById("delSeriesSubmit").disabled = (this.value !== seriesNameToDelete);
});
document.getElementById("delSeriesModal")?.addEventListener("click", function(e){
    if(e.target === this) closeDelSeries();
});
</script>
</body>
</html>
