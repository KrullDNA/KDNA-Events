=== KDNA Events ===
Contributors: kdna
Tags: events, ticketing, elementor, stripe, bookings
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress events and ticketing plugin built entirely on Elementor widgets, with Stripe Checkout for paid events and a pluggable CRM framework.

== Description ==

KDNA Events is a self-contained WordPress plugin for creating, displaying, and selling tickets to events. Every front-end element is delivered as a fully styleable Elementor widget. Paid events are processed through Stripe Checkout, free events skip payment but still capture attendee details. A pluggable CRM framework lets future add-on plugins push attendee data to any external CRM without touching core.

Core capabilities:

* Custom Events post type with structured meta fields (dates, registration window, price, type, capacity, location, organiser).
* Full suite of Elementor widgets for building single event pages, archive pages, checkout flow, success page, and user dashboard.
* Google Maps integration for in-person event locations.
* Stripe Checkout Sessions for paid events, webhook-driven order fulfilment.
* Multi-attendee checkout, collecting name, email, phone, and custom per-event fields for every ticket.
* Min/max tickets per order enforced at checkout.
* Registration window controls (opens at / closes at) distinct from event start.
* Free event flow that mirrors paid checkout without Stripe.
* Booking confirmation emails with ticket codes, optional notifications to per-event organiser.
* Pluggable CRM integration framework for future add-ons (HubSpot, ActiveCampaign, Mailchimp, Salesforce, etc).

== Installation ==

1. Ensure Elementor is installed and active.
2. Upload the plugin folder to /wp-content/plugins/ or install via the Plugins screen.
3. Run `composer install --no-dev` inside the plugin folder so the Stripe PHP SDK is available in `vendor/`.
4. Activate the plugin.
5. An 'Events' menu appears in the WordPress admin sidebar. Add your first event from there.
6. Assign a Checkout, Success and My Tickets page under Events, Settings, Pages.
7. For paid events, configure Stripe under Events, Settings, Stripe and add the webhook endpoint described below.

== Stripe Webhook Setup ==

KDNA Events relies on a webhook-driven flow so the order finalises even if the buyer closes the tab after paying.

1. In the Stripe Dashboard, open Developers, Webhooks, Add endpoint.
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

Yes. Every front-end element is an Elementor widget. The plugin deactivates itself with a notice if Elementor is not active.

= Does it work with free events? =

Yes. Set the event price to 0 and the plugin skips Stripe entirely while still collecting attendee details, generating tickets and sending confirmation emails.

= What happens if the buyer closes the browser after Stripe payment? =

The Stripe webhook is the authoritative signal. The order finalises (tickets, emails, CRM sync) even if the redirect back to the Success page never completes. The buyer can revisit the Success page with their order reference to retrieve their tickets.

== Changelog ==

= 1.0.0 =
* Stage 1: plugin foundation, custom post type, meta fields, admin Event Details meta box, custom list columns, custom tables for orders and tickets, helper library, uninstall cleanup.
* Stage 2: tabbed Settings page, Elementor widget category, template page assignment, front-end enqueue, widget base class.
* Stage 3: eight core single-event display widgets.
* Stage 4: Event Location widget with Google Maps integration.
* Stage 5: Event Grid and Event Filter widgets with AJAX filtering and pagination.
* Stage 6: Event Register Button widget with registration-window states.
* Stage 7: Checkout page widgets and AJAX order creation.
* Stage 8: Stripe Checkout Sessions, webhook-driven fulfilment, free event flow, ticket generation, Success page widgets.
