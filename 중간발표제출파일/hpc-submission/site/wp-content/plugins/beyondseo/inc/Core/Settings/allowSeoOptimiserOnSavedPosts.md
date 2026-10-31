# Allow SEO Optimiser on Saved Posts

## Overview
The `allow_seo_optimiser_on_saved_posts` setting controls whether the plugin automatically runs SEO score calculation and optimization analysis when posts or pages are saved. This feature provides real-time SEO feedback to content creators.

## Technical Details

### Setting Properties
- **Type**: `boolean`
- **Default Value**: `true`
- **Database Key**: `allow_seo_optimiser_on_saved_posts`
- **Location**: `WPSettings.php` line 24

## Functionality

### When Enabled (`true`)
- SEO optimization automatically triggers on post/page save events
- Real-time SEO score calculation via internal API
- Automatic analysis of content, keywords, and SEO factors
- Background processing with retry mechanisms for reliability

### When Disabled (`false`)
- No automatic SEO analysis on save
- Manual SEO optimization only
- Reduced server load during content editing
- SEO scores must be calculated manually or on page load

## Implementation Details

### Core Components

#### PostEventsManager Class
- **Location**: `inc/Core/PostEventsManager.php`
- **Hook**: `save_post` (priority 20)
- **Validation**: Checks setting before processing

#### Key Integration Points
```php
// Setting validation in PostEventsManager
$setting = SettingsManager::instance();
if((bool)$setting->get_option('allow_seo_optimiser_on_saved_posts', false) === true) {
    $canProcessSeoScoreCalculation = $this->shouldProcessSeoOptimization($post_id, $post, $update);
}
```

### Processing Logic

#### Trigger Conditions
- Post type must be 'post' or 'page'
- User must have edit permissions
- Not during autosave operations
- Not for post revisions
- Prevents duplicate processing within same request

#### SEO Optimization Process
1. **API Endpoint**: `/wp-json/rankingcoach/api/optimiser/{postId}`
2. **Authentication**: Basic auth with application password
3. **Retry Logic**: Up to 3 attempts with exponential backoff
4. **Timeout**: 30 seconds per request
5. **Result Storage**: SEO score saved to post meta

### Advanced Features

#### Duplicate Prevention
- Request-level tracking via `$processedPosts` array
- Cross-request throttling using transients (5-second window)
- Transient key pattern: `rc_post_processing_{post_id}`

#### Fallback Processing
- SEO analysis also triggers on post load if score doesn't exist
- Ensures content always has SEO data available
- Handles cases where save-time processing failed

## User Interface

### Admin Configuration
- **Location**: RankingCoach → Options → Plugin Options
- **Label**: "Allow run score calculation on page/post save"
- **Description**: "Enable this option to automatically run the SEO score calculation when a page or post is saved."

### Form Processing
- **Handler**: `OptionsAjaxHandler.php`
- **Validation**: Boolean checkbox processing
- **REST API**: Available as advanced setting

## Performance Considerations

### When Enabled
- **API Calls**: One per post/page save
- **Processing Time**: Up to 30 seconds per optimization
- **Retry Logic**: Additional attempts on failure
- **Background Processing**: Non-blocking for user experience

### When Disabled
- **Zero Impact**: No processing on save events
- **Manual Trigger**: SEO analysis only on page load if needed
- **Reduced Load**: Lower server resource usage

## SEO Analysis Components

### Content Analysis
- Keyword density and placement
- Content structure and readability
- Meta tags and descriptions
- Internal linking patterns

### Technical SEO
- Page load performance factors
- Mobile responsiveness indicators
- Schema markup validation
- URL structure analysis

### Result Storage
- **Meta Key**: `BaseConstants::OPTION_ANALYSIS_SEO_SCORE`
- **Status Tracking**: `BaseConstants::OPTION_ANALYSIS_STATUS`
- **Values**: 'completed', 'failed', or processing state

## Error Handling

### Retry Mechanism
```php
$maxRetries = 3;
$retryCount = 0;
while ($retryCount <= $maxRetries && !$success) {
    // API call with exponential backoff
    sleep(pow(2, $retryCount - 1));
}
```

### Logging
- Comprehensive error tracking
- API response logging (first 100 characters)
- Retry attempt monitoring
- Final outcome recording

## Security Features

### Authentication
- Application password validation
- User capability verification
- Referer header validation
- SSL verification in production

### Data Protection
- No sensitive data in logs
- Secure API communication
- User permission checks
- Input validation and sanitization

## Troubleshooting

### Common Issues
1. **SEO Scores Not Calculating**: Check API credentials and connectivity
2. **Performance Issues**: Consider disabling for high-traffic sites
3. **Timeout Errors**: Verify server configuration and API response times
4. **Duplicate Processing**: Monitor transient cleanup and request handling

### Debug Information
- Check error logs for API call failures
- Monitor post meta for SEO score storage
- Verify application password functionality
- Review ActionScheduler logs for background processing

## Best Practices

### Recommended Usage
- Enable for content-focused sites requiring real-time SEO feedback
- Disable for high-volume publishing environments
- Monitor server performance after enabling
- Ensure adequate server resources for API processing

### Performance Optimization
- Consider manual SEO analysis for bulk content operations
- Monitor API response times and adjust timeout if needed
- Use staging environment to test impact before production deployment