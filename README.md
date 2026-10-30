# Fluence — Creator Tracker

Plain PHP 8 + MySQL. No framework, no Composer, no Node build, so it runs on Hostinger shared hosting without a VPS.

## Roles
- **Super Admin**: sees every creator, edits any tracker, and manages all logins (Users page).
- **Admin**: sees the dashboard grid and every creator's tracker, with read-only monthly numbers by default, can post feedback, and can manage each creator’s Destination goals and Path. Set `admin_can_edit => true` in `config.php` to allow editing.
- **Creator**: logs in straight to their own tracker and fills in their monthly numbers, step status and notes.

## What's inside (v3)
- **Dashboard** — month KPIs (revenue vs last month, net sub growth, average roadmap progress, how many creators logged), search (`/`), filter chips by phase or "needs update", sort, grid/list view, 6-month sparklines, a quick panel per creator, a CSV export, and a recent-activity feed.
- **Creator tracker** — progress ring, goal tiles with month-over-month deltas and click-to-edit targets, autosaving monthly numbers with last month's values as hints and a "Copy from last month" button, a phase stepper with a "Next up" step, per-month notes, a **feedback thread** (team ↔ creator), charts with hover tooltips, history, and activity.
- **Users** — search, role filters, creator profile fields (handle, niche, channel, program start), and a copyable credentials card after you create a login or reset a password.
- Keyboard: `/` jumps to search, `←` `→` switch month; on a step, `Enter` cycles forward and `Backspace` (or right-click) cycles back.

## Upgrading
Upload the new files over the old ones, keeping your `config.php`. The database upgrades itself on the next page load, and your existing data stays.

## How month-based data works
- **Monthly numbers** (sponsor rev, info rev, subscribers, income streams, last meeting) are stored once per creator per month.
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
| `planner.php` + `inc/plans.php` | Per-creator goal and nested path editor, types and results |
| `api.php` | JSON autosave and feedback endpoint used by the tracker |
| `export.php` | CSV export of a month (staff only) |
| `inc/schema.php` | Tables and the automatic migration (bump `SCHEMA_VERSION` when you add columns) |
| `users.php` | Super Admin login management |
| `account.php` | Change own name and password |
| `assets/app.css` | All styles (same dark theme as the original HTML) |

## Custom destinations and paths

Staff can open a creator tracker and select **Edit destinations & path**. Both Admins and Super Admins can add, edit, reorder, and remove goals, phases, tasks, and nested subtasks. Each creator has a separate plan. Types include checkbox, text, money/budget, and number, with optional targets and instructions.

Creators enter results and changes save automatically. Numeric and money task percentages come from result ÷ target: zero is To do, partial values are In progress, and reaching the target is Complete. Checkbox tasks use a completion toggle. Text tasks complete when the expected answer matches (case-insensitive), or when filled if no expected answer is set. Numeric tasks without a positive target remain In progress until an admin adds a target. Parent tasks summarize the smallest subtasks, which drive phase and dashboard progress using partial percentages as well as completed tasks. Goal results are monthly; task status and evidence carry forward. Existing monthly-number goals remain linked to monthly entries. Only staff can change targets or plan structure.

Definitions apply across months. Removing an item hides it from the current plan while retaining saved history in the database. Existing roadmap keys, notes, targets, and progress are preserved when customizing a creator for the first time. Database schema v3 adds two tables automatically, without deleting existing data.

## Automatic social channel stats (Apify)

Admins and creators can link multiple public Instagram, YouTube, TikTok, and Facebook accounts by handle/profile URL. Open **Manage channels** on a creator tracker, or **Account → Manage my channels**. The tracker displays latest follower/subscriber counts, supported secondary metrics, last update, daily history and growth. Counts are provider-reported public stats, which can be rounded or unavailable. This does not verify account ownership and does not provide private analytics.

Add this inside the existing returned array in the Git-ignored **config.local.php**, separately on each server:

