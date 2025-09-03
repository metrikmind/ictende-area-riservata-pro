/* Area Riservata Frontend JavaScript - Versione Completa con Reset Password */

jQuery(document).ready(function($) {
    'use strict';

    // Configurazione globale
    const config = {
        ajaxUrl: areaRiservataFrontend?.ajax_url || '/wp-admin/admin-ajax.php',
        nonce: areaRiservataFrontend?.nonce || '',
        messages: areaRiservataFrontend?.messages || {}
    };

    // Debug
    console.log('Area Riservata Frontend caricato', config);

    // Utility functions
    const utils = {
        showMessage: function(container, message, type = 'success') {
            const $container = $(container);
            $container.removeClass('success error info')
                     .addClass(type)
                     .html(message)
                     .fadeIn();
            
            // Auto-hide dopo 8 secondi per messaggi di successo
            if (type === 'success') {
                setTimeout(() => {
                    $container.fadeOut();
                }, 8000);
            }
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
            } else {
                // Crea messaggio di errore se non esiste
                $field.after(`<div class="field-error" style="color: #dc3545; font-size: 12px; margin-top: 4px;">${message}</div>`);
            }
        },

        clearFieldErrors: function() {
            $('.field-error').hide().remove();
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
            return /^[0-9]{11}$/.test(piva.replace(/\s/g, ''));
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
        },

        // Nuova funzione per gestire le richieste AJAX con retry
        ajaxRequest: function(options) {
            const defaults = {
                url: config.ajaxUrl,
                type: 'POST',
                timeout: 30000,
                retries: 3,
                retryDelay: 1000
            };
            
            const settings = $.extend({}, defaults, options);
            let attempt = 0;
            
            function makeRequest() {
                attempt++;
                console.log(`Tentativo AJAX ${attempt}/${settings.retries + 1}:`, settings.data?.action);
                
                return $.ajax({
                    url: settings.url,
                    type: settings.type,
                    data: settings.data,
                    processData: settings.processData !== false,
                    contentType: settings.contentType !== false ? 'application/x-www-form-urlencoded; charset=UTF-8' : false,
                    timeout: settings.timeout
                }).fail(function(xhr, status, error) {
                    console.error(`AJAX Error (tentativo ${attempt}):`, status, error, xhr);
                    
                    if (attempt <= settings.retries && (status === 'timeout' || xhr.status === 0 || xhr.status >= 500)) {
                        console.log(`Riprovo tra ${settings.retryDelay}ms...`);
                        setTimeout(makeRequest, settings.retryDelay * attempt);
                        return;
                    }
                    
                    // Ultimo tentativo fallito
                    if (settings.error) {
                        settings.error(xhr, status, error);
                    }
                }).done(function(response) {
                    console.log(`AJAX Success (tentativo ${attempt}):`, response);
                    if (settings.success) {
                        settings.success(response);
                    }
                });
            }
            
            return makeRequest();
        }
    };

    // LOGIN FUNCTIONALITY
    const loginHandler = {
        init: function() {
            console.log('Inizializzazione login handler');
            $('#area-riservata-login-form').on('submit', this.handleSubmit.bind(this));
            $('.toggle-password').on('click', this.togglePassword);
        },

        handleSubmit: function(e) {
            e.preventDefault();
            console.log('Submit login form');
            
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
            
            utils.ajaxRequest({
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
                            window.location.href = response.data.redirect || 'https://ictende.scherpmind.com/area-riservata/';
                        }, 1500);
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
                error: function(xhr, status, error) {
                    let errorMessage = 'Errore di connessione. ';
                    if (status === 'timeout') {
                        errorMessage += 'Timeout della richiesta.';
                    } else if (xhr.status === 403) {
                        errorMessage += 'Accesso negato. Ricarica la pagina.';
                    } else {
                        errorMessage += 'Riprova più tardi.';
                    }
                    utils.showMessage('#login-message', errorMessage, 'error');
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
                $btn.attr('aria-label', 'Nascondi password');
            } else {
                $input.attr('type', 'password');
                $icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
                $btn.attr('aria-label', 'Mostra password');
            }
        }
    };

    // RESET PASSWORD FUNCTIONALITY
    const resetPasswordHandler = {
        init: function() {
            console.log('Inizializzazione reset password handler');
            $('#area-riservata-reset-form').on('submit', this.handleSubmit.bind(this));
        },

        handleSubmit: function(e) {
            e.preventDefault();
            console.log('Submit reset password form');
            
            const $form = $(e.target);
            const email = $('#reset_email').val();
            
            utils.clearFieldErrors();
            
            if (!email) {
                utils.showFieldError('email', 'Inserisci il tuo indirizzo email');
                return;
            }
            
            if (!utils.validateEmail(email)) {
                utils.showFieldError('email', 'Formato email non valido');
                return;
            }

            utils.showSpinner('#reset-submit');
            
            utils.ajaxRequest({
                data: {
                    action: 'ar_reset_password',
                    nonce: config.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        utils.showMessage('#reset-message', response.data.message, 'success');
                        $form[0].reset();
                    } else {
                        utils.showMessage('#reset-message', response.data.message, 'error');
                    }
                },
                error: function() {
                    utils.showMessage('#reset-message', 'Errore di connessione. Riprova più tardi.', 'error');
                },
                complete: function() {
                    utils.hideSpinner('#reset-submit');
                }
            });
        }
    };

    // NEW PASSWORD FORM FUNCTIONALITY
    const newPasswordHandler = {
        init: function() {
            console.log('Inizializzazione new password handler');
            $('#area-riservata-new-password-form').on('submit', this.handleSubmit.bind(this));
            $('.toggle-password').on('click', loginHandler.togglePassword);
            $('#new_password').on('input', this.checkPasswordStrength);
            $('#confirm_password').on('input', this.validatePasswordMatch);
        },

        handleSubmit: function(e) {
            e.preventDefault();
            console.log('Submit new password form');
            
            const $form = $(e.target);
            const token = $('input[name="token"]').val();
            const password = $('#new_password').val();
            const confirmPassword = $('#confirm_password').val();
            
            utils.clearFieldErrors();
            
            // Validazioni
            if (!password || password.length < 8) {
                utils.showFieldError('password', 'Password deve essere di almeno 8 caratteri');
                return;
            }
            
            if (!confirmPassword) {
                utils.showFieldError('confirm_password', 'Conferma la password');
                return;
            }
            
            if (password !== confirmPassword) {
                utils.showFieldError('confirm_password', 'Le password non coincidono');
                return;
            }

            utils.showSpinner('#new-password-submit');
            
            utils.ajaxRequest({
                data: {
                    action: 'ar_set_new_password',
                    nonce: config.nonce,
                    token: token,
                    password: password,
                    confirm_password: confirmPassword
                },
                success: function(response) {
                    if (response.success) {
                        utils.showMessage('#new-password-message', response.data.message, 'success');
                        $form[0].reset();
                        $('.password-strength').text('').removeClass('weak medium strong');
                        
                        // Redirect dopo 3 secondi
                        setTimeout(() => {
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                window.location.href = 'https://ictende.scherpmind.com/login/';
                            }
                        }, 3000);
                    } else {
                        utils.showMessage('#new-password-message', response.data.message, 'error');
                    }
                },
                error: function() {
                    utils.showMessage('#new-password-message', 'Errore di connessione. Riprova più tardi.', 'error');
                },
                complete: function() {
                    utils.hideSpinner('#new-password-submit');
                }
            });
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

        validatePasswordMatch: function() {
            const password = $('#new_password').val();
            const confirmPassword = $(this).val();
            const $error = $('#confirm-password-error');
            
            if (confirmPassword && password !== confirmPassword) {
                if ($error.length === 0) {
                    $(this).after('<div id="confirm-password-error" class="field-error">Le password non coincidono</div>');
                } else {
                    $error.text('Le password non coincidono').show();
                }
                $(this).addClass('error');
            } else {
                $error.hide();
                $(this).removeClass('error');
            }
        }
    };

    // REGISTRATION FUNCTIONALITY
    const registerHandler = {
        init: function() {
            console.log('Inizializzazione register handler');
            $('#area-riservata-register-form').on('submit', this.handleSubmit.bind(this));
            $('.toggle-password').on('click', loginHandler.togglePassword);
            $('#reg_password').on('input', this.checkPasswordStrength);
            $('#reg_partita_iva').on('input', this.validatePartitaIva);
            $('#reg_email').on('blur', this.validateEmail);
            $('#reg_username').on('blur', this.validateUsername);
        },

        handleSubmit: function(e) {
            e.preventDefault();
            console.log('Submit register form');
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            utils.clearFieldErrors();
            
            // Client-side validation
            if (!this.validateForm(formData)) {
                return;
            }

            // Add action and nonce to formData
            formData.append('action', 'ar_register_user');
            formData.append('nonce', config.nonce);

            console.log('Invio registrazione con nonce:', config.nonce);

            utils.showSpinner('#register-submit');
            
            utils.ajaxRequest({
                data: formData,
                processData: false,
                contentType: false,
                timeout: 45000, // Timeout più lungo per upload foto
                success: function(response) {
                    console.log('Risposta registrazione:', response);
                    if (response.success) {
                        utils.showMessage('#register-message', response.data.message, 'success');
                        $form[0].reset();
                        $('.password-strength').text('').removeClass('weak medium strong');
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
                        // Scroll to top per vedere gli errori
                        $('html, body').animate({ scrollTop: 0 }, 500);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Errore registrazione:', status, error, xhr);
                    
                    let errorMessage = 'Errore durante la registrazione. ';
                    if (status === 'timeout') {
                        errorMessage += 'Timeout della richiesta. Riprova.';
                    } else if (xhr.status === 403) {
                        errorMessage += 'Accesso negato. Ricarica la pagina.';
                    } else if (xhr.status === 413) {
                        errorMessage += 'File troppo grande.';
                    } else {
                        errorMessage += 'Riprova più tardi.';
                    }
                    
                    utils.showMessage('#register-message', errorMessage, 'error');
                    $('html, body').animate({ scrollTop: 0 }, 500);
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
                const value = formData.get(field);
                if (!value || value.trim() === '') {
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

            // File validation
            const foto = formData.get('foto_profilo');
            if (foto && foto.size > 0) {
                if (foto.size > 2 * 1024 * 1024) { // 2MB
                    utils.showFieldError('foto_profilo', 'La foto non può superare i 2MB');
                    isValid = false;
                }
                
                const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!validTypes.includes(foto.type)) {
                    utils.showFieldError('foto_profilo', 'Formato foto non supportato. Usa JPG, PNG o GIF');
                    isValid = false;
                }
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
            const piva = $(this).val().replace(/\s/g, ''); // Rimuovi spazi
            const $error = $('#partita-iva-error');
            
            if (piva && !utils.validatePartitaIva(piva)) {
                if ($error.length === 0) {
                    $(this).after('<div id="partita-iva-error" class="field-error">Partita IVA deve contenere 11 cifre</div>');
                } else {
                    $error.text('Partita IVA deve contenere 11 cifre').show();
                }
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
                if ($error.length === 0) {
                    $(this).after('<div id="email-error" class="field-error">Formato email non valido</div>');
                } else {
                    $error.text('Formato email non valido').show();
                }
                $(this).addClass('error');
            } else {
                $error.hide();
                $(this).removeClass('error');
            }
        },

        validateUsername: function() {
            const username = $(this).val();
            const $error = $('#username-error');
            
            if (username && (username.length < 3 || !/^[a-zA-Z0-9_]+$/.test(username))) {
                const message = username.length < 3 ? 
                    'Username deve essere di almeno 3 caratteri' : 
                    'Username può contenere solo lettere, numeri e underscore';
                
                if ($error.length === 0) {
                    $(this).after(`<div id="username-error" class="field-error">${message}</div>`);
                } else {
                    $error.text(message).show();
                }
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
            console.log('Inizializzazione dashboard handler');
            $('.view-category').on('click', this.showDocuments.bind(this));
            $('#back-to-categories').on('click', this.showCategories.bind(this));
            $('#edit-profile-btn').on('click', this.showProfileModal.bind(this));
            $('#edit-profile-form').on('submit', this.updateProfile.bind(this));
            $('#documents-search').on('input', this.debounce(this.searchDocuments.bind(this), 500));
            $('#documents-filter').on('change', this.filterDocuments.bind(this));
            
            // Modal handlers
            $('.ar-modal-close').on('click', this.hideModal);
            $(window).on('click', this.handleModalClick);
            
            // Load document counts for categories
            this.loadDocumentCounts();
        },

        // Debounce function per la ricerca
        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        showDocuments: function(e) {
            e.preventDefault();
            const $card = $(e.target).closest('.category-card');
            const category = $card.data('category');
            
            if (!category) {
                console.error('Categoria non trovata');
                return;
            }
            
            this.currentCategory = category;
            
            $('#section-title').text(`Documenti - ${category.charAt(0).toUpperCase() + category.slice(1)}`);
            $('.categories-grid').hide();
            $('#documents-section').show();
            
            this.loadDocuments(category);
        },

        showCategories: function(e) {
            e.preventDefault();
            $('#documents-section').hide();
            $('.categories-grid').show();
            this.currentCategory = null;
        },

        loadDocuments: function(category, search = '', sottocartella = '') {
            const $grid = $('#documents-grid');
            $grid.html(`
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Caricamento documenti...</p>
                </div>
            `);
            
            utils.ajaxRequest({
                data: {
                    action: 'ar_get_documents',
                    nonce: config.nonce,
                    category: category,
                    search: search,
                    sottocartella: sottocartella
                },
                success: (response) => {
                    if (response.success) {
                        this.renderDocuments(response.data.documents);
                        this.updateFilters(response.data.sottocartelle);
                    } else {
                        $grid.html('<p style="text-align: center; padding: 40px; color: #1b2341;">Errore nel caricamento dei documenti.</p>');
                    }
                },
                error: () => {
                    $grid.html('<p style="text-align: center; padding: 40px; color: #dc3545;">Errore di connessione. <button onclick="location.reload()" class="btn-secondary" style="margin-left: 10px;">Ricarica</button></p>');
                }
            });
        },

        renderDocuments: function(documents) {
            const $grid = $('#documents-grid');
            
            if (documents.length === 0) {
                $grid.html('<p style="text-align: center; padding: 40px; color: #1b2341;">📄 Nessun documento trovato per questa categoria.</p>');
                return;
            }
            
            let html = '';
            documents.forEach(doc => {
                html += `
                    <div class="document-card" data-sottocartella="${doc.sottocartella || ''}">
                        <h4 title="${doc.nome_file}">${doc.nome_file}</h4>
                        <div class="doc-meta">
                            ${doc.sottocartella ? `<p><strong>📁 Sottocartella:</strong> ${doc.sottocartella}</p>` : ''}
                            <p><strong>📅 Data:</strong> ${doc.data_formattata}</p>
                            ${doc.dimensione_formattata ? `<p><strong>📏 Dimensione:</strong> ${doc.dimensione_formattata}</p>` : ''}
                        </div>
                        <a href="${doc.url}" target="_blank" class="btn-primary" rel="noopener noreferrer">
                            <span class="dashicons dashicons-download"></span> Scarica
                        </a>
                    </div>
                `;
            });
            
            $grid.html(html);
            
            // Animazione per i documenti caricati
            $('.document-card').hide().each(function(index) {
                $(this).delay(index * 100).fadeIn(300);
            });
        },

        updateFilters: function(sottocartelle) {
            const $filter = $('#documents-filter');
            const currentValue = $filter.val();
            
            $filter.html('<option value="">Tutte le sottocartelle</option>');
            
            sottocartelle.forEach(sottocartella => {
                const selected = currentValue === sottocartella ? ' selected' : '';
                $filter.append(`<option value="${sottocartella}"${selected}>${sottocartella}</option>`);
            });
        },

        searchDocuments: function() {
            if (this.currentCategory) {
                const search = $('#documents-search').val();
                const sottocartella = $('#documents-filter').val();
                this.loadDocuments(this.currentCategory, search, sottocartella);
            }
        },

        filterDocuments: function() {
            if (dashboardHandler.currentCategory) {
                const search = $('#documents-search').val();
                const sottocartella = $(this).val();
                dashboardHandler.loadDocuments(dashboardHandler.currentCategory, search, sottocartella);
            }
        },

        loadDocumentCounts: function() {
            $('.category-card').each(function() {
                const $card = $(this);
                const category = $card.data('category');
                const $count = $card.find('.doc-count[data-category="' + category + '"]');
                
                if (!$count.length) return;
                
                utils.ajaxRequest({
                    data: {
                        action: 'ar_get_documents',
                        nonce: config.nonce,
                        category: category
                    },
                    success: function(response) {
                        if (response.success) {
                            const count = response.data.total || 0;
                            $count.text(count);
                            
                            // Aggiungi badge se ci sono documenti
                            if (count > 0 && !$card.find('.doc-badge').length) {
                                $card.find('.category-header').append(
                                    `<span class="doc-badge" style="background: #28a745; color: white; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold;">${count}</span>`
                                );
                            }
                        }
                    },
                    error: function() {
                        $count.text('?');
                    }
                });
            });
        },

        showProfileModal: function(e) {
            e.preventDefault();
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
            formData.append('action', 'ar_update_profile');
            formData.append('nonce', config.nonce);
            
            utils.showSpinner('#edit-profile-form button[type="submit"]');
            
            utils.ajaxRequest({
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $('.ar-modal').fadeOut();
                        
                        // Show success message
                        $('body').prepend(`
                            <div class="ar-message success" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 300px;">
                                ${response.data.message}
                            </div>
                        `);
                        
                        setTimeout(() => {
                            $('.ar-message').fadeOut(() => $('.ar-message').remove());
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert(response.data.message || 'Errore durante l\'aggiornamento');
                    }
                },
                error: function() {
                    alert('Errore di connessione. Riprova.');
                },
                complete: function() {
                    utils.hideSpinner('#edit-profile-form button[type="submit"]');
                }
            });
        }
    };

    // Initialize based on current page or form presence
    const currentPage = areaRiservataFrontend?.current_page || '';
    
    // Inizializza in base alla presenza dei form
    if ($('#area-riservata-login-form').length) {
        console.log('Trovato form di login');
        loginHandler.init();
    }
    
    if ($('#area-riservata-register-form').length) {
        console.log('Trovato form di registrazione');
        registerHandler.init();
    }
    
    if ($('#area-riservata-reset-form').length) {
        console.log('Trovato form di reset password');
        resetPasswordHandler.init();
    }
    
    if ($('#area-riservata-new-password-form').length) {
        console.log('Trovato form nuova password');
        newPasswordHandler.init();
    }
    
    if ($('.area-riservata-dashboard').length) {
        console.log('Trovata dashboard');
        dashboardHandler.init();
    }

    // Keyboard accessibility
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.ar-modal').fadeOut();
        }
    });

    // Form validation styling
    $(document).on('focus', 'input, select, textarea', function() {
        $(this).removeClass('error');
        $(this).siblings('.field-error').fadeOut();
    });

    // Auto-hide success messages after 8 seconds
    setTimeout(() => {
        $('.ar-message.success').not('.persistent').fadeOut();
    }, 8000);
    
    // Auto-hide error messages after 15 seconds
    setTimeout(() => {
        $('.ar-message.error').not('.persistent').fadeOut();
    }, 15000);

    // Gestione URL con parametri per reset password
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('action') && urlParams.get('action') === 'reset') {
        const token = urlParams.get('token');
        if (token) {
            // Mostra form per nuova password
            console.log('Token di reset trovato:', token);
            // Qui potresti aggiungere logica per mostrare un form di reset password
        }
    }

    // Debug info
    console.log('Area Riservata Frontend inizializzato completamente');
    if (window.location.hostname.includes('scherpmind.com')) {
        console.log('Ambiente di produzione rilevato');
    }
});