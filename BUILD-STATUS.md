# KDNA Events v1.1 Brief A, Build Status

Version target: **1.1.0**
Branch: `claude/review-kdna-emails-brief-JpO3o`

## Scope

Brief A, Branded HTML Email Templates + Event Header Image, plus the
design tweaks called out directly on `KDNA Events Email Template.pdf`
(uploaded to `main` by KDNA).

## Files created

- `kdna-events/templates/emails/css/email.css`
- `kdna-events/templates/emails/partials/doctype-head.php`
- `kdna-events/templates/emails/partials/preheader.php`
- `kdna-events/templates/emails/partials/logo.php`
- `kdna-events/templates/emails/partials/event-image.php`
- `kdna-events/templates/emails/partials/event-details.php`
- `kdna-events/templates/emails/partials/virtual-button.php`
- `kdna-events/templates/emails/partials/footer.php`
- `docs/email-booking-confirmation-reference.pdf` (copied from KDNA Events Email Template.pdf for future regression checks)

## Files modified

- `kdna-events/kdna-events.php` (version bump, image size registration, activation regen schedule)
- `kdna-events/readme.txt` (stable tag + changelog + upgrade notice)
- `kdna-events/composer.json` / `composer.lock` / `vendor/` (pelago/emogrifier + deps)
- `kdna-events/includes/class-kdna-events-cpt.php` (registered `_kdna_event_image`, `_kdna_event_email_heading`, `_kdna_event_email_subject`, `_kdna_event_email_content_1`, `_kdna_event_email_content_2`, `_kdna_event_email_footer_text`)
- `kdna-events/includes/class-kdna-events-admin.php` (Email Header Image picker + Email Overrides section in Event Details meta box; wp.media enqueue)
- `kdna-events/includes/class-kdna-events-settings.php` (new Email Design tab with Brand, Colours, Typography, Layout, Virtual Event Button, Content, Footer sections, live iframe preview, test-send field, schema-driven defaults + sanitiser, Google Font helpers, asset enqueue with wp-color-picker + wp.media; Emails tab now holds the moved send-related controls plus the new reply-to field and no longer registers/renders the deprecated v1.0 body textarea)
- `kdna-events/includes/helpers.php` (`kdna_events_get_email_header_image_url`, `kdna_events_regenerate_email_image_crops`, `kdna_events_mix_hex`, `kdna_events_render_merge_tags`)
- `kdna-events/includes/class-kdna-events-emails.php` (full rewrite: new `build_context`, `render_booking_confirmation_html`, `render_admin_notification_html`, `render_preview`, `get_sample_context`, `get_sample_admin_rows`, `strip_html_to_text`, `send_mail` with multipart/alternative via `phpmailer_init`, AJAX endpoints `kdna_events_send_test_email`, `kdna_events_preview_email`, `kdna_events_preview_test_send`, filter `kdna_events_email_attachments` applied before each `wp_mail`)
- `kdna-events/widgets/class-widget-success-tickets.php` (action `kdna_events_after_success_ticket` added inside per-ticket loop)
- `kdna-events/widgets/class-widget-my-tickets.php` (action `kdna_events_after_my_ticket` added inside per-ticket loop)
- `kdna-events/assets/js/kdna-events-admin.js` (wp.media picker wiring for Email Header Image + Email Design media fields, wp-color-picker init, form-change event bridging for live preview)
- `kdna-events/assets/css/kdna-events-admin.css` (Email Header Image preview styles, Email Design tab grid / sticky preview panel / sections)
- `kdna-events/docs/DEVELOPER.md` (documented the three new hooks, full merge tag list, Email Design architecture, `kdna_events_get_email_header_image_url`, v1.1.0 header bump)
- `kdna-events/templates/emails/booking-confirmation.php` (full rewrite, table-based layout matching the PDF exactly)
- `kdna-events/templates/emails/admin-notification.php` (full rewrite, compact table-based admin layout with shared logo + footer)

## Deviations from the brief

1. **No 'View My Tickets' CTA in the booking confirmation.** Brief
   Section 5 Block 7 specifies a bulletproof CTA labelled 'View My
   Tickets'. The KDNA Events Email Template.pdf supplied as the
   visual source of truth does not include that button; it only
   shows the Virtual Event link pill. Per the brief's conflict rule
   (PDF wins on visual decisions), the button is omitted for Brief A.
   The client confirmed 'View My Tickets' is reserved for Brief B.

