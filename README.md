# Fluence — Creator Tracker

Plain PHP 8 + MySQL. No framework, no Composer, no Node build, so it runs on Hostinger shared hosting without a VPS.

## Roles
- **Super Admin**: sees every creator, edits any tracker, and manages all logins (Users page).
- **Admin**: sees the dashboard grid and every creator's tracker, with read-only monthly numbers by default, can post feedback, and can manage each creator’s Destination goals and Path. Set `admin_can_edit => true` in `config.php` to allow editing.
- **Creator**: logs in straight to their own tracker and fills in their monthly numbers, step status and notes.

## What's inside (v3)
- **Dashboard** — month KPIs (revenue vs last month, net sub growth, average roadmap progress, how many creators logged), search (`/`), filter chips by phase or "needs update", sort, grid/list view, 6-month sparklines, a quick panel per creator, a CSV export, and a recent-activity feed.
- **Creator tracker** — progress ring, goal tiles with month-over-month deltas and click-to-edit targets, autosaving monthly numbers with last month's values as hints and a "Copy from last month" button, a phase stepper with a "Next up" step, per-month notes, a **feedback thread** (team ↔ creator), charts with hover tooltips, history, and activity.
- **Users** — search, role filters, creator goals and linked social profiles, and a copyable credentials card after you create a login or reset a password.
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

Creators enter results and changes save automatically. Numeric and money task percentages come from result ÷ target: zero is To do, partial values are In progress, and reaching the target is Complete. Checkbox tasks use a completion toggle. Text tasks complete whenever a nonempty result is entered. Numeric tasks without a positive target remain In progress until an admin adds a target. Parents with a positive numeric or money target use their own entered result to drive progress; their subtasks remain independently editable. Other parent tasks summarize their subtasks. Goal results are monthly; task status and evidence carry forward. Existing monthly-number goals remain linked to monthly entries. Only staff can change targets or plan structure.

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

Phases start collapsed, showing their title, overall progress, and a horizontal row of immediate task percentages. **Expand details** reveals task inputs, nested subtasks, instructions, and monthly notes. Selecting a task in the summary expands and focuses its detail. Numeric progress is calculated on both client and server; a submitted status cannot override the value. Phase, overall, dashboard, and CSV percentages average outcome tasks; numeric parents with targets count once in place of their descendants. Values just below a target never display 100% prematurely. Previously saved checkbox completion is retained, and earlier numeric statuses are recalculated from saved evidence.

## Meeting logs

Each creator tracker has a **Meetings** section at the bottom and a shortcut in the tracker navigation. Admins and the creator can log the meeting date, attendees, discussion, and action items. Logs are shown across all months, newest first, with ten per page. Creators can edit/remove their own logs; Admins and Super Admins can manage any log. Editing preserves the original author and rejects stale updates from other tabs. Text is escaped on output and all writes require CSRF protection.

The latest logged meeting date is included in dashboard/CSV **Last meeting**, alongside older manually entered dates, respecting the selected dashboard month. Creating a meeting does not create or overwrite monthly number entries. Schema v5 adds a meetings table without deleting existing data. Validation/permissions tests: `php tests/meetings.php` or `FL_TEST_MYSQL=1 php tests/meetings.php` for local MySQL.

## Destination goals on the dashboard

Creator cards and list rows show current results against targets and progress bars for up to three Destination goals. Open a creator's tracker to see every goal, including custom money, numeric, text, and checkbox goals. Goal progress is separate from Path progress and uses the selected month. Linked revenue/subscriber/income-stream goals use that month's numbers; custom goals use that month's saved results. Missing results remain blank rather than showing a fabricated zero. Percentages follow the same automatic status rules as the tracker. Tests: `php tests/dashboard-goals.php` (also supports local `FL_TEST_MYSQL=1`).

## Creator setup from Users & Logins

Super Admins can configure Destination goals and multiple social profiles while creating a creator login, or edit them later in the same user's expanded row. **Add goal** adds a title, type, target, and optional instructions. Existing linked goals retain their monthly-number sources and stable keys. **Add social profile** adds a platform selector plus public profile link/handle; rows can be edited or removed. A shortcut opens the full phase/nested-task editor after creation.

