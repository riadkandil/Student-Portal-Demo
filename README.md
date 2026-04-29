## Project Description
This is a **student Portal demo**

- 🔹 **Microservice decomposition** — three independent services, each with a single bounded responsibility
- 🔹 **Polyglot persistence** — three different database engines, each chosen to fit its data
- 🔹 **Database-per-service** — no service ever touches another service's database

## Architecture Overview

The system is composed of **four runnable processes** plus three databases:

| Component | Type | Role |
|-----------|------|------|
| Frontend | Static HTML+CSS+JS | Single-page app served by Apache |
| Gateway | PHP (HTTP) | Single entry point, routes to services |
| Auth Service | PHP (HTTP) | User authentication |
| Courses Service | PHP (HTTP) | Course catalog and enrollment logic |
| Notifications Service | PHP (HTTP) | Reads the event queue for the UI |
| Worker | PHP (CLI) | Drains the queue asynchronously |
| Auth DB | Supabase (Cloud PostgreSQL) | Users |
| Courses DB | MySQL (XAMPP) | Courses + enrollments |
| Notifications DB | SQLite (file-based) | Event queue |

## Polyglot Persistence
the practice of using multiple, distinct database technologies to handle different data storage needs within a single application or system.

Each service owns its own database, and each database is a **different engine** chosen to fit its data:

| Service | Database | Why this engine? |
|---------|----------|-----------------|
| Auth | **Supabase** (Cloud PostgreSQL via REST API) | Demonstrates calling a hosted SaaS over HTTP — no DB driver needed |
| Courses | **MySQL** (local XAMPP) | The classic relational DB; uses native MySQLi driver for direct connection |
| Notifications | **SQLite** (file-based) | Zero-config, file-based — perfect for a local queue/log without standing up another server |

This isn't gratuitous variety — it's deliberate. Each service can pick the **storage technology that best fits its access pattern**. Auth is small and read-heavy; SaaS REST is fine. Courses needs JOINs and transactions; SQL is right. Notifications are append-only event logs; an embedded file database is enough.

The price you pay is operational complexity — three databases means three sets of credentials, three failure modes, three backup procedures. **Polyglot persistence is a tradeoff, not a free lunch.** This project shows both sides.

## The Three Services

### 1. Auth Service (`auth-service/auth.php`)

**Single endpoint:** `POST /login`

Validates credentials against the users table and returns a token. The token is a `base64(JSON)` payload containing `{ id, name, email, role }` — deliberately not a JWT, to keep the demo dependency-free and educational. Production systems should use JWTs with proper signing.

This service **only** connects to its own user store. It has no knowledge of courses, enrollments, or notifications.

### 2. Courses Service (`courses-service/courses.php`)

**Five endpoints** covering the full course lifecycle:

| Method | Route | Purpose |
|--------|-------|---------|
| `GET`  | `/courses`      | List all courses (public) |
| `POST` | `/courses`      | Create a course (instructor only) |
| `POST` | `/enroll`       | Enroll in a course (student only) |
| `GET`  | `/my-courses`   | List a student's enrollments (JOIN) |
| `POST` | `/drop`         | Drop a course (student only) |

This is where the **asynchronous pattern** shows up: when a student enrolls or drops, the service writes to its own database, **then writes a separate event** into the notifications database queue, and returns to the user immediately. It does not wait for any notification to be delivered.

### 3. Notifications Service (`notifications-service/notifications.php` + `worker.php`)

This service has two completely independent components:

- **HTTP endpoint** (`notifications.php`): GET `/notifications` returns the queue for display in the UI

## Tech Stack

| Layer | Choice | Reason |
|-------|--------|--------|
| Web server | Apache (XAMPP) | Default, zero-config |
| Backend | PHP 8 | Available everywhere, no compilation |
| Auth DB | Supabase | Cloud PostgreSQL accessible via REST (no driver) |
| Courses DB | MySQL 8 / MariaDB | XAMPP's built-in DB |
| Notifications DB | SQLite | Embedded, file-based, accessible via PDO driver |
| Frontend | Vanilla HTML/CSS/JS | No build step, no framework |