2. **No social icons repeater in the footer.** Brief Section 4 lists
   Social links as a repeater (Facebook, Instagram, etc.). The PDF
   footer shows only plain-text fine print, so the repeater is
   omitted for Brief A. The hook surface stays clean for Brief B to
   add it without a schema change.

3. **Event hero image is the top of the content card, not a separate
   block below a header.** Brief Section 5 Block 2 defines a
   separate 'Header' block with the logo and a coloured
   background. The PDF puts the logo ABOVE the card on the grey
   page background and treats the event image as the hero at the
   top of the white card. The PDF wins and the templates reflect
   that.

4. **Tickets list block omitted.** Brief Section 5 Block 6 shows a
   'Your Tickets' list inside the email with per-ticket cards.
   The PDF does not render a ticket list, only a single
   `{ticket_code}` tag through content 1. The ticket list is
   retained in the admin notification table (where organisers need
   it) and omitted from the customer email. Per-ticket emails via
   the existing 'Per-attendee emails' toggle still render with the
   correct attendee name and code because `build_context` resolves
   those tags per recipient.

5. **Admin notification reference PDF not provided.** Only one PDF
   was supplied (customer-facing booking confirmation). The admin
   template follows the brief's Section 6 prescription with the
   same card / logo / footer frame so branding is consistent. Can
   be revisited when an admin PDF reference lands.

6. **Existing `kdna_events_booking_email_body` option is ignored.**
   The registration was dropped in v1.1 as the brief directs. The
   stored value is NOT deleted, so downgrading to v1.0 still works.

## Live preview + test send

- Email Design tab renders a two-tab live preview (Booking
  Confirmation / Admin Notification) in an iframe via srcdoc. Every
  form field carries `data-kdna-preview-key` so any change
  triggers a 300ms-debounced AJAX re-render against
  `wp_ajax_kdna_events_preview_email`. The preview uses
  `KDNA_Events_Emails::get_sample_context()` so it never touches
  real orders.
- Below the iframe, a 'Send test to inbox' email input + button
  sends the currently previewed template to any address via
  `wp_ajax_kdna_events_preview_test_send`. Saved settings are used,
  so saving before clicking the button gives you a pixel-accurate
  real-inbox copy.
- The v1.0 'Send test email' button on the Emails tab still works
  and now routes through the v1.1 template system.

## Testing matrix

**Not yet exercised across the full client matrix.** This build is
a code-complete Brief A; the cross-client rendering matrix (Gmail
web / iOS / Android, Apple Mail macOS / iOS, Outlook 365 web,
Outlook Desktop Windows, Outlook Mac, Outlook iOS, Yahoo) must be
run on a live WordPress install with SMTP configured before the
build can be declared shippable to production. I do not have a
running WordPress environment or email relay in this session.

What's verified locally:

- All touched PHP files pass `php -l`.
- Templates render without notices when included with a
  representative `$design`, `$context`, `$summary_rows` and
  `$attendee_rows`.
- Emogrifier is resolvable from the committed `vendor/` tree.
- wp.media and wp-color-picker are enqueued on the settings screen.
- The helper fallback cascade (event image, plugin default,
  empty string) is covered by explicit branches in
  `kdna_events_get_email_header_image_url`.

Recommended pre-release steps before tagging 1.1.0:

1. Enable SMTP on a staging site, place a free and a paid (Stripe
   test mode) booking, verify the branded email arrives.
2. Run the Email Design tab's 'Send test to inbox' against one
   address per client in the matrix; capture screenshots into
   `docs/testing-screenshots/`.
3. Specifically confirm Outlook Desktop Windows renders the VML
   virtual button with matching colours and shape, and that the
   hero image does not gap at the top of the white card.
4. Verify the preheader shows in the Gmail / Apple Mail inbox list
   but is invisible when the email is opened.
5. Toggle Apple Mail into dark mode and confirm the dark-mode
   overrides in `email.css` keep the design legible.

## Known limitations / follow-ups for Brief B

- Bundle PDF tickets via the new `kdna_events_email_attachments`
  filter.
- Bring back the 'View My Tickets' button as an optional block with
  its own style controls.
- Add a social-icons footer repeater once the design calls for it.
- Consider a secondary 'Add to calendar' partial powered by the new
  per-ticket actions.
