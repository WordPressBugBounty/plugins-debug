/**
 * Debug plugin admin behaviors.
 *
 * @package Debug
 */
(function () {
	'use strict';

	/**
	 * Toggle the email-notification field visibility based on the checkbox.
	 */
	function initToggle() {
		var cb = document.getElementById('enable_notification');
		if (!cb) {
			return;
		}

		var mailFields = document.querySelectorAll('.debug-email-field');
		var noMailFields = document.querySelectorAll('.debug-no-email-field');

		function toggle() {
			var show = cb.checked;
			mailFields.forEach(function (el) {
				el.style.display = show ? 'block' : 'none';
			});
			noMailFields.forEach(function (el) {
				el.style.display = show ? 'none' : 'block';
			});
		}

		cb.addEventListener('change', toggle);
		toggle();
	}

	/**
	 * Confirm destructive actions (clear log / restore config).
	 */
	function initConfirmations() {
		var clearBtn = document.getElementById('clearlog');
		if (clearBtn) {
			clearBtn.addEventListener('click', function (e) {
				if (!window.confirm('Clear the debug.log file?')) {
					e.preventDefault();
				}
			});
		}

		document.querySelectorAll('.debug-restore-btn').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				if (!window.confirm('Restore wp-config.php from this backup?')) {
					e.preventDefault();
				}
			});
		});

		document.querySelectorAll('.debug-delete-btn').forEach(function (btn) {
			btn.addEventListener('click', function (e) {
				if (!window.confirm('Delete this wp-config.php backup?')) {
					e.preventDefault();
				}
			});
		});
	}

	/**
	 * Auto-refresh the error-log viewer every few seconds while enabled.
	 */
	function initLogRefresh() {
		var logPre = document.getElementById('debug-log');
		var toggle = document.getElementById('debug-log-refresh');
		if (!logPre || !toggle) {
			return;
		}
		var timer = null;

		function stop() {
			if (timer) {
				window.clearInterval(timer);
				timer = null;
			}
		}

		toggle.addEventListener('change', function () {
			if (toggle.checked) {
				stop();
				timer = window.setInterval(function () {
					window.location.reload();
				}, 5000);
			} else {
				stop();
			}
		});
	}

	function init() {
		initToggle();
		initConfirmations();
		initLogRefresh();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();