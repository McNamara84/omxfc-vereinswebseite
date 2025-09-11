CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "current_team_id" integer not null default '1',
  "profile_photo_path" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "two_factor_secret" text,
  "two_factor_recovery_codes" text,
  "two_factor_confirmed_at" datetime,
  "vorname" varchar not null,
  "nachname" varchar not null,
  "strasse" varchar not null,
  "hausnummer" varchar not null,
  "plz" varchar not null,
  "stadt" varchar not null,
  "land" varchar not null,
  "telefon" varchar,
  "verein_gefunden" varchar,
  "mitgliedsbeitrag" numeric not null default '12',
  "einstiegsroman" varchar,
  "lesestand" varchar,
  "lieblingsroman" varchar,
  "lieblingsfigur" varchar,
  "lieblingsmutation" varchar,
  "lieblingsschauplatz" varchar,
  "lieblingsautor" varchar,
  "lieblingszyklus" varchar,
  "mitglied_seit" date,
  "bezahlt_bis" date,
  "notify_new_review" tinyint(1) not null default '0',
  "last_activity" integer,
  "lieblingsthema" varchar,
  "lat" numeric,
  "lon" numeric,
  "lieblingshardcover" varchar,
  "lieblingscover" varchar
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "personal_access_tokens"(
  "id" integer primary key autoincrement not null,
  "tokenable_type" varchar not null,
  "tokenable_id" integer not null,
  "name" varchar not null,
  "token" varchar not null,
  "abilities" text,
  "last_used_at" datetime,
  "expires_at" datetime,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "personal_access_tokens_tokenable_type_tokenable_id_index" on "personal_access_tokens"(
  "tokenable_type",
  "tokenable_id"
);
CREATE UNIQUE INDEX "personal_access_tokens_token_unique" on "personal_access_tokens"(
  "token"
);
CREATE TABLE IF NOT EXISTS "teams"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "personal_team" tinyint(1) not null,
  "created_at" datetime,
  "updated_at" datetime,
  "description" text,
  "email" varchar,
  "meeting_schedule" varchar,
  "logo_path" varchar
);
CREATE INDEX "teams_user_id_index" on "teams"("user_id");
CREATE TABLE IF NOT EXISTS "team_user"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "user_id" integer not null,
  "role" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "team_user_team_id_user_id_unique" on "team_user"(
  "team_id",
  "user_id"
);
CREATE TABLE IF NOT EXISTS "team_invitations"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "email" varchar not null,
  "role" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE UNIQUE INDEX "team_invitations_team_id_email_unique" on "team_invitations"(
  "team_id",
  "email"
);
CREATE TABLE IF NOT EXISTS "user_points"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "team_id" integer not null,
  "todo_id" integer,
  "points" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("todo_id") references "todos"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "todo_categories"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "slug" varchar not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "todo_categories_slug_unique" on "todo_categories"("slug");
