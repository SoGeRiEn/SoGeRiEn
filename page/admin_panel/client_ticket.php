<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function ct_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ct_s(mixed $value): string
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
if ($userId <= 0) {
    $_GET['next'] = '/client/support/ticket';
    require __DIR__ . '/page_login_form.php';
    Sogerien::markDone();
    return;
}

$tickets = new SupportTickets();
$tickets->init_db_alias($dbAlias);
$ticketId = ct_s($request['id'] ?? $request['ticket_id'] ?? '');
$alertType = '';
$alertText = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $ticketId !== '') {
    $action = ct_s($request['action'] ?? 'reply');
    if ($action === 'close' || $action === 'reopen') {
        $result = $tickets->set_status($ticketId, $action === 'close' ? 'closed' : 'open', $userId, false);
    } else {
        $result = $tickets->add_message($ticketId, $userId, 'user', ct_s($request['message'] ?? ''), false);
    }
    if (($result['ok'] ?? false) === true) {
        header('Location: /client/support/ticket?id=' . rawurlencode($ticketId));
        Sogerien::markDone();
        return;
    }
    $alertType = 'danger';
    $alertText = (string)($result['error'] ?? 'Action failed.');
}

$ticket = $tickets->get_ticket($ticketId, $userId, false);

Sogerien::Page()->title = 'Support Ticket';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= ct_h($alertType) ?>" role="alert"><?= ct_h($alertText) ?></div>
    <?php endif; ?>

    <?php if (!is_array($ticket)): ?>
        <div class="alert alert-danger" role="alert">Ticket not found.</div>
        <a class="btn btn-outline-secondary" href="/client/support/tickets">Back to tickets</a>
    <?php else: ?>
        <section class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div>
                        <h1 class="h4 mb-1"><?= ct_h($ticket['subject'] ?? '') ?></h1>
                        <div class="text-muted small">Ticket: <code><?= ct_h($ticketId) ?></code></div>
                        <div class="text-muted small">Department: <?= ct_h($ticket['department'] ?? '-') ?></div>
                        <div class="text-muted small">Priority: <?= ct_h(ucfirst(ct_s($ticket['priority'] ?? 'normal'))) ?></div>
                        <?php if (ct_s($ticket['service_title'] ?? $ticket['service_id'] ?? '') !== ''): ?>
                            <div class="text-muted small">Service: <?= ct_h($ticket['service_title'] ?? $ticket['service_id'] ?? '') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <span class="badge text-bg-secondary align-self-center"><?= ct_h($tickets->status_label(ct_s($ticket['status'] ?? ''))) ?></span>
                        <a class="btn btn-outline-secondary" href="/client/support/tickets">Back</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="card shadow-sm mb-3">
            <div class="card-header">Conversation</div>
            <div class="card-body">
                <?php $messages = isset($ticket['messages']) && is_array($ticket['messages']) ? $ticket['messages'] : []; ?>
                <?php foreach ($messages as $message): ?>
                    <?php if (!is_array($message)) { continue; } ?>
                    <?php $authorType = ct_s($message['author_type'] ?? 'user'); ?>
                    <div class="mb-3 p-3 border rounded <?= $authorType === 'admin' ? 'border-primary' : '' ?>">
                        <div class="d-flex justify-content-between gap-2 mb-2">
                            <strong><?= $authorType === 'admin' ? 'Support' : 'You' ?></strong>
                            <span class="text-muted small"><?= ct_h($message['created_at'] ?? '') ?></span>
                        </div>
                        <div style="white-space: pre-wrap;"><?= ct_h($message['body'] ?? '') ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="card shadow-sm mb-3">
            <div class="card-header">Reply</div>
            <div class="card-body">
                <form method="post" action="/client/support/ticket" class="mb-3">
                    <input type="hidden" name="id" value="<?= ct_h($ticketId) ?>">
                    <input type="hidden" name="action" value="reply">
                    <textarea class="form-control mb-3" name="message" rows="5" required></textarea>
                    <button class="btn btn-primary" type="submit">Send reply</button>
                </form>
                <form method="post" action="/client/support/ticket" class="d-inline">
                    <input type="hidden" name="id" value="<?= ct_h($ticketId) ?>">
                    <input type="hidden" name="action" value="<?= ct_s($ticket['status'] ?? '') === 'closed' ? 'reopen' : 'close' ?>">
                    <button class="btn btn-outline-secondary" type="submit"><?= ct_s($ticket['status'] ?? '') === 'closed' ? 'Reopen ticket' : 'Close ticket' ?></button>
                </form>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
Sogerien::Page()->footer();
