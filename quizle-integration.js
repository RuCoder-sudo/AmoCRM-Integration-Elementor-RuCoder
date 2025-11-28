/**
 * Интеграция Quizle с AmoCRM
 * Отслеживает завершение квиза и отправляет данные в AmoCRM
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Сбор UTM параметров и других данных
    function collectTrackingData() {
        var urlParams = new URLSearchParams(window.location.search);
        var trackingData = {};
        
        // UTM параметры
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function(param) {
            var value = urlParams.get(param);
            if (value) trackingData[param] = value;
        });
        
        // Roistat visit
        if (window.roistat && window.roistat.visit) {
            trackingData.roistat_visit = window.roistat.visit;
        }
        
        // Google Analytics Client ID
        if (window.ga && window.ga.getAll) {
            var tracker = window.ga.getAll()[0];
            if (tracker) trackingData.ga_client_id = tracker.get('clientId');
        }
        
        // Google Analytics 4
        if (window.gtag) {
            window.gtag('get', 'GA_MEASUREMENT_ID', 'client_id', function(clientId) {
                trackingData.ga4_client_id = clientId;
            });
        }
        
        // Facebook pixel
        if (window._fbp) trackingData.fbp = window._fbp;
        if (window._fbc) trackingData.fbc = window._fbc;
        
        // Yandex Metrica
        if (window.ym && window.ym.getClientID) {
            trackingData.yandex_client_id = window.ym.getClientID();
        }
        
        // Базовые данные
        trackingData.page_url = window.location.href;
        trackingData.user_agent = navigator.userAgent;
        trackingData.referrer = document.referrer;
        
        return trackingData;
    }
    
    // Отслеживание событий Quizle через официальные идентификаторы
    function setupQuizleEventListeners() {
        var quizData = {
            answers: {},
            currentStep: 0,
            trackingData: collectTrackingData()
        };
        
        // quizle:start — Срабатывает при первом взаимодействии с квизом
        document.addEventListener('quizle:start', function(e) {
            console.log('RuCoder AmoCRM: Квиз начат');
            quizData.started = true;
            quizData.start_time = new Date().toISOString();
        });
        
        // quizle:next — Срабатывает по кнопке "Дальше"
        document.addEventListener('quizle:next', function(e) {
            quizData.currentStep++;
            collectCurrentAnswer(e);
            console.log('RuCoder AmoCRM: Переход к следующему вопросу', quizData.currentStep);
        });
        
        // quizle:prev — Срабатывает по кнопке "Назад"
        document.addEventListener('quizle:prev', function(e) {
            quizData.currentStep--;
            console.log('RuCoder AmoCRM: Возврат к предыдущему вопросу', quizData.currentStep);
        });
        
        // quizle:progress — Срабатывает при изменении прогресса
        document.addEventListener('quizle:progress', function(e) {
            if (e.detail) {
                quizData.progress = e.detail.progress || e.detail;
            }
        });
        
        // quizle:show_results — Срабатывает при показе результатов
        document.addEventListener('quizle:show_results', function(e) {
            console.log('RuCoder AmoCRM: Показ результатов квиза');
            if (e.detail) {
                quizData.results = e.detail;
            }
            quizData.completed = true;
        });
        
        // quizle:show_contacts — Срабатывает при показе контактной формы
        document.addEventListener('quizle:show_contacts', function(e) {
            console.log('RuCoder AmoCRM: Показ контактной формы');
            quizData.contacts_shown = true;
        });
        
        // quizle:submit_contacts — Срабатывает при отправке контактных данных
        document.addEventListener('quizle:submit_contacts', function(e) {
            console.log('RuCoder AmoCRM: Отправка контактных данных');
            
            if (e.detail) {
                // Извлекаем контактные данные из события
                quizData.name = e.detail.name || e.detail.contact_name || '';
                quizData.phone = e.detail.phone || e.detail.contact_phone || '';
                quizData.email = e.detail.email || e.detail.contact_email || '';
            }
            
            // Если данных в событии нет, собираем из формы
            if (!quizData.name || !quizData.phone) {
                collectContactsFromForm();
            }
            
            sendQuizDataToAmoCRM(quizData);
        });
        
        // quizle:finish — Срабатывает при отправке последнего вопроса
        document.addEventListener('quizle:finish', function(e) {
            console.log('RuCoder AmoCRM: Квиз завершен');
            collectCurrentAnswer(e);
            quizData.finished = true;
            quizData.finish_time = new Date().toISOString();
            
            // Если контакты еще не отправлены, ждем события submit_contacts
            if (!quizData.contacts_submitted) {
                setTimeout(function() {
                    if (!quizData.contacts_submitted) {
                        // Пытаемся собрать контакты самостоятельно
                        collectContactsFromForm();
                        if (quizData.name || quizData.phone || quizData.email) {
                            sendQuizDataToAmoCRM(quizData);
                        }
                    }
                }, 3000);
            }
        });
        
        function collectCurrentAnswer(e) {
            // Собираем ответ с текущего шага
            var activeQuestion = document.querySelector('.quizle-question.active, .quiz-step.active, [class*="current"]');
            if (activeQuestion) {
                var questionText = activeQuestion.querySelector('h1, h2, h3, h4, h5, h6, .question-title, [class*="title"]');
                var selectedInputs = activeQuestion.querySelectorAll('input:checked, select option:selected');
                
                if (questionText && selectedInputs.length > 0) {
                    var answers = [];
                    selectedInputs.forEach(function(input) {
                        var value = input.value || input.textContent || input.innerText;
                        if (value && value.trim()) {
                            answers.push(value.trim());
                        }
                    });
                    
                    if (answers.length > 0) {
                        quizData.answers[questionText.textContent.trim()] = answers.join(', ');
                    }
                }
            }
            
            // Также пытаемся собрать из event detail
            if (e.detail) {
                Object.assign(quizData.answers, e.detail.answers || {});
            }
        }
        
        function collectContactsFromForm() {
            // Ищем форму контактов
            var contactForms = document.querySelectorAll('form, .contact-form, .quiz-contacts, [class*="contact"]');
            
            contactForms.forEach(function(form) {
                var inputs = form.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"]');
                
                inputs.forEach(function(input) {
                    var name = input.name || input.id || input.placeholder || '';
                    var value = input.value.trim();
                    
                    if (value) {
                        if (name.includes('name') || name.includes('имя') || input.type === 'text') {
                            quizData.name = quizData.name || value;
                        } else if (name.includes('phone') || name.includes('телефон') || input.type === 'tel') {
                            quizData.phone = quizData.phone || value;
                        } else if (name.includes('email') || name.includes('почта') || input.type === 'email') {
                            quizData.email = quizData.email || value;
                        }
                    }
                });
            });
        }
        
        function sendQuizDataToAmoCRM(data) {
            if (data.contacts_submitted) return; // Уже отправлено
            
            data.contacts_submitted = true;
            
            console.log('RuCoder AmoCRM: Отправка данных квиза', data);
            
            if (!data.name && !data.phone && !data.email) {
                console.log('RuCoder AmoCRM: Недостаточно контактных данных');
                return;
            }
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', rucoder_ajax.url);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    console.log('RuCoder AmoCRM: Данные квиза успешно отправлены в AmoCRM');
                } else {
                    console.error('RuCoder AmoCRM: Ошибка отправки данных квиза');
                }
            };
            
            var params = 'action=rucoder_amocrm_quizle_advanced&' +
                        'name=' + encodeURIComponent(data.name || '') +
                        '&phone=' + encodeURIComponent(data.phone || '') +
                        '&email=' + encodeURIComponent(data.email || '') +
                        '&quiz_data=' + encodeURIComponent(JSON.stringify(data));
            
            xhr.send(params);
        }
    }
    
    // Отслеживание завершения квиза Quizle (резервный метод)
    function handleQuizleSubmission() {
        // Проверяем наличие любого квиза
        var quizContainer = document.querySelector('[data-quiz-id]');
        var quizShortcode = document.querySelector('[id*="quizle-"]');
        
        if (quizContainer || quizShortcode) {
            console.log('RuCoder AmoCRM: Квиз найден на странице');
            
            // Отслеживаем отправку форм
            document.addEventListener('submit', function(e) {
                var form = e.target;
                var isQuizForm = form.closest('[data-quiz-id]') || 
                               form.closest('[id*="quizle"]') ||
                               form.querySelector('input[name*="quiz"]');
                
                if (isQuizForm) {
                    handleQuizFormSubmit(form);
                }
            });
            
            // Отслеживаем AJAX отправки
            var originalFetch = window.fetch;
            window.fetch = function() {
                return originalFetch.apply(this, arguments).then(function(response) {
                    if (response.url.includes('quizle') || response.url.includes('quiz')) {
                        interceptQuizleResponse(response);
                    }
                    return response;
                });
            };
        }
    }
    
    function handleQuizFormSubmit(form) {
        setTimeout(function() {
            var formData = new FormData(form);
            var quizData = {};
            var answers = {};
            
            // Собираем данные формы
            for (var pair of formData.entries()) {
                var key = pair[0];
                var value = pair[1];
                
                if (key.includes('name') || key.includes('имя')) {
                    quizData.name = value;
                } else if (key.includes('phone') || key.includes('телефон')) {
                    quizData.phone = value;
                } else if (key.includes('email') || key.includes('почта')) {
                    quizData.email = value;
                } else if (key.includes('question') || key.includes('answer')) {
                    answers[key] = value;
                } else {
                    answers[key] = value;
                }
            }
            
            // Собираем выбранные ответы из радиокнопок и чекбоксов
            var selectedInputs = form.querySelectorAll('input:checked, select option:selected');
            selectedInputs.forEach(function(input) {
                if (input.type === 'radio' || input.type === 'checkbox') {
                    var question = findQuestionForInput(input);
                    if (question) {
                        answers[question] = input.value || input.nextElementSibling?.textContent || 'Выбрано';
                    }
                } else if (input.tagName === 'OPTION') {
                    var select = input.closest('select');
                    var question = findQuestionForInput(select);
                    if (question) {
                        answers[question] = input.textContent;
                    }
                }
            });
            
            quizData.answers = answers;
            
            // Пытаемся получить ID квиза динамически
            var quizElement = form.closest('[data-quiz-id]');
            quizData.quiz_id = quizElement ? quizElement.dataset.quizId : 'unknown';
            quizData.quiz_name = 'Квиз с сайта';
            
            sendToAmoCRM(quizData);
        }, 1000);
    }
    
    function findQuestionForInput(input) {
        // Ищем вопрос в родительских элементах
        var parent = input.closest('.quiz-question, .quizle-question, [class*="question"]');
        if (parent) {
            var questionText = parent.querySelector('h1, h2, h3, h4, h5, h6, .question-title, [class*="title"]');
            if (questionText) {
                return questionText.textContent.trim();
            }
        }
        
        // Ищем по label
        var label = input.closest('label') || document.querySelector('label[for="' + input.id + '"]');
        if (label) {
            return label.textContent.trim();
        }
        
        // Ищем в предыдущих элементах
        var prev = input.previousElementSibling;
        while (prev) {
            if (prev.textContent && prev.textContent.trim().length > 5) {
                return prev.textContent.trim();
            }
            prev = prev.previousElementSibling;
        }
        
        return input.name || 'Неизвестный вопрос';
    }
    
    function interceptQuizleResponse(response) {
        // Попытка перехватить ответ от Quizle API
        response.clone().json().then(function(data) {
            if (data && (data.success || data.completed)) {
                console.log('RuCoder AmoCRM: Квиз завершен через API');
                // Здесь можно обработать данные из API ответа
            }
        }).catch(function(e) {
            // Не JSON ответ, игнорируем
        });
    }
    
    function sendToAmoCRM(quizData) {
        console.log('RuCoder AmoCRM: Отправка данных квиза', quizData);
        
        if (!quizData.name && !quizData.phone && !quizData.email) {
            console.log('RuCoder AmoCRM: Недостаточно данных для отправки');
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', rucoder_ajax.url);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                console.log('RuCoder AmoCRM: Данные квиза успешно отправлены');
            } else {
                console.error('RuCoder AmoCRM: Ошибка отправки данных квиза');
            }
        };
        
        var params = 'action=rucoder_amocrm_quizle_custom&' +
                    'name=' + encodeURIComponent(quizData.name || '') +
                    '&phone=' + encodeURIComponent(quizData.phone || '') +
                    '&email=' + encodeURIComponent(quizData.email || '') +
                    '&quiz_data=' + encodeURIComponent(JSON.stringify(quizData));
        
        xhr.send(params);
    }
    
    // Альтернативный метод - отслеживание изменений в DOM
    function observeForQuizCompletion() {
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    // Ищем элементы, указывающие на завершение квиза
                    var completedElements = document.querySelectorAll(
                        '.quiz-completed, .quizle-result, .quiz-result, ' +
                        '[class*="complete"], [class*="finished"], [class*="result"]'
                    );
                    
                    completedElements.forEach(function(element) {
                        if (element.style.display !== 'none' && !element.dataset.processed) {
                            element.dataset.processed = 'true';
                            extractResultData(element);
                        }
                    });
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    function extractResultData(resultElement) {
        var quizElement = resultElement.closest('[data-quiz-id]');
        var quizData = {
            quiz_id: quizElement ? quizElement.dataset.quizId : 'unknown',
            quiz_name: 'Квиз с сайта',
            answers: {}
        };
        
        // Ищем поля ввода в области результата
        var inputs = resultElement.querySelectorAll('input[type="text"], input[type="tel"], input[type="email"]');
        inputs.forEach(function(input) {
            var name = input.name || input.placeholder || input.id;
            if (name.includes('name') || name.includes('имя')) {
                quizData.name = input.value;
            } else if (name.includes('phone') || name.includes('телефон')) {
                quizData.phone = input.value;
            } else if (name.includes('email') || name.includes('почта')) {
                quizData.email = input.value;
            }
        });
        
        // Извлекаем результат квиза
        var resultText = resultElement.textContent || resultElement.innerText;
        if (resultText) {
            quizData.answers['Результат квиза'] = resultText.trim();
        }
        
        if (quizData.name || quizData.phone || quizData.email) {
            sendToAmoCRM(quizData);
        }
    }
    
    // Обработка кнопки "Связаться с нами" с полями arcu-field
    function setupContactButtonHandler() {
        console.log('RuCoder AmoCRM: Настройка обработчика кнопки связи');
        
        function handleContactButtonSubmit(formData) {
            var trackingData = collectTrackingData();
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', rucoder_ajax.url);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            var params = 'action=rucoder_contact_button';
            params += '&name=' + encodeURIComponent(formData.name || '');
            params += '&phone=' + encodeURIComponent(formData.phone || '');
            params += '&email=' + encodeURIComponent(formData.email || '');
            params += '&nonce=' + encodeURIComponent(rucoder_ajax.nonce || '');
            
            // Добавляем данные отслеживания
            Object.keys(trackingData).forEach(function(key) {
                params += '&tracking_' + key + '=' + encodeURIComponent(trackingData[key]);
            });
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            console.log('RuCoder AmoCRM: Заявка с кнопки связи отправлена:', response);
                        } else {
                            console.log('RuCoder AmoCRM: Ошибка отправки заявки:', response);
                        }
                    } catch (e) {
                        console.log('RuCoder AmoCRM: Ошибка обработки ответа:', e);
                    }
                }
            };
            
            xhr.send(params);
        }
        
        // Наблюдение за появлением полей кнопки связи
        function observeContactFields() {
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        // Ищем поля кнопки связи по классам arcu-field
                        var nameField = document.querySelector('.arcu-field-name');
                        var emailField = document.querySelector('.arcu-field-email');
                        var phoneField = document.querySelector('.arcu-field-phone');
                        
                        if (nameField || emailField || phoneField) {
                            console.log('RuCoder AmoCRM: Найдены поля кнопки связи');
                            
                            // Ищем кнопку отправки и форму
                            var submitButton = document.querySelector('button[type="submit"], input[type="submit"], [class*="submit"]');
                            var contactForm = nameField ? nameField.closest('form') : 
                                             phoneField ? phoneField.closest('form') : 
                                             emailField ? emailField.closest('form') : null;
                            
                            if (submitButton && !submitButton.dataset.rucoderHandled) {
                                submitButton.dataset.rucoderHandled = 'true';
                                submitButton.addEventListener('click', function(e) {
                                    setTimeout(function() {
                                        var formData = {
                                            name: nameField ? nameField.value : '',
                                            email: emailField ? emailField.value : '',
                                            phone: phoneField ? phoneField.value : ''
                                        };
                                        
                                        if (formData.phone || formData.email) {
                                            handleContactButtonSubmit(formData);
                                        }
                                    }, 500);
                                });
                            }
                        }
                    }
                });
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        
        observeContactFields();
    }
    
    // Запуск всех обработчиков
    setTimeout(function() {
        setupQuizleEventListeners();
        handleQuizleSubmission();
        observeForQuizCompletion();
        setupContactButtonHandler();
    }, 2000);
});