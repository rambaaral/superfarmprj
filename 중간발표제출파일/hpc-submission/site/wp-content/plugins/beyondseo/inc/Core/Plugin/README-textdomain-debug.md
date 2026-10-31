# RankingCoach Text Domain Debug Tool

This tool helps identify where the 'beyondseo' text domain is being loaded too early in the WordPress lifecycle, which causes the following error in WordPress 6.7+:

```
Notice: Function _load_textdomain_just_in_time was called incorrectly. Translation loading for the rankingcoach domain was triggered too early. This is usually an indicator for some code in the plugin or theme running too early. Translations should be loaded at the init action or later.
```

## How It Works

The debug tool hooks into WordPress at the earliest possible point and tracks:

1. All requests for the 'beyondseo' text domain before the 'init' hook
2. All instances where WordPress reports that the text domain is being loaded too early

When either of these conditions is detected, the tool logs:
- A timestamp
- The complete call stack showing which functions triggered the text domain loading
- File paths and line numbers to help identify the source of the issue

## Log Location

Logs are written to two locations:
- `uploads/rc-logs/textdomain_debug.log` - A dedicated log file for this issue
- PHP's error_log - Prefixed with 'RANKINGCOACH TEXTDOMAIN DEBUG:'

## How to Fix the Issue

Once you've identified the source of the early text domain loading, you have several options:

1. **Delay the code execution**: Move the code that's using translations to run after the 'init' hook.

2. **Remove translation calls**: If the code must run early, consider removing translation functions or using a placeholder that will be translated later.

3. **Use a lazy-loading approach**: Instead of directly using translation functions, create a function that checks if 'init' has run before attempting to translate.

## Example Fix

If you find that a function is using `__()` or other translation functions too early, you could modify it like this:

```php
// Before (problematic)
function early_function() {
    $message = __('Some text', 'beyondseo');
    // ...
}

// After (fixed)
function early_function() {
    // Option 1: Use a placeholder and translate later
    $message = 'Some text'; // Will be translated later when displayed
    
    // Option 2: Check if we can translate yet
    $message = did_action('init') 
        ? __('Some text', 'beyondseo') 
        : 'Some text';
    
    // ...
}
```

## Removing the Debug Tool

Once the issue is fixed, you can safely remove:
1. The inclusion of `debug-textdomain.php` from the main plugin file
2. The `debug-textdomain.php` file itself
