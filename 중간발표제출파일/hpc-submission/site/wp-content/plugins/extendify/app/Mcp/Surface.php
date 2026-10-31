<?php

/**
 * The tools a connection is offered, and the calls they make.
 */

namespace Extendify\Mcp;

defined('ABSPATH') || die('No direct access.');

/**
 * The model supplies arguments and never a path. Abilities a plugin registers
 * after we ship are reachable without a plugin release.
 */
class Surface
{
    // phpcs:disable PSR12.Properties.ConstantVisibility.NotFound
    /**
     * Model APIs cap a tool name at 64 characters.
     */
    const MAX_NAME = 64;

    /**
     * A handler's deliberate refusals, which the usage count keeps apart from failures.
     */
    const REFUSALS = ['extendify_mcp_refused', 'extendify_mcp_unconfirmed'];

    /**
     * A client validates the schema it is handed, and a plugin may write keys JSON Schema lacks.
     */
    const SCHEMA_KEYWORDS = [
        'additionalProperties',
        'anyOf',
        'const',
        'default',
        'description',
        'enum',
        'exclusiveMaximum',
        'exclusiveMinimum',
        'format',
        'items',
        'maxItems',
        'maxLength',
        'maximum',
        'minItems',
        'minLength',
        'minimum',
        'multipleOf',
        'oneOf',
        'pattern',
        'properties',
        'required',
        'title',
        'type',
        'uniqueItems',
    ];

    const DISCOVERY = ['list_tools', 'run_tool', 'run_tool_write'];
    // phpcs:enable PSR12.Properties.ConstantVisibility.NotFound

    /**
     * Clients keep this a day or more, and abilities change with plugins, so list_tools carries them.
     *
     * @param array $grants - What the connection is allowed to do.
     * @return array
     */
    public static function tools(array $grants)
    {
        return array_column(self::written($grants), 'definition');
    }

    /**
     * @param array $params     - The tools/call params.
     * @param array $grants     - What the connection is allowed to do.
     * @param array $connection - The connection the call arrived on.
     * @return array|\WP_Error
     */
    public static function call(array $params, array $grants, array $connection = [])
    {
        $name = (string) ($params['name'] ?? '');
        $started = microtime(true);
        $outcome = ['outcome' => 'ok'];

        try {
            $offered = self::offered($grants);
            if (!isset($offered[$name])) {
                $error = sprintf('Unknown tool: %s', $name);
                $outcome = ['outcome' => 'refused', 'error' => $error, 'reason' => 'extendify_mcp_unknown_tool'];

                return new \WP_Error('extendify_mcp_unknown_tool', $error);
            }

            $tool = $offered[$name];
            $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
            if (isset($tool['runs'])) {
                list($tool, $arguments) = self::target($tool, $arguments, self::catalog($offered));
                $name = $tool['definition']['name'];
            }

            $answered = Guard::during(function () use ($tool, $arguments, $grants) {
                return isset($tool['handler'])
                    ? self::handled($tool, $arguments, $grants)
                    : self::answer(\rest_do_request(self::abilityRequest($tool, $arguments)), $tool);
            });
            if (!\is_wp_error($answered)) {
                return $answered;
            }

            $outcome = [
                'outcome' => in_array($answered->get_error_code(), self::REFUSALS, true) ? 'refused' : 'error',
                'error' => $answered->get_error_message(),
                'reason' => (string) $answered->get_error_code(),
            ];

            return self::problem($answered->get_error_message());
        } catch (Refused $refused) {
            $outcome = ['outcome' => 'refused', 'error' => $refused->getMessage(), 'reason' => $refused->reason()];

            return self::problem($refused->getMessage());
        } catch (\Throwable $failed) {
            // Uncaught, this is WordPress's critical-error page, which no client can parse.
            $outcome = [
                'outcome' => 'error',
                'error' => $failed->getMessage(),
                'reason' => 'php:' . (new \ReflectionClass($failed))->getShortName(),
            ];

            return self::problem(sprintf('%s failed: %s', $name, $failed->getMessage()));
        } finally {
            $outcome['duration'] = microtime(true) - $started;
            Log::write($connection, $name, $outcome);
        }
    }

