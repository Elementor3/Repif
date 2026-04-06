/* global bootstrap */
(function () {
	function editStation(serial, name, desc) {
		var serialInput = document.getElementById('editSerial');
		var nameInput = document.getElementById('editName');
		var descInput = document.getElementById('editDesc');
		var modalEl = document.getElementById('editModal');

		if (!serialInput || !nameInput || !descInput || !modalEl) {
			return;
		}

		serialInput.value = serial || '';
		nameInput.value = name || '';
		descInput.value = desc || '';

		bootstrap.Modal.getOrCreateInstance(modalEl).show();
	}

	window.editStation = editStation;
})();
