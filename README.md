# Moodle Coupon Discount (enrol_coupon_discount)

This is a Moodle enrolment plugin that acts as a "middle layer" for payments. It allows users to apply coupon codes and receive discounts before proceeding to the actual payment gateway (such as BTCPay Server or Stripe).

## Installation

Following the Moodle plugin convention, this repository must be installed in:

**`public/enrol/coupon_discount`**

### Using Git Submodules (Recommended for Librería Moodle)

If you are using the `libreria-moodle` environment, add this repository as a submodule:

```bash
cd libreria-moodle
git submodule add https://github.com/LibreriadeSatoshi/btcPayServer_coupon_discount.git public/enrol/coupon_discount
git commit -m "Add coupon_discount enrolment layer"
```

Then, install it in Moodle by booting the environment and running the upgrade CLI:

```bash
docker compose exec -ti testmoodle php /root/libreria-moodle/admin/cli/upgrade.php --non-interactive
```

## Features

- **Coupon System**: Apply percentage-based discounts to course enrolment fees.
- **Modern UI**: Enhanced payment interface with a clean aesthetic and micro-animations.
- **Gateway Agnostic**: Seamlessly integrates with any payment gateway enabled in Moodle (BTCPay, Stripe, PayPal, etc.) via the standard `core_payment` API.

## Repository Structure

As per the `libreria-moodle` development guidelines, this repository contains the plugin files at its root:

```text
moodle-coupon-discount/
├── classes/          # Logic and payment service providers
├── db/               # Database schema and access rules
├── lang/             # Multi-language support
├── lib.php           # Main enrolment plugin class
├── styles.css        # Plugin-specific styles
├── verify_coupon.php # Coupon validation endpoint
├── version.php       # Plugin version and metadata
└── README.md         # This file
```

---
© 2026 Librería de Satoshi