    /**
     * @param array $grants - What the connection is allowed to do.
     * @return array
     */
    public static function offered(array $grants)
    {
        $offered = [];
        foreach (array_merge(self::written($grants), self::abilities($grants)) as $tool) {
            $name = $tool['definition']['name'];
            if (!isset($offered[$name])) {
                $offered[$name] = $tool;
            }
        }

        return $offered;
    }

    /**
     * @param array $offered - What the connection is offered, from offered().
     * @return array - What list_tools lists and run_tool reaches, by the name list_tools gives each.
     */
    public static function catalog(array $offered)
    {
        $catalog = [];
        foreach (array_diff_key($offered, array_flip(self::DISCOVERY)) as $name => $tool) {
            $catalog[$tool['ability'] ?? $name] = $tool;
        }

        return $catalog;
    }

    /**
     * Clients that cached an ability as a tool call it by the tool's name.
     *
     * @param array  $catalog - What the connection can reach now, from catalog().
     * @param string $asked   - The name a model asked for.
     * @return string|null - The name list_tools gives it, or null when nothing by that name is offered now.
     */
    public static function find(array $catalog, $asked)
    {
        if (isset($catalog[$asked])) {
            return $asked;
        }

        foreach ($catalog as $name => $tool) {
            if ($tool['definition']['name'] === $asked) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @param array $runner    - The run tool that was called.
     * @param array $arguments - What it was called with.
     * @param array $catalog   - What the connection can reach now, from catalog().
     * @return array - The tool to run, and the arguments to run it with.
     * @throws Refused - When the call names nothing this run tool may run.
     */
    private static function target(array $runner, array $arguments, array $catalog)
    {
        $arguments = self::valid($runner['inputSchema'], $arguments);
        if (\is_wp_error($arguments)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- the reason is a log code, never printed.
            throw new Refused(\esc_html($arguments->get_error_message()), (string) $arguments->get_error_code());
        }

        $name = self::find($catalog, $arguments['name']);
        if ($name === null) {
            throw new Refused(\esc_html(sprintf(
                'Unknown tool: %s. Call list_tools for the tools this connection can use now.',
                $arguments['name']
            )), 'extendify_mcp_unknown_tool');
        }

        $tool = $catalog[$name];
        if ($tool['mode'] !== $runner['runs']) {
            throw new Refused(\esc_html(sprintf(
                $tool['mode'] === Grants::WRITE
                    ? '%s changes the site, so run it with run_tool_write.'
                    : '%s changes nothing on the site, so run it with run_tool.',
                $name
            )), 'extendify_mcp_wrong_run_tool');
        }

        // The client called a run tool, which names no output schema.
        unset($tool['definition']['outputSchema']);

        return [$tool, (array) ($arguments['arguments'] ?? [])];
    }

    /**
     * @param array $grants - What the connection is allowed to do.
     * @return array
     */
    private static function written(array $grants)
    {
        $tools = [];
        foreach (Tools::all() as $name => $tool) {
            if (!in_array($tool['mode'], $grants, true)) {
                continue;
            }

            if (!Allowed::forTool($name, $tool['mode'])) {
                continue;
            }

            // Clients treat a tool without readOnlyHint as a write and ask before every call.
            $definition = [
                'name' => $name,
                'description' => $tool['description'],
                'inputSchema' => self::published($tool['inputSchema']),
                'annotations' => ['readOnlyHint' => $tool['mode'] === Grants::READ] + ($tool['annotations'] ?? []),
            ];

            $tools[] = [
                'mode' => $tool['mode'],
                'handler' => $tool['handler'] ?? null,
                'runs' => $tool['runs'] ?? null,
                'inputSchema' => $tool['inputSchema'],
                'definition' => $definition,
            ];
        }

        return $tools;
    }

    /**
     * Clients keep the tool list from connect time, so a type a plugin adds later must stay callable.
     *
     * @param array $schema - A tool's input schema, or one of its properties.
     * @return array - The schema without the choices that change with the site.
     */
    private static function published(array $schema)
    {
        if (!empty($schema['live'])) {
            unset($schema['enum'], $schema['live']);
        }

        if (isset($schema['items'])) {
            $schema['items'] = self::published($schema['items']);
        }

        foreach ($schema['properties'] ?? [] as $key => $property) {
            $schema['properties'][$key] = self::published($property);
        }

        return $schema;
    }

    /**
     * @param array $tool      - The tool being called.
     * @param array $arguments - The arguments the client called with.
     * @param array $grants    - What the connection is allowed to do.
     * @return array|\WP_Error
     */
    private static function handled(array $tool, array $arguments, array $grants)
    {
        $arguments = self::valid($tool['inputSchema'], $arguments);
        if (\is_wp_error($arguments)) {
            return $arguments;
        }

        $answered = call_user_func([Handlers::class, $tool['handler']], $arguments, $grants);

        return \is_wp_error($answered) ? $answered : self::json($answered);
    }

    /**
     * A model that reads why its arguments were turned down can correct itself.
     *
     * @param array $schema    - The tool's input schema.
     * @param array $arguments - The arguments the client called with.
     * @return array|\WP_Error
     */
    private static function valid(array $schema, array $arguments)
    {
        // A client needs {} for a tool taking nothing, and the validator fatals indexing a stdClass.
        $schema['properties'] = (array) ($schema['properties'] ?? []);
        foreach ($schema['properties'] as $key => $property) {
            if (!isset($arguments[$key]) && array_key_exists('default', (array) $property)) {
                $arguments[$key] = $property['default'];
            }
        }

        $checked = \rest_validate_value_from_schema($arguments, $schema, 'arguments');
        if (\is_wp_error($checked)) {
            return $checked;
        }

        return (array) \rest_sanitize_value_from_schema($arguments, $schema, 'arguments');
    }

    /**
     * @param string $message - What went wrong, for the model to read.
     * @return array
     */
    private static function problem($message)
    {
        return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
    }

    /**
     * @param array $grants - What the connection is allowed to do.
     * @return array
     */
    private static function abilities(array $grants)
    {
        $tools = [];
        foreach (self::reachable() as $reached) {
            if (in_array($reached['mode'], $grants, true)) {
                $tools[] = self::ability($reached['ability'], $reached['annotations'], $reached['mode']);
            }
        }

        return $tools;
    }

    /**
     * @return array - Each ability some connection may reach, with its annotations and the grant it needs.
     */
    public static function reachable()
    {
        if (!function_exists('wp_get_abilities')) {
            return [];
        }

        $reachable = [];
        foreach (\wp_get_abilities() as $ability) {
            if (!$ability->get_meta_item('show_in_rest')) {
                continue;
            }

            $annotations = (array) $ability->get_meta_item('annotations');
            $mode = Allowed::forAbility($ability->get_name(), $annotations);
            if ($mode !== null) {
                $reachable[] = ['ability' => $ability, 'annotations' => $annotations, 'mode' => $mode];
            }
        }

        return $reachable;
    }

    /**
     * The run controller insists on the method an ability's annotations imply.
     *
     * @param \WP_Ability $ability     - The registered ability.
     * @param array       $annotations - Its meta annotations.
     * @param string      $mode        - The grant it needs.
     * @return array
     */
    private static function ability($ability, array $annotations, $mode)
    {
        $method = 'POST';
        if (!empty($annotations['readonly'])) {
            $method = 'GET';
        } elseif (!empty($annotations['destructive']) && !empty($annotations['idempotent'])) {
            $method = 'DELETE';
        }

        // An ability may branch its whole input with oneOf rather than name properties.
        $schema = self::normalize($ability->get_input_schema());
        $schema['type'] = 'object';

        $definition = [
            'name' => self::name($ability->get_name()),
            'description' => $ability->get_description(),
            'inputSchema' => $schema,
        ];
        $output = self::normalize($ability->get_output_schema());
        if (($output['type'] ?? '') === 'object') {
            $definition['outputSchema'] = $output;
        }

        return [
            'mode' => $mode,
            'method' => $method,
            'ability' => $ability->get_name(),
            'definition' => $definition,
        ];
    }

    /**
     * The run controller reads input from the query except on a POST.
     *
     * @param array $tool      - The tool being called.
     * @param array $arguments - The arguments the client called with.
     * @return \WP_REST_Request
     */
    private static function abilityRequest(array $tool, array $arguments)
    {
        $request = new \WP_REST_Request($tool['method'], '/wp-abilities/v1/abilities/' . $tool['ability'] . '/run');
        if ($tool['method'] !== 'POST') {
            $request->set_query_params(['input' => $arguments]);

            return $request;
        }

        $request->set_header('Content-Type', 'application/json');
        $request->set_body(\wp_json_encode(['input' => (object) $arguments]));

        return $request;
    }

    /**
     * @param \WP_REST_Response $response - What the REST server answered.
     * @param array             $tool     - The tool that was called.
     * @return array|\WP_Error - An error carries the whole response body as its message.
     */
    private static function answer($response, array $tool)
    {
        $data = $response->get_data();
        $answer = self::json($data);
        if ($response->is_error()) {
            $code = (string) (((array) $data)['code'] ?? '') ?: 'extendify_mcp_ability_error';

            return new \WP_Error($code, $answer['content'][0]['text']);
        }

        // A tool that named an output schema owes the client a result shaped like it.
        if (isset($tool['definition']['outputSchema']) && (is_array($data) || is_object($data))) {
            $answer['structuredContent'] = (object) $data;
        }

        return $answer;
    }

    /**
     * @param mixed $data - What the tool answered with.
     * @return array
     */
    private static function json($data)
    {
        $text = \wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }

    /**
     * @param mixed $schema - A schema as a plugin registered it.
     * @return array
     */
    private static function normalize($schema)
    {
        if (!is_array($schema)) {
            return [];
        }

        $kept = array_intersect_key($schema, array_flip(self::SCHEMA_KEYWORDS));
        if (isset($kept['required']) && !is_array($kept['required'])) {
            unset($kept['required']);
        }

        if (isset($kept['properties']) && is_array($kept['properties'])) {
            $kept = array_merge($kept, self::describe($kept));
        }

        if (isset($kept['items'])) {
            $kept['items'] = self::normalize($kept['items']);
        }

        foreach (['oneOf', 'anyOf'] as $branch) {
            if (isset($kept[$branch]) && is_array($kept[$branch])) {
                $kept[$branch] = array_map([self::class, 'normalize'], $kept[$branch]);
            }
        }

        return $kept;
    }

    /**
     * A plugin may spell required on the property, as a REST arg does; a client wants it on the object.
     *
     * @param array $schema - A schema whose properties need normalizing.
     * @return array
     */
    private static function describe(array $schema)
    {
        $properties = [];
        $required = isset($schema['required']) ? $schema['required'] : [];
        foreach ($schema['properties'] as $name => $property) {
            if (!is_array($property)) {
                continue;
            }

            if (!empty($property['required'])) {
                $required[] = (string) $name;
            }

            $properties[$name] = self::normalize($property);
        }

        $described = ['properties' => (object) $properties];
        if ($required) {
            $described['required'] = array_values(array_unique($required));
        }

        return $described;
    }

    /**
     * @return array - The name a client calls each registered ability by.
     */
    public static function abilityNames()
    {
        if (!function_exists('wp_get_abilities')) {
            return [];
        }

        return array_values(array_map(function ($ability) {
            return self::name($ability->get_name());
        }, \wp_get_abilities()));
    }

    /**
     * @param string $subject - The ability name.
     * @return string
     */
    public static function name($subject)
    {
        $name = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $subject), '_'));
        if (strlen($name) <= self::MAX_NAME) {
            return $name;
        }

        return substr($name, 0, self::MAX_NAME - 9) . '_' . substr(md5($name), 0, 8);
    }
}
