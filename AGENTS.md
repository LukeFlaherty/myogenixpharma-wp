# Myogenix Theme Agent Instructions

This is the actual git repo for the Myogenix WordPress theme.

## Read First

Before doing any redesign or production work, read:

- `REDESIGN_WORKFLOW.md`

That file is the detailed source of truth for:

- Goal design screenshot locations
- Next.js preview and clone paths
- Redesign asset paths
- Live product and category URL inventory
- Included and excluded surfaces
- Production QA workflow
- PHP/WP tooling notes
- Rollback baseline

## Project Facts

- Local repo path: `/Users/lukeflaherty/dev/client/myogenixpharma-wp/wp-content/themes/myogenix-theme`
- GitHub remote: `https://github.com/LukeFlaherty/myogenixpharma-wp`
- Production URL: `https://myogenixpharma.com`
- Deploy model: push `main`, wait for WordPress.com deployment, then QA production.
- Staging is intentionally out of scope because it does not match production ordering functionality.

## Design References

- Next.js preview: `https://myogenix-pharma.vercel.app/`
- Next.js clone: `/Users/lukeflaherty/dev/client/myogenixpharma`
- Redesign assets: `/Users/lukeflaherty/dev/client/myogenixpharma/public/assets/grunge-redesign`
- Screenshot source folder: `/Users/lukeflaherty/dev/client/myogenix-assets/Design Screenshots/Myogenix Pharma Grunge Redesign R1`
- Screenshot output folder: `/Users/lukeflaherty/Downloads/Myogenix Pharma Grunge Redesign R1`

## Working Rules

- Work directly on `main`.
- Preserve current production URLs.
- Preserve WooCommerce variation/add-to-cart/order behavior.
- Preview design copy wins over current production copy when they differ, unless production legal/ordering requirements conflict.
- Checkout and popout cart are excluded unless the user explicitly brings them back into scope.
- Use Playwright production checks for visual QA.
- Before committing PHP/template changes, run `php -l` on touched PHP files and `git diff --check`.

## Rollback Anchor

Pre-redesign state:

- Tag: `pre-redesign-baseline-2026-08-07`
- Branch: `backup/pre-redesign-baseline-2026-08-07`
- Commit: `05f14a0034fab0a739d86eda6de23330f809fe46`
