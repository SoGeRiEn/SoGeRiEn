<?php exit();

 ?>

CREATE TABLE IF NOT EXISTS sogerien (
    id           BIGSERIAL PRIMARY KEY,

    table_name   TEXT        NOT NULL,
    table_index  TEXT        NULL,

    table_value  JSONB       NOT NULL DEFAULT '{}'::jsonb,

    status       SMALLINT    NOT NULL DEFAULT 1,

    created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ускорение выборок по типам сущностей
CREATE INDEX IF NOT EXISTS sogerien_table_name_idx ON sogerien(table_name);

-- уникальные "ключи" там, где это нужно (логин, email, sku и т.д.)
CREATE UNIQUE INDEX IF NOT EXISTS sogerien_uniq_name_index
ON sogerien(table_name, table_index)
WHERE table_index IS NOT NULL;

-- jsonb поиск
CREATE INDEX IF NOT EXISTS sogerien_value_gin
ON sogerien USING GIN (table_value);
CREATE INDEX idx_sogerien_table_name
ON sogerien (table_name);

Что в sogerien хранить (минимальные сущности)
user - ФИО, логин, пароль-хэш, контакты, язык, таймзона
access - роли, права, связи user->role->scope (как ты и хочешь)
contractor - подрядчики прокси (если есть)
product - типы прокси/пакеты (SKU), параметры
price_plan - прайс-листы и правила цены (но не факты продаж)
currency - справочник валют и правила округления
payment_method - провайдеры оплат, настройки (но не транзакции)


-- 1) Таблица sogerien
CREATE TABLE IF NOT EXISTS sogerien (
id           BIGSERIAL PRIMARY KEY,

table_name   TEXT        NOT NULL,        -- user, config, service, price, etc
name         TEXT        NULL,            -- например: credit_limit_default, partner_link, proxy_kiev_mob
table_index  JSONB       NULL,            -- например: {"user_id":40} или любой индекс-объект

table_value  JSONB       NOT NULL DEFAULT '{}'::jsonb,

status       TEXT        NOT NULL DEFAULT 'actual',  -- actual, archive, deleted, etc

created_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
updated_at   TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 2) Индексы под быстрые выборки
CREATE INDEX IF NOT EXISTS sogerien_table_name_idx
ON sogerien (table_name);

CREATE INDEX IF NOT EXISTS sogerien_table_name_name_idx
ON sogerien (table_name, name);

CREATE INDEX IF NOT EXISTS sogerien_status_idx
ON sogerien (status);

-- GIN индексы для JSONB
CREATE INDEX IF NOT EXISTS sogerien_table_index_gin
ON sogerien USING GIN (table_index);

CREATE INDEX IF NOT EXISTS sogerien_table_value_gin
ON sogerien USING GIN (table_value);

-- 3) Триггер на updated_at
CREATE OR REPLACE FUNCTION sogerien_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
NEW.updated_at = now();
RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_sogerien_set_updated_at ON sogerien;

CREATE TRIGGER trg_sogerien_set_updated_at
BEFORE UPDATE ON sogerien
FOR EACH ROW
EXECUTE FUNCTION sogerien_set_updated_at();




