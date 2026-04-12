function initMeasurementsClient() {
    if (typeof window.__measurementsCleanup === 'function') {
        window.__measurementsCleanup();
    }

    var cfgEl = document.getElementById('measurementsClientConfig');
    if (!cfgEl) {
        return;
    }

    if (cfgEl.dataset.initialized === '1') {
        return;
    }
    cfgEl.dataset.initialized = '1';

    var config = {};
    try {
        config = JSON.parse(cfgEl.textContent || '{}');
    } catch (e) {
        config = {};
    }

    var apiEndpoint = String(config.apiEndpoint || '/api/measurements.php');
    var pagePath = String(config.pagePath || '/user/measurements.php');
    var adminMode = !!config.adminMode;
    var showOwnerColumns = !!config.showOwnerColumns;

    var currentPage = Number(config.page || 1);
    var perPage = Number(config.perPage || 20);
    var pollTimer = null;
    var chartPollTimer = null;
    var chartInstance = null;
    var selectedChartMetric = 'temperature';
    var paginationTemplate = String(config.paginationTemplate || 'Showing {from} to {to} of {total}');
    var noDataText = String(config.noDataText || 'No measurements found');
    var chartStationLabel = String(config.chartStationLabel || 'Station');
    var chartStationLimitText = String(config.chartStationLimitText || 'Up to {count} stations at once');
    var chartStationSearchPlaceholder = String(config.chartStationSearchPlaceholder || 'Search stations...');
    var chartStationNoResultsText = String(config.chartStationNoResultsText || 'No stations found');
    var CHART_COLORS = ['#FF0000', '#FFFF00', '#00FF00', '#0000FF', '#FF00FF', '#00FFFF'];
    var chartDataCache = {
        temperature: [],
        airPressure: [],
        lightIntensity: [],
        airQuality: []
    };
    var chartOwnershipMapCache = {};
    var chartOwnershipTransitions = [];
    var chartHoveredOwnershipTransition = null;
    var isChartLoading = false;
    var pendingChartReload = false;
    var chartDataLoaded = {
        temperature: false,
        airPressure: false,
        lightIntensity: false,
        airQuality: false
    };
    var chartConfigMap = (config.chartConfigMap && typeof config.chartConfigMap === 'object')
        ? config.chartConfigMap
        : {
            temperature: { label: 'Temperature (degC)', yTitle: 'Temperature (degC)', metricKey: 'temperature' },
            airPressure: { label: 'Air Pressure (hPa)', yTitle: 'Air Pressure (hPa)', metricKey: 'airPressure' },
            lightIntensity: { label: 'Light Intensity (lux)', yTitle: 'Light Intensity (lux)', metricKey: 'lightIntensity' },
            airQuality: { label: 'Air Quality (ppm)', yTitle: 'Air Quality (ppm)', metricKey: 'airQuality' }
        };
    var CHART_STATION_LIMIT = 6;
    var selectedChartStations = {};
    var userManuallyChangedStationSelection = false;
    var chartStationSearchQuery = '';
    var chartStationPickerCollapse = null;
    var chartStationColorAssignments = {};
    var lastRowsRenderSignature = '';
    var measurementFiltersStateKey = 'measurements.filters.state.v1';
    var collectionOptions = Array.isArray(config.collectionOptions) ? config.collectionOptions : [];
    var stationOptions = Array.isArray(config.stationOptions) ? config.stationOptions : [];
    var collectionStationsMap = (config.collectionStationsMap && typeof config.collectionStationsMap === 'object')
        ? config.collectionStationsMap
        : {};
    var filterNoResultsText = String(config.filterNoResultsText || 'No results found');
    var uiText = (config.uiText && typeof config.uiText === 'object') ? config.uiText : {};
    var confirmDeleteOneText = String(uiText.confirmDeleteOne || 'Delete this measurement?');
    var confirmDeleteSelectedText = String(uiText.confirmDeleteSelected || 'Delete selected measurements?');

    function normalizeFilterDateTimeForApi(value) {
        var raw = String(value || '').trim();
        if (!raw) {
            return '';
        }

        var eu = raw.match(/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})$/);
        if (eu) {
            return eu[3] + '-' + eu[2] + '-' + eu[1] + ' ' + eu[4] + ':' + eu[5];
        }

        return raw;
    }

    function normalizeFilterDateToFilename(value) {
        var normalized = normalizeFilterDateTimeForApi(value);
        var m = String(normalized || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            return m[3] + m[2] + m[1];
        }

        m = String(value || '').match(/^(\d{2})\.(\d{2})\.(\d{4})/);
        return m ? (m[1] + m[2] + m[3]) : '';
    }

    function initMeasurementDateTimePickers() {
        if (!window.jQuery || !jQuery.fn.datetimepicker) {
            return;
        }

        jQuery('.js-measurement-datetime').each(function () {
            var $input = jQuery(this);
            if ($input.data('dtp-initialized')) {
                return;
            }

            $input.datetimepicker({
                format: 'd.m.Y H:i',
                step: 5,
                dayOfWeekStart: 1,
                scrollInput: false,
                closeOnDateSelect: false
            });
            $input.data('dtp-initialized', true);
        });

        jQuery('.measurement-picker-icon').off('click.measurementPicker').on('click.measurementPicker', function () {
            var $icon = jQuery(this);
            var $input = $icon.siblings('input.js-measurement-datetime').first();
            if (!$input.length) {
                $input = $icon.closest('.input-group').find('input.js-measurement-datetime').first();
            }
            if ($input.length) {
                $input.trigger('focus');
                try {
                    $input.datetimepicker('show');
                } catch (e) {
                    // Keep focus fallback when explicit show is unavailable.
                }
            }
        });
    }

    function initMeasurementComboFilters() {
        var ITEM_HEIGHT = 34;
        var BUFFER_ITEMS = 6;

        function normalizeOptions(options) {
            return options
                .map(function (item) {
                    return {
                        value: String((item && item.value) || '').trim(),
                        label: String((item && item.label) || '').trim()
                    };
                })
                .filter(function (item) {
                    return item.value !== '' && item.label !== '';
                });
        }

        var normalizedCollections = normalizeOptions(collectionOptions);
        var normalizedStations = normalizeOptions(stationOptions);
        var stationByValue = {};
        normalizedStations.forEach(function (item) {
            stationByValue[item.value] = item;
        });

        var collectionSetById = {};
        Object.keys(collectionStationsMap).forEach(function (collectionId) {
            var list = Array.isArray(collectionStationsMap[collectionId]) ? collectionStationsMap[collectionId] : [];
            var set = {};
            list.forEach(function (serial) {
                var key = String(serial || '').trim();
                if (key) {
                    set[key] = true;
                }
            });
            collectionSetById[String(collectionId)] = set;
        });

        var collectionValue = document.getElementById('measurementCollectionValue');
        var stationValue = document.getElementById('measurementStationValue');
        if (!collectionValue || !stationValue) {
            return;
        }

        function getStationOptionsForSelectedCollection() {
            var selectedCollectionId = String(collectionValue.value || '').trim();
            if (!selectedCollectionId) {
                return normalizedStations.slice();
            }

            var allowedSet = collectionSetById[selectedCollectionId] || {};
            return normalizedStations.filter(function (item) {
                return !!allowedSet[item.value];
            });
        }

        function createCombo(state) {
            var input = document.getElementById(state.inputId);
            var valueEl = document.getElementById(state.valueId);
            var toggleBtn = document.getElementById(state.toggleId);
            var panel = document.getElementById(state.panelId);
            var viewport = document.getElementById(state.viewportId);

            if (!input || !valueEl || !toggleBtn || !panel || !viewport) {
                return null;
            }

            var listRoot = document.createElement('div');
            listRoot.style.position = 'relative';
            listRoot.style.minHeight = ITEM_HEIGHT + 'px';
            viewport.innerHTML = '';
            viewport.appendChild(listRoot);

            state.input = input;
            state.valueEl = valueEl;
            state.toggleBtn = toggleBtn;
            state.panel = panel;
            state.viewport = viewport;
            state.listRoot = listRoot;
            state.isOpen = false;
            state.filtered = [];
            state.beforeOpen = null;

            function getOptions() {
                var raw = state.getOptions();
                return Array.isArray(raw) ? raw : [];
            }

            function getQuery() {
                return String(input.value || '').trim().toLowerCase();
            }

            function getFilteredOptions() {
                var query = getQuery();
                return getOptions().filter(function (item) {
                    return query === '' || item.label.toLowerCase().indexOf(query) !== -1;
                });
            }

            function setSelected(item) {
                if (!item) {
                    valueEl.value = '';
                    input.value = '';
                } else {
                    valueEl.value = item.value;
                    input.value = item.label;
                }

                valueEl.dispatchEvent(new Event('change', { bubbles: true }));
                close();
            }

            function ensureSelectionVisible() {
                var selectedValue = String(valueEl.value || '').trim();
                if (!selectedValue) {
                    return;
                }
                var selectedItem = getOptions().find(function (item) {
                    return item.value === selectedValue;
                });
                if (selectedItem) {
                    input.value = selectedItem.label;
                } else {
                    valueEl.value = '';
                    input.value = '';
                }
            }

            function renderViewport() {
                state.filtered = getFilteredOptions();
                var total = state.filtered.length;
                var scrollTop = viewport.scrollTop;
                var viewportHeight = viewport.clientHeight || 220;

                listRoot.innerHTML = '';
                listRoot.style.height = Math.max(total * ITEM_HEIGHT, ITEM_HEIGHT) + 'px';

                if (!total) {
                    var emptyRow = document.createElement('div');
                    emptyRow.className = 'px-2 py-1 text-muted small';
                    emptyRow.style.position = 'absolute';
                    emptyRow.style.top = '0';
                    emptyRow.style.left = '0';
                    emptyRow.style.right = '0';
                    emptyRow.textContent = filterNoResultsText;
                    listRoot.appendChild(emptyRow);
                    return;
                }

                var start = Math.max(0, Math.floor(scrollTop / ITEM_HEIGHT) - BUFFER_ITEMS);
                var end = Math.min(total, Math.ceil((scrollTop + viewportHeight) / ITEM_HEIGHT) + BUFFER_ITEMS);
                var selectedValue = String(valueEl.value || '').trim();

                for (var i = start; i < end; i++) {
                    var item = state.filtered[i];
                    var rowBtn = document.createElement('button');
                    rowBtn.type = 'button';
                    rowBtn.className = 'btn btn-sm text-start w-100 rounded-0 border-0 px-2';
                    if (item.value === selectedValue) {
                        rowBtn.classList.add('btn-primary');
                    } else {
                        rowBtn.classList.add('btn-light');
                    }
                    rowBtn.style.position = 'absolute';
                    rowBtn.style.top = (i * ITEM_HEIGHT) + 'px';
                    rowBtn.style.left = '0';
                    rowBtn.style.right = '0';
                    rowBtn.style.height = ITEM_HEIGHT + 'px';
                    rowBtn.textContent = item.label;
                    rowBtn.addEventListener('mousedown', function (evt) {
                        evt.preventDefault();
                    });
                    rowBtn.addEventListener('click', (function (nextItem) {
                        return function () {
                            setSelected(nextItem);
                        };
                    })(item));
                    listRoot.appendChild(rowBtn);
                }
            }

            function open() {
                if (typeof state.beforeOpen === 'function') {
                    state.beforeOpen();
                }
                if (state.isOpen) {
                    renderViewport();
                    return;
                }
                state.isOpen = true;
                panel.classList.remove('d-none');
                viewport.scrollTop = 0;
                renderViewport();
            }

            function close() {
                state.isOpen = false;
                panel.classList.add('d-none');
            }

            input.addEventListener('focus', function () {
                open();
            });
            input.addEventListener('input', function () {
                valueEl.value = '';
                open();
                renderViewport();
            });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var first = getFilteredOptions()[0] || null;
                    setSelected(first);
                    return;
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    close();
                }
            });

            toggleBtn.addEventListener('click', function () {
                if (state.isOpen) {
                    close();
                } else {
                    input.focus();
                    open();
                }
            });

            viewport.addEventListener('scroll', function () {
                if (state.isOpen) {
                    renderViewport();
                }
            });

            state.open = open;
            state.close = close;
            state.render = renderViewport;
            state.ensureSelectionVisible = ensureSelectionVisible;
            state.setSelected = setSelected;

            return state;
        }

        var collectionCombo = createCombo({
            inputId: 'measurementCollectionInput',
            valueId: 'measurementCollectionValue',
            toggleId: 'measurementCollectionToggleBtn',
            panelId: 'measurementCollectionComboPanel',
            viewportId: 'measurementCollectionViewport',
            getOptions: function () {
                return normalizedCollections;
            }
        });

        var stationCombo = createCombo({
            inputId: 'measurementStationInput',
            valueId: 'measurementStationValue',
            toggleId: 'measurementStationToggleBtn',
            panelId: 'measurementStationComboPanel',
            viewportId: 'measurementStationViewport',
            getOptions: function () {
                return getStationOptionsForSelectedCollection();
            }
        });

        if (!collectionCombo || !stationCombo) {
            return;
        }

        collectionCombo.beforeOpen = function () {
            stationCombo.close();
        };
        stationCombo.beforeOpen = function () {
            collectionCombo.close();
        };

        collectionValue.addEventListener('change', function () {
            var currentStation = String(stationValue.value || '').trim();
            if (currentStation) {
                var allowedNow = getStationOptionsForSelectedCollection().some(function (st) {
                    return st.value === currentStation;
                });
                if (!allowedNow) {
                    stationValue.value = '';
                    if (stationCombo.input) {
                        stationCombo.input.value = '';
                    }
                    stationValue.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            stationCombo.ensureSelectionVisible();
            if (stationCombo.isOpen) {
                stationCombo.render();
            }
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#measurementCollectionComboPanel') && !e.target.closest('#measurementCollectionInput') && !e.target.closest('#measurementCollectionToggleBtn')) {
                collectionCombo.close();
            }

            if (!e.target.closest('#measurementStationComboPanel') && !e.target.closest('#measurementStationInput') && !e.target.closest('#measurementStationToggleBtn')) {
                stationCombo.close();
            }
        });

        collectionCombo.ensureSelectionVisible();
        stationCombo.ensureSelectionVisible();

        var currentStation = String(stationValue.value || '').trim();
        if (currentStation && !getStationOptionsForSelectedCollection().some(function (st) { return st.value === currentStation; })) {
            stationValue.value = '';
            if (stationCombo.input) {
                stationCombo.input.value = '';
            }
        }
    }

    function saveMeasurementFiltersState() {
        var form = document.getElementById('measurementFiltersForm');
        if (!form || !window.sessionStorage) {
            return;
        }

        function getFieldValue(name) {
            var field = form.querySelector('[name="' + name + '"]');
            return field ? String(field.value || '') : '';
        }

        function getCheckedValues(name) {
            return Array.from(form.querySelectorAll('input[type="checkbox"][name="' + name + '"]:checked')).map(function (cb) {
                return String(cb.value || '').trim();
            }).filter(function (v) {
                return v !== '';
            });
        }

        var payload = {
            measurement_id: getFieldValue('measurement_id'),
            station: getFieldValue('station'),
            station_list: getCheckedValues('station[]'),
            collection: getFieldValue('collection'),
            collection_list: getCheckedValues('collection[]'),
            owner_id: getFieldValue('owner_id'),
            owner_id_list: getCheckedValues('owner_id[]'),
            date_from: getFieldValue('date_from'),
            date_to: getFieldValue('date_to'),
            ts: Date.now()
        };

        try {
            sessionStorage.setItem(measurementFiltersStateKey, JSON.stringify(payload));
        } catch (e) {
            // Ignore storage write errors.
        }
    }

    function clearMeasurementFiltersState() {
        if (!window.sessionStorage) {
            return;
        }
        try {
            sessionStorage.removeItem(measurementFiltersStateKey);
        } catch (e) {
            // Ignore storage remove errors.
        }
    }

    function restoreMeasurementFiltersStateIfNeeded() {
        var form = document.getElementById('measurementFiltersForm');
        if (!form || !window.sessionStorage) {
            return false;
        }

        var stationField = form.querySelector('[name="station"]');
        var collectionField = form.querySelector('[name="collection"]');
        var measurementIdField = form.querySelector('[name="measurement_id"]');
        var ownerField = form.querySelector('[name="owner_id"]');
        var dateFromField = form.querySelector('[name="date_from"]');
        var dateToField = form.querySelector('[name="date_to"]');
        if (!dateFromField || !dateToField) {
            return false;
        }

        var stationChecks = form.querySelectorAll('input[type="checkbox"][name="station[]"]');
        var collectionChecks = form.querySelectorAll('input[type="checkbox"][name="collection[]"]');
        var ownerChecks = form.querySelectorAll('input[type="checkbox"][name="owner_id[]"]');

        var hasInitialValues = !!(
            (measurementIdField && measurementIdField.value)
            || (stationField && stationField.value)
            || (collectionField && collectionField.value)
            || (ownerField && ownerField.value)
            || dateFromField.value
            || dateToField.value
            || Array.from(stationChecks).some(function (cb) { return cb.checked; })
            || Array.from(collectionChecks).some(function (cb) { return cb.checked; })
            || Array.from(ownerChecks).some(function (cb) { return cb.checked; })
        );
        if (hasInitialValues) {
            return false;
        }

        var raw = '';
        try {
            raw = sessionStorage.getItem(measurementFiltersStateKey) || '';
        } catch (e) {
            return false;
        }
        if (!raw) {
            return false;
        }

        var payload;
        try {
            payload = JSON.parse(raw);
        } catch (e) {
            return false;
        }
        if (!payload || typeof payload !== 'object') {
            return false;
        }

        if (measurementIdField) {
            measurementIdField.value = String(payload.measurement_id || '');
        }
        if (stationField) {
            stationField.value = String(payload.station || '');
        }
        if (collectionField) {
            collectionField.value = String(payload.collection || '');
        }
        if (ownerField) {
            ownerField.value = String(payload.owner_id || '');
        }

        var stationSet = Array.isArray(payload.station_list) ? payload.station_list.reduce(function (acc, v) { acc[String(v)] = true; return acc; }, {}) : {};
        var collectionSet = Array.isArray(payload.collection_list) ? payload.collection_list.reduce(function (acc, v) { acc[String(v)] = true; return acc; }, {}) : {};
        var ownerSet = Array.isArray(payload.owner_id_list) ? payload.owner_id_list.reduce(function (acc, v) { acc[String(v)] = true; return acc; }, {}) : {};
        stationChecks.forEach(function (cb) { cb.checked = !!stationSet[String(cb.value || '')]; });
        collectionChecks.forEach(function (cb) { cb.checked = !!collectionSet[String(cb.value || '')]; });
        ownerChecks.forEach(function (cb) { cb.checked = !!ownerSet[String(cb.value || '')]; });

        dateFromField.value = String(payload.date_from || '');
        dateToField.value = String(payload.date_to || '');
        return true;
    }

    async function getJson(url) {
        var response = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        });
        if (!response.ok) {
            throw new Error('GET ' + url + ' failed: ' + response.status);
        }
        return response.json();
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDateTime(value) {
        if (!value) return '-';
        var raw = String(value).trim();
        var parsed = new Date(raw.replace(' ', 'T'));

        if (!isNaN(parsed.getTime())) {
            return pad2(parsed.getDate()) + '.' + pad2(parsed.getMonth() + 1) + '.' + parsed.getFullYear()
                + ' ' + pad2(parsed.getHours()) + ':' + pad2(parsed.getMinutes());
        }

        var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/);
        if (m) {
            return m[3] + '.' + m[2] + '.' + m[1] + (m[4] ? (' ' + m[4] + ':' + m[5]) : '');
        }

        return raw;
    }

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function formatChartTimestamp(value) {
        if (!value) return '';
        var raw = String(value).trim();
        var parsed = new Date(raw.replace(' ', 'T'));

        if (!isNaN(parsed.getTime())) {
            return pad2(parsed.getDate()) + '.' + pad2(parsed.getMonth() + 1) + '.' + parsed.getFullYear()
                + ' ' + pad2(parsed.getHours()) + ':' + pad2(parsed.getMinutes());
        }

        var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/);
        if (m) {
            return m[3] + '.' + m[2] + '.' + m[1] + (m[4] ? (' ' + m[4] + ':' + m[5]) : '');
        }

        return raw;
    }

    function sanitizeFilePart(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'value';
    }

    function formatDateInputForFilename(value) {
        return normalizeFilterDateToFilename(value);
    }

    function getSelectedFilterLabel(fieldName) {
        var field = document.querySelector('#measurementFiltersForm [name="' + fieldName + '"]');
        if (field && field.value) {
            var option = field.options ? field.options[field.selectedIndex] : null;
            return option ? option.text.trim() : '';
        }

        var checked = document.querySelector('#measurementFiltersForm input[type="checkbox"][name="' + fieldName + '[]"]:checked');
        if (!checked) {
            return '';
        }

        var label = checked.closest('label');
        if (!label) {
            return '';
        }

        var text = label.textContent || '';
        return String(text).trim();
    }

    function buildFilenameFilterSuffix() {
        var parts = [];
        var collectionLabel = getSelectedFilterLabel('collection');
        var stationLabel = getSelectedFilterLabel('station');

        if (collectionLabel) {
            parts.push('col_' + sanitizeFilePart(collectionLabel));
        }
        if (stationLabel) {
            parts.push('st_' + sanitizeFilePart(stationLabel));
        }

        var dateFromField = document.querySelector('#measurementFiltersForm [name="date_from"]');
        var dateToField = document.querySelector('#measurementFiltersForm [name="date_to"]');
        var from = formatDateInputForFilename(dateFromField ? dateFromField.value : '');
        var to = formatDateInputForFilename(dateToField ? dateToField.value : '');

        if (from) {
            parts.push('f' + from);
        }
        if (to) {
            parts.push('t' + to);
        }

        return parts.length ? ('_' + parts.join('_')) : '';
    }

    function formatPaginationInfo(pagination) {
        return paginationTemplate
            .replace('{from}', pagination.from)
            .replace('{to}', pagination.to)
            .replace('{total}', pagination.total);
    }

    function getFilterParams() {
        var form = document.getElementById('measurementFiltersForm');
        var formData = new FormData(form);
        var params = new URLSearchParams();

        formData.forEach(function (value, key) {
            if (key === 'per_page') {
                return;
            }
            if (value !== '') {
                var isArrayKey = /\[\]$/.test(key);
                if (key === 'date_from' || key === 'date_to') {
                    if (isArrayKey) {
                        params.append(key, normalizeFilterDateTimeForApi(value));
                    } else {
                        params.set(key, normalizeFilterDateTimeForApi(value));
                    }
                } else {
                    if (isArrayKey) {
                        params.append(key, value);
                    } else {
                        params.set(key, value);
                    }
                }
            }
        });

        return params;
    }

    function updateUrlState() {
        var params = getFilterParams();
        params.set('page', String(currentPage));
        params.set('per_page', String(perPage));
        window.history.replaceState({}, '', pagePath + '?' + params.toString());
    }

    function updateExportLink() {
        var params = getFilterParams();
        params.set('export', 'csv');
        var href = '?' + params.toString();

        ['chartExportCsvBtn', 'dataExportCsvBtn'].forEach(function (id) {
            var exportLink = document.getElementById(id);
            if (exportLink) {
                exportLink.setAttribute('href', href);
            }
        });
    }

    function getDefaultMetricKey() {
        var keys = Object.keys(chartConfigMap);
        return keys.length ? keys[0] : 'temperature';
    }

    function ensureSelectedMetric() {
        if (!chartConfigMap[selectedChartMetric]) {
            selectedChartMetric = getDefaultMetricKey();
        }
        updateActiveMetricButtons();
    }

    function updateActiveMetricButtons() {
        var buttons = document.querySelectorAll('#chartMetricButtons [data-metric]');
        buttons.forEach(function (btn) {
            if (btn.getAttribute('data-metric') === selectedChartMetric) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    function buildRowHtml(row) {
        var rowId = String(row.pk_measurementID || '').trim();
        var temperature = row.temperature !== null ? escapeHtml(row.temperature) + ' &deg;C' : '-';
        var pressure = row.airPressure !== null ? escapeHtml(row.airPressure) + ' hPa' : '-';
        var light = row.lightIntensity !== null ? escapeHtml(row.lightIntensity) + ' lux' : '-';
        var airQuality = row.airQuality !== null ? escapeHtml(row.airQuality) + ' ppm' : '-';
        var stationName = escapeHtml(row.station_name || row.fk_station);
        var ownerId = escapeHtml(row.fk_ownerId || '');
        var ownerName = escapeHtml(row.owner_name || row.fk_ownerId || '');

        var ownerCell = '<span class="text-muted">-</span>';
        if (row.fk_ownerId) {
            ownerCell = '<div class="collection-card-shares justify-content-center"><a class="collection-share-item admin-shared-mini" href="/user/view_profile.php?user=' + encodeURIComponent(String(row.fk_ownerId)) + '&admin_view=1" title="' + ownerName + '">' +
                '<span class="collection-share-avatar"><i class="bi bi-person-circle"></i></span>' +
                '<span class="collection-share-username">@' + ownerId + '</span></a></div>';
        }

        var rawPayload = encodeURIComponent(JSON.stringify({
            pk_measurementID: row.pk_measurementID,
            timestamp: row.timestamp,
            temperature: row.temperature,
            airPressure: row.airPressure,
            lightIntensity: row.lightIntensity,
            airQuality: row.airQuality
        }));

        var html = '<tr>';

        if (adminMode) {
            html += '<td><input type="checkbox" class="form-check-input js-measurement-row-check" value="' + escapeHtml(rowId) + '"></td>' +
                '<td>' + escapeHtml(rowId) + '</td>';
        }

        html += '<td>' + escapeHtml(formatDateTime(row.timestamp)) + '</td>' +
            '<td>' + stationName + '</td>';

        if (showOwnerColumns) {
            html += '<td>' + ownerCell + '</td>';
        }

        html += '<td>' + temperature + '</td>' +
            '<td>' + pressure + '</td>' +
            '<td>' + light + '</td>' +
            '<td>' + airQuality + '</td>';

        if (adminMode) {
            html += '<td><div class="admin-actions-row">' +
                '<button type="button" class="btn btn-sm btn-outline-primary js-measurement-edit" data-measurement="' + rawPayload + '" title="Edit" aria-label="Edit"><i class="bi bi-pencil"></i></button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger js-measurement-delete" data-id="' + escapeHtml(rowId) + '" title="Delete" aria-label="Delete"><i class="bi bi-trash"></i></button>' +
                '</div></td>';
        }

        html += '</tr>';

        return html;
    }

    function renderRows(rows) {
        var tbody = document.getElementById('measurementsTableBody');
        var tableWrap = document.getElementById('measurementsTableWrap');
        var emptyAlert = document.getElementById('noMeasurementsAlert');
        var scrollHint = document.getElementById('measurementsScrollHint');

        var nextSignature = JSON.stringify(rows || []);
        if (!rows.length) {
            tbody.innerHTML = '';
            lastRowsRenderSignature = nextSignature;
            tableWrap.classList.add('d-none');
            emptyAlert.classList.remove('d-none');
            emptyAlert.textContent = noDataText;
            if (scrollHint) {
                scrollHint.classList.add('d-none');
            }
            return;
        }

        if (lastRowsRenderSignature === nextSignature) {
            tableWrap.classList.remove('d-none');
            emptyAlert.classList.add('d-none');
            if (scrollHint) {
                scrollHint.classList.remove('d-none');
            }
            return;
        }

        tbody.innerHTML = rows.map(buildRowHtml).join('');
        lastRowsRenderSignature = nextSignature;
        tableWrap.classList.remove('d-none');
        emptyAlert.classList.add('d-none');
        if (scrollHint) {
            scrollHint.classList.remove('d-none');
        }
    }

    function buildPaginationLink(page) {
        var params = getFilterParams();
        params.set('page', page);
        params.set('per_page', perPage);
        return '?' + params.toString();
    }

    function renderPagination(pagination) {
        var nav = document.getElementById('measurementsPaginationNav');
        var list = document.getElementById('measurementsPaginationList');
        var info = document.getElementById('paginationInfoText');
        info.textContent = formatPaginationInfo(pagination);

        if (pagination.total_pages <= 1) {
            nav.classList.add('d-none');
            list.innerHTML = '';
            return;
        }

        var html = '';
        for (var i = 1; i <= pagination.total_pages; i++) {
            var active = i === pagination.page ? ' active' : '';
            html += '<li class="page-item' + active + '"><a class="page-link measurement-page-link" data-page="' + i + '" href="' + buildPaginationLink(i) + '">' + i + '</a></li>';
        }

        nav.classList.remove('d-none');
        list.innerHTML = html;
    }

    function hexToRgba(hex, alpha) {
        var raw = String(hex || '').replace('#', '').trim();
        if (raw.length !== 6) {
            return 'rgba(0,0,0,' + alpha + ')';
        }

        var r = parseInt(raw.slice(0, 2), 16);
        var g = parseInt(raw.slice(2, 4), 16);
        var b = parseInt(raw.slice(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function getSeriesColorByIndex(index, alpha) {
        var color = CHART_COLORS[index % CHART_COLORS.length];
        if (alpha >= 1) {
            return color;
        }
        return hexToRgba(color, alpha);
    }

    function isMobileView() {
        return window.matchMedia && window.matchMedia('(max-width: 576px)').matches;
    }

    function formatXAxisTickLabel(label) {
        var text = String(label || '');
        if (!isMobileView()) {
            return text;
        }

        // Mobile: dd.mm.yyyy hh:mm -> dd.mm hh:mm
        var m = text.match(/^(\d{2}\.\d{2})\.\d{4}\s+(\d{2}:\d{2})$/);
        if (m) {
            return m[1] + ' ' + m[2];
        }

        return text;
    }

    function truncateLegendText(text, maxLen) {
        var src = String(text || '');
        if (src.length <= maxLen) {
            return src;
        }
        return src.slice(0, maxLen - 3) + '...';
    }

    function computeLegendDynamicPadding(chart, datasets) {
        if (!chart || !chart.ctx || !Array.isArray(datasets) || datasets.length === 0) {
            return isMobileView() ? 16 : 22;
        }

        var mobile = isMobileView();
        var cols = mobile ? 3 : Math.min(3, datasets.length);
        var slotWidth = Math.max(80, (chart.width || 0) / Math.max(1, cols));
        var fontSize = mobile ? 12 : 13;
        var pointWidth = mobile ? 12 : 14;
        var gap = 8;
        var maxLen = mobile ? 8 : 18;
        var maxTextWidth = 0;

        chart.ctx.save();
        chart.ctx.font = fontSize + 'px sans-serif';
        datasets.forEach(function (ds) {
            var txt = truncateLegendText(ds.label || '', maxLen);
            var w = chart.ctx.measureText(txt).width;
            if (w > maxTextWidth) {
                maxTextWidth = w;
            }
        });
        chart.ctx.restore();

        var innerWidth = pointWidth + gap + maxTextWidth;
        var computed = Math.floor((slotWidth - innerWidth) / 2);
        return Math.max(8, Math.min(32, computed));
    }

    function getChartStationPickerCollapse() {
        if (chartStationPickerCollapse) {
            return chartStationPickerCollapse;
        }

        var panel = document.getElementById('chartStationPickerPanel');
        if (!panel || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
            return null;
        }

        chartStationPickerCollapse = bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false });
        return chartStationPickerCollapse;
    }

    function updateStationColorAssignments(activeStationKeys) {
        var activeMap = {};
        activeStationKeys.forEach(function (key) {
            activeMap[key] = true;
        });

        Object.keys(chartStationColorAssignments).forEach(function (key) {
            if (!activeMap[key]) {
                delete chartStationColorAssignments[key];
            }
        });

        var used = {};
        Object.keys(chartStationColorAssignments).forEach(function (key) {
            var idx = chartStationColorAssignments[key];
            if (idx >= 0 && idx < CHART_COLORS.length) {
                used[idx] = true;
            }
        });

        activeStationKeys.forEach(function (key) {
            if (chartStationColorAssignments[key] !== undefined) {
                return;
            }

            var freeIdx = -1;
            for (var i = 0; i < CHART_COLORS.length; i++) {
                if (!used[i]) {
                    freeIdx = i;
                    break;
                }
            }

            if (freeIdx === -1) {
                freeIdx = 0;
            }

            chartStationColorAssignments[key] = freeIdx;
            used[freeIdx] = true;
        });
    }

    function getAvailableChartStations(chartRows) {
        var map = {};
        (chartRows || []).forEach(function (row) {
            var key = String(row.fk_station || '').trim();
            if (!key) return;
            if (!map[key]) {
                map[key] = {
                    key: key,
                    name: String(row.station_name || key)
                };
            }
        });

        return Object.keys(map).map(function (k) {
            return map[k];
        }).sort(function (a, b) {
            return a.name.localeCompare(b.name);
        });
    }

    function countSelectedStations(stations) {
        var count = 0;
        stations.forEach(function (s) {
            if (selectedChartStations[s.key]) {
                count += 1;
            }
        });
        return count;
    }

    function ensureStationSelection(stations) {
        if (!stations.length) {
            selectedChartStations = {};
            return;
        }

        // Drop selections that are no longer present in current dataset.
        var validKeys = {};
        stations.forEach(function (s) {
            validKeys[s.key] = true;
        });
        Object.keys(selectedChartStations).forEach(function (key) {
            if (!validKeys[key]) {
                delete selectedChartStations[key];
            }
        });

        var currentlySelected = countSelectedStations(stations);

        if (stations.length <= CHART_STATION_LIMIT) {
            if (currentlySelected === 0 && !userManuallyChangedStationSelection) {
                stations.forEach(function (s) {
                    selectedChartStations[s.key] = true;
                });
            }
            return;
        }

        // Enforce hard limit for cases when selection was previously made with <= limit.
        if (currentlySelected > CHART_STATION_LIMIT) {
            var keep = 0;
            stations.forEach(function (s) {
                if (!selectedChartStations[s.key]) {
                    return;
                }

                if (keep < CHART_STATION_LIMIT) {
                    keep += 1;
                    return;
                }

                delete selectedChartStations[s.key];
            });
            currentlySelected = countSelectedStations(stations);
        }

        if (currentlySelected > 0) {
            return;
        }

        if (userManuallyChangedStationSelection) {
            return;
        }

        selectedChartStations = {};
        stations.slice(0, CHART_STATION_LIMIT).forEach(function (s) {
            selectedChartStations[s.key] = true;
        });
    }

    function filterChartRowsForSelection(chartRows) {
        var stations = getAvailableChartStations(chartRows);
        ensureStationSelection(stations);

        return (chartRows || []).filter(function (row) {
            var key = String(row.fk_station || '').trim();
            return !!selectedChartStations[key];
        });
    }

    function renderChartStationPicker(stations) {
        var wrap = document.getElementById('chartStationPickerWrap');
        var menu = document.getElementById('chartStationPickerMenu');
        var btn = document.getElementById('chartStationPickerBtn');
        var hint = document.getElementById('chartStationPickerHint');
        if (!wrap || !menu || !btn || !hint) {
            return;
        }

        if (!stations.length || stations.length <= 1) {
            wrap.classList.add('d-none');
            menu.innerHTML = '';
            hint.textContent = '';
            var collapse = getChartStationPickerCollapse();
            if (collapse) {
                collapse.hide();
            }
            return;
        }

        wrap.classList.remove('d-none');
        var selectedCount = countSelectedStations(stations);
        btn.textContent = chartStationLabel + ' (' + selectedCount + '/' + CHART_STATION_LIMIT + ')';
        hint.textContent = chartStationLimitText.replace('{count}', String(CHART_STATION_LIMIT));

        var query = chartStationSearchQuery.trim().toLowerCase();
        var html = '';
        html += '<div class="mb-2">';
        html += '<input type="text" class="form-control form-control-sm" id="chartStationSearchInput" placeholder="' + escapeHtml(chartStationSearchPlaceholder) + '" value="' + escapeHtml(chartStationSearchQuery) + '">';
        html += '</div>';
        html += '<div id="chartStationPickerList" style="max-height:160px;overflow-y:auto;">';

        var visibleCount = 0;
        stations.forEach(function (s) {
            var stationName = String(s.name || s.key);
            var stationNameLower = stationName.toLowerCase();
            var stationKeyLower = String(s.key || '').toLowerCase();
            if (query && stationNameLower.indexOf(query) === -1 && stationKeyLower.indexOf(query) === -1) {
                return;
            }

            var safeId = 'chartStation_' + s.key.replace(/[^a-zA-Z0-9_-]/g, '_');
            var checked = selectedChartStations[s.key] ? 'checked' : '';
            html += '<div class="form-check mb-1 chart-station-item px-1 py-1 rounded" data-station-key="' + escapeHtml(s.key) + '" style="cursor:pointer;">';
            html += '<input class="chart-station-check me-2" type="checkbox" value="' + escapeHtml(s.key) + '" id="' + escapeHtml(safeId) + '" ' + checked + ' style="width:1rem;height:1rem;accent-color:#0d6efd;vertical-align:middle;">';
            html += '<label class="form-check-label chart-station-label" for="' + escapeHtml(safeId) + '">' + escapeHtml(stationName) + '</label>';
            html += '</div>';
            visibleCount += 1;
        });

        if (visibleCount === 0) {
            html += '<div class="text-muted small py-1">' + escapeHtml(chartStationNoResultsText) + '</div>';
        }

        html += '</div>';
        menu.innerHTML = html;

        var firstSelected = menu.querySelector('.chart-station-check:checked');
        if (firstSelected && firstSelected.scrollIntoView) {
            firstSelected.scrollIntoView({ block: 'nearest' });
        }
    }

    function isChartsTabActive() {
        var chartsPane = document.getElementById('measurementsChartsPane');
        return !!(chartsPane && chartsPane.classList.contains('active') && chartsPane.classList.contains('show'));
    }

    function renderOwnershipDetailsTable(stationKey, transitionOwnerId) {
        if (!adminMode) {
            return;
        }

        var card = document.getElementById('measurementOwnershipDetailsCard');
        var title = document.getElementById('measurementOwnershipDetailsTitle');
        var tbody = document.getElementById('measurementOwnershipDetailsBody');
        if (!card || !title || !tbody) {
            return;
        }

        var rows = Array.isArray(chartOwnershipMapCache[stationKey]) ? chartOwnershipMapCache[stationKey] : [];
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-muted text-center py-2">No ownership history</td></tr>';
            title.textContent = 'Ownership timeline: ' + stationKey;
            card.classList.remove('d-none');
            return;
        }

        title.textContent = 'Ownership timeline: ' + stationKey;
        tbody.innerHTML = rows.map(function (row) {
            var ownerId = String(row.fk_ownerId || '');
            var ownerName = String(row.owner_name || ownerId || '-');
            var isActive = transitionOwnerId && ownerId === transitionOwnerId;
            return '<tr' + (isActive ? ' class="table-primary"' : '') + '>' +
                '<td>' + escapeHtml(ownerId || '-') + '</td>' +
                '<td>' + escapeHtml(ownerName) + '</td>' +
                '<td>' + escapeHtml(formatDateTime(row.registeredAt || '')) + '</td>' +
                '<td>' + escapeHtml(formatDateTime(row.unregisteredAt || '')) + '</td>' +
                '</tr>';
        }).join('');
        card.classList.remove('d-none');
    }

    function setOwnershipHoverInfo(transition) {
        var hoverInfo = document.getElementById('measurementOwnershipHoverInfo');
        if (!hoverInfo || !adminMode) {
            return;
        }

        if (!transition) {
            hoverInfo.classList.add('d-none');
            hoverInfo.textContent = '';
            return;
        }

        var ownerId = String(transition.ownerId || '');
        var ownerName = String(transition.ownerName || ownerId || '-');
        hoverInfo.textContent = 'Owner from ' + formatDateTime(transition.timestampRaw) + ': ' + ownerName + (ownerId ? ' (@' + ownerId + ')' : '');
        hoverInfo.classList.remove('d-none');
    }

    function findNearestTransition(chart, x) {
        var transitions = Array.isArray(chartOwnershipTransitions) ? chartOwnershipTransitions : [];
        var nearest = null;
        var threshold = 7;

        transitions.forEach(function (tr) {
            var dx = Math.abs(Number(tr.pixelX || 0) - Number(x || 0));
            if (dx > threshold) {
                return;
            }
            if (!nearest || dx < nearest.dx) {
                nearest = { item: tr, dx: dx };
            }
        });

        return nearest ? nearest.item : null;
    }

    var ownershipTransitionPlugin = {
        id: 'ownershipTransitionPlugin',
        afterDatasetsDraw: function (chart) {
            var transitions = Array.isArray(chartOwnershipTransitions) ? chartOwnershipTransitions : [];
            if (!transitions.length) {
                return;
            }

            var xScale = chart.scales && chart.scales.x;
            var chartArea = chart.chartArea;
            if (!xScale || !chartArea) {
                return;
            }

            var ctx = chart.ctx;
            ctx.save();
            transitions.forEach(function (tr) {
                var x = xScale.getPixelForValue(tr.labelIndex);
                tr.pixelX = x;
                ctx.beginPath();
                ctx.setLineDash([4, 4]);
                ctx.lineWidth = chartHoveredOwnershipTransition === tr ? 2.2 : 1.4;
                ctx.strokeStyle = chartHoveredOwnershipTransition === tr ? '#0d6efd' : 'rgba(220,53,69,0.7)';
                ctx.moveTo(x, chartArea.top + 2);
                ctx.lineTo(x, chartArea.bottom - 2);
                ctx.stroke();
            });
            ctx.restore();
        },
        afterEvent: function (chart, args) {
            var evt = args && args.event ? args.event : null;
            if (!evt || typeof evt.x !== 'number') {
                return;
            }

            if (evt.type === 'mousemove') {
                var nextHover = findNearestTransition(chart, evt.x);
                if (nextHover !== chartHoveredOwnershipTransition) {
                    chartHoveredOwnershipTransition = nextHover;
                    setOwnershipHoverInfo(nextHover);
                    chart.draw();
                }
            }

            if (evt.type === 'mouseout') {
                if (chartHoveredOwnershipTransition) {
                    chartHoveredOwnershipTransition = null;
                    setOwnershipHoverInfo(null);
                    chart.draw();
                }
            }

            if (evt.type === 'click') {
                var clicked = findNearestTransition(chart, evt.x);
                if (clicked) {
                    renderOwnershipDetailsTable(clicked.stationKey, clicked.ownerId);
                }
            }
        }
    };

    function ensureChartInstance() {
        if (typeof Chart === 'undefined') {
            return null;
        }

        if (chartInstance) {
            return chartInstance;
        }

        var canvas = document.getElementById('measurementMetricChartCanvas');
        if (!canvas) {
            return null;
        }

        var ctx = canvas.getContext('2d');
        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                normalized: true,
                layout: {
                    padding: {
                        top: 8
                    }
                },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                var ds = context.dataset || {};
                                var stationKey = String(ds.stationKey || '');
                                var rawLabels = (context.chart && context.chart.$rawLabels) ? context.chart.$rawLabels : [];
                                var tsRaw = String(rawLabels[context.dataIndex] || '');
                                var ownerMap = (context.chart && context.chart.$ownerByStationTs) ? context.chart.$ownerByStationTs : {};
                                var ownerInfo = ownerMap[stationKey + '|' + tsRaw] || null;
                                var metricValue = context.formattedValue || '-';
                                var ownerLabel = ownerInfo ? ((ownerInfo.ownerName || ownerInfo.ownerId || '-') + (ownerInfo.ownerId ? ' (@' + ownerInfo.ownerId + ')' : '')) : '-';
                                return (ds.label || 'Station') + ': ' + metricValue + ' | Owner: ' + ownerLabel;
                            }
                        }
                    },
                    legend: {
                        display: true,
                        position: 'bottom',
                        align: 'center',
                        maxHeight: isMobileView() ? 120 : 90,
                        fullSize: true,
                        labels: {
                            padding: isMobileView() ? 18 : 24,
                            boxWidth: isMobileView() ? 12 : 14,
                            boxHeight: isMobileView() ? 12 : 14,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: isMobileView() ? 12 : 13
                            },
                            generateLabels: function (chart) {
                                var defaults = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                                var maxLen = isMobileView() ? 8 : 18;
                                return defaults.map(function (item) {
                                    item.text = truncateLegendText(item.text, maxLen);
                                    item.pointStyle = 'circle';
                                    return item;
                                });
                            }
                        }
                    },
                    decimation: {
                        enabled: true,
                        algorithm: 'lttb',
                        samples: 120
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: String(config.timestampLabel || 'Timestamp')
                        },
                        ticks: {
                            maxTicksLimit: isMobileView() ? 5 : 8,
                            maxRotation: 0,
                            minRotation: 0,
                            font: {
                                size: isMobileView() ? 10 : 12
                            },
                            callback: function (value) {
                                return formatXAxisTickLabel(this.getLabelForValue(value));
                            }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: ''
                        }
                    }
                }
            },
            plugins: [ownershipTransitionPlugin]
        });

        return chartInstance;
    }

    function buildSeriesByStation(chartRows, metricKey) {
        var labelsRaw = [];
        var labelsPretty = [];
        var labelIndex = {};
        var stationOrder = [];
        var stationMap = {};

        chartRows.forEach(function (row) {
            var tsRaw = String(row.timestamp || '');
            if (!tsRaw) {
                return;
            }

            if (labelIndex[tsRaw] === undefined) {
                labelIndex[tsRaw] = labelsRaw.length;
                labelsRaw.push(tsRaw);
                labelsPretty.push(formatChartTimestamp(tsRaw));
                stationOrder.forEach(function (stKey) {
                    stationMap[stKey].push(null);
                });
            }

            var idx = labelIndex[tsRaw];
            var stationKey = String(row.fk_station || 'unknown');
            if (!stationMap[stationKey]) {
                stationMap[stationKey] = Array(labelsRaw.length).fill(null);
                stationOrder.push(stationKey);
            }

            if (stationMap[stationKey].length < labelsRaw.length) {
                while (stationMap[stationKey].length < labelsRaw.length) {
                    stationMap[stationKey].push(null);
                }
            }

            var val = row.metric_value !== undefined ? row.metric_value : row[metricKey];
            stationMap[stationKey][idx] = (val !== null && val !== '') ? parseFloat(val) : null;
        });

        var stationNameMap = {};
        chartRows.forEach(function (row) {
            var key = String(row.fk_station || 'unknown');
            if (!stationNameMap[key]) {
                stationNameMap[key] = row.station_name || key;
            }
        });

        updateStationColorAssignments(stationOrder);

        var datasets = stationOrder.map(function (stationKey) {
            var stationName = stationNameMap[stationKey] || stationKey;
            var colorIndex = chartStationColorAssignments[stationKey] || 0;
            var nonNullPoints = stationMap[stationKey].reduce(function (acc, v) {
                return acc + (v !== null ? 1 : 0);
            }, 0);
            return {
                label: stationName,
                stationKey: stationKey,
                data: stationMap[stationKey],
                borderColor: getSeriesColorByIndex(colorIndex, 1),
                backgroundColor: getSeriesColorByIndex(colorIndex, 0.18),
                borderWidth: 2,
                pointRadius: nonNullPoints <= 1 ? 4 : 0,
                pointHoverRadius: nonNullPoints <= 1 ? 6 : 3,
                pointHitRadius: 8,
                tension: 0.25,
                spanGaps: true,
                fill: false
            };
        });

        return {
            labelsRaw: labelsRaw,
            labels: labelsPretty,
            datasets: datasets
        };
    }

    function buildOwnerByStationTimestamp(chartRows) {
        var map = {};
        (chartRows || []).forEach(function (row) {
            var stationKey = String(row.fk_station || '').trim();
            var tsRaw = String(row.timestamp || '').trim();
            if (!stationKey || !tsRaw) {
                return;
            }
            map[stationKey + '|' + tsRaw] = {
                ownerId: String(row.fk_ownerId || ''),
                ownerName: String(row.owner_name || row.fk_ownerId || '')
            };
        });
        return map;
    }

    function buildOwnershipTransitions(chartRows, labelsRaw) {
        var byStation = {};
        var labelIndexMap = {};
        var transitions = [];

        (labelsRaw || []).forEach(function (ts, idx) {
            if (labelIndexMap[String(ts)] === undefined) {
                labelIndexMap[String(ts)] = idx;
            }
        });

        (chartRows || []).forEach(function (row) {
            var stationKey = String(row.fk_station || '').trim();
            var tsRaw = String(row.timestamp || '').trim();
            if (!stationKey || !tsRaw) {
                return;
            }
            if (!byStation[stationKey]) {
                byStation[stationKey] = [];
            }
            byStation[stationKey].push({
                timestampRaw: tsRaw,
                ownerId: String(row.fk_ownerId || ''),
                ownerName: String(row.owner_name || row.fk_ownerId || '')
            });
        });

        Object.keys(byStation).forEach(function (stationKey) {
            var rows = byStation[stationKey];
            rows.sort(function (a, b) {
                return String(a.timestampRaw).localeCompare(String(b.timestampRaw));
            });

            var prevOwner = '';
            rows.forEach(function (row) {
                if (!prevOwner) {
                    prevOwner = row.ownerId;
                    return;
                }
                if (row.ownerId === prevOwner) {
                    return;
                }
                var idx = labelIndexMap[row.timestampRaw];
                if (idx === undefined) {
                    prevOwner = row.ownerId;
                    return;
                }
                transitions.push({
                    stationKey: stationKey,
                    labelIndex: idx,
                    timestampRaw: row.timestampRaw,
                    ownerId: row.ownerId,
                    ownerName: row.ownerName,
                    pixelX: 0
                });
                prevOwner = row.ownerId;
            });
        });

        return transitions;
    }

    function renderChart(metricKey, chartRows) {
        if (typeof Chart === 'undefined') {
            return;
        }

        var chartConfig = chartConfigMap[metricKey];
        if (!chartConfig) {
            return;
        }

        var chart = ensureChartInstance();
        if (!chart) {
            return;
        }

        var stations = getAvailableChartStations(chartRows || []);
        ensureStationSelection(stations);
        renderChartStationPicker(stations);

        var filteredRows = filterChartRowsForSelection(chartRows || []);
        var series = buildSeriesByStation(filteredRows, chartConfig.metricKey);
        chart.data.labels = series.labels;
        chart.data.datasets = series.datasets;
        chart.$rawLabels = series.labelsRaw || [];
        chart.$ownerByStationTs = buildOwnerByStationTimestamp(filteredRows);
        chartOwnershipTransitions = buildOwnershipTransitions(filteredRows, series.labelsRaw || []);
        chartHoveredOwnershipTransition = null;
        setOwnershipHoverInfo(null);
        chart.options.scales.y.title.text = chartConfig.yTitle;
        chart.options.scales.x.ticks.maxTicksLimit = isMobileView() ? 5 : 8;
        chart.options.scales.x.ticks.font.size = isMobileView() ? 10 : 12;
        chart.options.plugins.legend.maxHeight = isMobileView() ? 120 : 90;
        chart.options.plugins.legend.labels.boxWidth = isMobileView() ? 12 : 14;
        chart.options.plugins.legend.labels.boxHeight = isMobileView() ? 12 : 14;
        chart.options.plugins.legend.labels.font.size = isMobileView() ? 12 : 13;
        chart.options.plugins.legend.labels.padding = computeLegendDynamicPadding(chart, series.datasets);
        chart.update('none');

        var titleEl = document.getElementById('activeChartTitle');
        if (titleEl) {
            titleEl.textContent = chartConfig.label;
        }
    }

    async function loadChartData(forceReload) {
        var mustReload = !!forceReload;

        if (!mustReload && chartDataLoaded[selectedChartMetric]) {
            refreshChartsIfVisible();
            return;
        }

        if (isChartLoading) {
            pendingChartReload = true;
            return;
        }

        isChartLoading = true;
        var params = getFilterParams();
        params.set('action', 'chart');
        params.set('metric', selectedChartMetric);
        params.set('chart_limit', '120');
        params.set('_ts', Date.now());

        try {
            var res = await getJson(apiEndpoint + '?' + params.toString());
            if (!res || !res.success) {
                return;
            }

            var metricKey = res.metric || selectedChartMetric;
            chartDataCache[metricKey] = Array.isArray(res.data) ? res.data : [];
            chartOwnershipMapCache = (res.ownership_map && typeof res.ownership_map === 'object') ? res.ownership_map : {};
            chartDataLoaded[metricKey] = true;
            if (isChartsTabActive()) {
                requestAnimationFrame(function () {
                    refreshChartsIfVisible();
                });
            }
        } catch (err) {
            console.error('Loading chart data failed', err);
        } finally {
            isChartLoading = false;
            if (pendingChartReload) {
                pendingChartReload = false;
                loadChartData(true);
            }
        }
    }

    function refreshChartsIfVisible() {
        if (isChartsTabActive()) {
            renderChart(selectedChartMetric, chartDataCache[selectedChartMetric] || []);
            if (chartInstance) {
                chartInstance.resize();
                chartInstance.update('none');
            }
        }
    }

    async function pollMeasurements() {
        var params = getFilterParams();
        params.set('action', 'poll');
        params.set('page', currentPage);
        params.set('per_page', perPage);
        params.set('include_chart', '0');
        params.set('_ts', Date.now());

        try {
            var res = await getJson(apiEndpoint + '?' + params.toString());
            if (!res || !res.success) {
                return;
            }

            currentPage = res.pagination.page;
            renderRows(res.rows || []);
            renderPagination(res.pagination);
            updateExportLink();
            updateUrlState();
        } catch (err) {
            console.error('Polling measurements failed', err);
        }
    }

    function applyFiltersWithoutReload() {
        currentPage = 1;
        selectedChartStations = {};
        userManuallyChangedStationSelection = false;
        chartStationColorAssignments = {};
        chartOwnershipMapCache = {};
        chartOwnershipTransitions = [];
        chartHoveredOwnershipTransition = null;
        chartDataLoaded = {
            temperature: false,
            airPressure: false,
            lightIntensity: false,
            airQuality: false
        };
        chartDataCache = {
            temperature: [],
            airPressure: [],
            lightIntensity: [],
            airQuality: []
        };
        pendingChartReload = false;
        setOwnershipHoverInfo(null);
        var ownershipCard = document.getElementById('measurementOwnershipDetailsCard');
        var ownershipBody = document.getElementById('measurementOwnershipDetailsBody');
        if (ownershipCard) {
            ownershipCard.classList.add('d-none');
        }
        if (ownershipBody) {
            ownershipBody.innerHTML = '';
        }
        pollMeasurements();
        if (isChartsTabActive()) {
            loadChartData(true);
        }
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('.measurement-page-link');
        if (!link) {
            return;
        }

        e.preventDefault();
        currentPage = parseInt(link.getAttribute('data-page'), 10) || 1;
        pollMeasurements();
    });

    document.addEventListener('input', function (e) {
        var input = e.target.closest('#chartStationSearchInput');
        if (!input) {
            return;
        }

        chartStationSearchQuery = String(input.value || '');
        renderChartStationPicker(getAvailableChartStations(chartDataCache[selectedChartMetric] || []));

        var newInput = document.getElementById('chartStationSearchInput');
        if (newInput) {
            newInput.focus();
            var len = newInput.value.length;
            newInput.setSelectionRange(len, len);
        }
    });

    document.addEventListener('change', function (e) {
        var check = e.target.closest('.chart-station-check');
        if (!check) {
            return;
        }

        userManuallyChangedStationSelection = true;

        var stationKey = String(check.value || '').trim();
        if (!stationKey) {
            return;
        }

        if (check.checked) {
            var selectedCount = 0;
            Object.keys(selectedChartStations).forEach(function (k) {
                if (selectedChartStations[k]) {
                    selectedCount += 1;
                }
            });

            if (selectedCount >= CHART_STATION_LIMIT) {
                check.checked = false;
                return;
            }

            selectedChartStations[stationKey] = true;
        } else {
            delete selectedChartStations[stationKey];
        }

        refreshChartsIfVisible();
    });

    document.addEventListener('click', function (e) {
        var menu = e.target.closest('#chartStationPickerMenu');
        if (menu) {
            e.stopPropagation();
        }

        var row = e.target.closest('.chart-station-item');
        if (!row) {
            return;
        }

        if (e.target.closest('.chart-station-check') || e.target.closest('.chart-station-label')) {
            return;
        }

        var rowCheck = row.querySelector('.chart-station-check');
        if (!rowCheck) {
            return;
        }

        rowCheck.checked = !rowCheck.checked;
        rowCheck.dispatchEvent(new Event('change', { bubbles: true }));
    });

    var chartStationPickerPanel = document.getElementById('chartStationPickerPanel');
    if (chartStationPickerPanel) {
        chartStationPickerPanel.addEventListener('shown.bs.collapse', function () {
            var icon = document.getElementById('chartStationPickerBtnIcon');
            if (icon) {
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-up');
            }

            var input = chartStationPickerPanel.querySelector('#chartStationSearchInput');
            if (input) {
                input.focus();
                var len = input.value.length;
                input.setSelectionRange(len, len);
            }

            var selected = chartStationPickerPanel.querySelector('.chart-station-check:checked');
            if (selected && selected.scrollIntoView) {
                selected.scrollIntoView({ block: 'nearest' });
            }
        });

        chartStationPickerPanel.addEventListener('hidden.bs.collapse', function () {
            var icon = document.getElementById('chartStationPickerBtnIcon');
            if (icon) {
                icon.classList.remove('bi-chevron-up');
                icon.classList.add('bi-chevron-down');
            }
        });
    }

    initMeasurementDateTimePickers();
    var restoredMeasurementFilters = restoreMeasurementFiltersStateIfNeeded();
    initMeasurementComboFilters();

    document.getElementById('measurementFiltersForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveMeasurementFiltersState();
        applyFiltersWithoutReload();
    });

    document.getElementById('measurementFiltersForm').addEventListener('change', function (e) {
        var target = e.target;
        if (!target || !target.name) {
            return;
        }

        if (target.name === 'measurement_id' || target.name === 'station' || target.name === 'station[]' || target.name === 'collection' || target.name === 'collection[]' || target.name === 'owner_id' || target.name === 'owner_id[]' || target.name === 'date_from' || target.name === 'date_to') {
            saveMeasurementFiltersState();
            applyFiltersWithoutReload();
        }
    });

    document.getElementById('measurementFiltersForm').addEventListener('input', function (e) {
        var target = e.target;
        if (!target || !target.name) {
            return;
        }
        if (target.name === 'measurement_id' || target.name === 'station' || target.name === 'station[]' || target.name === 'collection' || target.name === 'collection[]' || target.name === 'owner_id' || target.name === 'owner_id[]' || target.name === 'date_from' || target.name === 'date_to') {
            saveMeasurementFiltersState();
        }
    });

    var clearFiltersBtn = document.getElementById('clearMeasurementFiltersBtn');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function () {
            var form = document.getElementById('measurementFiltersForm');
            if (!form) {
                return;
            }

            var stationField = form.querySelector('[name="station"]');
            var collectionField = form.querySelector('[name="collection"]');
            var measurementIdField = form.querySelector('[name="measurement_id"]');
            var ownerField = form.querySelector('[name="owner_id"]');
            var dateFromField = form.querySelector('[name="date_from"]');
            var dateToField = form.querySelector('[name="date_to"]');

            form.querySelectorAll('input[type="checkbox"][name="station[]"], input[type="checkbox"][name="collection[]"], input[type="checkbox"][name="owner_id[]"]').forEach(function (cb) {
                cb.checked = false;
            });

            if (measurementIdField) {
                measurementIdField.value = '';
            }
            if (stationField) {
                stationField.value = '';
            }
            if (collectionField) {
                collectionField.value = '';
            }
            if (ownerField) {
                ownerField.value = '';
            }
            if (dateFromField) {
                dateFromField.value = '';
            }
            if (dateToField) {
                dateToField.value = '';
            }

            var collectionInput = document.getElementById('measurementCollectionInput');
            var stationInput = document.getElementById('measurementStationInput');
            if (collectionInput) {
                collectionInput.value = '';
            }
            if (stationInput) {
                stationInput.value = '';
            }

            clearMeasurementFiltersState();
            applyFiltersWithoutReload();
        });
    }

    if (adminMode) {
        var selectAllBtn = document.getElementById('measurementsSelectAllBtn');
        var unselectAllBtn = document.getElementById('measurementsUnselectAllBtn');
        var deleteSelectedBtn = document.getElementById('measurementsDeleteSelectedBtn');

        function getSelectedMeasurementIds() {
            return Array.prototype.slice.call(document.querySelectorAll('.js-measurement-row-check:checked')).map(function (el) {
                return String(el.value || '').trim();
            }).filter(function (id) {
                return id !== '';
            });
        }

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function () {
                document.querySelectorAll('.js-measurement-row-check').forEach(function (cb) {
                    cb.checked = true;
                });
            });
        }

        if (unselectAllBtn) {
            unselectAllBtn.addEventListener('click', function () {
                document.querySelectorAll('.js-measurement-row-check').forEach(function (cb) {
                    cb.checked = false;
                });
            });
        }

        if (deleteSelectedBtn) {
            deleteSelectedBtn.addEventListener('click', async function () {
                var ids = getSelectedMeasurementIds();
                if (!ids.length) {
                    return;
                }
                if (!window.confirm(confirmDeleteSelectedText)) {
                    return;
                }

                var form = new FormData();
                form.set('action', 'delete_selected');
                form.set('admin_all', '1');
                ids.forEach(function (id) {
                    form.append('ids[]', id);
                });

                try {
                    var res = await fetch(apiEndpoint, {
                        method: 'POST',
                        body: form,
                        credentials: 'same-origin'
                    });
                    var payload = await res.json();
                    if (payload && payload.success) {
                        pollMeasurements();
                    }
                } catch (e) {
                    console.error(e);
                }
            });
        }

        document.addEventListener('click', async function (e) {
            var delBtn = e.target.closest('.js-measurement-delete');
            if (delBtn) {
                var id = String(delBtn.getAttribute('data-id') || '').trim();
                if (!id) {
                    return;
                }
                if (!window.confirm(confirmDeleteOneText)) {
                    return;
                }
                var delForm = new FormData();
                delForm.set('action', 'delete_one');
                delForm.set('admin_all', '1');
                delForm.set('id', id);
                try {
                    var delRes = await fetch(apiEndpoint, {
                        method: 'POST',
                        body: delForm,
                        credentials: 'same-origin'
                    });
                    var delPayload = await delRes.json();
                    if (delPayload && delPayload.success) {
                        pollMeasurements();
                    }
                } catch (err) {
                    console.error(err);
                }
                return;
            }

            var editBtn = e.target.closest('.js-measurement-edit');
            if (!editBtn) {
                return;
            }

            var raw = String(editBtn.getAttribute('data-measurement') || '');
            if (!raw) {
                return;
            }
            var data = null;
            try {
                data = JSON.parse(decodeURIComponent(raw));
            } catch (err) {
                data = null;
            }
            if (!data) {
                return;
            }

            var idInput = document.getElementById('editMeasurementId');
            var tsInput = document.getElementById('editMeasurementTimestamp');
            var tempInput = document.getElementById('editMeasurementTemperature');
            var pressureInput = document.getElementById('editMeasurementPressure');
            var lightInput = document.getElementById('editMeasurementLight');
            var aqInput = document.getElementById('editMeasurementAirQuality');
            var modalEl = document.getElementById('editMeasurementModal');
            if (!idInput || !tsInput || !tempInput || !pressureInput || !lightInput || !aqInput || !modalEl) {
                return;
            }

            idInput.value = String(data.pk_measurementID || '');
            tsInput.value = formatDateTime(data.timestamp || '');
            tempInput.value = data.temperature !== null && data.temperature !== undefined ? data.temperature : '';
            pressureInput.value = data.airPressure !== null && data.airPressure !== undefined ? data.airPressure : '';
            lightInput.value = data.lightIntensity !== null && data.lightIntensity !== undefined ? data.lightIntensity : '';
            aqInput.value = data.airQuality !== null && data.airQuality !== undefined ? data.airQuality : '';

            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });

        var editForm = document.getElementById('editMeasurementForm');
        if (editForm) {
            editForm.addEventListener('submit', async function (e) {
                e.preventDefault();
                var fd = new FormData(editForm);
                fd.set('action', 'update_one');
                fd.set('admin_all', '1');
                fd.set('timestamp', normalizeFilterDateTimeForApi(String(fd.get('timestamp') || '')));
                try {
                    var res = await fetch(apiEndpoint, {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin'
                    });
                    var payload = await res.json();
                    if (payload && payload.success) {
                        var modalEl = document.getElementById('editMeasurementModal');
                        if (modalEl) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                        }
                        pollMeasurements();
                    }
                } catch (err) {
                    console.error(err);
                }
            });
        }
    }

    var dataPerPageSelect = document.getElementById('dataPerPageSelect');
    if (dataPerPageSelect) {
        dataPerPageSelect.addEventListener('change', function () {
            perPage = parseInt(dataPerPageSelect.value, 10) || 20;
            currentPage = 1;
            pollMeasurements();
        });
    }

    var downloadCurrentChartBtn = document.getElementById('downloadCurrentChartBtn');
    if (downloadCurrentChartBtn) {
        downloadCurrentChartBtn.addEventListener('click', function () {
            var chart = ensureChartInstance();
            if (!chart) {
                return;
            }

            if (isChartsTabActive()) {
                refreshChartsIfVisible();
            }

            var canvas = document.getElementById('measurementMetricChartCanvas');
            if (!canvas) {
                return;
            }

            var link = document.createElement('a');
            var metricPart = sanitizeFilePart(selectedChartMetric);
            var filterSuffix = buildFilenameFilterSuffix();

            link.download = 'chart_' + metricPart + filterSuffix + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }

    var chartMetricButtons = document.getElementById('chartMetricButtons');
    if (chartMetricButtons) {
        chartMetricButtons.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-metric]');
            if (!btn) {
                return;
            }

            var nextMetric = btn.getAttribute('data-metric');
            if (!chartConfigMap[nextMetric]) {
                return;
            }

            selectedChartMetric = nextMetric;
            ensureSelectedMetric();

            if (!isChartsTabActive()) {
                return;
            }

            if (chartDataLoaded[selectedChartMetric]) {
                refreshChartsIfVisible();
            } else {
                loadChartData(true);
            }
        });
    }

    // Table is already server-rendered on first load, so skip immediate poll request.
    ensureSelectedMetric();
    if (restoredMeasurementFilters) {
        applyFiltersWithoutReload();
    }
    if (isChartsTabActive()) {
        ensureChartInstance();
        loadChartData(true);
    }
    pollTimer = setInterval(pollMeasurements, 10000);
    chartPollTimer = setInterval(function () {
        if (isChartsTabActive()) {
            loadChartData(true);
        }
    }, 10000);

    var chartsTabBtn = document.getElementById('measurements-charts-tab');
    if (chartsTabBtn) {
        chartsTabBtn.addEventListener('click', function () {
            ensureSelectedMetric();
            ensureChartInstance();

            if (!chartDataLoaded[selectedChartMetric]) {
                // Start data fetch before the tab transition completes.
                loadChartData(true);
            }
        });

        chartsTabBtn.addEventListener('shown.bs.tab', function () {
            ensureSelectedMetric();

            if (chartInstance) {
                chartInstance.resize();
            }

            if (!chartDataLoaded[selectedChartMetric]) {
                loadChartData(true);
            } else {
                refreshChartsIfVisible();
            }

            // Hidden-tab rendering race fallback: retry once shortly after tab is visible.
            setTimeout(function () {
                if (isChartsTabActive()) {
                    if (!chartDataLoaded[selectedChartMetric]) {
                        loadChartData(true);
                    } else {
                        refreshChartsIfVisible();
                    }
                }
            }, 180);
        });
    }

    window.addEventListener('load', function () {
        updateExportLink();
        updateUrlState();
    });

    window.__measurementsCleanup = function () {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (chartPollTimer) {
            clearInterval(chartPollTimer);
            chartPollTimer = null;
        }
    };

    window.addEventListener('beforeunload', window.__measurementsCleanup);
}

window.initMeasurementsClient = initMeasurementsClient;
initMeasurementsClient();
