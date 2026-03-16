;(function () {
	'use strict';

	if (typeof BX === 'undefined')
	{
		return;
	}

	BX.namespace('MyBpButton');

	var stateMap = (typeof WeakMap !== 'undefined') ? new WeakMap() : null;

	function getState(buttonEl)
	{
		if (!buttonEl)
		{
			return { status: 'idle', disabledByBusiness: false };
		}

		if (stateMap)
		{
			if (!stateMap.has(buttonEl))
			{
				stateMap.set(buttonEl, { status: 'idle', disabledByBusiness: false });
			}
			return stateMap.get(buttonEl);
		}

		var status = (buttonEl.dataset && buttonEl.dataset.bpbuttonState) ? buttonEl.dataset.bpbuttonState : 'idle';
		var disabledByBusiness = (buttonEl.dataset && buttonEl.dataset.bpbuttonDisabledBusiness === 'Y');
		return { status: status, disabledByBusiness: disabledByBusiness };
	}

	function setState(buttonEl, next)
	{
		if (!buttonEl)
		{
			return;
		}

		if (stateMap)
		{
			var current = getState(buttonEl);
			stateMap.set(buttonEl, BX.mergeEx(current, next));
		}
		else if (buttonEl.dataset)
		{
			if (typeof next.status === 'string')
			{
				buttonEl.dataset.bpbuttonState = next.status;
			}
			if (typeof next.disabledByBusiness !== 'undefined')
			{
				buttonEl.dataset.bpbuttonDisabledBusiness = next.disabledByBusiness ? 'Y' : 'N';
			}
		}
	}

	function notify(content)
	{
		if (BX.UI && BX.UI.Notification && BX.UI.Notification.Center)
		{
			BX.UI.Notification.Center.notify({
				content: content,
				autoHideDelay: 5000
			});
		}
	}

	function message(key, fallback)
	{
		if (BX.message && BX.message(key))
		{
			return BX.message(key);
		}
		return fallback || '';
	}

	function parseWidth(width)
	{
		if (typeof width === 'number')
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

		if (/^\d+$/.test(w))
		{
			return parseInt(w, 10);
		}

		return w;
	}

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

	BX.MyBpButton.Button = {
		selectors: {
			button: '.js-bpbutton-field'
		},

		init: function ()
		{
			var buttons = document.querySelectorAll(this.selectors.button);
			if (!buttons || !buttons.length)
			{
				return;
			}

			for (var i = 0; i < buttons.length; i++)
			{
				this.bind(buttons[i]);
			}
		},

		bind: function (buttonEl)
		{
			if (!buttonEl || buttonEl.nodeType !== 1)
			{
				return;
			}

			if (buttonEl.dataset && buttonEl.dataset.bpbuttonInit === 'Y')
			{
				return;
			}

			if (buttonEl.dataset)
			{
				buttonEl.dataset.bpbuttonInit = 'Y';
			}

			var self = this;
			buttonEl.addEventListener('click', function (e) {
				self.onClick(e, buttonEl);
			});
		},

		setIdle: function (buttonEl)
		{
			if (!buttonEl)
			{
				return;
			}

			BX.removeClass(buttonEl, 'ui-btn-wait');
			var st = getState(buttonEl);
			if (st && st.disabledByBusiness)
			{
				BX.addClass(buttonEl, 'ui-btn-disabled');
				setState(buttonEl, { status: 'disabled' });
				return;
			}

			BX.removeClass(buttonEl, 'ui-btn-disabled');
			setState(buttonEl, { status: 'idle' });
		},

		setLoading: function (buttonEl)
		{
			if (!buttonEl)
			{
				return;
			}

			BX.addClass(buttonEl, 'ui-btn-wait');
			setState(buttonEl, { status: 'loading' });
		},

		setDisabled: function (buttonEl)
		{
			if (!buttonEl)
			{
				return;
			}

			BX.removeClass(buttonEl, 'ui-btn-wait');
			BX.addClass(buttonEl, 'ui-btn-disabled');
			setState(buttonEl, { status: 'disabled', disabledByBusiness: true });
		},

		flashError: function (buttonEl)
		{
			if (!buttonEl)
			{
				return;
			}

			BX.addClass(buttonEl, 'ui-btn-danger');
			setTimeout(function () {
				BX.removeClass(buttonEl, 'ui-btn-danger');
			}, 900);
		},

		getConfigUrl: function ()
		{
			return '/bitrix/services/my.bpbutton/button/ajax.php';
		},

		onClick: function (event, buttonEl)
		{
			if (event)
			{
				event.preventDefault();
				event.stopPropagation();
			}

			var st = getState(buttonEl);
			if (st && (st.status === 'loading' || st.disabledByBusiness))
			{
				return;
			}

			if (BX.hasClass(buttonEl, 'ui-btn-disabled'))
			{
				return;
			}

			var entityId = buttonEl.dataset ? (buttonEl.dataset.entityId || '') : '';
			var elementId = buttonEl.dataset ? parseInt(buttonEl.dataset.elementId || '0', 10) : 0;
			var fieldId = buttonEl.dataset ? parseInt(buttonEl.dataset.fieldId || '0', 10) : 0;
			var userId = buttonEl.dataset ? parseInt(buttonEl.dataset.userId || '0', 10) : 0;

			this.setLoading(buttonEl);

			var self = this;
			BX.ajax({
				url: self.getConfigUrl(),
				method: 'POST',
				dataType: 'json',
				data: {
					entityId: entityId,
					elementId: elementId,
					fieldId: fieldId,
					sessid: (BX.bitrix_sessid ? BX.bitrix_sessid() : '')
				},
				onsuccess: function (response) {
					self.handleResponse(buttonEl, response, {
						entityId: entityId,
						elementId: elementId,
						fieldId: fieldId,
						userId: userId
					});
				},
				onfailure: function () {
					self.setIdle(buttonEl);
					notify(message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.'));
					self.logClick(buttonEl, { entityId: entityId, elementId: elementId, fieldId: fieldId, userId: userId }, 'INTERNAL_ERROR', 'AJAX failure');
				}
			});
		},

		handleResponse: function (buttonEl, response, fallbackContext)
		{
			if (!response || typeof response !== 'object')
			{
				this.setIdle(buttonEl);
				notify(message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.'));
				this.logClick(buttonEl, fallbackContext, 'INTERNAL_ERROR', 'Invalid JSON response');
				return;
			}

			if (response.success === true && response.data)
			{
				var data = response.data || {};
				var ctx = data.context || fallbackContext || {};

				var url = ensureContextUrl((data.url || ''), ctx);
				var title = (data.title || '');
				var width = parseWidth(data.width);

				if (!url)
				{
					this.setIdle(buttonEl);
					notify(message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.'));
					this.logClick(buttonEl, ctx, 'INTERNAL_ERROR', 'Empty url');
					return;
				}

				try
				{
					BX.SidePanel.Instance.open(url, {
						width: width,
						cacheable: false,
						allowChangeHistory: false,
						label: { text: title }
					});
				}
				catch (e)
				{
					notify(message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.'));
					this.logClick(buttonEl, ctx, 'INTERNAL_ERROR', (e && e.message) ? e.message : 'SidePanel open failed');
				}
				finally
				{
					this.setIdle(buttonEl);
				}

				this.logClick(buttonEl, ctx, 'SUCCESS', null);
				return;
			}

			this.setIdle(buttonEl);

			var code = response.error && response.error.code ? String(response.error.code) : 'INTERNAL_ERROR';
			var msg = response.error && response.error.message ? String(response.error.message) : message('MY_BPBTN_ERROR_DEFAULT', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.');

			if (code === 'BUTTON_INACTIVE')
			{
				this.setDisabled(buttonEl);
			}

			notify(msg);
			this.logClick(buttonEl, fallbackContext, code, msg);
		},

		logClick: function (buttonEl, context, status, messageText)
		{
			// TODO: реализация по TASK-004-logging / logClickAction
		}
	};

	BX.ready(function () {
		if (BX.MyBpButton && BX.MyBpButton.Button)
		{
			BX.MyBpButton.Button.init();
		}
	});
})();

