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
