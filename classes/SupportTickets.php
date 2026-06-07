<?php
declare(strict_types=1);

final class SupportTickets
{
    use SogerienClassHelp;

    private string $db_alias = 'front';

    public function init_db_alias(string $db_alias): void
    {
        $db_alias = trim($db_alias);
        $this->db_alias = $db_alias !== '' ? $db_alias : 'front';
        $this->ensure_storage();
    }

    /**
     * @return array<string,mixed>
     */
    public function create_ticket(int $user_id, string $subject, string $department, string $message, array $meta = []): array
    {
        $subject = trim($subject);
        $department = trim($department);
        $message = trim($message);
        if ($user_id <= 0) {
            return ['ok' => false, 'error' => 'User is required.'];
        }
        if ($subject === '' || $message === '') {
            return ['ok' => false, 'error' => 'Subject and message are required.'];
        }
        if ($department === '') {
            $department = 'Technical support';
        }
        $priority = strtolower(trim((string)($meta['priority'] ?? 'normal')));
        if (!in_array($priority, ['low', 'normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $ticketId = 'tkt_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $now = date('c');
        $ticket = [
            'ticket_id' => $ticketId,
            'user_id' => $user_id,
            'department' => $department,
            'priority' => $priority,
            'service_id' => trim((string)($meta['service_id'] ?? '')),
            'service_title' => trim((string)($meta['service_title'] ?? '')),
            'subject' => $subject,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
            'closed_at' => '',
            'last_reply_by' => 'user',
            'messages' => [[
                'message_id' => 'msg_' . bin2hex(random_bytes(6)),
                'author_type' => 'user',
                'author_id' => $user_id,
                'body' => $message,
                'created_at' => $now,
            ]],
        ];
        $inserted = $this->insert_row('support_ticket', $ticketId, $subject, $ticket);
        if (!$inserted) {
            return ['ok' => false, 'error' => 'Ticket was not saved.'];
        }
        return ['ok' => true, 'ticket_id' => $ticketId];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_user_tickets(int $user_id): array
    {
        if ($user_id <= 0) {
            return [];
        }
        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = 'support_ticket'
              AND status <> 'delete'
              AND table_value->>'user_id' = :user_id
            ORDER BY updated_at DESC
            LIMIT 1000
        ", ['user_id' => (string)$user_id]);
        return $this->extract_value_rows($resp);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list_all_tickets(string $status = ''): array
    {
        $status = $this->normalize_status($status);
        $params = [];
        $where = '';
        if ($status !== '') {
            $where = " AND table_value->>'status' = :status";
            $params['status'] = $status;
        }
        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = 'support_ticket'
              AND status <> 'delete'
              {$where}
            ORDER BY updated_at DESC
            LIMIT 2000
        ", $params);
        return $this->extract_value_rows($resp);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get_ticket(string $ticket_id, int $user_id = 0, bool $admin = false): ?array
    {
        $ticket_id = trim($ticket_id);
        if ($ticket_id === '') {
            return null;
        }
        $ticket = $this->load_one('support_ticket', $ticket_id);
        if (!is_array($ticket)) {
            return null;
        }
        if (!$admin && (int)($ticket['user_id'] ?? 0) !== $user_id) {
            return null;
        }
        return $ticket;
    }

    /**
     * @return array<string,mixed>
     */
    public function add_message(string $ticket_id, int $author_id, string $author_type, string $body, bool $admin = false): array
    {
        $ticket = $this->get_ticket($ticket_id, $author_id, $admin);
        if (!is_array($ticket)) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'error' => 'Message is required.'];
        }

        $author_type = $admin ? 'admin' : 'user';
        $messages = isset($ticket['messages']) && is_array($ticket['messages']) ? $ticket['messages'] : [];
        $messages[] = [
            'message_id' => 'msg_' . bin2hex(random_bytes(6)),
            'author_type' => $author_type,
            'author_id' => $author_id,
            'body' => $body,
            'created_at' => date('c'),
        ];
        $ticket['messages'] = $messages;
        $ticket['last_reply_by'] = $author_type;
        $ticket['updated_at'] = date('c');
        if ($author_type === 'admin' && ($ticket['status'] ?? '') === 'open') {
            $ticket['status'] = 'review';
        }
        if (($ticket['status'] ?? '') === 'closed') {
            $ticket['status'] = $admin ? 'review' : 'open';
            $ticket['closed_at'] = '';
        }
        $this->update_json_row('support_ticket', $ticket_id, $ticket);
        return ['ok' => true];
    }

    /**
     * @return array<string,mixed>
     */
    public function set_status(string $ticket_id, string $status, int $user_id, bool $admin = false): array
    {
        $ticket = $this->get_ticket($ticket_id, $user_id, $admin);
        if (!is_array($ticket)) {
            return ['ok' => false, 'error' => 'Ticket not found.'];
        }
        $status = $this->normalize_status($status);
        if ($status === '') {
            return ['ok' => false, 'error' => 'Invalid status.'];
        }
        if (!$admin && !in_array($status, ['open', 'closed'], true)) {
            return ['ok' => false, 'error' => 'This status is not allowed for client.'];
        }
        $ticket['status'] = $status;
        $ticket['updated_at'] = date('c');
        $ticket['closed_at'] = $status === 'closed' ? date('c') : '';
        $this->update_json_row('support_ticket', $ticket_id, $ticket);
        return ['ok' => true];
    }

    public function status_label(string $status): string
    {
        return match ($this->normalize_status($status)) {
            'open' => 'Open',
            'review' => 'In review',
            'closed' => 'Closed',
            default => 'Unknown',
        };
    }

    private function normalize_status(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['open', 'review', 'closed'], true)) {
            return $status;
        }
        if (in_array($status, ['in_review', 'pending', 'processing'], true)) {
            return 'review';
        }
        return '';
    }

    private function ensure_storage(): void
    {
        $this->sql("
            CREATE TABLE IF NOT EXISTS sogerien (
                id bigserial PRIMARY KEY,
                table_name text NOT NULL DEFAULT '',
                table_index text NOT NULL DEFAULT '',
                name text NOT NULL DEFAULT '',
                status text NOT NULL DEFAULT 'active',
                table_value jsonb NOT NULL DEFAULT '{}'::jsonb,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            )
        ", []);
        $this->sql("CREATE INDEX IF NOT EXISTS sogerien_support_ticket_idx ON sogerien (table_name, table_index)", []);
        $this->sql("CREATE INDEX IF NOT EXISTS sogerien_support_ticket_ticket_id_idx ON sogerien ((table_value->>'ticket_id')) WHERE table_name = 'support_ticket'", []);
    }

    /**
     * @param array<string,mixed> $value
     */
    private function insert_row(string $table_name, string $table_index, string $name, array $value): bool
    {
        $resp = $this->sql("
            INSERT INTO sogerien (table_name, table_index, name, status, table_value, created_at, updated_at)
            VALUES (:table_name, to_jsonb(:table_index::text), :name, 'active', :table_value::jsonb, now(), now())
        ", [
            'table_name' => $table_name,
            'table_index' => $table_index,
            'name' => $name,
            'table_value' => $value,
        ]);
        return ($resp['result'] ?? false) === true && (int)($resp['rowCount'] ?? 0) > 0;
    }

    /**
     * @param array<string,mixed> $value
     */
    private function update_json_row(string $table_name, string $table_index, array $value): void
    {
        $this->sql("
            UPDATE sogerien
            SET table_value = :table_value::jsonb, updated_at = now()
            WHERE id = (
                SELECT id FROM sogerien
                WHERE table_name = :table_name
                  AND status <> 'delete'
                  AND (
                      table_index = to_jsonb(:table_index::text)
                      OR table_value->>'ticket_id' = :table_index
                  )
                ORDER BY id DESC
                LIMIT 1
            )
        ", [
            'table_name' => $table_name,
            'table_index' => $table_index,
            'table_value' => $value,
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function load_one(string $table_name, string $table_index): ?array
    {
        $resp = $this->sql("
            SELECT table_value
            FROM sogerien
            WHERE table_name = :table_name
              AND status <> 'delete'
              AND (
                  table_index = to_jsonb(:table_index::text)
                  OR table_value->>'ticket_id' = :table_index
              )
            ORDER BY id DESC
            LIMIT 1
        ", ['table_name' => $table_name, 'table_index' => $table_index]);
        $row = ($resp['rows'] ?? [])[0] ?? null;
        return is_array($row) && isset($row['table_value']) && is_array($row['table_value']) ? $row['table_value'] : null;
    }

    /**
     * @param array<string,mixed> $resp
     * @return array<int,array<string,mixed>>
     */
    private function extract_value_rows(array $resp): array
    {
        $rows = [];
        foreach (($resp['rows'] ?? []) as $row) {
            if (isset($row['table_value']) && is_array($row['table_value'])) {
                $rows[] = $row['table_value'];
            }
        }
        return $rows;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    private function sql(string $sql, array $params): array
    {
        $json = Sogerien::DbController()->sql_request($this->db_alias, ['sql' => $sql, 'params' => $params]);
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : ['result' => false, 'rows' => []];
    }
}
