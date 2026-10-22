document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('[data-image-preview]').forEach(function (input) {
		var preview = document.getElementById(input.getAttribute('data-image-preview'));
		if (!preview) {
			return;
		}

		var previousUrl = null;
		input.addEventListener('change', function () {
			if (!input.files || !input.files[0]) {
				return;
			}

			if (previousUrl) {
				URL.revokeObjectURL(previousUrl);
			}
			previousUrl = URL.createObjectURL(input.files[0]);
			preview.src = previousUrl;
		});
	});
});
