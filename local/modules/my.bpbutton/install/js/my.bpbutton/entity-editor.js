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
	// Определяем entityId по data-entity-id кнопки на странице и проверяем через API
	(function initHideBpTab() {
		try {
			var BP_TAB_SEL = '[data-tab-id="tab_bizproc"],#tab_bizproc,[data-id="tab_bizproc"],.main-buttons-item[data-id="tab_bizproc"],#crm_entity_bp_starter,.crm-entity-bizproc-container,.crm-entity-section[data-tab-id="tab_bizproc"]';
			var _bpTabHidden = false;
			var _bpTabChecked = false;
			var _applyHideInterval = null;

			function hideBpTab() {
				try {
					var all = document.querySelectorAll(BP_TAB_SEL);
					for (var j = 0; j < all.length; j++) {
						var el = all[j];
						el.style.setProperty('display', 'none', 'important');
						el.style.setProperty('visibility', 'hidden', 'important');
						el.style.setProperty('pointer-events', 'none', 'important');
						el.setAttribute('aria-hidden', 'true');
					}
					if (all.length > 0 && window.location.search.indexOf('debug_bpbutton=1') >= 0) {
						console.log('[MY_BPBUTTON] Hiding BP tab, count=', all.length);
					}
				} catch (e) { /* ignore */ }
			}

			function applyHide() {
				if (_bpTabHidden) hideBpTab();
			}

			function startApplyHideInterval() {
				if (_applyHideInterval) return;
				_applyHideInterval = setInterval(applyHide, 500);
				setTimeout(function() {
					clearInterval(_applyHideInterval);
					_applyHideInterval = null;
				}, 12000);
			}

			function checkAndHide() {
				if (_bpTabChecked) return;
				var btn = document.querySelector('.js-bpbutton-field, .bpbutton-field-wrapper [data-entity-id]');
				var entityId = btn && btn.getAttribute && btn.getAttribute('data-entity-id');
				if (!entityId || entityId === '') return;

				_bpTabChecked = true;
				if (typeof BX.ajax !== 'function') return;
				BX.ajax({
					url: '/bitrix/services/my.bpbutton/button/ajax.php',
					method: 'POST',
					dataType: 'json',
					data: {
						action: 'getShouldHideBpTab',
						entityId: entityId,
						sessid: (BX.bitrix_sessid ? BX.bitrix_sessid() : '')
					},
					onsuccess: function(res) {
						if (res && res.shouldHide === true) {
							_bpTabHidden = true;
							hideBpTab();
							[200, 400, 600, 1000, 2000, 4000, 6000, 8000].forEach(function(ms) {
								setTimeout(applyHide, ms);
							});
							startApplyHideInterval();
						}
					},
					onfailure: function() {
						if (window.location.search.indexOf('debug_bpbutton=1') >= 0) {
							console.warn('[MY_BPBUTTON] getShouldHideBpTab API failed');
						}
					}
				});
			}

			function runCheck() {
				try {
					checkAndHide();
					if (_bpTabHidden) applyHide();
				} catch (e) { /* ignore */ }
			}

			function setupObserver() {
				if (typeof MutationObserver === 'undefined' || !document.body) return;
				try {
					var t = 0;
					var obs = new MutationObserver(function() {
						if (t) return;
						t = setTimeout(function() { t = 0; runCheck(); }, 150);
					});
					obs.observe(document.body, { childList: true, subtree: true });
					setTimeout(function() { obs.disconnect(); }, 30000);
				} catch (e) { /* ignore */ }
			}

			runCheck();
			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', function() {
					runCheck();
					setupObserver();
				});
			} else {
				setupObserver();
			}
			BX.ready(runCheck);
			[300, 600, 1000, 2000, 3000, 5000].forEach(function(ms) {
				setTimeout(runCheck, ms);
			});
		} catch (e) {
			if (window.location.search.indexOf('debug_bpbutton=1') >= 0) {
				console.error('[MY_BPBUTTON] initHideBpTab error:', e);
			}
		}
	})();
})();
