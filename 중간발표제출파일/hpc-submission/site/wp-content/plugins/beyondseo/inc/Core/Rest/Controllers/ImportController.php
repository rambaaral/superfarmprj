<?php

declare(strict_types=1);

namespace RankingCoach\Inc\Core\Rest\Controllers;

use InvalidArgumentException;
use RankingCoach\Inc\Core\Import\ImportRunner;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

final class ImportController
{
    public function registerRoutes(string $namespace): void
    {
        register_rest_route($namespace, '/imports/sources', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'sources'],
            'permission_callback' => [$this, 'permission'],
        ]);
        register_rest_route($namespace, '/imports/sources/(?P<source>aioseo|rankmath|yoast)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'source'],
            'permission_callback' => [$this, 'permission'],
        ]);
        register_rest_route($namespace, '/imports/sources/(?P<source>aioseo|rankmath|yoast)/options', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'sourceOptions'],
            'permission_callback' => [$this, 'permission'],
        ]);
        register_rest_route($namespace, '/imports', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'run'],
            'permission_callback' => [$this, 'permission'],
            'args' => [
                'source' => ['required' => true, 'type' => 'string', 'enum' => ['aioseo', 'rankmath', 'yoast']],
                'options' => [
                    'required' => false,
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'validate_callback' => [$this, 'validateOptions'],
                    'sanitize_callback' => static fn(mixed $value): mixed => $value,
                ],
            ],
        ]);
    }

    public function permission(): bool
    {
        return current_user_can('manage_options');
    }

    public function sources(): WP_REST_Response
    {
        return new WP_REST_Response(['sources' => (new ImportRunner())->sources()]);
    }

    public function source(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response((new ImportRunner())->source($request['source']));
    }

    public function sourceOptions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            return new WP_REST_Response((new ImportRunner())->sourceOptions($request['source']));
        } catch (Throwable $error) {
            return new WP_Error('seo_import_options_failed', __('Unable to inspect SEO import options. Please retry.', 'beyondseo'), ['status' => 500]);
        }
    }

    public function validateOptions(mixed $value, WP_REST_Request $request, string $param): bool|WP_Error
    {
        if ($request->is_json_content_type()) {
            $body = json_decode($request->get_body());
            if (is_object($body) && property_exists($body, $param) && !is_array($body->{$param})) {
                return new WP_Error('seo_import_options_invalid', __('SEO import options must be an array of strings.', 'beyondseo'), ['status' => 400]);
            }
        }
        if (!is_array($value)) {
            return new WP_Error('seo_import_options_invalid', __('SEO import options must be an array of strings.', 'beyondseo'), ['status' => 400]);
        }
        $valid = rest_validate_request_arg($value, $request, $param);
        if (is_wp_error($valid)) {
            $valid->add_data(array_merge((array) $valid->get_error_data(), ['status' => 400]));
            return $valid;
        }
        $source = $request->get_param('source');
        if (!is_string($source) || !in_array($source, ['aioseo', 'rankmath', 'yoast'], true)) {
            return new WP_Error('rest_invalid_param', __('Invalid SEO import source.', 'beyondseo'), ['status' => 400]);
        }
        try {
            (new ImportRunner())->normalizeOptions($source, $value);
        } catch (InvalidArgumentException $error) {
            return new WP_Error('seo_import_options_invalid', __('SEO import options must contain only supported option identifiers for the selected source.', 'beyondseo'), ['status' => 400]);
        }
        return true;
    }

    public function run(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $importer = new ImportRunner();
        $source = $request->get_param('source');
        $options = [];
        if ($request->has_param('options')) {
            $options = $request->get_param('options');
            $valid = $this->validateOptions($options, $request, 'options');
            if (is_wp_error($valid)) {
                return $valid;
            }
            $options = $importer->normalizeOptions($source, $options);
        }
        if (!$importer->source($source)['installed']) {
            return new WP_Error('seo_import_source_missing', __('SEO plugin is not installed.', 'beyondseo'), ['status' => 400]);
        }
        try {
            return new WP_REST_Response($importer->import($source, $options));
        } catch (Throwable $error) {
            return new WP_Error('seo_import_failed', __('SEO import failed. Some data may have been imported; please retry.', 'beyondseo'), ['status' => 500]);
        }
    }
}
