<?php
/**
 * Plugin Name: Area Riservata Pro
 * Description: Sistema completo di area riservata con gestione utenti e documenti
 * Version: 1.0.1
 * Author: Your Name
 */

// Prevenire accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Definire costanti del plugin
define('AREA_RISERVATA_VERSION', '1.0.1');
define('AREA_RISERVATA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AREA_RISERVATA_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Classe principale del plugin
class AreaRiservataPlugin {
    
    private $database;
    private $admin;
    private $frontend;
    
    public function __construct() {
        // Carica le dipendenze
        $this->load_dependencies();
        
        // Inizializza le classi
        $this->database = new AreaRiservata_Database();
        
        // Inizializza admin solo se siamo nell'admin
        if (is_admin()) {
            $this->admin = new AreaRiservata_Admin($this->database);
        }
        
        // Inizializza sempre il frontend
        $this->frontend = new AreaRiservata_Frontend($this->database);
        
        // Hook di attivazione/disattivazione
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Hook di inizializzazione
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Carica i file delle classi
     */
    private function load_dependencies() {
        require_once AREA_RISERVATA_PLUGIN_PATH . 'includes/class-database.php';
        
        if (is_admin()) {
            require_once AREA_RISERVATA_PLUGIN_PATH . 'includes/class-admin.php';
        }
        
        require_once AREA_RISERVATA_PLUGIN_PATH . 'includes/class-frontend.php';
    }
    
    /**
     * Inizializzazione del plugin
     */
    public function init() {
        // Carica textdomain per traduzioni
        load_plugin_textdomain('area-riservata-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Registra le impostazioni
        if (is_admin()) {
            add_action('admin_init', array($this, 'register_settings'));
        }
    }
    
    /**
     * Attivazione plugin
     */
    public function activate() {
        // Crea le tabelle
        $this->database->create_tables();
        
        // Impostazioni predefinite
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log attivazione
        if (method_exists($this->database, 'insert_log')) {
            $this->database->insert_log(0, "Plugin attivato", "Area Riservata Pro è stato attivato");
        }
    }
    
    /**
     * Disattivazione plugin
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Log disattivazione
        if ($this->database && method_exists($this->database, 'insert_log')) {
            $this->database->insert_log(0, "Plugin disattivato", "Area Riservata Pro è stato disattivato");
        }
    }
    
    /**
     * Imposta opzioni predefinite
     */
    private function set_default_options() {
        $default_options = array(
            'ar_auto_approve' => '0',
            'ar_notification_email' => get_admin_email(),
            'ar_max_file_size' => '10',
            'ar_allowed_file_types' => 'pdf,doc,docx,xls,xlsx',
            'ar_require_admin_approval' => '1',
            'ar_enable_photo_upload' => '1'
        );
        
        foreach ($default_options as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
    }
    
    /**
     * Registra le impostazioni del plugin
     */
    public function register_settings() {
        // Registra gruppo impostazioni
        register_setting('area_riservata_settings', 'ar_auto_approve');
        register_setting('area_riservata_settings', 'ar_notification_email');
        register_setting('area_riservata_settings', 'ar_max_file_size');
        register_setting('area_riservata_settings', 'ar_allowed_file_types');
        register_setting('area_riservata_settings', 'ar_require_admin_approval');
        register_setting('area_riservata_settings', 'ar_enable_photo_upload');
        
        // Sezioni e campi impostazioni
        add_settings_section(
            'ar_general_settings',
            'Impostazioni Generali',
            array($this, 'general_settings_callback'),
            'area_riservata_settings'
        );
        
        add_settings_field(
            'ar_auto_approve',
            'Approvazione Automatica',
            array($this, 'auto_approve_field_callback'),
            'area_riservata_settings',
            'ar_general_settings'
        );
        
        add_settings_field(
            'ar_notification_email',
            'Email Notifiche',
            array($this, 'notification_email_field_callback'),
            'area_riservata_settings',
            'ar_general_settings'
        );
        
        add_settings_field(
            'ar_max_file_size',
            'Dimensione Max File (MB)',
            array($this, 'max_file_size_field_callback'),
            'area_riservata_settings',
            'ar_general_settings'
        );
    }
    
    /**
     * Callback per sezione impostazioni generali
     */
    public function general_settings_callback() {
        echo '<p>Configura le impostazioni base del plugin Area Riservata Pro.</p>';
    }
    
    /**
     * Campo approvazione automatica
     */
    public function auto_approve_field_callback() {
        $option = get_option('ar_auto_approve', '0');
        echo '<label><input type="checkbox" name="ar_auto_approve" value="1" ' . checked($option, '1', false) . '> Approva automaticamente i nuovi utenti</label>';
        echo '<p class="description">Se attivo, i nuovi utenti saranno approvati automaticamente senza intervento manuale.</p>';
    }
    
    /**
     * Campo email notifiche
     */
    public function notification_email_field_callback() {
        $option = get_option('ar_notification_email', get_admin_email());
        echo '<input type="email" name="ar_notification_email" value="' . esc_attr($option) . '" class="regular-text">';
        echo '<p class="description">Email per ricevere notifiche di nuove registrazioni.</p>';
    }
    
    /**
     * Campo dimensione max file
     */
    public function max_file_size_field_callback() {
        $option = get_option('ar_max_file_size', '10');
        echo '<input type="number" name="ar_max_file_size" value="' . esc_attr($option) . '" min="1" max="50" class="small-text"> MB';
        echo '<p class="description">Dimensione massima consentita per i file caricati.</p>';
    }
    
    /**
     * Ottiene l'istanza del database
     */
    public function get_database() {
        return $this->database;
    }
    
    /**
     * Ottiene l'istanza admin
     */
    public function get_admin() {
        return $this->admin;
    }
    
    /**
     * Ottiene l'istanza frontend
     */
    public function get_frontend() {
        return $this->frontend;
    }
}

// HANDLER AJAX DIRETTI - SOLUZIONE AL PROBLEMA ERRORE 500

// Handler per registrazione - bypassa le classi
add_action('wp_ajax_ar_register_user', 'area_riservata_direct_register');
add_action('wp_ajax_nopriv_ar_register_user', 'area_riservata_direct_register');

function area_riservata_direct_register() {
    // Previeni output
    if (ob_get_length()) ob_clean();
    
    error_log('AREA RISERVATA: Handler diretto chiamato');
    
    try {
        // Verifica base
        if (empty($_POST['nome']) || empty($_POST['email'])) {
            wp_send_json_error(array(
                'message' => 'Nome e email sono obbligatori per la registrazione'
            ));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'area_riservata_users';
        
        // Crea tabella se non esiste
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                nome varchar(100) NOT NULL,
                cognome varchar(100) NOT NULL,
                email varchar(100) NOT NULL,
                telefono varchar(20) NOT NULL,
                ragione_sociale varchar(200) NOT NULL,
                partita_iva varchar(20) NOT NULL,
                username varchar(50) NOT NULL,
                password varchar(255) NOT NULL,
                tipo_utente enum('progettista', 'rivenditore') NOT NULL,
                foto_profilo varchar(255),
                status enum('pending', 'approved', 'rejected', 'disabled') DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            error_log('AREA RISERVATA: Tabella creata con handler diretto');
        }
        
        // Prepara dati con valori di default per campi mancanti
        $nome = sanitize_text_field($_POST['nome']);
        $cognome = sanitize_text_field($_POST['cognome'] ?? '');
        $email = sanitize_email($_POST['email']);
        $telefono = sanitize_text_field($_POST['telefono'] ?? '');
        $ragione_sociale = sanitize_text_field($_POST['ragione_sociale'] ?? '');
        $partita_iva = sanitize_text_field($_POST['partita_iva'] ?? '');
        $username = sanitize_text_field($_POST['username'] ?? $email); // Usa email come fallback
        $password = isset($_POST['password']) ? wp_hash_password($_POST['password']) : wp_hash_password('temp123');
        $tipo_utente = sanitize_text_field($_POST['tipo_utente'] ?? 'progettista');
        
        // Verifica se email o username esistono già
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE email = %s OR username = %s",
            $email, $username
        ));
        
        if ($existing) {
            wp_send_json_error(array(
                'message' => 'Email o username già registrati. Prova con dati diversi.'
            ));
            return;
        }
        
        // Inserimento con gestione errori
        $result = $wpdb->insert(
            $table_name,
            array(
                'nome' => $nome,
                'cognome' => $cognome,
                'email' => $email,
                'telefono' => $telefono,
                'ragione_sociale' => $ragione_sociale,
                'partita_iva' => $partita_iva,
                'username' => $username,
                'password' => $password,
                'tipo_utente' => $tipo_utente,
                'foto_profilo' => '',
                'status' => 'pending',
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            error_log('AREA RISERVATA: Errore inserimento DB: ' . $wpdb->last_error);
            wp_send_json_error(array(
                'message' => 'Errore durante la registrazione. Riprova più tardi.'
            ));
            return;
        }
        
        $user_id = $wpdb->insert_id;
        error_log('AREA RISERVATA: Utente registrato con ID: ' . $user_id);
        
        // Crea tabella logs se non esiste
        $logs_table = $wpdb->prefix . 'area_riservata_logs';
        $logs_exists = $wpdb->get_var("SHOW TABLES LIKE '$logs_table'");
        if (!$logs_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql_logs = "CREATE TABLE $logs_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                user_id mediumint(9),
                azione varchar(100) NOT NULL,
                dettagli text,
                ip_address varchar(45),
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";
            dbDelta($sql_logs);
        }
        
        // Log attività
        $wpdb->insert($logs_table, array(
            'user_id' => $user_id,
            'azione' => 'Registrazione',
            'dettagli' => 'Nuova registrazione: ' . $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'created_at' => current_time('mysql')
        ));
        
        // Invia email all'admin
        $admin_email = 'info@scherpmind.com'; // Email fissa per le notifiche
        $site_name = get_bloginfo('name');
        
        $subject = 'Nuova registrazione Area Riservata - ' . $site_name;
        $message = "È stata ricevuta una nuova richiesta di registrazione per l'Area Riservata:\n\n";
        $message .= "===== DETTAGLI UTENTE =====\n";
        $message .= "Nome: $nome $cognome\n";
        $message .= "Email: $email\n";
        $message .= "Telefono: $telefono\n";
        $message .= "Ragione Sociale: $ragione_sociale\n";
        $message .= "Partita IVA: $partita_iva\n";
        $message .= "Username: $username\n";
        $message .= "Tipo Utente: " . ucfirst($tipo_utente) . "\n";
        $message .= "Data Registrazione: " . current_time('d/m/Y H:i') . "\n\n";
        $message .= "===== AZIONI RICHIESTE =====\n";
        $message .= "Per approvare o rifiutare questa richiesta, accedi al pannello di amministrazione:\n";
        $message .= admin_url('admin.php?page=area-riservata') . "\n\n";
        $message .= "Una volta approvato, l'utente riceverà automaticamente un'email di conferma con le istruzioni per accedere.\n\n";
        $message .= "---\n";
        $message .= "Messaggio automatico da " . get_site_url();
        
        // Header personalizzati per migliorare la deliverability
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <noreply@ictende.com>',
            'Reply-To: info@scherpmind.com'
        );
        
        $email_sent = wp_mail($admin_email, $subject, $message, $headers);
        
        // Risposta di successo
        wp_send_json_success(array(
            'message' => 'Registrazione completata con successo!

La tua richiesta è stata inviata e verrà esaminata dall\'amministratore.

Ti invieremo un\'email di conferma quando il tuo account sarà attivato e potrai accedere all\'area riservata.

Grazie per la registrazione!'
        ));
        
    } catch (Exception $e) {
        error_log('AREA RISERVATA: Errore handler diretto: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'Errore interno del server. Riprova più tardi.'
        ));
    }
}

// Handler per login diretto
add_action('wp_ajax_ar_login_user', 'area_riservata_direct_login');
add_action('wp_ajax_nopriv_ar_login_user', 'area_riservata_direct_login');

function area_riservata_direct_login() {
    if (ob_get_length()) ob_clean();
    
    try {
        $username = sanitize_text_field($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            wp_send_json_error(array('message' => 'Username e password sono obbligatori'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'area_riservata_users';
        
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE username = %s OR email = %s",
            $username, $username
        ));
        
        if (!$user) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
            return;
        }
        
        if ($user->status !== 'approved') {
            wp_send_json_error(array('message' => 'Account non ancora approvato dall\'amministratore'));
            return;
        }
        
        if (!wp_check_password($password, $user->password)) {
            wp_send_json_error(array('message' => 'Password incorretta'));
            return;
        }
        
        // Login WordPress
        $wp_user = get_user_by('email', $user->email);
        if (!$wp_user) {
            $wp_user_id = wp_create_user($user->username, $password, $user->email);
            if (!is_wp_error($wp_user_id)) {
                update_user_meta($wp_user_id, 'area_riservata_user_id', $user->id);
                wp_set_current_user($wp_user_id);
                wp_set_auth_cookie($wp_user_id, true);
            }
        } else {
            update_user_meta($wp_user->ID, 'area_riservata_user_id', $user->id);
            wp_set_current_user($wp_user->ID);
            wp_set_auth_cookie($wp_user->ID, true);
        }
        
        // Log login
        $logs_table = $wpdb->prefix . 'area_riservata_logs';
        $wpdb->insert($logs_table, array(
            'user_id' => $user->id,
            'azione' => 'Login',
            'dettagli' => 'Login effettuato',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'created_at' => current_time('mysql')
        ));
        
        wp_send_json_success(array(
            'message' => 'Login effettuato con successo',
            'redirect' => 'https://ictende.scherpmind.com/area-riservata/'
        ));
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Errore login: ' . $e->getMessage()));
    }
}

// Handler per approvazione utenti dall'admin
add_action('wp_ajax_ar_approve_user_direct', 'area_riservata_direct_approve');

function area_riservata_direct_approve() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Non autorizzato'));
        return;
    }
    
    try {
        $user_id = intval($_POST['user_id']);
        $action = sanitize_text_field($_POST['action_type']);
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'area_riservata_users';
        
        // Ottieni dati utente
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d", $user_id
        ));
        
        if (!$user) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
            return;
        }
        
        // Aggiorna status
        $result = $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $user_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false && $status === 'approved') {
            // Invia email di approvazione
            $site_name = get_bloginfo('name');
            $login_url = 'https://ictende.scherpmind.com/login/';
            
            $subject = 'Account Approvato - ' . $site_name;
            $message = "Ciao {$user->nome} {$user->cognome},\n\n";
            $message .= "Il tuo account per l'Area Riservata di {$site_name} è stato approvato!\n\n";
            $message .= "Ora puoi accedere utilizzando le tue credenziali:\n";
            $message .= "Username: {$user->username}\n";
            $message .= "Email: {$user->email}\n\n";
            $message .= "ACCEDI QUI: {$login_url}\n\n";
            $message .= "Grazie per aver scelto {$site_name}!";
            
            wp_mail($user->email, $subject, $message);
        }
        
        wp_send_json_success(array(
            'message' => 'Utente ' . ($action === 'approve' ? 'approvato' : 'rifiutato') . ' con successo'
        ));
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => 'Errore: ' . $e->getMessage()));
    }
}

