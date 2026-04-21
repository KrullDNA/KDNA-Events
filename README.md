# KDNA Events

Source repository for the KDNA Events WordPress plugin. The plugin itself lives in `kdna-events/`; everything outside that folder is repo scaffolding, build output, or project documentation and is **not** part of the shipped plugin.

## Repository layout

```
.
├── kdna-events/               ← the WordPress plugin (this is what ends up in wp-content/plugins/)
│   ├── kdna-events.php        main plugin bootstrap
│   ├── readme.txt             WordPress.org readme
│   ├── uninstall.php
│   ├── composer.json          Stripe PHP SDK dep
│   ├── composer.lock
│   ├── vendor/                stripe-php committed so sites without composer still work
│   ├── includes/              CPT, admin, settings, orders, tickets, checkout, Stripe, emails, grid, CRM
│   ├── widgets/               21 Elementor widget classes (20 concrete + 1 abstract base)
│   ├── assets/                frontend + admin CSS and JS
│   ├── templates/             email templates + shared event card partial
│   ├── languages/             kdna-events.pot
│   └── docs/
│       └── DEVELOPER.md       hook reference, CRM build guide, template override guide
│
├── dist/
│   └── kdna-events-1.0.0.zip  packaged release, ready for upload
│
├── tests/
│   └── smoke-menu.php         admin-menu smoke test (run before admin-UI changes)
│
├── RELEASE-REPORT.md          internal 1.0.0 QA and release notes
├── KDNA-Events-Project-Brief.docx
└── README.md                  this file
```

## Working with the plugin

```bash
cd kdna-events
composer install --no-dev
```

Then symlink or copy `kdna-events/` into `wp-content/plugins/` on a WordPress install and activate from the Plugins screen. Elementor must be installed and active.

## Running the smoke tests

```bash
php tests/smoke-menu.php
```

Expected: `All menu assertions passed.` (30 assertions).

## Rebuilding the release zip

From the repo root:

```bash
cd kdna-events && composer install --no-dev --optimize-autoloader && cd ..
rm -f dist/kdna-events-*.zip
zip -qr dist/kdna-events-1.0.0.zip kdna-events \
  -x 'kdna-events/.git/*' \
  -x 'kdna-events/.gitignore' \
  -x 'kdna-events/composer.lock'
```

The resulting zip extracts to a top-level `kdna-events/` folder so it drops straight into `wp-content/plugins/`.
