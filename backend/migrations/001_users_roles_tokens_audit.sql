-- Users, roles, refresh tokens and the audit log.
-- All DATETIME columns hold UTC (the app sets the session time zone to +00:00).

CREATE TABLE users (
    id                   CHAR(36)     NOT NULL PRIMARY KEY,
    kerberos_id          VARCHAR(32)  NOT NULL UNIQUE,
    name                 VARCHAR(200) NOT NULL,
    iitd_email           VARCHAR(200) NOT NULL UNIQUE,
    department           VARCHAR(100) NULL,
    year_of_study        SMALLINT     NULL,
    phone                VARCHAR(20)  NULL,
    alt_email            VARCHAR(200) NULL,
    hostel               VARCHAR(50)  NULL,
    codeforces           VARCHAR(50)  NULL,
    codechef             VARCHAR(50)  NULL,
    avatar_url           VARCHAR(500) NULL,
    bank_details_on_file TINYINT(1)   NOT NULL DEFAULT 0,
    is_active            TINYINT(1)   NOT NULL DEFAULT 1,  -- 0 = banned; data is kept
    last_login_at        DATETIME(6)  NULL,
    created_at           DATETIME(6)  NOT NULL,
    updated_at           DATETIME(6)  NOT NULL,
    KEY ix_users_name (name),
    KEY ix_users_hostel (hostel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    id         CHAR(36)    NOT NULL PRIMARY KEY,
    user_id    CHAR(36)    NOT NULL,
    role       VARCHAR(32) NOT NULL,
    granted_by CHAR(36)    NULL,
    granted_at DATETIME(6) NOT NULL,
    UNIQUE KEY uq_user_role (user_id, role),
    CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_roles_granted_by FOREIGN KEY (granted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refresh_tokens (
    id         CHAR(36)     NOT NULL PRIMARY KEY,
    user_id    CHAR(36)     NOT NULL,
    token_hash CHAR(64)     NOT NULL UNIQUE,
    expires_at DATETIME(6)  NOT NULL,
    revoked_at DATETIME(6)  NULL,
    user_agent VARCHAR(300) NULL,
    ip         VARCHAR(64)  NULL,
    created_at DATETIME(6)  NOT NULL,
    KEY ix_refresh_tokens_user (user_id),
    CONSTRAINT fk_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id          CHAR(36)    NOT NULL PRIMARY KEY,
    actor_id    CHAR(36)    NULL,
    action      VARCHAR(64) NOT NULL,   -- e.g. role.set, user.update
    entity_type VARCHAR(64) NOT NULL,
    entity_id   VARCHAR(64) NOT NULL,
    `before`    JSON        NULL,
    `after`     JSON        NULL,
    ip          VARCHAR(64) NULL,
    created_at  DATETIME(6) NOT NULL,
    KEY ix_audit_action (action),
    KEY ix_audit_entity (entity_type, entity_id),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