The user, targets, and social-profile changes are saved atomically. Validation failures preserve entered fields. A complete-form marker prevents silently truncated large forms from removing saved rows. Plan revision and profile identity checks reject stale edits. Removing goals retains stored results. Removing/changing a profile archives the old identity and preserves its history; a new identity never inherits another profile's stats. Re-adding the same archived identity restores its own history. Public counts are not proof of account ownership.

New profiles queue a first sync after the user is saved, without blocking account creation on provider network requests. The browser starts these jobs through an authenticated, CSRF-protected endpoint; the scheduled worker is the fallback. Normal login-management permissions are unchanged: Super Admins manage logins, while Admins can still edit plans and social channels from creator trackers.

Creator dashboard cards and the tracker include **Social analytics · latest**. The overview shows every fetched metric (followers/subscribers, supported post/video counts, likes, channel views), growth, and sparklines. Actual capture times, stale/missing values, errors, and paused accounts are identified. **Refresh stats** requests an update subject to the existing hourly limit. Public scraper counts may be rounded, hidden, or temporarily unavailable; unknown values remain blank, and failures do not replace a last successful count with zero. Hostinger still requires the Apify token and cron job configured on that server for unattended updates.

Tests: `php tests/creator-details.php` or `FL_TEST_MYSQL=1 php tests/creator-details.php`.

Creator setup forms now show basic login information, then **Social profiles**, then **Destination goals**. Legacy handle, niche, channel-link, and program-start inputs are removed from creation/edit forms and Account profile settings. Existing stored metadata is preserved when saving the simplified forms.

Existing creator rows keep social-profile editing inline, but Destination goals are now a compact heading with an **Edit goals & path** link to that creator's plan editor. Initial goals remain editable in the new-user form. Saving basic info/social profiles preserves goals when their inline fields are absent.


### Recovering missed first syncs

The tracker and social-channel page check active profiles for missed first jobs and due updates through the authenticated/CSRF-protected status endpoint (`sync_due=1`). Jobs are started in bounded batches, existing runs are collected without duplicating paid jobs, and the hourly limit/backoff still applies. Tracker cards poll automatically and include **Sync now**. A server without an Apify token displays **Setup required**, rather than an indefinite Awaiting first sync. The cron job remains required for unattended updates when nobody is viewing the app. Tests: `php tests/social-first-sync.php`.

### X (Twitter)

X is available in all social-platform selectors. Enter `@handle`, `https://x.com/handle`, or a `twitter.com` profile link; stored links are normalized to `https://x.com/handle`. The integration uses `apidojo/twitter-user-scraper` with follower-list, following-list, retweeter, and extra-about extraction explicitly disabled. It requests a single profile result within the existing per-run spending cap. Followers and total posts are stored in daily history and shown on the tracker/dashboard. Favorites/likes-given are deliberately not presented as likes received. The same schedule, fallback polling, hourly limit, profile identity checks, and error handling apply. Actor access, credits, and provider limits still apply. Tests: `php tests/social-x.php` (also supports local MySQL).

## Creator navigation and admin access

Dashboard cards and list rows open the creator tracker directly. Admins and superadmins can use **Edit creator** beside the back button to update basic information and repeatable social profiles, with a link to edit destinations and the path. Only superadmins can change login activation.

**Log in as creator** opens an active creator account without changing its password. A banner provides **Return to admin account**, preserving the selected month. Switching requires a CSRF-protected POST, rotates the session and CSRF token, and records the original admin in activity logs. Creator permissions apply during the session; staff accounts cannot be impersonated. Each request revalidates the original admin account, and a removed, disabled, or promoted creator ends the creator session.

## Local Grok assistant preview

The bottom-right assistant opens a compact chat window on every authenticated local page. Admins can ask across creators without a creator selector; each included creator contributes their plan, results, selected-month notes, up to 12 monthly entries, 10 latest meetings and latest saved social snapshots. The first 50 creators are included and incomplete coverage must be identified. Oversized contexts are rejected explicitly. It does not inspect source files. Creators are forced to their own ID server-side. Data is fetched on each question; the model receives no database credentials or arbitrary SQL tools. Responses are rendered as plain text. Up to 20 separate chats (24 recent messages each) stay in sessionStorage for the browser tab, scoped to the signed-in identity. The header + creates a new chat and the selector switches conversations. Enter submits; Shift+Enter inserts a newline. There are no Send or Clear buttons. The assistant uses the current month automatically without a month selector.

In ignored `config.local.php`, add a top-level entry alongside the existing database/Apify settings:

