<?php
/**
 * Plugin Name: AmoCRM Integration Elementor RuCoder
 * Plugin URI: https://github.com/RuCoder-sudo/WooAmoSync
 * Description: Полнофункциональная интеграция WooCommerce с AmoCRM с автоматической синхронизацией заказов, контактов и сделок. Разработано Сергеем Солошенко - специалистом по WordPress/WooCommerce с 2018 года.
 * Version: 3.1
 * Author: Сергей Солошенко (RuCoder)
 * Author URI: https://рукодер.рф
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-amocrm-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 * Network: false
 * 
 * Разработчик: Сергей Солошенко | РуКодер
 * Специализация: Веб-разработка с 2018 года | WordPress / Full Stack
 * Принцип работы: "Сайт как для себя"
 * Контакты: 
 * - Телефон/WhatsApp: +7 (985) 985-53-97
 * - Email: support@рукодер.рф
 * - Telegram: @RussCoder
 * - Портфолио: https://рукодер.рф
 * - GitHub: https://github.com/RuCoder-sudo
 */

// Предотвращение прямого доступа
if (!defined('ABSPATH')) {
    exit;
}

// Константы плагина
define('RUCODER_AMOCRM_VERSION', '1.0');
define('RUCODER_AMOCRM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RUCODER_AMOCRM_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Активация плагина
register_activation_hook(__FILE__, 'rucoder_amocrm_activate');
function rucoder_amocrm_activate() {
    global $wpdb;
    
    // Создание таблицы логов
    $table_name = $wpdb->prefix . 'rucoder_amocrm_logs';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        type varchar(50) NOT NULL,
        source varchar(100) NOT NULL,
        status varchar(20) NOT NULL,
        message text NOT NULL,
        amocrm_id varchar(50),
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Установка значений по умолчанию
    add_option('rucoder_amocrm_enabled', '1');
    add_option('rucoder_amocrm_subdomain', '');
    add_option('rucoder_amocrm_pipeline_id', '');
    add_option('rucoder_amocrm_tag_name', 'консультация сайта');
    add_option('rucoder_amocrm_create_task', '1');
    add_option('rucoder_amocrm_task_text', 'Дать оценку стоимости и перезвонить клиенту!');
    add_option('rucoder_amocrm_task_hours', '24');
}

// Деактивация плагина
register_deactivation_hook(__FILE__, 'rucoder_amocrm_deactivate');
function rucoder_amocrm_deactivate() {
    // Очистка кэша
    wp_cache_flush();
}

// Добавление меню в админку
add_action('admin_menu', 'rucoder_amocrm_admin_menu');
function rucoder_amocrm_admin_menu() {
    add_options_page(
        'AmoCRM Integration Elementor RuCoder',
        'RuCoder AmoCRM',
        'manage_options',
        'rucoder-amocrm',
        'rucoder_amocrm_admin_page'
    );
}

// Страница настроек в админке
function rucoder_amocrm_admin_page() {
    include plugin_dir_path(__FILE__) . 'admin-page-clean.php';
}

// Функция логирования
function rucoder_amocrm_log($type, $source, $status, $message, $amocrm_id = null) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rucoder_amocrm_logs';
    
    $wpdb->insert(
        $table_name,
        array(
            'type' => $type,
            'source' => $source,
            'status' => $status,
            'message' => $message,
            'amocrm_id' => $amocrm_id
        ),
        array('%s', '%s', '%s', '%s', '%s')
    );
}

// Отображение логов
function rucoder_amocrm_display_logs() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rucoder_amocrm_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY date DESC LIMIT 50");
    
    if (empty($logs)) {
        echo '<p>Логи отсутствуют</p>';
        return;
    }
    
    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr><th>Дата</th><th>Тип</th><th>Источник</th><th>Статус</th><th>Сообщение</th><th>AmoCRM ID</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($logs as $log) {
        $status_class = '';
        switch ($log->status) {
            case 'success':
                $status_class = 'style="color: green;"';
                break;
            case 'error':
                $status_class = 'style="color: red;"';
                break;
            case 'warning':
                $status_class = 'style="color: orange;"';
                break;
        }
        
        echo '<tr>';
        echo '<td>' . esc_html($log->date) . '</td>';
        echo '<td>' . esc_html($log->type) . '</td>';
        echo '<td>' . esc_html($log->source) . '</td>';
        echo '<td ' . $status_class . '>' . esc_html(ucfirst($log->status)) . '</td>';
        echo '<td>' . esc_html(substr($log->message, 0, 100)) . (strlen($log->message) > 100 ? '...' : '') . '</td>';
        echo '<td>' . esc_html($log->amocrm_id) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}

