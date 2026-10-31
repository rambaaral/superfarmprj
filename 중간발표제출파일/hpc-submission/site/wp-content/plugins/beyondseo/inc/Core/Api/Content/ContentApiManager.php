<?php
declare( strict_types=1 );

namespace RankingCoach\Inc\Core\Api\Content;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use RankingCoach\Inc\Core\DB\DatabaseTablesManager;
use RankingCoach\Inc\Core\DB\DatabaseManager;
use RankingCoach\Inc\Exceptions\HttpApiException;
use RankingCoach\Inc\Core\Api\HttpApiClient;
use ReflectionException;
use Throwable;

/**
 * Class ContentApiManager
 */
class ContentApiManager extends HttpApiClient
{
    use RcLoggerTrait;

	/**
	 * Singleton instance
	 */
	protected static ?ContentApiManager $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @param array $defaultHeaders
	 * @param bool|null $bearerToken
	 *
	 * @return ContentApiManager
	 * @throws HttpApiException
	 * @throws ReflectionException
	 * @throws Exception
	 */
	public static function getInstance( array $defaultHeaders = [], ?bool $bearerToken = false ): ContentApiManager {
		if ( ! self::$instance ) {
			if($bearerToken) {
				$accessToken = self::handleTokenValidation();
			}
			self::$instance = new self($defaultHeaders, $accessToken ?? null);
		}

		return self::$instance;
	}

	/**
	 * ContentApiManager constructor.
	 *
	 * @param array $defaultHeaders
	 * @param string|null $accessToken
	 */
	public function __construct(
		array $defaultHeaders = [],
		?string $accessToken = null
	) {
		parent::__construct($defaultHeaders, $accessToken);
	}
	
	/**
	 * Synchronize keywords with RankingCoach API.
	 * 
	 * This method fetches all keywords from the local database,
	 * sends them to the RankingCoach API for synchronization,
	 * and updates the local database with the returned data.
	 * 
	 * @return null|array True if synchronization was successful, false otherwise.
	 * @throws Exception
	 */
	private function synchronizeKeywords(): ?array
	{
	    try {
	        // Get all keywords from the database
            /** @var array $keywords */
	        $keywords = $this->getKeywordsFromDatabase();

            // Validate the keywords set
	        if (empty($keywords)) {
                throw new Exception(esc_html__('No keywords found to synchronize', 'beyondseo'));
	        }

            // Check if keywords have external IDs null, otherwise no need to synchronize
            $continue = false;
            foreach ($keywords as $keyword) {
                if (is_null($keyword['externalId'])) {
                    $continue = true;
                }
            }
            if(!$continue) {
                throw new Exception(esc_html__('All keywords already have external IDs, no need to synchronize', 'beyondseo'));
            }
	        
	        // Prepare the payload for the API synchronization request
	        $payload = $this->generateCommonSecurityPayload([
	            'keywords' => $keywords,
	        ]);
	        
	        $this->setUrl('sync/keywords', 'baseUrl', true);

	        $this->prepareSecurityHeaders($this->getBearerToken(), $payload);
	        
	        $response = $this->post($payload);

	        if (empty($response['content'])) {
                throw new Exception(__('Empty response from keywords sync API', 'beyondseo'));
	        }
            $responseArray = json_decode(json_encode($response['content']), true);

            // Process the response and update the database
            $this->processKeywordsSyncResponse($responseArray);
            $numberOfRemainsKeywords = (int)$responseArray['numberOfRemainsKeywords'];

            // Update the last sync timestamp
	        update_option(BaseConstants::OPTION_SYNC_KEYWORDS_LAST_SYNC, time(), false);
            update_option(BaseConstants::OPTION_SYNC_KEYWORDS_REMAINS_KEYWORDS, $numberOfRemainsKeywords, true);

	        return $responseArray['keywords']['elements'];
	    } catch (Exception $e) {
            throw new Exception(esc_html($e->getMessage()));
	    }
	}

    /**
     * Retrieve all keywords from the database.
     *
     * @return array|null Set of keywords with their properties.
     * @throws Exception
     */
	private function getKeywordsFromDatabase(): ?array
	{
	    try {
	        $dbManager = DatabaseManager::getInstance();
	        
	        // Get all keywords from the database
	        $keywords = $dbManager->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
	            ->select(['*'])
	            ->get();
	        
	        if (empty($keywords)) {
	            return [];
	        }
	        
	        // Convert objects to arrays if needed
	        if (is_object($keywords[0])) {
	            $keywordsArray = [];
	            foreach ($keywords as $keyword) {
	                $keywordsArray[] = (array) $keyword;
	            }
	            return $keywordsArray;
	        }
	        
	        return $keywords;
	        
	    } catch (Exception $e) {
	        $this->log('Error fetching keywords: ' . $e->getMessage(), 'ERROR');
	        /* translators: %s is the error message */
	        throw new Exception(sprintf(esc_html__('Error fetching keywords: %s', 'beyondseo'), esc_html($e->getMessage())) );
	    }
	}

