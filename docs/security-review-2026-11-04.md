# HPS Consent security review - November 4, 2026

Reviewed source baseline: commit `2e4de4537438997fde43192c33f97b8a0205ca83`, plugin v1.0.3. The changes below are included in v1.0.4; v1.0.3 does not contain them. Production deployment is tracked separately from the original source review.

## Result and scope

No unauthenticated WordPress takeover, SQL injection, arbitrary file upload, PHP execution, or stored banner-script injection was identified in the reviewed source. This is a bounded source review with regression/contract tests and a dependency audit, not a full WordPress installation penetration test or an independent certification. No claim is made about unrelated plugins or the client's entire GTM container.

The runtime has no custom REST, AJAX, upload, authentication, or database-query endpoint. Its settings use WordPress's Settings API, a `manage_options` admin page and capability check, and `settings_fields()` nonce fields. Banner text is escaped, inline configuration uses JSON_HEX escaping, and CSS values/container IDs are restricted. The PHP harness uses WordPress stubs: it validates rendering and update boundaries, not actual role/nonce enforcement in a running WordPress installation.

## Findings and proposed changes

| Priority | Finding | Preconditions and impact | Patch |
| --- | --- | --- | --- |
| Medium hardening | ZIP integrity was not verified by the installed updater. Release and package versions were not bound exactly. | An administrator/update process must install the package. A package substituted relative to trusted metadata, or incorrectly linked to a different version, could install executable PHP. This is not an anonymous website upload vulnerability. | Bind package to the advertised repository/tag/asset; require GitHub SHA-256 and bounded declared size; verify bytes and size before WordPress unpacks; reject unverifiable updates. |
| Low | Consent cookie extras were merged into runtime state. | Someone able to alter a visitor's cookie could add an unsupported category, affecting existing deferred markup using that category. The record is not used for WordPress authorization. The object prototype could also be replaced locally; no global prototype pollution or server privilege escalation was demonstrated. | Copy only the three validated category booleans. Regression reproduced the pre-patch behavior and passes with the fix. |
| Medium hardening | CI referenced moving action tags, and release publishing could run on a version-tag push outside main. | Compromise/movement of an action tag or unauthorized release-path use could affect the package supply chain. Main-branch and tag protections were not audited through an authenticated settings interface. | Pin the four official actions to verified commit SHAs; stop persisting checkout credentials; restrict automated release publishing to main; add high/critical dependency audit gate and weekly Dependabot proposals. |
| Low hardening | Build included every file in runtime directories. | An accidentally added development or credential file in those directories could ship to clients. No leaked secret was found. | Explicit allowlist of the eleven distributed files; reject non-regular source entries. |

## Validation

- JavaScript syntax checks and all 21 browser behavior tests passed, including existing Basic/Advanced, GPC, CTM and withdrawal behavior.
- PHP 8.0 contract checks passed for escaping, sanitizer boundaries, metadata types, exact package version/source, missing/invalid digest, package size, hash mismatch, failed downloads, unrelated plugin isolation and temporary-file ownership.
- A deliberately altered package is rejected before installation; a file supplied by a different filter is not deleted by this plugin.
- `npm audit --json` reported zero known vulnerabilities across 68 dependencies at the time of the review. Dependencies are build/test-only and are not shipped in the runtime package.
- Build completes with the stable `hps-consent/` directory and eleven allowed files. No credentials or client-specific configuration were introduced into the repository.
- GitHub's current v1.0.3 ZIP asset supplies a SHA-256 digest, so the stricter metadata format is compatible with the existing publisher. That release reports `immutable: false`.
- A local WordPress Playground run on PHP 8.3 passed real WP_Upgrader download-hook validation, altered-package rejection before file replacement, an unrelated plugin download, and the normal AJAX/bulk plugin upgrade lifecycle. Plugin activation and all saved settings survived. GitHub HTTP metadata/package responses were supplied as local fixtures; this was not a production upgrade or a live GitHub network-integrity test. Additional PHP versions remain compatibility checks. The first upgrade from the old updater needs trusted manual package verification.

## Remaining recommendations

1. Enable immutable future releases, require reviewed pull requests/checks for main and release-related changes, protect release tags, and require strong MFA for publishing administrators. GitHub connector tools available for this review did not expose those protection settings, so they were not changed or assumed present.
2. Consider separately managed release signing with an independently trusted public key. Hash verification alone still trusts GitHub's metadata and release publisher.
3. The newer-package and rejected-package WordPress integration checks passed using local test fixtures. Keep repeating these checks before updater changes and bump the plugin/package/readme versions and release marker together for releases.
4. Keep GTM publishing access restricted. GTM intentionally executes third-party JavaScript; a compromised container can affect visitors even when WordPress PHP is secure. The requested tracking exceptions remain intentional behavior.

## References

- [WordPress Settings API](https://developer.wordpress.org/plugins/settings/settings-api/) and [nonce fields](https://developer.wordpress.org/reference/functions/settings_fields/).
- [WordPress pre-download hook](https://developer.wordpress.org/reference/hooks/upgrader_pre_download/), [download helper](https://developer.wordpress.org/reference/functions/download_url/), and [plugin upgrade lifecycle](https://developer.wordpress.org/reference/classes/plugin_upgrader/upgrade/).
- [GitHub asset digest metadata](https://docs.github.com/en/rest/releases/assets), [action security guidance](https://docs.github.com/en/actions/reference/security/secure-use), and [immutable releases](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/establish-provenance-and-integrity/prevent-release-changes).
