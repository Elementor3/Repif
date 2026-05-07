/* global bootstrap */
(function () {
	var autosaveTimer = null;
	var lastSavedPayload = null;
	var i18nEl = document.getElementById('stationsClientI18n');
	var alertsEl = document.getElementById('stationsAjaxAlerts');
	var i18n = {
		serialLabel: i18nEl ? (i18nEl.getAttribute('data-serial-label') || 'Serial Number') : 'Serial Number',
		descriptionLabel: i18nEl ? (i18nEl.getAttribute('data-description-label') || 'Description') : 'Description',
		registeredAtLabel: i18nEl ? (i18nEl.getAttribute('data-registered-at-label') || 'Registered At') : 'Registered At',
		unregisteredAtLabel: i18nEl ? (i18nEl.getAttribute('data-unregistered-at-label') || 'Unregistered At') : 'Unregistered At',
		editLabel: i18nEl ? (i18nEl.getAttribute('data-edit-label') || 'Edit') : 'Edit',
		measurementsLabel: i18nEl ? (i18nEl.getAttribute('data-measurements-label') || 'Measurements') : 'Measurements',
		deleteLabel: i18nEl ? (i18nEl.getAttribute('data-delete-label') || 'Delete') : 'Delete',
		confirmDelete: i18nEl ? (i18nEl.getAttribute('data-confirm-delete') || 'Are you sure?') : 'Are you sure?',
		defaultError: i18nEl ? (i18nEl.getAttribute('data-default-error') || 'Error occurred') : 'Error occurred',
		returnTo: i18nEl ? (i18nEl.getAttribute('data-return-to') || (window.location.pathname + window.location.search)) : (window.location.pathname + window.location.search)
	};

	function escapeHtml(value) {
		return String(value || '')
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function pad2(num) {
		return String(num).padStart(2, '0');
	}

	function formatDateTimeValue(value) {
		var raw = String(value || '').trim();
		if (raw === '') {
			return '-';
		}
		var normalized = raw.replace(' ', 'T');
		var date = new Date(normalized);
		if (Number.isNaN(date.getTime())) {
			return raw;
		}
		return pad2(date.getDate()) + '.' + pad2(date.getMonth() + 1) + '.' + date.getFullYear() + ' ' + pad2(date.getHours()) + ':' + pad2(date.getMinutes());
	}

	function getModalAlertsContainer(modalId) {
		var modal = document.getElementById(modalId);
		if (!modal) {
			return null;
		}

		var container = modal.querySelector('[data-modal-alerts]');
		if (container) {
			return container;
		}

		var body = modal.querySelector('.modal-body');
		if (!body) {
			return null;
		}

		container = document.createElement('div');
		container.setAttribute('data-modal-alerts', '');
		container.className = 'mb-3';
		body.insertBefore(container, body.firstChild);
		return container;
	}

	function getAlertTarget(preferredModalId) {
		if (preferredModalId) {
			var preferred = getModalAlertsContainer(preferredModalId);
			if (preferred) {
				return preferred;
			}
		}

		var activeModal = document.querySelector('.modal.show');
		if (activeModal) {
			var activeContainer = activeModal.querySelector('[data-modal-alerts]');
			if (!activeContainer && activeModal.id) {
				activeContainer = getModalAlertsContainer(activeModal.id);
			}
			if (activeContainer) {
				return activeContainer;
			}
		}

		return alertsEl;
	}

	function renderAlertTo(target, message, type) {
		if (!target) {
			return;
		}
		target.innerHTML = '';
		var alertType = type === 'success' ? 'success' : 'danger';
		var msg = escapeHtml(message || i18n.defaultError);
		target.insertAdjacentHTML('afterbegin',
			'<div class="alert alert-' + alertType + ' alert-dismissible fade show" role="alert">' +
			msg +
			'<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
			'</div>'
		);
	}

	function showAlert(message, type, preferredModalId) {
		var target = getAlertTarget(preferredModalId);
		renderAlertTo(target, message, type);

		if (alertsEl && target !== alertsEl) {
			renderAlertTo(alertsEl, message, type);
		}
	}

	function clearAlerts(preferredModalId) {
		if (preferredModalId) {
			var preferred = getModalAlertsContainer(preferredModalId);
			if (preferred) {
				preferred.innerHTML = '';
			}
			return;
		}

		if (alertsEl) {
			alertsEl.innerHTML = '';
		}

		var modalAlerts = document.querySelectorAll('[data-modal-alerts]');
		for (var i = 0; i < modalAlerts.length; i += 1) {
			modalAlerts[i].innerHTML = '';
		}
	}

	function findCard(serial, scope) {
		var cards = document.querySelectorAll('[data-station-card][data-station-scope="' + scope + '"]');
		for (var i = 0; i < cards.length; i += 1) {
			if ((cards[i].getAttribute('data-station-card') || '') === String(serial || '')) {
				return cards[i];
			}
		}
		return null;
	}

	function updateEmptyState() {
		var currentWrap = document.getElementById('stationsCardsGrid');
		var pastWrap = document.getElementById('pastStationsCardsGrid');
		var currentEmpty = document.getElementById('currentStationsEmpty');
		var pastEmpty = document.getElementById('pastStationsEmpty');

		if (currentWrap && currentEmpty) {
			currentEmpty.classList.toggle('d-none', currentWrap.children.length > 0);
		}
		if (pastWrap && pastEmpty) {
			pastEmpty.classList.toggle('d-none', pastWrap.children.length > 0);
		}
	}

	function insertCard(containerId, html, sortByName) {
		var wrap = document.getElementById(containerId);
		if (!wrap) {
			return;
		}

		if (!sortByName) {
			wrap.insertAdjacentHTML('afterbegin', html);
			return;
		}

		var probe = document.createElement('div');
		probe.innerHTML = html;
		var newCol = probe.firstElementChild;
		if (!newCol) {
			return;
		}

		var newNameEl = newCol.querySelector('[data-station-name]');
		var newName = (newNameEl ? newNameEl.textContent : '').trim().toLowerCase();
		var inserted = false;
		var children = Array.prototype.slice.call(wrap.children);
		for (var i = 0; i < children.length; i += 1) {
			var existingNameEl = children[i].querySelector('[data-station-name]');
			var existingName = (existingNameEl ? existingNameEl.textContent : '').trim().toLowerCase();
			if (newName < existingName) {
				wrap.insertBefore(newCol, children[i]);
				inserted = true;
				break;
			}
		}

		if (!inserted) {
			wrap.appendChild(newCol);
		}
	}

	function updateCardInPlace(serial, name, desc, scope) {
		var card = findCard(serial, scope || 'active');
		if (!card) {
			return;
		}

		var nameEl = card.querySelector('[data-station-name]');
		var descEl = card.querySelector('[data-station-description]');
		if (nameEl) {
			nameEl.textContent = (name || '').trim() !== '' ? name : serial;
		}
		if (descEl) {
			descEl.textContent = (desc || '').trim() !== '' ? desc : '-';
		}

		var editBtn = card.querySelector('.js-edit-station');
		if (editBtn) {
			editBtn.setAttribute('data-name', String(name || ''));
			editBtn.setAttribute('data-description', String(desc || ''));
		}

		if ((scope || 'active') === 'active') {
			var col = card.closest('.col-12');
			if (col) {
				col.remove();
				insertCard('stationsCardsGrid', col.outerHTML, true);
			}
		}
	}

	function removeCard(serial, scope) {
		var card = findCard(serial, scope);
		if (!card) {
			return;
		}
		var col = card.closest('.col-12');
		if (col) {
			col.remove();
		}
		updateEmptyState();
	}

	function buildActiveCard(data) {
		var serial = escapeHtml(data.serial || '');
		var rawName = (data.name || '').trim() !== '' ? data.name : data.serial;
		var rawDesc = (data.description || '').trim() !== '' ? data.description : '-';
		var name = escapeHtml(rawName);
		var desc = escapeHtml(rawDesc);
		var registeredAt = escapeHtml(formatDateTimeValue(data.registeredAt || ''));

		var measurementsUrl = '/user/measurements.php?station=' + encodeURIComponent(data.serial || '') + '&return_to=' + encodeURIComponent(i18n.returnTo || (window.location.pathname + window.location.search));

		return '' +
			'<div class="col-12 col-sm-6 col-lg-4 col-xl-3">' +
			'<div class="card station-list-card h-100" data-station-card="' + serial + '" data-station-scope="active">' +
			'<div class="card-body d-flex flex-column">' +
			'<div class="d-flex align-items-start justify-content-between gap-2 mb-2">' +
			'<h6 class="mb-0 text-truncate station-card-title" data-station-name>' + name + '</h6>' +
			'<i class="bi bi-broadcast-pin text-primary"></i>' +
			'</div>' +
			'<div class="small text-muted mb-1">' + escapeHtml(i18n.serialLabel) + '</div>' +
			'<div><code class="station-serial-code">' + serial + '</code></div>' +
			'<div class="small text-muted mt-2 mb-1">' + escapeHtml(i18n.descriptionLabel) + '</div>' +
			'<div class="station-list-description" data-station-description>' + desc + '</div>' +
			'<div class="small text-muted mt-2 mb-1">' + escapeHtml(i18n.registeredAtLabel) + '</div>' +
			'<div class="small">' + registeredAt + '</div>' +
			'</div>' +
			'<div class="card-footer bg-transparent border-top-0 pt-1">' +
			'<div class="d-flex gap-2 station-card-actions">' +
			'<button class="btn btn-outline-primary js-edit-station" title="' + escapeHtml(i18n.editLabel) + '" data-serial="' + serial + '" data-name="' + escapeHtml(data.name || '') + '" data-description="' + escapeHtml(data.description || '') + '" data-scope="active"><i class="bi bi-pencil"></i></button>' +
			'<a href="' + measurementsUrl + '" class="btn btn-outline-secondary" title="' + escapeHtml(i18n.measurementsLabel) + '"><i class="bi bi-graph-up"></i></a>' +
			'<form method="post" class="d-inline js-unregister-form" onsubmit="return confirm(\'' + escapeHtml(i18n.confirmDelete) + '\')">' +
			'<input type="hidden" name="action" value="unregister">' +
			'<input type="hidden" name="serial" value="' + serial + '">' +
			'<button type="submit" class="btn btn-outline-danger" title="' + escapeHtml(i18n.deleteLabel) + '"><i class="bi bi-x-circle"></i></button>' +
			'</form>' +
			'</div></div></div></div>';
	}

	function buildPastCard(data) {
		var serial = escapeHtml(data.serial || '');
		var rawName = (data.name || '').trim() !== '' ? data.name : data.serial;
		var rawDesc = (data.description || '').trim() !== '' ? data.description : '-';
		var name = escapeHtml(rawName);
		var desc = escapeHtml(rawDesc);
		var registeredAt = escapeHtml(formatDateTimeValue(data.registeredAt || ''));
		var unregisteredAt = escapeHtml(formatDateTimeValue(data.unregisteredAt || ''));

		var measurementsUrl = '/user/measurements.php?station=' + encodeURIComponent(data.serial || '') + '&return_to=' + encodeURIComponent(i18n.returnTo || (window.location.pathname + window.location.search));

		return '' +
			'<div class="col-12 col-sm-6 col-lg-4 col-xl-3">' +
			'<div class="card station-list-card h-100" data-station-card="' + serial + '" data-station-scope="past">' +
			'<div class="card-body d-flex flex-column">' +
			'<div class="d-flex align-items-start justify-content-between gap-2 mb-2">' +
			'<h6 class="mb-0 text-truncate station-card-title" data-station-name>' + name + '</h6>' +
			'<i class="bi bi-clock-history text-secondary"></i>' +
			'</div>' +
			'<div class="small text-muted mb-1">' + escapeHtml(i18n.serialLabel) + '</div>' +
			'<div><code class="station-serial-code">' + serial + '</code></div>' +
			'<div class="small text-muted mt-2 mb-1">' + escapeHtml(i18n.descriptionLabel) + '</div>' +
			'<div class="station-list-description" data-station-description>' + desc + '</div>' +
			'<div class="small text-muted mt-2 mb-1">' + escapeHtml(i18n.registeredAtLabel) + '</div>' +
			'<div class="small">' + registeredAt + '</div>' +
			'<div class="small text-muted mt-2 mb-1">' + escapeHtml(i18n.unregisteredAtLabel) + '</div>' +
			'<div class="small">' + unregisteredAt + '</div>' +
			'</div>' +
			'<div class="card-footer bg-transparent border-top-0 pt-1">' +
			'<div class="d-flex gap-2 station-card-actions">' +
			'<button class="btn btn-outline-primary js-edit-station" title="' + escapeHtml(i18n.editLabel) + '" data-serial="' + serial + '" data-name="' + escapeHtml(data.name || '') + '" data-description="' + escapeHtml(data.description || '') + '" data-scope="past"><i class="bi bi-pencil"></i></button>' +
			'<a href="' + measurementsUrl + '" class="btn btn-outline-secondary" title="' + escapeHtml(i18n.measurementsLabel) + '"><i class="bi bi-graph-up"></i></a>' +
			'</div></div></div></div>';
	}

	function buildPayload() {
		var serialInput = document.getElementById('editSerial');
		var nameInput = document.getElementById('editName');
		var descInput = document.getElementById('editDesc');
		var scopeInput = document.getElementById('editScope');
		if (!serialInput || !nameInput || !descInput) {
			return null;
		}

		return {
			action: 'update',
			serial: serialInput.value || '',
			scope: scopeInput ? (scopeInput.value || 'active') : 'active',
			name: nameInput.value || '',
			description: descInput.value || ''
		};
	}

	function payloadSignature(payload) {
		return JSON.stringify(payload || {});
	}

	function saveStationEdits() {
		var payload = buildPayload();
		if (!payload || !payload.serial) {
			return;
		}

		var sig = payloadSignature(payload);
		if (sig === lastSavedPayload) {
			return;
		}

		var body = new URLSearchParams(payload);
		return fetch('/user/stations.php', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-Requested-With': 'XMLHttpRequest'
			},
			credentials: 'same-origin',
			body: body.toString()
		})
			.then(function (res) { return res.json(); })
			.then(function (res) {
				if (!res || !res.success || !res.data) {
					throw new Error((res && res.message) || 'Save failed');
				}
				lastSavedPayload = sig;
				updateCardInPlace(res.data.serial, res.data.name, res.data.description, res.data.scope || payload.scope || 'active');
				clearAlerts();
			})
			.catch(function (err) {
				showAlert(err && err.message ? err.message : i18n.defaultError, 'danger');
			});
	}

	function scheduleAutosave() {
		if (autosaveTimer) {
			clearTimeout(autosaveTimer);
		}
		autosaveTimer = setTimeout(function () {
			saveStationEdits();
		}, 400);
	}

	function editStation(serial, name, desc, scope) {
		var serialInput = document.getElementById('editSerial');
		var nameInput = document.getElementById('editName');
		var descInput = document.getElementById('editDesc');
		var scopeInput = document.getElementById('editScope');
		var modalEl = document.getElementById('editModal');

		if (!serialInput || !nameInput || !descInput || !modalEl) {
			return;
		}

		serialInput.value = serial || '';
		nameInput.value = name || '';
		descInput.value = desc || '';
		if (scopeInput) {
			scopeInput.value = scope || 'active';
		}
		lastSavedPayload = payloadSignature(buildPayload());
		clearAlerts('editModal');

		bootstrap.Modal.getOrCreateInstance(modalEl).show();
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest('.js-edit-station');
		if (!btn) {
			return;
		}
		e.preventDefault();
		editStation(
			btn.getAttribute('data-serial') || '',
			btn.getAttribute('data-name') || '',
			btn.getAttribute('data-description') || '',
			btn.getAttribute('data-scope') || 'active'
		);
	});

	document.addEventListener('input', function (e) {
		if (!e.target || !e.target.closest('#editModal')) {
			return;
		}
		if (e.target.id === 'editName' || e.target.id === 'editDesc') {
			scheduleAutosave();
		}
	});

	document.addEventListener('blur', function (e) {
		if (!e.target || !e.target.closest('#editModal')) {
			return;
		}
		if (e.target.id === 'editName' || e.target.id === 'editDesc') {
			saveStationEdits();
		}
	}, true);

	document.addEventListener('submit', function (e) {
		var form = e.target;
		if (form && form.closest('#editModal form')) {
			e.preventDefault();
			saveStationEdits();
			return;
		}

		if (form && form.id === 'registerStationForm') {
			e.preventDefault();
			e.stopPropagation();
			var data = new URLSearchParams(new FormData(form));
			fetch('/user/stations.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest'
				},
				credentials: 'same-origin',
				body: data.toString()
			})
				.then(function (res) { return res.json(); })
				.then(function (res) {
					if (!res || !res.success || !res.data) {
						throw new Error((res && res.message) || 'Register failed');
					}

					removeCard(res.data.serial, 'past');
					removeCard(res.data.serial, 'active');
					insertCard('stationsCardsGrid', buildActiveCard(res.data), true);
					updateEmptyState();
					form.reset();
					var regModalEl = document.getElementById('registerModal');
					if (regModalEl) {
						bootstrap.Modal.getOrCreateInstance(regModalEl).hide();
					}
					clearAlerts();
				})
				.catch(function (err) {
					showAlert(err && err.message ? err.message : i18n.defaultError, 'danger', 'editModal');
				});
			return;
		}

		if (form && form.classList && form.classList.contains('js-unregister-form')) {
			e.preventDefault();
			e.stopPropagation();
			var unregData = new URLSearchParams(new FormData(form));
			var serial = (form.querySelector('input[name="serial"]') || {}).value || '';
			fetch('/user/stations.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest'
				},
				credentials: 'same-origin',
				body: unregData.toString()
			})
				.then(function (res) { return res.json(); })
				.then(function (res) {
					if (!res || !res.success || !res.data) {
						throw new Error((res && res.message) || 'Unregister failed');
					}

					removeCard(serial, 'active');
					removeCard(serial, 'past');
					insertCard('pastStationsCardsGrid', buildPastCard(res.data), false);
					updateEmptyState();
					clearAlerts();
				})
				.catch(function (err) {
					showAlert(err && err.message ? err.message : i18n.defaultError, 'danger', 'registerModal');
				});
		}
	}, true);

	var registerModalEl = document.getElementById('registerModal');
	if (registerModalEl) {
		registerModalEl.addEventListener('show.bs.modal', function () {
			clearAlerts('registerModal');
		});
	}

	var editModalEl = document.getElementById('editModal');
	if (editModalEl) {
		editModalEl.addEventListener('show.bs.modal', function () {
			clearAlerts('editModal');
		});
	}

	var prefillCode = i18nEl ? (i18nEl.getAttribute('data-prefill-code') || '') : '';
	if (prefillCode) {
		var registerForm = document.getElementById('registerStationForm');
		var codeInput = registerForm ? registerForm.querySelector('input[name="code"]') : null;
		if (codeInput) {
			codeInput.value = prefillCode;
		}
		if (registerModalEl) {
			bootstrap.Modal.getOrCreateInstance(registerModalEl).show();
		}
	}

	updateEmptyState();
	window.editStation = editStation;
})();
