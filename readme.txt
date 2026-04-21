=== KDNA Events ===
Contributors: kdna
Tags: events, ticketing, elementor, stripe, bookings
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Events and ticketing built entirely on Elementor widgets, with Stripe Checkout for paid events and a pluggable CRM framework.

== Description ==

KDNA Events is a self-contained WordPress plugin for creating, displaying, and selling tickets to events. Every front-end element is delivered as a fully styleable Elementor widget, so you can build your event singles, archive, checkout, success and user-dashboard pages directly in the Elementor editor without dropping a single shortcode.

Paid events are processed through Stripe Checkout Sessions with webhook-driven fulfilment, so the buyer's tickets arrive even if they close the browser on the way back from Stripe. Free events run the same flow without Stripe, collecting attendee details and generating tickets identically. A pluggable CRM integration framework lets future add-on plugins push attendee data to any external CRM (HubSpot, Mailchimp, ActiveCampaign, Salesforce, custom in-house) without touching core.

= What ships in 1.0 =

* Custom Events post type with structured meta fields (dates, registration window, price, type, capacity, location, organiser).
* 20 Elementor widgets covering single-event display, archive listing and filtering, checkout, success and user dashboard.
* Google Maps integration for in-person and hybrid event locations.
* Stripe Checkout Sessions with signature-validated webhooks and an idempotent finalisation path.
* Multi-attendee checkout, name + email + phone + per-event custom fields per ticket.
* Min / max tickets per order, capacity caps, registration window controls, all re-validated server-side.
* Free event flow that mirrors paid checkout without Stripe.
* Templated HTML booking confirmation emails, optional per-attendee emails, and a separate organiser notification path.
* My Tickets user dashboard widget with Upcoming / Past tabs and a guest login prompt.
* Pluggable CRM integration framework with master switch, per-integration settings, Test Connection button and rolling sync log.

== Installation ==

1. Ensure Elementor is installed and active.
2. Upload the plugin folder to `/wp-content/plugins/` or install via the Plugins screen.
3. Run `composer install --no-dev` inside the plugin folder so the Stripe PHP SDK is available in `vendor/`. Pre-packaged release zips ship `vendor/` already.
4. Activate the plugin. An 'Events' menu appears in the WordPress admin sidebar.
5. Publish three empty pages to use as Checkout, Success and My Tickets. Assign them under Events, Settings, Pages.
6. For paid events, add your Stripe keys under Events, Settings, Stripe and register the webhook described below.

== Stripe Webhook Setup ==

KDNA Events relies on a webhook-driven flow so the order finalises even if the buyer closes the tab after paying.

1. In the Stripe Dashboard open Developers, Webhooks, Add endpoint.
2. Use the URL `https://<your-site>/wp-json/kdna-events/v1/stripe-webhook`.
3. Listen for these events:
   * checkout.session.completed (primary)
   * payment_intent.succeeded (defensive fallback)
4. After creating the endpoint, Stripe shows a signing secret starting with `whsec_`. Copy it.
5. Paste the signing secret into Events, Settings, Stripe, Webhook signing secret and save.

Local testing works with the Stripe CLI:

`stripe listen --forward-to https://<your-site>/wp-json/kdna-events/v1/stripe-webhook`

The handler validates the signature with `Stripe\Webhook::constructEvent` and returns 200 on success, 400 on signature failure, 500 on unexpected processing errors. It is idempotent: retries on an already finalised order return 200 without creating duplicate tickets.

== Frequently Asked Questions ==

= Does this plugin require Elementor? =

Yes. Every front-end element is an Elementor widget. The plugin self-deactivates with an admin notice if Elementor is not active.

= Does it work with free events? =

Yes. Set the event price to 0. The plugin skips Stripe entirely while still collecting attendee details, generating tickets and sending confirmation emails on the same code path as paid events.

= What happens if the buyer closes the browser after paying on Stripe? =

The Stripe webhook is the authoritative signal. The order finalises (tickets, emails, CRM sync) even if the redirect back to the Success page never completes. The buyer can revisit the Success page later with their order reference and see their tickets.

= How do I change the Checkout page later? =

Go to Events, Settings, Pages and pick a different page from the Checkout page dropdown. The Register Button on every event and the internal checkout URL builder both pick up the new page immediately. No code changes, no shortcode swaps.

= How do the registration window controls work? =

Each event has an optional Opens at and Closes at datetime. If both are blank, registration is open immediately and closes at the event start. Setting Opens at in the future makes the Register Button render its 'not yet open' disabled state. Setting Closes at in the past, or letting the event start pass, renders the 'closed' disabled state. Capacity enforcement is independent: when the event has a capacity and tickets-sold reaches it, the button shows 'sold out' and the checkout AJAX handler rejects any new order. Every gate is applied server-side as well, so tampering with the client never oversells or bypasses the window.

= How do I add a new CRM integration? =

Build a small add-on plugin that extends `KDNA_Events_CRM_Integration` and registers on the `kdna_events_register_crm_integrations` action. See docs/DEVELOPER.md for a complete HubSpot example. The framework handles settings UI, Test Connection, enable / disable toggles, master switch, the payload build, the sync log and the `kdna_events_after_crm_sync` hook.

= Can I override the email templates? =

Yes. The booking confirmation and admin notification templates can be replaced via the `kdna_events_email_template_path` filter. See docs/DEVELOPER.md.

= Can I override the event grid card markup? =

Yes. The shared partial at `templates/partials/event-card.php` is resolved via the `kdna_events_event_card_template` filter so a theme can ship its own copy and both initial render and AJAX filter responses will use it.

== Screenshots ==

1. Event Details meta box on the kdna_event edit screen.
2. Settings page with General, Stripe, Google Maps, Pages, Emails and CRM tabs.
3. An event single page built with the core display widgets.
4. Event Grid with the Event Filter bar above it.
5. Checkout page with summary, quantity, attendees, order summary and pay button.
6. Success page with confirmation and ticket cards.
7. My Tickets page with Upcoming / Past tabs.

== Changelog ==

= 1.0.0 =
* Initial release.
* Stage 1: plugin foundation, custom post type, meta fields, admin Event Details meta box, custom list columns, custom tables for orders and tickets, helper library, uninstall cleanup.
* Stage 2: tabbed Settings page, Elementor widget category, template page assignment, front-end enqueue, widget base class.
* Stage 3: eight core single-event display widgets (Title, Subtitle, Date/Time, Price, Type Badge, Description, Image, Organiser).
* Stage 4: Event Location widget with Google Maps integration, deduplicated lazy loader and six built-in style presets.
* Stage 5: Event Grid and Event Filter widgets with shared card partial, AJAX filtering, numbered pagination and Load More, plus pushState URL sync.
* Stage 6: Event Register Button widget with active, not-yet-open, closed and sold-out states and a size-preset system driven by CSS custom properties.
* Stage 7: Checkout page widgets and AJAX order creation with server-side re-validation of window, min/max and capacity.
* Stage 8: Stripe Checkout Sessions, signature-validated webhook, idempotent finalisation, free event flow, ticket generation, Success page widgets and race-mitigation polling.
* Stage 9: templated HTML booking confirmation email, compact admin/organiser notification, optional per-attendee emails, admin Send test email button, and the My Tickets user dashboard widget with a guest login prompt.
* Stage 10: pluggable CRM integration framework with abstract base class, registry, ticket-created bridge, rolling sync log and a new CRM settings tab with Test Connection and Clear Log controls.
* Stage 11: final QA, translations, documentation, packaging.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
