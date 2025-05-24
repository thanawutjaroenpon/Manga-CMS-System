<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
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
function getPages($chapter_path) {
    $images = glob("$chapter_path/*.{jpg,jpeg,png,webp}", GLOB_BRACE);
    natsort($images);
    return array_map('basename', $images);
}
function get_meta($manga_root, $series, $file) {
    $f = "$manga_root/$series/$file";
    return file_exists($f) ? trim(file_get_contents($f)) : "";
}
function get_chapter_price($series, $chapter) {
    $file = __DIR__ . "/manga/$series/$chapter/price.txt";
    if (file_exists($file)) return max(0, intval(trim(file_get_contents($file))));
    return 0;
}

$username = $_SESSION['username'] ?? '';
$role = 'user';
$user_points = 0;
if ($username) {
    $stmt = $pdo->prepare("SELECT role, points FROM users WHERE username=?");
    $stmt->execute([$username]);
    $row = $stmt->fetch();
    if ($row) {
        $role = $row['role'];
        $user_points = intval($row['points']);
    }
}

// --- Check if user already bought chapter
function user_has_access($pdo, $username, $series, $chapter, $role) {
    if ($role === 'admin') return true;
    $stmt = $pdo->prepare("SELECT 1 FROM purchased_chapters WHERE username=? AND series=? AND chapter=?");
    $stmt->execute([$username, $series, $chapter]);
    return $stmt->fetchColumn() ? true : false;
}

// --- Handle Buy Request
$series = $_GET['series'] ?? null;
$chapter = $_GET['chapter'] ?? null;
$buy_message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['buy_chapter'], $_POST['series'], $_POST['chapter']) && $role != 'admin') {
    $buy_series = $_POST['series'];
    $buy_chapter = $_POST['chapter'];
    $price = get_chapter_price($buy_series, $buy_chapter);

    $stmt = $pdo->prepare("SELECT 1 FROM purchased_chapters WHERE username=? AND series=? AND chapter=?");
    $stmt->execute([$username, $buy_series, $buy_chapter]);
    if ($stmt->fetchColumn()) {
        $buy_message = "<span style='color:green;font-weight:600;'>Already unlocked.</span>";
    } else if ($price > $user_points) {
        $buy_message = "<span style='color:#b00;font-weight:600;'>Not enough points.</span>";
    } else {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET points = points - ? WHERE username=?")->execute([$price, $username]);
        $pdo->prepare("INSERT IGNORE INTO purchased_chapters (username, series, chapter) VALUES (?, ?, ?)")->execute([$username, $buy_series, $buy_chapter]);
        $pdo->commit();
        $buy_message = "<span style='color:green;font-weight:600;'>Unlocked!</span>";
        // Update user points
        $stmt = $pdo->prepare("SELECT points FROM users WHERE username=?");
        $stmt->execute([$username]);
        $user_points = intval($stmt->fetchColumn());
    }
}

