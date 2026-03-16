;(function () {
	'use strict';

	if (typeof BX === 'undefined')
	{
		return;
	}

	BX.namespace('MyBpButton');

	/**
	 * Преобразует width в число (пиксели) для BX.SidePanel.
	 * SidePanel принимает только number; проценты (80%) конвертируем в пиксели.
	 * @param {string|number} width — '800', '80%', 800
	 * @returns {number|undefined}
	 */
	function parseWidth(width)
	{
		if (typeof width === 'number' && width > 0)
		{
			return width;
		}

		if (typeof width !== 'string')
		{
			return undefined;
		}

		var w = width.trim();
		if (w === '')
		{
			return undefined;
		}

		// Только цифры — пиксели
		if (/^\d+$/.test(w))
		{
			return parseInt(w, 10);
		}

		// Проценты (например 80%) — конвертируем в пиксели
		var percentMatch = w.match(/^(\d+)\s*%$/);
		if (percentMatch)
		{
			var percent = parseInt(percentMatch[1], 10);
			if (percent > 0 && percent <= 100 && typeof window !== 'undefined' && window.innerWidth)
			{
				return Math.floor(window.innerWidth * (percent / 100));
			}
		}

		return undefined;
	}

	/**
	 * Добавляет контекст (ENTITY_ID, ELEMENT_ID, FIELD_ID, USER_ID) к URL.
	 * @param {string} url
	 * @param {object} context — { entityId, elementId, fieldId, userId }
	 * @returns {string}
	 */
	function ensureContextUrl(url, context)
	{
		if (!url || typeof url !== 'string')
		{
			return '';
		}

		if (!context || typeof context !== 'object')
		{
			return url;
		}

		var params = {
			ENTITY_ID: context.entityId,
			ELEMENT_ID: context.elementId,
			FIELD_ID: context.fieldId,
			USER_ID: context.userId
		};

		var query = [];
		for (var k in params)
		{
			if (!params.hasOwnProperty(k))
			{
				continue;
			}
			if (typeof params[k] === 'undefined' || params[k] === null || params[k] === '')
			{
				continue;
			}
			query.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(params[k])));
		}

		if (!query.length)
		{
			return url;
		}

		return (url.indexOf('?') >= 0) ? (url + '&' + query.join('&')) : (url + '?' + query.join('&'));
	}

	BX.MyBpButton.ButtonUtils = {
		parseWidth: parseWidth,
		ensureContextUrl: ensureContextUrl
	};
})();
