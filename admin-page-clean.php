<?php
// Обработка сохранения настроек
if (isset($_POST['save_settings'])) {
    global $wpdb;
    
    // Получаем новый токен
    $new_token = trim(sanitize_text_field($_POST['token']));
    
    if (!empty($new_token)) {
        // Сначала полностью удаляем все записи токена
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name = 'rucoder_amocrm_token'");
        
        // Очищаем кэш перед добавлением
        wp_cache_delete('rucoder_amocrm_token', 'options');
        
        // Добавляем новый токен через WordPress API
        update_option('rucoder_amocrm_token', $new_token, false);
        
        // Дополнительная проверка через прямую вставку если update_option не сработал
        $check = get_option('rucoder_amocrm_token');
        if ($check !== $new_token) {
            $wpdb->insert(
                $wpdb->options,
                array(
                    'option_name' => 'rucoder_amocrm_token',
                    'option_value' => $new_token,
                    'autoload' => 'no'
                ),
                array('%s', '%s', '%s')
            );
        }
    }
    
    update_option('rucoder_amocrm_subdomain', sanitize_text_field($_POST['subdomain']));
    update_option('rucoder_amocrm_pipeline_id', sanitize_text_field($_POST['pipeline_id']));
    update_option('rucoder_amocrm_status_id', sanitize_text_field($_POST['status_id']));
    update_option('rucoder_amocrm_tag_name', sanitize_text_field($_POST['tag_name']));
    update_option('rucoder_amocrm_enabled', isset($_POST['enabled']) ? '1' : '0');
    update_option('rucoder_amocrm_create_task', isset($_POST['create_task']) ? '1' : '0');
    update_option('rucoder_amocrm_task_text', sanitize_textarea_field($_POST['task_text']));
    update_option('rucoder_amocrm_task_hours', sanitize_text_field($_POST['task_hours']));
    
    // Очищаем кэш
    wp_cache_flush();
    
    // Проверяем что токен действительно сохранился
    $saved_token = get_option('rucoder_amocrm_token');
    if (!empty($new_token) && $saved_token === $new_token) {
        echo '<div class="notice notice-success"><p>✅ Настройки сохранены! Новый токен успешно установлен. Длина: ' . strlen($new_token) . ' символов</p></div>';
    } elseif (!empty($new_token)) {
        echo '<div class="notice notice-warning"><p>⚠️ Токен сохранен, но возможны проблемы с кэшем. Проверьте подключение.</p></div>';
    } else {
        echo '<div class="notice notice-success"><p>✅ Остальные настройки сохранены.</p></div>';
    }
}

$subdomain = get_option('rucoder_amocrm_subdomain', '');
$token = get_option('rucoder_amocrm_token', '');
$pipeline_id = get_option('rucoder_amocrm_pipeline_id', '');
$status_id = get_option('rucoder_amocrm_status_id', '');
$tag_name = get_option('rucoder_amocrm_tag_name', 'консультация сайта');
$enabled = get_option('rucoder_amocrm_enabled', '1');
$create_task = get_option('rucoder_amocrm_create_task', '1');
$task_text = get_option('rucoder_amocrm_task_text', 'Дать оценку стоимости и перезвонить клиенту!');
$task_hours = get_option('rucoder_amocrm_task_hours', '24');
?>

