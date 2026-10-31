# Allow Sync Keywords to RankingCoach

## Overview
The `allow_sync_keywords_to_rankingcoach` setting controls whether the plugin is permitted to synchronize keywords from the WordPress site to the RankingCoach platform. This is a core functionality that enables automated keyword tracking and analysis.

## Technical Details

### Setting Properties
- **Type**: `boolean`
- **Default Value**: `true`
- **Database Key**: `allow_sync_keywords_to_rankingcoach`
- **Location**: `WPSettings.php` line 18

## Functionality

### When Enabled (`true`)
- The `SyncKeywordsJob` class will execute keyword synchronization at the specified interval
- Keywords from WordPress posts and pages are automatically sent to the RankingCoach platform
- The synchronization process runs via ActionScheduler as a background job
- Requires onboarding to be completed (`WordpressHelpers::isOnboardingCompleted()`)

### When Disabled (`false`)
- The `SyncKeywordsJob` automatically unschedules itself when it detects the setting is disabled
- No keyword data is transmitted to the RankingCoach platform
- Existing scheduled jobs are cleaned up to prevent unnecessary processing

## Implementation Details

### Core Components

#### SyncKeywordsJob Class
- **Location**: `inc/Core/Jobs/SyncKeywordsJob.php`
- **Hook**: `keywords_synchronization`
- **Execution**: Managed by ActionScheduler
- **Validation**: Checks setting status before each execution

#### Key Methods
```php
// Setting validation
protected const ENABLE_SETTING_KEY = 'allow_sync_keywords_to_rankingcoach';

// Execution logic
public function execute(): void {
    if (!$this->isJobEnabled()) {
        $this->unscheduleJob();
        return;
    }
    
    $result = ContentApiManager::handleKeywordsSynchronization();
}
```

### User Interface
- **Admin Panel**: Available in Options tab (`OptionsTabRenderer.php`)
- **Form Field**: Checkbox input with dynamic interval display
- **AJAX Handler**: `OptionsAjaxHandler.php` processes form submissions
- **REST API**: Exposed via `RestManager.php` as advanced setting

### Logging and Monitoring
The job provides comprehensive logging:
- Operation status (completed_successfully, failed, skipped_disabled)
- Context tracking (sync_keywords_job, synchronization)
- Error handling with detailed exception information
- Timestamp tracking for audit purposes

## Configuration

### How to Modify
1. **Admin Interface**: Navigate to RankingCoach → Options → Plugin Options
2. **Programmatically**: 
   ```php
   $settings = SettingsManager::instance();
   $settings->update_option('allow_sync_keywords_to_rankingcoach', true);
   ```
3. **REST API**: Available through the plugin's REST endpoints

### Prerequisites
- Plugin must be activated and configured
- User must have completed onboarding process
- Valid RankingCoach API credentials required

## Impact on Plugin Functionality

### When Enabled
- Automatic keyword tracking and analysis
- Regular synchronization with RankingCoach platform
- Enhanced SEO insights and recommendations
- Background processing via WordPress cron system

### When Disabled
- Manual keyword management only
- No automated data transmission
- Reduced plugin functionality
- Lower resource usage

## Security Considerations
- Setting is validated as boolean type in REST API
- Requires appropriate user capabilities for modification
- Synchronization only occurs with valid authentication
- Respects user privacy preferences

## Performance Impact
- Minimal when disabled (no background jobs)
- Moderate when enabled (scheduled background synchronization)
- Uses ActionScheduler for efficient job management

## Troubleshooting

### Common Issues
1. **Synchronization Not Working**: Verify onboarding is completed
2. **Jobs Not Scheduling**: Check ActionScheduler functionality
3. **API Errors**: Validate RankingCoach credentials
4. **Performance Issues**: Adjust sync interval if needed

### Debug Information
Check logs for entries with context `keywords_sync` to monitor synchronization status and identify issues.