CREATE TABLE IF NOT EXISTS orders (
id              BIGSERIAL PRIMARY KEY,

-- 100% для связей и выборок
sogerien_id     BIGINT      NOT NULL,        -- user id в sogerien
by_service_id   BIGINT      NULL,            -- связь с services/by_services если применимо

-- типы как у тебя
order_type      SMALLINT    NOT NULL,        -- 1 real, 2 demo
order_kind      SMALLINT    NOT NULL,        -- 1 topup, 2 charge, 3 refund, 4 adjust
state           SMALLINT    NOT NULL DEFAULT 1,  -- 1 new, 2 pending, 3 paid, 4 failed, 5 refunded, 6 disputed, 7 canceled

-- деньги
amount          NUMERIC(18,6) NOT NULL,
currency        CHAR(3)     NOT NULL,        -- ISO 4217: USD, EUR...
fee_amount      NUMERIC(18,6) NULL,          -- комиссия провайдера/эквайринга (если известна сразу)
net_amount      NUMERIC(18,6) NULL,          -- amount - fee (если применимо)
exchange_rate   NUMERIC(18,8) NULL,          -- если была конвертация
amount_usd      NUMERIC(18,6) NULL,          -- если хочешь нормализовать в USD для аналитики

-- провайдер / идентификаторы
provider        TEXT        NOT NULL,        -- stripe, adyen, paypal, bank, manual, demo
provider_ref    TEXT        NULL,            -- payment_intent/charge_id/transaction_id
provider_ref2   TEXT        NULL,            -- иногда есть второй id (balance_txn, transfer_id, etc)
idempotency_key TEXT        NULL,            -- если ты используешь идемпотентность в API
external_ref    TEXT        NULL,            -- твой внешний референс (например "PM-2026-0001")

-- дата события у провайдера (важно отличать от created_at)
occurred_at     TIMESTAMPTZ NULL,

-- универсальная классификация платежного рельса (то, что есть у всех)
rail            TEXT        NOT NULL DEFAULT 'unknown', -- card, ach, wire, wallet, bank_transfer, cash, demo, manual
direction       SMALLINT    NOT NULL DEFAULT 1,         -- 1 inbound (пополнение), 2 outbound (выплата), 3 internal (корректировка)

-- стороны платежа (минимально универсально)
payer_name      TEXT        NULL,
payer_email     TEXT        NULL,
payer_phone     TEXT        NULL,

payee_name      TEXT        NULL,            -- обычно это твой мерчант/компания, но бывает marketplace
merchant_name   TEXT        NULL,
merchant_mcc    TEXT        NULL,            -- Merchant Category Code (часто приходит от провайдера)
merchant_country CHAR(2)    NULL,            -- ISO 3166-1 alpha-2
merchant_city   TEXT        NULL,

-- card fields (то, что чаще всего приходит у всех эквайеров)
card_brand      TEXT        NULL,            -- visa, mastercard, amex...
card_last4      CHAR(4)     NULL,
card_exp_month  SMALLINT    NULL,
card_exp_year   SMALLINT    NULL,
card_funding    TEXT        NULL,            -- credit/debit/prepaid
card_country    CHAR(2)     NULL,
card_fingerprint TEXT       NULL,            -- если провайдер даёт (Stripe даёт)
auth_code       TEXT        NULL,            -- authorization code
avs_result      TEXT        NULL,            -- AVS result code
cvv_result      TEXT        NULL,            -- CVV result code
eci             TEXT        NULL,            -- e-commerce indicator (если есть)
three_ds        TEXT        NULL,            -- frictionless/challenge/failed (если есть)
risk_score      NUMERIC(8,4) NULL,           -- если провайдер присылает риск-оценку
risk_level      TEXT        NULL,            -- low/normal/elevated/high

-- ACH/bank transfer fields (универсально для банковских интеграций в США)
bank_name       TEXT        NULL,
bank_country    CHAR(2)     NULL,
account_type    TEXT        NULL,            -- checking/savings
account_last4   CHAR(4)     NULL,
routing_last4   CHAR(4)     NULL,            -- не храним полный routing number
ach_sec_code    TEXT        NULL,            -- PPD/CCD/WEB/TEL (если есть)
trace_number    TEXT        NULL,            -- ACH trace number (если есть)
wire_imad       TEXT        NULL,            -- wire identifiers (если есть)
wire_omad       TEXT        NULL,

-- статусы банков/провайдера (универсально)
provider_status TEXT        NULL,            -- raw статус провайдера
failure_code    TEXT        NULL,
failure_message TEXT        NULL,

-- refunds/disputes (универсально для всех)
parent_order_id BIGINT      NULL,            -- если это refund - ссылка на исходный order
dispute_id      TEXT        NULL,
dispute_reason  TEXT        NULL,
dispute_amount  NUMERIC(18,6) NULL,

-- обязательные колонки как в sogerien
table_name      TEXT        NOT NULL DEFAULT 'orders',
name            TEXT        NULL,
table_index     JSONB       NULL,
table_value     JSONB       NOT NULL DEFAULT '{}'::jsonb,

status          TEXT        NOT NULL DEFAULT 'actual',

created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
updated_at      TIMESTAMPTZ NOT NULL DEFAULT now(),

-- связи
CONSTRAINT orders_order_type_chk CHECK (order_type IN (1,2)),
CONSTRAINT orders_order_kind_chk CHECK (order_kind IN (1,2,3,4)),
CONSTRAINT orders_state_chk CHECK (state IN (1,2,3,4,5,6,7)),
CONSTRAINT orders_direction_chk CHECK (direction IN (1,2,3)),
CONSTRAINT orders_currency_chk CHECK (currency ~ '^[A-Z]{3}$'),
CONSTRAINT orders_parent_fk FOREIGN KEY (parent_order_id) REFERENCES orders(id)
);

