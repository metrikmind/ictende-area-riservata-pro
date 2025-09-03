<?php
/**
 * Gestione Database per Area Riservata Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class AreaRiservata_Database {
    
    private $table_users;
    private $table_documents;
    private $table_logs;
    
    public function __construct() {
        global $wpdb;
        $this->table_users = $wpdb->prefix . 'area_riservata_users';
        $this->table_documents = $wpdb->prefix . 'area_riservata_documents';
        $this->table_logs = $wpdb->prefix . 'area_riservata_logs';
    }
    
    /**
     * Crea le tabelle del plugin
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabella utenti
        $sql_users = "CREATE TABLE {$this->table_users} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nome varchar(100) NOT NULL,
            cognome varchar(100) NOT NULL,
            email varchar(100) NOT NULL UNIQUE,
            telefono varchar(20) NOT NULL,
            ragione_sociale varchar(200) NOT NULL,
            partita_iva varchar(20) NOT NULL,
            username varchar(50) NOT NULL UNIQUE,
            password varchar(255) NOT NULL,
            tipo_utente enum('progettista', 'rivenditore') NOT NULL,
            foto_profilo varchar(255),
            status enum('pending', 'approved', 'rejected', 'disabled') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_email (email),
            KEY idx_username (username),
            KEY idx_status (status)
        ) $charset_collate;";
        
        // Tabella documenti
        $sql_docs = "CREATE TABLE {$this->table_documents} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nome_file varchar(255) NOT NULL,
            path_file varchar(500) NOT NULL,
            categoria enum('tende', 'pergoltende', 'motori') NOT NULL,
            destinazione enum('progettista', 'rivenditore', 'entrambi') NOT NULL,
            sottocartella varchar(100),
            dimensione int(11),
            uploaded_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_categoria (categoria),
            KEY idx_destinazione (destinazione)
        ) $charset_collate;";
        
        // Tabella log
        $sql_logs = "CREATE TABLE {$this->table_logs} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9),
            azione varchar(100) NOT NULL,
            dettagli text,
            ip_address varchar(45),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_users);
        dbDelta($sql_docs);
        dbDelta($sql_logs);
    }
    
    /**
     * Ottiene un utente per ID
     */
    public function get_user($user_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_users} WHERE id = %d",
            $user_id
        ));
    }
    
    /**
     * Ottiene un utente per username
     */
    public function get_user_by_username($username) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_users} WHERE username = %s",
            $username
        ));
    }
    
    /**
     * Ottiene un utente per email
     */
    public function get_user_by_email($email) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_users} WHERE email = %s",
            $email
        ));
    }
    
    /**
     * Inserisce un nuovo utente
     */
    public function insert_user($data) {
        global $wpdb;
        
        // Debug: verifica che la tabella esista
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_users}'");
        if (!$table_exists) {
            error_log('AREA RISERVATA: Tabella utenti non esiste, la creo');
            $this->create_tables();
        }
        
        $defaults = array(
            'status' => 'pending',
            'created_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Sanitizza i dati
        $clean_data = array();
        foreach ($data as $key => $value) {
            if ($key === 'email') {
                $clean_data[$key] = sanitize_email($value);
            } elseif ($key === 'password') {
                $clean_data[$key] = wp_hash_password($value);
            } else {
                $clean_data[$key] = sanitize_text_field($value);
            }
        }
        
        error_log('AREA RISERVATA: Tentativo inserimento utente con dati: ' . print_r($clean_data, true));
        
        $result = $wpdb->insert($this->table_users, $clean_data);
        
        if ($result === false) {
            error_log('AREA RISERVATA: Errore inserimento DB: ' . $wpdb->last_error);
            return false;
        }
        
        error_log('AREA RISERVATA: Utente inserito con ID: ' . $wpdb->insert_id);
        return $result;
    }
    
    /**
     * Aggiorna un utente
     */
    public function update_user($user_id, $data) {
        global $wpdb;
        
        // Sanitizza i dati
        $data = array_map('sanitize_text_field', $data);
        if (isset($data['email'])) {
            $data['email'] = sanitize_email($data['email']);
        }
        if (isset($data['password'])) {
            $data['password'] = wp_hash_password($data['password']);
        }
        
        return $wpdb->update(
            $this->table_users,
            $data,
            array('id' => $user_id),
            null,
            array('%d')
        );
    }
    
    /**
     * Ottiene tutti gli utenti con filtri
     */
    public function get_users($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => null,
            'tipo_utente' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $prepare_args = array();
        
        if ($args['status']) {
            $where[] = 'status = %s';
            $prepare_args[] = $args['status'];
        }
        
        if ($args['tipo_utente']) {
            $where[] = 'tipo_utente = %s';
            $prepare_args[] = $args['tipo_utente'];
        }
        
        $sql = "SELECT * FROM {$this->table_users} WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        if ($args['limit']) {
            $sql .= " LIMIT %d OFFSET %d";
            $prepare_args[] = $args['limit'];
            $prepare_args[] = $args['offset'];
        }
        
        if (!empty($prepare_args)) {
            $sql = $wpdb->prepare($sql, $prepare_args);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Ottiene statistiche utenti
     */
    public function get_user_stats() {
        global $wpdb;
        
        $stats = array();
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_users}");
        $stats['approved'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_users} WHERE status = 'approved'");
        $stats['pending'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_users} WHERE status = 'pending'");
        $stats['disabled'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_users} WHERE status = 'disabled'");
        $stats['progettisti'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_users} WHERE tipo_utente = 'progettista' AND status = 'approved'");
        $stats['rivenditori'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_users} WHERE tipo_utente = 'rivenditore' AND status = 'approved'");
        
        return $stats;
    }
    
    /**
     * Inserisce un documento
     */
    public function insert_document($data) {
        global $wpdb;
        
        $defaults = array(
            'uploaded_at' => current_time('mysql')
        );
        
        $data = wp_parse_args($data, $defaults);
        
        return $wpdb->insert($this->table_documents, $data);
    }
    
    /**
     * Ottiene documenti con filtri
     */
    public function get_documents($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'categoria' => null,
            'destinazione' => null,
            'sottocartella' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'uploaded_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $prepare_args = array();
        
        if ($args['categoria']) {
            $where[] = 'categoria = %s';
            $prepare_args[] = $args['categoria'];
        }
        
        if ($args['destinazione']) {
            $where[] = "(destinazione = %s OR destinazione = 'entrambi')";
            $prepare_args[] = $args['destinazione'];
        }
        
        if ($args['sottocartella']) {
            $where[] = 'sottocartella = %s';
            $prepare_args[] = $args['sottocartella'];
        }
        
        $sql = "SELECT * FROM {$this->table_documents} WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY {$args['orderby']} {$args['order']}";
        
        if ($args['limit']) {
            $sql .= " LIMIT %d OFFSET %d";
            $prepare_args[] = $args['limit'];
            $prepare_args[] = $args['offset'];
        }
        
        if (!empty($prepare_args)) {
            $sql = $wpdb->prepare($sql, $prepare_args);
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Ottiene un documento per ID
     */
    public function get_document($doc_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_documents} WHERE id = %d",
            $doc_id
        ));
    }
    
    /**
     * Elimina un documento
     */
    public function delete_document($doc_id) {
        global $wpdb;
        return $wpdb->delete(
            $this->table_documents,
            array('id' => $doc_id),
            array('%d')
        );
    }
    
    /**
     * Ottiene statistiche documenti
     */
    public function get_document_stats() {
        global $wpdb;
        
        $stats = array();
        $stats['total'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_documents}");
        $stats['tende'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_documents} WHERE categoria = 'tende'");
        $stats['pergoltende'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_documents} WHERE categoria = 'pergoltende'");
        $stats['motori'] = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_documents} WHERE categoria = 'motori'");
        
        return $stats;
    }
    
    /**
     * Inserisce un log di attività
     */
    public function insert_log($user_id, $azione, $dettagli = '', $ip_address = '') {
        global $wpdb;
        
        if (empty($ip_address)) {
            $ip_address = $this->get_client_ip();
        }
        
        return $wpdb->insert(
            $this->table_logs,
            array(
                'user_id' => $user_id,
                'azione' => sanitize_text_field($azione),
                'dettagli' => sanitize_textarea_field($dettagli),
                'ip_address' => $ip_address,
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Ottiene i log più recenti
     */
    public function get_recent_logs($limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.nome, u.cognome, u.email 
             FROM {$this->table_logs} l 
             LEFT JOIN {$this->table_users} u ON l.user_id = u.id 
             ORDER BY l.created_at DESC 
             LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Ottiene i log per un utente specifico
     */
    public function get_user_logs($user_id, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_logs} 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
    }
    
    /**
     * Pulisce i log vecchi (oltre X giorni)
     */
    public function cleanup_old_logs($days = 90) {
        global $wpdb;
        
        return $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_logs} 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * Ottiene IP del client
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
    
    /**
     * Verifica se username esiste
     */
    public function username_exists($username, $exclude_id = null) {
        global $wpdb;
        
        $sql = "SELECT id FROM {$this->table_users} WHERE username = %s";
        $args = array($username);
        
        if ($exclude_id) {
            $sql .= " AND id != %d";
            $args[] = $exclude_id;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare($sql, $args));
    }
    
    /**
     * Verifica se email esiste
     */
    public function email_exists($email, $exclude_id = null) {
        global $wpdb;
        
        $sql = "SELECT id FROM {$this->table_users} WHERE email = %s";
        $args = array($email);
        
        if ($exclude_id) {
            $sql .= " AND id != %d";
            $args[] = $exclude_id;
        }
        
        return (bool) $wpdb->get_var($wpdb->prepare($sql, $args));
    }
    
    /**
     * Elimina le tabelle del plugin
     */
    public function drop_tables() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_logs}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_documents}");
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_users}");
    }
}