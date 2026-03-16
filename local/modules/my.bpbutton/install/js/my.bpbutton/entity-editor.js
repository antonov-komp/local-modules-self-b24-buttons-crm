;(function () {
	'use strict';

	if (typeof BX === 'undefined') {
		return;
	}

	// Патч для main.field.config.detail: showErrors получает массив объектов {message, code},
	// а не строк — иначе выводится "[object Object]"
	function patchConfigShowErrors() {
		var ns = BX.Main && BX.Main.UserField;
		if (!ns || typeof ns.Config !== 'function') {
			return false;
		}
		var proto = ns.Config.prototype;
		if (!proto || proto._bpbuttonShowErrorsPatched) {
			return true;
		}
		var original = proto.showErrors;
		if (typeof original !== 'function') {
			return true;
		}
		proto.showErrors = function (errors) {
			var list = [];
			if (BX.Type.isArray(errors)) {
				errors.forEach(function (item) {
					if (BX.Type.isString(item)) {
						list.push(item);
					} else if (item && typeof item === 'object' && item.message) {
						list.push(String(item.message));
					} else if (item != null) {
						list.push(String(item));
					}
				});
			}
			return original.call(this, list);
		};
		proto._bpbuttonShowErrorsPatched = true;
		return true;
	}

	// Config может подгружаться позже (слайдер настроек поля)
	var patchAttempts = 0;
	var patchInterval = setInterval(function () {
		if (patchConfigShowErrors() || ++patchAttempts > 60) {
			clearInterval(patchInterval);
		}
	}, 500);

	// Добавляем bp_button_field в список типов при создании поля (только если UI загружен)
	if (typeof BX.UI !== 'undefined') {
	BX.addCustomEvent('BX.UI.EntityUserFieldManager:getTypes', function (event) {
		var types = event.getData().types;
		if (!BX.Type.isArray(types)) {
			return;
		}
		var hasBpButton = types.some(function (t) { return t.name === 'bp_button_field'; });
		if (!hasBpButton) {
			types.push({
				name: 'bp_button_field',
				title: BX.message('BPBUTTON_ENTITY_ED_TYPE_TITLE') || 'Кнопка бизнес‑процесса',
				legend: BX.message('BPBUTTON_ENTITY_ED_TYPE_LEGEND') || 'Кнопка для запуска действия. Обработчик настраивается в админке.'
			});
			event.setData({ types: types });
		}
	});

	// EntityEditorUserField может быть ещё не определён (подгружается асинхронно)
	function patchEntityEditorUserField() {
		if (typeof BX.UI.EntityEditorUserField === 'undefined') {
			setTimeout(patchEntityEditorUserField, 50);
			return;
		}

		var proto = BX.UI.EntityEditorUserField.prototype;
		if (proto._bpbuttonHasContentPatched) {
			return;
		}

		var originalHasContentToDisplay = proto.hasContentToDisplay;
		if (typeof originalHasContentToDisplay !== 'function') {
			return;
		}

		proto.hasContentToDisplay = function () {
			// Для bp_button_field всегда показываем контент (кнопку), даже если поле "пустое"
			if (this.getFieldType && this.getFieldType() === 'bp_button_field') {
				return true;
			}
			return originalHasContentToDisplay.apply(this, arguments);
		};
		proto._bpbuttonHasContentPatched = true;
	}

	// Патчим при загрузке
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', patchEntityEditorUserField);
	} else {
		patchEntityEditorUserField();
	}

	// Дополнительно — на случай асинхронной загрузки entity-editor
	BX.ready(patchEntityEditorUserField);
	}

	// TASK-014-A: скрытие вкладки «Бизнес-процессы» в карточке CRM
	if (typeof window.MY_BPBUTTON_HIDE_BP_TAB !== 'undefined' && window.MY_BPBUTTON_HIDE_BP_TAB) {
		function hideBpTab() {
			var tabContent = document.querySelector('[data-tab-id="tab_bizproc"]');
			var tabMenu = document.getElementById('tab_bizproc');
			var bpStarterBtn = document.getElementById('crm_entity_bp_starter');
			var bpStarterWrap = document.querySelector('.crm-entity-bizproc-container');
			if (tabContent) {
				tabContent.style.display = 'none';
			}
			if (tabMenu) {
				tabMenu.style.display = 'none';
			}
			if (bpStarterBtn) {
				bpStarterBtn.style.display = 'none';
			}
			if (bpStarterWrap) {
				bpStarterWrap.style.display = 'none';
			}
		}
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function() {
				hideBpTab();
				setTimeout(hideBpTab, 500);
				setTimeout(hideBpTab, 1500);
			});
		} else {
			hideBpTab();
			setTimeout(hideBpTab, 500);
			setTimeout(hideBpTab, 1500);
		}
		BX.ready(hideBpTab);
	}
})();