// Получение статистики
function rucoder_amocrm_get_stats_count() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rucoder_amocrm_logs';
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'success'");
    
    return intval($count);
}

// API запрос к AmoCRM
function rucoder_amocrm_api_request($method, $endpoint, $data = null) {
    $subdomain = get_option('rucoder_amocrm_subdomain');
    $token = get_option('rucoder_amocrm_token');
    
    if (empty($subdomain) || empty($token)) {
        throw new Exception('Не настроены поддомен или токен AmoCRM');
    }
    
    $url = "https://{$subdomain}/api/v4{$endpoint}";
    
    $args = array(
        'method' => $method,
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json'
        ),
        'timeout' => 30
    );
    
    if ($data && ($method === 'POST' || $method === 'PATCH')) {
        $args['body'] = json_encode($data);
    }
    
    rucoder_amocrm_log('api', 'request', 'info', "Отправка {$method} запроса: {$url}");
    
    $response = wp_remote_request($url, $args);
    
    if (is_wp_error($response)) {
        $error_msg = $response->get_error_message();
        rucoder_amocrm_log('api', 'response', 'error', "Ошибка запроса: {$error_msg}");
        throw new Exception($error_msg);
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    rucoder_amocrm_log('api', 'response', 'info', "Ответ API ({$status_code}): " . substr($body, 0, 200));
    
    if ($status_code >= 400) {
        rucoder_amocrm_log('api', 'response', 'error', "AmoCRM API ошибка ({$status_code}): {$body}");
        throw new Exception("AmoCRM API ошибка ({$status_code}): {$body}");
    }
    
    $result = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $error_msg = 'Ошибка JSON: ' . json_last_error_msg();
        rucoder_amocrm_log('api', 'response', 'error', $error_msg);
        throw new Exception($error_msg);
    }
    
    return $result;
}

