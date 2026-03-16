;(function () {
	'use strict';

	if (typeof BX === 'undefined')
	{
		return;
	}

	BX.namespace('MyBpButton');

	/**
	 * Открывает SidePanel.
	 * @param {string} url
	 * @param {string} title
	 * @param {string|number} width — '800', '80%', 800
	 * @param {object} context — { entityId, elementId, fieldId, userId }
	 * @throws {Error} — если BX.SidePanel.Instance недоступен
	 */
	function open(url, title, width, context)
	{
		if (!BX.SidePanel || !BX.SidePanel.Instance)
		{
			throw new Error('BX.SidePanel.Instance is not available');
		}

		var Utils = BX.MyBpButton.ButtonUtils;
		if (!Utils)
		{
			throw new Error('BX.MyBpButton.ButtonUtils is not available');
		}

		var finalUrl = Utils.ensureContextUrl(url, context || {});
		var parsedWidth = Utils.parseWidth(width);

		BX.SidePanel.Instance.open(finalUrl, {
			width: parsedWidth,
			cacheable: false,
			allowChangeHistory: false,
			label: { text: title || '' }
		});
	}

	BX.MyBpButton.ButtonSidePanel = {
		open: open
	};
})();
