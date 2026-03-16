;(function () {
	'use strict';

	if (typeof BX === 'undefined')
	{
		return;
	}

	BX.namespace('MyBpButton');

	var State = BX.MyBpButton.ButtonState;
	var Utils = BX.MyBpButton.ButtonUtils;
	var Api = BX.MyBpButton.ButtonApi;
	var SidePanel = BX.MyBpButton.ButtonSidePanel;

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

		onClick: function (event, buttonEl)
		{
			if (event)
			{
				event.preventDefault();
				event.stopPropagation();
			}

			var st = State.getState(buttonEl);
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

			var context = { entityId: entityId, elementId: elementId, fieldId: fieldId, userId: userId };

			State.setLoading(buttonEl);

			var self = this;
			Api.fetchConfig(
				{ entityId: entityId, elementId: elementId, fieldId: fieldId },
				function (response) {
					self.handleResponse(buttonEl, response, context);
				},
				function () {
					State.setIdle(buttonEl);
					State.notify(State.message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.'));
					self.logClick(buttonEl, context, 'INTERNAL_ERROR', 'AJAX failure');
				}
			);
		},

		handleResponse: function (buttonEl, response, fallbackContext)
		{
			if (!response || typeof response !== 'object')
			{
				State.setIdle(buttonEl);
				State.notify(State.message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.'));
				this.logClick(buttonEl, fallbackContext, 'INTERNAL_ERROR', 'Invalid JSON response');
				return;
			}

			if (response.success === true && response.data)
			{
				var data = response.data || {};
				var ctx = data.context || fallbackContext || {};
				var url = data.url || '';
				var title = (data.title || '');
				var width = data.width;

				if (!url)
				{
					State.setIdle(buttonEl);
					State.notify(State.message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.'));
					this.logClick(buttonEl, ctx, 'INTERNAL_ERROR', 'Empty url');
					return;
				}

				try
				{
					SidePanel.open(url, title, width, ctx);
				}
				catch (e)
				{
					State.notify(State.message('MY_BPBTN_ERROR_INTERNAL_ERROR', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.'));
					this.logClick(buttonEl, ctx, 'INTERNAL_ERROR', (e && e.message) ? e.message : 'SidePanel open failed');
				}
				finally
				{
					State.setIdle(buttonEl);
				}

				this.logClick(buttonEl, ctx, 'SUCCESS', null);
				return;
			}

			State.setIdle(buttonEl);

			var code = response.error && response.error.code ? String(response.error.code) : 'INTERNAL_ERROR';
			var msg = response.error && response.error.message ? String(response.error.message) : State.message('MY_BPBTN_ERROR_DEFAULT', 'Произошла ошибка. Попробуйте позже или обратитесь к администратору.');

			if (code === 'BUTTON_INACTIVE')
			{
				State.setDisabled(buttonEl);
			}

			State.notify(msg);
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
