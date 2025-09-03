<?php
/**
 * Gestione Frontend per Area Riservata Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class AreaRiservata_Frontend {
    
    private $db;
    private $user_meta_key = 'area_riservata_user_id';
    
    public function __construct($database) {
        $this->db = $database;
        $this->init();
    }
    
    public function init() {
        // Shortcodes
        add_shortcode('area_riservata_login', array($this, 'shortcode_login'));
        add_shortcode('area_riservata_register', array($this, 'shortcode_register'));
        add_shortcode('area_riservata_dashboard', array($this, 'shortcode_dashboard'));
        
        // Scripts e stili frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers frontend
        add_action('wp_ajax_nopriv_ar_register_user', array($this, 'ajax_register_user'));
        add_action('wp_ajax_nopriv_ar_login_user', array($this, 'ajax_login_user'));
        add_action('wp_ajax_ar_update_profile', array($this, 'ajax_update_profile'));
        add_action('wp_ajax_ar_get_documents', array($this, 'ajax_get_documents'));
        add_action('wp_ajax_ar_logout_user', array($this, 'ajax_logout_user'));
        add_action('wp_ajax_nopriv_ar_logout_user', array($this, 'ajax_logout_user'));
        
        // Handle logout via URL
        add_action('wp_loaded', array($this, 'handle_logout'));
        
        // Body class per pagine area riservata
        add_filter('body_class', array($this, 'add_body_classes'));
    }
    
    /**
     * Enqueue scripts e stili - carica sempre sulle pagine
     */
    public function enqueue_scripts() {
        // Per ora carichiamo sempre per assicurarci che funzioni
        // Più avanti si può ottimizzare
        
        wp_enqueue_script(
            'area-riservata-frontend',
            AREA_RISERVATA_PLUGIN_URL . 'assets/frontend.js',
            array('jquery'),
            AREA_RISERVATA_VERSION . '-' . time(), // Cache busting per debug
            true
        );
        
        wp_enqueue_style(
            'area-riservata-frontend',
            AREA_RISERVATA_PLUGIN_URL . 'assets/frontend.css',
            array(),
            AREA_RISERVATA_VERSION . '-' . time() // Cache busting per debug
        );
        
        wp_localize_script('area-riservata-frontend', 'areaRiservataFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('area_riservata_nonce'),
            'current_page' => isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '',
            'messages' => array(
                'login_success' => 'Login effettuato con successo!',
                'register_success' => 'Registrazione inviata! Attendi l\'approvazione.',
                'profile_updated' => 'Profilo aggiornato con successo!',
                'generic_error' => 'Si è verificato un errore. Riprova.'
            )
        ));
    }
    
    /**
     * Aggiunge classi CSS al body
     */
    public function add_body_classes($classes) {
        if (isset($_GET['page']) && in_array($_GET['page'], ['login', 'register', 'dashboard'])) {
            $classes[] = 'area-riservata-page';
            $classes[] = 'area-riservata-' . sanitize_html_class($_GET['page']);
        }
        
        if ($this->is_user_logged_in()) {
            $classes[] = 'area-riservata-logged-in';
        }
        
        return $classes;
    }
    
    /**
     * Shortcode Login
     */
    public function shortcode_login($atts) {
        // Redirect se già loggato
        if ($this->is_user_logged_in()) {
            if (!headers_sent()) {
                wp_redirect(add_query_arg('page', 'dashboard', get_permalink()));
                exit;
            }
            return '<div class="ar-redirect-notice"><p>Sei già loggato. <a href="?page=dashboard">Vai alla dashboard</a></p></div>';
        }
        
        $atts = shortcode_atts(array(
            'redirect_to' => '',
            'show_register_link' => 'true'
        ), $atts);
        
        ob_start();
        ?>
        <div class="area-riservata-login">
            <a href="<?php echo esc_url(home_url()); ?>" class="home-button">
                <span class="dashicons dashicons-arrow-left-alt"></span> Torna alla Home
            </a>
            
            <div class="login-container">
                <div class="login-header">
                    <h2>Accedi all'Area Riservata</h2>
                    <p>Inserisci le tue credenziali per continuare</p>
                </div>
                
                <div id="login-message" class="ar-message" style="display: none;"></div>
                
                <form id="area-riservata-login-form" class="login-form" novalidate>
                    <?php wp_nonce_field('area_riservata_nonce', 'nonce'); ?>
                    
                    <div class="form-group">
                        <label for="ar_username">Username</label>
                        <input type="text" 
                               id="ar_username" 
                               name="username" 
                               required 
                               autocomplete="username"
                               aria-describedby="username-error">
                        <div id="username-error" class="field-error" style="display: none;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ar_password">Password</label>
                        <div class="password-field">
                            <input type="password" 
                                   id="ar_password" 
                                   name="password" 
                                   required
                                   autocomplete="current-password"
                                   aria-describedby="password-error">
                            <button type="button" class="toggle-password" aria-label="Mostra/nascondi password">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                        <div id="password-error" class="field-error" style="display: none;"></div>
                    </div>
                    
                    <?php if (!empty($atts['redirect_to'])): ?>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr($atts['redirect_to']); ?>">
                    <?php endif; ?>
                    
                    <button type="submit" class="btn-primary" id="login-submit">
                        <span class="btn-text">Accedi</span>
                        <span class="btn-spinner" style="display: none;"></span>
                    </button>
                    
                    <?php if ($atts['show_register_link'] === 'true'): ?>
                        <div class="login-links">
                            <a href="https://ictende.scherpmind.com/registrazione/">Non hai un account? Registrati</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode Registrazione
     */
    public function shortcode_register($atts) {
        // Redirect se già loggato
        if ($this->is_user_logged_in()) {
            if (!headers_sent()) {
                wp_redirect(add_query_arg('page', 'dashboard', get_permalink()));
                exit;
            }
            return '<div class="ar-redirect-notice"><p>Sei già loggato. <a href="?page=dashboard">Vai alla dashboard</a></p></div>';
        }
        
        $atts = shortcode_atts(array(
            'show_login_link' => 'true',
            'auto_approve' => get_option('ar_auto_approve', '0')
        ), $atts);
        
        ob_start();
        ?>
        <div class="area-riservata-register">
            <a href="<?php echo esc_url(home_url()); ?>" class="home-button">
                <span class="dashicons dashicons-arrow-left-alt"></span> Torna alla Home
            </a>
            
            <div class="register-container">
                <div class="register-header">
                    <h2>Registrazione Area Riservata</h2>
                    <p>Compila tutti i campi per richiedere l'accesso</p>
                </div>
                
                <div id="register-message" class="ar-message" style="display: none;"></div>
                
                <form id="area-riservata-register-form" class="register-form" enctype="multipart/form-data" novalidate>
                    <?php wp_nonce_field('area_riservata_nonce', 'nonce'); ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="reg_nome">Nome *</label>
                            <input type="text" id="reg_nome" name="nome" required aria-describedby="nome-error">
                            <div id="nome-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_cognome">Cognome *</label>
                            <input type="text" id="reg_cognome" name="cognome" required aria-describedby="cognome-error">
                            <div id="cognome-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_email">Email *</label>
                            <input type="email" id="reg_email" name="email" required autocomplete="email" aria-describedby="email-error">
                            <div id="email-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_telefono">Telefono *</label>
                            <input type="tel" id="reg_telefono" name="telefono" required autocomplete="tel" aria-describedby="telefono-error">
                            <div id="telefono-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_ragione_sociale">Ragione Sociale *</label>
                            <input type="text" id="reg_ragione_sociale" name="ragione_sociale" required autocomplete="organization" aria-describedby="ragione-sociale-error">
                            <div id="ragione-sociale-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_partita_iva">Partita IVA *</label>
                            <input type="text" id="reg_partita_iva" name="partita_iva" required pattern="[0-9]{11}" aria-describedby="partita-iva-error">
                            <small>Inserisci 11 cifre senza spazi</small>
                            <div id="partita-iva-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_username">Username *</label>
                            <input type="text" id="reg_username" name="username" required autocomplete="username" minlength="3" aria-describedby="username-error">
                            <small>Minimo 3 caratteri, solo lettere, numeri e underscore</small>
                            <div id="username-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="reg_password">Password *</label>
                            <div class="password-field">
                                <input type="password" id="reg_password" name="password" required autocomplete="new-password" minlength="8" aria-describedby="password-error password-strength">
                                <button type="button" class="toggle-password" aria-label="Mostra/nascondi password">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <div id="password-strength" class="password-strength"></div>
                            <small>Minimo 8 caratteri</small>
                            <div id="password-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group single-column">
                            <label for="reg_tipo_utente">Tipo Utente *</label>
                            <select id="reg_tipo_utente" name="tipo_utente" required aria-describedby="tipo-utente-error">
                                <option value="">Seleziona...</option>
                                <option value="progettista">Progettista</option>
                                <option value="rivenditore">Rivenditore</option>
                            </select>
                            <div id="tipo-utente-error" class="field-error" style="display: none;"></div>
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="reg_foto">Foto Profilo (opzionale)</label>
                            <input type="file" id="reg_foto" name="foto_profilo" accept="image/*" aria-describedby="foto-error">
                            <small>Formati supportati: JPG, PNG, GIF (max 2MB)</small>
                            <div id="foto-error" class="field-error" style="display: none;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="privacy_accepted" name="privacy_accepted" required>
                            <span class="checkmark"></span>
                            Accetto l'<a href="/privacy-policy" target="_blank">informativa sulla privacy</a> *
                        </label>
                        <div id="privacy-error" class="field-error" style="display: none;"></div>
                    </div>
                    
                    <button type="submit" class="btn-primary" id="register-submit">
                        <span class="btn-text">Invia Richiesta</span>
                        <span class="btn-spinner" style="display: none;"></span>
                    </button>
                    
                    <?php if ($atts['show_login_link'] === 'true'): ?>
                        <div class="register-links">
                            <a href="?page=login">Hai già un account? Accedi</a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Shortcode Dashboard
     */
    public function shortcode_dashboard($atts) {
        if (!$this->is_user_logged_in()) {
            if (!headers_sent()) {
                wp_redirect(add_query_arg('page', 'login', get_permalink()));
                exit;
            }
            return '<div class="ar-redirect-notice"><p>Devi effettuare il login. <a href="?page=login">Accedi qui</a></p></div>';
        }
        
        $user = $this->get_current_user();
        if (!$user) {
            return '<div class="ar-error"><p>Errore nel caricamento del profilo utente.</p></div>';
        }
        
        $atts = shortcode_atts(array(
            'show_welcome' => 'true',
            'show_profile_edit' => 'true'
        ), $atts);
        
        ob_start();
        ?>
        <div class="area-riservata-dashboard">
            <?php if ($atts['show_welcome'] === 'true'): ?>
                <div class="dashboard-header">
                    <div class="user-welcome">
                        <?php if ($user->foto_profilo): ?>
                            <?php $upload_dir = wp_upload_dir(); ?>
                            <div class="user-avatar">
                                <img src="<?php echo esc_url($upload_dir['baseurl'] . '/area-riservata/' . $user->foto_profilo); ?>" 
                                     alt="Foto profilo" class="avatar">
                            </div>
                        <?php endif; ?>
                        <div class="welcome-text">
                            <h2>Benvenuto, <?php echo esc_html($user->nome . ' ' . $user->cognome); ?></h2>
                            <p>Tipo account: <span class="user-type <?php echo esc_attr($user->tipo_utente); ?>"><?php echo ucfirst($user->tipo_utente); ?></span></p>
                            <p class="company-info"><?php echo esc_html($user->ragione_sociale); ?></p>
                        </div>
                    </div>
                    <div class="dashboard-actions">
                        <?php if ($atts['show_profile_edit'] === 'true'): ?>
                            <button class="btn-secondary" id="edit-profile-btn">
                                <span class="dashicons dashicons-edit"></span> Modifica Profilo
                            </button>
                        <?php endif; ?>
                        <a href="?action=logout" class="btn-logout">
                            <span class="dashicons dashicons-exit"></span> Logout
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="dashboard-content">
                <div class="categories-grid">
                    <div class="category-card" data-category="tende">
                        <div class="category-header">
                            <div class="category-icon">🏠</div>
                            <h3>Tende</h3>
                        </div>
                        <p>Documenti e cataloghi per tende</p>
                        <div class="category-stats">
                            <span class="doc-count" data-category="tende">-</span> documenti
                        </div>
                        <button class="btn-primary view-category" data-category="tende">
                            Visualizza <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                    
                    <div class="category-card" data-category="pergoltende">
                        <div class="category-header">
                            <div class="category-icon">🌿</div>
                            <h3>Pergoltende</h3>
                        </div>
                        <p>Documenti e cataloghi per pergoltende</p>
                        <div class="category-stats">
                            <span class="doc-count" data-category="pergoltende">-</span> documenti
                        </div>
                        <button class="btn-primary view-category" data-category="pergoltende">
                            Visualizza <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                    
                    <div class="category-card" data-category="motori">
                        <div class="category-header">
                            <div class="category-icon">⚙️</div>
                            <h3>Motori</h3>
                        </div>
                        <p>Documenti tecnici per motori</p>
                        <div class="category-stats">
                            <span class="doc-count" data-category="motori">-</span> documenti
                        </div>
                        <button class="btn-primary view-category" data-category="motori">
                            Visualizza <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                    </div>
                </div>
                
                <div id="documents-section" class="documents-section" style="display: none;">
                    <div class="section-header">
                        <button class="btn-back" id="back-to-categories">
                            <span class="dashicons dashicons-arrow-left-alt2"></span> Torna alle categorie
                        </button>
                        <h3 id="section-title">Documenti</h3>
                        <div class="section-actions">
                            <input type="search" id="documents-search" placeholder="Cerca documenti...">
                            <select id="documents-filter">
                                <option value="">Tutte le sottocartelle</option>
                            </select>
                        </div>
                    </div>
                    <div id="documents-grid" class="documents-grid">
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Caricamento documenti...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($atts['show_profile_edit'] === 'true'): ?>
            <?php $this->render_edit_profile_modal($user); ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render modal modifica profilo
     */
    private function render_edit_profile_modal($user) {
        ?>
        <div id="edit-profile-modal" class="ar-modal" style="display: none;">
            <div class="ar-modal-content">
                <div class="ar-modal-header">
                    <h3>Modifica Profilo</h3>
                    <span class="ar-modal-close">&times;</span>
                </div>
                
                <form id="edit-profile-form" enctype="multipart/form-data" novalidate>
                    <?php wp_nonce_field('area_riservata_nonce', 'nonce'); ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="edit_nome">Nome</label>
                            <input type="text" id="edit_nome" name="nome" value="<?php echo esc_attr($user->nome); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_cognome">Cognome</label>
                            <input type="text" id="edit_cognome" name="cognome" value="<?php echo esc_attr($user->cognome); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_email">Email</label>
                            <input type="email" id="edit_email" name="email" value="<?php echo esc_attr($user->email); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_telefono">Telefono</label>
                            <input type="tel" id="edit_telefono" name="telefono" value="<?php echo esc_attr($user->telefono); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_ragione_sociale">Ragione Sociale</label>
                            <input type="text" id="edit_ragione_sociale" name="ragione_sociale" value="<?php echo esc_attr($user->ragione_sociale); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_partita_iva">Partita IVA</label>
                            <input type="text" id="edit_partita_iva" name="partita_iva" value="<?php echo esc_attr($user->partita_iva); ?>" required>
                        </div>
                        <div class="form-group full-width">
                            <label for="edit_foto">Nuova Foto Profilo</label>
                            <input type="file" id="edit_foto" name="foto_profilo" accept="image/*">
                            <small>Lascia vuoto per mantenere la foto attuale</small>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="button ar-modal-close">Annulla</button>
                        <button type="submit" class="btn-primary">
                            <span class="btn-text">Salva Modifiche</span>
                            <span class="btn-spinner" style="display: none;"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    // Metodi di autenticazione
    
    /**
     * Verifica se l'utente è loggato
     */
    private function is_user_logged_in() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $wp_user = wp_get_current_user();
        $area_user_id = get_user_meta($wp_user->ID, $this->user_meta_key, true);
        
        return !empty($area_user_id);
    }
    
    /**
     * Ottiene l'utente corrente dell'area riservata
     */
    private function get_current_user() {
        if (!$this->is_user_logged_in()) {
            return null;
        }
        
        $wp_user = wp_get_current_user();
        $area_user_id = get_user_meta($wp_user->ID, $this->user_meta_key, true);
        
        if (empty($area_user_id)) {
            return null;
        }
        
        return $this->db->get_user($area_user_id);
    }
    
    /**
     * Crea o aggiorna l'utente WordPress collegato
     */
    private function create_or_update_wp_user($area_user) {
        $wp_user = get_user_by('email', $area_user->email);
        
        if (!$wp_user) {
            // Crea nuovo utente WordPress
            $wp_user_id = wp_create_user(
                $area_user->username,
                wp_generate_password(), // Password temporanea
                $area_user->email
            );
            
            if (!is_wp_error($wp_user_id)) {
                update_user_meta($wp_user_id, $this->user_meta_key, $area_user->id);
                update_user_meta($wp_user_id, 'first_name', $area_user->nome);
                update_user_meta($wp_user_id, 'last_name', $area_user->cognome);
                return $wp_user_id;
            }
        } else {
            // Aggiorna utente esistente
            update_user_meta($wp_user->ID, $this->user_meta_key, $area_user->id);
            return $wp_user->ID;
        }
        
        return false;
    }
    
    // AJAX Handlers
    
    /**
     * Login utente
     */
    public function ajax_login_user() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        $username = sanitize_text_field($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            wp_send_json_error(array(
                'message' => 'Username e password sono obbligatori',
                'field_errors' => array(
                    'username' => empty($username) ? 'Username obbligatorio' : '',
                    'password' => empty($password) ? 'Password obbligatoria' : ''
                )
            ));
            return;
        }
        
        global $wpdb;
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}area_riservata_users WHERE username = %s",
            $username
        ));
        
        if (!$user) {
            wp_send_json_error(array(
                'message' => 'Credenziali non valide',
                'field_errors' => array('username' => 'Username non trovato')
            ));
            return;
        }
        
        if ($user->status !== 'approved') {
            $message = $user->status === 'pending' 
                ? 'Account in attesa di approvazione' 
                : 'Account non attivo';
            wp_send_json_error(array('message' => $message));
            return;
        }
        
        if (!wp_check_password($password, $user->password)) {
            wp_send_json_error(array(
                'message' => 'Credenziali non valide',
                'field_errors' => array('password' => 'Password incorretta')
            ));
            return;
        }
        
        // Crea/aggiorna utente WordPress e effettua login
        $wp_user_id = $this->create_or_update_wp_user($user);
        
        if ($wp_user_id) {
            wp_set_current_user($wp_user_id);
            wp_set_auth_cookie($wp_user_id, true);
            
            // Log dell'attività usando query diretta
            $wpdb->insert(
                $wpdb->prefix . 'area_riservata_logs',
                array(
                    'user_id' => $user->id,
                    'azione' => 'Login',
                    'dettagli' => 'Utente ha effettuato il login',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    'created_at' => current_time('mysql')
                )
            );
            
            wp_send_json_success(array(
                'message' => 'Login effettuato con successo',
                'redirect' => 'https://ictende.scherpmind.com/area-riservata/'
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante il login'));
        }
    }
    
    /**
     * Registrazione utente
     */
    public function ajax_register_user() {
        // Debug logging
        error_log('AREA RISERVATA: ajax_register_user chiamato');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verifica nonce
        if (!check_ajax_referer('area_riservata_nonce', 'nonce', false)) {
            error_log('AREA RISERVATA: Nonce non valido');
            wp_send_json_error(array('message' => 'Sicurezza: nonce non valido. Ricarica la pagina.'));
            return;
        }
        
        error_log('AREA RISERVATA: Nonce valido, procedo con validazione');
        
        // Validazione dati
        $required_fields = ['nome', 'cognome', 'email', 'telefono', 'ragione_sociale', 'partita_iva', 'username', 'password', 'tipo_utente'];
        $field_errors = array();
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $field_errors[$field] = 'Campo obbligatorio';
            }
        }
        
        if (empty($_POST['privacy_accepted'])) {
            $field_errors['privacy_accepted'] = 'Devi accettare l\'informativa sulla privacy';
        }
        
        // Validazioni specifiche
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            $field_errors['email'] = 'Formato email non valido';
        } elseif ($this->db->email_exists($email)) {
            $field_errors['email'] = 'Email già registrata';
        }
        
        $username = sanitize_text_field($_POST['username']);
        if (strlen($username) < 3) {
            $field_errors['username'] = 'Username deve essere di almeno 3 caratteri';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $field_errors['username'] = 'Username può contenere solo lettere, numeri e underscore';
        } elseif ($this->db->username_exists($username)) {
            $field_errors['username'] = 'Username già esistente';
        }
        
        $password = $_POST['password'];
        if (strlen($password) < 8) {
            $field_errors['password'] = 'Password deve essere di almeno 8 caratteri';
        }
        
        $partita_iva = sanitize_text_field($_POST['partita_iva']);
        if (!preg_match('/^[0-9]{11}$/', $partita_iva)) {
            $field_errors['partita_iva'] = 'Partita IVA deve contenere 11 cifre';
        }
        
        if (!empty($field_errors)) {
            error_log('AREA RISERVATA: Errori di validazione: ' . print_r($field_errors, true));
            wp_send_json_error(array(
                'message' => 'Correggi gli errori nei campi evidenziati',
                'field_errors' => $field_errors
            ));
            return;
        }
        
        error_log('AREA RISERVATA: Validazione completata, inserimento utente');
        
        // Inserimento utente (senza foto)
        $data = array(
            'nome' => sanitize_text_field($_POST['nome']),
            'cognome' => sanitize_text_field($_POST['cognome']),
            'email' => $email,
            'telefono' => sanitize_text_field($_POST['telefone']),
            'ragione_sociale' => sanitize_text_field($_POST['ragione_sociale']),
            'partita_iva' => $partita_iva,
            'username' => $username,
            'password' => $password, // Verrà hashata nel metodo insert_user
            'tipo_utente' => sanitize_text_field($_POST['tipo_utente']),
            'foto_profilo' => '', // Campo vuoto
            'status' => get_option('ar_auto_approve', '0') === '1' ? 'approved' : 'pending'
        );
        
        $result = $this->db->insert_user($data);
        
        if ($result) {
            error_log('AREA RISERVATA: Utente inserito con successo');
            
            // Log attività
            $this->db->insert_log(0, "Nuova registrazione", "Richiesta registrazione: {$email}");
            
            // Invia email di notifica all'admin se configurata
            $admin_email = get_option('ar_notification_email', get_admin_email());
            if ($admin_email && $data['status'] === 'pending') {
                $this->send_registration_notification($admin_email, $data);
            }
            
            $message = $data['status'] === 'approved' 
                ? 'Registrazione completata! Puoi ora effettuare il login.'
                : 'Registrazione inviata! Attendi l\'approvazione dell\'amministratore.';
            
            wp_send_json_success(array('message' => $message));
        } else {
            error_log('AREA RISERVATA: Errore inserimento utente nel database');
            wp_send_json_error(array('message' => 'Errore durante la registrazione. Riprova.'));
        }
    }
    
    /**
     * Ottiene documenti per categoria
     */
    public function ajax_get_documents() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!$this->is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Non autorizzato'));
            return;
        }
        
        $user = $this->get_current_user();
        $categoria = sanitize_text_field($_POST['category']);
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $sottocartella = isset($_POST['sottocartella']) ? sanitize_text_field($_POST['sottocartella']) : '';
        
        $documents = $this->db->get_documents(array(
            'categoria' => $categoria,
            'destinazione' => $user->tipo_utente,
            'sottocartella' => $sottocartella ?: null
        ));
        
        // Filtro per ricerca
        if (!empty($search)) {
            $documents = array_filter($documents, function($doc) use ($search) {
                return stripos($doc->nome_file, $search) !== false || 
                       stripos($doc->sottocartella, $search) !== false;
            });
        }
        
        $upload_dir = wp_upload_dir();
        $formatted_docs = array();
        $sottocartelle = array();
        
        foreach ($documents as $doc) {
            $formatted_docs[] = array(
                'id' => $doc->id,
                'nome_file' => $doc->nome_file,
                'categoria' => $doc->categoria,
                'sottocartella' => $doc->sottocartella,
                'data_formattata' => date('d/m/Y', strtotime($doc->uploaded_at)),
                'data_timestamp' => strtotime($doc->uploaded_at),
                'dimensione_formattata' => $doc->dimensione ? size_format($doc->dimensione) : '',
                'url' => $upload_dir['baseurl'] . '/area-riservata/' . $doc->path_file
            );
            
            if ($doc->sottocartella && !in_array($doc->sottocartella, $sottocartelle)) {
                $sottocartelle[] = $doc->sottocartella;
            }
        }
        
        // Log dell'attività
        $this->db->insert_log($user->id, "Visualizzazione documenti", "Categoria: {$categoria}");
        
        wp_send_json_success(array(
            'documents' => $formatted_docs,
            'sottocartelle' => $sottocartelle,
            'total' => count($formatted_docs)
        ));
    }
    
    /**
     * Aggiorna profilo utente
     */
    public function ajax_update_profile() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!$this->is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Non autorizzato'));
            return;
        }
        
        $user = $this->get_current_user();
        $field_errors = array();
        
        // Validazione email
        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            $field_errors['email'] = 'Formato email non valido';
        } elseif ($email !== $user->email && $this->db->email_exists($email, $user->id)) {
            $field_errors['email'] = 'Email già utilizzata da un altro utente';
        }
        
        if (!empty($field_errors)) {
            wp_send_json_error(array(
                'message' => 'Correggi gli errori nei campi',
                'field_errors' => $field_errors
            ));
            return;
        }
        
        // Gestione upload nuova foto
        $foto_path = $user->foto_profilo;
        if (!empty($_FILES['foto_profilo']['name'])) {
            $uploaded_file = $this->handle_photo_upload($_FILES['foto_profilo']);
            if (is_wp_error($uploaded_file)) {
                wp_send_json_error(array(
                    'message' => $uploaded_file->get_error_message(),
                    'field_errors' => array('foto_profilo' => $uploaded_file->get_error_message())
                ));
                return;
            }
            
            // Elimina la vecchia foto se esiste
            if ($user->foto_profilo) {
                $upload_dir = wp_upload_dir();
                $old_photo_path = $upload_dir['basedir'] . '/area-riservata/' . $user->foto_profilo;
                if (file_exists($old_photo_path)) {
                    unlink($old_photo_path);
                }
            }
            
            $foto_path = $uploaded_file;
        }
        
        $data = array(
            'nome' => sanitize_text_field($_POST['nome']),
            'cognome' => sanitize_text_field($_POST['cognome']),
            'email' => $email,
            'telefono' => sanitize_text_field($_POST['telefono']),
            'ragione_sociale' => sanitize_text_field($_POST['ragione_sociale']),
            'partita_iva' => sanitize_text_field($_POST['partita_iva']),
            'foto_profilo' => $foto_path
        );
        
        $result = $this->db->update_user($user->id, $data);
        
        if ($result !== false) {
            // Aggiorna anche l'utente WordPress collegato
            $wp_user = wp_get_current_user();
            wp_update_user(array(
                'ID' => $wp_user->ID,
                'first_name' => $data['nome'],
                'last_name' => $data['cognome'],
                'user_email' => $data['email']
            ));
            
            $this->db->insert_log($user->id, "Profilo aggiornato", "Aggiornamento dati profilo");
            wp_send_json_success(array('message' => 'Profilo aggiornato con successo'));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'aggiornamento del profilo'));
        }
    }
    
    /**
     * Logout utente
     */
    public function ajax_logout_user() {
        if ($this->is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            delete_user_meta($wp_user->ID, $this->user_meta_key);
            wp_logout();
        }
        
        wp_send_json_success(array(
            'message' => 'Logout effettuato',
            'redirect' => add_query_arg('page', 'login', get_permalink())
        ));
    }
    
    /**
     * Handle logout via URL
     */
    public function handle_logout() {
        if (isset($_GET['action']) && $_GET['action'] === 'logout') {
            if ($this->is_user_logged_in()) {
                $wp_user = wp_get_current_user();
                delete_user_meta($wp_user->ID, $this->user_meta_key);
                wp_logout();
            }
            wp_redirect(add_query_arg('page', 'login', get_permalink()));
            exit;
        }
    }
    
    // Utility methods
    
    /**
     * Gestisce l'upload delle foto profilo
     */
    private function handle_photo_upload($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'Errore durante il caricamento del file');
        }
        
        $max_size = 2 * 1024 * 1024; // 2MB
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File troppo grande. Massimo 2MB');
        }
        
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!in_array($file['type'], $allowed_types)) {
            return new WP_Error('invalid_file_type', 'Formato file non supportato. Usa JPG, PNG o GIF');
        }
        
        $upload_dir = wp_upload_dir();
        $profiles_dir = $upload_dir['basedir'] . '/area-riservata/profili/';
        
        if (!file_exists($profiles_dir)) {
            wp_mkdir_p($profiles_dir);
        }
        
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . wp_generate_password(8, false) . '.' . $file_extension;
        $filepath = $profiles_dir . $filename;
        $relative_path = 'profili/' . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $relative_path;
        } else {
            return new WP_Error('upload_failed', 'Errore nel salvataggio del file');
        }
    }
    
    /**
     * Invia email di notifica registrazione all'admin
     */
    private function send_registration_notification($admin_email, $user_data) {
        $subject = 'Nuova registrazione Area Riservata - ' . get_bloginfo('name');
        
        $message = "È stata ricevuta una nuova richiesta di registrazione:\n\n";
        $message .= "Nome: {$user_data['nome']} {$user_data['cognome']}\n";
        $message .= "Email: {$user_data['email']}\n";
        $message .= "Telefono: {$user_data['telefono']}\n";
        $message .= "Ragione Sociale: {$user_data['ragione_sociale']}\n";
        $message .= "Partita IVA: {$user_data['partita_iva']}\n";
        $message .= "Tipo Utente: " . ucfirst($user_data['tipo_utente']) . "\n\n";
        $message .= "Per approvare o rifiutare la richiesta, accedi al pannello di amministrazione:\n";
        $message .= admin_url('admin.php?page=area-riservata');
        
        wp_mail($admin_email, $subject, $message);
    }
}