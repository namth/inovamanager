#!/usr/bin/env php
<?php
/**
 * Inova Manager MCP Stdio Runner
 * 
 * Enables local AI agents (Claude Desktop, Cursor, etc.) to communicate
 * with Inova Manager via standard input/output (stdio) transport.
 * 
 * Usage in MCP config (e.g. claude_desktop_config.json):
 * {
 *   "mcpServers": {
 *     "inova-manager": {
 *       "command": "php",
 *       "args": ["/Users/namtran/Local Sites/inovamanager/app/public/wp-content/themes/inovamanager/mcp-stdio.php"]
 *     }
 *   }
 * }
 */

// Disable all error output to stdout to avoid corrupting JSON-RPC communication
error_reporting(0);
ini_set('display_errors', '0');

// Auto-detect Local by Flywheel MySQL socket if running on macOS Local environment
$home = getenv('HOME') ?: '/Users/' . get_current_user();
$local_socket = $home . '/Library/Application Support/Local/run/zAGtuumM8/mysql/mysqld.sock';
if (file_exists($local_socket)) {
    ini_set('mysqli.default_socket', $local_socket);
    ini_set('pdo_mysql.default_socket', $local_socket);
}

// Path to wp-load.php (3 levels up from theme directory)
$wp_load = dirname(dirname(dirname(__DIR__))) . '/wp-load.php';

if (!file_exists($wp_load)) {
    fwrite(STDERR, "Error: Could not locate wp-load.php at: {$wp_load}\n");
    exit(1);
}

// Catch any output from wp-load.php (like db connection errors)
ob_start();
define('WP_USE_THEMES', false);
try {
    require_once $wp_load;
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, "WordPress Bootstrap Error: " . $e->getMessage() . "\n");
    exit(1);
}
$init_output = ob_get_clean();

// Check if database error page was triggered
if (stripos($init_output, 'Error establishing a database connection') !== false) {
    fwrite(STDERR, "Notice: Local site is currently stopped in Local by Flywheel. Start the 'inovamanager' site in Local to enable MCP.\n");
    exit(1);
}

// Verify MCP Server class is loaded
if (!class_exists('Inova_MCP_Server')) {
    $server_file = __DIR__ . '/includes/mcp-server.php';
    if (file_exists($server_file)) {
        require_once $server_file;
    } else {
        fwrite(STDERR, "Error: Inova_MCP_Server class not found.\n");
        exit(1);
    }
}

// Loop to read JSON-RPC lines from STDIN
while ($line = fgets(STDIN)) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $request = json_decode($line, true);
    if (!is_array($request)) {
        $error_response = [
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => [
                'code' => -32700,
                'message' => 'Parse error: Invalid JSON'
            ]
        ];
        echo json_encode($error_response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        flush();
        continue;
    }

    $response = Inova_MCP_Server::handle_jsonrpc($request);

    // Notifications return null and do not require a response
    if ($response !== null) {
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        flush();
    }
}