```php
'apify' => [
    'token' => 'YOUR_APIFY_API_TOKEN',
    'refresh_hours' => 24,
    'max_run_cost_usd' => 0.05,
],
```

Do not replace your database settings, upload the local SQLite override to Hostinger, or commit the token. No token is rendered in HTML or placed in API URLs. PHP cURL and outbound HTTPS access to `api.apify.com` are required.

### Schedule on Hostinger

Open **Account → Social sync setup** as Super Admin to copy the server's exact cron command. Add a **custom cron job** in Hostinger hPanel every five minutes (`*/5 * * * *`):

```sh
php /absolute/path/to/public_html/scripts/sync-social.php
```

The job processes up to 20 due accounts per pass, starts asynchronous Apify jobs, and collects completed runs on subsequent passes. Each channel refreshes every 24 hours by default, including when nobody is logged in. The app shows the last scheduler heartbeat so a missing/stopped job is visible. The script is CLI-only. Linking/refreshing starts a first run immediately; the channel screen polls existing runs without starting additional paid jobs. Opening a page does not replace the scheduled job.

Manual refreshes are limited to once per hour per channel. Paused channels and disabled creators are excluded from scheduled work. Concurrent jobs use per-account database locks. Provider failures retain previous stats and retry later; transient collection failures reuse the existing run. Daily snapshots are stored separately from manually entered monthly numbers. Removing a channel deletes its snapshot history.

Each run requests one profile/video result, times out after 180 seconds, and passes the configured charge ceiling to Apify. `maxItems` and monetary ceilings depend on the actor's pricing model; set account-level spending limits in Apify too. Required actor subscriptions/permissions and credits must be available. The default actors are `apify/instagram-profile-scraper`, `streamers/youtube-channel-scraper`, `clockworks/tiktok-profile-scraper`, and `apify/facebook-pages-scraper`.

Tests (no network requests or production data changes): `php tests/social.php`; optional local MySQL: `FL_TEST_MYSQL=1 php tests/social.php`. Schema v4 adds social account/snapshot tables automatically, preserving existing trackers.

### Compact path view

Phases start collapsed, showing their title, overall progress, and a horizontal row of immediate task percentages. **Expand details** reveals task inputs, nested subtasks, instructions, and monthly notes. Selecting a task in the summary expands and focuses its detail. Numeric progress is calculated on both client and server; a submitted status cannot override the value. Phase, parent, overall, dashboard, and CSV percentages use the same smallest-task averages. Values just below a target never display 100% prematurely. Previously saved checkbox completion is retained, and earlier numeric statuses are recalculated from saved evidence.

## Meeting logs

Each creator tracker has a **Meetings** section at the bottom and a shortcut in the tracker navigation. Admins and the creator can log the meeting date, attendees, discussion, and action items. Logs are shown across all months, newest first, with ten per page. Creators can edit/remove their own logs; Admins and Super Admins can manage any log. Editing preserves the original author and rejects stale updates from other tabs. Text is escaped on output and all writes require CSRF protection.

The latest logged meeting date is included in dashboard/CSV **Last meeting**, alongside older manually entered dates, respecting the selected dashboard month. Creating a meeting does not create or overwrite monthly number entries. Schema v5 adds a meetings table without deleting existing data. Validation/permissions tests: `php tests/meetings.php` or `FL_TEST_MYSQL=1 php tests/meetings.php` for local MySQL.

## Destination goals on the dashboard

Creator cards and list rows show current results against targets and progress bars for up to three Destination goals. Open a creator's dashboard overview to see every goal, including custom money, numeric, text, and checkbox goals. Goal progress is separate from Path progress and uses the selected month. Linked revenue/subscriber/income-stream goals use that month's numbers; custom goals use that month's saved results. Missing results remain blank rather than showing a fabricated zero. Percentages follow the same automatic status rules as the tracker. Tests: `php tests/dashboard-goals.php` (also supports local `FL_TEST_MYSQL=1`).