CREATE TABLE IF NOT EXISTS "todos"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "created_by" integer not null,
  "assigned_to" integer,
  "verified_by" integer,
  "title" varchar not null,
  "description" text,
  "points" integer not null default('0'),
  "status" varchar not null default('open'),
  "completed_at" datetime,
  "verified_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "category_id" integer,
  foreign key("verified_by") references users("id") on delete set null on update no action,
  foreign key("assigned_to") references users("id") on delete set null on update no action,
  foreign key("created_by") references users("id") on delete cascade on update no action,
  foreign key("team_id") references teams("id") on delete cascade on update no action,
  foreign key("category_id") references "todo_categories"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "kassenbuch_entries"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "created_by" integer not null,
  "buchungsdatum" date not null,
  "betrag" numeric not null,
  "beschreibung" varchar not null,
  "typ" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("created_by") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "kassenstand"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "betrag" numeric not null default '0',
  "letzte_aktualisierung" date not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "missions"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "origin" varchar not null,
  "destination" varchar not null,
  "travel_duration" integer not null,
  "mission_duration" integer not null,
  "started_at" datetime,
  "arrival_at" datetime,
  "mission_ends_at" datetime,
  "completed" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "book_offers"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "series" varchar not null,
  "book_number" integer not null,
  "book_title" varchar not null,
  "condition" varchar not null,
  "photos" text,
  "completed" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "book_requests"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "series" varchar not null,
  "book_number" integer not null,
  "book_title" varchar not null,
  "condition" varchar not null,
  "completed" tinyint(1) not null default '0',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "book_swaps"(
  "id" integer primary key autoincrement not null,
  "offer_id" integer not null,
  "request_id" integer not null,
  "completed_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  "offer_confirmed" tinyint(1) not null default '0',
  "request_confirmed" tinyint(1) not null default '0',
  foreign key("offer_id") references "book_offers"("id") on delete cascade,
  foreign key("request_id") references "book_requests"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "books"(
  "id" integer primary key autoincrement not null,
  "roman_number" integer not null,
  "title" varchar not null,
  "author" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  "type" varchar not null default 'Maddrax - Die dunkle Zukunft der Erde'
);
CREATE TABLE IF NOT EXISTS "reviews"(
  "id" integer primary key autoincrement not null,
  "team_id" integer not null,
  "user_id" integer not null,
  "book_id" integer not null,
  "title" varchar not null,
  "content" text not null,
  "deleted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("team_id") references "teams"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("book_id") references "books"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "review_comments"(
  "id" integer primary key autoincrement not null,
  "review_id" integer not null,
  "user_id" integer not null,
  "parent_id" integer,
  "content" text not null,
  "deleted_at" datetime,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("review_id") references "reviews"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("parent_id") references "review_comments"("id") on delete cascade
);
CREATE INDEX "team_user_role_index" on "team_user"("role");
CREATE INDEX "team_user_team_id_role_index" on "team_user"("team_id", "role");
CREATE TABLE IF NOT EXISTS "activities"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "subject_type" varchar not null,
  "subject_id" integer not null,
  "created_at" datetime,
  "updated_at" datetime,
  "action" varchar,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "users_last_activity_index" on "users"("last_activity");
CREATE TABLE IF NOT EXISTS "page_visits"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "path" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "admin_messages"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "message" varchar not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "audiobook_episodes"(
  "id" integer primary key autoincrement not null,
  "episode_number" varchar not null,
  "title" varchar not null,
  "author" varchar not null,
  "status" varchar not null,
  "responsible_user_id" integer,
  "progress" integer not null default '0',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "planned_release_date" varchar,
  "roles_total" integer not null default '0',
  "roles_filled" integer not null default '0',
  foreign key("responsible_user_id") references "users"("id") on delete set null
);
CREATE UNIQUE INDEX "audiobook_episodes_episode_number_unique" on "audiobook_episodes"(
  "episode_number"
);
CREATE UNIQUE INDEX "books_roman_number_type_unique" on "books"(
  "roman_number",
  "type"
);
CREATE TABLE IF NOT EXISTS "audiobook_roles"(
  "id" integer primary key autoincrement not null,
  "episode_id" integer not null,
  "name" varchar not null,
  "description" text,
  "takes" integer not null default '0',
  "user_id" integer,
  "speaker_name" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("episode_id") references "audiobook_episodes"("id") on delete cascade,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "audiobook_roles_name_user_speaker_index" on "audiobook_roles"(
  "name",
  "user_id",
  "speaker_name"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2025_03_18_193704_add_two_factor_columns_to_users_table',1);
INSERT INTO migrations VALUES(5,'2025_03_18_193733_create_personal_access_tokens_table',1);
INSERT INTO migrations VALUES(6,'2025_03_18_193733_create_teams_table',1);
INSERT INTO migrations VALUES(7,'2025_03_18_193734_create_team_user_table',1);
INSERT INTO migrations VALUES(8,'2025_03_18_193735_create_team_invitations_table',1);
INSERT INTO migrations VALUES(9,'2025_03_23_041007_add_fields_to_users_table',1);
INSERT INTO migrations VALUES(10,'2025_03_23_131556_create_default_admin_and_team',1);
INSERT INTO migrations VALUES(11,'2025_03_30_024554_add_seriendaten_to_users_table',1);
INSERT INTO migrations VALUES(12,'2025_04_01_114641_add_mitglied_seit_to_users_table',1);
INSERT INTO migrations VALUES(13,'2025_04_01_122159_add_bezahlt_bis_to_users_table',1);
INSERT INTO migrations VALUES(14,'2025_04_01_183444_create_todos_table',1);
INSERT INTO migrations VALUES(15,'2025_04_01_183510_create_user_points_table',1);
INSERT INTO migrations VALUES(16,'2025_04_02_095145_create_todo_categories_table',1);
INSERT INTO migrations VALUES(17,'2025_04_07_131350_create_kassenbuch',1);
INSERT INTO migrations VALUES(18,'2025_04_09_153050_create_missions_table',1);
INSERT INTO migrations VALUES(19,'2025_04_18_160246_create_book_offers_table',1);
INSERT INTO migrations VALUES(20,'2025_04_18_160303_create_book_requests_table',1);
INSERT INTO migrations VALUES(21,'2025_04_18_160322_create_book_swaps_table',1);
INSERT INTO migrations VALUES(22,'2025_05_18_065853_create_books_table',1);
INSERT INTO migrations VALUES(23,'2025_05_18_070005_create_reviews_table',1);
INSERT INTO migrations VALUES(24,'2025_07_17_132020_create_review_comments',1);
INSERT INTO migrations VALUES(25,'2025_07_19_180314_add_notify_new_review_to_users_table',1);
INSERT INTO migrations VALUES(26,'2025_07_26_145711_add_confirmation_flags_to_book_swaps_table',1);
INSERT INTO migrations VALUES(27,'2025_07_27_044954_add_indexes_to_team_user_table',1);
INSERT INTO migrations VALUES(28,'2025_07_27_134700_create_activities_table',1);
INSERT INTO migrations VALUES(29,'2025_07_27_151556_add_action_to_activities_table',1);
INSERT INTO migrations VALUES(30,'2025_07_30_000000_add_fields_to_teams_table',1);
INSERT INTO migrations VALUES(31,'2025_08_02_181313_add_last_activity_to_users_table',1);
INSERT INTO migrations VALUES(32,'2025_08_02_205523_add_lieblingsthema_to_users_table',1);
INSERT INTO migrations VALUES(33,'2025_08_23_175217_add_lat_lon_to_users_table',1);
INSERT INTO migrations VALUES(34,'2025_09_01_000000_create_page_visits_table',1);
INSERT INTO migrations VALUES(35,'2025_09_02_155207_create_admin_messages_table',1);
INSERT INTO migrations VALUES(36,'2025_09_15_000000_create_audiobook_episodes_table',1);
INSERT INTO migrations VALUES(37,'2025_09_16_000000_change_planned_release_date_column_type',1);
INSERT INTO migrations VALUES(38,'2025_09_17_000000_add_roles_columns_to_audiobook_episodes_table',1);
INSERT INTO migrations VALUES(39,'2025_09_18_000000_update_status_enum_in_audiobook_episodes_table',1);
INSERT INTO migrations VALUES(40,'2025_09_19_000000_add_type_to_books_table',1);
INSERT INTO migrations VALUES(41,'2025_09_20_000000_add_lieblingshardcover_to_users_table',1);
INSERT INTO migrations VALUES(42,'2025_09_20_000001_add_lieblingscover_to_users_table',1);
INSERT INTO migrations VALUES(43,'2025_09_21_000000_create_audiobook_roles_table',1);
INSERT INTO migrations VALUES(44,'2025_09_22_000000_add_name_user_speaker_index_to_audiobook_roles_table',1);
INSERT INTO migrations VALUES(45,'2025_10_01_000000_update_book_type_enum',1);
