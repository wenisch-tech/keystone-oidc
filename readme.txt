=== Keystone OIDC ===
Contributors: wenisch-tech
Tags: oidc, openid-connect, sso, authentication, oauth2
Requires at least: 5.6
Tested up to: 6.9
Stable tag: 1.0.0
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

Alternatively, download the `keystone-oidc.zip` from the [GitHub Releases](https://github.com/wenisch-tech/keystone-oidc/releases) page and upload it via **Plugins → Add New → Upload Plugin**.

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
