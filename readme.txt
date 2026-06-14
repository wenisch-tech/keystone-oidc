=== Keystone OIDC ===
Contributors: jfwenisch
Tags: oidc, openid-connect, sso, authentication, oauth2
Requires at least: 5.6
Tested up to: 7.0
Stable tag: 2.2.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn your WordPress site into an OpenID Connect (OIDC) identity provider. Manage clients through a simple admin panel.

== Description ==

Keystone OIDC transforms your WordPress installation into a fully-featured **OpenID Connect (OIDC) identity provider**, allowing other applications to authenticate users via your WordPress user database.

= Key Features =

* **OIDC Authorization Code Flow** with PKCE support
* **RS256 JWT** signed access tokens and ID tokens
* **Admin UI** to create and manage multiple OIDC clients
* **Client secret management** – generate and reset secrets securely (shown only once)
* **OIDC Discovery** endpoint (`/wenisch-tech/keystone-oidc/.well-known/openid-configuration`) for automatic client configuration
* **Standard scopes**: `openid`, `profile`, `email`
* **Refresh tokens** for long-lived sessions
* **Zero additional configuration** after install – just create a client and you're ready

= Endpoints =

| Endpoint | URL |
|---|---|
| Discovery | `/wenisch-tech/keystone-oidc/.well-known/openid-configuration` |
| Authorization | `/wenisch-tech/keystone-oidc/oauth/authorize` |
| Token | `/wenisch-tech/keystone-oidc/oauth/token` |
| UserInfo | `/wenisch-tech/keystone-oidc/oauth/userinfo` |
| JWKS | `/wenisch-tech/keystone-oidc/oauth/jwks` |

Compatibility aliases are also routed under `/wenisch-tech/keystone-oidc/protocol/openid-connect/*` for clients that still derive Keycloak-style paths from the custom issuer URI. These aliases are not advertised in discovery.

= UserInfo Example =

For `openid profile email`, `/wenisch-tech/keystone-oidc/oauth/userinfo` returns:

```
{
  "sub": "42",
  "name": "Jane Doe",
  "given_name": "Jane",
  "family_name": "Doe",
  "preferred_username": "jane",
  "email": "jane@example.com",
  "email_verified": true
}
```

`sub` is the WordPress user ID as a string, `preferred_username` is the WordPress `user_login`, and `email` is the WordPress `user_email`.

Roles are not currently emitted. The plugin does not expose WordPress roles or capabilities in UserInfo or ID tokens.

= Quick Start =

1. Install and activate the plugin
2. Go to **OIDC Provider → Add Client** in your WordPress admin
3. Enter your application name and redirect URI(s)
4. Copy the generated **Client ID** and **Client Secret** (shown once)
5. Configure your OIDC client application with the discovery URL shown in the settings

== Installation ==

1. Upload the `keystone-oidc` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu
3. Navigate to **OIDC Provider** in the admin sidebar to create your first client

Alternatively, download the `keystone-oidc.zip` from the [GitHub Releases](https://github.com/wenisch-tech/wordpress-keystone-oidc/releases) page and upload it via **Plugins → Add New → Upload Plugin**.

== Frequently Asked Questions ==

= What OIDC flows are supported? =

Authorization Code Flow (with and without PKCE). This is the most secure flow and suitable for all application types.

= Where is the client secret stored? =

Client secrets are **hashed** using WordPress's password hashing (bcrypt). The plaintext secret is shown only once upon creation or reset and is never stored in the database.

= Does this plugin support multiple clients? =

Yes – you can create as many OIDC clients as you need from the admin panel.

= What happens if I rotate signing keys? =

All previously issued tokens will immediately become invalid. Use the **Settings** page to rotate keys when needed (e.g., after a security incident).

= Is PKCE supported? =

Yes, both `S256` and `plain` code challenge methods are supported.

== Changelog ==

= 2.2.2 =
### [2.2.2](https://github.com/wenisch-tech/wordpress-keystone-oidc/compare/v2.2.1...v2.2.2) (2026-06-12)


### Bug Fixes

* updated release versioning and changelog creation ([98cfb30](https://github.com/wenisch-tech/wordpress-keystone-oidc/commit/98cfb3062232f96346646f915a90198f69b17f51))
* updated repository links ([f46b2b6](https://github.com/wenisch-tech/wordpress-keystone-oidc/commit/f46b2b6f2012cd348eab5e73f5ca9410f0efc406))
* updatet generation of changelog. ([357bded](https://github.com/wenisch-tech/wordpress-keystone-oidc/commit/357bded5f6cd824859dfc4710d72bdbec60da983))


### Documentation

* added "Report a bug" button to plugin page ([8281f6c](https://github.com/wenisch-tech/wordpress-keystone-oidc/commit/8281f6c5cfd9474e785c06eaf562e1a2cb84f47d))



= 1.0.0 =
* Initial release
* Authorization Code Flow with PKCE
* RS256 JWT tokens
* Multi-client admin UI with secret management
* OIDC Discovery endpoint
* Refresh token support

== Upgrade Notice ==

= 1.0.0 =
Initial release.