<style>
    .rucoder-admin-wrap {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin: 20px 20px 20px 0;
        max-width: 1200px;
    }
    .rucoder-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 30px;
        border-radius: 8px 8px 0 0;
        margin: 0;
    }
    .rucoder-header h1 {
        margin: 0;
        font-size: 24px;
        font-weight: 600;
        color: white;
    }
    .rucoder-content {
        padding: 30px;
    }
    .rucoder-form-section {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 25px;
        margin: 20px 0;
        border-left: 4px solid #667eea;
    }
    .rucoder-form-section h3 {
        margin-top: 0;
        color: #2d3748;
        font-size: 16px;
    }
    .rucoder-form-table th {
        font-weight: 600;
        color: #333;
        width: 220px;
        padding: 15px 10px 15px 0;
        vertical-align: top;
    }
    .rucoder-form-table td {
        padding: 15px 0;
    }
    .rucoder-form-table input[type="text"], 
    .rucoder-form-table input[type="number"] {
        width: 100%;
        max-width: 450px;
        padding: 10px 15px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    .rucoder-form-table input[type="text"]:focus,
    .rucoder-form-table input[type="number"]:focus {
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .rucoder-form-table input[type="checkbox"] {
        transform: scale(1.3);
        margin-right: 10px;
        accent-color: #667eea;
    }
    .rucoder-button {
        background: #667eea;
        color: white;
        border: none;
        padding: 12px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        margin-right: 10px;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .rucoder-button:hover {
        background: #5a67d8;
        transform: translateY(-1px);
    }
    .rucoder-button.secondary {
        background: #718096;
    }
    .rucoder-button.secondary:hover {
        background: #4a5568;
    }
    .rucoder-test-section {
        background: #f7fafc;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 25px;
        margin: 30px 0;
    }
    .rucoder-test-section h3 {
        margin-top: 0;
        color: #2d3748;
    }
    .rucoder-logs-section {
        margin-top: 40px;
        padding: 25px;
        background: #f7fafc;
        border-radius: 8px;
    }
    .rucoder-stats {
        background: linear-gradient(135deg, #edf2f7 0%, #e2e8f0 100%);
        padding: 20px;
        border-radius: 8px;
        margin: 20px 0;
        font-size: 16px;
        font-weight: 600;
        color: #2d3748;
        border-left: 4px solid #38b2ac;
    }
    .description {
        color: #666;
        font-size: 13px;
        margin-top: 8px;
        line-height: 1.4;
    }
    .rucoder-token-info {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 6px;
        padding: 15px;
        margin: 10px 0;
        color: #856404;
    }
    .rucoder-token-info strong {
        color: #6c5200;
    }
    .rucoder-important-note {
        background: #e8f5e8;
        border: 2px solid #4caf50;
        border-radius: 6px;
        padding: 20px;
        margin: 20px 0;
        color: #2e7d32;
    }
</style>

<div class="rucoder-admin-wrap">
    <div class="rucoder-header">
        <h1>AmoCRM Integration Elementor RuCoder</h1>
        <p style="margin: 10px 0 0 0; opacity: 0.9;">Интеграция форм сайта с AmoCRM</p>
    </div>
    
    <div class="rucoder-content">
        <form method="post" action="">
            <div class="rucoder-form-section">
                <h3>Настройки подключения к AmoCRM</h3>
                
                <table class="form-table rucoder-form-table">
                    <tr>
                        <th scope="row">Поддомен AmoCRM</th>
                        <td>
                            <input type="text" name="subdomain" value="<?php echo esc_attr($subdomain); ?>" placeholder="example.amocrm.ru" />
                            <p class="description">Введите поддомен вашего AmoCRM аккаунта (без https://)</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Токен доступа</th>
                        <td>
                            <input type="text" name="token" id="token-field" value="" placeholder="Введите новый долгосрочный токен здесь" style="width: 500px; font-family: monospace;" />
                            <button type="button" onclick="document.getElementById('token-field').value=''; document.getElementById('token-field').focus();" style="margin-left: 10px; padding: 5px 10px;">Очистить</button>
                            <br><small style="color: #666;">
                                Текущий в базе: <?php echo $token ? substr($token, 0, 10) . '...' . substr($token, -5) . ' (' . strlen($token) . ' символов)' : 'не установлен'; ?>
                            </small>
                            
                            <div class="rucoder-token-info">
                                <strong>Из созданной интеграции используйте:</strong><br>
                                ✅ <strong>Долгосрочный токен</strong> - именно он нужен для плагина<br>
                                ❌ Секретный ключ - не используется<br>
                                ❌ ID интеграции - не используется<br>
                                ❌ Код авторизации - устаревает через 20 минут
                            </div>
                            
                            <p class="description">
                                <strong>Инструкция:</strong><br>
                                1. Откройте AmoCRM: Настройки → Интеграции → API и Webhooks<br>
                                2. Создайте или найдите интеграцию<br>
                                3. Скопируйте <strong>Долгосрочный токен</strong><br>
                                4. Вставьте в поле выше и сохраните настройки
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="rucoder-form-section">
                <h3>Настройки воронки "Анкеры"</h3>
                
                <table class="form-table rucoder-form-table">
                    <tr>
                        <th scope="row">ID воронки (Анкеры)</th>
                        <td>
                            <input type="text" name="pipeline_id" value="<?php echo esc_attr($pipeline_id); ?>" placeholder="Например: 1234567" />
                            <p class="description">ID воронки в AmoCRM<br>
                            Ссылка: https://your-domain.amocrm.ru/leads/pipeline/1234567/</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">ID этапа</th>
                        <td>
                            <input type="text" name="status_id" value="<?php echo esc_attr($status_id); ?>" placeholder="Введите ID этапа" />
                            <p class="description">ID этапа "Получено новое обращение" в воронке "Анкеры"<br>
                            Найдите в AmoCRM: Настройки → Воронки и статусы → Анкеры</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="rucoder-important-note">
                <strong>Важно:</strong> Все новые заявки с сайта попадают в воронку "Анкеры" на этап "Получено новое обращение" с тегом "консультация сайта"
            </div>
            
            <div class="rucoder-form-section">
                <h3>Настройки обработки заявок</h3>
                
                <table class="form-table rucoder-form-table">
                    <tr>
                        <th scope="row">Тег для заявок</th>
                        <td>
                            <input type="text" name="tag_name" value="<?php echo esc_attr($tag_name); ?>" placeholder="консультация сайта" />
                            <p class="description">Тег, который будет добавляться ко всем заявкам с сайта</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Включить интеграцию</th>
                        <td>
                            <input type="checkbox" name="enabled" value="1" <?php checked($enabled, '1'); ?> />
                            <span style="font-weight: 500;">Активировать обработку форм и отправку в AmoCRM</span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="rucoder-form-section">
                <h3>Настройки задач</h3>
                
                <table class="form-table rucoder-form-table">
                    <tr>
                        <th scope="row">Создавать задачи</th>
                        <td>
                            <input type="checkbox" name="create_task" value="1" <?php checked($create_task, '1'); ?> />
                            <span style="font-weight: 500;">Автоматически создавать задачи к новым сделкам</span>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Текст задачи</th>
                        <td>
                            <input type="text" name="task_text" value="<?php echo esc_attr($task_text); ?>" placeholder="Дать оценку стоимости и перезвонить клиенту!" />
                            <p class="description">Текст задачи. Доступные переменные: {{contact_name}}, {{phone}}, {{source}}</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">Срок выполнения (часов)</th>
                        <td>
                            <input type="number" name="task_hours" value="<?php echo esc_attr($task_hours); ?>" min="1" max="168" placeholder="24" />
                            <p class="description">Через сколько часов должна быть выполнена задача</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div style="text-align: center; margin: 30px 0;">
                <input type="submit" name="save_settings" value="Сохранить настройки" class="rucoder-button" onclick="return validateForm()" />
            </div>
            
            <script>
            function validateForm() {
                var token = document.getElementById('token-field').value;
                if (token.length > 0 && token.length < 20) {
                    alert('Токен слишком короткий. Долгосрочный токен AmoCRM обычно содержит более 50 символов.');
                    return false;
                }
                if (token.length > 0) {
                    return confirm('Сохранить новый токен длиной ' + token.length + ' символов?');
                }
                return confirm('Сохранить настройки без изменения токена?');
            }
            </script>
        </form>
        
        <div class="rucoder-test-section">
            <h3>Тестирование подключения</h3>
            <p>Проверьте работу интеграции перед использованием:</p>
            
            <button type="button" id="test-connection" class="rucoder-button">Проверить подключение</button>
            <button type="button" id="test-lead" class="rucoder-button secondary">Создать тестовую заявку</button>
            
            <div id="test-results" style="margin-top: 20px;"></div>
        </div>
        
        <div class="rucoder-stats">
            Статистика интеграций<br>
            Всего обработано заявок: <?php echo rucoder_amocrm_get_stats_count(); ?>
        </div>
        
        <div class="rucoder-logs-section">
            <h2 style="margin-top: 0;">Логи 
                <button type="button" id="clear-logs" class="rucoder-button secondary" style="font-size: 12px; padding: 8px 16px;">Очистить логи</button>
            </h2>
            <?php rucoder_amocrm_display_logs(); ?>
        </div>
    </div>
</div>

<?php if (!empty($token) && !empty($subdomain)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var testButton = document.getElementById('test-connection');
    var testLeadButton = document.getElementById('test-lead');
    var clearButton = document.getElementById('clear-logs');
    var resultsDiv = document.getElementById('test-results');
    
    if (testButton) {
        testButton.addEventListener('click', function() {
            resultsDiv.innerHTML = '<p>Проверка подключения...</p>';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        resultsDiv.innerHTML = '<div class="notice notice-success"><p>✓ API работает! ' + response.data + '</p></div>';
                    } else {
                        resultsDiv.innerHTML = '<div class="notice notice-error"><p>✗ Ошибка: ' + response.data + '</p></div>';
                    }
                }
            };
            xhr.send('action=rucoder_amocrm_test');
        });
    }
    
    if (testLeadButton) {
        testLeadButton.addEventListener('click', function() {
            resultsDiv.innerHTML = '<p>Создание тестовой заявки...</p>';
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        resultsDiv.innerHTML = '<div class="notice notice-success"><p>✓ Тестовая заявка создана! ' + response.data + '</p></div>';
                    } else {
                        resultsDiv.innerHTML = '<div class="notice notice-error"><p>✗ Ошибка: ' + response.data + '</p></div>';
                    }
                }
            };
            xhr.send('action=rucoder_amocrm_test_lead');
        });
    }
    
    if (clearButton) {
        clearButton.addEventListener('click', function() {
            if (confirm('Удалить все логи?')) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', ajaxurl);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    if (xhr.status === 200) {
                        location.reload();
                    }
                };
                xhr.send('action=rucoder_amocrm_clear_logs');
            }
        });
    }
    

});
</script>
<?php endif; ?>