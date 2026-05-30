<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class BaseAgKpis extends \Module
{
    private const TABLE = 'agkpis_kpi';

    public function __construct()
    {
        $this->name = 'agkpis';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'AGTI';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = 'AG KPIs';
        $this->description = 'Exibe KPIs personalizados acima da lista de pedidos.';
        $this->ps_versions_compliancy = ['min' => '1.7.8.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->installDatabase()
            && $this->ensureHookExists('actionOrdersKpiRowModifier')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('actionOrdersKpiRowModifier');
    }

    public function uninstall()
    {
        return $this->uninstallDatabase() && parent::uninstall();
    }

    public function getContent()
    {
        $messages = $this->handleConfigurationRequest();
        $editingId = (int) \Tools::getValue('id_agkpis_kpi');
        $definition = $editingId > 0 ? $this->getDefinitionById($editingId) : null;

        if (\Tools::isSubmit('submitAgKpiDefinition')) {
            $definition = $this->getDefinitionFromRequest();
        }

        return $messages
            . $this->renderHelpBlock()
            . $this->renderDefinitionsList()
            . $this->renderDefinitionForm($definition);
    }

    public function hookDisplayBackOfficeHeader()
    {
        $controller = (string) \Tools::getValue('controller');
        $configure = (string) \Tools::getValue('configure');
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $isOrdersPage = $controller === 'AdminOrders' || strpos($requestUri, '/sell/orders') !== false;

        if (!$isOrdersPage && $configure !== $this->name) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');
    }

    public function hookActionOrdersKpiRowModifier(array $params)
    {
        if (!isset($params['kpis']) || !is_array($params['kpis'])) {
            return;
        }

        foreach ($this->getDefinitions(true) as $definition) {
            $params['kpis'][] = new AgKpisOrdersCard(
                $this,
                $definition,
                $this->getMetricsForDefinition($definition)
            );
        }
    }

    public function transAdmin($message, array $parameters = [])
    {
        return $this->trans($message, $parameters, 'Modules.Agkpis.Admin');
    }

    public function formatAdminPrice($amount, \Currency $currency)
    {
        try {
            return \Tools::getContextLocale($this->context)->formatPrice((float) $amount, $currency->iso_code);
        } catch (\Throwable $exception) {
            return $currency->getSign() . number_format((float) $amount, max(0, (int) $currency->precision), ',', '.');
        }
    }

    public function getOrdersPanelTitle()
    {
        return $this->trans('KPIs personalizados', [], 'Modules.Agkpis.Admin');
    }

    public function getOrdersInlineCss()
    {
        return $this->getAssetContent('views/css/admin.css');
    }

    public function getOrdersInlineJs()
    {
        return $this->getAssetContent('views/js/admin.js');
    }

    public function getOrdersFilterUrl(array $definition)
    {
        $fromDate = date('Y-m-d', strtotime('-' . max(0, ((int) $definition['period_days']) - 1) . ' days'));
        $toDate = date('Y-m-d');

        return $this->context->link->getAdminLink('AdminOrders', true, [], [
            'order[filters][osname]' => (int) $definition['order_state_id'],
            'order[filters][date_add][from]' => $fromDate,
            'order[filters][date_add][to]' => $toDate,
        ]);
    }

    private function installDatabase()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_agkpis_kpi` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(191) NOT NULL,
            `background_color` VARCHAR(7) NOT NULL,
            `text_color` VARCHAR(7) NOT NULL,
            `order_states` TEXT NOT NULL,
            `period_days` INT UNSIGNED NOT NULL DEFAULT 30,
            `position` INT UNSIGNED NOT NULL DEFAULT 0,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_agkpis_kpi`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return \Db::getInstance()->execute($sql);
    }

    private function ensureHookExists($hookName)
    {
        if (\Hook::getIdByName($hookName, true, true)) {
            return true;
        }

        $hook = new \Hook();
        $hook->name = pSQL($hookName);
        $hook->title = pSQL($hookName);
        $hook->position = true;

        if (!$hook->add()) {
            return false;
        }

        return (bool) \Hook::getIdByName($hookName, true, true);
    }

    private function getAssetContent($relativePath)
    {
        $path = $this->local_path . ltrim($relativePath, '/');

        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        $content = file_get_contents($path);

        return $content === false ? '' : $content;
    }

    private function uninstallDatabase()
    {
        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
    }

    private function handleConfigurationRequest()
    {
        if (\Tools::isSubmit('submitAgKpiDefinition')) {
            $errors = $this->saveDefinitionFromRequest();

            if (empty($errors)) {
                \Tools::redirectAdmin($this->getConfigureUrl(['conf' => 'save']));
            }

            return $this->buildErrorsOutput($errors);
        }

        $action = (string) \Tools::getValue('agkpis_action');
        $definitionId = (int) \Tools::getValue('id_agkpis_kpi');

        if ($action === 'delete' && $definitionId > 0) {
            if ($this->deleteDefinition($definitionId)) {
                \Tools::redirectAdmin($this->getConfigureUrl(['conf' => 'delete']));
            }

            return $this->displayError($this->trans('Nao foi possivel remover o KPI.', [], 'Modules.Agkpis.Admin'));
        }

        if ($action === 'toggle' && $definitionId > 0) {
            if ($this->toggleDefinition($definitionId)) {
                \Tools::redirectAdmin($this->getConfigureUrl(['conf' => 'toggle']));
            }

            return $this->displayError($this->trans('Nao foi possivel alterar o status do KPI.', [], 'Modules.Agkpis.Admin'));
        }

        return $this->renderStatusMessage();
    }

    private function renderStatusMessage()
    {
        $conf = (string) \Tools::getValue('conf');

        if ($conf === 'save') {
            return $this->displayConfirmation($this->trans('KPI salvo com sucesso.', [], 'Modules.Agkpis.Admin'));
        }

        if ($conf === 'delete') {
            return $this->displayConfirmation($this->trans('KPI removido com sucesso.', [], 'Modules.Agkpis.Admin'));
        }

        if ($conf === 'toggle') {
            return $this->displayConfirmation($this->trans('Status do KPI atualizado.', [], 'Modules.Agkpis.Admin'));
        }

        return '';
    }

    private function renderHelpBlock()
    {
        $title = $this->trans('KPIs na listagem de pedidos', [], 'Modules.Agkpis.Admin');
        $description = $this->trans(
            'Cadastre cards com titulo, cores, periodo em dias e um estado de pedido. Cada card exibira quantidade de pedidos, valor total e quantidade de itens em um painel proprio abaixo dos KPIs nativos.',
            [],
            'Modules.Agkpis.Admin'
        );

        return '<div class="panel"><h3>' . $title . '</h3><p>' . $description . '</p></div>';
    }

    private function renderDefinitionsList()
    {
        $definitions = $this->getDefinitions();
        $statesMap = $this->getOrderStatesMap();
        $html = '<div class="panel agkpis-panel">';
        $html .= '<div class="agkpis-panel-header">';
        $html .= '<h3>' . $this->trans('KPIs cadastrados', [], 'Modules.Agkpis.Admin') . '</h3>';
        $html .= '<a class="btn btn-primary js-agkpis-scroll" href="#agkpis-form">';
        $html .= $this->trans('Novo KPI', [], 'Modules.Agkpis.Admin') . '</a>';
        $html .= '</div>';

        if (empty($definitions)) {
            $html .= '<p class="alert alert-info">'
                . $this->trans('Nenhum KPI cadastrado ainda.', [], 'Modules.Agkpis.Admin')
                . '</p></div>';

            return $html;
        }

        $html .= '<div class="table-responsive-row clearfix">';
        $html .= '<table class="table">';
        $html .= '<thead><tr>';
        $html .= '<th>' . $this->trans('Posicao', [], 'Modules.Agkpis.Admin') . '</th>';
        $html .= '<th>' . $this->trans('Titulo', [], 'Modules.Agkpis.Admin') . '</th>';
        $html .= '<th>' . $this->trans('Periodo', [], 'Modules.Agkpis.Admin') . '</th>';
        $html .= '<th>' . $this->trans('Estado', [], 'Modules.Agkpis.Admin') . '</th>';
        $html .= '<th>' . $this->trans('Cores', [], 'Modules.Agkpis.Admin') . '</th>';
        $html .= '<th>' . $this->trans('Ativo', [], 'Modules.Agkpis.Admin') . '</th>';
        $html .= '<th class="text-right">' . $this->trans('Acoes', [], 'Modules.Agkpis.Admin') . '</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($definitions as $definition) {
            $editUrl = $this->getConfigureUrl([
                'id_agkpis_kpi' => (int) $definition['id_agkpis_kpi'],
                'focus' => 'form',
            ]) . '#agkpis-form';
            $toggleUrl = $this->getConfigureUrl([
                'agkpis_action' => 'toggle',
                'id_agkpis_kpi' => (int) $definition['id_agkpis_kpi'],
            ]);
            $deleteUrl = $this->getConfigureUrl([
                'agkpis_action' => 'delete',
                'id_agkpis_kpi' => (int) $definition['id_agkpis_kpi'],
            ]);
            $stateName = isset($statesMap[$definition['order_state_id']])
                ? $statesMap[$definition['order_state_id']]
                : '';

            $html .= '<tr>';
            $html .= '<td>' . (int) $definition['position'] . '</td>';
            $html .= '<td>' . \Tools::safeOutput($definition['title']) . '</td>';
            $html .= '<td>' . sprintf(
                $this->trans('%d dias', [], 'Modules.Agkpis.Admin'),
                (int) $definition['period_days']
            ) . '</td>';
            $html .= '<td>' . \Tools::safeOutput($stateName) . '</td>';
            $html .= '<td><span class="agkpis-color-chip" style="background:'
                . \Tools::safeOutput($definition['background_color'])
                . ';color:' . \Tools::safeOutput($definition['text_color']) . ';">'
                . \Tools::safeOutput($definition['background_color']) . ' / ' . \Tools::safeOutput($definition['text_color'])
                . '</span></td>';
            $html .= '<td>' . ($definition['active'] ? $this->trans('Sim', [], 'Modules.Agkpis.Admin') : $this->trans('Nao', [], 'Modules.Agkpis.Admin')) . '</td>';
            $html .= '<td class="text-right">';
            $html .= '<a class="btn btn-default" href="' . htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8') . '">'
                . $this->trans('Editar', [], 'Modules.Agkpis.Admin') . '</a> ';
            $html .= '<a class="btn btn-default" href="' . htmlspecialchars($toggleUrl, ENT_QUOTES, 'UTF-8') . '">'
                . ($definition['active'] ? $this->trans('Desativar', [], 'Modules.Agkpis.Admin') : $this->trans('Ativar', [], 'Modules.Agkpis.Admin'))
                . '</a> ';
            $html .= '<a class="btn btn-danger" href="' . htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') . '" onclick="return confirm(\''
                . addslashes($this->trans('Deseja remover este KPI?', [], 'Modules.Agkpis.Admin'))
                . '\');">' . $this->trans('Remover', [], 'Modules.Agkpis.Admin') . '</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div></div>';

        return $html;
    }

    private function renderDefinitionForm($definition = null)
    {
        $helper = new \HelperForm();
        $helper->module = $this;
        $helper->show_toolbar = false;
        $helper->table = self::TABLE;
        $helper->name_controller = $this->name;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) \Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = 'id_agkpis_kpi';
        $helper->submit_action = 'submitAgKpiDefinition';
        $helper->currentIndex = $this->getConfigureUrl([], false);
        $helper->token = \Tools::getAdminTokenLite('AdminModules');

        $stateOptions = [];
        foreach (\OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            $stateOptions[] = [
                'id' => (int) $state['id_order_state'],
                'name' => $state['name'],
            ];
        }

        $fieldsForm = [[
            'form' => [
                'legend' => [
                    'title' => $definition && !empty($definition['id_agkpis_kpi'])
                        ? $this->trans('Editar KPI', [], 'Modules.Agkpis.Admin')
                        : $this->trans('Novo KPI', [], 'Modules.Agkpis.Admin'),
                ],
                'input' => [
                    [
                        'type' => 'hidden',
                        'name' => 'id_agkpis_kpi',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Titulo', [], 'Modules.Agkpis.Admin'),
                        'name' => 'AGKPIS_TITLE',
                        'required' => true,
                        'col' => 4,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Cor de fundo', [], 'Modules.Agkpis.Admin'),
                        'name' => 'AGKPIS_BACKGROUND_COLOR',
                        'required' => true,
                        'col' => 2,
                        'desc' => $this->trans('Use o formato hexadecimal, por exemplo #179BD7.', [], 'Modules.Agkpis.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Cor da fonte', [], 'Modules.Agkpis.Admin'),
                        'name' => 'AGKPIS_TEXT_COLOR',
                        'required' => true,
                        'col' => 2,
                        'desc' => $this->trans('Use o formato hexadecimal, por exemplo #FFFFFF.', [], 'Modules.Agkpis.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Periodo', [], 'Modules.Agkpis.Admin'),
                        'name' => 'AGKPIS_PERIOD_DAYS',
                        'required' => true,
                        'col' => 2,
                        'suffix' => $this->trans('dias', [], 'Modules.Agkpis.Admin'),
                        'desc' => $this->trans('Quantidade de dias para considerar no KPI.', [], 'Modules.Agkpis.Admin'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Posicao', [], 'Modules.Agkpis.Admin'),
                        'name' => 'AGKPIS_POSITION',
                        'required' => true,
                        'col' => 2,
                        'desc' => $this->trans('Menores valores aparecem primeiro.', [], 'Modules.Agkpis.Admin'),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Estado do pedido', [], 'Modules.Agkpis.Admin'),
                        'name' => 'AGKPIS_ORDER_STATE',
                        'col' => 4,
                        'required' => true,
                        'options' => [
                            'id' => 'id',
                            'name' => 'name',
                            'query' => $stateOptions,
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Ativo', [], 'Modules.Agkpis.Admin'),
                        'name' => 'AGKPIS_ACTIVE',
                        'values' => [
                            [
                                'id' => 'agkpis_active_on',
                                'value' => 1,
                                'label' => $this->trans('Sim', [], 'Modules.Agkpis.Admin'),
                            ],
                            [
                                'id' => 'agkpis_active_off',
                                'value' => 0,
                                'label' => $this->trans('Nao', [], 'Modules.Agkpis.Admin'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Salvar', [], 'Modules.Agkpis.Admin'),
                    'class' => 'btn btn-primary pull-right',
                ],
            ],
        ]];

        $values = $this->getDefinitionFormValues($definition);
        foreach ($values as $key => $value) {
            $helper->fields_value[$key] = $value;
        }

        $cancelUrl = $this->getConfigureUrl();
        $html = '<div id="agkpis-form" class="panel agkpis-panel">';
        if ($definition && !empty($definition['id_agkpis_kpi'])) {
            $html .= '<div class="agkpis-panel-header">';
            $html .= '<h3>' . $this->trans('Edicao', [], 'Modules.Agkpis.Admin') . '</h3>';
            $html .= '<a class="btn btn-default" href="' . htmlspecialchars($cancelUrl, ENT_QUOTES, 'UTF-8') . '">'
                . $this->trans('Cancelar edicao', [], 'Modules.Agkpis.Admin') . '</a>';
            $html .= '</div>';
        }
        $html .= $helper->generateForm($fieldsForm);
        $html .= '</div>';

        return $html;
    }

    private function getDefinitionFormValues($definition = null)
    {
        if ($definition === null) {
            return [
                'id_agkpis_kpi' => 0,
                'AGKPIS_TITLE' => '',
                'AGKPIS_BACKGROUND_COLOR' => '#179BD7',
                'AGKPIS_TEXT_COLOR' => '#FFFFFF',
                'AGKPIS_PERIOD_DAYS' => 30,
                'AGKPIS_POSITION' => $this->getNextPosition(),
                'AGKPIS_ORDER_STATE' => 0,
                'AGKPIS_ACTIVE' => 1,
            ];
        }

        return [
            'id_agkpis_kpi' => (int) $definition['id_agkpis_kpi'],
            'AGKPIS_TITLE' => $definition['title'],
            'AGKPIS_BACKGROUND_COLOR' => $definition['background_color'],
            'AGKPIS_TEXT_COLOR' => $definition['text_color'],
            'AGKPIS_PERIOD_DAYS' => (int) $definition['period_days'],
            'AGKPIS_POSITION' => (int) $definition['position'],
            'AGKPIS_ORDER_STATE' => (int) $definition['order_state_id'],
            'AGKPIS_ACTIVE' => (int) $definition['active'],
        ];
    }

    private function getDefinitionFromRequest()
    {
        return [
            'id_agkpis_kpi' => (int) \Tools::getValue('id_agkpis_kpi'),
            'title' => trim((string) \Tools::getValue('AGKPIS_TITLE')),
            'background_color' => trim((string) \Tools::getValue('AGKPIS_BACKGROUND_COLOR')),
            'text_color' => trim((string) \Tools::getValue('AGKPIS_TEXT_COLOR')),
            'period_days' => (int) \Tools::getValue('AGKPIS_PERIOD_DAYS'),
            'position' => (int) \Tools::getValue('AGKPIS_POSITION'),
            'order_state_ids' => $this->normalizeStateIds(\Tools::getValue('AGKPIS_ORDER_STATE')),
            'active' => (int) \Tools::getValue('AGKPIS_ACTIVE'),
        ];
    }

    private function saveDefinitionFromRequest()
    {
        $definition = $this->getDefinitionFromRequest();
        $errors = [];

        if ($definition['title'] === '') {
            $errors[] = $this->trans('Informe um titulo para o KPI.', [], 'Modules.Agkpis.Admin');
        }

        if (!$this->isValidHexColor($definition['background_color'])) {
            $errors[] = $this->trans('A cor de fundo deve estar no formato hexadecimal #RRGGBB.', [], 'Modules.Agkpis.Admin');
        }

        if (!$this->isValidHexColor($definition['text_color'])) {
            $errors[] = $this->trans('A cor da fonte deve estar no formato hexadecimal #RRGGBB.', [], 'Modules.Agkpis.Admin');
        }

        if ($definition['period_days'] < 1) {
            $errors[] = $this->trans('O periodo deve ser maior ou igual a 1 dia.', [], 'Modules.Agkpis.Admin');
        }

        if (empty($definition['order_state_ids'])) {
            $errors[] = $this->trans('Selecione um estado do pedido.', [], 'Modules.Agkpis.Admin');
        }

        if ($definition['position'] < 0) {
            $errors[] = $this->trans('A posicao deve ser maior ou igual a zero.', [], 'Modules.Agkpis.Admin');
        }

        if (!empty($errors)) {
            return $errors;
        }

        $now = date('Y-m-d H:i:s');
        $data = [
            'title' => pSQL($definition['title']),
            'background_color' => pSQL(strtoupper($definition['background_color'])),
            'text_color' => pSQL(strtoupper($definition['text_color'])),
            'order_states' => pSQL(json_encode(array_values($definition['order_state_ids']))),
            'period_days' => (int) $definition['period_days'],
            'position' => (int) $definition['position'],
            'active' => (int) $definition['active'],
            'date_upd' => $now,
        ];

        if ($definition['id_agkpis_kpi'] > 0) {
            $updated = \Db::getInstance()->update(
                self::TABLE,
                $data,
                '`id_agkpis_kpi` = ' . (int) $definition['id_agkpis_kpi']
            );

            return $updated ? [] : [$this->trans('Nao foi possivel atualizar o KPI.', [], 'Modules.Agkpis.Admin')];
        }

        $data['date_add'] = $now;
        $created = \Db::getInstance()->insert(self::TABLE, $data);

        return $created ? [] : [$this->trans('Nao foi possivel criar o KPI.', [], 'Modules.Agkpis.Admin')];
    }

    private function deleteDefinition($definitionId)
    {
        return \Db::getInstance()->delete(self::TABLE, '`id_agkpis_kpi` = ' . (int) $definitionId);
    }

    private function toggleDefinition($definitionId)
    {
        $definition = $this->getDefinitionById($definitionId);
        if (!$definition) {
            return false;
        }

        return \Db::getInstance()->update(
            self::TABLE,
            [
                'active' => (int) !$definition['active'],
                'date_upd' => date('Y-m-d H:i:s'),
            ],
            '`id_agkpis_kpi` = ' . (int) $definitionId
        );
    }

    private function getDefinitions($activeOnly = false)
    {
        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`';
        if ($activeOnly) {
            $sql .= ' WHERE `active` = 1';
        }
        $sql .= ' ORDER BY `position` ASC, `id_agkpis_kpi` ASC';

        $rows = \Db::getInstance()->executeS($sql);
        if (!$rows) {
            return [];
        }

        return array_map([$this, 'hydrateDefinition'], $rows);
    }

    private function getDefinitionById($definitionId)
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_agkpis_kpi` = ' . (int) $definitionId
        );

        if (!$row) {
            return null;
        }

        return $this->hydrateDefinition($row);
    }

    private function hydrateDefinition(array $row)
    {
        $stateIds = json_decode($row['order_states'], true);
        if (!is_array($stateIds)) {
            $stateIds = [];
        }

        $row['id_agkpis_kpi'] = (int) $row['id_agkpis_kpi'];
        $row['period_days'] = (int) $row['period_days'];
        $row['position'] = (int) $row['position'];
        $row['active'] = (int) $row['active'];
        $row['order_state_ids'] = $this->normalizeStateIds($stateIds);
        $row['order_state_id'] = (int) ($row['order_state_ids'][0] ?? 0);

        return $row;
    }

    private function getMetricsForDefinition(array $definition)
    {
        if (empty($definition['order_state_ids'])) {
            return [
                'orders_count' => 0,
                'orders_total' => 0.0,
                'items_count' => 0,
            ];
        }

        $where = $this->buildOrdersWhereClause($definition['order_state_ids'], (int) $definition['period_days']);
        $ordersSql = 'SELECT COUNT(o.`id_order`) AS orders_count,
                IFNULL(SUM(o.`total_paid_tax_incl` / IFNULL(NULLIF(o.`conversion_rate`, 0), 1)), 0) AS orders_total
            FROM `' . _DB_PREFIX_ . 'orders` o
            WHERE 1 ' . $where;
        $itemsSql = 'SELECT IFNULL(SUM(od.`product_quantity`), 0) AS items_count
            FROM `' . _DB_PREFIX_ . 'orders` o
            INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.`id_order` = o.`id_order`
            WHERE 1 ' . $where;

        $ordersRow = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($ordersSql);
        $itemsRow = \Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($itemsSql);

        return [
            'orders_count' => (int) ($ordersRow['orders_count'] ?? 0),
            'orders_total' => (float) ($ordersRow['orders_total'] ?? 0),
            'items_count' => (int) ($itemsRow['items_count'] ?? 0),
        ];
    }

    private function buildOrdersWhereClause(array $stateIds, $periodDays)
    {
        $sql = ' AND o.`current_state` IN (' . implode(', ', array_map('intval', $stateIds)) . ')';
        $sql .= \Shop::addSqlRestriction(\Shop::SHARE_ORDER, 'o');
        $sql .= ' AND o.`date_add` >= DATE_SUB(NOW(), INTERVAL ' . (int) $periodDays . ' DAY)';

        return $sql;
    }

    private function normalizeStateIds($stateIds)
    {
        if (!is_array($stateIds)) {
            $stateIds = [$stateIds];
        }

        $stateIds = array_map('intval', $stateIds);
        $stateIds = array_filter($stateIds, function ($stateId) {
            return $stateId > 0;
        });

        return array_values(array_unique($stateIds));
    }

    private function isValidHexColor($color)
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $color);
    }

    private function getNextPosition()
    {
        $position = \Db::getInstance()->getValue('SELECT MAX(`position`) FROM `' . _DB_PREFIX_ . self::TABLE . '`');

        return ((int) $position) + 1;
    }

    private function getOrderStatesMap()
    {
        $map = [];

        foreach (\OrderState::getOrderStates((int) $this->context->language->id) as $state) {
            $map[(int) $state['id_order_state']] = $state['name'];
        }

        return $map;
    }

    private function buildErrorsOutput(array $errors)
    {
        $html = '';

        foreach ($errors as $error) {
            $html .= $this->displayError($error);
        }

        return $html;
    }

    private function getConfigureUrl(array $params = [], $withToken = true)
    {
        return $this->context->link->getAdminLink(
            'AdminModules',
            $withToken,
            [],
            array_merge([
                'configure' => $this->name,
                'module_name' => $this->name,
                'tab_module' => $this->tab,
            ], $params)
        );
    }
}

class AgKpisOrdersCard implements \PrestaShop\PrestaShop\Core\Kpi\KpiInterface
{
    /** @var BaseAgKpis */
    private $module;

    /** @var array */
    private $definition;

    /** @var array */
    private $metrics;

    public function __construct(BaseAgKpis $module, array $definition, array $metrics)
    {
        $this->module = $module;
        $this->definition = $definition;
        $this->metrics = $metrics;
    }

    public function render()
    {
        static $assetsRendered = false;

        $currency = new \Currency((int) \Configuration::get('PS_CURRENCY_DEFAULT'));
        $title = \Tools::safeOutput($this->definition['title']);
        $backgroundColor = \Tools::safeOutput($this->definition['background_color']);
        $textColor = \Tools::safeOutput($this->definition['text_color']);
        $filterUrl = htmlspecialchars($this->module->getOrdersFilterUrl($this->definition), ENT_QUOTES, 'UTF-8');
        $panelTitle = htmlspecialchars($this->module->getOrdersPanelTitle(), ENT_QUOTES, 'UTF-8');
        $subtitle = (int) $this->definition['period_days'] === 1
            ? $this->module->transAdmin('Ultimo dia')
            : sprintf(
                $this->module->transAdmin('Ultimos %d dias'),
                (int) $this->definition['period_days']
            );

        $stats = [
            [
                'label' => $this->module->transAdmin('Pedidos'),
                'value' => number_format((int) $this->metrics['orders_count'], 0, ',', '.'),
            ],
            [
                'label' => $this->module->transAdmin('Valor total'),
                'value' => $this->module->formatAdminPrice((float) $this->metrics['orders_total'], $currency),
            ],
            [
                'label' => $this->module->transAdmin('Itens'),
                'value' => number_format((int) $this->metrics['items_count'], 0, ',', '.'),
            ],
        ];

        $html = '';

        if (!$assetsRendered) {
            $inlineCss = $this->module->getOrdersInlineCss();
            $inlineJs = $this->module->getOrdersInlineJs();

            if ($inlineCss !== '') {
                $html .= '<style>' . $inlineCss . '</style>';
            }

            if ($inlineJs !== '') {
                $html .= '<script>' . $inlineJs . '</script>';
            }

            $assetsRendered = true;
        }

        $html .= '<a class="agkpis-card-link" href="' . $filterUrl . '" data-panel-title="' . $panelTitle . '">';
        $html .= '<div class="agkpis-card" style="--agkpis-bg:' . $backgroundColor . ';--agkpis-text:' . $textColor . ';">';
        $html .= '<div class="agkpis-card__head">';
        $html .= '<div class="agkpis-card__title">' . $title . '</div>';
        $html .= '<div class="agkpis-card__subtitle">' . \Tools::safeOutput($subtitle) . '</div>';
        $html .= '</div>';
        $html .= '<div class="agkpis-card__stats">';

        foreach ($stats as $stat) {
            $html .= '<div class="agkpis-card__stat">';
            $html .= '<span class="agkpis-card__label">' . \Tools::safeOutput($stat['label']) . '</span>';
            $html .= '<strong class="agkpis-card__value">' . \Tools::safeOutput($stat['value']) . '</strong>';
            $html .= '</div>';
        }

        $html .= '</div></div></a>';

        return $html;
    }
}