// --- Comments Section
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'], $_SESSION['logged_in']) && $_SESSION['logged_in'] && $series && $chapter) {
    $comment = trim($_POST['comment']);
    if ($comment) {
        $user = $_SESSION['username'];
        $stmt = $pdo->prepare("INSERT INTO comments (series, chapter, username, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$series, $chapter, $user, $comment]);
        header("Location: index.php?series=".urlencode($series)."&chapter=".urlencode($chapter));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MangaCMS - Read Manga</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    /* Your original CSS is preserved here - unchanged from your upload! */
    body {
        background: #f2f4f7;
        font-family: 'Segoe UI', Arial, sans-serif;
        margin: 0;
        color: #202041;
    }
    .topbar {
        width: 100%;
        background: #fff;
        border-bottom: 1.5px solid #e0e3eb;
        padding: 0 2vw;
        height: 62px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-sizing: border-box;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    .topbar-left {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .logo {
        font-size: 1.5em;
        font-weight: 800;
        color: #202041;
        display: flex;
        align-items: center;
    }
    .logo img {
        height: 1.18em;
        margin-right: 0.4em;
        margin-top: -3px;
    }
    .nav-link {
        color: #3258ae;
        background: #e8f0ff;
        font-weight: 600;
        font-size: 1.06em;
        border-radius: 8px;
        text-decoration: none;
        padding: 0.3em 1em;
        margin-left: 1em;
        transition: background .14s;
    }
    .nav-link:hover {
        background: #d1e0ff;
    }
    .avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #ececec;
        object-fit: cover;
        margin-left: 0.5em;
    }
    .user-info-badge {
        font-size: 1em;
        color: #3258ae;
        margin-left: 1.3em;
        margin-right: 0.7em;
        font-weight: 500;
        letter-spacing: .01em;
        background: #e7eef9;
        padding: .26em .95em;
        border-radius: 12px;
    }
    .series-grid-main {
        max-width: 1200px;
        margin: 2.2em auto 0 auto;
        display: flex;
        flex-wrap: wrap;
        gap: 2.3em;
        justify-content: center;
    }
    .series-card {
        background: #fff;
        border-radius: 17px;
        box-shadow: 0 3px 18px #c7c6dc25;
        width: 220px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        text-decoration: none;
        transition: box-shadow .18s, transform .13s;
    }
    .series-card:hover {
        box-shadow: 0 9px 36px #d5e6fa56;
        transform: translateY(-7px) scale(1.022);
        z-index: 1;
    }
    .series-card-thumb {
        width: 100%;
        height: 295px;
        object-fit: cover;
        background: #d4d8e4;
        display: block;
    }
    .series-card-info {
        padding: 1.1em 1em 1.2em 1em;
    }
    .series-card-title {
        font-size: 1.17em;
        font-weight: 700;
        margin-bottom: 0.25em;
        color: #202041;
    }
    .series-card-desc {
        color: #547;
        font-size: .99em;
        opacity: 0.74;
    }
    .series-meta-row {
        margin-bottom: .5em;
        font-size: .97em;
        color: #4472a3;
        margin-top: .3em;
    }
    .series-meta-row span {
        display: inline-block;
        margin-right: .7em;
        padding: .19em .52em;
        background: #eef4fd;
        border-radius: 8px;
    }
    .series-page-main {
        max-width: 1200px;
        margin: 2.3em auto 1em auto;
        display: flex;
        gap: 2.2em;
        align-items: flex-start;
    }
    .series-info-side {
        width: 280px;
        min-width: 180px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .series-thumb {
        width: 100%;
        max-width: 260px;
        border-radius: 18px;
        box-shadow: 0 4px 28px #acabbc22;
        object-fit: cover;
        margin-bottom: 1.3em;
    }
    .series-meta-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 1px 14px #c9cbe044;
        padding: 1.35em 1em 1.25em 1em;
        width: 100%;
        margin-bottom: 1.6em;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    .series-title {
        font-size: 1.23em;
        font-weight: 700;
        margin-bottom: 0.5em;
        color: #222556;
    }
    .series-badge {
        background: #ff5d5d;
        color: #fff;
        border-radius: 19px;
        padding: 0.27em 0.9em;
        font-size: 1em;
        font-weight: 600;
        margin-right: .5em;
        letter-spacing: .01em;
    }
    .series-badge.ongoing {
        background: #34ba54;
    }
    .series-actions {
        margin-top: 1em;
    }
    .fav-btn {
        background: #b94756;
        color: #fff;
        border: none;
        border-radius: 17px;
        padding: 0.38em 1.2em;
        font-size: 1.04em;
        font-weight: 600;
        cursor: pointer;
        transition: background .12s;
    }
    .fav-btn:hover {
        background: #882e44;
    }
    .series-pub-details {
        font-size: 1em;
        color: #334e72;
        margin-top: 1.4em;
    }
    .series-chapters-side {
        flex: 1 1 0;
        min-width: 290px;
    }
    .chapter-list-title {
        font-size: 1.21em;
        font-weight: 700;
        margin-bottom: 1.3em;
        letter-spacing: 0.03em;
    }
    .chapter-list-card {
        background: #f8fafc;
        border-radius: 10px;
        box-shadow: 0 2px 16px #d4d7f044;
        padding: 1.1em 0;
    }
    .chapter-row {
        display: flex;
        align-items: center;
        padding: 1em 1.5em;
        border-bottom: 1px solid #e5e7ef;
        text-decoration: none;
        font-weight: 600;
        font-size: 1.09em;
        color: #202152;
        transition: background .12s;
        position:relative;
    }
    .chapter-row:last-child {
        border-bottom: none;
    }
    .chapter-row:hover {
        background: #e4eaff;
        color: #ff8800;
    }
    .chapter-row-icon {
        margin-right: 0.7em;
        font-size: 1.15em;
    }
    .chapter-row-title {
        flex: 1 1 0;
    }
    .back-btn {
        margin-top: 2.1em;
        display: inline-block;
        background: #23213b;
        color: #ffd94c;
        border-radius: 28px;
        padding: 0.44em 1.35em;
        font-weight: 600;
        text-decoration: none;
        letter-spacing: .04em;
        transition: background .13s;
        font-size: 1.07em;
        margin-bottom: 1.5em;
        box-shadow: 0 2px 13px #12091016;
    }
    .back-btn:hover {
        background: #29264a;
        color: #fff;
    }
    .manga-pages-centered {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1.8em;
        margin: 2.7em 0 3em 0;
    }
    .manga-img {
        display: block;
        margin: 0 auto 2em auto;
        width: 100%;
        max-width: 900px;
        border-radius: 18px;
        box-shadow: 0 8px 40px #190f3333;
        background: #23213b;
        padding: 0.1em;
        height: auto;
        object-fit: contain;
    }
    @media (max-width: 900px) {
      .series-page-main { flex-direction: column; align-items:center; }
      .series-info-side { width:100%; max-width:330px; margin:0 auto 1.7em auto; }
      .series-chapters-side { width:100%; }
      .series-grid-main {gap:1.5em;}
    }
    @media (max-width: 600px) {
        .topbar { padding:0 0.3em;}
        .series-page-main {margin:1em 0;}
        .series-card {width:98vw;}
        .series-card-thumb {height:37vw;min-height:150px;}
        .series-thumb {max-width:99vw;}
        .chapter-list-card {padding:0.1em 0;}
    }
    .comments-section {
        background: #fff;
        border-radius: 13px;
        box-shadow: 0 2px 10px #d2c7ec2a;
        padding: 2em 1.4em 1.7em 1.4em;
        margin-bottom: 3em;
        margin-top: 2em;
    }
    .comments-section h3 {
        margin-top:0;
        margin-bottom:1.3em;
        font-size:1.15em;
        color:#3258ae;
    }
    .comment-entry {
        background:#f7f7fa;
        padding:1em;
        margin-bottom:.7em;
        border-radius:8px;
    }
    .comment-entry .meta {
        color:#aab;
        font-size:.93em;
        margin-left:6px;
    }
    .comment-entry .username {
        color:#3258ae;
        font-weight:bold;
    }
    .comments-section textarea {
        width:100%;
        padding:0.8em;
        border-radius:7px;
        border:1px solid #b5c5db;
        font-size:1.07em;
        margin-bottom:.7em;
        background:#f7f7fc;
        resize:vertical;
    }
    .comments-section button {
        background:#3258ae;
        color:#fff;
        border:none;
        padding:.44em 1.4em;
        border-radius:7px;
        cursor:pointer;
        font-weight:600;
        font-size:1em;
    }
    .comments-section button:hover { background:#21346c; }
    .meta-row { margin:.5em 0 0.6em 0; font-size:.98em; color:#4472a3;}
    .meta-row span {display:inline-block; margin-right:.7em; padding:.18em .54em; background:#eef4fd; border-radius:8px;}
    .floating-chapter-bar {
      position: fixed;
      left: 20px;
      bottom: 24px;
      display: flex;
      flex-direction: row;
      background: #38384a;
      border-radius: 13px;
      box-shadow: 0 3px 18px #0003;
      z-index: 1002;
      align-items: center;
      overflow: hidden;
      min-width: 142px;
      min-height: 48px;
      transition: bottom 0.2s, left 0.2s, opacity 0.18s, transform 0.19s;
      opacity: 1;
      cursor: pointer;
    }
    .floating-chapter-bar.hide {
      opacity: 0;
      pointer-events: none;
      transform: translateY(30px) scale(.93);
    }
    .floating-chapter-bar.collapsed .fc-btn:not(.menu),
    .floating-chapter-bar.collapsed .fc-divider {
      display: none;
    }
    .floating-chapter-bar .fc-btn {
      color: #fff;
      background: none;
      border: none;
      font-size: 1.7em;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0 19px;
      height: 48px;
      text-decoration: none;
      transition: background .13s, color .13s;
      cursor: pointer;
      outline: none;
    }
    .floating-chapter-bar .fc-btn.menu {
      background: #ff8200;
      color: #fff;
      border-top-left-radius: 13px;
      border-bottom-left-radius: 13px;
      font-size: 1.45em;
      padding: 0 16px;
      border-right: 1.5px solid #444;
    }
    .floating-chapter-bar .fc-btn.menu:hover {
      background: #ffa13d;
    }
    .floating-chapter-bar .fc-btn:hover:not(.fc-disabled) {
      background: #48485a;
      color: #ffd94c;
    }
    .floating-chapter-bar .fc-divider {
      width: 2px;
      height: 28px;
      background: #222c;
      margin: 0 3px;
      border-radius: 4px;
    }
    .floating-chapter-bar .fc-disabled {
      color: #bbb !important;
      opacity: .45 !important;
      cursor: not-allowed;
      pointer-events: none;
      background: none !important;
    }
    @media (max-width:600px) {
      .floating-chapter-bar {left: 7px; bottom: 10px; min-width: 66px; min-height: 38px;}
      .floating-chapter-bar .fc-btn {font-size:1.15em; padding:0 10px; height:38px;}
      .floating-chapter-bar .fc-btn.menu {padding:0 8px;}
      .floating-chapter-bar .fc-divider {height:18px;}
    }
    </style>
</head>
<body>
<!-- Top Navigation -->
<div class="topbar">
  <div class="topbar-left">
    <span class="logo">
      <img src="https://img.icons8.com/color/40/000000/book.png" alt="logo">
      MangaCMS
    </span>
    <a href="index.php" class="nav-link">Contents</a>
    <?php if($role === 'admin'): ?>
      <a href="admin.php" class="nav-link">Admin Panel</a>
      <a href="admin_point.php" class="nav-link">Edit Points</a>
    <?php endif; ?>
  </div>
  <div class="topbar-right">
    <span class="user-info-badge"><?=htmlspecialchars($username)?> (<?=$role?>) | Points: <?=$user_points?></span>
    <a href="logout.php" class="nav-link">Logout</a>
    <img src="https://api.dicebear.com/7.x/pixel-art/svg?seed=<?=urlencode($username)?>" alt="Profile" class="avatar">
  </div>
</div>

<?php if(!$series && !$chapter): ?>
  <h2 style="text-align:center; margin-top:2.2em; font-size:2.2em; font-weight:900; letter-spacing:.01em;">
    Explore Manga Library
  </h2>
  <div class="series-grid-main">
    <?php
    foreach(getSeries($manga_root) as $s):
        $cover_candidates = glob("$manga_root/$s/cover.*");
        if ($cover_candidates) {
            $cover = "manga/" . rawurlencode($s) . "/" . basename($cover_candidates[0]);
        } else {
            $chapters = getChapters("$manga_root/$s");
            $cover = '';
            if ($chapters) {
                $pages = getPages("$manga_root/$s/" . $chapters[0]);
                if ($pages) $cover = "manga/" . rawurlencode($s) . "/" . rawurlencode($chapters[0]) . "/" . rawurlencode($pages[0]);
            }
            if (!$cover) $cover = "https://img.icons8.com/fluency/256/book-shelf.png";
        }
        $author = get_meta($manga_root, $s, 'author.txt');
        $status = get_meta($manga_root, $s, 'status.txt');
        $genre  = get_meta($manga_root, $s, 'genre.txt');
    ?>
      <a href="?series=<?= urlencode($s) ?>" class="series-card">
        <img src="<?= htmlspecialchars($cover) ?>" alt="Cover" class="series-card-thumb">
        <div class="series-card-info">
          <div class="series-card-title"><?= htmlspecialchars($s) ?></div>
          <div class="series-meta-row">
            <?php if ($author): ?><span>✍️ <?=htmlspecialchars($author)?></span><?php endif; ?>
            <?php if ($status): ?><span><?=htmlspecialchars($status)?></span><?php endif; ?>
            <?php if ($genre): ?>
              <?php foreach(explode(',', $genre) as $g): ?>
                <span><?=htmlspecialchars(trim($g))?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <div class="series-card-desc">Read all chapters</div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>
<?php elseif($series && !$chapter): ?>
  <?php
    $descfile = "$manga_root/$series/description.txt";
    $description = file_exists($descfile) ? file_get_contents($descfile) : '';
    $cover_candidates = glob("$manga_root/$series/cover.*");
    if ($cover_candidates) {
        $cover = "manga/" . rawurlencode($series) . "/" . basename($cover_candidates[0]);
    } else {
        $chapters = getChapters("$manga_root/$series");
        $cover = '';
        if ($chapters) {
            $pages = getPages("$manga_root/$series/" . $chapters[0]);
            if ($pages) $cover = "manga/" . rawurlencode($series) . "/" . rawurlencode($chapters[0]) . "/" . rawurlencode($pages[0]);
        }
        if (!$cover) $cover = "https://img.icons8.com/fluency/256/book-shelf.png";
    }
    $author = get_meta($manga_root, $series, 'author.txt');
    $status = get_meta($manga_root, $series, 'status.txt');
    $genre  = get_meta($manga_root, $series, 'genre.txt');
  ?>
  <a href="index.php" class="back-btn">&larr; Back to Library</a>
  <div class="series-page-main">
    <div class="series-info-side">
      <img src="<?= htmlspecialchars($cover) ?>" alt="Cover" class="series-thumb">
      <div class="series-meta-card">
        <div class="series-title"><?= htmlspecialchars($series) ?></div>
        <div class="meta-row">
          <?php if ($author): ?><span>✍️ <?=htmlspecialchars($author)?></span><?php endif; ?>
          <?php if ($status): ?><span><?=htmlspecialchars($status)?></span><?php endif; ?>
          <?php if ($genre): ?>
            <?php foreach(explode(',', $genre) as $g): ?>
                <span><?=htmlspecialchars(trim($g))?></span>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <div style="margin-top:0.8em;">
          <span class="series-badge">Manga</span>
          <span class="series-badge ongoing"><?= $status ? htmlspecialchars($status) : 'Ongoing' ?></span>
        </div>
        <?php if(trim($description)): ?>
        <div style="margin-top:1.1em;font-size:1.03em; color:#374361;"><?=nl2br(htmlspecialchars($description))?></div>
        <?php endif; ?>
        <div class="series-actions">
          <button class="fav-btn">☆ Favorite</button>
        </div>
        <div class="series-pub-details">
          <b>Total Chapter:</b> <?= count(getChapters("$manga_root/$series")) ?>
        </div>
      </div>
    </div>
    <div class="series-chapters-side">
      <h2 class="chapter-list-title"><span style="color:orange">Chapter</span> List</h2>
      <div class="chapter-list-card">
<?php foreach(getChapters("$manga_root/$series") as $c):
    $chapter_price = get_chapter_price($series, $c);
    $is_unlocked = user_has_access($pdo, $username, $series, $c, $role);
?>
    <?php if($chapter_price > 0 && !$is_unlocked): ?>
        <div class="chapter-row" style="opacity:0.68;cursor:not-allowed;" title="Locked">
            <span class="chapter-row-icon">🔒</span>
            <span class="chapter-row-title">
                Chapter <?= htmlspecialchars($c) ?>
                <span style="font-size:0.92em;color:#b74;">(<?=$chapter_price?> pts to unlock)</span>
                <form method="post" action="?series=<?=urlencode($series)?>&chapter=<?=urlencode($c)?>" style="display:inline;margin-left:9px;"
                    onsubmit="return confirmBuyChapter(this, '<?=htmlspecialchars($c)?>', '<?=$chapter_price?>');">
                    <input type="hidden" name="series" value="<?=htmlspecialchars($series)?>">
                    <input type="hidden" name="chapter" value="<?=htmlspecialchars($c)?>">
                    <button type="submit" name="buy_chapter" style="margin-left:7px;padding:2px 13px;background:#46bb54;color:#fff;border:none;border-radius:7px;cursor:pointer;font-size:0.97em;font-weight:600;">Buy</button>
                </form>
            </span>
        </div>
    <?php else: ?>
        <a href="?series=<?= urlencode($series) ?>&chapter=<?= urlencode($c) ?>" class="chapter-row">
            <span class="chapter-row-icon"><?= $chapter_price > 0 ? "🔓" : "📖" ?></span>
            <span class="chapter-row-title">
                Chapter <?= htmlspecialchars($c) ?>
                <?php if($chapter_price > 0): ?>
                    <span style="font-size:0.92em;color:#b74;">(<?=$chapter_price?> pts)</span>
                <?php endif; ?>
            </span>
        </a>
    <?php endif; ?>
<?php endforeach; ?>
</div>

    </div>
  </div>
<?php elseif($series && $chapter): ?>
  <?php
    $author = get_meta($manga_root, $series, 'author.txt');
    $status = get_meta($manga_root, $series, 'status.txt');
    $genre  = get_meta($manga_root, $series, 'genre.txt');
    $pages = getPages("$manga_root/$series/$chapter");
    $chapter_list = getChapters("$manga_root/$series");
    $chapter_list = array_values($chapter_list);
    $curr_index = array_search($chapter, $chapter_list);
    $prev_chapter = false;
    $next_chapter = false;
    if ($curr_index !== false && isset($chapter_list[$curr_index-1])) $prev_chapter = $chapter_list[$curr_index-1];
    if ($curr_index !== false && isset($chapter_list[$curr_index+1])) $next_chapter = $chapter_list[$curr_index+1];
    $chapter_price = get_chapter_price($series, $chapter);
    $is_unlocked = user_has_access($pdo, $username, $series, $chapter, $role);
  ?>
  <a href="?series=<?= urlencode($series) ?>" class="back-btn">&larr; Back to Chapters</a>
  <h2 style="text-align:center;margin-top:1.2em;color:#202041;font-size:1.37em;font-weight:800;">
    <?= htmlspecialchars($series) ?> / Chapter <?= htmlspecialchars($chapter) ?>
  </h2>
  <div class="meta-row" style="text-align:center;margin-bottom:1.5em;">
    <?php if ($author): ?><span>✍️ <?=htmlspecialchars($author)?></span><?php endif; ?>
    <?php if ($status): ?><span><?=htmlspecialchars($status)?></span><?php endif; ?>
    <?php if ($genre): ?>
      <?php foreach(explode(',', $genre) as $g): ?>
          <span><?=htmlspecialchars(trim($g))?></span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php if($chapter_price > 0 && !$is_unlocked && $role !== 'admin'): ?>
      <div style="max-width:520px;margin:2.8em auto 2.6em auto;padding:2em 1.5em;background:#fff9e7;border:1.5px solid #f8e09d;border-radius:15px;text-align:center;">
        <div style="font-size:2.2em;line-height:1em;">🔒</div>
        <div style="font-size:1.2em;margin:.7em 0 1.1em 0;color:#b84b09;">This chapter is locked</div>
        <div style="font-size:1.09em;color:#784;font-weight:600;margin-bottom:1em;">Price: <b><?=$chapter_price?> points</b></div>
        <?php if ($buy_message): ?>
            <div style="font-weight:600;margin-bottom:1em;"><?=$buy_message?></div>
        <?php endif; ?>
        <?php if ($user_points >= $chapter_price): ?>
            <form method="post" onsubmit="return confirmBuyChapter(this, '<?=htmlspecialchars($chapter)?>', '<?=$chapter_price?>');">
                <input type="hidden" name="series" value="<?=htmlspecialchars($series)?>">
                <input type="hidden" name="chapter" value="<?=htmlspecialchars($chapter)?>">
                <button type="submit" name="buy_chapter" style="background:#46bb54;color:#fff;padding:0.45em 1.6em;font-size:1.17em;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Unlock for <?=$chapter_price?> points</button>
            </form>
        <?php else: ?>
            <div style="color:#b00;font-weight:600;">You don't have enough points.</div>
        <?php endif; ?>
        <a href="?series=<?=urlencode($series)?>" style="color:#6857db;text-decoration:underline;display:block;margin-top:2em;">&larr; Back to chapters</a>
      </div>
  <?php else: ?>
      <div class="manga-pages-centered">
        <?php
          if (!$pages) echo "<p style='color:#e8e1a8;font-size:1.18em;'>No pages found.</p>";
          else foreach($pages as $img):
            $src = "manga/" . rawurlencode($series) . "/" . rawurlencode($chapter) . "/" . rawurlencode($img);
            echo "<img src='$src' alt='Page' class='manga-img'>";
          endforeach;
        ?>
      </div>
  <?php endif; ?>
  <!-- FLOATING BOTTOM-LEFT BAR -->
  <div class="floating-chapter-bar collapsed" id="chapterBar">
    <button class="fc-btn menu" type="button" title="Chapter List"><span>&#9776;</span></button>
    <div class="fc-divider"></div>
    <?php if($prev_chapter): ?>
      <a href="?series=<?=urlencode($series)?>&chapter=<?=urlencode($prev_chapter)?>" class="fc-btn"><span>&#8592;</span></a>
    <?php else: ?>
      <span class="fc-btn fc-disabled"><span>&#8592;</span></span>
    <?php endif; ?>
    <?php if($next_chapter): ?>
      <a href="?series=<?=urlencode($series)?>&chapter=<?=urlencode($next_chapter)?>" class="fc-btn"><span>&#8594;</span></a>
    <?php else: ?>
      <span class="fc-btn fc-disabled"><span>&#8594;</span></span>
    <?php endif; ?>
  </div>
  <!-- COMMENTS SECTION -->
  <?php
    $stmt = $pdo->prepare("SELECT username, comment, created_at FROM comments WHERE series=? AND chapter=? ORDER BY created_at ASC");
    $stmt->execute([$series, $chapter]);
    $comments = $stmt->fetchAll();
  ?>
  <div class="comments-section" style="max-width:900px;margin:2em auto 0 auto;">
    <h3>Comments</h3>
    <?php
    if ($comments) {
        foreach($comments as $c) {
            echo '<div class="comment-entry"><span class="username">'.htmlspecialchars($c['username']).'</span> <span class="meta">['.htmlspecialchars($c['created_at']).']</span><br>'
            .nl2br(htmlspecialchars($c['comment'])) . '</div>';
        }
    } else {
        echo "<div style='color:#999;margin-bottom:.7em;'>No comments yet.</div>";
    }
    ?>
    <form method="POST" style="margin-top:1.4em;">
        <textarea name="comment" rows="2" placeholder="Add a comment..." required></textarea>
        <button type="submit">Post Comment</button>
    </form>
  </div>
<?php endif; ?>

<script>
(function(){
  let prevScroll = window.scrollY;
  let bar = document.getElementById("chapterBar");
  let lastDirection = 'up';
  let ticking = false;
  window.addEventListener('scroll', function(){
    if(!ticking){
      window.requestAnimationFrame(function(){
        let curr = window.scrollY;
        if(curr > prevScroll+9 && curr > 80) {
          bar.classList.add('hide'); lastDirection = 'down';
        } else if(curr < prevScroll-9 || curr < 30) {
          bar.classList.remove('hide'); lastDirection = 'up';
        }
        prevScroll = curr;
        ticking = false;
      });
      ticking = true;
    }
  });
  // Tap/click menu to expand/collapse
  bar.querySelector('.menu').addEventListener('click', function(e){
    bar.classList.toggle('collapsed');
    e.stopPropagation();
  });
  // Collapse on outside click (mobile)
  document.body.addEventListener('click', function(e){
    if (!bar.contains(e.target) && !bar.classList.contains('collapsed')) {
      bar.classList.add('collapsed');
    }
  });
  // Start collapsed on mobile
  if(window.innerWidth < 800) bar.classList.add('collapsed');
})();

// Confirmation for buying chapter
function confirmBuyChapter(form, chapter, price) {
    return confirm("Are you sure you want to buy Chapter " + chapter + " for " + price + " points?");
}
</script>
</body>
</html>
