# ESC Unified Design System

Enterprise Safe Contracts uses one design language across `https://esc.50sols.com/`, WordPress/admin, Flutter mobile, authentication, transactional email and branded reports. New ESC UI must consume the shared tokens in `assets/enterprise/theme/tokens.json` rather than inventing isolated palettes or spacing scales.

## Principles

1. Enterprise clarity over decoration: dense operational screens remain scannable, predictable and keyboard-friendly.
2. One semantic language: contract, approval, payment, obligation and risk states use the same wording and visual meaning across web/admin/mobile/reports.
3. Arabic and English are first-class: layouts must support RTL/LTR without mirroring icons whose meaning should remain directional or brand-specific.
4. Responsive by design: desktop admin efficiency, tablet review flows and mobile monitoring are designed from the same component contracts.
5. Accessible defaults: WCAG AA body contrast target, visible focus, explicit labels, non-color status cues and minimum 44px touch targets.
6. Tenant white-labeling layers over the ESC base tokens. Tenant overrides may change approved brand tokens but must not override semantic danger/success meaning, accessibility requirements or product-security states.

## Core components

All product surfaces should converge on reusable specifications for buttons, text inputs, selects, date/currency inputs, tables, cards, tabs, drawers, dialogs, toasts, status chips, pagination, filters, empty/loading/error states, approval timelines, audit timelines and responsive navigation.

### Status vocabulary

- Neutral: Draft, Archived, Not Started.
- Informational: In Review, Pending, Due Soon.
- Success: Approved, Active, Paid, Completed.
- Warning: Expiring, At Risk, Partially Paid, Action Required.
- Danger: Rejected, Overdue, Breached, Suspended, Failed.

Status meaning must be conveyed by text/iconography in addition to color.

## Landing page relationship

The public landing page at `https://esc.50sols.com/` is a product surface, not a disconnected marketing theme. It uses the same typography, spacing, brand palette, button hierarchy, illustration/icon language and responsive rules. Feature claims are sourced from the ESC Feature Registry and only Beta/Public features may be marketed as available.

## Admin relationship

WordPress remains the backend shell, but operational ESC pages should visually behave like one enterprise application. WordPress-native technical administration may remain available to authorized system administrators while tenant users receive the ESC shell and navigation.

## Mobile relationship

Flutter should map the same semantic tokens into its Theme/Data model. Mobile-specific navigation and density are allowed; semantic colors, typography hierarchy, states and branding remain aligned with the web/admin system.

## Dark mode

Dark mode may be enabled per product surface only after every core component meets contrast and state visibility requirements. The canonical dark surface tokens are already reserved in the token file.

## White-label override policy

Tenant branding may override primary/secondary/accent, logo, organization name and approved email/report identity fields. The system must preserve:

- accessibility contrast;
- danger/warning/success semantics;
- ESC security/error states;
- readable focus states;
- legal/product attribution where required by plan or deployment terms.

## Change control

Any change to shared tokens requires impact review across landing, admin, Flutter, email and reports. Breaking token changes require a token schema-version update and migration notes. Component-specific one-off values are exceptions and must be documented rather than silently becoming a second design system.
