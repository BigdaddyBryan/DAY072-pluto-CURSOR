BEGIN TRANSACTION;

CREATE TABLE IF NOT EXISTS "users_groups" (
    "user_id" INTEGER NOT NULL,
    "group_id" INTEGER NOT NULL,
    UNIQUE ("user_id", "group_id")
);

CREATE TABLE IF NOT EXISTS "users_tags" (
    "user_id" INTEGER NOT NULL,
    "tag_id" INTEGER NOT NULL,
    UNIQUE ("user_id", "tag_id")
);

CREATE TABLE IF NOT EXISTS "archive" (
    "id" INTEGER NOT NULL,
    "table_name" TEXT,
    "title" TEXT,
    "table_id" INTEGER,
    "created_at" DATE,
    "creator" INTEGER,
    "modifier" INTEGER,
    "modified_at" DATE,
    "date" DATE,
    "second_table_id" INTEGER,
    "shortlink" TEXT,
    "url" TEXT,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "groups" (
    "id" INTEGER NOT NULL,
    "title" TEXT,
    "image" TEXT,
    "description" TEXT,
    "creator" INTEGER,
    "created_at" DATE,
    "modifier" INTEGER,
    "modified_at" DATE,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "link_groups" (
    "link_id" INTEGER NOT NULL,
    "group_id" INTEGER NOT NULL,
    UNIQUE ("link_id", "group_id")
);

CREATE TABLE IF NOT EXISTS "link_tags" (
    "link_id" INTEGER NOT NULL,
    "tag_id" INTEGER NOT NULL,
    UNIQUE ("link_id", "tag_id")
);

CREATE TABLE IF NOT EXISTS "links" (
    "id" INTEGER NOT NULL,
    "title" TEXT,
    "shortlink" TEXT,
    "url" TEXT,
    "creator" TEXT,
    "created_at" DATE,
    "modifier" TEXT,
    "modified_at" DATE,
    "status" INTEGER DEFAULT 0,
    "visit_count" INTEGER,
    "last_visited_at" DATE,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "tags" (
    "id" INTEGER NOT NULL,
    "title" TEXT,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "user_links" (
    "user_id" INTEGER,
    "link_id" INTEGER
);

CREATE TABLE IF NOT EXISTS "users" (
    "id" INTEGER NOT NULL,
    "email" TEXT,
    "password" TEXT,
    "name" TEXT,
    "family_name" TEXT,
    "picture" TEXT,
    "role" TEXT,
    "creator" INTEGER,
    "created_at" DATE,
    "modifier" INTEGER,
    "modified_at" INTEGER,
    "last_login" DATE,
    "mode" TEXT,
    "view" TEXT,
    "limit" INTEGER,
    "sort_preference" TEXT DEFAULT 'latest_modified',
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "visitors" (
    "id" INTEGER NOT NULL,
    "name" TEXT,
    "ip" TEXT,
    "browser" TEXT,
    "modifier" INTEGER,
    "modified_at" DATE,
    "last_visit" INTEGER,
    "created_at" DATE,
    "visit_count" INTEGER,
    "last_visit_date" DATE,
    PRIMARY KEY ("id")
);

CREATE TABLE IF NOT EXISTS "visitors_groups" (
    "visitor_id" INTEGER,
    "group_id" INTEGER,
    UNIQUE ("visitor_id", "group_id")
);

CREATE TABLE IF NOT EXISTS "visitors_tags" (
    "visitor_id" INTEGER,
    "tag_id" INTEGER,
    UNIQUE ("visitor_id", "tag_id")
);

CREATE TABLE IF NOT EXISTS "visits" (
    "id" INTEGER NOT NULL,
    "ip" TEXT,
    "date" DATE,
    "link_id" INTEGER,
    "visitor_id" INTEGER,
    "shortlink_used" TEXT,
    "referer" TEXT,
    "user_agent" TEXT,
    PRIMARY KEY ("id")
);

COMMIT;