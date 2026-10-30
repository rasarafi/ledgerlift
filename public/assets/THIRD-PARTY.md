# Third-party assets

Everything under `public/assets/` (except this file and `LICENSE-InvoicePlane.txt`)
is build output copied from the InvoicePlane v1.7.2 release
(https://github.com/InvoicePlane/InvoicePlane), which is MIT licensed. Upstream's
per-directory `.gitignore` files are omitted so that git tracks the build output.
Regenerate it with `scripts/import-legacy-assets.sh`.

InvoicePlane's copyright and permission notice, together with its trademark
notice and the CodeIgniter licence, is in `LICENSE-InvoicePlane.txt` (the
upstream `LICENSE.txt` at v1.7.2, verbatim). It applies to these assets and to
`lang/en/ip.php`, which is derived from InvoicePlane's
`application/language/english/ip_lang.php` under the same licence.

The libraries bundled inside that output (for example in
`core/js/dependencies.min.js` and `core/fonts/`) remain under their own
licences; consult each library's upstream project for its terms.