// Создание контакта и сделки в AmoCRM
function rucoder_amocrm_create_lead($contact_name, $contact_phone = '', $contact_email = '', $source = 'Сайт', $additional_data = array()) {
    if (get_option('rucoder_amocrm_enabled') !== '1') {
        return false;
    }
    
    try {
        rucoder_amocrm_log('lead', $source, 'info', "Начало создания заявки для: {$contact_name}");
        
        // 1. Создание контакта
        $contact_data = array('name' => $contact_name);
        
        $custom_fields = array();
        
        if (!empty($contact_phone)) {
            $custom_fields[] = array(
                'field_code' => 'PHONE',
                'values' => array(array('value' => $contact_phone, 'enum_code' => 'WORK'))
            );
        }
        
        if (!empty($contact_email)) {
            $custom_fields[] = array(
                'field_code' => 'EMAIL',
                'values' => array(array('value' => $contact_email, 'enum_code' => 'WORK'))
            );
        }
        
        if (!empty($custom_fields)) {
            $contact_data['custom_fields_values'] = $custom_fields;
        }
        
        $contacts_response = rucoder_amocrm_api_request('POST', '/contacts', array($contact_data));
        
        if (!isset($contacts_response['_embedded']['contacts'][0]['id'])) {
            throw new Exception('Не удалось создать контакт');
        }
        
        $contact_id = $contacts_response['_embedded']['contacts'][0]['id'];
        rucoder_amocrm_log('contact', $source, 'success', "Контакт создан с ID: {$contact_id}");
        
        // 2. Создание сделки
        $pipeline_id = get_option('rucoder_amocrm_pipeline_id');
        $status_id = get_option('rucoder_amocrm_status_id');
        
        $lead_name = "Заявка с сайта - {$contact_name}";
        if (!empty($additional_data['form_name'])) {
            $lead_name .= " ({$additional_data['form_name']})";
        }
        
        $lead_data = array(
            'name' => $lead_name,
            'price' => 0,
            '_embedded' => array(
                'contacts' => array(
                    array('id' => $contact_id)
                )
            )
        );
        
        if (!empty($pipeline_id)) {
            $lead_data['pipeline_id'] = intval($pipeline_id);
        }
        if (!empty($status_id)) {
            $lead_data['status_id'] = intval($status_id);
        }
        
        $leads_response = rucoder_amocrm_api_request('POST', '/leads', array($lead_data));
        
        if (!isset($leads_response['_embedded']['leads'][0]['id'])) {
            throw new Exception('Не удалось создать сделку');
        }
        
        $lead_id = $leads_response['_embedded']['leads'][0]['id'];
        rucoder_amocrm_log('lead', $source, 'success', "Сделка создана с ID: {$lead_id}");
        
        // 3. Добавление тега через правильный API метод
        $tag_name = get_option('rucoder_amocrm_tag_name', 'консультация сайта');
        
        if (!empty($tag_name)) {
            // Добавляем тег сразу при создании сделки через _embedded
            try {
                $lead_with_tag = array(
                    '_embedded' => array(
                        'tags' => array(
                            array('name' => $tag_name)
                        )
                    )
                );
                
                rucoder_amocrm_api_request('PATCH', "/leads/{$lead_id}", $lead_with_tag);
                rucoder_amocrm_log('tag', $source, 'success', "Тег '{$tag_name}' успешно добавлен к сделке {$lead_id}");
            } catch (Exception $e) {
                rucoder_amocrm_log('tag', $source, 'warning', "Не удалось добавить тег: " . $e->getMessage());
            }
        }
        
        // 4. Создание задачи (если включено)
        if (get_option('rucoder_amocrm_create_task') === '1') {
            try {
                $task_text = get_option('rucoder_amocrm_task_text', 'Дать оценку стоимости и перезвонить клиенту!');
                $task_hours = intval(get_option('rucoder_amocrm_task_hours', '24'));
                
                // Заменяем переменные в тексте задачи
                $task_text = str_replace(
                    array('{{contact_name}}', '{{phone}}', '{{source}}'),
                    array($contact_name, $contact_phone, $source),
                    $task_text
                );
                
                $task_data = array(
                    'text' => $task_text,
                    'complete_till' => time() + ($task_hours * 3600), // Unix timestamp
                    'entity_id' => intval($lead_id),
                    'entity_type' => 'leads',
                    'task_type_id' => 1, // Звонок
                    'created_by' => 0,
                    'responsible_user_id' => 0 // Ответственный будет назначен автоматически
                );
                
                $task_response = rucoder_amocrm_api_request('POST', '/tasks', array($task_data));
                
                if (isset($task_response['_embedded']['tasks'][0]['id'])) {
                    $task_id = $task_response['_embedded']['tasks'][0]['id'];
                    rucoder_amocrm_log('task', $source, 'success', "Задача создана с ID: {$task_id} для сделки {$lead_id}");
                }
            } catch (Exception $e) {
                rucoder_amocrm_log('task', $source, 'warning', "Не удалось создать задачу: " . $e->getMessage());
            }
        }
        
        rucoder_amocrm_log('lead', $source, 'success', "Заявка успешно обработана. Сделка ID: {$lead_id}, Контакт ID: {$contact_id}");
        return $lead_id;
        
    } catch (Exception $e) {
        rucoder_amocrm_log('lead', $source, 'error', "Ошибка создания заявки: " . $e->getMessage());
        return false;
    }
}

