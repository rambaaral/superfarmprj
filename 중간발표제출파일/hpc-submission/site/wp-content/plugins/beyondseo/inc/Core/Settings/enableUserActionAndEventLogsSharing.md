# Enable User Action and Event Logs Sharing

## Overview
The `enable_user_action_and_event_logs_sharing` setting controls whether the plugin is permitted to collect and share anonymous usage data and debug logs with the plugin developers. This setting is crucial for privacy compliance and debugging support.

## Technical Details

### Setting Properties
- **Type**: `boolean`
- **Default Value**: `true`
- **Database Key**: `enable_user_action_and_event_logs_sharing`
- **Location**: `WPSettings.php` line 27

## Functionality

### When Enabled (`true`)
- Plugin collects anonymous usage statistics
- Debug logs can be packaged and shared for support
- System metadata is included in diagnostic packages
- Log download functionality is available to administrators

### When Disabled (`false`)
- No anonymous data collection
- Log sharing and download features are blocked
- Enhanced privacy protection
- Limited debugging capabilities for support

## Implementation Details

### Core Components

#### FileManager Class
- **Location**: `inc/Core/FileManager.php`
- **Primary Functions**: Log packaging and download management
- **Validation**: Checks setting before any log operations

#### Key Validation Points
```php
// Log archive creation validation
$settingsManager = SettingsManager::instance();
$enableLogSharing = $settingsManager->get_option('enable_user_action_and_event_logs_sharing', false);

if (!$enableLogSharing) {
    $this->log('Log archive creation denied: Log sharing is disabled by user settings', 'WARNING');
    return null;
}
```

### Log Collection Components

#### Included Log Sources
1. **RankingCoach Logs**: `uploads/rc-logs/` directory
2. **API Logs**: `app/var/log/` directory
3. **WordPress Debug Log**: `wp-content/debug.log`
4. **System Metadata**: WordPress instance information

#### System Metadata Collection
```php
private function generateInstanceMetadata(): array {
    return [
        'wordpress' => [...], // WP version, URLs, settings
        'theme' => [...],     // Active theme information
        'server' => [...],    // PHP, server configuration
        'database' => [...],  // DB version, charset
        'rankingcoach' => [...], // Plugin version, paths
        'active_plugins' => [...] // All active plugins
    ];
}
```

### Log Archive Features

#### Archive Contents
- **Structured Organization**: Logs organized by type and source
- **Metadata File**: `wp_instance_debug.json` with system information
- **Timestamp Naming**: Archives named with creation timestamp
- **Automatic Cleanup**: Temporary files removed after 1 hour

#### Archive Structure
```
rankingcoach-logs-YYYY-MM-DD-HHMMSS.zip
├── wp_instance_debug.json
├── rc-logs/
│   └── [RankingCoach log files]
├── logs/
│   └── [API log files]
└── wordpress/
    └── debug.log
```

## User Interface

### Admin Configuration
- **Location**: RankingCoach → Options → Plugin Options
- **Label**: "Enable anonymous usage data sharing"
- **Description**: "Enable this option to share anonymous usage data with the plugin developers."

### Download Functionality
- **Access Control**: Requires `manage_options` capability
- **Error Handling**: Comprehensive validation and user feedback
- **Security**: Temporary file cleanup and access restrictions

## Privacy and Security

### Data Protection Measures
- **Anonymous Collection**: No personally identifiable information
- **User Consent**: Explicit opt-in required
- **Temporary Storage**: Log archives auto-deleted after 1 hour
- **Access Control**: Admin-only download permissions

### Compliance Features
- **GDPR Compliance**: User control over data sharing
- **Transparency**: Clear indication of what data is collected
- **Revocable Consent**: Can be disabled at any time
- **Data Minimization**: Only necessary debugging information included

## REST API Integration

### Endpoint Configuration
```php
// RestManager.php validation
$enableLogSharing = $settingsManager->get_option('enable_user_action_and_event_logs_sharing', false);
```

### API Response Handling
- **Blocked Operations**: Returns error when sharing disabled
- **Error Messages**: Clear indication of privacy settings
- **Status Codes**: Appropriate HTTP responses for blocked requests

## Error Handling

### When Sharing Disabled
```php
wp_send_json_error([
    'message' => 'Log sharing is disabled. The client has not agreed to share logs for debugging purposes.',
    'code' => 'logs_sharing_disabled'
]);
```

### Validation Checks
1. **User Permissions**: `manage_options` capability required
2. **Setting Status**: Must be enabled for any log operations
3. **File System**: ZipArchive availability and file permissions
4. **Content Validation**: Ensures logs exist before packaging

## Technical Requirements

### System Dependencies
- **ZipArchive Class**: Required for log packaging
- **File Permissions**: Write access to uploads directory
- **WordPress Cron**: For automatic cleanup scheduling

### File System Operations
- **Upload Directory**: Uses WordPress uploads folder
- **Temporary Files**: Automatic cleanup via scheduled events
- **Permissions**: Validates file system access before operations

## Performance Impact

### When Enabled
- **Minimal Runtime Impact**: Only during log operations
- **Storage Usage**: Temporary zip files (auto-cleaned)
- **Processing Load**: Only when downloading logs

### When Disabled
- **Zero Impact**: No data collection or processing
- **Blocked Operations**: All log-related functions return early
- **Resource Savings**: No file system operations

## Debugging and Support

### Support Benefits (When Enabled)
- **Comprehensive Diagnostics**: Full system and plugin information
- **Error Context**: Detailed logs for troubleshooting
- **Configuration Analysis**: Complete WordPress environment data
- **Issue Resolution**: Faster support response times

### Privacy Protection (When Disabled)
- **No Data Collection**: Complete privacy protection
- **Limited Support**: Reduced debugging capabilities
- **Manual Diagnostics**: Support requires manual information gathering

## Best Practices

### Recommended Settings
- **Development/Staging**: Enable for comprehensive debugging
- **Production Sites**: Consider privacy requirements and user preferences
- **Support Scenarios**: Temporarily enable when requesting support
- **Regular Review**: Periodically assess privacy needs

### Security Considerations
- **Regular Cleanup**: Verify automatic file cleanup is working
- **Access Monitoring**: Monitor admin access to log downloads
- **Privacy Audits**: Regular review of collected data types
- **User Communication**: Clear privacy policy regarding data collection

## Troubleshooting

### Common Issues
1. **Download Failures**: Check ZipArchive availability and file permissions
2. **Empty Archives**: Verify log files exist and are readable
3. **Cleanup Issues**: Monitor scheduled event execution
4. **Permission Errors**: Validate user capabilities and file system access

### Debug Steps
1. Verify setting status in database
2. Check file system permissions
3. Validate ZipArchive functionality
4. Monitor scheduled cleanup events
5. Review error logs for specific failures