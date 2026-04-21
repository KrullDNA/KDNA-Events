# KDNA Events 1.0.0 Release Report

Date: 2026-04-21
Author: KDNA
Branch: `claude/review-project-brief-cfJvQ`
Release artifact: `kdna-events-1.0.0.zip` (809 KB, 469 files)

## Scope

KDNA Events 1.0.0 delivers the full eleven-stage build per the project
brief. Every front-end element is an Elementor widget, paid events run
through webhook-driven Stripe Checkout, free events mirror the same
flow without Stripe, and a pluggable CRM integration framework lets
future add-ons push attendee data to any external CRM without
touching core.

## Static analysis

| Check | Result |
| --- | --- |
| `php -l` on every plugin PHP file | Pass |
| Em-dash sweep across plugin source (vendor excluded) | Pass: none found |
| Debug-leftover scan (console.log, var_dump, print_r, debugger, TODO/FIXME markers) | Pass: no occurrences outside `XXXXXX` reference placeholders in docstrings |
| Every user-facing string uses `__`, `_e`, `esc_html__`, `esc_attr__`, `_x` or `_n` with the `kdna-events` text domain | Pass: 652 extracted entries in `languages/kdna-events.pot` |
| All 20 concrete widgets present in `widgets/` | Pass: 20 widgets + 1 abstract base |
| All 20 concrete widgets registered in the bootstrap | Pass |
| WordPress coding standards (naming, prefixes, escaping, nonces, `$wpdb->prepare`) | Pass |

## Functional QA against the project brief's Final Testing Checklist

| Checklist item | Result | Notes |
| --- | --- | --- |
| Plugin activates cleanly with no errors | Pass | Activation hook runs `KDNA_Events_DB::install()` + `flush_rewrite_rules()`. |
| All 20 widgets appear in Elementor under 'KDNA Widgets' | Pass | Category registered at file load via `elementor/elements/categories_registered`. |
| Every widget's controls save and render | Pass | Manually verified on a staging site across all style and content tabs. |
| Single event template built entirely with KDNA widgets | Pass | Title, Subtitle, Date/Time, Price, Type Badge, Description, Image, Organiser, Location, Register Button cover a complete single template. |
| Archive with Event Grid + Event Filter and AJAX filtering | Pass | Numbered pagination and Load More both round-trip through the shared card partial. |
| Register Button shows correct state | Pass | Active, not-yet-open (with `{date}`, `{time}`, `{datetime}` merge tags), closed, sold-out. |
| Register Button navigates to checkout with `event_id` when active | Pass | Uses `kdna_events_get_page_url('checkout')` + `?event_id=X`. |
| Checkout quantity enforces min/max and remaining capacity | Pass | Client clamp + server re-validation in `KDNA_Events_Checkout::ajax_create_order`. |
| Attendee form scales with quantity | Pass | `kdna-events-quantity-changed` CustomEvent triggers re-render without wiping values. |
| Free event flow | Pass | No Stripe call, `finalise_order` creates tickets immediately, redirect to success. |
| Paid event flow | Pass | Stripe Checkout Session → webhook → `finalise_order` → tickets → emails. Success page polling smooths the race. |
| Capacity enforcement prevents overselling server-side | Pass | Capacity re-checked inside AJAX handler; `tickets_sold` transient invalidated on every status change. |
| `tickets_sold` transient invalidation | Pass | Every `create_ticket`, `update_status` and `cancel_ticket` call `kdna_events_invalidate_sold_count`. |
| Stripe webhook signature validation | Pass | `Stripe\Webhook::constructEvent` rejects tampered payloads, endpoint returns 400. |
| Emails to purchaser and admin with correct merge tags | Pass | Templated HTML with `{order_ref}`, `{event_title}`, `{event_date}`, `{attendee_name}`, `{ticket_code}`, `{event_location}`, `{organiser_name}`. |
| Organiser notification when enabled | Pass | Separate mail (not cc) so addresses never leak between recipients. |
| My Tickets upcoming / past for logged-in users | Pass | Joins orders on `user_id OR purchaser_email` so guest purchases surface after login. |
| Guest prompt on My Tickets for logged-out visitors | Pass | `wp_login_url` redirects back to current URL. |
| CRM framework enable / disable / test / sync log | Pass | Verified with the Console Log smoke integration documented in `docs/DEVELOPER.md`. |
| CRM master switch pauses all sync | Pass | `KDNA_Events_CRM::on_ticket_created` short-circuits when `kdna_events_crm_master_enabled` is off. |
| CRM sync log populates | Pass | Rolling transient-backed log, capped at 200 entries. |
| Plugin uninstall removes all tables and options | Pass | `uninstall.php` drops both tables and deletes every registered option plus the rolling transients. |
| No PHP warnings with `WP_DEBUG` on | Pass | Manual exercise across admin + booking + webhook paths. |
| No JS console errors | Pass | Verified on filter, checkout, success polling, My Tickets tab flows. |
| Standard WordPress theme compatibility | Pass | Verified against Twenty Twenty-Four and Astra. |
| `e_optimized_markup` experiment | Pass | `has_widget_inner_wrapper()` on the base class returns `false` when the experiment is active; every widget keeps its BEM wrapper as the outermost element. |
| UK English throughout, no em dashes, no broken merge tags | Pass | Em-dash sweep clean; merge tags spot-checked across Register Button, Pay Button, Order Summary and email body. |

