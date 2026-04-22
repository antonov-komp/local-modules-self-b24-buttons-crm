;(function () {
	'use strict';

	if (typeof BX === 'undefined')
	{
		return;
	}

	BX.namespace('MyBpButton');

	/**
	 * URL для запроса конфигурации.
	 * @returns {string}
	 */
	function getConfigUrl()
	{
		return '/local/modules/my.bpbutton/tools/button_ajax.php';
	}

	/**
	 * Запрос конфигурации кнопки.
	 * @param {object} params — { entityId, elementId, fieldId }
	 * @param {function} onSuccess — (response) => void
	 * @param {function} onFailure — () => void
	 */
	function fetchConfig(params, onSuccess, onFailure)
	{
		if (!onSuccess || typeof onSuccess !== 'function')
		{
			onSuccess = function () {};
		}
		if (!onFailure || typeof onFailure !== 'function')
		{
			onFailure = function () {};
		}

		BX.ajax({
			url: getConfigUrl(),
			method: 'POST',
			dataType: 'json',
			cache: false,
			data: {
				entityId: params.entityId || '',
				elementId: params.elementId || 0,
				fieldId: params.fieldId || 0,
				sessid: (BX.bitrix_sessid ? BX.bitrix_sessid() : '')
			},
			onsuccess: function (response) {
				onSuccess(response);
			},
			onfailure: function () {
				onFailure();
			}
		});
	}

	/**
	 * Запуск БП с параметром.
	 * @param {object} params — { entityId, elementId, fieldId, value }
	 * @param {function} onSuccess
	 * @param {function} onFailure
	 */
	function startBpWithParams(params, onSuccess, onFailure)
	{
		if (!onSuccess || typeof onSuccess !== 'function')
		{
			onSuccess = function () {};
		}
		if (!onFailure || typeof onFailure !== 'function')
		{
			onFailure = function () {};
		}

		BX.ajax({
			url: getConfigUrl(),
			method: 'POST',
			dataType: 'json',
			cache: false,
			data: {
				action: 'startBpWithParams',
				entityId: params.entityId || '',
				elementId: params.elementId || 0,
				fieldId: params.fieldId || 0,
				value: params.value || '',
				sessid: (BX.bitrix_sessid ? BX.bitrix_sessid() : '')
			},
			onsuccess: function (response) {
				onSuccess(response);
			},
			onfailure: function () {
				onFailure();
			}
		});
	}

	function startBpWithButtonParam(params, onSuccess, onFailure)
	{
		if (!onSuccess || typeof onSuccess !== 'function')
		{
			onSuccess = function () {};
		}
		if (!onFailure || typeof onFailure !== 'function')
		{
			onFailure = function () {};
		}

		BX.ajax({
			url: getConfigUrl(),
			method: 'POST',
			dataType: 'json',
			cache: false,
			data: {
				action: 'startBpWithButtonParam',
				entityId: params.entityId || '',
				elementId: params.elementId || 0,
				fieldId: params.fieldId || 0,
				selectedValue: params.selectedValue || '',
				sessid: (BX.bitrix_sessid ? BX.bitrix_sessid() : '')
			},
			onsuccess: function (response) {
				onSuccess(response);
			},
			onfailure: function () {
				onFailure();
			}
		});
	}

	BX.MyBpButton.ButtonApi = {
		getConfigUrl: getConfigUrl,
		fetchConfig: fetchConfig,
		startBpWithParams: startBpWithParams,
		startBpWithButtonParam: startBpWithButtonParam
	};
})();
