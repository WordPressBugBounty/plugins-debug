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
	}

	function init() {
		initToggle();
		initConfirmations();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();