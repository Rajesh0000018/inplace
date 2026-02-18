<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('student');

$pageTitle    = 'Messages';
$pageSubtitle = 'Welcome back, ' . explode(' ', authName())[0];
$activePage   = 'messages';
$userId       = authId();

// Sidebar unread badge
$stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadCount = (int)$stmt->fetchColumn();

/**
 * Auto-detect column names in `messages` table
 * - time column: created_at / sent_at / timestamp / date_sent / uploaded_at ...
 * - text column: body / message / content / text ...
 */
function pickExistingColumn(PDO $pdo, string $table, array $candidates): ?string {
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME IN (" . implode(',', array_fill(0, count($candidates), '?')) . ")
    ");
    $stmt->execute(array_merge([$table], $candidates));
    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // return the first candidate in the preferred order that exists
    foreach ($candidates as $c) {
        if (in_array($c, $found, true)) return $c;
    }
    return null;
}

$timeCol = pickExistingColumn($pdo, 'messages', [
    'created_at', 'sent_at', 'timestamp', 'date_sent', 'sent_on', 'created_on', 'time', 'uploaded_at'
]);

$textCol = pickExistingColumn($pdo, 'messages', [
    'body', 'message', 'content', 'text'
]);

if (!$timeCol) {
    // last resort: use id ordering only (works even without any datetime column)
    $timeCol = 'id';
}
if (!$textCol) {
    // if you REALLY don't have a text col, set something safe to avoid crashing
    $textCol = 'id';
}
// ------------------------------
// 1) Conversations list (FIX: use ? placeholders, not repeated :me)
// ------------------------------
$sqlConvos = "
    SELECT
        t.other_id,
        u.full_name AS other_name,
        u.role      AS other_role,
        t.last_body,
        t.last_time,
        COALESCE(unread.unread_count, 0) AS unread_count
    FROM (
        SELECT
            CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END AS other_id,
            MAX(m.id) AS last_msg_id,
            MAX(m.`$timeCol`) AS last_time,
            SUBSTRING_INDEX(
                GROUP_CONCAT(m.`$textCol` ORDER BY m.id DESC SEPARATOR '|||'),
                '|||', 1
            ) AS last_body
        FROM messages m
        WHERE m.sender_id = ? OR m.receiver_id = ?
        GROUP BY other_id
    ) t
    JOIN users u ON u.id = t.other_id
    LEFT JOIN (
        SELECT sender_id AS other_id, COUNT(*) AS unread_count
        FROM messages
        WHERE receiver_id = ? AND is_read = 0
        GROUP BY sender_id
    ) unread ON unread.other_id = t.other_id
    ORDER BY t.last_time DESC
";

$stmt = $pdo->prepare($sqlConvos);
// IMPORTANT: pass $userId 4 times in the same order as the ? placeholders
$stmt->execute([$userId, $userId, $userId, $userId]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// active chat
$withId = isset($_GET['with']) ? (int)$_GET['with'] : 0;
if ($withId <= 0 && !empty($conversations)) {
    $withId = (int)$conversations[0]['other_id'];
}

// chat user info
$chatUser = null;
if ($withId > 0) {
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$withId]);
    $chatUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ------------------------------
// 2) Send message
// ------------------------------
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $toId = (int)($_POST['to_id'] ?? 0);
    $body = trim($_POST['body'] ?? '');

    if ($toId <= 0 || $body === '') {
        $error = "Please type a message.";
    } else {
        // Build insert depending on whether time column exists (if $timeCol == 'id', it's not a real time column)
        $hasRealTime = ($timeCol !== 'id');

        if ($hasRealTime) {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, `$textCol`, `$timeCol`, is_read)
                VALUES (?, ?, ?, NOW(), 0)
            ");
            $stmt->execute([$userId, $toId, $body]);
        } else {
            // no time column, insert without it
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, `$textCol`, is_read)
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$userId, $toId, $body]);
        }

        header("Location: /inplace/student/messages.php?with=" . $toId);
        exit;
    }
}

// ------------------------------
// 3) Load thread + mark read
// ------------------------------
$thread = [];
if ($chatUser) {
    $stmt = $pdo->prepare("
        UPDATE messages
        SET is_read = 1
        WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId, $withId]);

    $sqlThread = "
        SELECT id, sender_id, receiver_id, `$textCol` AS body, `$timeCol` AS created_at
        FROM messages
        WHERE (sender_id = ? AND receiver_id = ?)
           OR (sender_id = ? AND receiver_id = ?)
        ORDER BY id ASC
        LIMIT 300
    ";
    $stmt = $pdo->prepare($sqlThread);
    $stmt->execute([$userId, $withId, $withId, $userId]);
    $thread = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function timeLabel($val) {
    if ($val === null || $val === '') return '';
    // If val is numeric (id fallback), show blank
    if (is_numeric($val)) return '';
    $ts = strtotime($val);
    if (!$ts) return '';
    return date('g:i A', $ts);
}

