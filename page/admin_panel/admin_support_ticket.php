<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function asv_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function asv_s(mixed $value): string
{
    if (is_string($value)) {
        return trim($value);
    }
    if (is_int($value) || is_float($value) || is_bool($value)) {
        return trim((string)$value);
    }
    return '';
}

$request = Sogerien::InputRequest()->request_post_get_cookie_json;
$dbAlias = trim((string)Sogerien::AccessCheck()->db_alias);
if ($dbAlias === '') {
    $dbAlias = 'front';
}

$users = Sogerien::Users();
$users->init_db_alias($dbAlias);
$users->load_identity_from_token();
$userId = (int)$users->user_id;
$isAdmin = isset($users->user_group['admin']);
if ($userId <= 0) {
    $_GET['next'] = '/admin/support/ticket';
    require __DIR__ . '/page_login_form.php';
    Sogerien::markDone();
    return;
}

$tickets = new SupportTickets();
$tickets->init_db_alias($dbAlias);
$ticketId = asv_s($request['id'] ?? $request['ticket_id'] ?? '');
$alertType = '';
$alertText = '';

Sogerien::Page()->title = 'Admin Support Ticket';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
if (!$isAdmin) {
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger" role="alert">Admin access required.</div></main>';
    Sogerien::Page()->footer();
    return;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $ticketId !== '') {
    $action = asv_s($request['action'] ?? 'reply');
    if ($action === 'status') {
        $result = $tickets->set_status($ticketId, asv_s($request['status'] ?? ''), $userId, true);
    } else {
        $result = $tickets->add_message($ticketId, $userId, 'admin', asv_s($request['message'] ?? ''), true);
    }
    if (($result['ok'] ?? false) === true) {
        header('Location: /admin/support/ticket?id=' . rawurlencode($ticketId));
        Sogerien::markDone();
        return;
    }
    $alertType = 'danger';
    $alertText = (string)($result['error'] ?? 'Action failed.');
}

$ticket = $tickets->get_ticket($ticketId, $userId, true);
?>
<main class="container my-4 sog-ui">
    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= asv_h($alertType) ?>" role="alert"><?= asv_h($alertText) ?></div>
    <?php endif; ?>

    <?php if (!is_array($ticket)): ?>
        <div class="alert alert-danger" role="alert">Ticket not found.</div>
        <a class="btn btn-outline-secondary" href="/admin/support/tickets">Back to tickets</a>
    <?php else: ?>
        <section class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h1 class="h4 mb-1"><?= asv_h($ticket['subject'] ?? '') ?></h1>
                        <div class="text-muted small">Ticket: <code><?= asv_h($ticketId) ?></code></div>
                        <div class="text-muted small">Client: #<?= (int)($ticket['user_id'] ?? 0) ?> - <?= asv_h($ticket['department'] ?? '-') ?></div>
                    </div>
                    <a class="btn btn-outline-secondary" href="/admin/support/tickets">Back</a>
                </div>
            </div>
        </section>

        <section class="card shadow-sm mb-3">
            <div class="card-header">Status</div>
            <div class="card-body">
                <form method="post" action="/admin/support/ticket" class="row g-2 align-items-end">
                    <input type="hidden" name="id" value="<?= asv_h($ticketId) ?>">
                    <input type="hidden" name="action" value="status">
                    <div class="col-md-4">
                        <label class="form-label" for="asvStatus">Status</label>
                        <select class="form-select" id="asvStatus" name="status">
                            <?php foreach (['open' => 'Open', 'review' => 'In review', 'closed' => 'Closed'] as $value => $label): ?>
                                <option value="<?= asv_h($value) ?>" <?= asv_s($ticket['status'] ?? '') === $value ? 'selected' : '' ?>><?= asv_h($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary" type="submit">Update status</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card shadow-sm mb-3">
            <div class="card-header">Conversation</div>
            <div class="card-body">
                <?php $messages = isset($ticket['messages']) && is_array($ticket['messages']) ? $ticket['messages'] : []; ?>
                <?php foreach ($messages as $message): ?>
                    <?php if (!is_array($message)) { continue; } ?>
                    <?php $authorType = asv_s($message['author_type'] ?? 'user'); ?>
                    <div class="mb-3 p-3 border rounded <?= $authorType === 'admin' ? 'border-primary' : '' ?>">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <strong><?= $authorType === 'admin' ? 'Support' : 'Client #' . (int)($message['author_id'] ?? 0) ?></strong>
                            <span class="text-muted small"><?= asv_h($message['created_at'] ?? '') ?></span>
                        </div>
                        <div style="white-space: pre-wrap;"><?= asv_h($message['body'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card shadow-sm mb-3">
            <div class="card-header">Reply as support</div>
            <div class="card-body">
                <form method="post" action="/admin/support/ticket">
                    <input type="hidden" name="id" value="<?= asv_h($ticketId) ?>">
                    <input type="hidden" name="action" value="reply">
                    <textarea class="form-control mb-3" name="message" rows="5" required></textarea>
                    <button class="btn btn-primary" type="submit">Send reply</button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
Sogerien::Page()->footer();
