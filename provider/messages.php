<?php
// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../includes/auth.php';
require_once '../config/db.php';

requireAuth('provider');

$pageTitle = 'Messages';
$pageSubtitle = 'Communicate with students and tutors';
$activePage = 'messages';
$userId = authId();

// Get provider info
$stmt = $pdo->prepare("SELECT company_id FROM users WHERE id = ?");
$stmt->execute([$userId]);
$provider = $stmt->fetch();

// Check if provider has a company
if (!$provider || !$provider['company_id']) {
    ?>
    <?php include '../includes/header.php'; ?>
    <div class="main">
        <?php include '../includes/topbar.php'; ?>
        <div class="page-content">
            <div class="panel" style="padding:3rem;text-align:center;">
                <div style="font-size:3rem;margin-bottom:1rem;">⚠️</div>
                <h3 style="color:var(--danger);margin-bottom:1rem;">Company Not Assigned</h3>
                <p style="color:var(--muted);">Your provider account is not linked to a company yet.</p>
                <p style="color:var(--muted);margin-top:0.5rem;">Please contact the administrator to assign a company to your account.</p>
                
                <div style="margin-top:2rem;padding:1.5rem;background:var(--cream);border-radius:var(--radius-sm);text-align:left;">
                    <h4 style="margin-bottom:0.75rem;">Debug Information:</h4>
                    <p style="font-size:0.875rem;color:var(--muted);font-family:monospace;">
                        User ID: <?= $userId ?><br>
                        Company ID: <?= $provider['company_id'] ?? 'NULL' ?>
                    </p>
                </div>

                <div style="margin-top:2rem;">
                    <a href="/inplace/provider/dashboard.php" class="btn btn-primary">← Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <?php
    exit;
}

// Fetch all conversations related to this provider's company
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            p.id AS placement_id,
            p.student_id,
            p.tutor_id,
            s.full_name AS student_name,
            s.avatar_initials AS student_initials,
            t.full_name AS tutor_name,
            t.avatar_initials AS tutor_initials,
            c.name AS company_name,
            p.role_title,
            (SELECT message FROM messages WHERE placement_id = p.id ORDER BY created_at DESC LIMIT 1) AS last_message,
            (SELECT created_at FROM messages WHERE placement_id = p.id ORDER BY created_at DESC LIMIT 1) AS last_message_time,
            (SELECT COUNT(*) FROM messages WHERE placement_id = p.id AND sender_id != ? AND is_read = 0) AS unread_count
        FROM placements p
        JOIN users s ON p.student_id = s.id
        LEFT JOIN users t ON p.tutor_id = t.id
        JOIN companies c ON p.company_id = c.id
        WHERE p.company_id = ?
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([$userId, $provider['company_id']]);
    $conversations = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get selected conversation
$selectedPlacementId = $_GET['placement'] ?? ($conversations[0]['placement_id'] ?? null);
$selectedConversation = null;
$messages = [];

if ($selectedPlacementId) {
    // Get conversation details
    foreach ($conversations as $conv) {
        if ($conv['placement_id'] == $selectedPlacementId) {
            $selectedConversation = $conv;
            break;
        }
    }
    
    // Fetch messages for selected conversation
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            u.full_name AS sender_name,
            u.avatar_initials,
            u.role AS sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.placement_id = ?
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$selectedPlacementId]);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE placement_id = ? AND sender_id != ?");
    $stmt->execute([$selectedPlacementId, $userId]);
}

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $messageContent = trim($_POST['message_content']);
    $placementId = $_POST['placement_id'];
    
    if (!empty($messageContent)) {
        $stmt = $pdo->prepare("
            INSERT INTO messages (placement_id, sender_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$placementId, $userId, $messageContent]);
        
        header("Location: messages.php?placement=$placementId");
        exit;
    }
}

$unreadCount = 0;
$pendingRequests = 0;
?>
<?php include '../includes/header.php'; ?>

