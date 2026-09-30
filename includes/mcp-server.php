<?php
/**
 * Inova Manager MCP (Model Context Protocol) Server
 * 
 * Provides an MCP Server endpoint directly inside WordPress, exposing existing
 * Inova Manager REST APIs as standardized MCP Tools for AI Agents (Claude Desktop,
 * Cursor, Antigravity, Cline, etc.).
 * 
 * Endpoints:
 * - POST /wp-json/inova-mcp/v1/rpc      (JSON-RPC 2.0 direct streamable HTTP)
 * - GET  /wp-json/inova-mcp/v1/sse      (SSE connection endpoint)
 * - POST /wp-json/inova-mcp/v1/messages (SSE message receiver)
 * - GET  /wp-json/inova-mcp/v1/tools    (Tool inspection helper)
 * 
 * @package InovaManager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Inova_MCP_Server
{
    const PROTOCOL_VERSION = '2024-11-05';
    const SERVER_NAME = 'inova-manager-mcp';
    const SERVER_VERSION = '1.0.0';

    /**
     * Initialize the MCP Server hooks
     */
    public static function init()
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes for MCP
     */
    public static function register_routes()
    {
        // 1. Universal JSON-RPC 2.0 Endpoint (Streamable HTTP / POST)
        register_rest_route('inova-mcp/v1', '/rpc', [
            'methods' => ['POST', 'OPTIONS'],
            'callback' => [__CLASS__, 'handle_rpc_request'],
            'permission_callback' => [__CLASS__, 'check_permission']
        ]);

        // 2. Server-Sent Events (SSE) Endpoint
        register_rest_route('inova-mcp/v1', '/sse', [
            'methods' => ['GET', 'OPTIONS'],
            'callback' => [__CLASS__, 'handle_sse_connect'],
            'permission_callback' => [__CLASS__, 'check_permission']
        ]);

        // 3. SSE Messages Receiver
        register_rest_route('inova-mcp/v1', '/messages', [
            'methods' => ['POST', 'OPTIONS'],
            'callback' => [__CLASS__, 'handle_sse_message'],
            'permission_callback' => [__CLASS__, 'check_permission']
        ]);

        // 4. Convenience endpoint to view available tools list
        register_rest_route('inova-mcp/v1', '/tools', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_get_tools_info'],
            'permission_callback' => [__CLASS__, 'check_permission']
        ]);
    }

    /**
     * Validate MCP Authentication
     * Supports:
     * - Header: X-API-KEY
     * - Header: Authorization: Bearer <key>
     * - Query param: api_key
     */
    public static function check_permission($request)
    {
        // Handle preflight OPTIONS request
        if ($request->get_method() === 'OPTIONS') {
            return true;
        }

        $api_key = '';

        // 1. Check X-API-KEY header
        $header_key = $request->get_header('X-API-KEY');
        if (!empty($header_key)) {
            $api_key = trim($header_key);
        }

        // 2. Check Authorization: Bearer <key>
        if (empty($api_key)) {
            $auth_header = $request->get_header('Authorization');
            if (!empty($auth_header) && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
                $api_key = trim($matches[1]);
            }
        }

        // 3. Check query param ?api_key=...
        if (empty($api_key)) {
            $param_key = $request->get_param('api_key');
            if (!empty($param_key)) {
                $api_key = trim($param_key);
            }
        }

        if (empty($api_key)) {
            return new WP_Error(
                'mcp_unauthorized',
                'Authentication required. Provide X-API-KEY header or Bearer token.',
                ['status' => 401]
            );
        }

        $valid_api_key = get_option('bookorder_api_key', 'your-secure-api-key-here');

        if ($api_key !== $valid_api_key) {
            return new WP_Error(
                'mcp_forbidden',
                'Invalid API key provided.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Handle Direct JSON-RPC Request (POST /wp-json/inova-mcp/v1/rpc)
     */
    public static function handle_rpc_request($request)
    {
        $raw_body = $request->get_body();
        $payload = json_decode($raw_body, true);

        if (empty($payload) || !is_array($payload)) {
            return new WP_REST_Response([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error: Invalid JSON payload'
                ]
            ], 400);
        }

        // Support batch JSON-RPC requests
        if (isset($payload[0]) && is_array($payload[0])) {
            $responses = [];
            foreach ($payload as $item) {
                $responses[] = self::handle_jsonrpc($item);
            }
            return new WP_REST_Response($responses, 200);
        }

        $response = self::handle_jsonrpc($payload);
        return new WP_REST_Response($response, 200);
    }

    /**
     * Handle SSE Connection (GET /wp-json/inova-mcp/v1/sse)
     */
    public static function handle_sse_connect($request)
    {
        // Generate or retrieve session ID
        $session_id = $request->get_param('sessionId') ?: wp_generate_uuid4();
        $api_key = $request->get_param('api_key') ?: $request->get_header('X-API-KEY');

        // Prevent buffering
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        ini_set('zlib.output_compression', '0');
        ini_set('implicit_flush', '1');

        header('Content-Type: text/event-stream; charset=UTF-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Build endpoint URL for client to POST messages to
        $messages_url = rest_url('inova-mcp/v1/messages?sessionId=' . urlencode($session_id));
        if (!empty($api_key)) {
            $messages_url .= '&api_key=' . urlencode($api_key);
        }

        // Send endpoint event according to MCP SSE specification
        echo "event: endpoint\n";
        echo "data: " . $messages_url . "\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();

        // Listen for queued messages in transient for up to 55 seconds (or until aborted)
        $start_time = time();
        $timeout = 55;

        while ((time() - $start_time) < $timeout && !connection_aborted()) {
            $transient_key = 'inova_mcp_sse_' . $session_id;
            $queued = get_transient($transient_key);

            if (!empty($queued) && is_array($queued)) {
                delete_transient($transient_key);
                foreach ($queued as $msg) {
                    echo "event: message\n";
                    echo "data: " . json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
                }
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            // Keep connection alive with heartbeat comment every 15s
            if ((time() - $start_time) % 15 === 0) {
                echo ": keepalive\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }

            usleep(250000); // sleep 250ms
        }

        exit;
    }

    /**
     * Handle SSE Messages Receiver (POST /wp-json/inova-mcp/v1/messages)
     */
    public static function handle_sse_message($request)
    {
        $session_id = $request->get_param('sessionId');
        $raw_body = $request->get_body();
        $payload = json_decode($raw_body, true);

        if (empty($payload)) {
            return new WP_REST_Response([
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => ['code' => -32700, 'message' => 'Invalid JSON']
            ], 400);
        }

        $result = self::handle_jsonrpc($payload);

        // If session_id is provided, queue into transient for SSE delivery
        if (!empty($session_id)) {
            $transient_key = 'inova_mcp_sse_' . $session_id;
            $queue = get_transient($transient_key) ?: [];
            $queue[] = $result;
            set_transient($transient_key, $queue, 60);

            // Also return 202 Accepted or direct result for dual compatibility
            return new WP_REST_Response([
                'status' => 'accepted',
                'result' => $result
            ], 202);
        }

        return new WP_REST_Response($result, 200);
    }

    /**
     * Helper endpoint to inspect tools as JSON
     */
    public static function handle_get_tools_info($request)
    {
        return new WP_REST_Response([
            'server' => self::SERVER_NAME,
            'version' => self::SERVER_VERSION,
            'protocol' => self::PROTOCOL_VERSION,
            'total_tools' => count(self::get_tools_definitions()),
            'tools' => self::get_tools_definitions()
        ], 200);
    }

    /**
     * Core JSON-RPC 2.0 Dispatcher
     * 
     * Handles methods:
     * - initialize
     * - notifications/initialized
     * - ping
     * - tools/list
     * - tools/call
     * - resources/list
     * - prompts/list
     */
    public static function handle_jsonrpc($request)
    {
        $id = isset($request['id']) ? $request['id'] : null;
        $method = isset($request['method']) ? $request['method'] : '';
        $params = isset($request['params']) && is_array($request['params']) ? $request['params'] : [];

        switch ($method) {
            case 'initialize':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'protocolVersion' => self::PROTOCOL_VERSION,
                        'capabilities' => [
                            'tools' => [
                                'listChanged' => false
                            ]
                        ],
                        'serverInfo' => [
                            'name' => self::SERVER_NAME,
                            'version' => self::SERVER_VERSION
                        ]
                    ]
                ];

            case 'notifications/initialized':
                // Client initialized notification - no response required in standard JSON-RPC notification,
                // but if an id was provided, return empty result
                if ($id !== null) {
                    return [
                        'jsonrpc' => '2.0',
                        'id' => $id,
                        'result' => new stdClass()
                    ];
                }
                return null;

            case 'ping':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => new stdClass()
                ];

            case 'tools/list':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'tools' => self::get_tools_definitions()
                    ]
                ];

            case 'tools/call':
                $tool_name = isset($params['name']) ? $params['name'] : '';
                $arguments = isset($params['arguments']) && is_array($params['arguments']) ? $params['arguments'] : [];

                $call_result = self::execute_tool($tool_name, $arguments);

                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => $call_result
                ];

            case 'resources/list':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'resources' => []
                    ]
                ];

            case 'prompts/list':
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'prompts' => []
                    ]
                ];

            default:
                return [
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'error' => [
                        'code' => -32601,
                        'message' => "Method not found: {$method}"
                    ]
                ];
        }
    }

    /**
     * Dispatch and execute a tool via internal REST API call
     */
    public static function execute_tool($tool_name, $arguments)
    {
        $tools = self::get_tools_registry();

        if (!isset($tools[$tool_name])) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: Unknown tool '{$tool_name}'."
                    ]
                ],
                'isError' => true
            ];
        }

        $config = $tools[$tool_name];
        $method = $config['method'];
        $path = $config['path'];

        // Replace path placeholders like {id} if any
        if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches)) {
            foreach ($matches[1] as $placeholder) {
                if (isset($arguments[$placeholder])) {
                    $path = str_replace('{' . $placeholder . '}', $arguments[$placeholder], $path);
                    unset($arguments[$placeholder]);
                }
            }
        }

        // Execute internal WordPress REST Request in-memory
        $exec_result = self::dispatch_internal_rest_request($method, $path, $arguments);

        $is_error = $exec_result['status'] >= 400;
        $formatted_output = is_string($exec_result['data'])
            ? $exec_result['data']
            : json_encode($exec_result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $formatted_output
                ]
            ],
            'isError' => $is_error
        ];
    }

    /**
     * Execute WordPress REST Route in-memory using rest_do_request()
     */
    public static function dispatch_internal_rest_request($method, $path, $params = [])
    {
        $request = new WP_REST_Request($method, $path);

        // Attach system API key to header so existing validate_api_key in api.php passes
        $valid_api_key = get_option('bookorder_api_key', 'your-secure-api-key-here');
        $request->set_header('X-API-KEY', $valid_api_key);
        $request->set_header('Content-Type', 'application/json');

        if (strtoupper($method) === 'GET') {
            $request->set_query_params($params);
        } else {
            $request->set_body_params($params);
            // Also set JSON body for endpoints reading raw body
            $request->set_body(json_encode($params));
        }

        $response = rest_do_request($request);
        $server = rest_get_server();
        $data = $server ? $server->response_to_data($response, false) : $response->get_data();
        $status = $response->get_status();

        return [
            'status' => $status,
            'data' => $data
        ];
    }

    /**
     * Registry of all MCP Tools with route mapping and JSON Schema
     */
    public static function get_tools_registry()
    {
        return [
            'inova_search_partners' => [
                'method' => 'GET',
                'path' => '/bookorder/v1/search-partners',
                'description' => 'Search for partners, customers, or suppliers in Inova Manager by keyword, type, or status.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Search keyword for name, email, or user_code.'
                        ],
                        'user_type' => [
                            'type' => 'string',
                            'enum' => ['INDIVIDUAL', 'BUSINESS', 'PARTNER', 'SUPPLIER'],
                            'description' => 'Filter by customer type.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['ACTIVE', 'INACTIVE', 'DELETED'],
                            'description' => 'Filter by status (default ACTIVE).'
                        ],
                        'page' => [
                            'type' => 'integer',
                            'description' => 'Page number (default 1).'
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'description' => 'Number of results per page (default 20).'
                        ],
                        'include_contacts' => [
                            'type' => 'boolean',
                            'description' => 'Whether to include contacts for business customers.'
                        ]
                    ]
                ]
            ],

            'inova_import_partner' => [
                'method' => 'POST',
                'path' => '/bookorder/v1/import-partner',
                'description' => 'Create or import a new partner/customer into Inova Manager.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'user_code' => [
                            'type' => 'string',
                            'description' => 'Unique code for customer (e.g. KH001, DT-INOVA).'
                        ],
                        'user_type' => [
                            'type' => 'string',
                            'enum' => ['INDIVIDUAL', 'BUSINESS', 'PARTNER', 'SUPPLIER'],
                            'description' => 'Customer classification.'
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Full name or company name.'
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => 'Primary email address.'
                        ],
                        'phone_number' => [
                            'type' => 'string',
                            'description' => 'Primary phone number.'
                        ],
                        'tax_code' => [
                            'type' => 'string',
                            'description' => 'Tax identification code.'
                        ],
                        'address' => [
                            'type' => 'string',
                            'description' => 'Physical or billing address.'
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Internal notes regarding this partner.'
                        ]
                    ],
                    'required' => ['user_code', 'user_type', 'name']
                ]
            ],

            'inova_update_partner' => [
                'method' => 'PUT',
                'path' => '/bookorder/v1/update-partner/{id}',
                'description' => 'Update an existing partner or customer by their ID.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'integer',
                            'description' => 'ID of the partner to update.'
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Updated name.'
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => 'Updated email.'
                        ],
                        'phone_number' => [
                            'type' => 'string',
                            'description' => 'Updated phone.'
                        ],
                        'tax_code' => [
                            'type' => 'string',
                            'description' => 'Updated tax code.'
                        ],
                        'address' => [
                            'type' => 'string',
                            'description' => 'Updated address.'
                        ],
                        'zalo_thread_id' => [
                            'type' => 'string',
                            'description' => 'Zalo group/thread ID associated with this partner.'
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Updated notes.'
                        ]
                    ],
                    'required' => ['id']
                ]
            ],

            'inova_add_contact' => [
                'method' => 'POST',
                'path' => '/bookorder/v1/add-contact',
                'description' => 'Add a contact person for a business customer.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'owner_user_id' => [
                            'type' => 'integer',
                            'description' => 'ID of the business customer.'
                        ],
                        'full_name' => [
                            'type' => 'string',
                            'description' => 'Full name of the contact person.'
                        ],
                        'email' => [
                            'type' => 'string',
                            'description' => 'Email of the contact person.'
                        ],
                        'phone_number' => [
                            'type' => 'string',
                            'description' => 'Phone number of the contact person.'
                        ],
                        'position' => [
                            'type' => 'string',
                            'description' => 'Job title/position (e.g. Director, Accountant).'
                        ],
                        'is_primary' => [
                            'type' => 'boolean',
                            'description' => 'Set as primary contact.'
                        ]
                    ],
                    'required' => ['owner_user_id', 'full_name']
                ]
            ],

            'inova_get_services_expiring' => [
                'method' => 'GET',
                'path' => '/bookorder/v1/services-expiring',
                'description' => 'Get a list of services (domains, hosting, maintenance) expiring soon or already expired.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'days' => [
                            'type' => 'integer',
                            'description' => 'Day offset (+3: in next 3 days, -30: expired within last 30 days, default 30).'
                        ],
                        'service_type' => [
                            'type' => 'string',
                            'enum' => ['domain', 'hosting', 'maintenance'],
                            'description' => 'Filter by service type.'
                        ],
                        'owner_user_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by owner user ID.'
                        ],
                        'page' => [
                            'type' => 'integer',
                            'description' => 'Page number.'
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'description' => 'Results per page (default 20).'
                        ]
                    ]
                ]
            ],

            'inova_get_pending_invoices' => [
                'method' => 'GET',
                'path' => '/bookorder/v1/invoices-pending',
                'description' => 'Get pending or overdue invoices with detailed items, total amounts, and overdue days.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'owner_user_id' => [
                            'type' => 'string',
                            'description' => 'Filter by owner user ID (single ID "5", CSV "1,2,3", or JSON "[1,2]").'
                        ],
                        'overdue_days' => [
                            'type' => 'integer',
                            'description' => 'Filter invoices overdue by at least this number of days.'
                        ],
                        'page' => [
                            'type' => 'integer',
                            'description' => 'Page number.'
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'description' => 'Items per page.'
                        ]
                    ]
                ]
            ],

            'inova_update_invoice_status' => [
                'method' => 'POST',
                'path' => '/bookorder/v1/update-invoice-status',
                'description' => 'Update the status of an invoice (e.g. mark as paid, pending, canceled). When marking paid, payment date and amount auto-fill if not supplied.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'invoice_id' => [
                            'type' => 'integer',
                            'description' => 'Invoice ID to update.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['draft', 'pending', 'pending_completion', 'paid', 'canceled'],
                            'description' => 'New invoice status.'
                        ],
                        'payment_date' => [
                            'type' => 'string',
                            'description' => 'Payment date in YYYY-MM-DD HH:MM:SS format.'
                        ],
                        'paid_amount' => [
                            'type' => 'number',
                            'description' => 'Amount paid (defaults to total amount if paid).'
                        ],
                        'payment_method' => [
                            'type' => 'string',
                            'description' => 'Payment method (e.g. chuyển khoản ngân hàng).'
                        ]
                    ],
                    'required' => ['invoice_id', 'status']
                ]
            ],

            'inova_create_bulk_invoice' => [
                'method' => 'POST',
                'path' => '/inova/v1/bulk-invoice',
                'description' => 'Create a bulk invoice containing multiple renewal or new services for a customer.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'owner_user_id' => [
                            'type' => 'integer',
                            'description' => 'Customer user ID.'
                        ],
                        'services' => [
                            'type' => 'array',
                            'description' => 'List of services to include in the invoice.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'service_type' => ['type' => 'string', 'enum' => ['domain', 'hosting', 'maintenance']],
                                    'service_id' => ['type' => 'integer'],
                                    'quantity' => ['type' => 'integer'],
                                    'unit_price' => ['type' => 'number'],
                                    'description' => ['type' => 'string']
                                ],
                                'required' => ['service_type', 'service_id']
                            ]
                        ],
                        'invoice_date' => [
                            'type' => 'string',
                            'description' => 'Invoice date (YYYY-MM-DD).'
                        ],
                        'due_date' => [
                            'type' => 'string',
                            'description' => 'Due date (YYYY-MM-DD).'
                        ],
                        'notes' => [
                            'type' => 'string',
                            'description' => 'Invoice notes.'
                        ]
                    ],
                    'required' => ['owner_user_id', 'services']
                ]
            ],

            'inova_get_domain_info' => [
                'method' => 'GET',
                'path' => '/bookorder/v1/domain-management-info',
                'description' => 'Get domain management credentials and registrar URL (password is automatically decrypted).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'owner_user_id' => [
                            'type' => 'string',
                            'description' => 'Owner user ID for permission check.'
                        ],
                        'domain_id' => [
                            'type' => 'integer',
                            'description' => 'Domain ID.'
                        ],
                        'domain_name' => [
                            'type' => 'string',
                            'description' => 'Domain name (e.g. example.com).'
                        ]
                    ],
                    'required' => ['owner_user_id']
                ]
            ],

            'inova_get_website_info' => [
                'method' => 'GET',
                'path' => '/bookorder/v1/website-management-info',
                'description' => 'Get website admin URL, username, and decrypted management password.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'owner_user_id' => [
                            'type' => 'string',
                            'description' => 'Owner user ID for permission check.'
                        ],
                        'website_id' => [
                            'type' => 'integer',
                            'description' => 'Website ID.'
                        ],
                        'website_name' => [
                            'type' => 'string',
                            'description' => 'Website name or domain.'
                        ]
                    ],
                    'required' => ['owner_user_id']
                ]
            ],

            'inova_update_domain_info' => [
                'method' => 'POST',
                'path' => '/bookorder/v1/update-domain-management-info',
                'description' => 'Update domain management registrar URL, username, and password.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'owner_user_id' => [
                            'type' => 'string',
                            'description' => 'Owner user ID.'
                        ],
                        'domain_id' => [
                            'type' => 'integer',
                            'description' => 'Domain ID.'
                        ],
                        'domain_name' => [
                            'type' => 'string',
                            'description' => 'Domain name.'
                        ],
                        'management_url' => [
                            'type' => 'string',
                            'description' => 'New registrar URL.'
                        ],
                        'management_username' => [
                            'type' => 'string',
                            'description' => 'New username.'
                        ],
                        'management_password' => [
                            'type' => 'string',
                            'description' => 'New password (will be automatically encrypted).'
                        ]
                    ],
                    'required' => ['owner_user_id']
                ]
            ],

            'inova_update_website_info' => [
                'method' => 'POST',
                'path' => '/bookorder/v1/update-website-management-info',
                'description' => 'Update website admin URL, username, and password.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'owner_user_id' => [
                            'type' => 'string',
                            'description' => 'Owner user ID.'
                        ],
                        'website_id' => [
                            'type' => 'integer',
                            'description' => 'Website ID.'
                        ],
                        'website_name' => [
                            'type' => 'string',
                            'description' => 'Website name.'
                        ],
                        'admin_url' => [
                            'type' => 'string',
                            'description' => 'New WordPress admin URL.'
                        ],
                        'admin_username' => [
                            'type' => 'string',
                            'description' => 'New admin username.'
                        ],
                        'admin_password' => [
                            'type' => 'string',
                            'description' => 'New admin password (will be automatically encrypted).'
                        ]
                    ],
                    'required' => ['owner_user_id']
                ]
            ],

            'inova_check_website_status' => [
                'method' => 'POST',
                'path' => '/bookorder/v1/check-status',
                'description' => 'Ping and verify real-time online status of a satellite website and update database.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'website' => [
                            'type' => 'string',
                            'description' => 'Domain or website name to check (e.g. example.com).'
                        ]
                    ],
                    'required' => ['website']
                ]
            ],

            'inova_partner_managed_users' => [
                'method' => 'GET',
                'path' => '/bookorder/v1/partner-managed-users',
                'description' => 'Get the list of customer user IDs managed by a partner agency.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'partner_id' => [
                            'type' => 'integer',
                            'description' => 'Partner user ID.'
                        ]
                    ],
                    'required' => ['partner_id']
                ]
            ],

            'inova_get_websites_expiry_info' => [
                'method' => 'POST',
                'path' => '/bookorder/v1/websites-expiry-info',
                'description' => 'Get a structured markdown summary of expiration dates for all services of a website.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'website_id' => [
                            'type' => 'integer',
                            'description' => 'Website ID.'
                        ],
                        'domain' => [
                            'type' => 'string',
                            'description' => 'Domain name.'
                        ]
                    ]
                ]
            ],

            'inova_list_managed_websites' => [
                'method' => 'GET',
                'path' => '/bookorder/v1/managed-websites',
                'description' => 'List all websites managed in Inova Manager with status and owner information.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'owner_user_id' => [
                            'type' => 'integer',
                            'description' => 'Filter by owner user ID.'
                        ],
                        'status' => [
                            'type' => 'string',
                            'description' => 'Filter by website status.'
                        ],
                        'page' => [
                            'type' => 'integer',
                            'description' => 'Page number.'
                        ],
                        'per_page' => [
                            'type' => 'integer',
                            'description' => 'Results per page.'
                        ]
                    ]
                ]
            ],

            'inova_check_domain_whois' => [
                'method' => 'POST',
                'path' => '/bookorder/v1/check-domain-whois',
                'description' => 'Query domain WHOIS data and automatically synchronize expiry date in Inova Manager.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'domain' => [
                            'type' => 'string',
                            'description' => 'Domain name to check (e.g. inovasolutions.vn).'
                        ]
                    ],
                    'required' => ['domain']
                ]
            ]
        ];
    }

    /**
     * Get tools definitions array formatted for MCP protocol (tools/list)
     */
    public static function get_tools_definitions()
    {
        $registry = self::get_tools_registry();
        $definitions = [];

        foreach ($registry as $tool_name => $config) {
            $definitions[] = [
                'name' => $tool_name,
                'description' => $config['description'],
                'inputSchema' => $config['inputSchema']
            ];
        }

        return $definitions;
    }
}

// Boot server
Inova_MCP_Server::init();
