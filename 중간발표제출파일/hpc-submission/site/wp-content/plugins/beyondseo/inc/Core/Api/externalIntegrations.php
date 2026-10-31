<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

return [
    'collectorsApi'         => '%s/wp-json/rankingcoach/api/onboarding/retrieveDataFromCollectors',
	'baseUrl'               => 'https://%s.rankingcoach.com/app/api/client/integrations/wordpress/',
    'register'              => 'https://%s.rankingcoach.com/app/api/public/',
    'publicApi'             => 'https://%s.rankingcoach.com/app/api/public/integrations/wordpress/',
	'refreshUrl'            => 'https://%s.rankingcoach.com/app/api/client/common/auth/account/',
	'iframeUrl'             => 'https://%s.rankingcoach.com/customer/index/?do=cls&locale=%s&noCache=1&debug=1&projectId=%d&sessionId=%s&parentOrigin=%s&jwtSource=internal_requests&jwtToken=%s',
    'iframeMapUrl'          => 'https://%s.rankingcoach.com/customer/client/redirect?do=cls&locationId=%d&jwtSource=internal_requests&jwtToken=%s&redirect_url=https://%s.rankingcoach.com/%s/business-info?debug=1',
    'codeUrl'               => 'https://%s.rankingcoach.com/%s/c/%s?redirecturl=%s',
	'devEnv'                => 'wp',
    'liveEnv'               => 'wp'
];