?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <?php if ($error): ?>
            <div style="background:var(--danger-bg);border:1px solid #fca5a5;border-radius:var(--radius);
                        padding:1.25rem 2rem;margin-bottom:1.5rem;">
                <p style="color:var(--danger);font-weight:600;">⚠️ <?= htmlspecialchars($error) ?></p>
            </div>
        <?php endif; ?>

        <div class="two-col" style="grid-template-columns: 420px 1fr;">

            <!-- LEFT -->
            <div class="panel">
                <div class="panel-header">
                    <h3>Conversations</h3>
                </div>

                <div class="panel-body" style="padding:0;">
                    <?php if (empty($conversations)): ?>
                        <div style="text-align:center;padding:2.5rem 1.5rem;">
                            <div style="font-size:2.25rem;margin-bottom:0.5rem;">💬</div>
                            <p style="color:var(--muted);">No conversations yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $c): ?>
                            <?php $active = ($withId === (int)$c['other_id']); ?>
                            <a href="/inplace/student/messages.php?with=<?= (int)$c['other_id'] ?>"
                               style="display:flex;gap:0.9rem;align-items:center;
                                      padding:1.1rem 1.25rem;
                                      text-decoration:none;
                                      border-bottom:1px solid var(--border);
                                      background:<?= $active ? 'var(--cream)' : 'transparent' ?>;">

                                <div style="width:40px;height:40px;border-radius:12px;
                                            background:var(--navy);color:white;
                                            display:flex;align-items:center;justify-content:center;
                                            font-weight:700;font-size:0.8rem;">
                                    <?php
                                        $parts = preg_split('/\s+/', trim($c['other_name']));
                                        $a = strtoupper(substr($parts[0] ?? 'U', 0, 1));
                                        $b = strtoupper(substr($parts[1] ?? '', 0, 1));
                                        echo $a . ($b ?: $a);
                                    ?>
                                </div>

                                <div style="flex:1;min-width:0;">
                                    <div style="display:flex;justify-content:space-between;gap:0.75rem;">
                                        <div style="font-weight:650;color:var(--text);">
                                            <?= htmlspecialchars($c['other_name']) ?>
                                        </div>
                                        <div style="font-size:0.75rem;color:var(--muted);white-space:nowrap;">
                                            <?= htmlspecialchars(timeLabel($c['last_time'])) ?>
                                        </div>
                                    </div>

                                    <div style="display:flex;justify-content:space-between;gap:0.75rem;margin-top:0.2rem;">
                                        <div style="font-size:0.82rem;color:var(--muted);
                                                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                            <?= htmlspecialchars($c['other_role'] ? ucwords(str_replace('_',' ', $c['other_role'])) : '') ?>
                                            <?= $c['last_body'] ? '· ' . htmlspecialchars($c['last_body']) : '' ?>
                                        </div>

                                        <?php if ((int)$c['unread_count'] > 0): ?>
                                            <span class="badge badge-open" style="min-width:28px;text-align:center;">
                                                <?= (int)$c['unread_count'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="width:28px;"></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- RIGHT -->
            <div class="panel" style="display:flex;flex-direction:column;min-height:620px;">
                <div class="panel-header" style="display:flex;align-items:center;gap:0.9rem;">
                    <?php if ($chatUser): ?>
                        <div style="width:42px;height:42px;border-radius:14px;background:var(--navy);color:white;
                                    display:flex;align-items:center;justify-content:center;font-weight:700;">
                            <?php
                                $parts = preg_split('/\s+/', trim($chatUser['full_name']));
                                $a = strtoupper(substr($parts[0] ?? 'U', 0, 1));
                                $b = strtoupper(substr($parts[1] ?? '', 0, 1));
                                echo $a . ($b ?: $a);
                            ?>
                        </div>
                        <div>
                            <div style="font-weight:700;"><?= htmlspecialchars($chatUser['full_name']) ?></div>
                            <div style="font-size:0.82rem;color:var(--muted);">
                                <?= htmlspecialchars($chatUser['role'] ? ucwords(str_replace('_',' ', $chatUser['role'])) : '') ?> · Online
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="font-weight:700;">Select a conversation</div>
                    <?php endif; ?>
                </div>

                <div style="flex:1; padding:1.25rem; overflow:auto;">
                    <?php if (!$chatUser): ?>
                        <div style="text-align:center;padding:3rem 1rem;color:var(--muted);">
                            Choose a conversation from the left.
                        </div>
                    <?php elseif (empty($thread)): ?>
                        <div style="text-align:center;padding:3rem 1rem;color:var(--muted);">
                            No messages yet. Say hi 👋
                        </div>
                    <?php else: ?>
                        <?php foreach ($thread as $m): ?>
                            <?php $mine = ((int)$m['sender_id'] === $userId); ?>
                            <div style="display:flex;justify-content:<?= $mine ? 'flex-end' : 'flex-start' ?>; margin-bottom:0.9rem;">
                                <div style="max-width:70%;
                                            padding:0.9rem 1rem;
                                            border-radius:14px;
                                            background:<?= $mine ? 'var(--navy)' : 'var(--cream)' ?>;
                                            color:<?= $mine ? 'white' : 'var(--text)' ?>;
                                            box-shadow:0 8px 25px rgba(0,0,0,0.06);">
                                    <div style="white-space:pre-wrap;line-height:1.45;">
                                        <?= htmlspecialchars($m['body']) ?>
                                    </div>
                                    <div style="font-size:0.72rem;margin-top:0.45rem;opacity:0.75;">
                                        <?= htmlspecialchars(timeLabel($m['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($chatUser): ?>
                <div style="border-top:1px solid var(--border); padding:1rem 1.25rem;">
                    <form method="POST" style="display:flex;gap:0.75rem;align-items:center;">
                        <input type="hidden" name="to_id" value="<?= (int)$chatUser['id'] ?>">
                        <input type="text" name="body" placeholder="Type a message..."
                               style="flex:1;padding:0.9rem 1rem;border:2px solid var(--border);
                                      border-radius:12px;background:var(--cream);font-family:inherit;">
                        <button type="submit" name="send_message" class="btn btn-primary" style="white-space:nowrap;">
                            Send →
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>