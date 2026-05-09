<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function ast_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ast_s(mixed $value): string
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
    $_GET['next'] = '/admin/support/tickets';
    require __DIR__ . '/page_login_form.php';
    Sogerien::markDone();
    return;
}

Sogerien::Page()->title = 'Admin Support Tickets';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
if (!$isAdmin) {
    echo '<main class="container my-4 sog-ui"><div class="alert alert-danger" role="alert">Admin access required.</div></main>';
    Sogerien::Page()->footer();
    return;
}

$tickets = new SupportTickets();
$tickets->init_db_alias($dbAlias);
$status = ast_s($request['status'] ?? '');
$rows = $tickets->list_all_tickets($status);
?>
<main class="container my-4 sog-ui">
    <section class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap">
                <div>
                    <h1 class="h4 mb-1">Support tickets</h1>
                    <div class="text-muted">Client tickets sorted by status and update time.</div>
                </div>
                <form method="get" action="/admin/support/tickets" class="d-flex gap-2 align-items-end">
                    <div>
                        <label class="form-label" for="astStatus">Status</label>
                        <select class="form-select" id="astStatus" name="status">
                            <option value="">All</option>
                            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="review" <?= $status === 'review' ? 'selected' : '' ?>>In review</option>
                            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">Apply</button>
                </form>
            </div>
        </div>
    </section>

    <section class="card shadow-sm mb-3">
        <div class="card-body p-0">
            <?php if ($rows === []): ?>
                <div class="p-3 text-muted">No tickets found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Ticket</th>
                                <th>User</th>
                                <th>Service</th>
                                <th>Priority</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Last reply</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $ticket): ?>
                            <?php $ticketId = ast_s($ticket['ticket_id'] ?? ''); ?>
                            <tr>
                                <td><a href="/admin/support/ticket?id=<?= ast_h(rawurlencode($ticketId)) ?>"><?= ast_h($ticket['subject'] ?? $ticketId) ?></a></td>
                                <td>#<?= (int)($ticket['user_id'] ?? 0) ?></td>
                                <td><?= ast_h($ticket['service_title'] ?? $ticket['service_id'] ?? '-') ?></td>
                                <td><?= ast_h(ucfirst(ast_s($ticket['priority'] ?? 'normal'))) ?></td>
                                <td><?= ast_h($ticket['department'] ?? '-') ?></td>
                                <td><?= ast_h($tickets->status_label(ast_s($ticket['status'] ?? ''))) ?></td>
                                <td><?= ast_h($ticket['last_reply_by'] ?? '-') ?></td>
                                <td><?= ast_h($ticket['updated_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
Sogerien::Page()->footer();