// Inizializza il plugin
function area_riservata_init() {
    global $area_riservata_plugin;
    $area_riservata_plugin = new AreaRiservataPlugin();
}

// Hook di inizializzazione
add_action('plugins_loaded', 'area_riservata_init');

/**
 * Funzione helper per ottenere l'istanza del plugin
 */
function area_riservata() {
    global $area_riservata_plugin;
    return $area_riservata_plugin;
}

/**
 * Hook di disinstallazione
 */
register_uninstall_hook(__FILE__, 'area_riservata_uninstall');

function area_riservata_uninstall() {
    // Elimina le opzioni
    delete_option('ar_auto_approve');
    delete_option('ar_notification_email');
    delete_option('ar_max_file_size');
    delete_option('ar_allowed_file_types');
    delete_option('ar_require_admin_approval');
    delete_option('ar_enable_photo_upload');
}

/**
 * Aggiunge link alle impostazioni nella pagina dei plugin
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'area_riservata_add_settings_link');

function area_riservata_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=area-riservata">Impostazioni</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Hook per aggiornamenti del database
 */
add_action('plugins_loaded', 'area_riservata_update_db_check');

function area_riservata_update_db_check() {
    $current_version = get_option('area_riservata_db_version', '1.0');
    
    if (version_compare($current_version, AREA_RISERVATA_VERSION, '<')) {
        // Esegui aggiornamenti database se necessario
        if (class_exists('AreaRiservata_Database')) {
            $database = new AreaRiservata_Database();
            $database->create_tables();
        }
        
        update_option('area_riservata_db_version', AREA_RISERVATA_VERSION);
    }
}