/* Area Riservata Frontend JavaScript */

jQuery(document).ready(function($) {
    'use strict';

    // Configurazione globale
    const config = {
        ajaxUrl: areaRiservataFrontend.ajax_url,
        nonce: areaRiservataFrontend.nonce,
        messages: areaRiservataFrontend.messages || {}
    };

    // Utility functions
    const utils = {
        showMessage: function(container, message, type = 'success') {
            const $container = $(container);
            $container.removeClass('success error info')
                     .addClass(type)
                     .html(message)
                     .fadeIn();
        },

        hideMessage: function(container) {
            $(container).fadeOut();
        },

        showFieldError: function(fieldName, message) {
            const $field = $(`input[name="${fieldName}"], select[name="${fieldName}"]`);
            const $error = $(`#${fieldName.replace('_', '-')}-error`);
            
            $field.addClass('error');
            if ($error.length) {
                $error.text(message).show();
            }
        },

        clearFieldErrors: function() {
            $('.field-error').hide();
            $('input, select').removeClass('error');
        },

        showSpinner: function(button) {
            const $btn = $(button);
            $btn.prop('disabled', true);
            $btn.find('.btn-text').hide();
            $btn.find('.btn-spinner').show();
        },

        hideSpinner: function(button) {
            const $btn = $(button);
            $btn.prop('disabled', false);
            $btn.find('.btn-text').show();
            $btn.find('.btn-spinner').hide();
        },

        validateEmail: function(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        },

        validatePartitaIva: function(piva) {
            return /^[0-9]{11}$/.test(piva);
        },

        checkPasswordStrength: function(password) {
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

            return { strength, feedback };
        }
    };

    // LOGIN FUNCTIONALITY
    const loginHandler = {
        init: function() {
            $('#area-riservata-login-form').on('submit', this.handleSubmit.bind(this));
            $('.toggle-password').on('click', this.togglePassword);
        },

        handleSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            // Validation
            const username = formData.get('username');
            const password = formData.get('password');
            
            utils.clearFieldErrors();
            
            if (!username) {
                utils.showFieldError('username', 'Username obbligatorio');
                return;
            }
            
            if (!password) {
                utils.showFieldError('password', 'Password obbligatoria');
                return;
            }

            // AJAX request
            utils.showSpinner('#login-submit');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ar_login_user',
                    nonce: config.nonce,
                    username: username,
                    password: password,
                    redirect_to: formData.get('redirect_to') || ''
                },
                success: function(response) {
                    if (response.success) {
                        utils.showMessage('#login-message', response.data.message, 'success');
                        setTimeout(() => {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    } else {
                        utils.showMessage('#login-message', response.data.message, 'error');
                        if (response.data.field_errors) {
                            Object.keys(response.data.field_errors).forEach(field => {
                                if (response.data.field_errors[field]) {
                                    utils.showFieldError(field, response.data.field_errors[field]);
                                }
                            });
                        }
                    }
                },
                error: function() {
                    utils.showMessage('#login-message', 'Errore di connessione. Riprova.', 'error');
                },
                complete: function() {
                    utils.hideSpinner('#login-submit');
                }
            });
        },

        togglePassword: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const $input = $btn.siblings('input');
            const $icon = $btn.find('.dashicons');
            
            if ($input.attr('type') === 'password') {
                $input.attr('type', 'text');
                $icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
            }
        }
    };

    // REGISTRATION FUNCTIONALITY
    const registerHandler = {
        init: function() {
            $('#area-riservata-register-form').on('submit', this.handleSubmit.bind(this));
            $('.toggle-password').on('click', loginHandler.togglePassword);
            $('#reg_password').on('input', this.checkPasswordStrength);
            $('#reg_partita_iva').on('input', this.validatePartitaIva);
            $('#reg_email').on('blur', this.validateEmail);
        },

        handleSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            utils.clearFieldErrors();
            
            // Client-side validation
            if (!this.validateForm(formData)) {
                return;
            }

            // Add action and nonce to formData - IMPORTANTE: questo deve essere fatto!
            formData.append('action', 'ar_register_user');
            formData.append('nonce', config.nonce);

            // Debug: stampiamo in console cosa stiamo inviando
            console.log('Invio registrazione con nonce:', config.nonce);
            console.log('URL AJAX:', config.ajaxUrl);

            utils.showSpinner('#register-submit');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 30000, // 30 secondi di timeout
                success: function(response) {
                    console.log('Risposta server:', response);
                    if (response.success) {
                        utils.showMessage('#register-message', response.data.message, 'success');
                        $form[0].reset();
                        // Scroll to top
                        $('html, body').animate({ scrollTop: 0 }, 500);
                    } else {
                        utils.showMessage('#register-message', response.data.message || 'Errore nella registrazione', 'error');
                        if (response.data.field_errors) {
                            Object.keys(response.data.field_errors).forEach(field => {
                                if (response.data.field_errors[field]) {
                                    utils.showFieldError(field, response.data.field_errors[field]);
                                }
                            });
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Errore AJAX:', status, error);
                    console.error('Risposta completa:', xhr);
                    
                    let errorMessage = 'Errore di connessione. ';
                    if (status === 'timeout') {
                        errorMessage += 'Timeout della richiesta.';
                    } else if (xhr.status === 403) {
                        errorMessage += 'Accesso negato. Ricarica la pagina.';
                    } else if (xhr.status === 404) {
                        errorMessage += 'Endpoint non trovato.';
                    } else {
                        errorMessage += 'Codice errore: ' + xhr.status;
                    }
                    
                    utils.showMessage('#register-message', errorMessage, 'error');
                },
                complete: function() {
                    utils.hideSpinner('#register-submit');
                }
            });
        },

        validateForm: function(formData) {
            let isValid = true;
            
            // Required fields
            const requiredFields = ['nome', 'cognome', 'email', 'telefono', 'ragione_sociale', 'partita_iva', 'username', 'password', 'tipo_utente'];
            
            requiredFields.forEach(field => {
                if (!formData.get(field)) {
                    utils.showFieldError(field, 'Campo obbligatorio');
                    isValid = false;
                }
            });

            // Email validation
            const email = formData.get('email');
            if (email && !utils.validateEmail(email)) {
                utils.showFieldError('email', 'Formato email non valido');
                isValid = false;
            }

            // Partita IVA validation
            const piva = formData.get('partita_iva');
            if (piva && !utils.validatePartitaIva(piva)) {
                utils.showFieldError('partita_iva', 'Partita IVA deve contenere 11 cifre');
                isValid = false;
            }

            // Password validation
            const password = formData.get('password');
            if (password && password.length < 8) {
                utils.showFieldError('password', 'Password deve essere di almeno 8 caratteri');
                isValid = false;
            }

            // Username validation
            const username = formData.get('username');
            if (username && (username.length < 3 || !/^[a-zA-Z0-9_]+$/.test(username))) {
                utils.showFieldError('username', 'Username deve essere di almeno 3 caratteri e contenere solo lettere, numeri e underscore');
                isValid = false;
            }

            // Privacy acceptance
            if (!formData.get('privacy_accepted')) {
                utils.showFieldError('privacy_accepted', 'Devi accettare l\'informativa sulla privacy');
                isValid = false;
            }

            return isValid;
        },

        checkPasswordStrength: function() {
            const password = $(this).val();
            const $strength = $('.password-strength');
            
            if (password.length === 0) {
                $strength.text('').removeClass('weak medium strong');
                return;
            }
            
            const result = utils.checkPasswordStrength(password);
            $strength.text(result.feedback)
                    .removeClass('weak medium strong')
                    .addClass(result.strength <= 2 ? 'weak' : result.strength === 3 ? 'medium' : 'strong');
        },

        validatePartitaIva: function() {
            const piva = $(this).val();
            const $error = $('#partita-iva-error');
            
            if (piva && !utils.validatePartitaIva(piva)) {
                $error.text('Partita IVA deve contenere 11 cifre').show();
                $(this).addClass('error');
            } else {
                $error.hide();
                $(this).removeClass('error');
            }
        },

        validateEmail: function() {
            const email = $(this).val();
            const $error = $('#email-error');
            
            if (email && !utils.validateEmail(email)) {
                $error.text('Formato email non valido').show();
                $(this).addClass('error');
            } else {
                $error.hide();
                $(this).removeClass('error');
            }
        }
    };

    // DASHBOARD FUNCTIONALITY
    const dashboardHandler = {
        currentCategory: null,

        init: function() {
            $('.view-category').on('click', this.showDocuments.bind(this));
            $('#back-to-categories').on('click', this.showCategories.bind(this));
            $('#edit-profile-btn').on('click', this.showProfileModal.bind(this));
            $('#edit-profile-form').on('submit', this.updateProfile.bind(this));
            $('#documents-search').on('input', this.searchDocuments.bind(this));
            $('#documents-filter').on('change', this.filterDocuments.bind(this));
            
            // Modal handlers
            $('.ar-modal-close').on('click', this.hideModal);
            $(window).on('click', this.handleModalClick);
            
            // Load document counts for categories
            this.loadDocumentCounts();
        },

        showDocuments: function(e) {
            const category = $(e.target).closest('.category-card').data('category');
            this.currentCategory = category;
            
            $('#section-title').text(`Documenti - ${category.charAt(0).toUpperCase() + category.slice(1)}`);
            $('.categories-grid').hide();
            $('#documents-section').show();
            
            this.loadDocuments(category);
        },

        showCategories: function() {
            $('#documents-section').hide();
            $('.categories-grid').show();
            this.currentCategory = null;
        },

        loadDocuments: function(category, search = '', sottocartella = '') {
            const $grid = $('#documents-grid');
            $grid.html('<div class="loading-spinner"><div class="spinner"></div><p>Caricamento documenti...</p></div>');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ar_get_documents',
                    nonce: config.nonce,
                    category: category,
                    search: search,
                    sottocartella: sottocartella
                },
                success: function(response) {
                    if (response.success) {
                        dashboardHandler.renderDocuments(response.data.documents);
                        dashboardHandler.updateFilters(response.data.sottocartelle);
                    } else {
                        $grid.html('<p>Errore nel caricamento dei documenti.</p>');
                    }
                },
                error: function() {
                    $grid.html('<p>Errore di connessione.</p>');
                }
            });
        },

        renderDocuments: function(documents) {
            const $grid = $('#documents-grid');
            
            if (documents.length === 0) {
                $grid.html('<p>Nessun documento trovato per questa categoria.</p>');
                return;
            }
            
            let html = '';
            documents.forEach(doc => {
                html += `
                    <div class="document-card" data-sottocartella="${doc.sottocartella || ''}">
                        <h4>${doc.nome_file}</h4>
                        <div class="doc-meta">
                            ${doc.sottocartella ? `<p><strong>Sottocartella:</strong> ${doc.sottocartella}</p>` : ''}
                            <p><strong>Data:</strong> ${doc.data_formattata}</p>
                            ${doc.dimensione_formattata ? `<p><strong>Dimensione:</strong> ${doc.dimensione_formattata}</p>` : ''}
                        </div>
                        <a href="${doc.url}" target="_blank" class="btn-primary">
                            <span class="dashicons dashicons-download"></span> Scarica
                        </a>
                    </div>
                `;
            });
            
            $grid.html(html);
        },

        updateFilters: function(sottocartelle) {
            const $filter = $('#documents-filter');
            $filter.html('<option value="">Tutte le sottocartelle</option>');
            
            sottocartelle.forEach(sottocartella => {
                $filter.append(`<option value="${sottocartella}">${sottocartella}</option>`);
            });
        },

        searchDocuments: function() {
            if (this.currentCategory) {
                const search = $(this).val();
                const sottocartella = $('#documents-filter').val();
                this.loadDocuments(this.currentCategory, search, sottocartella);
            }
        },

        filterDocuments: function() {
            if (this.currentCategory) {
                const search = $('#documents-search').val();
                const sottocartella = $(this).val();
                this.loadDocuments(this.currentCategory, search, sottocartella);
            }
        },

        loadDocumentCounts: function() {
            $('.category-card').each(function() {
                const category = $(this).data('category');
                const $count = $(this).find('.doc-count[data-category="' + category + '"]');
                
                $.ajax({
                    url: config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'ar_get_documents',
                        nonce: config.nonce,
                        category: category
                    },
                    success: function(response) {
                        if (response.success) {
                            $count.text(response.data.total || 0);
                        }
                    }
                });
            });
        },

        showProfileModal: function() {
            $('#edit-profile-modal').fadeIn();
        },

        hideModal: function() {
            $('.ar-modal').fadeOut();
        },

        handleModalClick: function(e) {
            if ($(e.target).hasClass('ar-modal')) {
                $('.ar-modal').fadeOut();
            }
        },

        updateProfile: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            utils.showSpinner('#edit-profile-form button[type="submit"]');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('.ar-modal').fadeOut();
                        // Show success message
                        $('body').prepend(`
                            <div class="ar-message success" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                                ${response.data.message}
                            </div>
                        `);
                        setTimeout(() => {
                            $('.ar-message').fadeOut(() => $('.ar-message').remove());
                        }, 3000);
                        
                        // Refresh page to show updated info
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        alert(response.data.message || 'Errore durante l\'aggiornamento');
                    }
                },
                error: function() {
                    alert('Errore di connessione');
                },
                complete: function() {
                    utils.hideSpinner('#edit-profile-form button[type="submit"]');
                }
            });
        }
    };

    // Initialize based on current page
    const currentPage = areaRiservataFrontend.current_page || '';
    
    switch (currentPage) {
        case 'login':
            loginHandler.init();
            break;
        case 'register':
            registerHandler.init();
            break;
        case 'dashboard':
            dashboardHandler.init();
            break;
        default:
            // Initialize all if page parameter not available
            if ($('#area-riservata-login-form').length) {
                loginHandler.init();
            }
            if ($('#area-riservata-register-form').length) {
                registerHandler.init();
            }
            if ($('.area-riservata-dashboard').length) {
                dashboardHandler.init();
            }
    }

    // Keyboard accessibility
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.ar-modal').fadeOut();
        }
    });

    // Form validation styling
    $('input, select, textarea').on('focus', function() {
        $(this).removeClass('error');
        $(this).siblings('.field-error').hide();
    });

    // Auto-hide messages after 5 seconds
    setTimeout(() => {
        $('.ar-message').not('.persistent').fadeOut();
    }, 5000);
});