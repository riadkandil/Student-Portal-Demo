CREATE TABLE users (
    id       BIGSERIAL PRIMARY KEY,
    name     TEXT NOT NULL,
    email    TEXT NOT NULL UNIQUE,
    password TEXT NOT NULL,
    role     TEXT NOT NULL CHECK (role IN ('student', 'instructor'))
);

INSERT INTO users (name, email, password, role) VALUES
    ('Student User',  'student@test.com', 'admin', 'student'),
    ('Teacher Smith', 'teacher@test.com', 'admin', 'instructor');
