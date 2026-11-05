# User creation and authentication

FLOSC creates a WordPress account when a person needs purchased access, membership state, quiz results, or protected content to persist across sessions. The account is a subscriber, or another nonprivileged role an administrator configured with `flosc_registration_role()`. FLOSC does not create an administrator. A MagicLink never creates an account. A server-to-server payment notification may create the buyer's account and does not log a browser in.

Session issuance uses WordPress `wp_set_auth_cookie()` and `wp_set_current_user()`. Those calls remain because they are the WordPress session APIs. Replacing them with a private cookie would bypass security plugins that watch `wp_login`. Each session issuance fires `wp_login` once. The wp-sync hop only reissues a cookie for a MagicLink login that already fired `wp_login`, and does not fire `wp_login` again.

Post-purchase browser login stays off unless `flosc_post_purchase_instant_login` or `flosc_post_purchase_login_token` is enabled. MagicLink stays off unless `flosc_enable_magic_access_links` is enabled and that flow's own switch is on. The email-verification, MagicLink, login-token, and wp-sync consume paths are rate-limited. `wp_login` does not run WordPress `authenticate` or password-attempt blockers.

The same explanation is in the readme FAQ "When does FLOSC create or log in a WordPress user?"

## Call sites

- `includes/flosc-accessors.php:171` `* @return string|WP_Error Role slug for wp_insert_user(), or an error when no`
- `includes/class-flosc-framework.php:2165` `// WordPress fires user_register from wp_insert_user(), which every FLOSC`
- `includes/class-flosc-framework.php:8686` `wp_update_user(`
- `includes/class-flosc-framework.php:8704` `wp_set_password( $password, $user_id );`
- `includes/class-flosc-framework.php:8705` `// wp_set_password() clears all sessions — re-issue auth cookies for this user only.`
- `includes/class-flosc-framework.php:8706` `wp_set_auth_cookie( $user_id, true );`
- `includes/class-flosc-framework.php:9482` `$created = wp_insert_user(`
- `includes/class-flosc-framework.php:9500` `wp_update_user(`
- `includes/class-flosc-framework.php:9992` `$created_id = wp_insert_user(`
- `includes/class-flosc-framework.php:10006` `wp_update_user(`
- `includes/class-flosc-framework.php:10194` `wp_set_current_user( $uid );`
- `includes/magic-link/class-flosc-magic-link-trait.php:281` `wp_set_current_user( $user_id );`
- `includes/magic-link/class-flosc-magic-link-trait.php:282` `wp_set_auth_cookie( $user_id, true );`
- `includes/magic-link/class-flosc-magic-link-trait.php:455` `wp_set_current_user( $user_id );`
- `includes/magic-link/class-flosc-magic-link-trait.php:456` `wp_set_auth_cookie( $user_id, true );`
- `includes/magic-link/class-flosc-magic-link-trait.php:570` `wp_set_current_user( $user_id );`
- `includes/magic-link/class-flosc-magic-link-trait.php:571` `wp_set_auth_cookie( $user_id, true );`
- `includes/magic-link/class-flosc-magic-link-trait.php:601` `wp_set_current_user( $user_id );`
- `includes/magic-link/class-flosc-magic-link-trait.php:602` `wp_set_auth_cookie( $user_id, true );`
- `includes/magic-link/class-flosc-magic-link-trait.php:709` `$user_id                                    = wp_insert_user(`
- `includes/first-party-authentication/class-flosc-first-party-authentication.php:801` `* 4. Calls wp_set_current_user(0) → undoes our FLOSC token auth`
- `includes/filesystem/flosc-data-paths.php:858` `wp_set_current_user( $user_id );`
- `includes/filesystem/flosc-data-paths.php:859` `wp_set_auth_cookie( $user_id, true );`
- `includes/sso/class-oauth2-handler.php:958` `wp_set_current_user( $user_id );`
- `includes/sso/class-oauth2-handler.php:959` `wp_set_auth_cookie( $user_id, true );`
- `includes/sso/class-user-linker.php:195` `$user_id = wp_insert_user( $user_data_wp );`
- `includes/sale/providers/class-flosc-clickbank-provider.php:673` `$user_id = wp_insert_user(`
- `includes/sale/providers/class-flosc-clickbank-provider.php:691` `wp_update_user(`
