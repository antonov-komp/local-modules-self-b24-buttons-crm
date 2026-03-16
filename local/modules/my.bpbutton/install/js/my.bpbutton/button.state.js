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

	BX.MyBpButton.ButtonState = {
		getState: getState,
		setState: setState,
		notify: notify,
		message: message,

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
		}
	};
})();