```php
'grok' => [
    'enabled' => true,
    'api_key' => '', // Add your own xAI API key locally, or set XAI_API_KEY.
    'model' => 'grok-4.7', // Use a Responses API model available to your account.
],
```

Click **Ask Grok** after logging in at `http://localhost:8000`. Old `assistant.php` bookmarks redirect to the global widget. PHP cURL and paid API access are required for real answers. Tracker records are sent to xAI only when a question is submitted. Requests use the Responses API with `store=false`, a bounded context and output, a 55-second timeout and a five-second session throttle. This is a local preview: both page and endpoint require an enabled flag **and a loopback client IP**, ignoring Host/forwarded headers. Do not enable it behind a production reverse proxy that presents remote traffic as loopback. Deploying the source alone does not enable the feature; production config remains untouched. Test: `php tests/assistant.php`.

## Overall creator overview and editable numbers

Staff see a cross-month **Creator overview** on every creator tracker: all-time recorded revenue, latest recorded audience, current Path progress, meeting count/date and the latest saved result against each Destination target. It does not depend on the month selector. Missing months are not estimated; monthly revenue goals use their latest month’s revenue rather than the all-time total.

Numbers uses admin-defined labels for linked audience and income-stream goals, and also includes editable result fields for every manual Destination goal. Goal targets remain editable in the plan editor. For parent steps such as Halloween Sale, configure Number and a target of 10 in the plan editor, then enter 6 in the tracker to show 60%, while keeping the promotional subtasks. Tests: `php tests/parent-metrics.php` and `php tests/creator-overview.php`.

## Overall vision and monthly execution boards

Creator pages open on **Overview**, for admins and creators. This view has no month selector: its vision, goals, targets and current results are stored independently of monthly entries. It shows the full Path, all-time revenue, latest audience, meetings and social channels. Admins use **Edit overall vision** to maintain the macro goals and their results. No monthly targets are silently copied into the overall vision.

**Monthly view** has the month selector and existing tracker tools. **Plan this month** sets a focus statement, that month’s goal targets, and the main Path steps being worked on (including their descendants). Monthly goal results are entered in Numbers. If a month has no explicit board, existing goal definitions and the full Path remain visible for compatibility; saving a monthly board makes its targets and selected work independent. Macro goals remain independent unless a monthly goal is explicitly linked; linked progress follows the overall goal’s calculation mode. Path evidence still carries forward because completing a project step is lasting progress.

Schema v6 adds `creator_boards` without deleting existing plans or results. Board writes check revision numbers. Tests: `php tests/boards.php`, `php tests/creator-overview.php`; browser checks cover macro/monthly separation, monthly focus, 60% result persistence and responsive views.

## Monthly contributions to overall goals

In **Plan this month**, select **Contributes to** for each monthly goal. Links must belong to the same creator and use the same value type. Each overall goal accepts one monthly goal per month to prevent duplicate counting.

In **Edit overall vision**, choose its progress calculation: **Manual result**, **Starting balance + monthly results** for numeric/money contributions (such as sales), or **Latest monthly result** for snapshots (such as audience size). Existing goals stay manual until an admin chooses an automatic mode. Targets remain independent; changing a monthly target never changes the macro target.

The Overview calculates linked results on each load and offers a monthly contribution breakdown linking back to the source tracker. Editing a past month recalculates totals rather than incrementing them. Blank results are ignored; explicit zeroes are counted. Deleting a macro goal leaves historical monthly results intact and removes that contribution from the macro view. Changing a linked goal to an incompatible value type is rejected. Test: `php tests/goal-rollups.php`.

## Configure Grok from the Super Admin account

Open **Account → Grok assistant**, paste an xAI API key, choose a model, enable the assistant and save. **Test saved connection** sends a small paid request without creator records. The field is always blank on reload; leaving it blank retains the existing key. **Remove saved key & disable** removes the stored key and disables access. Only active Super Admin sessions can save, test or remove settings; requests require CSRF protection.

Settings are written atomically to Git-ignored `config.grok.php` with owner-only permissions. This private PHP return file is also denied by Apache and overrides local config settings. PHP must have write permission to the project configuration directory. Preserve this file across deployments and back it up privately. Never commit it. Saving enabled settings through Account explicitly allows authenticated live website access, while config-only local previews remain restricted to loopback unless `allow_remote` is set. All existing creator access restrictions still apply to chatbot context. No valid key is bundled with the project.
