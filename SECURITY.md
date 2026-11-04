# Security

HPS Consent is a client-side consent manager, not an authentication or authorization system. Its browser cookie and public JavaScript API must never authorize accounts, protected content, form access, payments, or disclosure of patient data. Visitors can edit their own cookie. GTM and vendor behavior require separate review on each site.

## Reporting a problem

Contact a repository maintainer privately, or use GitHub private vulnerability reporting when enabled. Do not publish credentials, patient information, or an exploit against a client website in an issue. Include the affected version, prerequisites, a minimal reproduction on a local test site, and expected versus observed behavior.

## Update trust

GitHub releases distribute executable WordPress PHP. Anyone able to publish or replace a trusted release can potentially affect every site that installs it. Protect repository and organization accounts with strong multifactor authentication, least privilege, reviewed changes, protected branches, and immutable releases. Maintain restorable site backups.

The hardening implementation verifies the exact release tag and package URL, GitHub's asset SHA-256 digest, and file size before an automatic/one-click package is unpacked. Missing metadata or a hash mismatch stops the update and leaves the installed plugin files untouched. This detects corruption or package substitution relative to trusted metadata; it is **not independent publisher signing** and does not protect against a compromised GitHub publisher that can change both package and metadata. Separately managed signing keys and a verified release process would strengthen that boundary.

The new verification applies after the hardened plugin is installed; an older installed updater cannot verify the upgrade that first introduces it. Verify that first ZIP manually against a trusted release digest or deploy it through an independently trusted administration process. Administrator-uploaded local ZIPs remain WordPress's responsibility and are outside this automatic-update verification path.

Build/test dependencies are not bundled into the runtime ZIP. CI uses pinned action commits, dependency auditing, and an explicit runtime-file allowlist. Dependabot proposes updates for review; it does not automatically merge them. Automated release publishing is restricted to the main branch and an explicit release-version change. A version tag alone does not publish a release.

## Operational boundaries

- Keep WordPress, PHP, Elementor, other plugins, GitHub access, and GTM permissions maintained separately.
- The plugin does not block arbitrary scripts already installed elsewhere, third-party iframe internals, or server-side integrations.
- URL consent bypasses and pre-choice CTM operation are intentional administrator options, not security or privacy guarantees. Keep bypasses empty unless explicitly needed.
- The plugin is not a Google-certified CMP, an independent consent audit ledger, or a HIPAA authorization mechanism.
- A source review and passing tests do not establish that a site is free of vulnerabilities.