<div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-content">

        <div style="display:grid;grid-template-columns:360px 1fr;gap:1.5rem;height:calc(100vh - 200px);">

            <!-- Conversations List -->
            <div class="panel" style="margin-bottom:0;display:flex;flex-direction:column;overflow:hidden;">
                <div class="panel-header" style="border-bottom:1px solid var(--border);">
                    <h3>💬 Conversations</h3>
                </div>

                <div style="flex:1;overflow-y:auto;padding:0.75rem;">
                    <?php if (empty($conversations)): ?>
                        <div style="text-align:center;padding:3rem 1rem;color:var(--muted);">
                            <div style="font-size:2.5rem;margin-bottom:1rem;">💬</div>
                            <p>No conversations yet</p>
                            <p style="font-size:0.8125rem;margin-top:0.5rem;">Students will appear here once they have placements at your company.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                        <a href="messages.php?placement=<?= $conv['placement_id'] ?>" 
                           style="display:block;padding:1rem;border-radius:var(--radius-sm);
                                  margin-bottom:0.5rem;text-decoration:none;transition:all 0.2s;
                                  background:<?= $conv['placement_id']==$selectedPlacementId?'var(--cream)':'transparent' ?>;
                                  border:1px solid <?= $conv['placement_id']==$selectedPlacementId?'var(--border)':'transparent' ?>;">
                            
                            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;">
                                <div class="avatar" style="width:40px;height:40px;">
                                    <?= htmlspecialchars($conv['student_initials'] ?? '??') ?>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <h4 style="font-size:0.9375rem;font-weight:600;color:var(--navy);
                                               white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($conv['student_name']) ?>
                                    </h4>
                                    <p style="font-size:0.75rem;color:var(--muted);
                                              white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                        <?= htmlspecialchars($conv['role_title'] ?? 'No role specified') ?>
                                    </p>
                                </div>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span style="background:var(--danger);color:white;
                                                 border-radius:50px;padding:0.2rem 0.5rem;
                                                 font-size:0.6875rem;font-weight:700;">
                                        <?= $conv['unread_count'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($conv['last_message']): ?>
                                <p style="font-size:0.8125rem;color:var(--muted);
                                          white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    <?= htmlspecialchars(substr($conv['last_message'], 0, 60)) ?>...
                                </p>
                                <p style="font-size:0.6875rem;color:var(--muted);margin-top:0.25rem;">
                                    <?= date('M j, g:i A', strtotime($conv['last_message_time'])) ?>
                                </p>
                            <?php endif; ?>
                        </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Message Thread -->
            <div class="panel" style="margin-bottom:0;display:flex;flex-direction:column;overflow:hidden;">
                
                <?php if ($selectedConversation): ?>
                    
                    <!-- Thread Header -->
                    <div class="panel-header" style="border-bottom:1px solid var(--border);">
                        <div>
                            <h3><?= htmlspecialchars($selectedConversation['student_name']) ?></h3>
                            <p style="margin-top:0.25rem;">
                                <?= htmlspecialchars($selectedConversation['role_title'] ?? 'No role specified') ?> · 
                                <?= htmlspecialchars($selectedConversation['company_name']) ?>
                            </p>
                        </div>
                        <?php if ($selectedConversation['tutor_name']): ?>
                            <span class="type-chip">
                                👨‍🏫 Tutor: <?= htmlspecialchars($selectedConversation['tutor_name']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Messages -->
                    <div style="flex:1;overflow-y:auto;padding:1.5rem;background:var(--cream);">
                        <div class="message-list">
                            <?php foreach ($messages as $msg): ?>
                            <div class="message-item <?= $msg['sender_id']==$userId?'outgoing':'' ?>">
                                <div class="avatar" style="width:36px;height:36px;font-size:0.75rem;">
                                    <?= htmlspecialchars($msg['avatar_initials'] ?? '??') ?>
                                </div>
                                <div style="flex:1;">
                                    <div class="msg-bubble">
                                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                    </div>
                                    <div class="msg-meta">
                                        <?= htmlspecialchars($msg['sender_name']) ?> · 
                                        <?= date('M j, g:i A', strtotime($msg['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <?php if (empty($messages)): ?>
                            <div style="text-align:center;padding:3rem;color:var(--muted);">
                                <div style="font-size:3rem;margin-bottom:1rem;">💬</div>
                                <p>No messages yet. Start the conversation!</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Message Input -->
                    <form method="POST" class="msg-input-bar">
                        <input type="hidden" name="placement_id" value="<?= $selectedPlacementId ?>">
                        <input type="text" name="message_content" class="msg-input" 
                               placeholder="Type your message..." required>
                        <button type="submit" name="send_message" class="btn btn-primary">
                            Send
                        </button>
                    </form>

                <?php else: ?>
                    <div style="display:flex;align-items:center;justify-content:center;height:100%;">
                        <div style="text-align:center;color:var(--muted);">
                            <div style="font-size:4rem;margin-bottom:1rem;">💬</div>
                            <h3 style="color:var(--navy);margin-bottom:0.5rem;">Select a conversation</h3>
                            <p>Choose a student from the list to view messages</p>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </div>
</div>

<?php include '../includes/footer.php'; ?>