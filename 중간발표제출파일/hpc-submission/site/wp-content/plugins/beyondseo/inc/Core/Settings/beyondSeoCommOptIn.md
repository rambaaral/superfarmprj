# beyondseo_comm_opt_in Setting

Controls whether the user has explicitly opted in to allow the plugin to communicate 
with rankingCoach backend servers.

## Properties
- **Database Key**: `beyondseo_comm_opt_in`
- **Type**: `boolean`
- **Default**: `false` (off — explicit opt-in required)
- **WordPress Compliant**: Yes — off by default, requires active user opt-in

## How Consent Is Collected
Users can provide consent in two places:
1. During account registration via an optional checkbox labelled  
   "(Optional) I allow the rankingCoach plugin to communicate with rankingCoach backend servers..."
2. At any time via Plugin Settings → General Settings →  
   "Allow communication with rankingCoach servers" toggle.

## Usage in PHP
```php
$settingsManager = SettingsManager::instance();
$commOptIn = $settingsManager->get_option('beyondseo_comm_opt_in', false);

if ($commOptIn) {
    // Only communicate with backend if user has explicitly opted in
}
