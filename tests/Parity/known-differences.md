# Known differences outside the database

The parity suite diffs both databases after every scenario; the differences below are in
what the apps send back instead, and are deliberate. Database differences that are
deliberate live in `intentional-differences.php`.

- **Page title default.** With `custom_title` unset, ledgerlift titles pages `ledgerlift`
  (login: `ledgerlift - Login`); legacy uses `InvoicePlane`. A set `custom_title` renders the
  same on both.
- **Users without `user_psalt`.** Legacy logs them in through its pre-1.2.0 MD5 path and
  rewrites their hash (`Mdl_sessions.php:40`); ledgerlift refuses them. Every user ledgerlift
  creates has a salt.
- **Guest users (user_type 2).** Both redirect to `guest` after login, but that page is
  legacy's, where no session exists: the sessions are not shared.
- **Sessions.** Cookie `ledgerlift_session` instead of `ip_session`; ids and files are not
  shared; `sess_match_ip` is not ported; the user row is read through the guard instead of
  being copied into the session.
- **CSRF.** Laravel's per-session token, regenerated at login and logout (legacy's is a
  fixed cookie value). Laravel also accepts `Sec-Fetch-Site: same-origin` requests without a
  token, sends `X-_ip_csrf` on every web response (legacy: admin pages only) and sets an
  `XSRF-TOKEN` cookie. A failed check is a 403 on both, with a different body.
- **Cache-Control** on admin pages carries an extra `private`; same no-store/no-cache meaning.
- **Setup.** Legacy's "Security Warning" flash about the setup wizard is not ported; there
  is no setup wizard.
- **Asset version suffix** (`?v=`): ledgerlift reports 1.7.2; the legacy installer leaves
  1.4.7 as the newest applied version (`019_1.4.7.sql` is applied last by timestamp).
- **Create Quote** in the navbar is a plain link to legacy `quotes/index`; legacy opens a modal.
- **Deleting a record.** Legacy v1.7.2 never deletes through `Admin_Controller::ensure_valid_post_request()`
  (tax rates, families, units, payment methods, invoice groups, products, clients). CodeIgniter's global CSRF check removes `_ip_csrf` from `$_POST`, then
  `verify_csrf_token()` looks for it there, finds nothing and refuses: legacy flashes the raw lang key
  `invalid_request`, redirects (to `<module>/index`, `families` for families) and keeps the row. Products behave like the rest
  (`Mdl_products::delete()` is a plain `DELETE ... WHERE product_id = id`, no other cleanup).
  ledgerlift deletes the record, flashes "Record successfully deleted" (what `Response_Model::delete()`
  was written to do) and redirects to `<module>`. The parity scenarios record both answers and remove the
  row from the legacy database by hand so the two end states can still be diffed. For a client that is the
  model-level delete, `Mdl_clients::delete()` (a DELETE by primary key) followed by `delete_orphans()`
  (`orphan_helper.php:24-41`), applied to the legacy database by SQL; `ip_user_clients` is not part of it, so a
  deleted client's assignments stay on both sides.
- **Stale session posts.** A POST to an admin URL with a valid login-page token but no login redirects
  to the login page on legacy (its CSRF cookie outlives the session); ledgerlift answers 403, its token
  belonging to the session.
- **Input echoed unescaped.** `invoice_groups/form` prints `invoice_group_next_id` and
  `invoice_group_left_pad` into their `value` attributes without escaping; ledgerlift escapes them.
- **Offsets.** A non-numeric list offset (`tax_rates/index/abc`) is a 404 here; legacy fails with a PHP error.
- **Families search.** The debug-only `console.log(data)` of the filter script is not ported.
- **Product modal, crafted parameters.** An array-valued `filter_product[]` or `filter_family[]` GET parameter
  gives the full modal here; legacy answers an empty table or HTTP 500. The modal's `json_parse` debug flag
  (legacy `ENABLE_DEBUG`) is `config('app.debug')` here, and `.env.example` ships `APP_DEBUG=true`.
- **Clients, scope.** Custom fields (their rows are neither read nor saved), e-invoicing (the `einvoicing` setting
  must stay unset: no panel, no columns, and since no XML configuration exists every non-empty
  `client_einvoicing_version` is rejected, as on an install with none) and `clients/ajax/get_latest` (nothing calls
  it) are not ported. The language list holds `english` only, the country list is the English one.
- **Clients, links.** The client view's quotes tab is a link to the client's quotes on the legacy app, and the
  invoices tab's "send email" link goes to the legacy mailer; legacy shows both in place.
- **Clients, crafted input.** `name_query` with an array `query` answers `[]` (legacy searches for the text
  "Array" after a PHP warning). A non-numeric list offset or tab page is a 404 here; legacy fails with a PHP error.
  A client country that is not in the country list is printed escaped (`partial_client_address.php` echoes it raw).
