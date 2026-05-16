#Token-based access plugin


- Advertisers' contact details (phone number, email address, optional WhatsApp) are hidden by default for all users
- The user (ad recipient) can see the ads and interest in the offer, but cannot see any contact details without purchasing access
- After purchasing a product for a fixed sum in WooCommerce, the user receives a unique token in the form of a link sent to their email


After clicking on the link, the plugin:
- verifies the token
- activates access for X hours from the first click. (X is the number of hours set in the plugin settings)
- saves the user's session in the browser (cookie + server record)

Plugin features:
- For X hours, the user has access to contact details in all ads on the portal
- After X hours, access expires automatically
- The plugin does not require user registration or login
- Contact details are not visible in the HTML code of the page to people without access
- Access token works on only one device/one session at a time (attempting to use it on another device would invalidate the previous session)
- Tokens are generated automatically after payment for an order in WooCommerce
- The X-hour starts from the first activation of the link (not from the moment of purchase).

