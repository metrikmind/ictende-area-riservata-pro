/* Area Riservata Frontend JavaScript - VERSIONE COMPLETA CON REDIRECT ROBUSTO */

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

    // LOGIN FUNCTIONALITY - VERSIONE COMPLETA CON REDIRECT ROBUSTO
    const loginHandler = {
        init: function() {
            $('#area-riservata-login-form').on('submit', this.handleSubmit.bind(this));
            $('#area-riservata-reset-form').on('submit', this.handleResetSubmit.bind(this));
            $('#forgot-password-form').on('submit', this.handleForgotSubmit.bind(this));
            
            $('.toggle-password').on('click', this.togglePassword);
            $('#forgot-password-link').on('click', this.showForgotForm.bind(this));
            $('#back-to-login').on('click', this.showLoginForm.bind(this));
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

            // AJAX request con gestione redirect estesa
            utils.showSpinner('#login-submit');
            
            console.log('🚀 AVVIO LOGIN REQUEST');
            console.log('URL:', config.ajaxUrl);
            console.log('Username:', username);
            
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
                timeout: 25000, // 25 secondi
                success: function(response) {
                    console.log('✅ LOGIN RESPONSE:', response);
                    
                    if (response.success) {
                        const redirectUrl = response.data.redirect || 'https://ictende.scherpmind.com/area-riservata/';
                        console.log('🎯 REDIRECT URL:', redirectUrl);
                        
                        // Nascondi form e mostra messaggio
                        $form.hide();
                        utils.showMessage('#login-message', 
                            '✅ ' + response.data.message + '<br><br>' +
                            '🔄 Reindirizzamento in corso...<br>' +
                            '<small>Destinazione: ' + redirectUrl + '</small>', 
                            'success'
                        );
                        
                        // Se il server ha inviato HTML per il redirect forzato, eseguilo
                        if (response.data.redirect_html && response.data.force_redirect) {
                            console.log('🔧 USANDO REDIRECT HTML FORZATO');
                            const scriptDiv = document.createElement('div');
                            scriptDiv.innerHTML = response.data.redirect_html;
                            document.head.appendChild(scriptDiv);
                        }
                        
                        // SERIE DI TENTATIVI DI REDIRECT PROGRESSIVI
                        
                        // TENTATIVO 1: Redirect immediato (500ms)
                        setTimeout(() => {
                            console.log('🔄 Tentativo 1: location.href immediato');
                            try {
                                window.location.href = redirectUrl;
                            } catch(e) {
                                console.error('❌ Errore tentativo 1:', e);
                            }
                        }, 500);
                        
                        // TENTATIVO 2: Replace (1.5s)
                        setTimeout(() => {
                            if (window.location.href.indexOf('area-riservata') === -1) {
                                console.log('🔄 Tentativo 2: location.replace');
                                try {
                                    window.location.replace(redirectUrl);
                                } catch(e) {
                                    console.error('❌ Errore tentativo 2:', e);
                                }
                            }
                        }, 1500);
                        
                        // TENTATIVO 3: Assign (2.5s)
                        setTimeout(() => {
                            if (window.location.href.indexOf('area-riservata') === -1) {
                                console.log('🔄 Tentativo 3: location.assign');
                                try {
                                    window.location.assign(redirectUrl);
                                } catch(e) {
                                    console.error('❌ Errore tentativo 3:', e);
                                }
                            }
                        }, 2500);
                        
                        // TENTATIVO 4: Meta refresh dinamico (3.5s)
                        setTimeout(() => {
                            if (window.location.href.indexOf('area-riservata') === -1) {
                                console.log('🔄 Tentativo 4: Meta refresh dinamico');
                                try {
                                    const meta = document.createElement('meta');
                                    meta.setAttribute('http-equiv', 'refresh');
                                    meta.setAttribute('content', '0; url=' + redirectUrl);
                                    document.head.appendChild(meta);
                                } catch(e) {
                                    console.error('❌ Errore tentativo 4:', e);
                                }
                            }
                        }, 3500);
                        
                        // TENTATIVO 5: Window.open con _self (4.5s)
                        setTimeout(() => {
                            if (window.location.href.indexOf('area-riservata') === -1) {
                                console.log('🔄 Tentativo 5: window.open _self');
                                try {
                                    window.open(redirectUrl, '_self');
                                } catch(e) {
                                    console.error('❌ Errore tentativo 5:', e);
                                }
                            }
                        }, 4500);
                        
                        // TENTATIVO 6: Form submit nascosto (6s)
                        setTimeout(() => {
                            if (window.location.href.indexOf('area-riservata') === -1) {
                                console.log('🔄 Tentativo 6: Form submit');
                                try {
                                    const form = document.createElement('form');
                                    form.method = 'GET';
                                    form.action = redirectUrl;
                                    form.style.display = 'none';
                                    document.body.appendChild(form);
                                    form.submit();
                                } catch(e) {
                                    console.error('❌ Errore tentativo 6:', e);
                                }
                            }
                        }, 6000);
                        
                        // TENTATIVO 7: Link di backup manuale (7.5s)
                        setTimeout(() => {
                            if (window.location.href.indexOf('area-riservata') === -1) {
                                console.log('⚠️ Tutti i redirect automatici falliti, mostro interfaccia di backup');
                                utils.showMessage('#login-message', 
                                    '<div style="text-align: center; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; margin: 20px 0;">' +
                                        '<h3 style="color: white; margin: 0 0 15px 0;">✅ Login Completato con Successo!</h3>' +
                                        '<p style="color: white; margin: 0 0 20px 0;">Il redirect automatico sembra essere bloccato dal tuo browser.</p>' +
                                        '<div style="margin: 20px 0;">' +
                                            '<a href="' + redirectUrl + '" id="manual-redirect-link" style="display: inline-block; margin: 10px; padding: 15px 30px; background: white; color: #1b2341; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px; transition: transform 0.3s;">' +
                                                '🚀 VAI ALL\'AREA RISERVATA' +
                                            '</a>' +
                                        '</div>' +
                                        '<small style="color: rgba(255,255,255,0.8);">URL: ' + redirectUrl + '</small>' +
                                        '<div style="margin-top: 15px; font-size: 12px; color: rgba(255,255,255,0.7);">Auto-redirect tra <span id="countdown">3</span> secondi...</div>' +
                                    '</div>', 
                                    'success'
                                );
                                
                                // Countdown e auto-click
                                let countdown = 3;
                                const countdownElement = document.getElementById('countdown');
                                const countdownTimer = setInterval(() => {
                                    countdown--;
                                    if (countdownElement) {
                                        countdownElement.textContent = countdown;
                                    }
                                    if (countdown <= 0) {
                                        clearInterval(countdownTimer);
                                        const link = document.getElementById('manual-redirect-link');
                                        if (link) {
                                            console.log('🤖 Auto-click del link di backup');
                                            link.click();
                                        }
                                    }
                                }, 1000);
                                
                                // Effetto hover sul link
                                $('#manual-redirect-link').hover(
                                    function() { $(this).css('transform', 'scale(1.05)'); },
                                    function() { $(this).css('transform', 'scale(1)'); }
                                );
                            }
                        }, 7500);
                        
                        // DEBUG: Monitor del processo
                        let debugCount = 0;
                        const debugTimer = setInterval(() => {
                            debugCount++;
                            const currentUrl = window.location.href;
                            const hasReached = currentUrl.indexOf('area-riservata') !== -1;
                            
                            console.log(`🔍 DEBUG ${debugCount}: URL = ${currentUrl}`);
                            console.log(`🔍 DEBUG ${debugCount}: Reached target = ${hasReached}`);
                            
                            if (debugCount >= 15 || hasReached) {
                                console.log(`🏁 DEBUG TERMINATO: ${hasReached ? 'SUCCESSO' : 'FALLIMENTO'}`);
                                clearInterval(debugTimer);
                            }
                        }, 1000);
                        
                    } else {
                        console.log('❌ LOGIN FAILED:', response.data.message);
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
                    console.error('💥 AJAX ERROR:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                    
                    let errorMessage = 'Errore di connessione. ';
                    if (status === 'timeout') {
                        errorMessage += 'Il server impiega troppo tempo a rispondere.';
                    } else if (xhr.status === 403) {
                        errorMessage += 'Accesso negato. Ricarica la pagina.';
                    } else if (xhr.status === 404) {
                        errorMessage += 'Servizio di login non trovato.';
                    } else if (xhr.status === 500) {
                        errorMessage += 'Errore interno del server.';
                    } else if (xhr.status === 0) {
                        errorMessage += 'Nessuna connessione di rete.';
                    } else {
                        errorMessage += `Codice errore: ${xhr.status}`;
                    }
                    
                    utils.showMessage('#login-message', errorMessage, 'error');
                },
                complete: function() {
                    utils.hideSpinner('#login-submit');
                }
            });
        },

        showForgotForm: function(e) {
            e.preventDefault();
            $('#area-riservata-login-form').hide();
            $('#forgot-password-form').show();
            utils.hideMessage('#login-message');
            utils.clearFieldErrors();
        },

        showLoginForm: function(e) {
            e.preventDefault();
            $('#forgot-password-form').hide();
            $('#area-riservata-login-form').show();
            utils.hideMessage('#forgot-message');
            utils.clearFieldErrors();
        },

        handleForgotSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const email = $form.find('input[name="email"]').val();
            
            utils.clearFieldErrors();
            
            if (!email) {
                utils.showFieldError('email', 'Email obbligatoria');
                return;
            }
            
            if (!utils.validateEmail(email)) {
                utils.showFieldError('email', 'Formato email non valido');
                return;
            }

            utils.showSpinner('#forgot-submit');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ar_reset_password',
                    nonce: config.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        utils.showMessage('#forgot-message', response.data.message, 'success');
                        $form[0].reset();
                    } else {
                        utils.showMessage('#forgot-message', response.data.message, 'error');
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
                    utils.showMessage('#forgot-message', 'Errore di connessione. Riprova.', 'error');
                },
                complete: function() {
                    utils.hideSpinner('#forgot-submit');
                }
            });
        },

        handleResetSubmit: function(e) {
            e.preventDefault();
            
            const $form = $(e.target);
            const formData = new FormData($form[0]);
            
            const token = formData.get('token');
            const password = formData.get('password');
            const confirmPassword = $form.find('input[name="confirm_password"]').val();
            
            utils.clearFieldErrors();
            
            if (!password) {
                utils.showFieldError('password', 'Password obbligatoria');
                return;
            }
            
            if (password.length < 8) {
                utils.showFieldError('password', 'Password deve essere di almeno 8 caratteri');
                return;
            }
            
            if (password !== confirmPassword) {
                utils.showFieldError('confirm_password', 'Le password non corrispondono');
                return;
            }

            utils.showSpinner('#reset-submit');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ar_confirm_reset',
                    nonce: config.nonce,
                    token: token,
                    password: password,
                    confirm_password: confirmPassword
                },
                success: function(response) {
                    if (response.success) {
                        utils.showMessage('#reset-message', response.data.message, 'success');
                        setTimeout(() => {
                            if (response.data.redirect) {
                                window.location.href = response.data.redirect;
                            } else {
                                window.location.href = 'https://ictende.scherpmind.com/login/';
                            }
                        }, 3000);
                    } else {
                        utils.showMessage('#reset-message', response.data.message, 'error');
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
                    utils.showMessage('#reset-message', 'Errore di connessione. Riprova.', 'error');
                },
                complete: function() {
                    utils.hideSpinner('#reset-submit');
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
        },

        // Metodo di test per il redirect manuale
        testRedirect: function() {
            const testUrl = 'https://ictende.scherpmind.com/area-riservata/';
            console.log('🧪 TEST REDIRECT MANUALE a:', testUrl);
            window.location.href = testUrl;
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

            // Add action and nonce to formData
            formData.append('action', 'ar_register_user');
            formData.append('nonce', config.nonce);

            console.log('Invio registrazione con nonce:', config.nonce);
            console.log('URL AJAX:', config.ajaxUrl);

            utils.showSpinner('#register-submit');
            
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                timeout: 30000,
                success: function(response) {
                    console.log('Risposta server registrazione:', response);
                    if (response.success) {
                        utils.showMessage('#register-message', response.data.message, 'success');
                        $form[0].reset();
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
                    console.error('Errore AJAX registrazione:', status, error);
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
                        $('body').prepend(`
                            <div class="ar-message success" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">
                                ${response.data.message}
                            </div>
                        `);
                        setTimeout(() => {
                            $('.ar-message').fadeOut(() => $('.ar-message').remove());
                        }, 3000);
                        
                        if (response.data.reload) {
                            setTimeout(() => window.location.reload(), 1000);
                        }
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
            if ($('#area-riservata-login-form').length || $('#area-riservata-reset-form').length || $('#forgot-password-form').length) {
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

    // Auto-hide messages after 8 seconds
    setTimeout(() => {
        $('.ar-message').not('.persistent').fadeOut();
    }, 8000);

    // DEBUG e controlli globali
    if (window.location.href.indexOf('debug=1') !== -1 || window.location.href.indexOf('login_debug=1') !== -1) {
        console.log('🔍 AREA RISERVATA DEBUG MODE ATTIVO');
        console.log('Config:', config);
        console.log('Current URL:', window.location.href);
        console.log('Forms presenti:', {
            login: $('#area-riservata-login-form').length > 0,
            register: $('#area-riservata-register-form').length > 0,
            dashboard: $('.area-riservata-dashboard').length > 0
        });
        
        // Aggiungi pulsante di test redirect nella console
        window.testRedirect = loginHandler.testRedirect;
        console.log('🧪 Usa window.testRedirect() per testare il redirect manualmente');
    }

    // Gestione speciale per il messaggio "reindirizzamento in corso"
    // Se l'utente rimane sulla stessa pagina per più di 10 secondi dopo un login
    let loginAttempted = false;
    $(document).on('ajaxComplete', function(event, xhr, settings) {
        if (settings.data && settings.data.indexOf('action=ar_login_user') !== -1) {
            loginAttempted = true;
            setTimeout(() => {
                if (loginAttempted && window.location.href.indexOf('login') !== -1) {
                    console.log('⚠️ REDIRECT FALLITO - Mostro opzioni alternative');
                    if ($('#login-message').is(':visible') && $('#login-message').hasClass('success')) {
                        $('#login-message').append('<br><div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 5px;"><strong>Il redirect non funziona?</strong><br><a href="https://ictende.scherpmind.com/area-riservata/" style="color: white; font-weight: bold;">Clicca qui per accedere manualmente</a></div>');
                    }
                }
            }, 10000);
        }
    });

    console.log('🚀 Area Riservata Frontend JavaScript caricato con successo');
});