-- Индексы под реальную работу
CREATE INDEX IF NOT EXISTS orders_user_created_idx
ON orders (sogerien_id, created_at DESC);

CREATE INDEX IF NOT EXISTS orders_user_state_idx
ON orders (sogerien_id, state, created_at DESC);

CREATE INDEX IF NOT EXISTS orders_by_service_idx
ON orders (by_service_id, created_at DESC);

CREATE INDEX IF NOT EXISTS orders_provider_ref_idx
ON orders (provider, provider_ref);

CREATE UNIQUE INDEX IF NOT EXISTS orders_provider_ref_uniq
ON orders(provider, provider_ref)
WHERE provider_ref IS NOT NULL;

CREATE INDEX IF NOT EXISTS orders_occurred_at_idx
ON orders (occurred_at DESC);

CREATE INDEX IF NOT EXISTS orders_table_value_gin
ON orders USING GIN (table_value);

CREATE INDEX IF NOT EXISTS orders_table_index_gin
ON orders USING GIN (table_index);

-- updated_at trigger
CREATE OR REPLACE FUNCTION orders_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
NEW.updated_at = now();
RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_orders_set_updated_at ON orders;

CREATE TRIGGER trg_orders_set_updated_at
BEFORE UPDATE ON orders
FOR EACH ROW
EXECUTE FUNCTION orders_set_updated_at();


CREATE TABLE IF NOT EXISTS services (
id                 BIGSERIAL PRIMARY KEY,

-- 100% нужное для выборок/связей
sogerien_user_id   BIGINT      NOT NULL,     -- user id в sogerien
sogerien_service_id BIGINT     NOT NULL,     -- service id в sogerien (например service:8)
service_type       TEXT        NOT NULL,     -- proxy / sms / vpn / etc
server             TEXT        NOT NULL,     -- my / contractor / vendor_name / node_id

-- обязательные колонки как в sogerien
table_name         TEXT        NOT NULL DEFAULT 'services',
name               TEXT        NULL,
table_index        JSONB       NULL,
table_value        JSONB       NOT NULL DEFAULT '{}'::jsonb,

status             TEXT        NOT NULL DEFAULT 'actual',

created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
updated_at         TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Индексы
CREATE INDEX IF NOT EXISTS services_user_idx
ON services (sogerien_user_id, status);

CREATE INDEX IF NOT EXISTS services_service_idx
ON services (sogerien_service_id, status);

CREATE INDEX IF NOT EXISTS services_type_server_idx
ON services (service_type, server, status);

CREATE INDEX IF NOT EXISTS services_table_index_gin
ON services USING GIN (table_index);

CREATE INDEX IF NOT EXISTS services_table_value_gin
ON services USING GIN (table_value);

-- updated_at trigger
CREATE OR REPLACE FUNCTION services_set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
NEW.updated_at = now();
RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_services_set_updated_at ON services;

CREATE TRIGGER trg_services_set_updated_at
BEFORE UPDATE ON services
FOR EACH ROW
EXECUTE FUNCTION services_set_updated_at();



