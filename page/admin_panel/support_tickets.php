<?php
declare(strict_types=1);

function pm_ticket_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pm_ticket_db(string $sql, array $params = []): array
{
    return Sogerien::API()->Postgresql()->sql($sql, $params, 'front');
}

function pm_ticket_user_id(): int
{
    Sogerien::Users()->load_identity_from_token();
    return (int)(Sogerien::Users()->user_id ?? 0);
}

function pm_ticket_is_admin(): bool
{
    Sogerien::Users()->load_identity_from_token();
    return isset(Sogerien::Users()->user_group['admin']);
}

function pm_ticket_json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '{}';
}

function pm_ticket_create_tables(): void
{
    pm_ticket_db("
        CREATE TABLE IF NOT EXISTS sogerien (
            id BIGSERIAL PRIMARY KEY,
            table_name TEXT NOT NULL,
            name TEXT NOT NULL DEFAULT '',
            table_value JSONB NOT NULL DEFAULT '{}'::jsonb,
            status TEXT NOT NULL DEFAULT 'actual'
        );
    ");
}

function pm_ticket_create(int $userId, string $title, string $message): int
{
    $payload = [
        'client_id' => $userId,
        'title' => $title,
        'status' => 'waiting_admin',
        'last_message' => $message,
        'last_author' => 'client',
        'updated_at' => gmdate('c'),
    ];
    $res = pm_ticket_db(
        "INSERT INTO sogerien (table_name, name, table_value, status)
         VALUES ('support_ticket', :name, CAST(:payload AS jsonb), 'actual')
         RETURNING id",
        ['name' => $title, 'payload' => pm_ticket_json($payload)]
    );
    $ticketId = (int)($res['rows'][0]['id'] ?? 0);
    if ($ticketId > 0) {
        pm_ticket_add_message($ticketId, $userId, 'client', $message);
    }
    return $ticketId;
}

function pm_ticket_add_message(int $ticketId, int $userId, string $authorType, string $message): void
{
    $payload = [
        'ticket_id' => $ticketId,
        'author_id' => $userId,
        'author_type' => $authorType,
        'message' => $message,
        'created_at' => gmdate('c'),
    ];
    pm_ticket_db(
        "INSERT INTO sogerien (table_name, name, table_value, status)
         VALUES ('support_ticket_message', :name, CAST(:payload AS jsonb), 'actual')",
        ['name' => 'ticket_' . $ticketId, 'payload' => pm_ticket_json($payload)]
    );
    $ticketStatus = $authorType === 'admin' ? 'waiting_client' : 'waiting_admin';
    pm_ticket_db(
        "UPDATE sogerien
         SET table_value = jsonb_set(jsonb_set(jsonb_set(jsonb_set(table_value, '{status}', to_jsonb(CAST(:ticket_status AS text)), true), '{last_message}', to_jsonb(CAST(:message AS text)), true), '{last_author}', to_jsonb(CAST(:author_type AS text)), true), '{updated_at}', to_jsonb(CAST(:updated_at AS text)), true)
         WHERE table_name = 'support_ticket' AND id = :ticket_id",
        [
            'ticket_status' => $ticketStatus,
            'message' => $message,
            'author_type' => $authorType,
            'updated_at' => gmdate('c'),
            'ticket_id' => $ticketId,
        ]
    );
}

function pm_ticket_rows(bool $admin, bool $pendingOnly, int $userId): array
{
    $where = $admin ? "table_name = 'support_ticket'" : "table_name = 'support_ticket' AND (table_value->>'client_id')::int = :user_id";
    $params = $admin ? [] : ['user_id' => $userId];
    if ($pendingOnly) {
        $where .= " AND table_value->>'status' = 'waiting_admin'";
    }
    $res = pm_ticket_db(
        "SELECT t.id, t.name, t.table_value,
                u.table_value AS user_value
         FROM sogerien t
         LEFT JOIN sogerien u ON u.table_name = 'user' AND u.id = NULLIF(t.table_value->>'client_id', '')::int
         WHERE {$where}
         ORDER BY COALESCE(t.table_value->>'updated_at', '') DESC, t.id DESC",
        $params
    );
    return is_array($res['rows'] ?? null) ? $res['rows'] : [];
}

function pm_ticket_messages(int $ticketId): array
{
    $res = pm_ticket_db(
        "SELECT id, table_value
         FROM sogerien
         WHERE table_name = 'support_ticket_message'
           AND (table_value->>'ticket_id')::int = :ticket_id
         ORDER BY id",
        ['ticket_id' => $ticketId]
    );
    return is_array($res['rows'] ?? null) ? $res['rows'] : [];
}

pm_ticket_create_tables();
$userId = pm_ticket_user_id();
$isAdmin = pm_ticket_is_admin();
$path = trim((string)(Sogerien::InputRequest()->url ?? ''), '/');
$isAdminPage = str_starts_with($path, 'admin/ticket');
$pendingOnly = $path === 'admin/tickets/pending';
$ticketId = (int)($_GET['id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'create') {
        $title = trim((string)($_POST['title'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($title === '' || $message === '') {
            $error = Sogerien::Lang()->get('tickets.fill_title_message');
        } else {
            $newId = pm_ticket_create($userId, $title, $message);
            header('Location: /support/ticket?id=' . $newId);
            exit;
        }
    } elseif ($action === 'reply') {
        $message = trim((string)($_POST['message'] ?? ''));
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        if ($ticketId > 0 && $message !== '') {
            pm_ticket_add_message($ticketId, $userId, $isAdminPage && $isAdmin ? 'admin' : 'client', $message);
            header('Location: ' . ($isAdminPage ? '/admin/ticket' : '/support/ticket') . '?id=' . $ticketId);
            exit;
        }
        $error = Sogerien::Lang()->get('tickets.empty_message');
    }
}

$tpl = Sogerien::Template();
$tpl->title = Sogerien::Lang()->get($isAdminPage || str_starts_with($path, 'admin/tickets') ? 'tickets.admin_title' : 'tickets.title');
$tpl->header();
$tpl->mainmenu();

echo '<main class="pm-content"><section class="pm-panel pm-ticket-panel">';
if ($error !== '') {
    echo '<div class="alert alert-danger">' . pm_ticket_h($error) . '</div>';
}

if ($path === 'support/tickets/create') {
    echo '<div class="pm-panel-head"><h1>' . pm_ticket_h(Sogerien::Lang()->get('tickets.create')) . '</h1></div>';
    echo '<form class="pm-ticket-form" method="post"><input type="hidden" name="action" value="create">';
    echo '<label>' . pm_ticket_h(Sogerien::Lang()->get('tickets.subject')) . '<input class="form-control" name="title" required></label>';
    echo '<label>' . pm_ticket_h(Sogerien::Lang()->get('tickets.message')) . '<textarea class="form-control" name="message" rows="8" required></textarea></label>';
    echo '<button class="pm-cta pm-cta-primary" type="submit">' . pm_ticket_h(Sogerien::Lang()->get('tickets.create')) . '</button></form>';
} elseif ($ticketId > 0) {
    $messages = pm_ticket_messages($ticketId);
    echo '<div class="pm-panel-head"><h1>' . pm_ticket_h(Sogerien::Lang()->get('tickets.ticket')) . ' #' . $ticketId . '</h1></div>';
    echo '<div class="pm-ticket-chat">';
    foreach ($messages as $messageRow) {
        $tv = $messageRow['table_value'] ?? [];
        if (is_string($tv)) {
            $tv = json_decode($tv, true) ?: [];
        }
        $author = (string)($tv['author_type'] ?? 'client');
        echo '<div class="pm-ticket-bubble ' . ($author === 'admin' ? 'is-admin' : 'is-client') . '">';
        echo '<div class="pm-ticket-author">' . pm_ticket_h($author === 'admin' ? Sogerien::Lang()->get('tickets.admin') : Sogerien::Lang()->get('tickets.client')) . '</div>';
        echo '<div>' . nl2br(pm_ticket_h((string)($tv['message'] ?? ''))) . '</div>';
        echo '</div>';
    }
    echo '</div><form class="pm-ticket-form" method="post"><input type="hidden" name="action" value="reply"><input type="hidden" name="ticket_id" value="' . $ticketId . '">';
    echo '<label>' . pm_ticket_h(Sogerien::Lang()->get('tickets.reply')) . '<textarea class="form-control" name="message" rows="5" required></textarea></label>';
    echo '<button class="pm-cta pm-cta-primary" type="submit">' . pm_ticket_h(Sogerien::Lang()->get('tickets.send')) . '</button></form>';
} else {
    $rows = pm_ticket_rows($isAdminPage || str_starts_with($path, 'admin/tickets'), $pendingOnly, $userId);
    echo '<div class="pm-panel-head"><h1>' . pm_ticket_h($tpl->title) . '</h1><a class="pm-cta pm-cta-primary" href="/support/tickets/create">' . pm_ticket_h(Sogerien::Lang()->get('tickets.create')) . '</a></div>';
    echo '<div class="table-responsive"><table class="table table-dark table-hover pm-ticket-table"><thead><tr>';
    echo '<th>' . pm_ticket_h(Sogerien::Lang()->get('common.id')) . '</th><th>' . pm_ticket_h(Sogerien::Lang()->get('tickets.client_id')) . '</th><th>' . pm_ticket_h(Sogerien::Lang()->get('tickets.fio')) . '</th><th>' . pm_ticket_h(Sogerien::Lang()->get('tickets.subject')) . '</th><th>' . pm_ticket_h(Sogerien::Lang()->get('tickets.message')) . '</th>';
    echo '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $tv = $row['table_value'] ?? [];
        $uv = $row['user_value'] ?? [];
        if (is_string($tv)) {
            $tv = json_decode($tv, true) ?: [];
        }
        if (is_string($uv)) {
            $uv = json_decode($uv, true) ?: [];
        }
        $link = ($isAdminPage || str_starts_with($path, 'admin/tickets')) ? '/admin/ticket?id=' : '/support/ticket?id=';
        echo '<tr onclick="location.href=\'' . pm_ticket_h($link . (string)$row['id']) . '\'">';
        echo '<td>' . pm_ticket_h($row['id'] ?? '') . '</td>';
        echo '<td>' . pm_ticket_h($tv['client_id'] ?? '') . '</td>';
        echo '<td>' . pm_ticket_h($uv['fio'] ?? $uv['login'] ?? '') . '</td>';
        echo '<td>' . pm_ticket_h($tv['title'] ?? $row['name'] ?? '') . '</td>';
        echo '<td>' . pm_ticket_h($tv['last_message'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

echo '</section></main>';
$tpl->footer();
