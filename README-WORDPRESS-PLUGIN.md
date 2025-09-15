# Ecocash Payment Gateway for WordPress

[![License: MIT][license_badge]][license_link]

A comprehensive WordPress plugin for integrating Ecocash payment services (Zimbabwe) with WooCommerce. This plugin provides seamless payment processing, transaction management, and refund capabilities using the Ecocash API.

> **Note**: This plugin is developed independently and is not officially affiliated with Econet Wireless Zimbabwe or Ecocash. For official documentation and API access, visit [developers.ecocash.co.zw](https://developers.ecocash.co.zw).

## Features 🚀

- **🎯 WooCommerce Integration**: Seamless checkout experience with Ecocash payments
- **💳 Instant Payments**: Real-time C2B payment processing
- **🔍 Transaction Lookup**: Check payment status and transaction details
- **💰 Refund Processing**: Handle payment refunds directly from WordPress admin
- **🛡️ Dual Environment**: Sandbox and live environment support for testing and production
- **📱 Mobile Number Validation**: Automatic validation and formatting of Zimbabwean mobile numbers
- **💵 Multi-Currency**: Support for USD, ZWL, and ZiG currencies
- **📊 Transaction Logging**: Complete transaction history and status tracking
- **🔧 Easy Setup**: Intuitive admin interface with step-by-step setup guide
- **🐛 Debug Logging**: Comprehensive logging for troubleshooting
- **📱 Responsive Design**: Mobile-friendly admin interface

## Requirements 📋

- **WordPress**: 5.0 or higher
- **WooCommerce**: 5.0 or higher  
- **PHP**: 7.4 or higher
- **SSL Certificate**: Required for live payments
- **Ecocash Developer Account**: Get your API keys from [developers.ecocash.co.zw](https://developers.ecocash.co.zw)

## Installation 💻

### Method 1: WordPress Admin (Recommended)

1. Download the plugin ZIP file
2. Go to your WordPress admin dashboard
3. Navigate to **Plugins → Add New**
4. Click **Upload Plugin**
5. Choose the downloaded ZIP file
6. Click **Install Now**
7. Activate the plugin

### Method 2: Manual Installation

1. Download and extract the plugin files
2. Upload the `ecocash-payment-gateway` folder to `/wp-content/plugins/`
3. Go to WordPress admin → **Plugins**
4. Find "Ecocash Payment Gateway" and click **Activate**

### Method 3: Git Clone

```bash
cd /wp-content/plugins/
git clone https://github.com/IamBlackShifu/ecocashWordPress.git ecocash-payment-gateway
```

## Quick Setup Guide 🚀

### 1. Get Your API Credentials

1. Visit [developers.ecocash.co.zw](https://developers.ecocash.co.zw)
2. Sign up for a developer account
3. Create a new application to get your API key
4. Note down both sandbox and live API keys

### 2. Configure the Plugin

1. Go to **WordPress Admin → Ecocash → Settings**
2. Enter your API keys:
   - **Sandbox API Key**: For testing
   - **Live API Key**: For production
3. Enable **Sandbox Mode** for testing
4. Customize gateway title and description
5. Click **Test API Connection** to verify setup
6. Save your settings

### 3. Enable in WooCommerce

1. Go to **WooCommerce → Settings → Payments**
2. Find **Ecocash Payment Gateway**
3. Click **Manage** or toggle to enable
4. Configure additional settings if needed
5. Save changes

### 4. Test Your Setup

1. Ensure **Sandbox Mode** is enabled
2. Create a test product in your store
3. Go through the checkout process
4. Use test mobile number: `263771234567`
5. Complete the payment flow
6. Check **Ecocash → Transactions** for results

### 5. Go Live

1. Add your **Live API Key**
2. Disable **Sandbox Mode**
3. Test with a small real transaction
4. Monitor transactions for any issues

## Configuration Options ⚙️

### General Settings

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Gateway** | Enable/disable Ecocash payments | Disabled |
| **Sandbox Mode** | Use sandbox for testing | Enabled |
| **Gateway Title** | Title shown to customers | "Ecocash Payment" |
| **Description** | Payment method description | "Pay securely using your Ecocash mobile wallet" |
| **Debug Logging** | Enable detailed logging | Disabled |

### API Configuration

| Setting | Description |
|---------|-------------|
| **Sandbox API Key** | Your Ecocash sandbox API key for testing |
| **Live API Key** | Your Ecocash live API key for production |

### Supported Currencies

- **USD** - United States Dollar
- **ZWL** - Zimbabwe Dollar
- **ZiG** - Zimbabwe Gold

## Usage Examples 📖

### Customer Checkout Flow

1. Customer selects Ecocash as payment method
2. Enters their mobile number (e.g., `263771234567`)
3. Completes WooCommerce checkout
4. Receives payment prompt on their phone
5. Confirms payment in Ecocash app
6. Order is automatically updated when payment is confirmed

### Admin Transaction Management

- View all transactions in **Ecocash → Transactions**
- Check individual order payment details
- Process refunds directly from order edit page
- Monitor payment status in real-time

## Mobile Number Formats 📱

The plugin automatically handles various mobile number formats:

| Input Format | Automatically Converted To |
|--------------|---------------------------|
| `0771234567` | `263771234567` |
| `771234567` | `263771234567` |
| `263771234567` | `263771234567` (no change) |
| `+263771234567` | `263771234567` |

## Screenshots 📸

### Admin Settings Page
The intuitive admin interface makes configuration easy:
- API key management
- Environment switching (sandbox/live)
- Connection testing
- Gateway customization

### Checkout Experience
Seamless integration with WooCommerce checkout:
- Mobile number field with validation
- Clear payment instructions
- Real-time status updates

### Transaction Management
Complete transaction oversight:
- Transaction history and status
- Order-specific payment details
- Refund processing capabilities

## Troubleshooting 🔧

### Common Issues

**Q: Payment gateway not showing at checkout**
- Ensure WooCommerce is installed and active
- Check that the gateway is enabled in WooCommerce settings
- Verify your store currency is supported (USD, ZWL, ZiG)
- Make sure API key is configured

**Q: API connection test fails**
- Verify your API key is correct
- Check if you're using the right key for your environment (sandbox/live)
- Ensure your server can make HTTPS requests
- Check if your hosting provider blocks external API calls

**Q: Mobile number validation errors**
- Use Zimbabwean mobile number format: `263XXXXXXXXX`
- The plugin auto-formats common variations
- Ensure the number has exactly 12 digits starting with 263

**Q: Payments not processing**
- Check transaction logs in **Ecocash → Transactions**
- Enable debug logging for detailed error information
- Verify sufficient balance in customer's Ecocash wallet
- Ensure customer completes payment on their phone

### Debug Logging

1. Enable **Debug Logging** in plugin settings
2. Reproduce the issue
3. Check logs in `/wp-content/plugins/ecocash-payment-gateway/logs/`
4. Look for error messages and API responses

## Security Best Practices 🔒

1. **API Key Security**
   - Never expose API keys in frontend code
   - Use environment variables for API keys when possible
   - Regularly rotate your API keys

2. **SSL/HTTPS**
   - Always use SSL certificates in production
   - Ecocash requires HTTPS for live transactions

3. **Server Security**
   - Keep WordPress and plugins updated
   - Use strong passwords and two-factor authentication
   - Regularly backup your site

4. **Transaction Validation**
   - Always verify transaction status before fulfilling orders
   - Use webhook notifications for real-time updates
   - Implement proper error handling

## Contributing 🤝

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Setup

1. Clone the repository
2. Set up a local WordPress development environment
3. Install WooCommerce
4. Configure the plugin with sandbox credentials
5. Test thoroughly before submitting changes

## Changelog 📋

### Version 1.0.0
- Initial release
- Complete Ecocash API integration
- WooCommerce payment gateway
- Admin interface with settings and transaction management
- Support for payments, lookups, and refunds
- Sandbox and live environment support
- Mobile number validation and formatting
- Transaction logging and status tracking
- Debug logging capabilities
- Responsive admin design

## License 📄

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Disclaimer ⚠️

This plugin is developed independently and is not officially affiliated with, endorsed by, or connected to Econet Wireless Zimbabwe or Ecocash. It is an independent implementation based on publicly available APIs and documentation.

For official support, terms of service, and API documentation, please visit [developers.ecocash.co.zw](https://developers.ecocash.co.zw).

## Support the Project ❤️

If this plugin helps your business, consider:

- ⭐ Starring the repository
- 🐛 Reporting bugs and issues
- 💡 Suggesting new features
- 🔄 Contributing code improvements
- 📢 Sharing with other developers

---

**Made with ❤️ for the Zimbabwean developer community**

For questions, support, or feature requests, please open an issue on GitHub or contact the maintainers.

[license_badge]: https://img.shields.io/badge/license-MIT-blue.svg
[license_link]: https://opensource.org/licenses/MIT