    /**
     * Process the response from the keywords sync API and update the database.
     *
     * @param array $response The response from the API.
     * @return void
     * @throws Exception
     */
	private function processKeywordsSyncResponse(array $response): void
	{
	    $dbManager = DatabaseManager::getInstance();

	    // Check if the response contains errors
        if (!empty($response) && isset($response['error'])) {
            $this->log('Error in keywords sync API response: ' . $response['error'], 'ERROR');
            /* translators: %s is the API error message */
            throw new Exception(sprintf(esc_html__('Error in keywords sync API response: %s', 'beyondseo'), esc_html($response['error'])) );
        }

        // Check if the response indicates a failure
        if (!empty($response) && isset($response['success']) && !$response['success']) {
            // split message from trace content. #0 is the first part of the trace message, until that point
            $message = explode('#0', $response['message'], 2);
            if (count($message) > 1) {
                $response['message'] = $message[0];
            }
            $this->log('Keywords sync API returned non-success message: ' . $response['message'], 'WARNING');
            /* translators: %s is the API response message */
            throw new Exception(sprintf(esc_html__('Keywords sync API returned non-success message: %s', 'beyondseo'), esc_html($response['message'])) );
        }

	    // Validate response structure
	    if (empty($response) || !isset($response['keywords']) || !isset($response['keywords']['elements']) || !is_array($response['keywords']['elements'])) {
	        $this->log('Invalid response format from keywords sync API', 'ERROR');
	        /* translators: %s is the API response data */
            throw new Exception(sprintf(esc_html__('Invalid response format from keywords sync API. Response: %s', 'beyondseo'), json_encode($response)));
	    }
	    
	    $updatedCount = 0;
	    $insertedCount = 0;
	    $keywordElements = $response['keywords']['elements'];
	    
	    // Get all existing keywords by alias for efficient lookup
	    $existingKeywords = [];
	    $existingKeywordsResult = $dbManager->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
	        ->select(['id', 'alias'])
	        ->get();
	    
	    if ($existingKeywordsResult) {
	        foreach ($existingKeywordsResult as $row) {
	            $rowData = is_object($row) ? (array) $row : $row;
	            if (!empty($rowData['alias'])) {
	                $existingKeywords[$rowData['alias']] = $rowData['id'];
	            }
	        }
	    }
	    
	    $dbManager->beginTransaction();
	    
	    try {
	        foreach ($keywordElements as $keyword) {
	            // Skip invalid keywords
	            if (empty($keyword['alias'])) {
	                continue;
	            }
	            
	            // Prepare data for update/insert
	            $keywordData = [
	                'externalId' => $keyword['externalId'] ?? null,
	                'name' => $keyword['name'] ?? '',
	                'hash' => $keyword['hash'] ?? null,
	                'alias' => $keyword['alias']
	            ];
	            
	            // Check if keyword exists by alias
	            if (isset($existingKeywords[$keyword['alias']])) {
	                // Update existing keyword
	                $result = $dbManager->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
	                    ->update()
	                    ->set($keywordData)
	                    ->where('alias', $keyword['alias'])
	                    ->get();
	                
	                if ($result !== false) {
	                    $updatedCount++;
	                }
	            } else {
	                // Insert new keyword
	                $result = $dbManager->table(DatabaseTablesManager::DATABASE_APP_KEYWORDS)
	                    ->insert()
	                    ->set($keywordData)
	                    ->get();
	                
	                if ($result) {
	                    $insertedCount++;
	                    // Add to existing keywords map for subsequent lookups
	                    $existingKeywords[$keyword['alias']] = $result;
	                }
	            }
	        }
	        
	        $dbManager->commit();
	    } catch (Exception $e) {
	        $dbManager->rollback();
	        $this->log('Error processing keywords sync response: ' . $e->getMessage(), 'ERROR');
	        throw new Exception(
	            sprintf(
                /* translators: %s is the error message */
                esc_html__('Error processing keywords sync response: %s', 'beyondseo'),
	                esc_html($e->getMessage())
	            )
	        );
	    }
	}

    /**
     * Static method to handle keywords synchronization from cron jobs.
     *
     * @return null|array Returns the synchronized keywords or null if synchronization failed.
     * @throws Throwable
     */
	public static function handleKeywordsSynchronization(): ?array
	{
	    /** @var ContentApiManager $instance This handles the API requests */
	    $instance = self::getInstance([], null, true);
	    return $instance->synchronizeKeywords();
	}
}
