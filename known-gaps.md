# Known Gaps — FormFlow Lite

Honest debt ledger. Format: `<area> · <why skipped/missing> · <what unblocks it>`.

- WP REST contract (`/wp-json/fffl/v1/*`) · the route response shape needs a booting WordPress REST harness (wp-phpunit / `WP_TESTS_DIR`), which is not provisioned in CI/local · provision wp-phpunit (or the WP test-suite installer) in CI, then pin the route JSON shape in `tests/Contract/`.
- `XmlParser::parse_simple()` (no-attribute mode) · BUG — throws `TypeError` ("Cannot access offset of type string on string", `includes/api/class-xml-parser.php:108`) on ANY nested XML, because an opening tag stores a string under `$current[$tag]` then descends into it. Latent: production only calls attribute-mode `parse()`. Documented + reproduced (skipped) in `tests/Contract/KnownGapsContractTest.php` · fix the no-attribute branch in `build_array()` (mirror the attribute-mode descent) and replace the skip with a real assertion.
- Legacy `tests/Unit/*` + `tests/test-*.php` suites · reference `FFFL\Tests\TestCase` / WP-mock harness that is not autoloaded under the PHPUnit 10 toolchain; pre-existing, out of scope for the property/contract nets · add a `tests/` PSR-4 autoload mapping (or wire wp-phpunit) and migrate these suites to PHPUnit 10.
