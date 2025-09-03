<?php
/**
 * Gestione Backend/Admin per Area Riservata Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

class AreaRiservata_Admin {
    
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
        $this->init();
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_ar_approve_user', array($this, 'ajax_approve_user'));
        add_action('wp_ajax_ar_toggle_user_status', array($this, 'ajax_toggle_user_status'));
        add_action('wp_ajax_ar_add_manual_user', array($this, 'ajax_add_manual_user'));
        add_action('wp_ajax_ar_upload_document', array($this, 'ajax_upload_document'));
        add_action('wp_ajax_ar_delete_document', array($this, 'ajax_delete_document'));
        add_action('wp_ajax_ar_get_stats', array($this, 'ajax_get_stats'));
        add_action('wp_ajax_ar_delete_user', array($this, 'ajax_delete_user'));
    }
    
    /**
     * Aggiunge menu admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'Area Riservata',
            'Area Riservata',
            'manage_options',
            'area-riservata',
            array($this, 'admin_page'),
            'dashicons-lock',
            30
        );
        
        // Sottomenu
        add_submenu_page(
            'area-riservata',
            'Impostazioni',
            'Impostazioni',
            'manage_options',
            'area-riservata-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue scripts e stili admin
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'area-riservata') === false) {
            return;
        }
        
        wp_enqueue_script(
            'area-riservata-admin',
            AREA_RISERVATA_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            AREA_RISERVATA_VERSION,
            true
        );
        
        wp_enqueue_style(
            'area-riservata-admin',
            AREA_RISERVATA_PLUGIN_URL . 'assets/admin.css',
            array(),
            AREA_RISERVATA_VERSION
        );
        
        wp_localize_script('area-riservata-admin', 'areaRiservataAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('area_riservata_nonce'),
            'confirm_delete' => __('Sei sicuro di voler eliminare questo elemento?'),
            'confirm_approve' => __('Confermi l\'approvazione di questo utente?')
        ));
    }
    
    /**
     * Pagina admin principale
     */
    public function admin_page() {
        ?>
        <div class="wrap area-riservata-admin">
            <h1>Area Riservata Pro</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="#panoramica" class="nav-tab nav-tab-active" data-tab="panoramica">Panoramica</a>
                <a href="#utenti" class="nav-tab" data-tab="utenti">Utenti</a>
                <a href="#documenti" class="nav-tab" data-tab="documenti">Documenti</a>
                <a href="#logs" class="nav-tab" data-tab="logs">Log Attività</a>
            </nav>
            
            <div id="panoramica" class="tab-content active">
                <?php $this->render_overview_tab(); ?>
            </div>
            
            <div id="utenti" class="tab-content">
                <?php $this->render_users_tab(); ?>
            </div>
            
            <div id="documenti" class="tab-content">
                <?php $this->render_documents_tab(); ?>
            </div>
            
            <div id="logs" class="tab-content">
                <?php $this->render_logs_tab(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Tab Panoramica
     */
    private function render_overview_tab() {
        $user_stats = $this->db->get_user_stats();
        $doc_stats = $this->db->get_document_stats();
        $recent_logs = $this->db->get_recent_logs(10);
        ?>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-icon">👥</div>
                <div class="stat-content">
                    <h3><?php echo esc_html($user_stats['approved']); ?></h3>
                    <p>Utenti Attivi</p>
                </div>
            </div>
            
            <div class="stat-box pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-content">
                    <h3><?php echo esc_html($user_stats['pending']); ?></h3>
                    <p>In Attesa di Approvazione</p>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon">📄</div>
                <div class="stat-content">
                    <h3><?php echo esc_html($doc_stats['total']); ?></h3>
                    <p>Documenti Caricati</p>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon">🏗️</div>
                <div class="stat-content">
                    <h3><?php echo esc_html($user_stats['progettisti']); ?></h3>
                    <p>Progettisti</p>
                </div>
            </div>
            
            <div class="stat-box">
                <div class="stat-icon">🏪</div>
                <div class="stat-content">
                    <h3><?php echo esc_html($user_stats['rivenditori']); ?></h3>
                    <p>Rivenditori</p>
                </div>
            </div>
        </div>
        
        <div class="quick-actions">
            <h3>Azioni Rapide</h3>
            <div class="actions-grid">
                <button class="action-btn" id="quick-add-user">
                    <span class="dashicons dashicons-plus"></span>
                    Aggiungi Utente
                </button>
                <button class="action-btn" id="quick-upload-doc">
                    <span class="dashicons dashicons-upload"></span>
                    Carica Documento
                </button>
                <button class="action-btn" id="export-users">
                    <span class="dashicons dashicons-download"></span>
                    Esporta Utenti
                </button>
            </div>
        </div>
        
        <div class="recent-activity">
            <h3>Attività Recenti</h3>
            <?php if ($recent_logs): ?>
                <div class="activity-list">
                    <?php foreach ($recent_logs as $log): ?>
                        <div class="activity-item">
                            <div class="activity-time">
                                <?php echo esc_html(human_time_diff(strtotime($log->created_at))) . ' fa'; ?>
                            </div>
                            <div class="activity-content">
                                <strong><?php echo esc_html($log->azione); ?></strong>
                                <?php if ($log->nome && $log->cognome): ?>
                                    - <?php echo esc_html($log->nome . ' ' . $log->cognome); ?>
                                <?php endif; ?>
                                <?php if ($log->dettagli): ?>
                                    <br><small><?php echo esc_html($log->dettagli); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Nessuna attività registrata.</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Tab Utenti
     */
    private function render_users_tab() {
        $users = $this->db->get_users();
        ?>
        <div class="users-section">
            <div class="section-header">
                <h3>Gestione Utenti</h3>
                <div class="header-actions">
                    <select id="filter-status">
                        <option value="">Tutti gli status</option>
                        <option value="pending">In attesa</option>
                        <option value="approved">Approvati</option>
                        <option value="disabled">Disabilitati</option>
                        <option value="rejected">Rifiutati</option>
                    </select>
                    <select id="filter-type">
                        <option value="">Tutti i tipi</option>
                        <option value="progettista">Progettisti</option>
                        <option value="rivenditore">Rivenditori</option>
                    </select>
                    <button class="button button-primary" id="add-user-btn">Aggiungi Utente</button>
                </div>
            </div>
            
            <div class="users-table">
                <?php if ($users): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Tipo</th>
                                <th>Status</th>
                                <th>Data Registrazione</th>
                                <th>Ultimo Accesso</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <?php foreach ($users as $user): ?>
                                <tr data-user-id="<?php echo esc_attr($user->id); ?>" 
                                    data-status="<?php echo esc_attr($user->status); ?>" 
                                    data-type="<?php echo esc_attr($user->tipo_utente); ?>">
                                    <td>
                                        <strong><?php echo esc_html($user->nome . ' ' . $user->cognome); ?></strong>
                                        <br><small><?php echo esc_html($user->ragione_sociale); ?></small>
                                    </td>
                                    <td><?php echo esc_html($user->email); ?></td>
                                    <td>
                                        <span class="user-type <?php echo esc_attr($user->tipo_utente); ?>">
                                            <?php echo ucfirst($user->tipo_utente); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo esc_attr($user->status); ?>">
                                            <?php echo ucfirst($user->status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(date('d/m/Y', strtotime($user->created_at))); ?></td>
                                    <td>
                                        <?php
                                        $last_login = $this->get_user_last_login($user->id);
                                        echo $last_login ? esc_html(human_time_diff(strtotime($last_login))) . ' fa' : 'Mai';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <?php if ($user->status == 'pending'): ?>
                                                <button class="button button-small button-primary approve-user" 
                                                        data-user-id="<?php echo esc_attr($user->id); ?>">
                                                    Approva
                                                </button>
                                                <button class="button button-small reject-user" 
                                                        data-user-id="<?php echo esc_attr($user->id); ?>">
                                                    Rifiuta
                                                </button>
                                            <?php else: ?>
                                                <button class="button button-small toggle-status" 
                                                        data-user-id="<?php echo esc_attr($user->id); ?>" 
                                                        data-current-status="<?php echo esc_attr($user->status); ?>">
                                                    <?php echo $user->status == 'approved' ? 'Disabilita' : 'Abilita'; ?>
                                                </button>
                                            <?php endif; ?>
                                            <button class="button button-small view-user" 
                                                    data-user-id="<?php echo esc_attr($user->id); ?>">
                                                Visualizza
                                            </button>
                                            <button class="button button-small button-link-delete delete-user" 
                                                    data-user-id="<?php echo esc_attr($user->id); ?>">
                                                Elimina
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>Nessun utente registrato.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php $this->render_add_user_modal(); ?>
        <?php $this->render_view_user_modal(); ?>
        <?php
    }
    
    /**
     * Tab Documenti
     */
    private function render_documents_tab() {
        $documents = $this->db->get_documents();
        $upload_dir = wp_upload_dir();
        ?>
        <div class="documents-section">
            <div class="section-header">
                <h3>Gestione Documenti</h3>
                <div class="header-actions">
                    <select id="filter-category">
                        <option value="">Tutte le categorie</option>
                        <option value="tende">Tende</option>
                        <option value="pergoltende">Pergoltende</option>
                        <option value="motori">Motori</option>
                    </select>
                    <select id="filter-destination">
                        <option value="">Tutte le destinazioni</option>
                        <option value="progettista">Solo Progettisti</option>
                        <option value="rivenditore">Solo Rivenditori</option>
                        <option value="entrambi">Entrambi</option>
                    </select>
                    <button class="button button-primary" id="upload-doc-btn">Carica Documento</button>
                </div>
            </div>
            
            <div class="documents-grid">
                <?php if ($documents): ?>
                    <?php foreach ($documents as $doc): ?>
                        <div class="document-card" data-category="<?php echo esc_attr($doc->categoria); ?>" 
                             data-destination="<?php echo esc_attr($doc->destinazione); ?>">
                            <div class="doc-header">
                                <div class="doc-icon">📄</div>
                                <div class="doc-actions">
                                    <button class="button button-small delete-doc" 
                                            data-doc-id="<?php echo esc_attr($doc->id); ?>"
                                            title="Elimina documento">
                                        <span class="dashicons dashicons-trash"></span>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="doc-info">
                                <h4><?php echo esc_html($doc->nome_file); ?></h4>
                                <div class="doc-meta">
                                    <p><strong>Categoria:</strong> <?php echo ucfirst($doc->categoria); ?></p>
                                    <p><strong>Destinazione:</strong> <?php echo ucfirst($doc->destinazione); ?></p>
                                    <?php if ($doc->sottocartella): ?>
                                        <p><strong>Sottocartella:</strong> <?php echo esc_html($doc->sottocartella); ?></p>
                                    <?php endif; ?>
                                    <p><strong>Data:</strong> <?php echo date('d/m/Y H:i', strtotime($doc->uploaded_at)); ?></p>
                                    <?php if ($doc->dimensione): ?>
                                        <p><strong>Dimensione:</strong> <?php echo size_format($doc->dimensione); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="doc-footer">
                                <a href="<?php echo esc_url($upload_dir['baseurl'] . '/area-riservata/' . $doc->path_file); ?>" 
                                   target="_blank" 
                                   class="button button-small button-primary">
                                    Visualizza
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>Nessun documento caricato.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php $this->render_upload_doc_modal(); ?>
        <?php
    }
    
    /**
     * Tab Log Attività
     */
    private function render_logs_tab() {
        $logs = $this->db->get_recent_logs(100);
        ?>
        <div class="logs-section">
            <div class="section-header">
                <h3>Log delle Attività</h3>
                <div class="header-actions">
                    <input type="date" id="log-date-from" placeholder="Data da">
                    <input type="date" id="log-date-to" placeholder="Data a">
                    <select id="log-action-filter">
                        <option value="">Tutte le azioni</option>
                        <option value="Login">Login</option>
                        <option value="Logout">Logout</option>
                        <option value="Registrazione">Registrazione</option>
                        <option value="Visualizzazione documenti">Visualizzazione documenti</option>
                        <option value="Documento caricato">Documento caricato</option>
                        <option value="Utente approvato">Utente approvato</option>
                    </select>
                    <button class="button" id="clear-logs">Pulisci Log Vecchi</button>
                </div>
            </div>
            
            <?php if ($logs): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Data/Ora</th>
                            <th>Utente</th>
                            <th>Azione</th>
                            <th>Dettagli</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody id="logs-table-body">
                        <?php foreach ($logs as $log): ?>
                            <tr data-action="<?php echo esc_attr($log->azione); ?>" 
                                data-date="<?php echo esc_attr(date('Y-m-d', strtotime($log->created_at))); ?>">
                                <td><?php echo esc_html(date('d/m/Y H:i:s', strtotime($log->created_at))); ?></td>
                                <td>
                                    <?php if ($log->nome && $log->cognome): ?>
                                        <?php echo esc_html($log->nome . ' ' . $log->cognome); ?>
                                        <br><small><?php echo esc_html($log->email); ?></small>
                                    <?php elseif ($log->user_id): ?>
                                        ID: <?php echo esc_html($log->user_id); ?>
                                    <?php else: ?>
                                        Sistema
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="action-badge"><?php echo esc_html($log->azione); ?></span>
                                </td>
                                <td><?php echo esc_html($log->dettagli); ?></td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Nessun log disponibile.</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Modal Aggiungi Utente
     */
    private function render_add_user_modal() {
        ?>
        <div id="add-user-modal" class="ar-modal" style="display: none;">
            <div class="ar-modal-content">
                <div class="ar-modal-header">
                    <h3>Aggiungi Nuovo Utente</h3>
                    <span class="ar-modal-close">&times;</span>
                </div>
                <form id="add-user-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nome *</label>
                            <input type="text" name="nome" required>
                        </div>
                        <div class="form-group">
                            <label>Cognome *</label>
                            <input type="text" name="cognome" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Telefono *</label>
                            <input type="text" name="telefono" required>
                        </div>
                        <div class="form-group">
                            <label>Ragione Sociale *</label>
                            <input type="text" name="ragione_sociale" required>
                        </div>
                        <div class="form-group">
                            <label>Partita IVA *</label>
                            <input type="text" name="partita_iva" required>
                        </div>
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" required>
                            <div class="password-strength"></div>
                        </div>
                        <div class="form-group">
                            <label>Tipo Utente *</label>
                            <select name="tipo_utente" required>
                                <option value="">Seleziona...</option>
                                <option value="progettista">Progettista</option>
                                <option value="rivenditore">Rivenditore</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="approved">Approvato</option>
                                <option value="pending">In attesa</option>
                                <option value="disabled">Disabilitato</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="button ar-modal-close">Annulla</button>
                        <button type="submit" class="button button-primary">Aggiungi Utente</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Modal Visualizza Utente
     */
    private function render_view_user_modal() {
        ?>
        <div id="view-user-modal" class="ar-modal" style="display: none;">
            <div class="ar-modal-content ar-modal-large">
                <div class="ar-modal-header">
                    <h3>Dettagli Utente</h3>
                    <span class="ar-modal-close">&times;</span>
                </div>
                <div id="user-details-content">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Modal Carica Documento
     */
    private function render_upload_doc_modal() {
        ?>
        <div id="upload-doc-modal" class="ar-modal" style="display: none;">
            <div class="ar-modal-content">
                <div class="ar-modal-header">
                    <h3>Carica Nuovo Documento</h3>
                    <span class="ar-modal-close">&times;</span>
                </div>
                <form id="upload-doc-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>File PDF *</label>
                        <input type="file" name="documento" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                        <small>Formati supportati: PDF, DOC, DOCX, XLS, XLSX (max 10MB)</small>
                    </div>
                    <div class="form-group">
                        <label>Categoria *</label>
                        <select name="categoria" required>
                            <option value="">Seleziona categoria...</option>
                            <option value="tende">Tende</option>
                            <option value="pergoltende">Pergoltende</option>
                            <option value="motori">Motori</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Destinazione *</label>
                        <select name="destinazione" required>
                            <option value="">Chi può vedere questo documento?</option>
                            <option value="progettista">Solo Progettisti</option>
                            <option value="rivenditore">Solo Rivenditori</option>
                            <option value="entrambi">Entrambi</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sottocartella (opzionale)</label>
                        <input type="text" name="sottocartella" placeholder="es: cataloghi, schede-tecniche">
                    </div>
                    <div class="upload-progress" style="display: none;">
                        <div class="progress-bar">
                            <div class="progress-fill"></div>
                        </div>
                        <div class="progress-text">0%</div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="button ar-modal-close">Annulla</button>
                        <button type="submit" class="button button-primary">Carica Documento</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Pagina impostazioni
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>Impostazioni Area Riservata</h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('area_riservata_settings');
                do_settings_sections('area_riservata_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Approvazione Automatica</th>
                        <td>
                            <label>
                                <input type="checkbox" name="ar_auto_approve" value="1" 
                                       <?php checked(get_option('ar_auto_approve', 0)); ?>>
                                Approva automaticamente i nuovi utenti
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Email Notifiche</th>
                        <td>
                            <input type="email" name="ar_notification_email" 
                                   value="<?php echo esc_attr(get_option('ar_notification_email', get_admin_email())); ?>">
                            <p class="description">Email per ricevere notifiche di nuove registrazioni</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Dimensione Max File</th>
                        <td>
                            <input type="number" name="ar_max_file_size" min="1" max="50" 
                                   value="<?php echo esc_attr(get_option('ar_max_file_size', 10)); ?>"> MB
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    // AJAX Handlers
    
    /**
     * Approva/Rifiuta utente
     */
    public function ajax_approve_user() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        $action = sanitize_text_field($_POST['action_type']);
        $status = $action === 'approve' ? 'approved' : 'rejected';
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'area_riservata_users';
        
        // Ottieni i dati dell'utente prima dell'aggiornamento
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $user_id
        ));
        
        if (!$user) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
            return;
        }
        
        // Aggiorna lo status
        $result = $wpdb->update(
            $table_name,
            array('status' => $status),
            array('id' => $user_id),
            array('%s'),
            array('%d')
        );
        
        if ($result !== false) {
            // Log attività
            $wpdb->insert(
                $wpdb->prefix . 'area_riservata_logs',
                array(
                    'user_id' => 0,
                    'azione' => "Utente {$status}",
                    'dettagli' => "Utente ID {$user_id} è stato {$status}",
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                    'created_at' => current_time('mysql')
                )
            );
            
            // Invia email di notifica se approvato
            if ($status === 'approved') {
                $this->send_approval_notification($user);
            }
            
            wp_send_json_success(array(
                'message' => 'Utente ' . ($action === 'approve' ? 'approvato' : 'rifiutato') . ' con successo',
                'new_status' => $status
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'operazione'));
        }
    }
    
    /**
     * Invia email di notifica approvazione all'utente
     */
    private function send_approval_notification($user) {
        $site_name = get_bloginfo('name');
        $login_url = 'https://ictende.scherpmind.com/login/';
        $area_riservata_url = 'https://ictende.scherpmind.com/area-riservata/';
        
        $subject = 'Account Approvato - ' . $site_name;
        
        $message = "Ciao {$user->nome} {$user->cognome},\n\n";
        $message .= "Il tuo account per l'Area Riservata di {$site_name} è stato approvato!\n\n";
        $message .= "Ora puoi accedere all'area riservata utilizzando le tue credenziali:\n\n";
        $message .= "Username: {$user->username}\n";
        $message .= "Email: {$user->email}\n\n";
        $message .= "ACCEDI QUI: {$login_url}\n\n";
        $message .= "Oppure vai direttamente all'area riservata: {$area_riservata_url}\n\n";
        $message .= "Se hai dimenticato la password, contattaci.\n\n";
        $message .= "Grazie per aver scelto {$site_name}!\n\n";
        $message .= "---\n";
        $message .= "Questo è un messaggio automatico, non rispondere a questa email.";
        
        // Header per migliorare la deliverability
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <noreply@ictende.com>'
        );
        
        wp_mail($user->email, $subject, $message, $headers);
        
        // Log dell'invio email
        error_log('AREA RISERVATA: Email di approvazione inviata a ' . $user->email);
    }
    
    /**
     * Cambia status utente
     */
    public function ajax_toggle_user_status() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        $current_status = sanitize_text_field($_POST['current_status']);
        $new_status = $current_status === 'approved' ? 'disabled' : 'approved';
        
        $result = $this->db->update_user($user_id, array('status' => $new_status));
        
        if ($result !== false) {
            $this->db->insert_log(0, "Status utente cambiato", "Utente ID {$user_id} status: {$new_status}");
            wp_send_json_success(array(
                'message' => 'Status utente aggiornato',
                'new_status' => $new_status
            ));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'operazione'));
        }
    }
    
    /**
     * Aggiungi utente manualmente
     */
    public function ajax_add_manual_user() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Validazione dati
        $required_fields = ['nome', 'cognome', 'email', 'telefono', 'ragione_sociale', 'partita_iva', 'username', 'password', 'tipo_utente'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => "Il campo {$field} è obbligatorio"));
                return;
            }
        }
        
        $email = sanitize_email($_POST['email']);
        $username = sanitize_text_field($_POST['username']);
        
        // Verifica unicità email e username
        if ($this->db->email_exists($email)) {
            wp_send_json_error(array('message' => 'Email già esistente'));
            return;
        }
        
        if ($this->db->username_exists($username)) {
            wp_send_json_error(array('message' => 'Username già esistente'));
            return;
        }
        
        $data = array(
            'nome' => sanitize_text_field($_POST['nome']),
            'cognome' => sanitize_text_field($_POST['cognome']),
            'email' => $email,
            'telefono' => sanitize_text_field($_POST['telefono']),
            'ragione_sociale' => sanitize_text_field($_POST['ragione_sociale']),
            'partita_iva' => sanitize_text_field($_POST['partita_iva']),
            'username' => $username,
            'password' => $_POST['password'], // Will be hashed in insert_user
            'tipo_utente' => sanitize_text_field($_POST['tipo_utente']),
            'status' => isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'approved'
        );
        
        $result = $this->db->insert_user($data);
        
        if ($result) {
            $this->db->insert_log(0, "Utente aggiunto manualmente", "Nuovo utente: {$email}");
            wp_send_json_success(array('message' => 'Utente aggiunto con successo'));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'aggiunta dell\'utente'));
        }
    }
    
    /**
     * Carica documento
     */
    public function ajax_upload_document() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        if (!isset($_FILES['documento'])) {
            wp_send_json_error(array('message' => 'Nessun file selezionato'));
            return;
        }
        
        $file = $_FILES['documento'];
        $max_size = get_option('ar_max_file_size', 10) * 1024 * 1024; // MB to bytes
        
        // Validazioni file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'Errore durante il caricamento del file'));
            return;
        }
        
        if ($file['size'] > $max_size) {
            wp_send_json_error(array('message' => 'File troppo grande. Massimo ' . get_option('ar_max_file_size', 10) . 'MB'));
            return;
        }
        
        $allowed_types = array('pdf', 'doc', 'docx', 'xls', 'xlsx');
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_types)) {
            wp_send_json_error(array('message' => 'Tipo di file non supportato'));
            return;
        }
        
        // Preparazione directory
        $upload_dir = wp_upload_dir();
        $categoria = sanitize_text_field($_POST['categoria']);
        $base_dir = $upload_dir['basedir'] . '/area-riservata/';
        $target_dir = $base_dir . $categoria . '/';
        
        if (!empty($_POST['sottocartella'])) {
            $sottocartella = sanitize_file_name($_POST['sottocartella']);
            $target_dir .= $sottocartella . '/';
        }
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        // Caricamento file
        $file_name = sanitize_file_name($file['name']);
        $unique_filename = time() . '_' . $file_name;
        $file_path = $target_dir . $unique_filename;
        $relative_path = str_replace($base_dir, '', $file_path);
        
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $result = $this->db->insert_document(array(
                'nome_file' => $file_name,
                'path_file' => $relative_path,
                'categoria' => $categoria,
                'destinazione' => sanitize_text_field($_POST['destinazione']),
                'sottocartella' => !empty($_POST['sottocartella']) ? sanitize_text_field($_POST['sottocartella']) : null,
                'dimensione' => $file['size']
            ));
            
            if ($result) {
                $this->db->insert_log(0, "Documento caricato", "File: {$file_name}, Categoria: {$categoria}");
                wp_send_json_success(array('message' => 'Documento caricato con successo'));
            } else {
                unlink($file_path); // Remove uploaded file if DB insert fails
                wp_send_json_error(array('message' => 'Errore nel salvataggio del documento'));
            }
        } else {
            wp_send_json_error(array('message' => 'Errore nel caricamento del file'));
        }
    }
    
    /**
     * Elimina documento
     */
    public function ajax_delete_document() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $doc_id = intval($_POST['doc_id']);
        $document = $this->db->get_document($doc_id);
        
        if (!$document) {
            wp_send_json_error(array('message' => 'Documento non trovato'));
            return;
        }
        
        // Elimina file fisico
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/area-riservata/' . $document->path_file;
        
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Elimina record database
        $result = $this->db->delete_document($doc_id);
        
        if ($result) {
            $this->db->insert_log(0, "Documento eliminato", "File: {$document->nome_file}");
            wp_send_json_success(array('message' => 'Documento eliminato con successo'));
        } else {
            wp_send_json_error(array('message' => 'Errore nell\'eliminazione del documento'));
        }
    }
    
    /**
     * Elimina utente
     */
    public function ajax_delete_user() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_id = intval($_POST['user_id']);
        $user = $this->db->get_user($user_id);
        
        if (!$user) {
            wp_send_json_error(array('message' => 'Utente non trovato'));
            return;
        }
        
        global $wpdb;
        $result = $wpdb->delete(
            $wpdb->prefix . 'area_riservata_users',
            array('id' => $user_id),
            array('%d')
        );
        
        if ($result) {
            $this->db->insert_log(0, "Utente eliminato", "Utente: {$user->email}");
            wp_send_json_success(array('message' => 'Utente eliminato con successo'));
        } else {
            wp_send_json_error(array('message' => 'Errore durante l\'eliminazione'));
        }
    }
    
    /**
     * Ottieni statistiche
     */
    public function ajax_get_stats() {
        check_ajax_referer('area_riservata_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $user_stats = $this->db->get_user_stats();
        $doc_stats = $this->db->get_document_stats();
        
        wp_send_json_success(array(
            'users' => $user_stats,
            'documents' => $doc_stats
        ));
    }
    
    /**
     * Ottiene ultimo login utente
     */
    private function get_user_last_login($user_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT created_at FROM {$wpdb->prefix}area_riservata_logs 
             WHERE user_id = %d AND azione = 'Login' 
             ORDER BY created_at DESC LIMIT 1",
            $user_id
        ));
    }
}