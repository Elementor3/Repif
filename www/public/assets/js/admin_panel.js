(function () {
'use strict';

var resizeTimer = null;
var adminLiveRefreshTimer = null;
var adminLiveRefreshBusy = false;
var lastRestoredCollectionsModalToken = '';
var lastRestoredStationHistoryToken = '';

function escapeHtmlAdmin(value) {
return String(value || '')
.replace(/&/g, '&amp;')
.replace(/</g, '&lt;')
.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
.replace(/'/g, '&#39;');
}

function parseAdminCollectionsConfig() {
var configEl = document.getElementById('adminCollectionsConfig');
if (!configEl) {
return {};
}
try {
return JSON.parse(configEl.textContent || '{}');
} catch (e) {
return {};
}
}

function getCurrentTabFromUrl() {
try {
var params = new URLSearchParams(window.location.search || '');
return params.get('tab') || 'users';
} catch (e) {
return 'users';
}
}

function setActiveTabInNav(tab) {
var nav = document.getElementById('adminTabsNav');
if (!nav) {
return;
}
var links = nav.querySelectorAll('a.nav-link');
links.forEach(function (link) {
var isActive = link.getAttribute('href') === ('?tab=' + tab);
link.classList.toggle('active', isActive);
});
}

function updateAdminAlerts(html) {
var alertsWrap = document.querySelector('.admin-panel-alerts');
if (!alertsWrap) {
return;
}
alertsWrap.innerHTML = String(html || '');
scheduleAdminAlertsAutoDismiss();
}

function scheduleAdminAlertsAutoDismiss() {
var alertsWrap = document.querySelector('.admin-panel-alerts');
if (!alertsWrap) {
return;
}
alertsWrap.querySelectorAll('.alert').forEach(function (alertEl) {
if (alertEl.dataset.dismissScheduled === '1') {
return;
}
alertEl.dataset.dismissScheduled = '1';
setTimeout(function () {
if (!alertEl.isConnected) {
return;
}
if (window.jQuery) {
window.jQuery(alertEl).fadeOut(220, function () {
if (alertEl && alertEl.parentNode) {
alertEl.parentNode.removeChild(alertEl);
}
});
} else {
alertEl.style.transition = 'opacity 0.22s ease';
alertEl.style.opacity = '0';
setTimeout(function () {
if (alertEl && alertEl.parentNode) {
alertEl.parentNode.removeChild(alertEl);
}
}, 230);
}
}, 4500);
});
}

function closeOpenModals() {
document.querySelectorAll('.modal.show').forEach(function (modalEl) {
var modal = bootstrap.Modal.getInstance(modalEl);
if (modal) {
modal.hide();
}
});
}

function cleanupModalBackdrops() {
document.querySelectorAll('.modal-backdrop').forEach(function (el) {
el.remove();
});
document.body.classList.remove('modal-open');
document.body.style.removeProperty('padding-right');
}

function urlWithAjaxFlag(rawUrl) {
var url = new URL(rawUrl, window.location.origin);
url.searchParams.set('ajax_tab', '1');
return url;
}

function setInlineAdminError(message) {
var text = String(message || 'Failed to load content').trim();
var openModals = document.querySelectorAll('.modal.show');
if (openModals.length > 0) {
var modalEl = openModals[openModals.length - 1];
var modalBody = modalEl.querySelector('.modal-body');
if (modalBody) {
var existing = modalBody.querySelector('.admin-modal-inline-alert');
if (existing) {
existing.remove();
}

var wrap = document.createElement('div');
wrap.className = 'alert alert-danger admin-modal-inline-alert';
wrap.setAttribute('role', 'alert');
wrap.textContent = text;
modalBody.prepend(wrap);

setTimeout(function () {
if (wrap && wrap.parentNode) {
wrap.remove();
}
}, 4500);
return;
}
}

updateAdminAlerts('<div class="alert alert-danger" role="alert">' + escapeHtmlAdmin(text) + '</div>');
}

function extractTabFromHtmlDocument(doc) {
if (!doc) {
return null;
}
var tabNode = doc.querySelector('#adminTabContent');
if (!tabNode) {
return null;
}
var alertsNode = doc.querySelector('.admin-panel-alerts');
var activeTabNode = doc.querySelector('#adminTabsNav a.nav-link.active');
var activeTab = null;
if (activeTabNode) {
try {
var href = activeTabNode.getAttribute('href') || '';
activeTab = new URL(href, window.location.origin).searchParams.get('tab');
} catch (e) {
activeTab = null;
}
}
return {
success: true,
activeTab: activeTab || getCurrentTabFromUrl(),
tabHtml: tabNode.innerHTML,
alertsHtml: alertsNode ? alertsNode.innerHTML : ''
};
}

function parseAjaxTabResponse(res) {
var contentType = String((res.headers && res.headers.get('content-type')) || '').toLowerCase();
if (contentType.indexOf('application/json') !== -1) {
return res.json();
}
return res.text().then(function (html) {
var parser = new DOMParser();
var doc = parser.parseFromString(String(html || ''), 'text/html');
var payload = extractTabFromHtmlDocument(doc);
if (payload) {
return payload;
}
throw new Error('Unexpected response format');
});
}

function syncAdminCollectionsConfigFromTabHtml(tabHtml) {
var currentConfigEl = document.getElementById('adminCollectionsConfig');
if (!currentConfigEl) {
return;
}

var parser = new DOMParser();
var doc = parser.parseFromString(String(tabHtml || ''), 'text/html');
var freshConfigEl = doc.getElementById('adminCollectionsConfig');
if (!freshConfigEl) {
return;
}

currentConfigEl.textContent = freshConfigEl.textContent || '{}';
}

function loadTabByUrl(rawUrl, pushHistory) {
var contentWrap = document.getElementById('adminTabContent');
if (!contentWrap) {
return Promise.resolve(false);
}

var ajaxUrl = urlWithAjaxFlag(rawUrl);
return fetch(ajaxUrl.toString(), {
method: 'GET',
headers: {
'Accept': 'application/json',
'X-Requested-With': 'XMLHttpRequest'
},
credentials: 'same-origin'
}).then(function (res) {
if (!res.ok) {
throw new Error('Failed to load tab');
}
return parseAjaxTabResponse(res);
}).then(function (payload) {
if (!payload || payload.success !== true) {
throw new Error('Invalid tab payload');
}

contentWrap.innerHTML = String(payload.tabHtml || '');
updateAdminAlerts(String(payload.alertsHtml || ''));

var cleanUrl = new URL(rawUrl, window.location.origin);
cleanUrl.searchParams.delete('ajax_tab');
if (pushHistory) {
history.pushState({ tab: payload.activeTab || getCurrentTabFromUrl() }, '', cleanUrl.pathname + cleanUrl.search);
}

setActiveTabInNav(payload.activeTab || getCurrentTabFromUrl());
onAdminTabContentUpdated(payload.activeTab || getCurrentTabFromUrl());
return true;
}).catch(function (err) {
console.error(err);
setInlineAdminError('Failed to load tab asynchronously');
return false;
});
}

function submitAdminFormAjax(form) {
var currentTab = getCurrentTabFromUrl();
var actionUrl = form.getAttribute('action') || ('/admin/panel.php?tab=' + encodeURIComponent(currentTab));
var reqUrl = urlWithAjaxFlag(actionUrl);

var fd = new FormData(form);
fd.set('ajax_tab', '1');

var action = String(fd.get('action') || '').trim();
var slotsModalEl = document.getElementById('collectionSlotsAdminModal');
var keepSlotsModalOpen = !!(form.closest('#collectionSlotsAdminModal') && (action === 'add_collection_slot_admin' || action === 'remove_collection_slot_admin'));
var reopenCollectionId = 0;
var reopenCollectionName = '';
if (keepSlotsModalOpen && slotsModalEl) {
reopenCollectionId = Number(slotsModalEl.getAttribute('data-collection-id') || 0);
reopenCollectionName = String(slotsModalEl.getAttribute('data-collection-name') || '');
}

return fetch(reqUrl.toString(), {
method: 'POST',
body: fd,
headers: {
'Accept': 'application/json',
'X-Requested-With': 'XMLHttpRequest'
},
credentials: 'same-origin'
}).then(function (res) {
if (!res.ok) {
throw new Error('Failed to submit form');
}
return parseAjaxTabResponse(res);
}).then(function (payload) {
var contentWrap = document.getElementById('adminTabContent');
if (!payload || payload.success !== true || !contentWrap) {
throw new Error('Invalid form payload');
}
updateAdminAlerts(String(payload.alertsHtml || ''));

if (keepSlotsModalOpen) {
var parser = new DOMParser();
var alertsDoc = parser.parseFromString('<div>' + String(payload.alertsHtml || '') + '</div>', 'text/html');
var dangerAlert = alertsDoc.querySelector('.alert-danger');
if (dangerAlert) {
setInlineAdminError(String(dangerAlert.textContent || '').trim());
}
}
if (keepSlotsModalOpen) {
syncAdminCollectionsConfigFromTabHtml(payload.tabHtml || '');
if (reopenCollectionId > 0) {
openCollectionSlotsModal(reopenCollectionId, reopenCollectionName);
}
return true;
}

closeOpenModals();
contentWrap.innerHTML = String(payload.tabHtml || '');
setActiveTabInNav(payload.activeTab || currentTab);
onAdminTabContentUpdated(payload.activeTab || currentTab);
return true;
}).catch(function (err) {
console.error(err);
setInlineAdminError('Failed to submit form asynchronously');
return false;
});
}

function syncRecipientsVisibilityByIds(audienceId, wrapId) {
var audienceEl = document.getElementById(audienceId);
var recipientsWrap = document.getElementById(wrapId);
if (!audienceEl || !recipientsWrap) return;
recipientsWrap.classList.toggle('d-none', audienceEl.value !== 'selected');
}

function bindAudienceAndSearch(audienceId, wrapId, searchId, itemsSelector) {
var audienceEl = document.getElementById(audienceId);
var recipientsSearch = document.getElementById(searchId);

if (audienceEl) {
audienceEl.addEventListener('change', function () {
syncRecipientsVisibilityByIds(audienceId, wrapId);
});
syncRecipientsVisibilityByIds(audienceId, wrapId);
}

if (recipientsSearch) {
recipientsSearch.addEventListener('input', function () {
var q = recipientsSearch.value.toLowerCase();
var items = document.querySelectorAll(itemsSelector);
items.forEach(function (item) {
item.style.display = item.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
});
});
}
}

function buildMiniUserLink(user, extraClass) {
var username = String((user && (user.username || user.pk_username)) || '').trim();
if (!username) {
return '';
}
var firstName = String((user && user.firstName) || '').trim();
var lastName = String((user && user.lastName) || '').trim();
var avatarUrl = String((user && user.avatarUrl) || '').trim();
var profileUrl = String((user && user.profileUrl) || '#').trim();
var tooltip = (firstName + ' ' + lastName).trim() || ('@' + username);

return '' +
'<a class="collection-share-item ' + escapeHtmlAdmin(extraClass || '') + '" href="' + escapeHtmlAdmin(profileUrl) + '" title="' + escapeHtmlAdmin(tooltip) + '">' +
(avatarUrl
? '<img src="' + escapeHtmlAdmin(avatarUrl) + '" class="collection-share-avatar" alt="avatar">'
: '<span class="collection-share-avatar"><i class="bi bi-person-circle"></i></span>') +
'<span class="collection-share-username">@' + escapeHtmlAdmin(username) + '</span>' +
'</a>';
}

function buildProfileUrlWithAdminBackState(baseProfileUrl, modalState) {
var rawUrl = String(baseProfileUrl || '').trim();
if (rawUrl === '' || rawUrl === '#') {
return rawUrl || '#';
}

try {
var profileUrl = new URL(rawUrl, window.location.origin);
var currentBack = profileUrl.searchParams.get('back') || (window.location.pathname + window.location.search);
var backUrl = new URL(currentBack, window.location.origin);
backUrl.searchParams.delete('ajax_tab');

if (modalState && typeof modalState === 'object') {
Object.keys(modalState).forEach(function (key) {
var val = modalState[key];
if (val === null || val === undefined || String(val).trim() === '') {
backUrl.searchParams.delete(key);
} else {
backUrl.searchParams.set(key, String(val));
}
});
}

profileUrl.searchParams.set('back', backUrl.pathname + backUrl.search);
return profileUrl.pathname + profileUrl.search + profileUrl.hash;
} catch (e) {
return rawUrl;
}
}

function buildFriendPickerItemHtml(user, selected, groupName) {
var username = String((user && (user.username || user.pk_username)) || '').trim();
if (!username) {
return '';
}

var firstName = String((user && user.firstName) || '').trim();
var lastName = String((user && user.lastName) || '').trim();
var fullName = (firstName + ' ' + lastName).trim() || username;
var avatarUrl = String((user && user.avatarUrl) || '').trim();

return '' +
'<label class="share-friend-item" data-admin-friend-item="' + escapeHtmlAdmin(username) + '">' +
'<input type="radio" class="form-check-input js-admin-friend-radio" name="' + escapeHtmlAdmin(groupName) + '" value="' + escapeHtmlAdmin(username) + '"' + (selected ? ' checked' : '') + '>' +
(avatarUrl
? '<img src="' + escapeHtmlAdmin(avatarUrl) + '" class="share-user-avatar" alt="avatar">'
: '<span class="share-user-avatar"><i class="bi bi-person-circle"></i></span>') +
'<span class="share-friend-meta">' +
'<span class="share-user-username">@' + escapeHtmlAdmin(username) + '</span>' +
'<span class="share-user-fullname">' + escapeHtmlAdmin(fullName) + '</span>' +
'</span>' +
'</label>';
}

function renderAdminOwnerPicker(selectedUsername) {
var listEl = document.getElementById('editCollectionAdminOwnerList');
var hiddenEl = document.getElementById('editCollectionAdminOwner');
var searchEl = document.getElementById('editCollectionAdminOwnerSearch');
if (!listEl || !hiddenEl) {
return;
}

var cfg = parseAdminCollectionsConfig();
var allUsers = Array.isArray(cfg.allUsers) ? cfg.allUsers : [];
var selected = String(selectedUsername || hiddenEl.value || '').trim();
var query = String((searchEl && searchEl.value) || '').trim().toLowerCase();

if (selected !== '') {
hiddenEl.value = selected;
}

var html = '';
var visible = 0;
allUsers.forEach(function (u) {
var uname = String((u && u.pk_username) || '').trim();
if (!uname) {
return;
}
var first = String((u && u.firstName) || '').trim();
var last = String((u && u.lastName) || '').trim();
var haystack = (uname + ' ' + first + ' ' + last + ' ' + (first + ' ' + last).trim()).toLowerCase();
if (query !== '' && haystack.indexOf(query) === -1) {
return;
}
html += buildFriendPickerItemHtml(u, uname === selected, 'editCollectionAdminOwnerRadio');
visible += 1;
});

if (!visible) {
html = '<div class="text-muted small py-2">No users</div>';
}

listEl.innerHTML = html;
}

function renderAdminShareFriendsList(collectionId, selectedUsername) {
var listEl = document.getElementById('collectionShareAdminFriendsList');
var searchEl = document.getElementById('collectionShareAdminSearch');
var hiddenEl = document.getElementById('collectionShareAdminUserSelect');
if (!listEl || !hiddenEl) {
return;
}

var cfg = parseAdminCollectionsConfig();
var currentAdminUsername = String(cfg.currentAdminUsername || '');
var allUsers = Array.isArray(cfg.allUsers) ? cfg.allUsers : [];
var sharesMap = cfg.collectionSharesByCollection && typeof cfg.collectionSharesByCollection === 'object'
? cfg.collectionSharesByCollection
: {};
var shared = Array.isArray(sharesMap[String(collectionId)]) ? sharesMap[String(collectionId)] : [];
var sharedMap = {};
shared.forEach(function (s) {
var uname = String((s && s.pk_username) || '').trim();
if (uname) {
sharedMap[uname] = true;
}
});

var selected = String(selectedUsername || hiddenEl.value || '').trim();
if (selected !== '') {
hiddenEl.value = selected;
}

var query = String((searchEl && searchEl.value) || '').trim().toLowerCase();
var html = '';
var visible = 0;
allUsers.forEach(function (u) {
var uname = String((u && u.pk_username) || '').trim();
if (!uname || uname === currentAdminUsername || sharedMap[uname]) {
return;
}
var first = String((u && u.firstName) || '').trim();
var last = String((u && u.lastName) || '').trim();
var haystack = (uname + ' ' + first + ' ' + last + ' ' + (first + ' ' + last).trim()).toLowerCase();
if (query !== '' && haystack.indexOf(query) === -1) {
return;
}
html += buildFriendPickerItemHtml(u, uname === selected, 'collectionShareAdminFriendRadio');
visible += 1;
});

if (!visible) {
html = '<div class="text-muted small py-2">No users</div>';
}

listEl.innerHTML = html;
}

function openCollectionSharedUsersModal(collectionId, collectionName) {
var modalEl = document.getElementById('collectionSharedUsersModal');
var titleEl = document.getElementById('collectionSharedUsersModalTitle');
var listEl = document.getElementById('collectionSharedUsersModalUsersList');
if (!modalEl || !titleEl || !listEl) {
return;
}

var cfg = parseAdminCollectionsConfig();
var sharesMap = cfg.collectionSharesViewByCollection && typeof cfg.collectionSharesViewByCollection === 'object'
? cfg.collectionSharesViewByCollection
: {};
var sharedWithLabel = String(cfg.sharedWithLabel || 'Shared with');
var noSharedUsersLabel = String(cfg.noSharedUsersLabel || 'No shared users');
var users = Array.isArray(sharesMap[String(collectionId)]) ? sharesMap[String(collectionId)] : [];

titleEl.textContent = sharedWithLabel + ': ' + String(collectionName || '');
if (!users.length) {
listEl.innerHTML = '<div class="text-muted small">' + escapeHtmlAdmin(noSharedUsersLabel) + '</div>';
} else {
listEl.innerHTML = '<div class="collection-card-shares admin-modal-user-grid">' + users.map(function (u) {
return buildMiniUserLink(u, 'admin-modal-mini');
}).join('') + '</div>';
}

new bootstrap.Modal(modalEl).show();
}

function hydrateSharedUsersCells() {
document.querySelectorAll('.js-admin-shared-users').forEach(function (container) {
var sharesRaw = String(container.getAttribute('data-shares') || '').trim();
if (!sharesRaw) {
container.innerHTML = '<span class="text-muted">-</span>';
container.setAttribute('data-hydrated', '1');
return;
}

var users = [];
try {
users = JSON.parse(sharesRaw);
} catch (e) {
users = [];
}

container.innerHTML = users.map(function (u) {
return buildMiniUserLink(u, 'admin-shared-mini');
}).join('');

var items = Array.prototype.slice.call(container.querySelectorAll('.collection-share-item'));
if (items.length > 1) {
var containerWidth = container.clientWidth || 0;
var gap = 6;
try {
var computed = window.getComputedStyle(container);
var gapRaw = parseFloat(computed.columnGap || computed.gap || '6');
if (!isNaN(gapRaw) && gapRaw >= 0) {
gap = gapRaw;
}
} catch (e) {
gap = 6;
}

var reserveForMoreBtn = 36;
var usedWidth = 0;
var visibleCount = 0;

for (var i = 0; i < items.length; i += 1) {
var itemWidth = Math.ceil(items[i].getBoundingClientRect().width || 0);
if (itemWidth <= 0) {
itemWidth = 42;
}

var additional = (visibleCount > 0 ? gap : 0) + itemWidth;
var remainingAfterCurrent = (items.length - (i + 1));
var reserve = remainingAfterCurrent > 0 ? (gap + reserveForMoreBtn) : 0;
if (containerWidth > 0 && (usedWidth + additional + reserve) > containerWidth) {
break;
}

usedWidth += additional;
visibleCount += 1;
}

if (visibleCount < items.length) {
for (var j = visibleCount; j < items.length; j += 1) {
items[j].remove();
}

var moreBtn = document.createElement('button');
moreBtn.type = 'button';
moreBtn.className = 'btn btn-outline-secondary btn-sm collection-share-more admin-shared-row-more-btn';
moreBtn.textContent = '...';
moreBtn.addEventListener('click', function () {
var cId = Number(container.getAttribute('data-collection-id') || 0);
var cName = String(container.getAttribute('data-collection-name') || '');
openCollectionSharedUsersModal(cId, cName);
});
container.appendChild(moreBtn);
}
}

container.setAttribute('data-hydrated', '1');
});
}

function initAdminSlotDateTimeInputs() {
if (!window.jQuery || !jQuery.fn.datetimepicker) {
return;
}

jQuery('#collectionSlotsAdminModal .js-datetime-input').each(function () {
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
}

function initAdminCollectionsDateTimePickers() {
if (!window.jQuery || !jQuery.fn.datetimepicker) {
return;
}

jQuery('.js-admin-collections-datetime').each(function () {
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

jQuery('.measurement-picker-icon').off('click.adminCollectionsDate').on('click.adminCollectionsDate', function () {
var $icon = jQuery(this);
var $input = $icon.siblings('input.js-admin-collections-datetime').first();
if (!$input.length) {
$input = $icon.closest('.input-group').find('input.js-admin-collections-datetime').first();
}
if ($input.length) {
$input.trigger('focus');
try {
$input.datetimepicker('show');
} catch (e) {
// Focus fallback only.
}
}
});
}

function fitAdminUsersText() {
var cells = document.querySelectorAll('#adminUsersTable td.admin-users-fit-cell');
if (!cells.length) {
return;
}

var measurer = document.getElementById('adminUsersTextMeasurer');
if (!measurer) {
measurer = document.createElement('span');
measurer.id = 'adminUsersTextMeasurer';
measurer.style.position = 'fixed';
measurer.style.left = '-99999px';
measurer.style.top = '-99999px';
measurer.style.visibility = 'hidden';
measurer.style.whiteSpace = 'nowrap';
document.body.appendChild(measurer);
}

cells.forEach(function (cell) {
var textEl = cell.querySelector('.js-admin-users-fit-text');
var moreBtn = cell.querySelector('.js-admin-users-fit-more');
if (!textEl || !moreBtn) {
return;
}

var fullText = String(textEl.getAttribute('data-full-text') || '').trim();
if (!fullText) {
moreBtn.classList.add('d-none');
textEl.textContent = '';
return;
}

textEl.textContent = fullText;
moreBtn.classList.add('d-none');

var computed = window.getComputedStyle(textEl);
measurer.style.font = computed.font;
measurer.style.fontSize = computed.fontSize;
measurer.style.fontFamily = computed.fontFamily;

var cellWidth = cell.clientWidth || 0;
if (cellWidth <= 0) {
return;
}

var extraReserved = 0;
var verifyIcon = cell.querySelector('.admin-users-verify-icon');
if (verifyIcon) {
extraReserved += Math.ceil(verifyIcon.getBoundingClientRect().width || 0) + 10;
}

var moreBtnWidth = Math.ceil(moreBtn.getBoundingClientRect().width || 18);
var available = Math.max(10, cellWidth - moreBtnWidth - extraReserved - 12);

measurer.textContent = fullText;
if (measurer.getBoundingClientRect().width <= available) {
return;
}

var low = 0;
var high = fullText.length;
while (low < high) {
var mid = Math.ceil((low + high) / 2);
var candidate = fullText.slice(0, mid) + '...';
measurer.textContent = candidate;
if (measurer.getBoundingClientRect().width <= available) {
low = mid;
} else {
high = mid - 1;
}
}

var cut = Math.max(1, low);
textEl.textContent = fullText.slice(0, cut) + '...';
moreBtn.classList.remove('d-none');
});
}

function fitCollectionDescriptionText() {
var rows = document.querySelectorAll('#adminCollectionsTable td.admin-col-description');
if (!rows.length) {
return;
}

var measurer = document.getElementById('collectionDescriptionMeasurer');
if (!measurer) {
measurer = document.createElement('span');
measurer.id = 'collectionDescriptionMeasurer';
measurer.style.position = 'fixed';
measurer.style.left = '-99999px';
measurer.style.top = '-99999px';
measurer.style.visibility = 'hidden';
measurer.style.whiteSpace = 'nowrap';
document.body.appendChild(measurer);
}

rows.forEach(function (cell) {
var textEl = cell.querySelector('.js-collection-description-text');
var moreBtn = cell.querySelector('.js-collection-description-more');
if (!textEl) {
return;
}

var fullText = String(textEl.getAttribute('data-full-description') || '');
textEl.textContent = fullText;
if (!moreBtn) {
return;
}

var computed = window.getComputedStyle(textEl);
measurer.style.font = computed.font;
measurer.style.fontSize = computed.fontSize;
measurer.style.fontFamily = computed.fontFamily;

var cellWidth = cell.clientWidth || 0;
var moreWidth = moreBtn.getBoundingClientRect().width || 18;
var available = Math.max(10, cellWidth - moreWidth - 16);

measurer.textContent = fullText;
if (measurer.getBoundingClientRect().width <= available) {
moreBtn.classList.add('d-none');
return;
}

var low = 0;
var high = fullText.length;
while (low < high) {
var mid = Math.ceil((low + high) / 2);
var candidate = fullText.slice(0, mid) + '...';
measurer.textContent = candidate;
if (measurer.getBoundingClientRect().width <= available) {
low = mid;
} else {
high = mid - 1;
}
}

var cut = Math.max(1, low);
textEl.textContent = fullText.slice(0, cut) + '...';
moreBtn.classList.remove('d-none');
});
}

function initAdminCollectionsAutoFilter() {
var form = document.getElementById('adminCollectionsFilterForm');
if (!form || form.dataset.autoFilterInited === '1') {
return;
}
form.dataset.autoFilterInited = '1';

var submitTimer = null;
function scheduleSubmit(delay) {
clearTimeout(submitTimer);
submitTimer = setTimeout(function () {
form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}, delay);
}

form.addEventListener('change', function (e) {
var target = e.target;
if (!target) return;

if (target.matches('[data-role="search"]')) {
return;
}

if (target.matches('input[name="collections_id"], input[name="collections_created_from"], input[name="collections_created_to"], input[type="checkbox"][name="collections_name[]"], input[type="checkbox"][name="collections_owner[]"], input[type="checkbox"][name="collections_shared_users[]"]')) {
scheduleSubmit(120);
}
});

form.addEventListener('input', function (e) {
var target = e.target;
if (!target) return;

if (target.matches('input[name="collections_id"], input[name="collections_created_from"], input[name="collections_created_to"]')) {
scheduleSubmit(350);
}
});
}

function initAdminUsersAutoFilter() {
var form = document.getElementById('adminUsersFilterForm');
if (!form || form.dataset.autoFilterInited === '1') {
return;
}
form.dataset.autoFilterInited = '1';

var submitTimer = null;
function scheduleSubmit(delay) {
window.clearTimeout(submitTimer);
submitTimer = window.setTimeout(function () {
form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}, delay);
}

form.addEventListener('change', function (e) {
var target = e.target;
if (!target) {
return;
}

if (target.matches('[data-role="search"]')) {
return;
}

if (target.matches('input[name="users_created_from"], input[name="users_created_to"], input[type="checkbox"][name="users_id[]"], input[type="checkbox"][name="users_first_name[]"], input[type="checkbox"][name="users_last_name[]"], input[type="checkbox"][name="users_email[]"], input[type="checkbox"][name="users_role[]"]')) {
scheduleSubmit(120);
}
});

form.addEventListener('input', function (e) {
var target = e.target;
if (!target) {
return;
}

if (target.matches('input[name="users_created_from"], input[name="users_created_to"]')) {
scheduleSubmit(320);
}
});
}

function initAdminStationsAutoFilter() {
var form = document.getElementById('adminStationsFilterForm');
if (!form || form.dataset.autoFilterInited === '1') {
return;
}
form.dataset.autoFilterInited = '1';

var submitTimer = null;
function scheduleSubmit(delay) {
window.clearTimeout(submitTimer);
submitTimer = window.setTimeout(function () {
form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}, delay);
}

form.addEventListener('change', function (e) {
var target = e.target;
if (!target) {
return;
}

if (target.matches('[data-role="search"]')) {
return;
}

if (target.matches('input[name="stations_created_from"], input[name="stations_created_to"], input[name="stations_registered_from"], input[name="stations_registered_to"], input[type="checkbox"][name="stations_serial[]"], input[type="checkbox"][name="stations_name[]"], input[type="checkbox"][name="stations_description[]"], input[type="checkbox"][name="stations_created_by[]"], input[type="checkbox"][name="stations_registered_by[]"]')) {
scheduleSubmit(120);
}
});

form.addEventListener('input', function (e) {
var target = e.target;
if (!target) {
return;
}

if (target.matches('input[name="stations_created_from"], input[name="stations_created_to"], input[name="stations_registered_from"], input[name="stations_registered_to"]')) {
scheduleSubmit(320);
}
});
}

function initAdminPostsAutoFilter() {
var form = document.getElementById('adminPostsFilterForm');
if (!form || form.dataset.autoFilterInited === '1') {
return;
}
form.dataset.autoFilterInited = '1';

var submitTimer = null;
function scheduleSubmit(delay) {
window.clearTimeout(submitTimer);
submitTimer = window.setTimeout(function () {
form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
}, delay);
}

form.addEventListener('change', function (e) {
var target = e.target;
if (!target) {
return;
}

if (target.matches('[data-role="search"]')) {
return;
}

if (target.matches('input[name="posts_id"], input[name="posts_description"], input[name="posts_created_from"], input[name="posts_created_to"], input[type="checkbox"][name="posts_title[]"], input[type="checkbox"][name="posts_author[]"]')) {
scheduleSubmit(140);
}
});

form.addEventListener('input', function (e) {
var target = e.target;
if (!target) {
return;
}

if (target.matches('input[name="posts_id"], input[name="posts_description"], input[name="posts_created_from"], input[name="posts_created_to"]')) {
scheduleSubmit(320);
}
});
}

function initAdminUsersDatePickers() {
if (!window.jQuery || !jQuery.fn.datetimepicker) {
return;
}

jQuery('#adminUsersFilterForm .js-admin-users-datetime').each(function () {
var $input = jQuery(this);
if ($input.data('dtp-initialized')) {
return;
}

$input.datetimepicker({
format: 'd.m.Y H:i',
timepicker: true,
step: 5,
dayOfWeekStart: 1,
scrollInput: false,
closeOnDateSelect: false,
onClose: function () {
$input.trigger('change');
}
});

$input.data('dtp-initialized', true);
});

jQuery('#adminUsersFilterForm .measurement-picker-icon').off('click.adminUsersDate').on('click.adminUsersDate', function () {
var $icon = jQuery(this);
var $input = $icon.siblings('input.js-admin-users-datetime').first();
if (!$input.length) {
$input = $icon.closest('.input-group').find('input.js-admin-users-datetime').first();
}
if ($input.length) {
$input.trigger('focus');
try {
$input.datetimepicker('show');
} catch (e) {
// Focus fallback only.
}
}
});
}

function initAdminPostsDatePickers() {
if (!window.jQuery || !jQuery.fn.datetimepicker) {
return;
}

jQuery('#adminPostsFilterForm .js-admin-posts-datetime').each(function () {
var $input = jQuery(this);
if ($input.data('dtp-initialized')) {
return;
}

$input.datetimepicker({
format: 'd.m.Y H:i',
timepicker: true,
step: 5,
dayOfWeekStart: 1,
scrollInput: false,
closeOnDateSelect: false,
onClose: function () {
$input.trigger('change');
}
});

$input.data('dtp-initialized', true);
});

jQuery('#adminPostsFilterForm .measurement-picker-icon').off('click.adminPostsDate').on('click.adminPostsDate', function () {
var $icon = jQuery(this);
var $input = $icon.siblings('input.js-admin-posts-datetime').first();
if (!$input.length) {
$input = $icon.closest('.input-group').find('input.js-admin-posts-datetime').first();
}
if ($input.length) {
$input.trigger('focus');
try {
$input.datetimepicker('show');
} catch (e) {
// Focus fallback only.
}
}
});
}

function initAdminStationsDatePickers() {
if (!window.jQuery || !jQuery.fn.datetimepicker) {
return;
}

jQuery('#adminStationsFilterForm .js-admin-stations-datetime').each(function () {
var $input = jQuery(this);
if ($input.data('dtp-initialized')) {
return;
}

$input.datetimepicker({
format: 'd.m.Y H:i',
timepicker: true,
step: 5,
dayOfWeekStart: 1,
scrollInput: false,
closeOnDateSelect: false,
onClose: function () {
$input.trigger('change');
}
});

$input.data('dtp-initialized', true);
});

jQuery('#adminStationsFilterForm .measurement-picker-icon').off('click.adminStationsDate').on('click.adminStationsDate', function () {
var $icon = jQuery(this);
var $input = $icon.siblings('input.js-admin-stations-datetime').first();
if (!$input.length) {
$input = $icon.closest('.input-group').find('input.js-admin-stations-datetime').first();
}
if ($input.length) {
$input.trigger('focus');
try {
$input.datetimepicker('show');
} catch (e) {
// Focus fallback only.
}
}
});
}

function initAdminSingleCombos() {
document.querySelectorAll('[data-single-combo]').forEach(function (combo) {
if (combo.dataset.inited === '1') {
return;
}
combo.dataset.inited = '1';

var hidden = combo.querySelector('input[type="hidden"]');
var toggle = combo.querySelector('[data-role="toggle"]');
var summary = combo.querySelector('[data-role="summary"]');
var panel = combo.querySelector('[data-role="panel"]');
var search = combo.querySelector('[data-role="search"]');
var optionsWrap = combo.querySelector('[data-role="options"]');
if (!hidden || !toggle || !summary || !panel || !optionsWrap) {
return;
}

function updateSummary() {
var baseLabel = String(summary.getAttribute('data-base-label') || '').trim() || 'Select';
var selected = optionsWrap.querySelector('input[data-role="single-option"]:checked');
var selectedLabel = '-';
if (selected) {
var label = selected.closest('label');
var textNode = label ? label.querySelector('span') : null;
selectedLabel = String((textNode && textNode.textContent) || selected.value || '-').trim() || '-';
hidden.value = selected.value || '';
}
summary.textContent = baseLabel + ': ' + selectedLabel;
}

function syncFromHidden() {
var val = String(hidden.value || '');
var target = optionsWrap.querySelector('input[data-role="single-option"][value="' + CSS.escape(val) + '"]');
if (!target) {
target = optionsWrap.querySelector('input[data-role="single-option"][value=""]');
}
if (target) {
target.checked = true;
}
updateSummary();
}

toggle.addEventListener('click', function (e) {
e.preventDefault();
panel.classList.toggle('d-none');
if (!panel.classList.contains('d-none') && search) {
search.focus();
}
});

if (search) {
search.addEventListener('input', function () {
var q = String(search.value || '').trim().toLowerCase();
optionsWrap.querySelectorAll('.admin-multicombo-option').forEach(function (opt) {
var label = String(opt.getAttribute('data-label') || '').toLowerCase();
opt.classList.toggle('d-none', q !== '' && label.indexOf(q) === -1);
});
});
}

combo.addEventListener('change', function (e) {
if (e.target && e.target.matches('input[data-role="single-option"]')) {
updateSummary();
panel.classList.add('d-none');
}
});

hidden.addEventListener('change', syncFromHidden);

document.addEventListener('click', function (e) {
if (!combo.contains(e.target)) {
panel.classList.add('d-none');
}
});

syncFromHidden();
});
}

function parseDateTimeInputToTs(value) {
var raw = String(value || '').trim();
if (!raw) {
return null;
}

var eu = raw.match(/^(\d{2})\.(\d{2})\.(\d{4})(?:\s+(\d{2}):(\d{2}))?$/);
if (eu) {
var dd = Number(eu[1]);
var mm = Number(eu[2]) - 1;
var yyyy = Number(eu[3]);
var hh = Number(eu[4] || '0');
var mi = Number(eu[5] || '0');
return new Date(yyyy, mm, dd, hh, mi, 0, 0).getTime();
}

var parsed = Date.parse(raw.replace(' ', 'T'));
return isNaN(parsed) ? null : parsed;
}

function openAdminStationHistoryModal(serial, historyRows, stationMeasurementsUrl) {
var modalEl = document.getElementById('adminStationHistoryModal');
var titleEl = document.getElementById('adminStationHistoryTitle');
var headerActionsEl = document.getElementById('adminStationHistoryHeaderActions');
var bodyEl = document.getElementById('adminStationHistoryBody');
if (!modalEl || !titleEl || !bodyEl) {
return;
}

var rows = Array.isArray(historyRows) ? historyRows : [];
titleEl.textContent = 'Ownership history: ' + String(serial || '');
if (headerActionsEl) {
headerActionsEl.innerHTML = '<a href="' + escapeHtmlAdmin(String(stationMeasurementsUrl || '#')) + '" class="btn btn-outline-secondary btn-sm admin-ajax-link" title="View all station measurements" aria-label="View all station measurements"><i class="bi bi-graph-up"></i></a>';
}

if (!rows.length) {
bodyEl.innerHTML = '<div class="text-muted small">No history</div>';
} else {
bodyEl.innerHTML = '' +
'<div class="row g-2 mb-2" id="adminStationHistoryFiltersRowFrom">' +
'<div class="col-12 col-md-3">' +
'<div class="admin-multicombo" data-multi-combo id="stationHistoryOwnerCombo">' +
'<button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle"><span data-role="summary" data-base-label="Owner">Owner: all</span><i class="bi bi-chevron-down"></i></button>' +
'<div class="admin-multicombo-panel d-none" data-role="panel"><input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search"><div class="admin-multicombo-options" data-role="options" id="stationHistoryOwnerOptions"></div></div>' +
'</div>' +
'</div>' +
'<div class="col-12 col-md-3"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm js-admin-station-history-datetime" id="stationHistoryFilterFrom" placeholder="Registered from"><span class="input-group-text measurement-picker-icon"><i class="bi bi-calendar-event"></i></span></div></div>' +
'<div class="col-12 col-md-3"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm js-admin-station-history-datetime" id="stationHistoryFilterUnregFrom" placeholder="Unregistered from"><span class="input-group-text measurement-picker-icon"><i class="bi bi-calendar-event"></i></span></div></div>' +
'</div>' +
'<div class="row g-2 mb-3" id="adminStationHistoryFiltersRowTo">' +
'<div class="col-12 col-md-3 offset-md-3"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm js-admin-station-history-datetime" id="stationHistoryFilterTo" placeholder="Registered untill"><span class="input-group-text measurement-picker-icon"><i class="bi bi-calendar-event"></i></span></div></div>' +
'<div class="col-12 col-md-3"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm js-admin-station-history-datetime" id="stationHistoryFilterUnregTo" placeholder="Unregistered untill"><span class="input-group-text measurement-picker-icon"><i class="bi bi-calendar-event"></i></span></div></div>' +
'<div class="col-12 col-md-3 d-grid"><button type="button" class="btn btn-outline-secondary btn-sm" id="stationHistoryFilterClear">Clear</button></div>' +
'</div>' +
'<div class="table-responsive admin-station-history-rows-wrap"><table class="table table-sm align-middle mb-0"><thead><tr><th>Owner</th><th>Registered at</th><th>Unregistered at</th><th>Action</th></tr></thead><tbody id="adminStationHistoryRows"></tbody></table></div>';

var ownerOptions = bodyEl.querySelector('#stationHistoryOwnerOptions');
var fromInput = bodyEl.querySelector('#stationHistoryFilterFrom');
var toInput = bodyEl.querySelector('#stationHistoryFilterTo');
var unregFromInput = bodyEl.querySelector('#stationHistoryFilterUnregFrom');
var unregToInput = bodyEl.querySelector('#stationHistoryFilterUnregTo');
var clearBtn = bodyEl.querySelector('#stationHistoryFilterClear');
var tbody = bodyEl.querySelector('#adminStationHistoryRows');

var ownersMap = {};
rows.forEach(function (row) {
var uname = String(row.username || '').trim();
if (!uname) {
return;
}
ownersMap[uname] = true;
});

if (ownerOptions) {
Object.keys(ownersMap).sort().forEach(function (uname) {
var label = document.createElement('label');
label.className = 'admin-multicombo-option';
label.setAttribute('data-label', uname.toLowerCase());
label.innerHTML = '<input type="checkbox" value="' + escapeHtmlAdmin(uname) + '" class="js-station-history-owner-opt"> <span>' + escapeHtmlAdmin(uname) + '</span>';
ownerOptions.appendChild(label);
});
}

initAdminMultiCombos();

if (window.jQuery && jQuery.fn.datetimepicker) {
var $historyInputs = jQuery('#adminStationHistoryModal .js-admin-station-history-datetime');
$historyInputs.each(function () {
var $input = jQuery(this);
if ($input.data('dtp-initialized')) {
return;
}
$input.datetimepicker({
format: 'd.m.Y H:i',
timepicker: true,
step: 5,
dayOfWeekStart: 1,
scrollInput: false,
closeOnDateSelect: false,
onClose: function () {
$input.trigger('input');
}
});
$input.data('dtp-initialized', true);
});

jQuery('#adminStationHistoryModal .measurement-picker-icon').off('click.stationHistoryDate').on('click.stationHistoryDate', function () {
var $icon = jQuery(this);
var $input = $icon.siblings('input.js-admin-station-history-datetime').first();
if ($input.length) {
$input.trigger('focus');
try {
$input.datetimepicker('show');
} catch (e) {
// Focus fallback only.
}
}
});
}

function renderRows() {
if (!tbody) {
return;
}

var selectedOwners = Array.prototype.slice.call(bodyEl.querySelectorAll('.js-station-history-owner-opt:checked')).map(function (el) {
return String(el.value || '').trim();
});
var fromTs = parseDateTimeInputToTs(fromInput ? fromInput.value : '');
var toTs = parseDateTimeInputToTs(toInput ? toInput.value : '');
var unregFromTs = parseDateTimeInputToTs(unregFromInput ? unregFromInput.value : '');
var unregToTs = parseDateTimeInputToTs(unregToInput ? unregToInput.value : '');

var filtered = rows.filter(function (row) {
var username = String(row.username || '').trim();
if (selectedOwners.length && selectedOwners.indexOf(username) === -1) {
return false;
}

var regTs = parseDateTimeInputToTs(String(row.registeredAtRaw || ''));
if (fromTs !== null && regTs !== null && regTs < fromTs) {
return false;
}
if (toTs !== null && regTs !== null && regTs > toTs) {
return false;
}

var unregTs = parseDateTimeInputToTs(String(row.unregisteredAtRaw || ''));
if (unregFromTs !== null) {
if (unregTs === null || unregTs < unregFromTs) {
return false;
}
}
if (unregToTs !== null) {
if (unregTs === null || unregTs > unregToTs) {
return false;
}
}
return true;
});

if (!filtered.length) {
tbody.innerHTML = '<tr><td colspan="4" class="text-muted">No rows</td></tr>';
return;
}

tbody.innerHTML = filtered.map(function (row) {
var owner = buildMiniUserLink({
username: String(row.username || ''),
firstName: String(row.firstName || ''),
lastName: String(row.lastName || ''),
avatarUrl: String(row.avatarUrl || ''),
profileUrl: String(row.profileUrl || '#')
}, 'admin-modal-mini');
var registeredAt = String(row.registeredAt || '-');
var unregisteredAt = String(row.unregisteredAt || '-') || '-';
var url = String(row.measurementsUrl || '#');
var actionBtn = '<a href="' + escapeHtmlAdmin(url) + '" class="btn btn-sm btn-outline-primary admin-ajax-link" title="View measurements"><i class="bi bi-graph-up"></i></a>';
return '<tr><td>' + owner + '</td><td>' + escapeHtmlAdmin(registeredAt) + '</td><td>' + escapeHtmlAdmin(unregisteredAt) + '</td><td>' + actionBtn + '</td></tr>';
}).join('');
}

['input', 'change'].forEach(function (evt) {
if (fromInput) {
fromInput.addEventListener(evt, renderRows);
}
if (toInput) {
toInput.addEventListener(evt, renderRows);
}
if (unregFromInput) {
unregFromInput.addEventListener(evt, renderRows);
}
if (unregToInput) {
unregToInput.addEventListener(evt, renderRows);
}
});

bodyEl.addEventListener('change', function (e) {
if (e.target && e.target.matches('.js-station-history-owner-opt')) {
renderRows();
}
});

if (clearBtn) {
clearBtn.addEventListener('click', function () {
Array.prototype.slice.call(bodyEl.querySelectorAll('.js-station-history-owner-opt')).forEach(function (el) {
el.checked = false;
});
if (fromInput) fromInput.value = '';
if (toInput) toInput.value = '';
if (unregFromInput) unregFromInput.value = '';
if (unregToInput) unregToInput.value = '';
renderRows();
});
}

renderRows();
}

bootstrap.Modal.getOrCreateInstance(modalEl).show();
setTimeout(function () {
bodyEl.scrollTop = 0;
}, 0);
}

function openAdminUserFriendsModal(username, rawFriends) {
var modalEl = document.getElementById('adminUserFriendsModal');
var titleEl = document.getElementById('adminUserFriendsTitle');
var bodyEl = document.getElementById('adminUserFriendsBody');
if (!modalEl || !titleEl || !bodyEl) {
return;
}

var friends = [];
if (Array.isArray(rawFriends)) {
friends = rawFriends;
}

titleEl.textContent = 'Friends: @' + String(username || '');
if (!friends.length) {
bodyEl.innerHTML = '<div class="text-muted small">No friends</div>';
} else {
bodyEl.innerHTML = '<div class="collection-card-shares admin-modal-user-grid">' + friends.map(function (u) {
return buildMiniUserLink(u, 'admin-modal-mini');
}).join('') + '</div>';
}

bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function initAdminUsersFullTextViewer() {
if (document.body.dataset.adminUsersFullTextInited === '1') {
return;
}

document.body.dataset.adminUsersFullTextInited = '1';

document.addEventListener('click', function (e) {
var trigger = e.target.closest('.js-admin-open-full-text');
if (!trigger) {
return;
}

var modalEl = document.getElementById('adminUsersFullTextModal');
var titleEl = document.getElementById('adminUsersFullTextTitle');
var bodyEl = document.getElementById('adminUsersFullTextBody');
if (!modalEl || !titleEl || !bodyEl) {
return;
}

titleEl.textContent = String(trigger.getAttribute('data-title') || 'Text');
bodyEl.textContent = String(trigger.getAttribute('data-full-text') || '');
bootstrap.Modal.getOrCreateInstance(modalEl).show();
});
}

function initAdminMultiCombos() {
document.querySelectorAll('[data-multi-combo]').forEach(function (combo) {
if (combo.dataset.inited === '1') {
return;
}
combo.dataset.inited = '1';

var toggle = combo.querySelector('[data-role="toggle"]');
var summary = combo.querySelector('[data-role="summary"]');
var panel = combo.querySelector('[data-role="panel"]');
var search = combo.querySelector('[data-role="search"]');
var optionsWrap = combo.querySelector('[data-role="options"]');
if (!toggle || !summary || !panel || !optionsWrap) {
return;
}

function updateSummary() {
var checks = combo.querySelectorAll('input[type="checkbox"]');
var checked = combo.querySelectorAll('input[type="checkbox"]:checked');
var baseLabel = String(summary.getAttribute('data-base-label') || '').trim();
if (!baseLabel) {
baseLabel = toggle.textContent.split(':')[0].trim();
}
summary.textContent = baseLabel + ': ' + (checked.length === 0 ? 'all' : String(checked.length));
if (checks.length > 0 && checked.length === checks.length) {
summary.textContent = baseLabel + ': all';
}
}

toggle.addEventListener('click', function (e) {
e.preventDefault();
panel.classList.toggle('d-none');
if (!panel.classList.contains('d-none') && search) {
search.focus();
}
});

if (search) {
search.addEventListener('input', function () {
var q = String(search.value || '').trim().toLowerCase();
optionsWrap.querySelectorAll('.admin-multicombo-option').forEach(function (opt) {
var label = String(opt.getAttribute('data-label') || '').toLowerCase();
opt.classList.toggle('d-none', q !== '' && label.indexOf(q) === -1);
});
});
}

combo.addEventListener('change', function (e) {
if (e.target && e.target.matches('input[type="checkbox"]')) {
updateSummary();
}
});

document.addEventListener('click', function (e) {
if (!combo.contains(e.target)) {
panel.classList.add('d-none');
}
});

updateSummary();
});
}

function openCollectionDescriptionModal(collectionName, description) {
var titleEl = document.getElementById('collectionDescriptionModalTitle');
var bodyEl = document.getElementById('collectionDescriptionModalBody');
var modalEl = document.getElementById('collectionDescriptionModal');
if (!titleEl || !bodyEl || !modalEl) {
return;
}
titleEl.textContent = 'Description: ' + String(collectionName || '');
bodyEl.textContent = String(description || '');
new bootstrap.Modal(modalEl).show();
}

function onAdminTabContentUpdated(tab) {
try {
bindAudienceAndSearch('postAudience', 'postRecipientsWrap', 'postRecipientsSearch', '#postRecipientsList .group-member-item');
bindAudienceAndSearch('editPostAudience', 'editPostRecipientsWrap', 'editPostRecipientsSearch', '#editPostRecipientsList .group-member-item');
initAdminSlotDateTimeInputs();
initAdminCollectionsDateTimePickers();
hydrateSharedUsersCells();
initAdminMultiCombos();
initAdminSingleCombos();
initAdminCollectionsAutoFilter();
initAdminUsersAutoFilter();
initAdminStationsAutoFilter();
initAdminPostsAutoFilter();
initAdminUsersDatePickers();
initAdminStationsDatePickers();
initAdminPostsDatePickers();
initAdminUsersFullTextViewer();
fitCollectionDescriptionText();
fitAdminUsersText();
if (typeof window.enhancePaginationUI === 'function') {
window.enhancePaginationUI(document.getElementById('adminTabContent'));
}

if (tab === 'measurements' && typeof window.initMeasurementsClient === 'function') {
window.initMeasurementsClient();
}

if (tab === 'collections') {
var params = new URLSearchParams(window.location.search || '');
var sharedUsersModalId = Number(params.get('open_shared_users_modal') || 0);
var sharedUsersModalName = String(params.get('open_shared_users_name') || '').trim();
var token = [sharedUsersModalId, sharedUsersModalName].join('|');

if (token !== '0|' && token !== lastRestoredCollectionsModalToken) {
lastRestoredCollectionsModalToken = token;
if (sharedUsersModalId > 0) {
openCollectionSharedUsersModal(sharedUsersModalId, sharedUsersModalName);
}
}
}

if (tab === 'stations') {
var stationParams = new URLSearchParams(window.location.search || '');
var historySerial = String(stationParams.get('open_station_history_serial') || '').trim();
if (historySerial !== '') {
var stationToken = historySerial;
if (stationToken !== lastRestoredStationHistoryToken) {
lastRestoredStationHistoryToken = stationToken;
var historyBtn = document.querySelector('.js-admin-open-station-history[data-serial="' + CSS.escape(historySerial) + '"]');
if (historyBtn) {
historyBtn.click();
}
}
}
}
} catch (err) {
console.error(err);
}
}

function scheduleAdminLiveRefresh() {
if (adminLiveRefreshTimer) {
return;
}

adminLiveRefreshTimer = window.setInterval(function () {
if (document.hidden || adminLiveRefreshBusy) {
return;
}

if (document.querySelector('.modal.show')) {
return;
}

var activeEl = document.activeElement;
if (activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT' || activeEl.isContentEditable)) {
return;
}

var measurementsChartsPane = document.getElementById('measurementsChartsPane');
if (measurementsChartsPane && measurementsChartsPane.classList.contains('active') && measurementsChartsPane.classList.contains('show')) {
return;
}

if (getCurrentTabFromUrl() === 'measurements') {
return;
}

adminLiveRefreshBusy = true;
loadTabByUrl(window.location.href, false).then(function () {
adminLiveRefreshBusy = false;
}).catch(function () {
adminLiveRefreshBusy = false;
});
}, 15000);
}

function editUser(u) {
document.getElementById('editUserUsername').value = u.pk_username;
document.getElementById('editUserFn').value = u.firstName;
document.getElementById('editUserLn').value = u.lastName;
document.getElementById('editUserEmail').value = u.email;
document.getElementById('editUserRole').value = u.role;
var verifiedInput = document.getElementById('editUserIsEmailVerified');
if (verifiedInput) {
verifiedInput.checked = Number(u.isEmailVerified || 0) === 1;
}
new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function editStation(s) {
document.getElementById('editStSerial').value = s.pk_serialNumber;
document.getElementById('editStName').value = s.name || '';
document.getElementById('editStDesc').value = s.description || '';
var regByEl = document.getElementById('editStRegBy');
if (regByEl) {
regByEl.value = s.fk_registeredBy || '';
regByEl.dispatchEvent(new Event('change', { bubbles: true }));
}
new bootstrap.Modal(document.getElementById('editStationModal')).show();
}

function editPost(p) {
document.getElementById('editPostId').value = p.pk_postID;
document.getElementById('editPostTitle').value = p.title;
document.getElementById('editPostContent').value = p.content;

var editAudience = document.getElementById('editPostAudience');
var editRecipientChecks = document.querySelectorAll('#editPostRecipientsList .edit-post-recipient-check');
var recipients = Array.isArray(p.recipients) ? p.recipients : [];

if (editAudience) {
editAudience.value = p.audience || 'selected';
}

editRecipientChecks.forEach(function (cb) {
cb.checked = recipients.indexOf(cb.value) !== -1;
});

syncRecipientsVisibilityByIds('editPostAudience', 'editPostRecipientsWrap');
var modalEl = document.getElementById('editPostModal');
var modalBody = modalEl ? modalEl.querySelector('.modal-body') : null;
if (modalBody) {
modalBody.scrollTop = 0;
}
new bootstrap.Modal(modalEl).show();
window.setTimeout(function () {
var titleInput = document.getElementById('editPostTitle');
if (titleInput) {
titleInput.focus();
}
}, 30);
}

function editAdminCollection(c) {
var idEl = document.getElementById('editCollectionAdminId');
var ownerEl = document.getElementById('editCollectionAdminOwner');
var nameEl = document.getElementById('editCollectionAdminName');
var descEl = document.getElementById('editCollectionAdminDescription');
var modalEl = document.getElementById('editCollectionAdminModal');
var ownerSearchEl = document.getElementById('editCollectionAdminOwnerSearch');

if (!idEl || !ownerEl || !nameEl || !descEl || !modalEl) {
return;
}

idEl.value = c.pk_collectionID || '';
ownerEl.value = c.fk_user || '';
nameEl.value = c.name || '';
descEl.value = c.description || '';

if (ownerSearchEl) {
ownerSearchEl.value = String(c.fk_user || '').trim();
}

renderAdminOwnerPicker(ownerEl.value);

new bootstrap.Modal(modalEl).show();
}

function openCollectionSlotsModal(collectionId, collectionName) {
var modalEl = document.getElementById('collectionSlotsAdminModal');
var titleEl = document.getElementById('collectionSlotsAdminTitle');
var collectionIdEl = document.getElementById('collectionSlotsAdminCollectionId');
var tbody = document.getElementById('collectionSlotsAdminTbody');
var emptyEl = document.getElementById('collectionSlotsAdminEmpty');
var cfg = parseAdminCollectionsConfig();

if (!modalEl || !titleEl || !collectionIdEl || !tbody || !emptyEl) {
return;
}

modalEl.setAttribute('data-collection-id', String(collectionId || ''));
modalEl.setAttribute('data-collection-name', String(collectionName || ''));

var slotsMap = cfg.slotsByCollection && typeof cfg.slotsByCollection === 'object' ? cfg.slotsByCollection : {};
var slots = slotsMap[String(collectionId)] || [];

titleEl.textContent = 'Slots: ' + String(collectionName || '');
collectionIdEl.value = String(collectionId || '');

if (!slots.length) {
tbody.innerHTML = '';
emptyEl.classList.remove('d-none');
} else {
emptyEl.classList.add('d-none');
tbody.innerHTML = slots.map(function (slot) {
var station = slot.station_name || slot.fk_station || '';
var start = slot.startDateTime || '';
var end = slot.endDateTime || '';
var sampleId = slot.pk_sampleID || '';
var measurementsUrl = slot.measurements_url || '#';
return '' +
'<tr>' +
'<td>' + escapeHtmlAdmin(station || '-') + '</td>' +
'<td>' + escapeHtmlAdmin((start || '-') + ' - ' + (end || '-')) + '</td>' +
'<td>' +
'<a href="' + escapeHtmlAdmin(measurementsUrl) + '" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-eye"></i></a>' +
'<form method="post" class="d-inline">' +
'<input type="hidden" name="action" value="remove_collection_slot_admin">' +
'<input type="hidden" name="sample_id" value="' + escapeHtmlAdmin(String(sampleId)) + '">' +
'<button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>' +
'</form>' +
'</td>' +
'</tr>';
}).join('');
}

initAdminSlotDateTimeInputs();
var slotsModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
if (!modalEl.classList.contains('show')) {
slotsModalInstance.show();
}
}

window.editUser = editUser;
window.editStation = editStation;
window.editPost = editPost;
window.editAdminCollection = editAdminCollection;
window.openCollectionSlotsModal = openCollectionSlotsModal;
window.openCollectionSharedUsersModal = openCollectionSharedUsersModal;
window.openCollectionDescriptionModal = openCollectionDescriptionModal;

document.addEventListener('click', function (e) {
var tabLink = e.target.closest('#adminTabsNav a.nav-link');
if (tabLink) {
e.preventDefault();
loadTabByUrl(tabLink.href, true);
return;
}

var ajaxLink = e.target.closest('#adminTabContent a.admin-ajax-link, #adminTabContent .page-link');
if (!ajaxLink) {
return;
}

if (ajaxLink.closest('#measurementsPaginationNav') || ajaxLink.classList.contains('measurement-page-link')) {
return;
}

var href = ajaxLink.getAttribute('href') || '';
if (!href || href.indexOf('javascript:') === 0 || href.indexOf('#') === 0) {
return;
}

e.preventDefault();
closeOpenModals();
cleanupModalBackdrops();
loadTabByUrl(ajaxLink.href, true);
});

document.addEventListener('submit', function (e) {
var form = e.target.closest('#adminTabContent form');
if (!form) {
return;
}

var actionField = form.querySelector('input[name="action"]');
var actionValue = String((actionField && actionField.value) || '').trim();
if (actionValue === 'share_collection_admin') {
var shareInput = form.querySelector('input[name="share_with"]');
if (shareInput && String(shareInput.value || '').trim() === '') {
e.preventDefault();
setInlineAdminError('Select user to share');
return;
}
}

if (form.matches('#measurementFiltersForm')) {
return;
}

if (form.matches('#editMeasurementForm')) {
return;
}

var method = String(form.getAttribute('method') || 'get').toLowerCase();
e.preventDefault();

if (method === 'get') {
var url = new URL((form.getAttribute('action') || window.location.pathname), window.location.origin);
var fd = new FormData(form);
url.search = '';
fd.forEach(function (val, key) {
if (String(val).trim() !== '') {
url.searchParams.append(key, String(val));
}
});
loadTabByUrl(url.toString(), true);
return;
}

submitAdminFormAjax(form);
});

document.addEventListener('click', function (e) {
var ownerItem = e.target.closest('#editCollectionAdminOwnerList [data-admin-friend-item]');
if (ownerItem) {
var username = String(ownerItem.getAttribute('data-admin-friend-item') || '').trim();
var hiddenOwner = document.getElementById('editCollectionAdminOwner');
if (hiddenOwner) {
hiddenOwner.value = username;
}
renderAdminOwnerPicker(username);
return;
}

var shareItem = e.target.closest('#collectionShareAdminFriendsList [data-admin-friend-item]');
if (shareItem) {
var shareUsername = String(shareItem.getAttribute('data-admin-friend-item') || '').trim();
var hiddenShare = document.getElementById('collectionShareAdminUserSelect');
var collectionIdEl = document.getElementById('collectionShareAdminCollectionId');
if (hiddenShare) {
hiddenShare.value = shareUsername;
}
renderAdminShareFriendsList(Number((collectionIdEl && collectionIdEl.value) || 0), shareUsername);
}

var userFriendsBtn = e.target.closest('.js-admin-open-user-friends');
if (userFriendsBtn) {
var username = String(userFriendsBtn.getAttribute('data-username') || '').trim();
var raw = String(userFriendsBtn.getAttribute('data-friends') || '[]');
var friends = [];
try {
friends = JSON.parse(raw);
} catch (err) {
friends = [];
}
openAdminUserFriendsModal(username, friends);
}

var stationHistoryBtn = e.target.closest('.js-admin-open-station-history');
if (stationHistoryBtn) {
var stationSerial = String(stationHistoryBtn.getAttribute('data-serial') || '').trim();
var stationRaw = String(stationHistoryBtn.getAttribute('data-history') || '[]');
var stationAllMeasurementsUrl = String(stationHistoryBtn.getAttribute('data-station-measurements-url') || '#').trim();
var history = [];
try {
history = JSON.parse(stationRaw);
} catch (err) {
history = [];
}
openAdminStationHistoryModal(stationSerial, history, stationAllMeasurementsUrl);
}
});

document.addEventListener('input', function (e) {
if (e.target && e.target.id === 'editCollectionAdminOwnerSearch') {
renderAdminOwnerPicker((document.getElementById('editCollectionAdminOwner') || {}).value || '');
return;
}

if (e.target && e.target.id === 'collectionShareAdminSearch') {
var collectionIdEl = document.getElementById('collectionShareAdminCollectionId');
var hiddenShare = document.getElementById('collectionShareAdminUserSelect');
renderAdminShareFriendsList(Number((collectionIdEl && collectionIdEl.value) || 0), (hiddenShare && hiddenShare.value) || '');
}
});

window.addEventListener('popstate', function () {
loadTabByUrl(window.location.href, false);
});

window.addEventListener('resize', function () {
clearTimeout(resizeTimer);
resizeTimer = setTimeout(function () {
hydrateSharedUsersCells();
fitCollectionDescriptionText();
fitAdminUsersText();
}, 140);
});

document.addEventListener('change', function (e) {
var perPage = e.target.closest('#collectionsPerPage, #usersPerPage, #stationsPerPage');
if (!perPage) {
return;
}
var form = perPage.closest('form');
if (!form) {
return;
}
var event = new Event('submit', { bubbles: true, cancelable: true });
form.dispatchEvent(event);
});

document.addEventListener('DOMContentLoaded', function () {
setActiveTabInNav(getCurrentTabFromUrl());
onAdminTabContentUpdated(getCurrentTabFromUrl());
scheduleAdminAlertsAutoDismiss();
scheduleAdminLiveRefresh();
});
})();