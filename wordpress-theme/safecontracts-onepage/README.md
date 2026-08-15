# SafeContracts One Page WordPress Theme

Bilingual SafeContracts WordPress theme covering the public landing page, theme settings, wp-admin visual identity, and the WordPress login screen.

## Isolation

This theme lives entirely under:

`wordpress-theme/safecontracts-onepage/`

It does not contain or duplicate SafeContracts plugin business logic. The existing `wordpress-plugin/` remains the backend/source-of-truth implementation.

## Public homepage

`front-page.php` is a public one-page experience and does not require authentication. It contains:

- Hero
- Benefits
- Use Cases
- How It Works
- Smart Dashboards
- Security & Control
- FAQ
- Final CTA
- Footer

## Arabic / English

Use the header language switcher or append:

- `?lang=ar` for Arabic/RTL
- `?lang=en` for English/LTR

Language selection is intentionally theme-local and does not change the WordPress admin locale or plugin behavior.

## Homepage backend editor

Open **Appearance → SafeContracts Home** to manage the public landing page without editing code.

The editor includes:

- Arabic homepage copy
- English homepage copy
- Primary, secondary and CTA button colors
- Button destinations under **Links / الروابط**

Editable button destinations are:

- Request Demo — Header
- Login
- Hero Primary
- Hero Secondary
- Final CTA

A destination may be a full URL, a site-relative path, or a same-page anchor such as `#benefits`.

Legacy Demo/Login URLs from the WordPress Customizer are still used as fallbacks until a value is saved in the new button-links editor.

## SafeContracts wp-admin identity

Version 0.3.0 applies the same SafeContracts visual language to WordPress backend screens while leaving WordPress/plugin functionality intact.

The theme now brands:

- WordPress admin bar with a SafeContracts shortcut and mark
- Left admin menu / submenu states
- Primary and secondary buttons
- Forms and input focus states
- Dashboard/postbox cards
- Tables and list views
- Notices, tabs, and common settings surfaces
- A SafeContracts dashboard welcome widget with shortcuts to edit the homepage and view the public site
- Admin footer text
- WordPress login screen and login logo

Admin presentation lives in `assets/css/admin.css`; login presentation lives in `assets/css/login.css`. Backend hooks are isolated in `inc/admin-branding.php` so presentation remains separate from SafeContracts plugin business logic.

## Assets

Landing and brand visuals are stored under `assets/images/` and follow the approved SafeContracts blue/teal SaaS direction. Assets are local to the theme; no third-party image CDN is required.

The client-contract illustration is a web-optimized derivative of the AI-generated concept. Dashboard, reminder, document, analytics and security visuals are lightweight SVG interpretations of the approved AI design direction so the public page remains fast and easy to maintain.

## Accessibility / responsive behavior

- Semantic sections and headings
- Skip link
- Keyboard-safe native FAQ disclosure controls
- Accessible mobile menu state
- Desktop, tablet and phone breakpoints
- RTL/LTR logical layout
- `prefers-reduced-motion` support
- Responsive wp-admin branding without hiding core controls

## Install

Copy `safecontracts-onepage` to `wp-content/themes/`, activate **SafeContracts One Page**, and use it for the public site front page. The wp-admin and login branding is applied automatically while the theme is active.

## Development validation

Theme CI validates PHP syntax, JSON, JavaScript, required assets, bilingual public-page contracts, backend content/color/link editing, SafeContracts wp-admin/login branding, and deterministic installable ZIP packaging.
