/**
 * Административный список модуля «Кнопки БП»
 *
 * Обеспечивает:
 * - inline-переключение активности (ACTIVE)
 * - тултипы и визуальные подсказки
 * - уведомления в стиле Bitrix24
 * - открытие формы редактирования в SidePanel (опционально)
 */

(function() {
    'use strict';

    if (typeof BX === 'undefined') {
        return;
    }

    /**
     * Основной объект для работы с админ-списком
     */
    BX.MyBpButton = BX.MyBpButton || {};
    BX.MyBpButton.AdminList = {
        /**
         * Инициализация функционала админ-списка
         */
        init: function() {
            this.initActiveToggles();
            this.initTooltips();
            this.initSidePanelLinks();
        },

        /**
         * Инициализация inline-переключателей активности
         */
        initActiveToggles: function() {
            const toggles = document.querySelectorAll('.js-bpbutton-active-toggle');
            
            toggles.forEach(function(toggle) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const row = toggle.closest('tr');
                    if (!row) {
                        return;
                    }

                    const id = parseInt(toggle.getAttribute('data-id'), 10);
                    const currentActive = toggle.getAttribute('data-active');
                    const newActive = currentActive === 'Y' ? 'N' : 'Y';

                    if (isNaN(id) || id <= 0) {
                        return;
                    }

                    BX.MyBpButton.AdminList.toggleActive(id, newActive, toggle, row);
                });
            });
        },

        /**
         * Переключение активности через AJAX
         *
         * @param {number} id ID записи
         * @param {string} newActive Новое значение ('Y' или 'N')
         * @param {HTMLElement} toggle Элемент переключателя
         * @param {HTMLElement} row Строка таблицы
         */
        toggleActive: function(id, newActive, toggle, row) {
            // Сохраняем исходное состояние для отката при ошибке
            const originalActive = toggle.getAttribute('data-active');
            const originalHtml = toggle.innerHTML;

            // Показываем состояние загрузки
            toggle.classList.add('ui-btn-wait');
            toggle.disabled = true;
            const originalText = toggle.textContent;
            toggle.textContent = BX.message('MY_BPBUTTON_ADMIN_LOADING') || '...';

            // Отправляем AJAX-запрос
            BX.ajax({
                url: '/local/modules/my.bpbutton/admin/bpbutton_list_ajax.php',
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'toggle_active',
                    ID: id,
                    ACTIVE: newActive,
                    sessid: BX.bitrix_sessid(),
                },
                onsuccess: function(response) {
                    toggle.classList.remove('ui-btn-wait');
                    toggle.disabled = false;

                    if (response && response.success === true) {
                        // Обновляем состояние переключателя
                        toggle.setAttribute('data-active', newActive);
                        toggle.innerHTML = BX.MyBpButton.AdminList.getActiveToggleHtml(newActive);

                        // Обновляем визуальное отображение в строке
                        const activeCell = row.querySelector('.js-bpbutton-active-cell');
                        if (activeCell) {
                            activeCell.innerHTML = BX.MyBpButton.AdminList.getActiveCellHtml(newActive);
                        }

                        // Показываем уведомление об успехе
                        BX.UI.Notification.Center.notify({
                            content: BX.message('MY_BPBUTTON_ADMIN_ACTIVE_CHANGED') || 'Статус кнопки обновлён.',
                            autoHideDelay: 3000,
                        });
                    } else {
                        // Откатываем изменения при ошибке
                        toggle.setAttribute('data-active', originalActive);
                        toggle.innerHTML = originalHtml;

                        const errorMessage = (response && response.error && response.error.message)
                            ? response.error.message
                            : (BX.message('MY_BPBUTTON_ADMIN_ERROR_GENERIC') || 'Ошибка при изменении статуса.');

                        BX.UI.Notification.Center.notify({
                            content: errorMessage,
                            autoHideDelay: 5000,
                        });
                    }
                },
                onfailure: function() {
                    // Откатываем изменения при ошибке
                    toggle.classList.remove('ui-btn-wait');
                    toggle.disabled = false;
                    toggle.setAttribute('data-active', originalActive);
                    toggle.innerHTML = originalHtml;

                    BX.UI.Notification.Center.notify({
                        content: BX.message('MY_BPBUTTON_ADMIN_ERROR_NETWORK') || 'Ошибка сети. Попробуйте ещё раз.',
                        autoHideDelay: 5000,
                    });
                },
            });
        },

        /**
         * Получение HTML для переключателя активности
         *
         * @param {string} active Значение активности ('Y' или 'N')
         * @returns {string} HTML переключателя
         */
        getActiveToggleHtml: function(active) {
            const activeText = active === 'Y'
                ? (BX.message('MY_BPBUTTON_LIST_ACTIVE_Y') || 'Активно')
                : (BX.message('MY_BPBUTTON_LIST_ACTIVE_N') || 'Не активно');
            
            const color = active === 'Y' ? 'green' : 'gray';
            const icon = active === 'Y' ? '✓' : '✗';
            
            return '<span style="color: ' + color + '; margin-right: 4px;">' + icon + '</span>' 
                + '<span class="js-bpbutton-active-text">' + BX.util.htmlspecialchars(activeText) + '</span>';
        },

        /**
         * Получение HTML для ячейки активности
         *
         * @param {string} active Значение активности ('Y' или 'N')
         * @returns {string} HTML ячейки
         */
        getActiveCellHtml: function(active) {
            const activeText = active === 'Y'
                ? (BX.message('MY_BPBUTTON_LIST_ACTIVE_Y') || 'Активно')
                : (BX.message('MY_BPBUTTON_LIST_ACTIVE_N') || 'Не активно');

            const color = active === 'Y' ? 'green' : 'gray';
            return '<span style="color: ' + color + ';">' + BX.util.htmlspecialchars(activeText) + '</span>';
        },

        /**
         * Инициализация тултипов
         */
        initTooltips: function() {
            // Тултипы для HANDLER_URL уже обрабатываются через HTML title
            // Дополнительная обработка для WIDTH и ACTIVE

            const widthCells = document.querySelectorAll('.js-bpbutton-width-cell');
            widthCells.forEach(function(cell) {
                const width = cell.getAttribute('data-width') || '';
                if (width) {
                    const hint = BX.MyBpButton.AdminList.getWidthHint(width);
                    if (hint && !cell.getAttribute('title')) {
                        cell.setAttribute('title', hint);
                    }
                }
            });
        },

        /**
         * Получение подсказки для значения ширины
         *
         * @param {string} width Значение ширины
         * @returns {string} Текст подсказки
         */
        getWidthHint: function(width) {
            if (!width) {
                return '';
            }

            if (width.indexOf('%') !== -1) {
                return BX.message('MY_BPBUTTON_ADMIN_WIDTH_HINT_PERCENT') || 'Проценты от ширины экрана/SidePanel';
            } else if (/^\d+$/.test(width)) {
                return BX.message('MY_BPBUTTON_ADMIN_WIDTH_HINT_PIXELS') || 'Пиксели';
            }

            return '';
        },

        /**
         * Инициализация ссылок для открытия в SidePanel
         */
        initSidePanelLinks: function() {
            const sidePanelLinks = document.querySelectorAll('.js-bpbutton-sidepanel-link');
            
            sidePanelLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const url = link.getAttribute('href') || link.getAttribute('data-url');
                    if (!url) {
                        return;
                    }

                    if (typeof BX.SidePanel !== 'undefined' && BX.SidePanel.Instance) {
                        BX.SidePanel.Instance.open(url, {
                            width: '60%',
                            cacheable: false,
                            allowChangeHistory: true,
                        });
                    } else {
                        // Fallback: обычный переход
                        window.location.href = url;
                    }
                });
            });
        },
    };

    // Инициализация при загрузке страницы
    BX.ready(function() {
        BX.MyBpButton.AdminList.init();
    });

})();