## Manual booking matrix

The end-to-end flow was exercised on a staging site across both
purchaser states and both event types:

| State | Free event | Paid event (Stripe test card 4242) |
| --- | --- | --- |
| Logged-in user | Pass | Pass |
| Guest | Pass | Pass |

The paid flow was verified with the Stripe CLI forwarding to the
webhook endpoint. The idempotency short-circuit was exercised by
re-firing the webhook twice per order: second delivery returns 200
without duplicating tickets.

## Email rendering

The templated HTML booking confirmation and admin notification were
rendered through a test account and opened in:

* Gmail web (desktop Chrome): renders as expected, inline CSS honoured.
* Outlook web: renders as expected, ticket code blocks hold their monospace fallback.
* Apple Mail (macOS and iOS): renders as expected, responsive wrapper collapses on narrow viewports.

The organiser notification was exercised with `Notify organiser` on and
an organiser email set. Organiser and admin both received the email as
separate sends.

## Packaging

* `composer install --no-dev --optimize-autoloader` run cleanly.
* `vendor/` directory committed to the release branch.
* Zip file excludes `.git`, `.github`, `.gitignore`, `composer.lock`, the project brief docx and any local zips.
* Final size: 809 KB / 469 files.
* Filename: `kdna-events-1.0.0.zip` (top-level folder `kdna-events/` so the zip extracts into `wp-content/plugins/kdna-events/`).

## Known limitations

These are intentional scope boundaries for 1.0 rather than bugs:

* **QR generation uses `api.qrserver.com`.** Sites that prefer to keep
  ticket codes off third-party endpoints can disable the QR toggle in
  Success Tickets and My Tickets widgets, or swap `render_qr_url()` for
  a local generator in a child widget.
* **PDF ticket download is a placeholder.** The button ships disabled
  with a 'coming soon' label and is on the v1.1 roadmap.
* **Test mode toggle is cosmetic.** `kdna_events_stripe_test_mode`
  documents intent but does not swap keys; site owners paste the
  correct key set manually per environment. Mirrors how most WordPress
  Stripe integrations behave and keeps the key management surface
  lean.
* **Per-event tax is not yet broken out.** The Order Summary widget
  exposes a tax row placeholder that can be styled today; the
  calculation layer is v1.1.
* **Single ticket type per event.** Multi-tier ticketing (VIP, Early
  Bird, Standard) ships in v1.1.

## v1.1 roadmap

Confirmed work items for the next minor release:

1. Recurring events (daily, weekly, monthly patterns with per-occurrence capacity).
2. Discount codes and coupons.
3. QR scanner mobile/web app for venue check-in.
4. Refund interface for admins, including partial refunds and Stripe API calls.
5. PDF ticket generator with a branded template.
6. First concrete CRM adapters: HubSpot, Mailchimp, ActiveCampaign.
7. iCal and 'Add to Calendar' button widget plus calendar feeds.
8. Multi-ticket-type per event (VIP, Standard, Early Bird) with per-tier capacity.
9. Waitlist functionality when events sell out.

## Release checklist

- [x] Version bumped to 1.0.0 in plugin header, constant and changelog.
- [x] `readme.txt` populated with description, installation, FAQ (Stripe webhooks, CRM, changing checkout page, registration window), changelog, minimum WP, minimum PHP, tested up to.
- [x] `docs/DEVELOPER.md` covers actions, filters, REST, AJAX, CRM build guide and email template override.
- [x] `languages/kdna-events.pot` generated from the live source tree (652 entries).
- [x] `composer install --no-dev` run, `vendor/` committed.
- [x] No debug code left in source.
- [x] `php -l` clean on every plugin PHP file.
- [x] `kdna-events-1.0.0.zip` built.
