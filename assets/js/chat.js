/* global $, bootstrap */
(function () {
	if (!window.chatPageData) {
		return;
	}

	var chatCurrentUser = window.chatPageData.currentUser;
	var chatConvIdInitial = Number(window.chatPageData.activeConvId || 0);
	var chatLastMsgIdInitial = Number(window.chatPageData.initialLastMsgId || 0);
	var i18n = window.chatPageData.i18n || {};

	var chatViewProfileTitle = String(i18n.viewProfileTitle || '');
	var chatErrorText = String(i18n.errorText || 'Error');
	var chatNoConversationsText = String(i18n.noConversationsText || '');
	var chatGroupChatText = String(i18n.groupChatText || '');
	var chatTypeMessageText = String(i18n.typeMessageText || '');
	var chatUploadFileText = String(i18n.uploadFileText || '');
	var chatBackText = String(i18n.backText || '');
	var chatGroupInfoText = String(i18n.groupInfoText || '');
	var chatGroupOwnerText = String(i18n.groupOwnerText || '');
	var chatRemoveMemberText = String(i18n.removeMemberText || '');
	var chatNoMembersYetText = String(i18n.noMembersYetText || '');
	var chatNoFriendsToAddText = String(i18n.noFriendsToAddText || '');
	var chatConfirmRemoveMemberText = String(i18n.confirmRemoveMemberText || '');
	var chatGroupNameRequiredText = String(i18n.groupNameRequiredText || '');
	var chatUnknownUserText = String(i18n.unknownUserText || 'Unknown user');
	var chatLeaveGroupText = String(i18n.leaveGroupText || 'Leave group');
	var chatConfirmLeaveGroupText = String(i18n.confirmLeaveGroupText || 'Leave this group?');
	var chatGroupLeftNoticeText = String(i18n.groupLeftNoticeText || '{name} left the group');
	var chatGroupJoinedNoticeText = String(i18n.groupJoinedNoticeText || '{name} joined the group');

	function esc(str) {
		return $('<div>').text(str || '').html();
	}

	function timeFromDateStr(dateStr) {
		if (!dateStr) return '';
		var parts = String(dateStr).split(' ');
		if (parts.length >= 2) return parts[1].substring(0, 5);
		return '';
	}

	function getChatBackQuery(convId) {
		var back = '/user/chat.php';
		if (convId) {
			back += '?conv=' + encodeURIComponent(convId);
		}
		return '&back=' + encodeURIComponent(back);
	}

	function resolveChatName(displayName, fallbackName) {
		var d = String(displayName || '').trim();
		if (d === '__unknown_user__') {
			return chatUnknownUserText;
		}
		if (d) return d;

		var f = String(fallbackName || '').trim();
		if (f === '__unknown_user__') {
			return chatUnknownUserText;
		}
		if (f) return f;
		return 'Chat';
	}

	function parseSystemToken(text) {
		var match = String(text || '').match(/^\[\[sys:([a-z_]+)\|([^\]|]*)\|([^\]]*)\]\]$/);
		if (!match) return null;
		return {
			type: String(match[1] || ''),
			actorUsername: String(match[2] || '').trim(),
			actorName: String(match[3] || '').trim()
		};
	}

	function formatGroupLeftNotice(name) {
		var actor = String(name || '').trim() || chatUnknownUserText;
		return chatGroupLeftNoticeText.replace('{name}', actor);
	}

	function formatGroupJoinedNotice(name) {
		var actor = String(name || '').trim() || chatUnknownUserText;
		return chatGroupJoinedNoticeText.replace('{name}', actor);
	}

	function getConversationPreview(c) {
		if (c && c.last_message) {
			var parsed = parseSystemToken(c.last_message);
			if (parsed && parsed.type === 'left_group') {
				return formatGroupLeftNotice(parsed.actorName || parsed.actorUsername);
			}
			if (parsed && parsed.type === 'joined_group') {
				return formatGroupJoinedNotice(parsed.actorName || parsed.actorUsername);
			}
		}
		return String((c && c.last_message) || '');
	}

	function updateChatUnreadBadges(payload) {
		if (!payload || !payload.by_conversation) return;

		$('.chat-conv-unread').each(function () {
			var convId = String($(this).data('conv-unread') || '');
			var count = parseInt(payload.by_conversation[convId] || 0, 10);
			if (count > 0) {
				$(this).text(count).removeClass('d-none');
			} else {
				$(this).text('0').addClass('d-none');
			}
		});

		if ($('#chatUnreadBadge').length) {
			var total = parseInt(payload.total || 0, 10);
			if (total > 0) {
				$('#chatUnreadBadge').text(total).removeClass('d-none');
			} else {
				$('#chatUnreadBadge').text('0').addClass('d-none');
			}
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		var chatConvId = chatConvIdInitial;
		var chatLastMsgId = chatLastMsgIdInitial;
		var chatSelectedFiles = [];
		var pollMessagesTimer = null;
		var pollChatsTimer = null;
		var pollUnreadTimer = null;
		var draftSaveTimer = null;
		var chatDraftFiles = [];
		var draftDirty = false;
		var lastDraftValue = '';
		var searchTimer;
		var newGroupSearchTimer = null;
		var groupAddSearchTimer = null;
		var selectedNewGroupMembers = {};
		var selectedGroupAddMembers = {};
		var newGroupAvatarFile = null;
		var groupInfoSaveTimer = null;
		var groupInfoIsOwner = false;
		var groupInfoLoadedConvId = 0;
		var keepGroupInfoState = false;
		var GROUP_MODAL_STATE_KEY = 'chatGroupInfoModalState';
		var NEW_GROUP_MODAL_STATE_KEY = 'chatNewGroupModalState';
		var CHAT_SEARCH_STORAGE_KEY = 'chatSearchQuery';
		var chatReferrer = document.referrer || '';
		var keepNewGroupState = false;

		function setMobilePane(showMain) {
			$('#chatContainer').toggleClass('chat-mobile-open', !!showMain);
		}

		function renderAttachmentPreview() {
			var preview = $('#attachmentPreview');
			if (!preview.length) return;
			preview.empty();
			chatSelectedFiles.forEach(function (f, idx) {
				var pill = $('<span class="attachment-pill me-1 mb-1"></span>');
				pill.append($('<span></span>').text(f.name));
				var closeBtn = $('<button type="button" class="attachment-pill-close ms-1" aria-label="Remove">&times;</button>');
				closeBtn.on('click', function () {
					chatSelectedFiles.splice(idx, 1);
					renderAttachmentPreview();
				});
				pill.append(closeBtn);
				preview.append(pill);
			});

			chatDraftFiles.forEach(function (f) {
				var pill = $('<span class="attachment-pill me-1 mb-1"></span>');
				pill.append($('<span></span>').text(f.file_name || f.file_path || 'file'));
				var closeBtn = $('<button type="button" class="attachment-pill-close ms-1" aria-label="Remove">&times;</button>');
				closeBtn.on('click', function () {
					$.post('/api/chat.php', {
						action: 'remove_draft_file',
						conversation_id: chatConvId,
						draft_file_id: f.pk_draft_file_id
					}, function (res) {
						if (res && res.success) {
							chatDraftFiles = Array.isArray(res.files) ? res.files : [];
							renderAttachmentPreview();
						}
					}, 'json');
				});
				pill.append(closeBtn);
				preview.append(pill);
			});
		}

		function saveDraftNow() {
			if (!chatConvId || !$('#chatMsg').length) return;
			var currentDraft = $('#chatMsg').val() || '';
			$.post('/api/chat.php', {
				action: 'save_draft',
				conversation_id: chatConvId,
				draft: currentDraft
			}, function (res) {
				if (res && res.success) {
					draftDirty = false;
					lastDraftValue = currentDraft;
				}
			}, 'json');
		}

		function scheduleDraftSave() {
			clearTimeout(draftSaveTimer);
			draftSaveTimer = setTimeout(saveDraftNow, 350);
		}

		function messageHtml(m) {
			if (m && m.system_type === 'left_group') {
				var systemActor = String(m.system_actor_name || '').trim() || String(m.system_actor_username || '').trim() || chatUnknownUserText;
				return '<div class="chat-date-separator chat-system-message"><span>' + esc(formatGroupLeftNotice(systemActor)) + '</span></div>';
			}
			if (m && m.system_type === 'joined_group') {
				var joinedActor = String(m.system_actor_name || '').trim() || String(m.system_actor_username || '').trim() || chatUnknownUserText;
				return '<div class="chat-date-separator chat-system-message"><span>' + esc(formatGroupJoinedNotice(joinedActor)) + '</span></div>';
			}

			var isOwn = m.fk_sender === chatCurrentUser;
			var html = '<div class="chat-message ' + (isOwn ? 'own' : 'other') + '">';
			if (!isOwn) {
				html += '<div class="d-flex align-items-center gap-2 mb-1">';
				html += m.avatar_url
					? '<img src="' + esc(m.avatar_url) + '" class="chat-msg-avatar" alt="avatar">'
					: '<i class="bi bi-person-circle text-muted"></i>';
				var senderName = String(((m.firstName || '') + ' ' + (m.lastName || '')).trim());
				if (!senderName) {
					senderName = String(m.fk_sender || '').trim() || chatUnknownUserText;
				}
				if (senderName === '__unknown_user__') {
					senderName = chatUnknownUserText;
				}
				html += '<small class="text-muted d-block mb-0">' + esc(senderName) + '</small>';
				html += '</div>';
			}
			html += '<div class="bubble">';
			if (m.file_name) {
				var viewUrl = '/download_chat_file.php?id=' + encodeURIComponent(m.pk_messageID) + '&mode=view';
				var downloadUrl = '/download_chat_file.php?id=' + encodeURIComponent(m.pk_messageID) + '&mode=download';
				html += '<div class="d-flex align-items-center justify-content-between">';
				html += '<a href="' + viewUrl + '" target="_blank" class="text-white text-truncate me-2">';
				html += '<i class="bi bi-file-earmark me-1"></i>' + esc(m.file_name) + '</a>';
				html += '<a href="' + downloadUrl + '" class="btn btn-sm btn-outline-light" download><i class="bi bi-download"></i></a>';
				html += '</div>';
			} else {
				html += esc(m.message || '');
			}
			html += '</div><small class="text-muted">' + esc(timeFromDateStr(m.createdAt)) + '</small></div>';
			return html;
		}

		function renderEmptyMain() {
			$('#chatMain').html('<div class="d-flex align-items-center justify-content-center flex-grow-1 text-muted"><div class="text-center"><i class="bi bi-chat-dots display-1 mb-3"></i><p>' + esc(chatNoConversationsText) + '</p></div></div>');
		}

		function renderConversationMain(conversation, messages, draftText, draftFiles) {
			var convName = resolveChatName(conversation.display_name, conversation.name);
			var headerHtml = '<div class="p-3 border-bottom d-flex justify-content-between align-items-center chat-main-header">';
			if ((conversation.type || '') === 'group') {
				headerHtml += '<div class="d-flex align-items-center gap-2">';
				headerHtml += '<button type="button" class="btn btn-sm btn-outline-secondary d-sm-none" id="chatBackBtn" title="' + esc(chatBackText) + '"><i class="bi bi-arrow-left"></i></button>';
				headerHtml += '<button type="button" class="chat-group-header-trigger" id="groupHeaderInfoTrigger" data-conv-id="' + Number(conversation.pk_conversationID) + '" title="' + esc(chatGroupInfoText) + '">';
				headerHtml += conversation.avatar_url
					? '<img src="' + esc(conversation.avatar_url) + '" class="chat-header-avatar" alt="avatar">'
					: '<span class="chat-header-avatar"><i class="bi bi-people-fill"></i></span>';
				headerHtml += '<strong id="groupHeaderInfoTitle">' + esc(convName) + '</strong>';
				headerHtml += '</button>';
				headerHtml += '<span class="badge bg-secondary">' + esc(chatGroupChatText) + '</span></div>';
				headerHtml += '<button type="button" class="btn btn-sm btn-outline-danger" id="chatLeaveGroupBtn" title="' + esc(chatLeaveGroupText) + '"><i class="bi bi-box-arrow-right"></i></button>';
			} else {
				headerHtml += '<div class="d-flex align-items-center gap-2">';
				headerHtml += '<button type="button" class="btn btn-sm btn-outline-secondary d-sm-none" id="chatBackBtn" title="' + esc(chatBackText) + '"><i class="bi bi-arrow-left"></i></button>';
				headerHtml += conversation.avatar_url
					? '<img src="' + esc(conversation.avatar_url) + '" class="chat-header-avatar" alt="avatar">'
					: '<span class="chat-header-avatar"><i class="bi bi-person-circle"></i></span>';
				headerHtml += '<strong>' + esc(convName) + '</strong>';
				if (conversation.other_username) {
					headerHtml += '<a href="/user/view_profile.php?user=' + encodeURIComponent(conversation.other_username) + getChatBackQuery(conversation.pk_conversationID) + '" class="btn btn-sm btn-outline-secondary" title="' + esc(chatViewProfileTitle) + '"><i class="bi bi-person"></i></a>';
				}
				headerHtml += '</div>';
			}
			headerHtml += '</div>';

			var listHtml = '';
			$.each(messages || [], function (_, m) {
				listHtml += messageHtml(m);
			});

			var formHtml = '';
			formHtml += '<div class="chat-input-area"><div id="attachmentPreview" class="attachment-preview"></div>';
			formHtml += '<form id="chatForm" enctype="multipart/form-data">';
			formHtml += '<input type="hidden" id="convId" value="' + Number(conversation.pk_conversationID) + '">';
			formHtml += '<div class="d-flex gap-2">';
			formHtml += '<input type="text" id="chatMsg" class="form-control" placeholder="' + esc(chatTypeMessageText) + '" value="' + esc(draftText || '') + '">';
			formHtml += '<label class="btn btn-outline-secondary" title="' + esc(chatUploadFileText) + '">';
			formHtml += '<i class="bi bi-paperclip"></i><input type="file" id="chatFile" name="files[]" class="d-none" multiple>';
			formHtml += '</label><button type="submit" class="btn btn-primary"><i class="bi bi-send"></i></button>';
			formHtml += '</div></form></div>';

			$('#chatMain').html(headerHtml + '<div class="chat-messages" id="chatMessages">' + listHtml + '</div>' + formHtml);
			var cm = document.getElementById('chatMessages');
			if (cm) cm.scrollTop = cm.scrollHeight;
			chatSelectedFiles = [];
			chatDraftFiles = Array.isArray(draftFiles) ? draftFiles : [];
			lastDraftValue = String(draftText || '');
			draftDirty = false;
			renderAttachmentPreview();
		}

		function isSameDraftFiles(a, b) {
			if (!Array.isArray(a) || !Array.isArray(b)) return false;
			if (a.length !== b.length) return false;
			for (var i = 0; i < a.length; i++) {
				var left = String((a[i] && a[i].pk_draft_file_id) || '');
				var right = String((b[i] && b[i].pk_draft_file_id) || '');
				if (left !== right) return false;
			}
			return true;
		}

		function syncDraftFromServer(forceApply) {
			if (!chatConvId || !$('#chatMsg').length) return;
			$.get('/api/chat.php', { action: 'get_draft', conversation_id: chatConvId }, function (res) {
				if (!res || !res.success) return;

				var serverDraft = String(res.draft || '');
				var serverFiles = Array.isArray(res.files) ? res.files : [];
				var localDraft = String($('#chatMsg').val() || '');
				var canApply = forceApply || (!draftDirty && localDraft === lastDraftValue);

				if (canApply) {
					if (localDraft !== serverDraft) {
						$('#chatMsg').val(serverDraft);
					}
					if (!isSameDraftFiles(chatDraftFiles, serverFiles)) {
						chatDraftFiles = serverFiles;
						renderAttachmentPreview();
					}
					lastDraftValue = serverDraft;
					draftDirty = false;
				}
			}, 'json');
		}

		function userProfileButton(username, convId) {
			if (!username || username === chatCurrentUser) {
				return '';
			}
			return '<a href="/user/view_profile.php?user=' + encodeURIComponent(username) + getChatBackQuery(convId) + '" class="btn btn-sm btn-outline-secondary chat-view-profile-btn" title="' + esc(chatViewProfileTitle) + '"><i class="bi bi-person"></i></a>';
		}

		function renderFriendSearchResults(containerSelector, users, selectedMap, idPrefix, convId) {
			var $list = $(containerSelector);
			$list.empty();

			if (!users || !users.length) {
				$list.append('<div class="text-muted small px-3 py-1">' + esc(chatNoFriendsToAddText) + '</div>');
				return;
			}

			$.each(users, function (_, u) {
				var uname = String(u.pk_username || '');
				if (!uname) return;
				var checked = !!selectedMap[uname];
				var safeId = idPrefix + uname.replace(/[^a-zA-Z0-9_-]/g, '_');

				var row = '';
				row += '<div class="group-member-item group-member-row d-flex align-items-center gap-2 px-2 py-1">';
				row += '<input class="form-check-input group-member-check mt-0" type="checkbox" value="' + esc(uname) + '" id="' + esc(safeId) + '" ' + (checked ? 'checked' : '') + '>';
				row += '<label class="form-check-label d-flex align-items-center gap-2 flex-grow-1 mb-0" for="' + esc(safeId) + '" style="min-width:0;">';
				row += u.avatar_url
					? '<img src="' + esc(u.avatar_url) + '" class="chat-msg-avatar" alt="avatar">'
					: '<i class="bi bi-person-circle text-muted"></i>';
				row += '<span style="min-width:0;"><span class="d-block text-truncate">' + esc((u.firstName || '') + ' ' + (u.lastName || '')) + '</span><small class="text-muted d-block">@' + esc(uname) + '</small></span>';
				row += '</label>';
				row += userProfileButton(uname, convId);
				row += '</div>';
				$list.append(row);
			});
		}

		function searchNewGroupFriends(query) {
			if (!query) {
				$('#newGroupMemberList').html('<div class="text-muted small px-3 py-1">' + esc(chatNoFriendsToAddText) + '</div>');
				return;
			}

			$.get('/api/chat.php', { action: 'search_group_friends', query: query }, function (res) {
				if (!res || !res.success) {
					$('#newGroupMemberList').html('<div class="text-muted small px-3 py-1">' + esc(chatErrorText) + '</div>');
					return;
				}
				renderFriendSearchResults('#newGroupMemberList', res.users || [], selectedNewGroupMembers, 'ngm_', 0);
			}, 'json').fail(function () {
				$('#newGroupMemberList').html('<div class="text-muted small px-3 py-1">' + esc(chatErrorText) + '</div>');
			});
		}

		function searchGroupAddableFriends(query) {
			if (!chatConvId || !query) {
				$('#groupInfoAddList').html('<div class="text-muted small px-3 py-1">' + esc(chatNoFriendsToAddText) + '</div>');
				return;
			}

			$.get('/api/chat.php', { action: 'search_group_friends', query: query, chat_id: chatConvId }, function (res) {
				if (!res || !res.success) {
					$('#groupInfoAddList').html('<div class="text-muted small px-3 py-1">' + esc(chatErrorText) + '</div>');
					return;
				}
				renderFriendSearchResults('#groupInfoAddList', res.users || [], selectedGroupAddMembers, 'gaf_', chatConvId);
			}, 'json').fail(function () {
				$('#groupInfoAddList').html('<div class="text-muted small px-3 py-1">' + esc(chatErrorText) + '</div>');
			});
		}

		function updateGroupAvatarPreview(avatarUrl) {
			if (avatarUrl) {
				$('#groupInfoAvatarPreview').attr('src', avatarUrl).removeClass('d-none');
				$('#groupInfoAvatarIcon').addClass('d-none');
				if (groupInfoIsOwner) {
					$('#groupInfoAvatarClearBtn').removeClass('d-none');
				}
			} else {
				$('#groupInfoAvatarPreview').attr('src', '').addClass('d-none');
				$('#groupInfoAvatarIcon').removeClass('d-none');
				$('#groupInfoAvatarClearBtn').addClass('d-none');
			}
		}

		function applyGroupInfoData(d) {
			groupInfoIsOwner = d.createdBy === chatCurrentUser;
			groupInfoLoadedConvId = Number(d.id || chatConvId || 0);

			$('#groupInfoModalTitle').text(d.name || chatGroupInfoText);
			$('#groupInfoNameView').text(d.name || chatGroupInfoText);
			$('#groupInfoNameInput').val(d.name || '');

			if (groupInfoIsOwner) {
				$('#groupInfoNameView').addClass('d-none');
				$('#groupInfoNameEdit').removeClass('d-none');
				$('#groupInfoDescription').addClass('d-none');
				$('#groupInfoDescriptionEdit').removeClass('d-none');
				$('#groupInfoDescriptionInput').val(d.description || '');
				$('#groupInfoAvatarEdit').removeClass('d-none');
			} else {
				$('#groupInfoNameView').removeClass('d-none');
				$('#groupInfoNameEdit').addClass('d-none');
				$('#groupInfoDescription').removeClass('d-none').text(d.description || '');
				$('#groupInfoDescriptionEdit').addClass('d-none');
				$('#groupInfoAvatarEdit').addClass('d-none');
			}

			updateGroupAvatarPreview(d.avatar_url || '');

			var ul = $('#groupInfoMembers');
			ul.empty();
			if (d.members && d.members.length) {
				$.each(d.members, function (_, m) {
					var badge = m.role === 'owner' ? ' <span class="badge bg-secondary ms-1">' + esc(chatGroupOwnerText) + '</span>' : '';
					var removeBtn = '';
					if (groupInfoIsOwner && m.role !== 'owner') {
						removeBtn = ' <button type="button" class="btn btn-sm btn-outline-danger group-remove-member ms-1 py-0 px-1" data-username="' + esc(m.pk_username) + '" title="' + esc(chatRemoveMemberText) + '">&times;</button>';
					}
					var memberProfileBtn = userProfileButton(m.pk_username, groupInfoLoadedConvId);
					var memberAvatar = m.avatar_url
						? '<img src="' + esc(m.avatar_url) + '" class="chat-msg-avatar" alt="avatar">'
						: '<i class="bi bi-person-circle text-muted"></i>';
					ul.append('<li class="py-1 d-flex align-items-center gap-2"><span class="d-flex align-items-center gap-2 flex-grow-1" style="min-width:0;">' + memberAvatar + '<span style="min-width:0;"><span class="d-block text-truncate">' + esc(m.firstName + ' ' + m.lastName) + badge + '</span><small class="text-muted d-block">@' + esc(m.pk_username) + '</small></span></span>' + memberProfileBtn + removeBtn + '</li>');
				});
			} else {
				ul.append('<li class="text-muted small">' + esc(chatNoMembersYetText) + '</li>');
			}

			if (groupInfoIsOwner) {
				$('#groupInfoAddDivider').removeClass('d-none');
				$('#groupInfoAddSection').removeClass('d-none');
				$('#groupInfoAddList').html('<div class="text-muted small px-3 py-1">' + esc(chatNoFriendsToAddText) + '</div>');
			} else {
				$('#groupInfoAddDivider').addClass('d-none');
				$('#groupInfoAddSection').addClass('d-none');
			}
		}

		function saveGroupInfoState() {
			if (!$('#groupInfoModal').hasClass('show') || !groupInfoLoadedConvId) {
				return;
			}

			var state = {
				convId: groupInfoLoadedConvId,
				isOwner: !!groupInfoIsOwner,
				name: String($('#groupInfoNameInput').val() || ''),
				description: String($('#groupInfoDescriptionInput').val() || ''),
				addSearch: String($('#groupInfoMemberSearch').val() || ''),
				selectedGroupAddMembers: selectedGroupAddMembers
			};
			sessionStorage.setItem(GROUP_MODAL_STATE_KEY, JSON.stringify(state));
		}

		function clearGroupInfoState() {
			sessionStorage.removeItem(GROUP_MODAL_STATE_KEY);
		}

		function scheduleGroupInfoSave() {
			if (!groupInfoIsOwner || !groupInfoLoadedConvId) {
				return;
			}

			clearTimeout(groupInfoSaveTimer);
			groupInfoSaveTimer = setTimeout(function () {
				var name = $('#groupInfoNameInput').val().trim();
				var desc = $('#groupInfoDescriptionInput').val();

				$.post('/api/chat.php', { action: 'update_group', chat_id: groupInfoLoadedConvId, name: name, description: desc }, function (res) {
					if (res && res.success && res.data) {
						$('#groupInfoError').addClass('d-none');
						$('#groupInfoModalTitle').text(res.data.name || chatGroupInfoText);
						$('#groupInfoNameView').text(res.data.name || chatGroupInfoText);
						$('#groupInfoNameInput').val(res.data.name || '');
						$('#groupHeaderInfoTitle').text(res.data.name || chatGroupInfoText);
						$('#groupInfoDescription').text(res.data.description || '');
						$('#groupInfoDescriptionInput').val(res.data.description || '');
						saveGroupInfoState();
						loadChatsList();
					} else {
						$('#groupInfoError').removeClass('d-none').text((res && res.message) ? res.message : chatErrorText);
					}
				}, 'json').fail(function () {
					$('#groupInfoError').removeClass('d-none').text(chatErrorText);
				});
			}, 350);
		}

		function restoreGroupInfoFromStateIfNeeded() {
			var raw = sessionStorage.getItem(GROUP_MODAL_STATE_KEY);
			if (!raw) {
				return;
			}

			var state;
			try {
				state = JSON.parse(raw);
			} catch (e) {
				clearGroupInfoState();
				return;
			}

			var convId = Number((state && state.convId) || 0);
			if (!convId || convId !== chatConvId) {
				return;
			}

			loadGroupInfo(convId, state);
		}

		function renderChatsList(chats) {
			var html = '';
			if (!chats || !chats.length) {
				html = '<div class="text-center text-muted py-4 small">' + esc(chatNoConversationsText) + '</div>';
			} else {
				$.each(chats, function (_, c) {
					var convId = Number(c.pk_conversationID);
					var activeClass = (chatConvId === convId) ? ' active' : '';
					html += '<a href="/user/chat.php?conv=' + convId + '" data-conv-id="' + convId + '" class="text-decoration-none chat-conv-item' + activeClass + '">';
					if ((c.type || '') === 'group') {
						html += c.avatar_url
							? '<img src="' + esc(c.avatar_url) + '" class="conv-avatar" alt="avatar">'
							: '<span class="conv-avatar"><i class="bi bi-people-fill"></i></span>';
					} else if (c.avatar_url) {
						html += '<img src="' + esc(c.avatar_url) + '" class="conv-avatar" alt="avatar">';
					} else {
						html += '<span class="conv-avatar"><i class="bi bi-person-circle"></i></span>';
					}
					html += '<div class="conv-info"><div class="conv-name">' + esc(resolveChatName(c.display_name, c.name)) + '</div><div class="conv-preview">' + esc(getConversationPreview(c)) + '</div></div>';
					var unread = Number(c.unread_count || 0);
					html += '<span class="badge rounded-pill bg-danger ms-2 chat-conv-unread ' + (unread > 0 ? '' : 'd-none') + '" data-conv-unread="' + convId + '">' + unread + '</span></a>';
				});
			}
			$('#chatSidebarList').html(html);
		}

		function loadUnreadCounts() {
			$.get('/api/chat.php', { action: 'get_unread_counts' }, function (res) {
				if (res && res.success) updateChatUnreadBadges(res);
			}, 'json');
		}

		function loadChatsList() {
			$.get('/api/chat.php', { action: 'get_chats' }, function (res) {
				if (res && res.success) renderChatsList(res.chats || []);
			}, 'json');
		}

		function loadConversation(convId, pushHistory) {
			$.get('/api/chat.php', { action: 'get_conversation', conversation_id: convId }, function (res) {
				if (!res || !res.success) {
					alert((res && res.message) ? res.message : chatErrorText);
					return;
				}
				var conv = res.conversation || {};
				chatConvId = Number(conv.pk_conversationID || 0);
				chatLastMsgId = Number(res.last_message_id || 0);
				renderConversationMain(conv, res.messages || [], res.draft_text || '', res.draft_files || []);
				setMobilePane(true);
				if (pushHistory) {
					window.history.pushState({ conv: chatConvId }, '', '/user/chat.php?conv=' + chatConvId);
				}
				loadChatsList();
				loadUnreadCounts();
				restoreGroupInfoFromStateIfNeeded();
			}, 'json').fail(function () {
				alert(chatErrorText);
			});
		}

		function loadNewMessages() {
			if (!chatConvId) return;
			$.get('/api/chat.php', { action: 'get_messages', conversation_id: chatConvId, since_id: chatLastMsgId }, function (res) {
				if (res && res.success && res.messages && res.messages.length) {
					var $messages = $('#chatMessages');
					$.each(res.messages, function (_, m) {
						$messages.append(messageHtml(m));
						chatLastMsgId = Number(m.pk_messageID || chatLastMsgId);
					});
					var cm = document.getElementById('chatMessages');
					if (cm) cm.scrollTop = cm.scrollHeight;
					loadChatsList();
					loadUnreadCounts();
				}
				syncDraftFromServer(false);
			}, 'json');
		}

		function openPrivateChat(otherUser) {
			$('#userSearch').val('');
			$('#searchResults').addClass('d-none').html('');
			sessionStorage.removeItem(CHAT_SEARCH_STORAGE_KEY);

			$.post('/api/chat.php', { action: 'create_private_chat', with_user: otherUser }, function (res) {
				if (res && res.success) {
					loadConversation(Number(res.conversation_id), true);
				} else {
					alert((res && res.message) ? res.message : chatErrorText);
				}
			}, 'json').fail(function () {
				alert(chatErrorText);
			});
		}

		function loadGroupInfo(convId, restoreState) {
			$('#groupInfoError').addClass('d-none');
			$('#groupInfoMembers').empty();
			$('#groupInfoAddList').empty();
			$('#groupInfoMemberSearch').val('');
			selectedGroupAddMembers = {};

			$.get('/api/chat.php', { action: 'get_group_info', chat_id: convId }, function (res) {
				if (!res.success) {
					$('#groupInfoError').removeClass('d-none').text(res.message || chatErrorText);
					$('#groupInfoModal').modal('show');
					return;
				}

				var d = res.data;
				applyGroupInfoData(d);

				if (restoreState && restoreState.isOwner && groupInfoIsOwner) {
					$('#groupInfoNameInput').val(String(restoreState.name || d.name || ''));
					$('#groupInfoDescriptionInput').val(String(restoreState.description || d.description || ''));
					selectedGroupAddMembers = (restoreState.selectedGroupAddMembers && typeof restoreState.selectedGroupAddMembers === 'object')
						? restoreState.selectedGroupAddMembers
						: {};

					var addSearch = String(restoreState.addSearch || '');
					$('#groupInfoMemberSearch').val(addSearch);
					if (addSearch) {
						searchGroupAddableFriends(addSearch);
					}
				}

				saveGroupInfoState();

				$('#groupInfoModal').modal('show');
			}, 'json').fail(function () {
				$('#groupInfoError').removeClass('d-none').text(chatErrorText);
				$('#groupInfoModal').modal('show');
			});
		}

		function updateNewGroupAvatarPreview(avatarUrl) {
			if (avatarUrl) {
				$('#newGroupAvatarPreview').attr('src', avatarUrl).removeClass('d-none');
				$('#newGroupAvatarIcon').addClass('d-none');
				$('#newGroupAvatarClearBtn').removeClass('d-none');
			} else {
				$('#newGroupAvatarPreview').attr('src', '').addClass('d-none');
				$('#newGroupAvatarIcon').removeClass('d-none');
				$('#newGroupAvatarClearBtn').addClass('d-none');
			}
		}

		function saveNewGroupState() {
			if (!$('#newGroupModal').hasClass('show')) {
				return;
			}

			var state = {
				name: String($('#groupName').val() || ''),
				description: String($('#groupDescription').val() || ''),
				search: String($('#newGroupMemberSearch').val() || ''),
				selectedMembers: selectedNewGroupMembers
			};
			sessionStorage.setItem(NEW_GROUP_MODAL_STATE_KEY, JSON.stringify(state));
		}

		function clearNewGroupState() {
			sessionStorage.removeItem(NEW_GROUP_MODAL_STATE_KEY);
		}

		function restoreNewGroupFromState() {
			var raw = sessionStorage.getItem(NEW_GROUP_MODAL_STATE_KEY);
			if (!raw) {
				return;
			}

			try {
				var state = JSON.parse(raw);
				$('#groupName').val(String((state && state.name) || ''));
				$('#groupDescription').val(String((state && state.description) || ''));
				selectedNewGroupMembers = (state && state.selectedMembers && typeof state.selectedMembers === 'object') ? state.selectedMembers : {};

				var q = String((state && state.search) || '');
				$('#newGroupMemberSearch').val(q);
				if (q) {
					searchNewGroupFriends(q);
				}
			} catch (e) {
				clearNewGroupState();
			}
		}

		if (chatReferrer.indexOf('/user/view_profile.php') === -1) {
			sessionStorage.removeItem(CHAT_SEARCH_STORAGE_KEY);
		}
		var savedChatQuery = sessionStorage.getItem(CHAT_SEARCH_STORAGE_KEY) || '';
		if (savedChatQuery) {
			$('#userSearch').val(savedChatQuery);
		}

		if (chatReferrer.indexOf('/user/view_profile.php') !== -1 && sessionStorage.getItem(NEW_GROUP_MODAL_STATE_KEY)) {
			var newGroupModalEl = document.getElementById('newGroupModal');
			if (newGroupModalEl) {
				bootstrap.Modal.getOrCreateInstance(newGroupModalEl).show();
			}
		}

		loadUnreadCounts();
		loadChatsList();
		pollUnreadTimer = setInterval(loadUnreadCounts, 3000);
		pollChatsTimer = setInterval(loadChatsList, 5000);
		pollMessagesTimer = setInterval(loadNewMessages, 3000);

		$(document).on('click', '.chat-conv-item[data-conv-id]', function (e) {
			e.preventDefault();
			var convId = Number($(this).data('conv-id') || 0);
			if (convId) {
				saveDraftNow();
				loadConversation(convId, true);
			}
		});

		$(document).on('click', '#chatBackBtn', function () {
			setMobilePane(false);
		});

		$('#userSearch').on('input', function () {
			clearTimeout(searchTimer);
			var q = $(this).val().trim();
			if (!q) {
				sessionStorage.removeItem(CHAT_SEARCH_STORAGE_KEY);
				$('#searchResults').addClass('d-none').html('');
				return;
			}
			sessionStorage.setItem(CHAT_SEARCH_STORAGE_KEY, q);
			searchTimer = setTimeout(function () {
				$.get('/api/chat.php', { action: 'search_users', query: q }, function (res) {
					if (!res.success || !res.users.length) {
						$('#searchResults').addClass('d-none').html('');
						return;
					}
					var html = '';
					$.each(res.users, function (_, u) {
						html += '<div class="chat-search-item d-flex align-items-center justify-content-between gap-2" data-username="' + esc(u.pk_username) + '">';
						html += '<div class="d-flex align-items-center gap-2 flex-grow-1" style="min-width:0;">';
						html += u.avatar_url ? '<img src="' + esc(u.avatar_url) + '" class="chat-msg-avatar" alt="avatar">' : '<i class="bi bi-person-circle"></i>';
						html += '<div style="min-width:0;"><div class="text-truncate">' + esc((u.firstName || '') + ' ' + (u.lastName || '')) + '</div><small class="text-muted">@' + esc(u.pk_username) + '</small></div></div>';
						html += '<div class="d-flex align-items-center gap-1 chat-search-action">';
						html += '<a href="/user/view_profile.php?user=' + encodeURIComponent(u.pk_username) + getChatBackQuery(chatConvId) + '" class="btn btn-sm btn-outline-secondary chat-view-profile-btn" title="' + esc(chatViewProfileTitle) + '"><i class="bi bi-person"></i></a>';
						html += '</div></div>';
					});
					$('#searchResults').removeClass('d-none').html(html);
				}, 'json').fail(function () {});
			}, 120);
		});

		if (savedChatQuery) {
			$('#userSearch').trigger('input');
		}

		$(document).on('click', '.chat-view-profile-btn', function (e) {
			e.stopPropagation();
			if ($('#groupInfoModal').hasClass('show')) {
				keepGroupInfoState = true;
			}
			if ($('#newGroupModal').hasClass('show')) {
				keepNewGroupState = true;
				saveNewGroupState();
			}
			saveGroupInfoState();
		});

		$(document).on('click', '.chat-search-item', function () {
			var otherUser = $(this).data('username');
			if (otherUser) {
				openPrivateChat(otherUser);
			}
		});

		$(document).on('change', '#chatFile', function () {
			var files = Array.from(this.files || []);
			if (files.length) {
				var formData = new FormData();
				formData.append('action', 'upload_draft_files');
				formData.append('conversation_id', chatConvId);
				files.forEach(function (f) {
					formData.append('files[]', f);
				});

				$.ajax({
					url: '/api/chat.php',
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					dataType: 'json',
					success: function (res) {
						if (res && res.success) {
							chatDraftFiles = Array.isArray(res.files) ? res.files : [];
							renderAttachmentPreview();
							if (res.errors && res.errors.length) {
								alert(res.errors.join('\n'));
							}
						} else if (res && res.message) {
							alert(res.message);
						}
					}
				});
			}
			$('#chatFile').val('');
		});

		$(document).on('input', '#chatMsg', function () {
			draftDirty = true;
			scheduleDraftSave();
		});

		$(document).on('submit', '#chatForm', function (e) {
			e.preventDefault();
			if (!chatConvId) return;
			var msg = $('#chatMsg').val().trim();
			if (!msg && !chatSelectedFiles.length && !chatDraftFiles.length) return;

			var formData = new FormData();
			formData.append('action', 'send');
			formData.append('conversation_id', chatConvId);
			formData.append('message', msg);
			chatSelectedFiles.forEach(function (f) {
				formData.append('files[]', f);
			});

			$.ajax({
				url: '/api/chat.php',
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function (res) {
					if (res && res.success) {
						$('#chatMsg').val('');
						chatSelectedFiles = [];
						chatDraftFiles = [];
						draftDirty = false;
						lastDraftValue = '';
						$('#chatFile').val('');
						renderAttachmentPreview();
						saveDraftNow();
						syncDraftFromServer(true);
						loadNewMessages();
						loadChatsList();
						loadUnreadCounts();
						if (res.errors && res.errors.length) {
							alert(res.errors.join('\n'));
						}
					} else if (res && res.message) {
						alert(res.message);
					}
				}
			});
		});

		$(document).on('click', '#groupHeaderInfoTrigger', function () {
			var convId = Number($(this).data('conv-id') || chatConvId || 0);
			if (convId) {
				loadGroupInfo(convId);
			}
		});

		$(document).on('click', '#chatLeaveGroupBtn', function () {
			if (!chatConvId) return;
			if (!confirm(chatConfirmLeaveGroupText)) return;

			$.post('/api/chat.php', { action: 'leave_group', chat_id: chatConvId }, function (res) {
				if (res && res.success) {
					$('#groupInfoModal').modal('hide');
					clearGroupInfoState();
					chatConvId = 0;
					chatLastMsgId = 0;
					setMobilePane(false);
					renderEmptyMain();
					window.history.pushState({}, '', '/user/chat.php');
					loadChatsList();
					loadUnreadCounts();
				} else {
					alert((res && res.message) ? res.message : chatErrorText);
				}
			}, 'json').fail(function () {
				alert(chatErrorText);
			});
		});

		$('#groupInfoMemberSearch').on('input', function () {
			var q = $(this).val().trim();
			saveGroupInfoState();
			clearTimeout(groupAddSearchTimer);
			groupAddSearchTimer = setTimeout(function () {
				searchGroupAddableFriends(q);
			}, 150);
		});

		$('#groupInfoAddBtn').on('click', function () {
			if (!chatConvId) return;
			var selected = [];
			Object.keys(selectedGroupAddMembers).forEach(function (uname) {
				if (selectedGroupAddMembers[uname]) selected.push(uname);
			});
			if (!selected.length) return;

			$.post('/api/chat.php', { action: 'add_group_members', chat_id: chatConvId, 'members[]': selected }, function (res) {
				if (res && res.success) {
					$('#groupInfoError').addClass('d-none');
					selectedGroupAddMembers = {};
					loadGroupInfo(chatConvId);
				} else {
					$('#groupInfoError').removeClass('d-none').text((res && res.message) ? res.message : chatErrorText);
				}
			}, 'json').fail(function () {
				$('#groupInfoError').removeClass('d-none').text(chatErrorText);
			});
		});

		$('#groupInfoNameInput, #groupInfoDescriptionInput').on('input', function () {
			saveGroupInfoState();
			scheduleGroupInfoSave();
		});

		$('#groupInfoAvatarInput').on('change', function () {
			if (!groupInfoIsOwner || !groupInfoLoadedConvId) {
				return;
			}

			var file = this.files && this.files[0] ? this.files[0] : null;
			if (!file) {
				return;
			}

			var formData = new FormData();
			formData.append('action', 'upload_group_avatar');
			formData.append('chat_id', String(groupInfoLoadedConvId));
			formData.append('avatar_file', file);

			$.ajax({
				url: '/api/chat.php',
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function (res) {
					if (res && res.success && res.data) {
						$('#groupInfoError').addClass('d-none');
						updateGroupAvatarPreview(res.data.avatar_url || '');
						loadChatsList();
						if ($('#groupHeaderInfoTrigger').length && chatConvId === groupInfoLoadedConvId) {
							loadConversation(chatConvId, false);
						}
					} else {
						$('#groupInfoError').removeClass('d-none').text((res && res.message) ? res.message : chatErrorText);
					}
				},
				error: function () {
					$('#groupInfoError').removeClass('d-none').text(chatErrorText);
				},
				complete: function () {
					$('#groupInfoAvatarInput').val('');
				}
			});
		});

		$('#groupInfoAvatarClearBtn').on('click', function () {
			if (!groupInfoIsOwner || !groupInfoLoadedConvId) {
				return;
			}

			$.post('/api/chat.php', { action: 'upload_group_avatar', chat_id: groupInfoLoadedConvId, clear_avatar: 1 }, function (res) {
				if (res && res.success) {
					$('#groupInfoError').addClass('d-none');
					updateGroupAvatarPreview('');
					loadChatsList();
					if ($('#groupHeaderInfoTrigger').length && chatConvId === groupInfoLoadedConvId) {
						loadConversation(chatConvId, false);
					}
				} else {
					$('#groupInfoError').removeClass('d-none').text((res && res.message) ? res.message : chatErrorText);
				}
			}, 'json').fail(function () {
				$('#groupInfoError').removeClass('d-none').text(chatErrorText);
			});
		});

		$(document).on('click', '.group-remove-member', function () {
			if (!chatConvId) return;
			var username = $(this).data('username');
			if (!username) return;
			if (!confirm(chatConfirmRemoveMemberText)) return;

			$.post('/api/chat.php', { action: 'remove_group_member', chat_id: chatConvId, member_username: username }, function (res) {
				if (res && res.success) {
					$('#groupInfoError').addClass('d-none');
					loadGroupInfo(chatConvId);
				} else {
					$('#groupInfoError').removeClass('d-none').text((res && res.message) ? res.message : chatErrorText);
				}
			}, 'json').fail(function () {
				$('#groupInfoError').removeClass('d-none').text(chatErrorText);
			});
		});

		$(document).on('change', '#newGroupMemberList .group-member-check', function () {
			var uname = String($(this).val() || '');
			if (!uname) return;
			selectedNewGroupMembers[uname] = $(this).is(':checked');
			saveNewGroupState();
		});

		$(document).on('change', '#groupInfoAddList .group-member-check', function () {
			var uname = String($(this).val() || '');
			if (!uname) return;
			selectedGroupAddMembers[uname] = $(this).is(':checked');
			saveGroupInfoState();
		});

		$('#newGroupMemberSearch').on('input', function () {
			var q = $(this).val().trim();
			saveNewGroupState();
			clearTimeout(newGroupSearchTimer);
			newGroupSearchTimer = setTimeout(function () {
				searchNewGroupFriends(q);
			}, 150);
		});

		$('#groupName, #groupDescription').on('input', function () {
			saveNewGroupState();
		});

		$('#newGroupAvatarInput').on('change', function () {
			var file = this.files && this.files[0] ? this.files[0] : null;
			newGroupAvatarFile = file;
			if (file) {
				updateNewGroupAvatarPreview(URL.createObjectURL(file));
			} else {
				updateNewGroupAvatarPreview('');
			}
			saveNewGroupState();
		});

		$('#newGroupAvatarClearBtn').on('click', function () {
			newGroupAvatarFile = null;
			$('#newGroupAvatarInput').val('');
			updateNewGroupAvatarPreview('');
			saveNewGroupState();
		});

		$('#createGroupBtn').on('click', function () {
			var name = $('#groupName').val().trim();
			var description = $('#groupDescription').val().trim();
			var members = [];
			Object.keys(selectedNewGroupMembers).forEach(function (uname) {
				if (selectedNewGroupMembers[uname]) members.push(uname);
			});

			if (!name) {
				$('#groupModalError').removeClass('d-none').text(chatGroupNameRequiredText);
				return;
			}

			$('#groupModalError').addClass('d-none');
			var fd = new FormData();
			fd.append('action', 'create_group_chat');
			fd.append('group_name', name);
			fd.append('group_description', description);
			members.forEach(function (m) {
				fd.append('members[]', m);
			});
			if (newGroupAvatarFile) {
				fd.append('avatar_file', newGroupAvatarFile);
			}

			$.ajax({
				url: '/api/chat.php',
				type: 'POST',
				data: fd,
				processData: false,
				contentType: false,
				dataType: 'json',
				success: function (res) {
					if (res && res.success) {
						loadConversation(Number(res.conversation_id), true);
						$('#newGroupModal').modal('hide');
						clearNewGroupState();
						loadChatsList();
					} else {
						$('#groupModalError').removeClass('d-none').text((res && res.message) ? res.message : chatErrorText);
					}
				},
				error: function () {
					$('#groupModalError').removeClass('d-none').text(chatErrorText);
				}
			});
		});

		$('#newGroupModal').on('hidden.bs.modal', function () {
			if (!keepNewGroupState) {
				$('#groupName').val('');
				$('#groupDescription').val('');
				selectedNewGroupMembers = {};
				newGroupAvatarFile = null;
				$('#newGroupAvatarInput').val('');
				updateNewGroupAvatarPreview('');
				$('#newGroupMemberSearch').val('');
				$('#newGroupMemberList').html('<div class="text-muted small px-3 py-1">' + esc(chatNoFriendsToAddText) + '</div>');
				$('#groupModalError').addClass('d-none');
				clearNewGroupState();
			}
			keepNewGroupState = false;
		});

		$('#newGroupModal').on('shown.bs.modal', function () {
			restoreNewGroupFromState();
			var q = $('#newGroupMemberSearch').val().trim();
			if (q) {
				searchNewGroupFriends(q);
			}
		});

		$('#groupInfoModal').on('hidden.bs.modal', function () {
			clearTimeout(groupInfoSaveTimer);
			groupInfoLoadedConvId = 0;
			groupInfoIsOwner = false;
			if (!keepGroupInfoState) {
				clearGroupInfoState();
			}
			keepGroupInfoState = false;
		});

		window.addEventListener('popstate', function () {
			var params = new URLSearchParams(window.location.search);
			var conv = Number(params.get('conv') || 0);
			if (conv > 0) {
				loadConversation(conv, false);
			} else {
				chatConvId = 0;
				chatLastMsgId = 0;
				setMobilePane(false);
				renderEmptyMain();
				loadChatsList();
				loadUnreadCounts();
			}
		});

		if (chatConvId) {
			$.get('/api/chat.php', { action: 'get_draft', conversation_id: chatConvId }, function (res) {
				if (res && res.success && $('#chatMsg').length) {
					$('#chatMsg').val(res.draft || '');
					chatDraftFiles = Array.isArray(res.files) ? res.files : [];
					lastDraftValue = String(res.draft || '');
					draftDirty = false;
					renderAttachmentPreview();
				}
			}, 'json');

			restoreGroupInfoFromStateIfNeeded();
		}

		window.addEventListener('beforeunload', function () {
			saveDraftNow();
		});
	});
})();
