# Fluence — Creator Tracker

Plain PHP 8 + MySQL. No framework, no Composer, no Node build, so it runs on Hostinger shared hosting without a VPS.

## Roles
- **Super Admin**: sees every creator, edits any tracker, and manages all logins (Users page).
- **Admin**: sees the dashboard grid and every creator's tracker, read-only, and can post feedback. Set `admin_can_edit => true` in `config.php` to allow editing.
- **Creator**: logs in straight to their own tracker and fills in their monthly numbers, step status and notes.

## What's inside (v2)
- **Dashboard** — month KPIs (revenue vs last month, net sub growth, average roadmap progress, how many creators logged), search (`/`), filter chips by phase or "needs update", sort, grid/list view, 6-month sparklines, a quick panel per creator, a CSV export, and a recent-activity feed.
- **Creator tracker** — progress ring, goal tiles with month-over-month deltas and click-to-edit targets, autosaving monthly numbers with last month's values as hints and a "Copy from last month" button, a phase stepper with a "Next up" step, per-month notes, a **feedback thread** (team ↔ creator), charts with hover tooltips, history, and activity.
- **Users** — search, role filters, creator profile fields (handle, niche, channel, program start), and a copyable credentials card after you create a login or reset a password.
- Keyboard: `/` jumps to search, `←` `→` switch month; on a step, `Enter` cycles forward and `Backspace` (or right-click) cycles back.

## Upgrading
Upload the new files over the old ones, keeping your `config.php`. The database upgrades itself on the next page load, and your existing data stays.

## How month-based data works
- **Monthly numbers** (sponsor rev, info rev, subscribers, hours/week, income streams, last meeting) are stored once per creator per month.
- **Step status** carries forward. A month with nothing saved shows the latest earlier month's steps. The first change saves a full snapshot for that month, so you can look back at any month.
- **Phase notes** are per month.
- **Goal targets** are per creator, not per month.

## Deploy on Hostinger
1. In hPanel, go to **Databases → MySQL Databases** and create a database and user. Note the DB name, user and password.
2. Upload the folder contents to `public_html` (or a subfolder) with File Manager, or deploy with Git.
3. Copy `config.sample.php` to `config.php` and fill in the DB details. `config.php` is git-ignored, so create it on the server.
4. Open `https://yourdomain.com/install.php`, then create the Super Admin.
5. **Delete `install.php`** from the server.
6. Log in, go to **Users**, and add admins and creators.

The installer creates the tables. If you'd rather import them yourself, use `schema.sql` in phpMyAdmin, then run `install.php` to create the first login.

## Local development (VS Code)
In `config.php`, set `'driver' => 'sqlite'`, then run:

```
php -S localhost:8000
```

Open http://localhost:8000/install.php. Data is saved to `data/fluence.sqlite`.

## Files
| File | What it does |
|---|---|
| `inc/phases.php` | Roadmap phases and steps. Edit titles freely, but don't rename a `key` once data exists. |
| `inc/progress.php` | Progress maths, month carry-forward, dashboard card data |
| `inc/auth.php` | Login, roles, permissions, CSRF |
| `dashboard.php` + `assets/dashboard.js` | Admin grid and pop-up panel |
| `creator.php` + `assets/tracker.js` | Full tracker: numbers, goals, path, charts, history |
| `api.php` | JSON autosave and feedback endpoint used by the tracker |
| `export.php` | CSV export of a month (staff only) |
| `inc/schema.php` | Tables and the automatic migration (bump `SCHEMA_VERSION` when you add columns) |
| `users.php` | Super Admin login management |
| `account.php` | Change own name and password |
| `assets/app.css` | All styles (same dark theme as the original HTML) |
