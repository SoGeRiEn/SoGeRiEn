CREATE TABLE IF NOT EXISTS sogerien (
    id BIGSERIAL PRIMARY KEY,
    table_name TEXT NOT NULL,
    table_key TEXT NOT NULL DEFAULT '',
    table_value JSONB NOT NULL DEFAULT '{}'::jsonb,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS sogerien_table_name_idx ON sogerien (table_name);
CREATE INDEX IF NOT EXISTS sogerien_status_idx ON sogerien (status);
CREATE INDEX IF NOT EXISTS sogerien_table_value_gin_idx ON sogerien USING GIN (table_value);

INSERT INTO sogerien (id, table_name, table_key, table_value, status)
VALUES (
    1,
    'user',
    'admin',
    jsonb_build_object(
        'user_id', 1,
        'login', 'admin',
        'email', 'admin@example.com',
        'name', 'Default Admin',
        'roles', jsonb_build_object('admin', true, 'user', true),
        'user_group', jsonb_build_object('admin', true, 'user', true),
        'pass_hash', '$2b$12$/ktP6PCO7IzqG7VPqLcZLuFEsANmm6C1CKt5eDmp5VdH3oWXtH32m'
    ),
    'active'
)
ON CONFLICT (id) DO NOTHING;

INSERT INTO sogerien (table_name, table_key, table_value, status)
VALUES
('role', 'admin', '{"code":"admin","name":"Admin"}'::jsonb, 'active'),
('role', 'user', '{"code":"user","name":"User"}'::jsonb, 'active')
ON CONFLICT DO NOTHING;
