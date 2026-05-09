<?php
declare(strict_types=1);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

function st_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function st_s(mixed $value): string
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
    $_GET['next'] = '/client/support/tickets';
    require __DIR__ . '/page_login_form.php';
    Sogerien::markDone();
    return;
}

$tickets = new SupportTickets();
$tickets->init_db_alias($dbAlias);
$shop = new ProxyShop();
$shop->init_db_alias($dbAlias);
$services = $shop->list_user_services($userId);

$alertType = '';
$alertText = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $serviceId = st_s($request['service_id'] ?? '');
    $serviceTitle = '';
    foreach ($services as $service) {
        if (is_array($service) && st_s($service['service_id'] ?? '') === $serviceId) {
            $serviceTitle = st_s($service['title'] ?? '');
            break;
        }
    }
    $result = $tickets->create_ticket(
        $userId,
        st_s($request['subject'] ?? ''),
        st_s($request['department'] ?? ''),
        st_s($request['message'] ?? ''),
        [
            'priority' => st_s($request['priority'] ?? 'normal'),
            'service_id' => $serviceId,
            'service_title' => $serviceTitle,
        ]
    );
    if (($result['ok'] ?? false) === true) {
        header('Location: /client/support/ticket?id=' . rawurlencode((string)$result['ticket_id']));
        Sogerien::markDone();
        return;
    }
    $alertType = 'danger';
    $alertText = (string)($result['error'] ?? 'Ticket was not created.');
}

$rows = $tickets->list_user_tickets($userId);

Sogerien::Page()->title = 'Support Tickets';
Sogerien::Page()->header();
Sogerien::Page()->mainmenu();
?>
<main class="container my-4 sog-ui">
    <?php if ($alertText !== ''): ?>
        <div class="alert alert-<?= st_h($alertType) ?>" role="alert"><?= st_h($alertText) ?></div>
    <?php endif; ?>

    <section class="card shadow-sm mb-3">
        <div class="card-header">Open ticket</div>
        <div class="card-body">
            <form method="post" action="/client/support/tickets" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="stDepartment">Department</label>
                    <select class="form-select" id="stDepartment" name="department">
                        <option value="Technical support">Technical support</option>
                        <option value="Billing">Billing</option>
                        <option value="Account">Account</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="stPriority">Priority</label>
                    <select class="form-select" id="stPriority" name="priority">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="stService">Service</label>
                    <select class="form-select" id="stService" name="service_id">
                        <option value="">Not linked</option>
                        <?php foreach ($services as $service): ?>
                            <?php if (!is_array($service)) { continue; } ?>
                            <?php $sid = st_s($service['service_id'] ?? ''); ?>
                            <?php if ($sid === '') { continue; } ?>
                            <option value="<?= st_h($sid) ?>"><?= st_h(st_s($service['title'] ?? $sid)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label" for="stSubject">Subject</label>
                    <input class="form-control" id="stSubject" name="subject" required>
                </div>
                <div class="col-12">
                    <label class="form-label" for="stMessage">Message</label>
                    <textarea class="form-control" id="stMessage" name="message" rows="5" required></textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit">Create ticket</button>
                </div>
            </form>
        </div>
    </section>

    <section class="card shadow-sm mb-3">
        <div class="card-header">My tickets</div>
        <div class="card-body p-0">
            <?php if ($rows === []): ?>
                <div class="p-3 text-muted">No tickets yet.</div>
            <?php else: ?>
                <?php
                $tableRows = [];
                foreach ($rows as $ticket) {
                    $ticketId = st_s($ticket['ticket_id'] ?? '');
                    $tableRows[] = [
                        'ticket' => '<a href="/client/support/ticket?id=' . st_h(rawurlencode($ticketId)) . '">' . st_h($ticket['subject'] ?? $ticketId) . '</a>',
                        'service' => st_s($ticket['service_title'] ?? $ticket['service_id'] ?? '-'),
                        'priority' => ucfirst(st_s($ticket['priority'] ?? 'normal')),
                        'department' => st_s($ticket['department'] ?? '-'),
                        'status' => $tickets->status_label(st_s($ticket['status'] ?? '')),
                        'updated' => st_s($ticket['updated_at'] ?? '-'),
                    ];
                }
                $tr = Sogerien::TableRenderer();
                $tr->set_params = new SetParams();
                $tr->set_params->data = $tableRows;
                $tr->set_params->columns = ['ticket', 'service', 'priority', 'department', 'status', 'updated'];
                $tr->set_params->headers = ['ticket' => 'Ticket', 'service' => 'Service', 'priority' => 'Priority', 'department' => 'Department', 'status' => 'Status', 'updated' => 'Updated'];
                $tr->set_params->gridId = 'client_tickets_grid';
                $tr->set_params->searchCols = $tr->set_params->columns;
                $tr->set_params->perPage = 50;
                $tr->set_params->formatters['ticket'] = static fn($value): string => (string)$value;
                $tr->render();
                ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php
Sogerien::Page()->footer();
