# FormFlow Lite project starting point

Provide a lightweight WordPress enrollment and scheduling product with third-party API integration.

## Direction

Documented intent: Provide a lightweight WordPress enrollment and scheduling product with third-party API integration.

Owner confirmation needed: ultimate scope, launch or completion acceptance criteria, and the next priority. This brief does not mark any feature or project complete.

## Source baseline

- Inspected: 2026-10-09
- Local branch: `fix/confirmation-display-and-demo-account`
- Local HEAD: `2fc13a233fe191e9922d09369f7ecdc574377db0`
- Lifecycle registry: `active`; classification: `distributed-plugin` (registry reviewed 2026-08-29; current lifecycle not independently confirmed).
- Working tree at inspection: contains local changes.

## What it is built with

- frontend/package.json: react ^19.0.0; tailwindcss ^4.0.0; typescript ~5.6.2; vite ^6.4.3; vitest ^3.2.7; engines {"node": "22.22.2", "npm": "10.9.7"}
- composer.json: php >=8.0
- formflow-lite.php: WordPress plugin FormFlow Lite; WordPress 6.0; PHP 8.0

## Build and verification entry points

These are source-declared entry points, not commands executed by this audit. Install dependencies and configure a local environment according to the linked setup documentation before running them. Release/hosting actions remain subject to the project’s deployment rules.

- frontend/package.json: `npm run dev`
- frontend/package.json: `npm run build`
- frontend/package.json: `npm test`
- frontend/package.json: `npm run typecheck`
- frontend/package.json: `npm run runtime:verify`
- composer.json: `composer test`

## Structure and relationships

- `public/` is present in this checkout.
- `tests/` is present in this checkout.
- Cross-project integration details belong in the linked architecture and API documents; folder similarity alone does not establish a dependency.

## Gaps to resolve

- Working tree has uncommitted changes; this baseline includes local declarations beyond the recorded HEAD.
- Owner acceptance criteria and current priority order are not yet recorded in this brief.

## Source documents

- [README.md](README.md)
- [docs/](docs/)

## Keeping this current

Update this starting point when product direction, architecture, dependencies, or build/release entry points change. Recheck source facts against the current checkout. Date stamps describe inspection, not live production verification.

<!-- dino-source-audit {"date":"2026-10-09","head":"2fc13a233fe191e9922d09369f7ecdc574377db0","sources":{"README.md":"306f4b150acc381c11c7e1278afd4d8f2162a26bae971f3efbb443047809da54","composer.json":"877e3636a90875b22c3d17440082780b6d1b61d17c0945992f3ae0ea0c8ab9e1",".nvmrc":"4c42fb8d6334c5cdcac68b93f96c581fb83b1f58cda898cff115e5e941ef717d","frontend/package.json":"8bae14e14df2daedfbaeab8c2da9736c3c18d34389e73505d0ae4e853ae114b6","formflow-lite.php":"2cdb8f9ca330c8d9259704762d9ad1a973afc330d11da95cdf19d050ecb187cd"}} -->