// AJAX тест подключения
function rucoder_amocrm_ajax_test() {
    try {
        $account = rucoder_amocrm_api_request('GET', '/account');
        if (isset($account['name'])) {
            wp_send_json_success("Подключение успешно! Аккаунт: " . $account['name']);
        } else {
            wp_send_json_error('Неверный ответ API');
        }
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

// AJAX создание тестовой заявки
function rucoder_amocrm_ajax_test_lead() {
    try {
        $test_name = 'Тест ' . date('H:i:s');
        $test_phone = '+7 (999) 123-45-67';
        $test_email = 'test@example.com';
        
        $lead_id = rucoder_amocrm_create_lead(
            $test_name,
            $test_phone,
            $test_email,
            'Тест админки',
            array('form_name' => 'Тестовая форма')
        );
        
        if ($lead_id) {
            wp_send_json_success("Тестовая заявка создана! ID сделки: {$lead_id}");
        } else {
            wp_send_json_error('Не удалось создать тестовую заявку');
        }
    } catch (Exception $e) {
        wp_send_json_error($e->getMessage());
    }
}

// AJAX очистка логов
function rucoder_amocrm_ajax_clear_logs() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'rucoder_amocrm_logs';
    $wpdb->query("DELETE FROM $table_name");
    
    wp_send_json_success('Логи очищены');
}

// Интеграция с Elementor Pro Forms
add_action('elementor_pro/forms/new_record', 'rucoder_amocrm_elementor_form_handler', 10, 2);
function rucoder_amocrm_elementor_form_handler($record, $handler) {
    $form_name = $record->get_form_settings('form_name');
    $raw_fields = $record->get('fields');
    
    if (empty($raw_fields)) {
        return;
    }
    
    $fields = array();
    foreach ($raw_fields as $id => $field) {
        $fields[$id] = $field['value'];
    }
    
    // Извлекаем поля по точным ID из форм Elementor
    $name = $fields['name'] ?? '';
    $phone = $fields['tel'] ?? '';
    $email = $fields['email'] ?? '';
    $company = $fields['comaniya'] ?? '';
    $message = $fields['message'] ?? '';
    
    // Определяем источник формы
    $form_id = $record->get_form_settings('form_id');
    $form_source = 'Elementor Pro';
    if ($form_id === 'formhome') {
        $form_source = 'Главная страница';
    } elseif ($form_id === 'formpupe') {
        $form_source = 'Popup форма';
    }
    
    // Если имя не заполнено, используем компанию или создаем имя
    if (empty($name)) {
        if (!empty($company)) {
            $name = $company;
        } elseif (!empty($phone)) {
            $name = 'Клиент ' . substr($phone, -4);
        } elseif (!empty($email)) {
            $name = 'Клиент ' . substr($email, 0, strpos($email, '@'));
        } else {
            $name = 'Посетитель сайта';
        }
    }
    
    $additional_data = array(
        'form_name' => $form_source,
        'Компания' => $company,
        'Сообщение' => $message,
        'Form ID' => $form_id
    );
    
    // Добавляем все остальные поля как дополнительные
    foreach ($fields as $key => $value) {
        if (!in_array($key, ['name', 'tel', 'email', 'comaniya', 'message']) && !empty($value)) {
            $additional_data[$key] = $value;
        }
    }
    
    rucoder_amocrm_create_lead($name, $phone, $email, $form_source, $additional_data);
}

// Интеграция с кнопкой "Связаться с нами" 
add_action('wp_ajax_nopriv_rucoder_contact_button', 'rucoder_amocrm_contact_button_handler');
add_action('wp_ajax_rucoder_contact_button', 'rucoder_amocrm_contact_button_handler');
function rucoder_amocrm_contact_button_handler() {
    if (isset($_POST['name']) || isset($_POST['phone'])) {
        $name = sanitize_text_field($_POST['name'] ?? '');
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $email = sanitize_text_field($_POST['email'] ?? '');
        
        if (empty($name) && !empty($phone)) {
            $name = 'Обратный звонок ' . substr($phone, -4);
        } elseif (empty($name)) {
            $name = 'Обратный звонок';
        }
        
        $additional_data = array(
            'form_name' => 'Кнопка связи',
            'Источник' => 'Contact Us Button'
        );
        
        rucoder_amocrm_create_lead($name, $phone, $email, 'Кнопка связи', $additional_data);
        
        wp_send_json_success('Заявка отправлена');
    } else {
        wp_send_json_error('Не заполнены обязательные поля');
    }
}

// Подключение JavaScript для интеграции
add_action('wp_enqueue_scripts', 'rucoder_amocrm_enqueue_scripts');
function rucoder_amocrm_enqueue_scripts() {
    wp_enqueue_script(
        'rucoder-amocrm-integration',
        RUCODER_AMOCRM_PLUGIN_URL . 'quizle-integration.js',
        array(),
        RUCODER_AMOCRM_VERSION,
        true
    );
    
    wp_localize_script('rucoder-amocrm-integration', 'rucoder_ajax', array(
        'url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('rucoder_amocrm_nonce')
    ));
}

// Регистрация AJAX обработчиков
add_action('wp_ajax_rucoder_amocrm_test', 'rucoder_amocrm_ajax_test');
add_action('wp_ajax_rucoder_amocrm_test_lead', 'rucoder_amocrm_ajax_test_lead');
add_action('wp_ajax_rucoder_amocrm_clear_logs', 'rucoder_amocrm_ajax_clear_logs');

?>