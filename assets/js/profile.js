(function () {
    function escHtml(s) {
        return String(s || '').replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
        });
    }

    function showProfileFlash(type, message) {
        if (type !== 'error') return;
        var box = document.getElementById('profileFlashContainer');
        if (!box || !message) return;
        var html = '<div class="alert alert-danger alert-dismissible fade show auto-dismiss" role="alert">'
            + escHtml(message)
            + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        box.innerHTML = html;
        setTimeout(function () {
            var alert = box.querySelector('.auto-dismiss');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(function () {
                    if (alert.parentNode) alert.parentNode.removeChild(alert);
                }, 200);
            }
        }, 5000);
    }

    function updateHeaderAvatar(avatarUrl) {
        var brand = document.querySelector('.navbar-brand[href="/user/profile.php"]');
        if (!brand) return;

        var existingImg = brand.querySelector('img.rounded-circle');
        var existingIcon = brand.querySelector('i.bi-person-circle');

        if (avatarUrl) {
            if (existingImg) {
                existingImg.src = avatarUrl;
            } else {
                var img = document.createElement('img');
                img.src = avatarUrl;
                img.className = 'rounded-circle me-1';
                img.width = 32;
                img.height = 32;
                img.alt = 'avatar';
                if (existingIcon) {
                    existingIcon.replaceWith(img);
                } else {
                    brand.insertBefore(img, brand.firstChild);
                }
            }
        } else if (existingImg) {
            var icon = document.createElement('i');
            icon.className = 'bi bi-person-circle fs-4 me-1';
            existingImg.replaceWith(icon);
        }
    }

    function submitProfileForm(formEl, onSuccess) {
        var formData = new FormData(formEl);
        return fetch('/user/profile.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    showProfileFlash('error', (data && data.message) ? data.message : 'Error');
                    return;
                }
                if (typeof onSuccess === 'function') onSuccess(data);
            })
            .catch(function () {
                showProfileFlash('error', 'Error');
            });
    }

    function selectAvatar(el) {
        document.querySelectorAll('.avatar-option').forEach(function (e) {
            e.classList.remove('selected');
        });
        el.classList.add('selected');

        var selectedAvatar = document.getElementById('selectedAvatar');
        if (selectedAvatar) selectedAvatar.value = el.dataset.avatar || '';

        var uploadInput = document.getElementById('avatarUploadInput');
        if (uploadInput) uploadInput.value = '';

        var uploadName = document.getElementById('avatarUploadFileName');
        if (uploadName) uploadName.textContent = '';

        var avatarUrl = el.dataset.avatarUrl || '';
        var previewImage = document.getElementById('avatarPreviewImage');
        var previewIcon = document.getElementById('avatarPreviewIcon');
        var clearBtn = document.getElementById('avatarClearBtn');

        if (previewImage && previewIcon && avatarUrl) {
            previewImage.src = avatarUrl;
            previewImage.classList.remove('d-none');
            previewIcon.classList.add('d-none');
        }
        if (clearBtn) clearBtn.classList.remove('d-none');
    }

    document.addEventListener('DOMContentLoaded', function () {
        var avatarForm = document.getElementById('avatarForm');
        if (!avatarForm) return;

        var themeLightLabel = avatarForm.dataset.themeLight || 'Light';
        var themeDarkLabel = avatarForm.dataset.themeDark || 'Dark';
        var genericError = avatarForm.dataset.errorGeneric || 'Error';

        document.querySelectorAll('.avatar-option').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectAvatar(btn);
            });
        });

        var clearBtn = document.getElementById('avatarClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                document.querySelectorAll('.avatar-option').forEach(function (e) {
                    e.classList.remove('selected');
                });

                var uploadInput = document.getElementById('avatarUploadInput');
                if (uploadInput) uploadInput.value = '';

                var uploadName = document.getElementById('avatarUploadFileName');
                if (uploadName) uploadName.textContent = '';

                var selectedAvatar = document.getElementById('selectedAvatar');
                if (selectedAvatar) selectedAvatar.value = '__none__';

                var previewImage = document.getElementById('avatarPreviewImage');
                var previewIcon = document.getElementById('avatarPreviewIcon');
                if (previewImage && previewIcon) {
                    previewImage.src = '';
                    previewImage.classList.add('d-none');
                    previewIcon.classList.remove('d-none');
                }
                clearBtn.classList.add('d-none');
            });
        }

        var uploadInput = document.getElementById('avatarUploadInput');
        if (uploadInput) {
            uploadInput.addEventListener('change', function () {
                var file = uploadInput.files && uploadInput.files[0] ? uploadInput.files[0] : null;
                var uploadName = document.getElementById('avatarUploadFileName');
                if (uploadName) uploadName.textContent = file ? file.name : '';
                if (!file) return;

                document.querySelectorAll('.avatar-option').forEach(function (e) {
                    e.classList.remove('selected');
                });
                var selectedAvatar = document.getElementById('selectedAvatar');
                if (selectedAvatar) selectedAvatar.value = '';

                var reader = new FileReader();
                reader.onload = function (e) {
                    var previewImage = document.getElementById('avatarPreviewImage');
                    var previewIcon = document.getElementById('avatarPreviewIcon');
                    if (previewImage && previewIcon) {
                        previewImage.src = e.target && e.target.result ? e.target.result : '';
                        previewImage.classList.remove('d-none');
                        previewIcon.classList.add('d-none');
                    }
                    if (clearBtn) clearBtn.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            });
        }

        avatarForm.addEventListener('submit', function (e) {
            e.preventDefault();
            submitProfileForm(avatarForm, function (data) {
                var avatarUrl = data.avatar_url || '';
                var previewImage = document.getElementById('avatarPreviewImage');
                var previewIcon = document.getElementById('avatarPreviewIcon');
                if (previewImage && previewIcon) {
                    if (avatarUrl) {
                        previewImage.src = avatarUrl;
                        previewImage.classList.remove('d-none');
                        previewIcon.classList.add('d-none');
                        if (clearBtn) clearBtn.classList.remove('d-none');
                    } else {
                        previewImage.src = '';
                        previewImage.classList.add('d-none');
                        previewIcon.classList.remove('d-none');
                        if (clearBtn) clearBtn.classList.add('d-none');
                    }
                }
                updateHeaderAvatar(avatarUrl);
            });
        });

        var profileUpdateForm = document.getElementById('profileUpdateForm');
        if (profileUpdateForm) {
            profileUpdateForm.addEventListener('submit', function (e) {
                e.preventDefault();
                submitProfileForm(profileUpdateForm, function (data) {
                    var navName = document.querySelector('.navbar-brand span');
                    if (navName && data.full_name) navName.textContent = data.full_name;
                });
            });
        }

        var startEmailChangeForm = document.getElementById('startEmailChangeForm');
        if (startEmailChangeForm) {
            startEmailChangeForm.addEventListener('submit', function (e) {
                e.preventDefault();
                submitProfileForm(startEmailChangeForm, function (data) {
                    var pending = document.getElementById('emailChangePendingBox');
                    if (pending && data.pending_email_change) {
                        pending.classList.remove('d-none');
                        if (data.pending_email_expires_at) {
                            pending.dataset.expiresAt = String(data.pending_email_expires_at);
                        }
                    }
                });
            });
        }

        var emailChangePendingBox = document.getElementById('emailChangePendingBox');
        if (emailChangePendingBox) {
            emailChangePendingBox.addEventListener('submit', function (e) {
                e.preventDefault();
                submitProfileForm(emailChangePendingBox, function (data) {
                    if (!data.pending_email_change) {
                        emailChangePendingBox.classList.add('d-none');
                    }
                });
            });
        }

        var changePasswordForm = document.getElementById('changePasswordForm');
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', function (e) {
                e.preventDefault();
                submitProfileForm(changePasswordForm, function () {
                    changePasswordForm.reset();
                });
            });
        }

        var profileLocaleSelect = document.getElementById('profileLocaleSelect');
        if (profileLocaleSelect) {
            profileLocaleSelect.addEventListener('change', function () {
                fetch('/api/profile.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams({ action: 'set_locale', locale: profileLocaleSelect.value })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.success) {
                            showProfileFlash('error', (data && data.message) ? data.message : genericError);
                            return;
                        }
                        window.location.reload();
                    })
                    .catch(function () {
                        showProfileFlash('error', genericError);
                    });
            });
        }

        var profileThemeToggleBtn = document.getElementById('profileThemeToggleBtn');
        if (profileThemeToggleBtn) {
            profileThemeToggleBtn.addEventListener('click', function () {
                var htmlEl = document.documentElement;
                var current = htmlEl.getAttribute('data-bs-theme') || 'light';
                var next = current === 'dark' ? 'light' : 'dark';
                htmlEl.setAttribute('data-bs-theme', next);

                var profileThemeIcon = document.getElementById('profileThemeIcon');
                if (profileThemeIcon) {
                    profileThemeIcon.classList.remove('bi-sun-fill', 'bi-moon-fill');
                    profileThemeIcon.classList.add(next === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill');
                }

                var profileThemeLabel = document.getElementById('profileThemeLabel');
                if (profileThemeLabel) {
                    profileThemeLabel.textContent = next === 'dark' ? themeDarkLabel : themeLightLabel;
                }

                fetch('/api/profile.php', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new URLSearchParams({ action: 'set_theme', theme: next })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data || !data.success) {
                            htmlEl.setAttribute('data-bs-theme', current);
                            if (profileThemeIcon) {
                                profileThemeIcon.classList.remove('bi-sun-fill', 'bi-moon-fill');
                                profileThemeIcon.classList.add(current === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill');
                            }
                            if (profileThemeLabel) {
                                profileThemeLabel.textContent = current === 'dark' ? themeDarkLabel : themeLightLabel;
                            }
                            showProfileFlash('error', (data && data.message) ? data.message : genericError);
                            return;
                        }

                        var navThemeIcon = document.getElementById('themeIcon');
                        if (navThemeIcon) {
                            navThemeIcon.classList.remove('bi-sun-fill', 'bi-moon-fill');
                            navThemeIcon.classList.add(next === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill');
                        }
                    })
                    .catch(function () {
                        htmlEl.setAttribute('data-bs-theme', current);
                        if (profileThemeIcon) {
                            profileThemeIcon.classList.remove('bi-sun-fill', 'bi-moon-fill');
                            profileThemeIcon.classList.add(current === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill');
                        }
                        if (profileThemeLabel) {
                            profileThemeLabel.textContent = current === 'dark' ? themeDarkLabel : themeLightLabel;
                        }
                        showProfileFlash('error', genericError);
                    });
            });
        }

        var pendingBox = document.getElementById('emailChangePendingBox');
        if (!pendingBox || pendingBox.classList.contains('d-none')) return;

        var expiresAt = parseInt(pendingBox.dataset.expiresAt || '0', 10);
        if (!expiresAt) return;

        var now = Math.floor(Date.now() / 1000);
        var msLeft = (expiresAt - now) * 1000;
        if (msLeft <= 0) {
            pendingBox.classList.add('d-none');
            return;
        }

        window.setTimeout(function () {
            pendingBox.classList.add('d-none');
        }, msLeft);
    });
})();
