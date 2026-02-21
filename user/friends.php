<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../services/friends.php';

requireLogin();

$pageTitle = 'Friends';
require_once '../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <h2>Friends Management</h2>
        
        <div id="alert-container"></div>
        
        <!-- Add Friend -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New Friend</h5>
            </div>
            <div class="card-body">
                <form id="addFriendForm" class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Friend's Username</label>
                        <input type="text" class="form-control" id="friend_username" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">Add Friend</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Pending Requests -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Friend Requests</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table" id="requests-table">
                        <tbody id="requests-container">
                            <tr><td colspan="2" class="text-center">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Friends List -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">My Friends</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Friends Since</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="friends-container">
                            <tr><td colspan="5" class="text-center">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    loadRequests();
    loadFriends();
    
    $('#addFriendForm').submit(function(e) {
        e.preventDefault();
        
        $.post('/api/friends.php', {
            action: 'send_request',
            friend_username: $('#friend_username').val()
        }, function(response) {
            var parts = response.split('|');
            if (parts[0] === 'OK') {
                showAlert('success', parts[1]);
                $('#friend_username').val('');
                loadRequests();
                loadFriends();
            } else {
                showAlert('danger', parts[1]);
            }
        });
    });
});

function loadRequests() {
    $.get('/api/friends.php?action=get_requests', function(data) {
        $('#requests-container').html(data);
    });
}

function loadFriends() {
    $.get('/api/friends.php?action=get_friends', function(data) {
        $('#friends-container').html(data);
    });
}

function acceptRequest(requestId) {
    $.post('/api/friends.php', {
        action: 'accept_request',
        request_id: requestId
    }, function(response) {
        var parts = response.split('|');
        if (parts[0] === 'OK') {
            showAlert('success', parts[1]);
            loadRequests();
            loadFriends();
        } else {
            showAlert('danger', parts[1]);
        }
    });
}

function rejectRequest(requestId) {
    $.post('/api/friends.php', {
        action: 'reject_request',
        request_id: requestId
    }, function(response) {
        var parts = response.split('|');
        if (parts[0] === 'OK') {
            showAlert('success', parts[1]);
            loadRequests();
        } else {
            showAlert('danger', parts[1]);
        }
    });
}

function removeFriend(username) {
    if (!confirm('This will also unshare all collections between you. Continue?')) {
        return;
    }
    
    $.post('/api/friends.php', {
        action: 'remove_friend',
        friend_username: username
    }, function(response) {
        var parts = response.split('|');
        if (parts[0] === 'OK') {
            showAlert('success', parts[1]);
            loadFriends();
        } else {
            showAlert('danger', parts[1]);
        }
    });
}

function showAlert(type, message) {
    var alertHtml = '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                    escapeHtml(message) +
                    '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' +
                    '</div>';
    $('#alert-container').html(alertHtml);
    
    setTimeout(function() {
        $('.alert').fadeOut();
    }, 5000);
}

function escapeHtml(text) {
    return $('<div>').text(text).html();
}
</script>

<?php require_once '../includes/footer.php'; ?>