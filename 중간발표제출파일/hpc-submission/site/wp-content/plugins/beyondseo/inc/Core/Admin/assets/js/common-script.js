(function($) {
    'use strict';

    // Listen for custom plugin update events
    $(document).on('wp-plugin-update-success', function (event, data) {
        
        // Find and remove the update badge element from the DOM
        let updateBadgeElement = $('.rc-toolbar-badge.rc-badge-update');
        if (updateBadgeElement.length > 0) {
            updateBadgeElement.remove();
        }
    });

})(jQuery);