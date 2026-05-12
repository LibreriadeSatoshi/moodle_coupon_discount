# BTCPay Server Payment Gateway for Moodle

This plugin integrates [BTCPay Server](https://btcpayserver.org/) with Moodle's Payment API so you can accept Bitcoin and Lightning payments for enrolments and other payable components.

## Installation

1. Copy the plugin into your Moodle installation:
   - Place the `btcpay` folder at `payment/gateway/btcpay/` (so that `version.php` is at `payment/gateway/btcpay/version.php`).
2. Go to **Site administration → Notifications**.
3. Click **Upgrade Moodle database now**.
4. The plugin will be installed.

## BTCPay Server setup

### Create an API key

1. Log in to your BTCPay Server instance.
2. Go to **Account → Manage Account → API Keys** (or **Store → Settings → Access token**).
3. Create a new API key with at least these permissions:
   - `btcpay.store.canviewinvoices`
   - `btcpay.store.cancreateinvoice`
4. Copy the API key (it is shown only once).

### Create a webhook

1. In BTCPay Server go to **Store Settings → Webhooks**.
2. Click **Create a new webhook**.
3. Set the URL to: `https://YOURMOODLE/payment/gateway/btcpay/webhook.php`
   - Replace `YOURMOODLE` with your Moodle site URL.
   - The URL must be publicly reachable over HTTPS.
4. If your Moodle is behind a firewall or NAT, ensure the webhook URL is reachable from the internet (e.g. use a reverse proxy or a tunnel such as ngrok or Cloudflare Tunnel).
5. Subscribe to these events:
   - Invoice created
   - Invoice received payment
   - Invoice settled
   - Invoice invalid
   - Invoice expired
6. Copy the **webhook secret** (shown only once).

## Moodle configuration

1. Go to **Site administration → Plugins → Enrolments → Payment gateways** (or **Site administration → Payment accounts**).
2. Create or edit a payment account.
3. Enable the **BTCPay Server** gateway and open its configuration.
4. Set:
   - **BTCPay Server Base URL**: Your BTCPay Server URL (e.g. `https://btcpay.example.com`). Must be HTTPS.
   - **Store ID**: From your BTCPay Server store settings.
   - **API Key**: The API key you created.
   - **Webhook Secret**: The webhook secret from the webhook you created.
   - **Fulfill Order Status**: Usually **Settled** (fulfil when the invoice is settled). Optionally **Processing** for earlier fulfilment.
   - **Invoice Expiration (minutes)**: How long the invoice is valid (default 60).
5. Save changes.

## Testing

1. **Create a payable course**
   - Use "Enrolment on payment" (fee-based enrolment) and set a fee and currency that BTCPay supports (e.g. USD).
2. **Run through a payment**
   - Enrol as a student and choose the BTCPay Server payment method.
   - You should be redirected to BTCPay to pay (use a testnet if available).
3. **Complete payment**
   - After paying, the webhook will be called by BTCPay. Moodle will then deliver the order (e.g. enrol the user).
4. **Check logs**
   - Go to **Site administration → Reports → Logs** and filter by payment or BTCPay if your plugin logs there.

## Troubleshooting

- **Webhook not reachable**  
  Ensure the webhook URL is publicly accessible (HTTPS). Test with a browser or `curl`. If Moodle is internal-only, use a tunnel or reverse proxy so BTCPay can reach the URL. Check BTCPay’s webhook delivery logs.

- **Invalid webhook signature**  
  Ensure the webhook secret in Moodle exactly matches the one in BTCPay (no extra spaces, same case). The header used is `BTCPAY-SIG` (case-insensitive).

- **Invoice never settles**  
  In BTCPay, check the invoice status. Confirm the payment was completed (e.g. on-chain or Lightning). Ensure the store’s payment methods and currency match what you use in Moodle.

- **Order not delivered**  
  Check that the webhook is being called and that Moodle’s payment/log reports show no errors. Ensure the payable component (e.g. enrol_fee) implements the payment callback correctly. The transaction record should have `delivered = 1` after a successful delivery.

- **Currency or amount mismatch**  
  Use the same currency in Moodle as in your BTCPay store (e.g. USD). Check amount format (decimal separator, precision).

## Security

- The webhook endpoint is public (no login) but **must** verify the `BTCPAY-SIG` HMAC-SHA256 signature using the configured webhook secret. Invalid requests receive 403.
- Fulfilment is driven only by verified webhooks; the user return page does not trigger delivery.
- API key and webhook secret are stored per gateway instance and should be kept confidential.

## License

GPL v3 or later.
