/* Area Riservata Admin JavaScript */

jQuery(document).ready(function($) {
    'use strict';

    // Configuration
    const config = {
        ajaxUrl: areaRiservataAdmin.ajax_url,
        nonce: areaRiservataAdmin.nonce,
        confirmDelete: areaRiservataAdmin.confirm_delete || 'Sei sicuro di voler eliminare questo elemento?',
        confirmApprove: areaRiservataAdmin.confirm_approve || 'Confermi l\'approvazione di questo utente?'
    };

    // Tab Navigation
    const tabHandler = {
        init: function() {
            $('.nav-tab').on('click', this.switchTab.bind(this));
        },

        switchTab: function(e) {
            e.preventDefault();
            
            const $tab = $(e.target);
            const targetTab = $tab.attr('href').substring(1);
            
            // Update nav tabs
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Update content
            $('.tab-content').removeClass('active');
            $('#' + targetTab).addClass('active');
        }
    };

    // Users Management
    const usersHandler = {
        init: function() {
            $('#add-user-btn, #quick-add-user').on('click', this.showAddUserModal.bind(this));
            $('#add-user-form').on('submit', this.addUser.bind(this));
            
            $('.approve-user').on('click', this.approveUser.bind(this));
            $('.reject-user').on('click', this.rejectUser.bind(this));
            $('.toggle-status').on('click', this.toggleStatus.bind(this));
            $('.delete-user').on('click', this.deleteUser.bind(this));
            $('.view-user').on('click', this.viewUser.bind(this));
            
            // Filters
            $('#filter-status, #filter-type').on('change', this.filterUsers.bind(this));
            
            // Password strength
            $('#add-user-form input[name="password"]').on('input', this.checkPasswordStrength.bind(this));
        },

        showAddUserModal: function() {
            $('#add-user-modal').fadeIn();
            $('#add-user-form')[0].reset();
            $('.password-strength').text('').removeClass('weak medium strong');
        },

        addUser: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            // Add action and nonce
            formData.append('action', 'ar_add_manual_user');
            formData.append('nonce', config.nonce);
            
            const $submitBtn = $form.find('button[type="submit"]');
            this.showSpinner($submitBtn);
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('#add-user-modal').fadeOut();
                        location.reload(); // Refresh to show new user
                    } else {
                        alert(response.data.message || 'Errore durante l\'aggiunta dell\'utente');
                    }
                },
                error: function() {
                    alert('Errore di connessione');
                },
                complete: function() {
                    usersHandler.hideSpinner($submitBtn);
                }
            });
        },

        approveUser: function(e) {
            const userId = $(e.target).data('user-id');
            
            if (confirm(config.confirmApprove)) {
                this.changeUserStatus(userId, 'approve');
            }
        },

        rejectUser: function(e) {
            const userId = $(e.target).data('user-id');
            
            if (confirm('Confermi il rifiuto di questo utente?')) {
                this.changeUserStatus(userId, 'reject');
            }
        },

        changeUserStatus: function(userId, actionType) {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ar_approve_user',
                    nonce: config.nonce,
                    user_id: userId,
                    action_type: actionType
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Errore durante l\'operazione');
                    }
                },
                error: function() {
                    alert('Errore di connessione');
                }
            });
        },

        toggleStatus: function(e) {
            const $btn = $(e.target);
            const userId = $btn.data('user-id');
            const currentStatus = $btn.data('current-status');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ar_toggle_user_status',
                    nonce: config.nonce,
                    user_id: userId,
                    current_status: currentStatus
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || 'Errore durante l\'operazione');
                    }
                },
                error: function() {
                    alert('Errore di connessione');
                }
            });
        },

        deleteUser: function(e) {
            const userId = $(e.target).data('user-id');
            
            if (confirm(config.confirmDelete)) {
                $.ajax({
                    url: config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ar_delete_user',
                        nonce: config.nonce,
                        user_id: userId
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`tr[data-user-id="${userId}"]`).fadeOut(() => {
                                $(`tr[data-user-id="${userId}"]`).remove();
                            });
                        } else {
                            alert(response.data.message || 'Errore durante l\'eliminazione');
                        }
                    },
                    error: function() {
                        alert('Errore di connessione');
                    }
                });
            }
        },

        viewUser: function(e) {
            const userId = $(e.target).data('user-id');
            
            $('#view-user-modal').fadeIn();
            $('#user-details-content').html('<div class="loading">Caricamento...</div>');
            
            // Load user details (you can implement this endpoint)
            const $row = $(`tr[data-user-id="${userId}"]`);
            const userData = {
                name: $row.find('td:first').text(),
                email: $row.find('td:nth-child(2)').text(),
                type: $row.find('td:nth-child(3)').text(),
                status: $row.find('td:nth-child(4)').text(),
                created: $row.find('td:nth-child(5)').text()
            };
            
            let html = '<div class="user-details">';
            html += '<h4>Dettagli Utente</h4>';
            html += '<p><strong>Nome:</strong> ' + userData.name + '</p>';
            html += '<p><strong>Email:</strong> ' + userData.email + '</p>';
            html += '<p><strong>Tipo:</strong> ' + userData.type + '</p>';
            html += '<p><strong>Status:</strong> ' + userData.status + '</p>';
            html += '<p><strong>Registrato il:</strong> ' + userData.created + '</p>';
            html += '</div>';
            
            $('#user-details-content').html(html);
        },

        filterUsers: function() {
            const statusFilter = $('#filter-status').val();
            const typeFilter = $('#filter-type').val();
            
            $('#users-table-body tr').each(function() {
                const $row = $(this);
                const userStatus = $row.data('status');
                const userType = $row.data('type');
                
                let show = true;
                
                if (statusFilter && userStatus !== statusFilter) {
                    show = false;
                }
                
                if (typeFilter && userType !== typeFilter) {
                    show = false;
                }
                
                if (show) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },

        checkPasswordStrength: function(e) {
            const password = $(e.target).val();
            const $strength = $('.password-strength');
            
            if (password.length === 0) {
                $strength.text('').removeClass('weak medium strong');
                return;
            }
            
            let strength = 0;
            let feedback = '';

            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;

            switch (strength) {
                case 0:
                case 1:
                    feedback = 'Troppo debole';
                    break;
                case 2:
                    feedback = 'Debole';
                    break;
                case 3:
                    feedback = 'Media';
                    break;
                case 4:
                case 5:
                    feedback = 'Forte';
                    break;
            }
            
            $strength.text(feedback)
                    .removeClass('weak medium strong')
                    .addClass(strength <= 2 ? 'weak' : strength === 3 ? 'medium' : 'strong');
        },

        showSpinner: function($btn) {
            $btn.prop('disabled', true).prepend('<span class="spinner"></span>');
        },

        hideSpinner: function($btn) {
            $btn.prop('disabled', false).find('.spinner').remove();
        }
    };

    // Documents Management
    const documentsHandler = {
        init: function() {
            $('#upload-doc-btn, #quick-upload-doc').on('click', this.showUploadModal.bind(this));
            $('#upload-doc-form').on('submit', this.uploadDocument.bind(this));
            $('.delete-doc').on('click', this.deleteDocument.bind(this));
            
            // Filters
            $('#filter-category, #filter-destination').on('change', this.filterDocuments.bind(this));
        },

        showUploadModal: function() {
            $('#upload-doc-modal').fadeIn();
            $('#upload-doc-form')[0].reset();
            $('.upload-progress').hide();
        },

        uploadDocument: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            // Add action and nonce
            formData.append('action', 'ar_upload_document');
            formData.append('nonce', config.nonce);
            
            const $submitBtn = $form.find('button[type="submit"]');
            const $progress = $('.upload-progress');
            
            $submitBtn.prop('disabled', true);
            $progress.show();
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percentComplete = (e.loaded / e.total) * 100;
                            $('.progress-fill').css('width', percentComplete + '%');
                            $('.progress-text').text(Math.round(percentComplete) + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    if (response.success) {
                        $('#upload-doc-modal').fadeOut();
                        location.reload();
                    } else {
                        alert(response.data.message || 'Errore durante il caricamento');
                    }
                },
                error: function() {
                    alert('Errore di connessione');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                    $progress.hide();
                    $('.progress-fill').css('width', '0%');
                    $('.progress-text').text('0%');
                }
            });
        },

        deleteDocument: function(e) {
            const docId = $(e.target).data('doc-id');
            
            if (confirm(config.confirmDelete)) {
                $.ajax({
                    url: config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ar_delete_document',
                        nonce: config.nonce,
                        doc_id: docId
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`.document-card`).has(`[data-doc-id="${docId}"]`).fadeOut(() => {
                                $(`.document-card`).has(`[data-doc-id="${docId}"]`).remove();
                            });
                        } else {
                            alert(response.data.message || 'Errore durante l\'eliminazione');
                        }
                    },
                    error: function() {
                        alert('Errore di connessione');
                    }
                });
            }
        },

        filterDocuments: function() {
            const categoryFilter = $('#filter-category').val();
            const destinationFilter = $('#filter-destination').val();
            
            $('.document-card').each(function() {
                const $card = $(this);
                const docCategory = $card.data('category');
                const docDestination = $card.data('destination');
                
                let show = true;
                
                if (categoryFilter && docCategory !== categoryFilter) {
                    show = false;
                }
                
                if (destinationFilter && docDestination !== destinationFilter) {
                    show = false;
                }
                
                if (show) {
                    $card.show();
                } else {
                    $card.hide();
                }
            });
        }
    };

    // Logs Management
    const logsHandler = {
        init: function() {
            $('#log-date-from, #log-date-to, #log-action-filter').on('change', this.filterLogs.bind(this));
            $('#clear-logs').on('click', this.clearOldLogs.bind(this));
        },

        filterLogs: function() {
            const dateFrom = $('#log-date-from').val();
            const dateTo = $('#log-date-to').val();
            const actionFilter = $('#log-action-filter').val();
            
            $('#logs-table-body tr').each(function() {
                const $row = $(this);
                const logDate = $row.data('date');
                const logAction = $row.data('action');
                
                let show = true;
                
                if (dateFrom && logDate < dateFrom) {
                    show = false;
                }
                
                if (dateTo && logDate > dateTo) {
                    show = false;
                }
                
                if (actionFilter && logAction !== actionFilter) {
                    show = false;
                }
                
                if (show) {
                    $row.show();
                } else {
                    $row.hide();
                }
            });
        },

        clearOldLogs: function() {
            if (confirm('Eliminare i log più vecchi di 90 giorni?')) {
                $.ajax({
                    url: config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ar_clear_old_logs',
                        nonce: config.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Errore durante la pulizia dei log');
                        }
                    },
                    error: function() {
                        alert('Errore di connessione');
                    }
                });
            }
        }
    };

    // Stats Handler
    const statsHandler = {
        init: function() {
            this.loadStats();
            setInterval(this.loadStats.bind(this), 300000); // Refresh every 5 minutes
        },

        loadStats: function() {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ar_get_stats',
                    nonce: config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        const stats = response.data;
                        // Update stat boxes if they exist
                        // This is a placeholder for stat updating logic
                    }
                }
            });
        }
    };

    // Modal Handler
    const modalHandler = {
        init: function() {
            $('.ar-modal-close').on('click', this.closeModal);
            $(window).on('click', this.handleBackdropClick);
            $(document).on('keydown', this.handleEscKey);
        },

        closeModal: function() {
            $('.ar-modal').fadeOut();
        },

        handleBackdropClick: function(e) {
            if ($(e.target).hasClass('ar-modal')) {
                $('.ar-modal').fadeOut();
            }
        },

        handleEscKey: function(e) {
            if (e.key === 'Escape') {
                $('.ar-modal').fadeOut();
            }
        }
    };

    // Export Handler
    const exportHandler = {
        init: function() {
            $('#export-users').on('click', this.exportUsers.bind(this));
        },

        exportUsers: function() {
            window.location.href = config.ajaxUrl + '?action=ar_export_users&nonce=' + config.nonce;
        }
    };

    // Initialize all handlers
    tabHandler.init();
    usersHandler.init();
    documentsHandler.init();
    logsHandler.init();
    statsHandler.init();
    modalHandler.init();
    exportHandler.init();

    // Global close modal function
    window.closeModal = function() {
        $('.ar-modal').fadeOut();
    };
});