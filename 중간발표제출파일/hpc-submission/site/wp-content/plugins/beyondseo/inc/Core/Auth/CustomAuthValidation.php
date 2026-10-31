<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Auth;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Exception;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\TokensManager;
use RankingCoach\Inc\Traits\SingletonTrait;
use ReflectionException;
use WP_REST_Request;

/**
 * Class CustomAuthValidation
 */
class CustomAuthValidation {

	use RcLoggerTrait;
    use SingletonTrait;

    /**
     * Custom JWT Token Validation
     *
     * @param WP_REST_Request $request
     *
     * @return bool
     * @throws Exception
     */
	public function hasValidateRequestToken( WP_REST_Request $request ): bool {

		/** @var TokensManager $tokensManager */
        $tokensManager = TokensManager::getInstance();
		$accessToken = $tokensManager->getStoredAccessToken();
		if (TokensManager::validateToken($accessToken, false) === false) {
			// return error
			throw new Exception( esc_html__( 'Invalid account access token are assigned to this JWT', 'beyondseo'), 403 );
		}

		[ $accountId, $expiresAt ] = TokensManager::getJwtParts($accessToken);
		// validate account id and expiresAt
		if( ! $accountId || $expiresAt < time()) {
			// return error
			throw new Exception( esc_html__( 'Invalid account access token are assigned to this JWT', 'beyondseo'), 403 );
		}

		$auth_header = $request->get_header( 'Authorization' );
		if ( ! $auth_header || ! str_starts_with( $auth_header, 'Bearer ' ) ) {
			throw new Exception( esc_html__( 'JWT Token is missing', 'beyondseo'), 403 );
		}

		// Extract token from Authorization header
		$jwt_token = trim( str_replace( 'Bearer ', '', $auth_header ) );

		try {
			// Decode and verify JWT
			$authKey = defined('RC_AUT_KEY') ? RC_AUT_KEY : getenv('RC_AUT_KEY');
			$decoded = \BeyondSEODeps\Firebase\JWT\JWT::decode( $jwt_token, new \BeyondSEODeps\Firebase\JWT\Key( $authKey, 'HS256' ) );

			// Check if token has expired
			if ( $decoded->expiresAt < time() ) {
				throw new Exception( esc_html__( 'JWT Token has expired', 'beyondseo'), 403 );
			}


			// validate the accountId from the JWT needs to be the same as the one in the access token
			if($decoded->accountId !== $accountId) {
				throw new Exception( esc_html__( 'Invalid JWT Token', 'beyondseo'), 403 );
			}

			return true;

		} catch ( Exception $e ) {
			$this->log($e->getMessage(), 'ERROR');
			return false;
		}
	}
}
