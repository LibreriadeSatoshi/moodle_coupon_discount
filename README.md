# Moodle Bitcoin Integration (BTCPay Server)

This repository contains two Moodle plugins designed to integrate Bitcoin payments via BTCPay Server, including a coupon/discount system with a modern UI.

## Included Plugins

1. **Enrolment BTC Coupon (enrol_btc_coupon)**: An enrollment plugin that adds a modern payment interface with coupon/discount support.
2. **BTCPay Payment Gateway (paygw_btcpay)**: The core payment gateway that connects Moodle to BTCPay Server using the Greenfield API.

---

## Installation via Moodle Dashboard

To install these plugins using the Moodle UI, follow these steps:

### 1. Prepare the ZIP files
Moodle requires each plugin to be in its own ZIP file. 
- Create a ZIP of the **enrol_btc_coupon** folder.
- Create a ZIP of the **paygw_btcpay** folder.

### 2. Upload to Moodle
1. Log in to your Moodle instance as an **Administrator**.
2. Go to **Site administration > Plugins > Install plugins**.
3. Upload the `enrol_btc_coupon.zip` first. Follow the on-screen instructions to complete the installation.
4. Repeat the process for `paygw_btcpay.zip`.

---

## Configuration

### BTCPay Server Setup
1. Log in to your **BTCPay Server** instance.
2. Create a **Store** and configure your Bitcoin wallet.
3. Go to **Account > API Keys** and create a new Greenfield API Key with the following permissions:
   - `btcpay.store.cancreateinvoice`
   - `btcpay.store.canviewinvoices`
4. Note down your **Store ID** and **API Key**.

### Moodle Setup
1. In Moodle, go to **Site administration > Payment > Accounts**.
2. Create a new Payment Account (or edit an existing one).
3. Enable the **BTCPay** gateway.
4. Enter your **BTCPay Server URL**, **Store ID**, and **API Key**.
5. Save changes.

---

## Technical Features
- **Native cURL**: Patched to bypass Moodle's internal proxy/security restrictions for better compatibility with external BTCPay instances.
- **Modern UI**: Enhanced CSS for the coupon search box and payment modal, featuring a clean "Bitcoin Orange" aesthetic.
- **SSRF Compatibility**: Works in both HTTP and HTTPS environments (HTTPS recommended for production).


