<?php

declare(strict_types=1);

final class LSE_Headless_AI_Plugin
{
    private LSE_Headless_AI_Api_Client $client;

    /**
     * @var list<array{type:string,message:string}>
     */
    private array $notices = [];

    public function __construct(LSE_Headless_AI_Api_Client $client)
    {
        $this->client = $client;
    }

    public function init(): void
    {
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_notices', [$this, 'renderNotices']);
    }

    public function registerAdminMenu(): void
    {
        $hook = add_menu_page(
            'Project Aurora',
            'Headless AI',
            'manage_options',
            'lse-headless-ai',
            [$this, 'renderBlueprintsPage'],
            'dashicons-chart-network',
            58
        );

        add_submenu_page('lse-headless-ai', 'Blueprints', 'Blueprints', 'manage_options', 'lse-headless-ai', [$this, 'renderBlueprintsPage']);
        add_submenu_page('lse-headless-ai', 'Strategy', 'Strategy', 'manage_options', 'lse-headless-ai-strategy', [$this, 'renderStrategyPage']);
        add_submenu_page('lse-headless-ai', 'Analytics', 'Analytics', 'manage_options', 'lse-headless-ai-analytics', [$this, 'renderAnalyticsPage']);
        add_submenu_page('lse-headless-ai', 'Billing', 'Billing', 'manage_options', 'lse-headless-ai-billing', [$this, 'renderBillingPage']);
        add_submenu_page('lse-headless-ai', 'Settings', 'Settings', 'manage_options', 'lse-headless-ai-settings', [$this, 'renderSettingsPage']);

        if ($hook !== false) {
            add_action("load-$hook", [$this, 'handleBlueprintActions']);
        }
    }

