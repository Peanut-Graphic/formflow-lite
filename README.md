# FormFlow Lite

> **Dependency updates:** `renovate.json` extends `local>peanutgraphic/renovate-config`, but Renovate is not installed on the Peanut-Graphic GitHub org, so it has never opened a PR here. `.github/dependabot.yml` provides automated dependency PRs instead — see that file to add/adjust ecosystems.

A lightweight, API-integrated enrollment and scheduling form plugin for WordPress. Designed for utility demand response programs with seamless third-party API integration.

## Features

### Form Management
- **Multi-Step Forms** - Create guided enrollment workflows
- **Conditional Logic** - Show/hide fields based on user input
- **Form Templates** - Pre-built templates for common use cases
- **Resume Capability** - Save progress and continue later via email link

### API Integration
- **Connector System** - Modular API connectors for different providers
- **Real-Time Validation** - Validate data against external APIs
- **Appointment Scheduling** - Fetch and display available time slots
- **Auto-Enrollment** - Submit enrollments directly to provider systems

### Data Management
- **Submission Storage** - Secure local storage of form submissions
- **Export Options** - CSV export for reporting
- **Webhook Support** - Real-time notifications to external systems
- **Activity Logging** - Detailed submission and error logs

### Admin Dashboard
- **React SPA Interface** - Modern admin experience
- **Submission Viewer** - Review and manage form submissions
- **Analytics** - Basic form performance metrics
- **API Usage Tracking** - Monitor external API calls

## Requirements

- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+

The installed plugin and shared FormFlow Core runtime support PHP 8.0 and
later. Local dependency resolution and the complete PHP test suites require
PHP 8.1 or later because the locked PHPUnit graph includes
`doctrine/instantiator` 2.x. Required CI separately proves exact PHP 8.0
parsing/declarations, the full suites on exact PHP 8.1, current PHP 8.2/8.3,
and the real WordPress contract on PHP 8.4.

## Installation

1. Upload the `formflow-lite` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Navigate to **FormFlow** in the admin menu
4. Create your first form instance

## Quick Start

1. Go to **FormFlow > Instances** and click **Add New**
2. Select a connector (API provider) or use standalone mode
3. Configure form fields and settings
4. Use the shortcode to embed the form: `[formflow id="1"]`

## Shortcodes

| Shortcode | Description |
|-----------|-------------|
| `[formflow id="X"]` | Display form instance X |
| `[formflow id="X" theme="dark"]` | Display with dark theme |

## REST API

The plugin provides a REST API at `/wp-json/fffl/v1/`:

### Public Endpoints
```
GET  /wp-json/fffl/v1/form/{id}         # Get form configuration
POST /wp-json/fffl/v1/submit            # Submit form data
GET  /wp-json/fffl/v1/appointments/{id} # Get available appointments
POST /wp-json/fffl/v1/validate          # Validate field data
GET  /wp-json/fffl/v1/resume/{token}    # Resume saved form
```

### Admin Endpoints (Authentication Required)
```
GET  /wp-json/fffl/v1/admin/instances    # List form instances
POST /wp-json/fffl/v1/admin/instances    # Create instance
GET  /wp-json/fffl/v1/admin/submissions  # List submissions
GET  /wp-json/fffl/v1/admin/logs         # View activity logs
GET  /wp-json/fffl/v1/admin/webhooks     # Webhook configurations
```

## Database Tables

The plugin creates tables with the `fffl_` prefix:
- `fffl_instances` - Form instance configurations
- `fffl_submissions` - Form submissions
- `fffl_logs` - Activity and error logs
- `fffl_resume_tokens` - Form resume tokens
- `fffl_webhooks` - Webhook configurations
- `fffl_api_usage` - API call tracking

## Connectors

FormFlow Lite supports modular connectors for different API providers:

### Available Connectors
- **Standalone** - No API integration, local storage only
- **Custom** - Build your own connector

### Creating a Custom Connector
```php
// connectors/my-connector/class-connector.php
namespace FFFL\Connectors\MyConnector;

class Connector extends \FFFL\Connectors\Base_Connector {
    public function validate_account($account_number) {
        // Implement validation logic
    }

    public function get_appointments($params) {
        // Fetch available appointments
    }

    public function submit_enrollment($data) {
        // Submit to external API
    }
}
```

## Hooks & Filters

### Actions
```php
do_action('fffl_form_submitted', $submission_id, $data);
do_action('fffl_form_validated', $instance_id, $data);
do_action('fffl_webhook_sent', $webhook_id, $response);
do_action('fffl_api_error', $connector, $error);
```

### Filters
```php
apply_filters('fffl_form_fields', $fields, $instance_id);
apply_filters('fffl_validation_rules', $rules, $field);
apply_filters('fffl_submission_data', $data, $instance_id);
apply_filters('fffl_api_request', $request, $connector);
```

## Upgrade to FormFlow Pro

FormFlow Pro includes additional features:
- Advanced analytics and funnel tracking
- A/B testing for form variations
- Document upload with virus scanning
- Fraud detection and prevention
- GDPR compliance tools
- Scheduled reports
- Priority support

Visit [formflow.dev](https://formflow.dev) to learn more.

## Development

### Prerequisites
- PHP 8.1+ for Composer dependencies and test tooling
- Node.js 22.22.2 with npm 10.9.7 (`nvm use` from the repository root)
- Composer

### Setup
```bash
# Clone the repository
git clone https://github.com/peanutgraphic/formflow-lite.git
cd formflow-lite

# Install frontend dependencies
cd frontend
npm ci --legacy-peer-deps

# Verify package, lockfile, version-file, CI, and active runtime declarations
npm run runtime:verify

# Build frontend assets
npm run build

# Start development server
npm run dev
```

### Testing
```bash
# Run PHP tests
./vendor/bin/phpunit

# Run frontend tests
cd frontend && npm run test
```

## Security

- All database queries use prepared statements
- Input sanitization on all endpoints
- Output escaping throughout templates
- CSRF protection via WordPress nonces
- Secure token generation for form resume

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

GPL v2 or later

## Support

For issues and feature requests, please [open an issue](https://github.com/peanutgraphic/formflow-lite/issues).