    public function registerSettings(): void
    {
        register_setting('lse_headless_ai_settings_group', 'lse_headless_ai_settings', [$this, 'sanitizeSettings']);

        add_settings_section(
            'lse_headless_ai_settings_section',
            'Service Connectivity',
            static function (): void {
                echo '<p>Enter your Luminate Strategy Engine API key to connect your WordPress site to the AI platform.</p>';
                echo '<p class="description">The API key is provided when you register for an account. All service endpoints are pre-configured.</p>';
            },
            'lse-headless-ai-settings'
        );

        $fields = [
            'api_key' => 'API Key',
            'site_context_id' => 'Site Context ID (Optional)',
        ];

        foreach ($fields as $key => $label) {
            add_settings_field(
                $key,
                esc_html($label),
                function () use ($key): void {
                    $options = $this->client->getOptions();
                    $value = isset($options[$key]) ? (string) $options[$key] : '';
                    $type = $key === 'api_key' ? 'password' : 'text';
                    printf(
                        '<input type="%1$s" id="%2$s" name="lse_headless_ai_settings[%2$s]" value="%3$s" class="regular-text" autocomplete="off" />',
                        esc_attr($type),
                        esc_attr($key),
                        esc_attr($value)
                    );
                },
                'lse-headless-ai-settings',
                'lse_headless_ai_settings_section'
            );
        }
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function sanitizeSettings(array $input): array
    {
        $options = $this->client->getOptions();

        foreach ($input as $key => $value) {
            if (! array_key_exists($key, $options)) {
                continue;
            }

            if ($key === 'api_key') {
                $options[$key] = sanitize_text_field((string) $value);
                continue;
            }

            if ($key === 'site_context_id') {
                $options[$key] = absint($value);
                continue;
            }

            $options[$key] = esc_url_raw((string) $value);
        }

        $this->addNotice('updated', 'Settings saved.');
        return $options;
    }

    public function renderSettingsPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>Headless AI Settings</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('lse_headless_ai_settings_group');
        do_settings_sections('lse-headless-ai-settings');
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public function handleBlueprintActions(): void
    {
        if (! isset($_POST['lse_create_blueprint'])) {
            return;
        }

        check_admin_referer('lse_create_blueprint');

        $name = isset($_POST['blueprint_name']) ? sanitize_text_field((string) $_POST['blueprint_name']) : '';
        $category = isset($_POST['blueprint_category']) ? sanitize_text_field((string) $_POST['blueprint_category']) : '';
        $status = isset($_POST['blueprint_status']) ? sanitize_text_field((string) $_POST['blueprint_status']) : 'draft';
        $description = isset($_POST['blueprint_description']) ? sanitize_textarea_field((string) $_POST['blueprint_description']) : '';
        $workflowJson = isset($_POST['blueprint_workflow']) ? (string) wp_unslash($_POST['blueprint_workflow']) : '';

        if ($name === '') {
            $this->addNotice('error', 'Blueprint name is required.');
            return;
        }

        $workflowDefinition = null;
        if ($workflowJson !== '') {
            $decoded = json_decode($workflowJson, true);
            if (! is_array($decoded)) {
                $this->addNotice('error', 'Workflow definition must be valid JSON.');
                return;
            }

            $workflowDefinition = $decoded;
        }

        $payload = [
            'name' => $name,
            'category' => $category !== '' ? $category : null,
            'status' => $status !== '' ? $status : null,
            'description' => $description !== '' ? $description : null,
            'workflowDefinition' => $workflowDefinition,
        ];

        $response = $this->client->request('m_api', '/blueprints', 'POST', $payload);
        if ($response['ok']) {
            $this->addNotice('updated', 'Blueprint created successfully.');
        } else {
            $message = $response['error'] ?? sprintf('Blueprint creation failed (HTTP %d).', $response['status']);
            $this->addNotice('error', $message);
        }
    }

    public function renderBlueprintsPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        echo '<div class="wrap">';
        echo '<h1>Blueprints</h1>';

        if (! $this->checkApiKeyConfigured()) {
            echo '</div>';
            return;
        }

        $listResponse = $this->client->request('m_api', '/blueprints', 'GET');
        $blueprints = $listResponse['ok'] && isset($listResponse['data']['blueprints']) && is_array($listResponse['data']['blueprints'])
            ? $listResponse['data']['blueprints']
            : [];

        if (! $listResponse['ok']) {
            $error = esc_html($listResponse['error'] ?? 'Unable to load blueprints.');
            printf('<div class="notice notice-error"><p>%s</p></div>', $error);
        }

        echo '<h2 class="title">Create Blueprint</h2>';
        echo '<form method="post">';
        wp_nonce_field('lse_create_blueprint');
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th><label for="blueprint_name">Name</label></th><td><input type="text" id="blueprint_name" name="blueprint_name" class="regular-text" required></td></tr>';
        echo '<tr><th><label for="blueprint_category">Category</label></th><td><input type="text" id="blueprint_category" name="blueprint_category" class="regular-text"></td></tr>';
        echo '<tr><th><label for="blueprint_status">Status</label></th><td><select id="blueprint_status" name="blueprint_status"><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></td></tr>';
        echo '<tr><th><label for="blueprint_description">Description</label></th><td><textarea id="blueprint_description" name="blueprint_description" rows="3" class="large-text"></textarea></td></tr>';
        echo '<tr><th><label for="blueprint_workflow">Workflow Definition (JSON)</label></th><td><textarea id="blueprint_workflow" name="blueprint_workflow" rows="6" class="large-text" placeholder="{\n  \"steps\": [\"research\", \"draft\"]\n}"></textarea></td></tr>';
        echo '</table>';
        submit_button('Create Blueprint', 'primary', 'lse_create_blueprint');
        echo '</form>';

        echo '<h2 class="title">Existing Blueprints</h2>';
        if ($blueprints === []) {
            echo '<p>No blueprints found for the configured operator.</p>';
        } else {
            echo '<table class="widefat fixed striped"><thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Version</th><th>Category</th><th>Created</th></tr></thead><tbody>';
            foreach ($blueprints as $blueprint) {
                printf(
                    '<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
                    esc_html((string) ($blueprint['id'] ?? '')),
                    esc_html((string) ($blueprint['name'] ?? '')),
                    esc_html((string) ($blueprint['status'] ?? '')),
                    esc_html((string) ($blueprint['version'] ?? '')),
                    esc_html((string) ($blueprint['category'] ?? '')),
                    esc_html((string) ($blueprint['created_at'] ?? ''))
                );
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function renderStrategyPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        echo '<div class="wrap">';
        echo '<h1>Strategy Suggestions</h1>';

        if (! $this->checkApiKeyConfigured()) {
            echo '</div>';
            return;
        }

        $options = $this->client->getOptions();
        $defaultContext = isset($options['site_context_id']) ? (int) $options['site_context_id'] : 0;

        $suggestions = null;
        if (isset($_POST['lse_generate_suggestions'])) {
            check_admin_referer('lse_generate_suggestions');
            $siteContextId = isset($_POST['site_context_id']) ? absint($_POST['site_context_id']) : 0;
            $trendPayload = isset($_POST['trend_payload']) ? (string) wp_unslash($_POST['trend_payload']) : '';
            $blueprintIdsRaw = isset($_POST['blueprint_ids']) ? (string) wp_unslash($_POST['blueprint_ids']) : '';

            if ($siteContextId <= 0) {
                $this->addNotice('error', 'Site Context ID is required to generate suggestions.');
            } else {
                $trendSignals = json_decode($trendPayload, true);
                if (! is_array($trendSignals)) {
                    $this->addNotice('error', 'Trend signals must be valid JSON.');
                } else {
                    $blueprintIds = null;
                    if ($blueprintIdsRaw !== '') {
                        $parsed = array_filter(array_map('absint', explode(',', $blueprintIdsRaw)));
                        $blueprintIds = $parsed !== [] ? array_values($parsed) : null;
                    }

                    $payload = [
                        'siteContextId' => $siteContextId,
                        'trendSignals' => array_values($trendSignals),
                    ];

                    if ($blueprintIds !== null) {
                        $payload['blueprintIds'] = $blueprintIds;
                    }

                    // Strategy suggestions are routed through M-API which calls S-API internally
                    $response = $this->client->request('m_api', '/strategy/suggestions', 'POST', $payload);
                    if ($response['ok']) {
                        $suggestions = $response['data']['suggestions'] ?? [];
                        $count = is_array($suggestions) ? count($suggestions) : 0;
                        $this->addNotice('updated', sprintf('Generated %d suggestions.', $count));
                    } else {
                        $this->addNotice('error', $response['error'] ?? 'Suggestion generation failed.');
                    }
                }
            }
        }

        echo '<p>Provide recent trend signals to generate prioritized content actions using the Strategy API.</p>';
        echo '<form method="post">';
        wp_nonce_field('lse_generate_suggestions');
        echo '<table class="form-table"><tr><th><label for="site_context_id">Site Context ID</label></th><td><input type="number" min="1" id="site_context_id" name="site_context_id" value="' . esc_attr((string) $defaultContext) . '" required></td></tr>';
        echo '<tr><th><label for="blueprint_ids">Blueprint IDs (comma separated)</label></th><td><input type="text" id="blueprint_ids" name="blueprint_ids" class="regular-text" placeholder="1,2"></td></tr>';
        echo '<tr><th><label for="trend_payload">Trend Signals JSON</label></th><td><textarea id="trend_payload" name="trend_payload" rows="8" class="large-text" placeholder="[{\n  \"topic\": \"Zero-party data trends\",\n  \"searchVolume\": 2800,\n  \"growthRate\": 0.4,\n  \"competition\": 0.2\n}]"></textarea></td></tr></table>';
        submit_button('Generate Suggestions', 'primary', 'lse_generate_suggestions');
        echo '</form>';

        if (is_array($suggestions) && $suggestions !== []) {
            echo '<h2 class="title">Suggested Initiatives</h2>';
            echo '<table class="widefat fixed striped"><thead><tr><th>Topic</th><th>Priority</th><th>Suggested For</th><th>Blueprint</th><th>Call To Action</th></tr></thead><tbody>';
            foreach ($suggestions as $suggestion) {
                $payload = is_array($suggestion['payload'] ?? null) ? $suggestion['payload'] : [];
                printf(
                    '<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
                    esc_html((string) ($payload['topic'] ?? '')),
                    esc_html((string) ($suggestion['priority'] ?? '')),
                    esc_html((string) ($suggestion['suggestedFor'] ?? '')),
                    esc_html((string) ($suggestion['blueprintId'] ?? '')),
                    esc_html((string) ($payload['call_to_action'] ?? ''))
                );
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function renderAnalyticsPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        echo '<div class="wrap">';
        echo '<h1>Analytics & Agent Detection</h1>';

        if (! $this->checkApiKeyConfigured()) {
            echo '</div>';
            return;
        }

        $detections = null;
        if (isset($_POST['lse_detect_agents'])) {
            check_admin_referer('lse_detect_agents');
            $eventsJson = isset($_POST['agent_events']) ? (string) wp_unslash($_POST['agent_events']) : '';
            $events = json_decode($eventsJson, true);

            if (! is_array($events)) {
                $this->addNotice('error', 'Agent events must be valid JSON.');
            } else {
                // Agent detection is routed through M-API which calls S-API internally
                $response = $this->client->request('m_api', '/analytics/agent-detections', 'POST', ['events' => $events]);
                if ($response['ok']) {
                    $detections = $response['data']['detections'] ?? [];
                    $count = is_array($detections) ? count($detections) : 0;
                    $this->addNotice('updated', sprintf('Detected %d AI agents.', $count));
                } else {
                    $this->addNotice('error', $response['error'] ?? 'Detection request failed.');
                }
            }
        }

        echo '<p>Submit analytics log samples to classify AI agent activity for your site.</p>';
        echo '<form method="post">';
        wp_nonce_field('lse_detect_agents');
        echo '<table class="form-table"><tr><th><label for="agent_events">Analytics Events JSON</label></th><td><textarea id="agent_events" name="agent_events" rows="8" class="large-text" placeholder="[{\n  \"analyticsLogId\": 77,\n  \"userAgent\": \"Mozilla/5.0 (compatible; GPTBot/1.0)\"\n}]"></textarea></td></tr></table>';
        submit_button('Identify Agents', 'primary', 'lse_detect_agents');
        echo '</form>';

        if (is_array($detections) && $detections !== []) {
            echo '<h2 class="title">Detected Agents</h2>';
            echo '<table class="widefat fixed striped"><thead><tr><th>Log ID</th><th>Agent</th><th>Family</th><th>Confidence</th><th>Guidance</th></tr></thead><tbody>';
            foreach ($detections as $detection) {
                printf(
                    '<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td></tr>',
                    esc_html((string) ($detection['analyticsLogId'] ?? '')),
                    esc_html((string) ($detection['agentName'] ?? '')),
                    esc_html((string) ($detection['agentFamily'] ?? '')),
                    esc_html((string) ($detection['confidence'] ?? '')),
                    esc_html((string) ($detection['guidance'] ?? ''))
                );
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }

    public function renderBillingPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        echo '<div class="wrap">';
        echo '<h1>Billing Overview</h1>';

        // Check if API key is configured
        $options = $this->client->getOptions();
        $apiKey = isset($options['api_key']) ? trim((string) $options['api_key']) : '';
        
        if ($apiKey === '') {
            printf(
                '<div class="notice notice-warning"><p>Please configure your API key in <a href="%s">Settings</a> to view billing information.</p></div>',
                esc_url(admin_url('admin.php?page=lse-headless-ai-settings'))
            );
            echo '</div>';
            return;
        }

        $response = $this->client->request('m_api', '/billing/status', 'GET');

        if (! $response['ok'] || ! isset($response['data']['billing'])) {
            $error = esc_html($response['error'] ?? 'Unable to retrieve billing status.');
            printf('<div class="notice notice-error"><p>%s</p></div>', $error);
            echo '</div>';
            return;
        }

        $billing = $response['data']['billing'];
        $user = $response['data']['user'] ?? [];

        echo '<p><strong>User:</strong> ' . esc_html((string) ($user['email'] ?? 'Unknown')) . '</p>';

        echo '<table class="widefat fixed striped"><thead><tr><th>Period</th><th>Subtotal</th><th>Discounts</th><th>Taxes</th><th>Total Due</th><th>Status</th></tr></thead><tbody>';
        foreach ($billing['invoices'] ?? [] as $invoice) {
            printf(
                '<tr><td>%1$s &ndash; %2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td><td>%7$s</td></tr>',
                esc_html((string) ($invoice['billing_period_start'] ?? '')),
                esc_html((string) ($invoice['billing_period_end'] ?? '')),
                esc_html((string) ($invoice['subtotal'] ?? '0')),
                esc_html((string) ($invoice['discounts'] ?? '0')),
                esc_html((string) ($invoice['taxes'] ?? '0')),
                esc_html((string) ($invoice['total_due'] ?? '0')),
                esc_html((string) ($invoice['status'] ?? 'pending'))
            );
        }
        echo '</tbody></table>';

        if (isset($billing['tokenUsageSummary']) && is_array($billing['tokenUsageSummary'])) {
            $summary = $billing['tokenUsageSummary'];
            echo '<h2 class="title">Token Usage Summary</h2>';
            echo '<ul>';
            echo '<li><strong>Total Tokens:</strong> ' . esc_html((string) ($summary['totalTokens'] ?? 0)) . '</li>';
            echo '<li><strong>Estimated Cost:</strong> ' . esc_html((string) ($summary['estimatedCost'] ?? '0')) . '</li>';
            echo '<li><strong>Last Activity:</strong> ' . esc_html((string) ($summary['lastActivity'] ?? 'N/A')) . '</li>';
            echo '</ul>';
        }

        echo '</div>';
    }

    public function renderNotices(): void
    {
        foreach ($this->notices as $notice) {
            printf('<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr($notice['type']), esc_html($notice['message']));
        }
        $this->notices = [];
    }

    /**
     * Check if API key is configured, show warning if not.
     * @return bool True if configured, false otherwise
     */
    private function checkApiKeyConfigured(): bool
    {
        $options = $this->client->getOptions();
        $apiKey = isset($options['api_key']) ? trim((string) $options['api_key']) : '';
        
        if ($apiKey === '') {
            printf(
                '<div class="notice notice-warning"><p>Please configure your API key in <a href="%s">Settings</a> to use this feature.</p></div>',
                esc_url(admin_url('admin.php?page=lse-headless-ai-settings'))
            );
            return false;
        }
        
        return true;
    }

    private function addNotice(string $type, string $message): void
    {
        $this->notices[] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}
