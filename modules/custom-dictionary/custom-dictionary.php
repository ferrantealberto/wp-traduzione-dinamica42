<?php
/**
 * Modulo Dizionario Personalizzato per Dynamic Page Translator
 * File: modules/custom-dictionary/custom-dictionary.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Custom_Dictionary_Module {
    
    private $dictionary_data = array();
    private $exclude_words = array();
    private $manual_translations = array();
    
    public function __construct() {
        $this->init_dictionary_data();
        $this->init_hooks();
        $this->register_module();
    }
    
    /**
     * Inizializza dati dizionario
     */
    private function init_dictionary_data() {
        $this->dictionary_data = get_option('dpt_custom_dictionary', array());
        $this->exclude_words = get_option('dpt_exclude_words', array());
        $this->manual_translations = get_option('dpt_manual_translations', array());
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        // Hook per filtrare traduzioni
        add_filter('dpt_before_translation', array($this, 'process_dictionary_rules'), 10, 4);
        add_filter('dpt_after_translation', array($this, 'apply_manual_corrections'), 10, 4);
        
        // Hook admin
        add_action('admin_menu', array($this, 'add_dictionary_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dictionary_assets'));
        
        // AJAX hooks
        add_action('wp_ajax_dpt_save_dictionary_entry', array($this, 'ajax_save_dictionary_entry'));
        add_action('wp_ajax_dpt_delete_dictionary_entry', array($this, 'ajax_delete_dictionary_entry'));
        add_action('wp_ajax_dpt_import_dictionary', array($this, 'ajax_import_dictionary'));
        add_action('wp_ajax_dpt_export_dictionary', array($this, 'ajax_export_dictionary'));
        add_action('wp_ajax_dpt_search_dictionary', array($this, 'ajax_search_dictionary'));
        add_action('wp_ajax_dpt_bulk_add_excludes', array($this, 'ajax_bulk_add_excludes'));
        add_action('wp_ajax_dpt_suggest_translations', array($this, 'ajax_suggest_translations'));
        
        // Hook per traduzione real-time
        add_action('wp_ajax_dpt_check_dictionary_match', array($this, 'ajax_check_dictionary_match'));
        add_action('wp_ajax_nopriv_dpt_check_dictionary_match', array($this, 'ajax_check_dictionary_match'));
    }
    
    /**
     * Registra modulo
     */
    private function register_module() {
        add_action('dpt_modules_loaded', function() {
            $plugin = DynamicPageTranslator::get_instance();
            $plugin->register_module('custom_dictionary', $this);
        }, 5);
    }
    
    /**
     * Processa regole dizionario prima della traduzione
     */
    public function process_dictionary_rules($content, $source_lang, $target_lang, $context) {
        // 1. Controlla parole da escludere
        $protected_content = $this->protect_excluded_words($content, $target_lang);
        
        // 2. Applica traduzioni esatte
        $translated_content = $this->apply_exact_translations($protected_content, $source_lang, $target_lang);
        
        // 3. Applica sostituzioni parziali
        $final_content = $this->apply_partial_replacements($translated_content, $source_lang, $target_lang);
        
        return $final_content;
    }
    
    /**
     * Applica correzioni manuali dopo la traduzione
     */
    public function apply_manual_corrections($translation, $original, $source_lang, $target_lang) {
        if (!isset($this->manual_translations[$target_lang])) {
            return $translation;
        }
        
        $corrections = $this->manual_translations[$target_lang];
        
        // Applica correzioni post-traduzione
        foreach ($corrections['post_translation'] ?? array() as $search => $replace) {
            $translation = str_replace($search, $replace, $translation);
        }
        
        // Applica correzioni con regex
        foreach ($corrections['regex_corrections'] ?? array() as $pattern => $replacement) {
            $translation = preg_replace($pattern, $replacement, $translation);
        }
        
        return $translation;
    }
    
    /**
     * Protegge parole da escludere
     */
    private function protect_excluded_words($content, $target_lang) {
        if (!isset($this->exclude_words[$target_lang])) {
            return $content;
        }
        
        $excludes = $this->exclude_words[$target_lang];
        $protected_content = $content;
        $protection_map = array();
        
        // Protegge parole esatte
        foreach ($excludes['exact_words'] ?? array() as $word) {
            $placeholder = '[[PROTECT_' . md5($word) . ']]';
            $protection_map[$placeholder] = $word;
            
            // Protegge word boundaries
            $protected_content = preg_replace('/\b' . preg_quote($word, '/') . '\b/i', $placeholder, $protected_content);
        }
        
        // Protegge frasi
        foreach ($excludes['phrases'] ?? array() as $phrase) {
            $placeholder = '[[PROTECT_' . md5($phrase) . ']]';
            $protection_map[$placeholder] = $phrase;
            $protected_content = str_replace($phrase, $placeholder, $protected_content);
        }
        
        // Protegge pattern regex
        foreach ($excludes['regex_patterns'] ?? array() as $pattern) {
            $protected_content = preg_replace_callback($pattern, function($matches) use (&$protection_map) {
                $placeholder = '[[PROTECT_' . md5($matches[0]) . ']]';
                $protection_map[$placeholder] = $matches[0];
                return $placeholder;
            }, $protected_content);
        }
        
        // Salva mappa protezione per restore
        $this->store_protection_map($protection_map, $target_lang);
        
        return $protected_content;
    }
    
    /**
     * Applica traduzioni esatte
     */
    private function apply_exact_translations($content, $source_lang, $target_lang) {
        if (!isset($this->dictionary_data[$target_lang]['exact_translations'])) {
            return $content;
        }
        
        $exact_translations = $this->dictionary_data[$target_lang]['exact_translations'];
        $translated_content = $content;
        
        // Ordina per lunghezza decrescente per evitare sostituzioni parziali
        uksort($exact_translations, function($a, $b) {
            return strlen($b) - strlen($a);
        });
        
        foreach ($exact_translations as $original => $translation) {
            // Sostituzione case-insensitive con preservazione case
            $translated_content = preg_replace_callback(
                '/\b' . preg_quote($original, '/') . '\b/i',
                function($matches) use ($translation) {
                    return $this->preserve_case($matches[0], $translation);
                },
                $translated_content
            );
        }
        
        return $translated_content;
    }
    
    /**
     * Applica sostituzioni parziali
     */
    private function apply_partial_replacements($content, $source_lang, $target_lang) {
        if (!isset($this->dictionary_data[$target_lang]['partial_replacements'])) {
            return $content;
        }
        
        $partial_replacements = $this->dictionary_data[$target_lang]['partial_replacements'];
        $modified_content = $content;
        
        foreach ($partial_replacements as $search => $replace) {
            $modified_content = str_ireplace($search, $replace, $modified_content);
        }
        
        return $modified_content;
    }
    
    /**
     * Preserva il case della parola originale
     */
    private function preserve_case($original, $translation) {
        if (ctype_upper($original)) {
            return strtoupper($translation);
        } elseif (ctype_upper($original[0])) {
            return ucfirst(strtolower($translation));
        } else {
            return strtolower($translation);
        }
    }
    
    /**
     * Aggiunge menu admin dizionario
     */
    public function add_dictionary_admin_menu() {
        add_submenu_page(
            'dynamic-translator',
            __('Dizionario Personalizzato', 'dynamic-translator'),
            __('Dizionario', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-dictionary',
            array($this, 'render_dictionary_admin_page')
        );
    }
    
    /**
     * Enqueue assets per dizionario
     */
    public function enqueue_dictionary_assets($hook) {
        if (strpos($hook, 'dynamic-translator-dictionary') === false) {
            return;
        }
        
        wp_enqueue_script(
            'dpt-dictionary',
            DPT_PLUGIN_URL . 'assets/js/dictionary.js',
            array('jquery', 'jquery-ui-autocomplete', 'jquery-ui-sortable'),
            DPT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'dpt-dictionary',
            DPT_PLUGIN_URL . 'assets/css/dictionary.css',
            array(),
            DPT_VERSION
        );
        
        wp_localize_script('dpt-dictionary', 'dptDictionary', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpt_dictionary_nonce'),
            'languages' => dpt_get_option('enabled_languages', array()),
            'strings' => array(
                'confirmDelete' => __('Sei sicuro di voler eliminare questa voce?', 'dynamic-translator'),
                'addTranslation' => __('Aggiungi Traduzione', 'dynamic-translator'),
                'editTranslation' => __('Modifica Traduzione', 'dynamic-translator'),
                'saveSuccess' => __('Salvato con successo!', 'dynamic-translator'),
                'saveError' => __('Errore durante il salvataggio', 'dynamic-translator'),
                'searching' => __('Ricerca in corso...', 'dynamic-translator'),
                'noResults' => __('Nessun risultato trovato', 'dynamic-translator')
            )
        ));
    }
    
    /**
     * Renderizza pagina admin dizionario
     */
    public function render_dictionary_admin_page() {
        $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'exact';
        $enabled_languages = dpt_get_option('enabled_languages', array());
        $current_language = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : $enabled_languages[0];
        
        ?>
        <div class="wrap">
            <h1><?php _e('Dizionario Personalizzato', 'dynamic-translator'); ?></h1>
            
            <!-- Selezione Lingua -->
            <div class="dpt-language-selector">
                <label for="dictionary-language"><?php _e('Lingua:', 'dynamic-translator'); ?></label>
                <select id="dictionary-language" onchange="changeDictionaryLanguage(this.value)">
                    <?php foreach ($enabled_languages as $lang_code): ?>
                        <option value="<?php echo $lang_code; ?>" <?php selected($current_language, $lang_code); ?>>
                            <?php echo $this->get_language_name($lang_code); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Tabs Navigation -->
            <nav class="nav-tab-wrapper">
                <a href="?page=dynamic-translator-dictionary&tab=exact&lang=<?php echo $current_language; ?>" 
                   class="nav-tab <?php echo $current_tab === 'exact' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Traduzioni Esatte', 'dynamic-translator'); ?>
                </a>
                <a href="?page=dynamic-translator-dictionary&tab=partial&lang=<?php echo $current_language; ?>" 
                   class="nav-tab <?php echo $current_tab === 'partial' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Sostituzioni Parziali', 'dynamic-translator'); ?>
                </a>
                <a href="?page=dynamic-translator-dictionary&tab=exclude&lang=<?php echo $current_language; ?>" 
                   class="nav-tab <?php echo $current_tab === 'exclude' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Parole da Non Tradurre', 'dynamic-translator'); ?>
                </a>
                <a href="?page=dynamic-translator-dictionary&tab=corrections&lang=<?php echo $current_language; ?>" 
                   class="nav-tab <?php echo $current_tab === 'corrections' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Correzioni Post-Traduzione', 'dynamic-translator'); ?>
                </a>
                <a href="?page=dynamic-translator-dictionary&tab=import-export&lang=<?php echo $current_language; ?>" 
                   class="nav-tab <?php echo $current_tab === 'import-export' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Import/Export', 'dynamic-translator'); ?>
                </a>
            </nav>
            
            <!-- Tab Content -->
            <div class="dpt-tab-content">
                <?php
                switch ($current_tab) {
                    case 'exact':
                        $this->render_exact_translations_tab($current_language);
                        break;
                    case 'partial':
                        $this->render_partial_replacements_tab($current_language);
                        break;
                    case 'exclude':
                        $this->render_exclude_words_tab($current_language);
                        break;
                    case 'corrections':
                        $this->render_corrections_tab($current_language);
                        break;
                    case 'import-export':
                        $this->render_import_export_tab($current_language);
                        break;
                }
                ?>
            </div>
        </div>
        
        <!-- Modal per aggiungere/modificare voci -->
        <div id="dictionary-modal" class="dpt-modal" style="display: none;">
            <div class="dpt-modal-content">
                <div class="dpt-modal-header">
                    <h3 id="modal-title"><?php _e('Aggiungi Voce', 'dynamic-translator'); ?></h3>
                    <button class="dpt-modal-close">&times;</button>
                </div>
                <div class="dpt-modal-body">
                    <form id="dictionary-entry-form">
                        <input type="hidden" id="entry-id" name="entry_id">
                        <input type="hidden" id="entry-type" name="entry_type">
                        <input type="hidden" id="entry-language" name="entry_language" value="<?php echo $current_language; ?>">
                        
                        <table class="form-table">
                            <tr id="original-row">
                                <th><label for="original-text"><?php _e('Testo Originale', 'dynamic-translator'); ?></label></th>
                                <td>
                                    <input type="text" id="original-text" name="original_text" class="regular-text" required>
                                    <p class="description"><?php _e('Testo nella lingua originale del sito', 'dynamic-translator'); ?></p>
                                </td>
                            </tr>
                            <tr id="translation-row">
                                <th><label for="translation-text"><?php _e('Traduzione', 'dynamic-translator'); ?></label></th>
                                <td>
                                    <input type="text" id="translation-text" name="translation_text" class="regular-text">
                                    <p class="description"><?php _e('Traduzione personalizzata', 'dynamic-translator'); ?></p>
                                </td>
                            </tr>
                            <tr id="context-row">
                                <th><label for="context"><?php _e('Contesto', 'dynamic-translator'); ?></label></th>
                                <td>
                                    <select id="context" name="context">
                                        <option value="general"><?php _e('Generale', 'dynamic-translator'); ?></option>
                                        <option value="woocommerce"><?php _e('WooCommerce', 'dynamic-translator'); ?></option>
                                        <option value="menu"><?php _e('Menu', 'dynamic-translator'); ?></option>
                                        <option value="content"><?php _e('Contenuto', 'dynamic-translator'); ?></option>
                                        <option value="forms"><?php _e('Form', 'dynamic-translator'); ?></option>
                                        <option value="seo"><?php _e('SEO', 'dynamic-translator'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="case-sensitive-row">
                                <th><label for="case-sensitive"><?php _e('Case Sensitive', 'dynamic-translator'); ?></label></th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="case-sensitive" name="case_sensitive" value="1">
                                        <?php _e('Rispetta maiuscole/minuscole', 'dynamic-translator'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr id="priority-row">
                                <th><label for="priority"><?php _e('Priorità', 'dynamic-translator'); ?></label></th>
                                <td>
                                    <select id="priority" name="priority">
                                        <option value="low"><?php _e('Bassa', 'dynamic-translator'); ?></option>
                                        <option value="normal" selected><?php _e('Normale', 'dynamic-translator'); ?></option>
                                        <option value="high"><?php _e('Alta', 'dynamic-translator'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
                <div class="dpt-modal-footer">
                    <button type="button" id="save-dictionary-entry" class="button button-primary">
                        <?php _e('Salva', 'dynamic-translator'); ?>
                    </button>
                    <button type="button" class="button dpt-modal-close">
                        <?php _e('Annulla', 'dynamic-translator'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        function changeDictionaryLanguage(lang) {
            const currentTab = '<?php echo $current_tab; ?>';
            window.location.href = `?page=dynamic-translator-dictionary&tab=${currentTab}&lang=${lang}`;
        }
        </script>
        
        <style>
        .dpt-language-selector { margin: 20px 0; }
        .dpt-language-selector select { margin-left: 10px; }
        .dpt-dictionary-search { margin: 20px 0; }
        .dpt-dictionary-search input { width: 300px; margin-right: 10px; }
        .dpt-dictionary-table { margin-top: 20px; }
        .dpt-dictionary-table th { width: 200px; }
        .dpt-modal { position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .dpt-modal-content { background: white; margin: 5% auto; padding: 0; width: 80%; max-width: 600px; border-radius: 6px; }
        .dpt-modal-header { padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
        .dpt-modal-header h3 { margin: 0; }
        .dpt-modal-close { background: none; border: none; font-size: 24px; cursor: pointer; }
        .dpt-modal-body { padding: 20px; }
        .dpt-modal-footer { padding: 20px; border-top: 1px solid #ddd; text-align: right; }
        .dpt-modal-footer .button { margin-left: 10px; }
        .dpt-quick-actions { margin: 15px 0; }
        .dpt-quick-actions .button { margin-right: 10px; }
        .dpt-entry-actions { white-space: nowrap; }
        .dpt-entry-actions .button { margin-right: 5px; }
        .dpt-priority-high { color: #d63638; font-weight: bold; }
        .dpt-priority-normal { color: #646970; }
        .dpt-priority-low { color: #00a32a; }
        .dpt-context-tag { display: inline-block; padding: 2px 6px; background: #f0f0f1; border-radius: 3px; font-size: 11px; margin-right: 5px; }
        </style>
        <?php
    }
    
    /**
     * Renderizza tab traduzioni esatte
     */
    private function render_exact_translations_tab($language) {
        $exact_translations = $this->dictionary_data[$language]['exact_translations'] ?? array();
        ?>
        <div class="dpt-tab-section">
            <div class="dpt-section-header">
                <h2><?php _e('Traduzioni Esatte', 'dynamic-translator'); ?></h2>
                <p><?php _e('Definisci traduzioni personalizzate per parole o frasi specifiche. Queste sostituiranno completamente la traduzione automatica.', 'dynamic-translator'); ?></p>
            </div>
            
            <div class="dpt-quick-actions">
                <button type="button" id="add-exact-translation" class="button button-primary">
                    <?php _e('Aggiungi Traduzione Esatta', 'dynamic-translator'); ?>
                </button>
                <button type="button" id="import-common-phrases" class="button">
                    <?php _e('Importa Frasi Comuni', 'dynamic-translator'); ?>
                </button>
                <button type="button" id="suggest-from-content" class="button">
                    <?php _e('Suggerisci da Contenuto', 'dynamic-translator'); ?>
                </button>
            </div>
            
            <div class="dpt-dictionary-search">
                <input type="text" id="search-exact" placeholder="<?php esc_attr_e('Cerca traduzioni...', 'dynamic-translator'); ?>">
                <button type="button" class="button"><?php _e('Cerca', 'dynamic-translator'); ?></button>
            </div>
            
            <table class="wp-list-table widefat striped dpt-dictionary-table">
                <thead>
                    <tr>
                        <th><?php _e('Testo Originale', 'dynamic-translator'); ?></th>
                        <th><?php _e('Traduzione', 'dynamic-translator'); ?></th>
                        <th><?php _e('Contesto', 'dynamic-translator'); ?></th>
                        <th><?php _e('Priorità', 'dynamic-translator'); ?></th>
                        <th><?php _e('Azioni', 'dynamic-translator'); ?></th>
                    </tr>
                </thead>
                <tbody id="exact-translations-list">
                    <?php if (empty($exact_translations)): ?>
                        <tr>
                            <td colspan="5"><?php _e('Nessuna traduzione esatta definita.', 'dynamic-translator'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exact_translations as $original => $data): ?>
                            <?php
                            $translation = is_string($data) ? $data : $data['translation'];
                            $context = is_array($data) ? ($data['context'] ?? 'general') : 'general';
                            $priority = is_array($data) ? ($data['priority'] ?? 'normal') : 'normal';
                            ?>
                            <tr data-original="<?php echo esc_attr($original); ?>">
                                <td><strong><?php echo esc_html($original); ?></strong></td>
                                <td><?php echo esc_html($translation); ?></td>
                                <td><span class="dpt-context-tag"><?php echo esc_html($context); ?></span></td>
                                <td><span class="dpt-priority-<?php echo $priority; ?>"><?php echo esc_html(ucfirst($priority)); ?></span></td>
                                <td class="dpt-entry-actions">
                                    <button type="button" class="button button-small edit-exact" data-original="<?php echo esc_attr($original); ?>">
                                        <?php _e('Modifica', 'dynamic-translator'); ?>
                                    </button>
                                    <button type="button" class="button button-small delete-exact" data-original="<?php echo esc_attr($original); ?>">
                                        <?php _e('Elimina', 'dynamic-translator'); ?>
                                    </button>
                                    <button type="button" class="button button-small test-exact" data-original="<?php echo esc_attr($original); ?>">
                                        <?php _e('Test', 'dynamic-translator'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Renderizza tab sostituzioni parziali
     */
    private function render_partial_replacements_tab($language) {
        $partial_replacements = $this->dictionary_data[$language]['partial_replacements'] ?? array();
        ?>
        <div class="dpt-tab-section">
            <div class="dpt-section-header">
                <h2><?php _e('Sostituzioni Parziali', 'dynamic-translator'); ?></h2>
                <p><?php _e('Sostituisci parti di testo ovunque appaiano. Utile per correggere traduzioni ricorrenti o sostituire termini tecnici.', 'dynamic-translator'); ?></p>
            </div>
            
            <div class="dpt-quick-actions">
                <button type="button" id="add-partial-replacement" class="button button-primary">
                    <?php _e('Aggiungi Sostituzione', 'dynamic-translator'); ?>
                </button>
                <button type="button" id="import-brand-terms" class="button">
                    <?php _e('Importa Termini Brand', 'dynamic-translator'); ?>
                </button>
            </div>
            
            <table class="wp-list-table widefat striped dpt-dictionary-table">
                <thead>
                    <tr>
                        <th><?php _e('Cerca', 'dynamic-translator'); ?></th>
                        <th><?php _e('Sostituisci con', 'dynamic-translator'); ?></th>
                        <th><?php _e('Case Sensitive', 'dynamic-translator'); ?></th>
                        <th><?php _e('Azioni', 'dynamic-translator'); ?></th>
                    </tr>
                </thead>
                <tbody id="partial-replacements-list">
                    <?php if (empty($partial_replacements)): ?>
                        <tr>
                            <td colspan="4"><?php _e('Nessuna sostituzione parziale definita.', 'dynamic-translator'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($partial_replacements as $search => $data): ?>
                            <?php
                            $replace = is_string($data) ? $data : $data['replace'];
                            $case_sensitive = is_array($data) ? ($data['case_sensitive'] ?? false) : false;
                            ?>
                            <tr data-search="<?php echo esc_attr($search); ?>">
                                <td><code><?php echo esc_html($search); ?></code></td>
                                <td><strong><?php echo esc_html($replace); ?></strong></td>
                                <td><?php echo $case_sensitive ? '✓' : '✗'; ?></td>
                                <td class="dpt-entry-actions">
                                    <button type="button" class="button button-small edit-partial" data-search="<?php echo esc_attr($search); ?>">
                                        <?php _e('Modifica', 'dynamic-translator'); ?>
                                    </button>
                                    <button type="button" class="button button-small delete-partial" data-search="<?php echo esc_attr($search); ?>">
                                        <?php _e('Elimina', 'dynamic-translator'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Renderizza tab parole da escludere
     */
    private function render_exclude_words_tab($language) {
        $exclude_words = $this->exclude_words[$language] ?? array();
        ?>
        <div class="dpt-tab-section">
            <div class="dpt-section-header">
                <h2><?php _e('Parole da Non Tradurre', 'dynamic-translator'); ?></h2>
                <p><?php _e('Specifica parole, frasi o pattern che non devono mai essere tradotti. Utile per nomi brand, termini tecnici, etc.', 'dynamic-translator'); ?></p>
            </div>
            
            <div class="dpt-quick-actions">
                <button type="button" id="add-exclude-word" class="button button-primary">
                    <?php _e('Aggiungi Esclusione', 'dynamic-translator'); ?>
                </button>
                <button type="button" id="bulk-add-excludes" class="button">
                    <?php _e('Aggiunta Multipla', 'dynamic-translator'); ?>
                </button>
                <button type="button" id="import-common-excludes" class="button">
                    <?php _e('Importa Esclusioni Comuni', 'dynamic-translator'); ?>
                </button>
            </div>
            
            <!-- Tab per tipi di esclusione -->
            <div class="dpt-exclude-tabs">
                <button type="button" class="dpt-exclude-tab active" data-type="exact_words">
                    <?php _e('Parole Esatte', 'dynamic-translator'); ?>
                </button>
                <button type="button" class="dpt-exclude-tab" data-type="phrases">
                    <?php _e('Frasi', 'dynamic-translator'); ?>
                </button>
                <button type="button" class="dpt-exclude-tab" data-type="regex_patterns">
                    <?php _e('Pattern Regex', 'dynamic-translator'); ?>
                </button>
            </div>
            
            <div class="dpt-exclude-content">
                <!-- Parole esatte -->
                <div id="exact-words-content" class="dpt-exclude-section active">
                    <h4><?php _e('Parole Esatte da Non Tradurre', 'dynamic-translator'); ?></h4>
                    <div class="dpt-exclude-list">
                        <?php
                        $exact_words = $exclude_words['exact_words'] ?? array();
                        foreach ($exact_words as $word):
                        ?>
                            <div class="dpt-exclude-item">
                                <span class="dpt-exclude-word"><?php echo esc_html($word); ?></span>
                                <button type="button" class="button button-small delete-exclude" data-type="exact_words" data-value="<?php echo esc_attr($word); ?>">
                                    <?php _e('Rimuovi', 'dynamic-translator'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dpt-add-exclude">
                        <input type="text" id="new-exact-word" placeholder="<?php esc_attr_e('Aggiungi parola da escludere...', 'dynamic-translator'); ?>">
                        <button type="button" id="add-exact-word" class="button">
                            <?php _e('Aggiungi', 'dynamic-translator'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Frasi -->
                <div id="phrases-content" class="dpt-exclude-section">
                    <h4><?php _e('Frasi da Non Tradurre', 'dynamic-translator'); ?></h4>
                    <div class="dpt-exclude-list">
                        <?php
                        $phrases = $exclude_words['phrases'] ?? array();
                        foreach ($phrases as $phrase):
                        ?>
                            <div class="dpt-exclude-item">
                                <span class="dpt-exclude-phrase"><?php echo esc_html($phrase); ?></span>
                                <button type="button" class="button button-small delete-exclude" data-type="phrases" data-value="<?php echo esc_attr($phrase); ?>">
                                    <?php _e('Rimuovi', 'dynamic-translator'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dpt-add-exclude">
                        <input type="text" id="new-phrase" placeholder="<?php esc_attr_e('Aggiungi frase da escludere...', 'dynamic-translator'); ?>">
                        <button type="button" id="add-phrase" class="button">
                            <?php _e('Aggiungi', 'dynamic-translator'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Pattern Regex -->
                <div id="regex-patterns-content" class="dpt-exclude-section">
                    <h4><?php _e('Pattern Regex da Non Tradurre', 'dynamic-translator'); ?></h4>
                    <p class="description"><?php _e('Usa espressioni regolari per pattern complessi (es: /\\d+\\.\\d+/ per numeri decimali)', 'dynamic-translator'); ?></p>
                    <div class="dpt-exclude-list">
                        <?php
                        $regex_patterns = $exclude_words['regex_patterns'] ?? array();
                        foreach ($regex_patterns as $pattern):
                        ?>
                            <div class="dpt-exclude-item">
                                <code class="dpt-exclude-pattern"><?php echo esc_html($pattern); ?></code>
                                <button type="button" class="button button-small delete-exclude" data-type="regex_patterns" data-value="<?php echo esc_attr($pattern); ?>">
                                    <?php _e('Rimuovi', 'dynamic-translator'); ?>
                                </button>
                                <button type="button" class="button button-small test-regex" data-pattern="<?php echo esc_attr($pattern); ?>">
                                    <?php _e('Test', 'dynamic-translator'); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="dpt-add-exclude">
                        <input type="text" id="new-regex" placeholder="<?php esc_attr_e('/pattern/flags', 'dynamic-translator'); ?>">
                        <button type="button" id="add-regex" class="button">
                            <?php _e('Aggiungi', 'dynamic-translator'); ?>
                        </button>
                        <button type="button" id="test-regex-input" class="button">
                            <?php _e('Test', 'dynamic-translator'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .dpt-exclude-tabs { margin: 20px 0; border-bottom: 1px solid #ddd; }
        .dpt-exclude-tab { background: none; border: none; padding: 10px 20px; cursor: pointer; border-bottom: 3px solid transparent; }
        .dpt-exclude-tab.active { border-bottom-color: #007cba; color: #007cba; font-weight: 600; }
        .dpt-exclude-section { display: none; padding: 20px 0; }
        .dpt-exclude-section.active { display: block; }
        .dpt-exclude-list { margin: 15px 0; max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; }
        .dpt-exclude-item { display: flex; justify-content: space-between; align-items: center; padding: 5px 0; border-bottom: 1px solid #eee; }
        .dpt-exclude-item:last-child { border-bottom: none; }
        .dpt-exclude-word, .dpt-exclude-phrase { font-weight: 600; }
        .dpt-exclude-pattern { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; }
        .dpt-add-exclude { display: flex; gap: 10px; align-items: center; }
        .dpt-add-exclude input { flex: 1; }
        </style>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Tab switching
            $('.dpt-exclude-tab').on('click', function() {
                const type = $(this).data('type');
                
                $('.dpt-exclude-tab').removeClass('active');
                $(this).addClass('active');
                
                $('.dpt-exclude-section').removeClass('active');
                $('#' + type.replace('_', '-') + '-content').addClass('active');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderizza tab correzioni post-traduzione
     */
    private function render_corrections_tab($language) {
        $corrections = $this->manual_translations[$language]['post_translation'] ?? array();
        ?>
        <div class="dpt-tab-section">
            <div class="dpt-section-header">
                <h2><?php _e('Correzioni Post-Traduzione', 'dynamic-translator'); ?></h2>
                <p><?php _e('Applica correzioni dopo che la traduzione automatica è stata completata. Utile per rifinire traduzioni automatiche.', 'dynamic-translator'); ?></p>
            </div>
            
            <div class="dpt-quick-actions">
                <button type="button" id="add-correction" class="button button-primary">
                    <?php _e('Aggiungi Correzione', 'dynamic-translator'); ?>
                </button>
                <button type="button" id="learn-from-translations" class="button">
                    <?php _e('Impara da Traduzioni', 'dynamic-translator'); ?>
                </button>
            </div>
            
            <table class="wp-list-table widefat striped dpt-dictionary-table">
                <thead>
                    <tr>
                        <th><?php _e('Traduzione Automatica', 'dynamic-translator'); ?></th>
                        <th><?php _e('Correzione', 'dynamic-translator'); ?></th>
                        <th><?php _e('Tipo', 'dynamic-translator'); ?></th>
                        <th><?php _e('Azioni', 'dynamic-translator'); ?></th>
                    </tr>
                </thead>
                <tbody id="corrections-list">
                    <?php if (empty($corrections)): ?>
                        <tr>
                            <td colspan="4"><?php _e('Nessuna correzione definita.', 'dynamic-translator'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($corrections as $search => $replace): ?>
                            <tr>
                                <td><code><?php echo esc_html($search); ?></code></td>
                                <td><strong><?php echo esc_html($replace); ?></strong></td>
                                <td><span class="dpt-context-tag">Text</span></td>
                                <td class="dpt-entry-actions">
                                    <button type="button" class="button button-small edit-correction" data-search="<?php echo esc_attr($search); ?>">
                                        <?php _e('Modifica', 'dynamic-translator'); ?>
                                    </button>
                                    <button type="button" class="button button-small delete-correction" data-search="<?php echo esc_attr($search); ?>">
                                        <?php _e('Elimina', 'dynamic-translator'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Renderizza tab import/export
     */
    private function render_import_export_tab($language) {
        ?>
        <div class="dpt-tab-section">
            <div class="dpt-section-header">
                <h2><?php _e('Import/Export Dizionario', 'dynamic-translator'); ?></h2>
                <p><?php _e('Importa ed esporta il dizionario personalizzato per backup o condivisione tra siti.', 'dynamic-translator'); ?></p>
            </div>
            
            <div class="dpt-import-export-grid">
                <!-- Export -->
                <div class="dpt-ie-section">
                    <h3><?php _e('Esporta Dizionario', 'dynamic-translator'); ?></h3>
                    <p><?php _e('Scarica il dizionario in formato JSON per backup o condivisione.', 'dynamic-translator'); ?></p>
                    
                    <div class="dpt-export-options">
                        <label>
                            <input type="checkbox" id="export-exact" checked>
                            <?php _e('Traduzioni Esatte', 'dynamic-translator'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="export-partial" checked>
                            <?php _e('Sostituzioni Parziali', 'dynamic-translator'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="export-excludes" checked>
                            <?php _e('Parole da Escludere', 'dynamic-translator'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="export-corrections" checked>
                            <?php _e('Correzioni Post-Traduzione', 'dynamic-translator'); ?>
                        </label>
                    </div>
                    
                    <div class="dpt-export-actions">
                        <button type="button" id="export-current-language" class="button button-primary">
                            <?php printf(__('Esporta %s', 'dynamic-translator'), $this->get_language_name($language)); ?>
                        </button>
                        <button type="button" id="export-all-languages" class="button">
                            <?php _e('Esporta Tutte le Lingue', 'dynamic-translator'); ?>
                        </button>
                    </div>
                </div>
                
                <!-- Import -->
                <div class="dpt-ie-section">
                    <h3><?php _e('Importa Dizionario', 'dynamic-translator'); ?></h3>
                    <p><?php _e('Carica un file JSON di dizionario precedentemente esportato.', 'dynamic-translator'); ?></p>
                    
                    <div class="dpt-import-area">
                        <input type="file" id="import-file" accept=".json" style="display: none;">
                        <div class="dpt-upload-area" onclick="document.getElementById('import-file').click();">
                            <div class="dpt-upload-icon">📁</div>
                            <div class="dpt-upload-text"><?php _e('Clicca per selezionare file JSON', 'dynamic-translator'); ?></div>
                            <div class="dpt-upload-hint"><?php _e('Oppure trascina il file qui', 'dynamic-translator'); ?></div>
                        </div>
                    </div>
                    
                    <div class="dpt-import-options" style="display: none;">
                        <h4><?php _e('Opzioni Import', 'dynamic-translator'); ?></h4>
                        <label>
                            <input type="radio" name="import-mode" value="merge" checked>
                            <?php _e('Unisci con esistente', 'dynamic-translator'); ?>
                        </label>
                        <label>
                            <input type="radio" name="import-mode" value="replace">
                            <?php _e('Sostituisci esistente', 'dynamic-translator'); ?>
                        </label>
                        <label>
                            <input type="checkbox" id="backup-before-import" checked>
                            <?php _e('Crea backup prima dell\'import', 'dynamic-translator'); ?>
                        </label>
                    </div>
                    
                    <div class="dpt-import-actions" style="display: none;">
                        <button type="button" id="start-import" class="button button-primary">
                            <?php _e('Avvia Import', 'dynamic-translator'); ?>
                        </button>
                        <button type="button" id="cancel-import" class="button">
                            <?php _e('Annulla', 'dynamic-translator'); ?>
                        </button>
                    </div>
                    
                    <div id="import-progress" style="display: none;">
                        <div class="dpt-progress">
                            <div class="dpt-progress-bar"></div>
                        </div>
                        <p class="dpt-progress-text"></p>
                    </div>
                </div>
                
                <!-- Preset Dizionari -->
                <div class="dpt-ie-section">
                    <h3><?php _e('Dizionari Predefiniti', 'dynamic-translator'); ?></h3>
                    <p><?php _e('Importa dizionari predefiniti per domini specifici.', 'dynamic-translator'); ?></p>
                    
                    <div class="dpt-preset-dictionaries">
                        <div class="dpt-preset-item">
                            <h4><?php _e('E-commerce', 'dynamic-translator'); ?></h4>
                            <p><?php _e('Termini comuni per negozi online', 'dynamic-translator'); ?></p>
                            <button type="button" class="button import-preset" data-preset="ecommerce">
                                <?php _e('Importa', 'dynamic-translator'); ?>
                            </button>
                        </div>
                        
                        <div class="dpt-preset-item">
                            <h4><?php _e('Corporate', 'dynamic-translator'); ?></h4>
                            <p><?php _e('Terminologia aziendale e business', 'dynamic-translator'); ?></p>
                            <button type="button" class="button import-preset" data-preset="corporate">
                                <?php _e('Importa', 'dynamic-translator'); ?>
                            </button>
                        </div>
                        
                        <div class="dpt-preset-item">
                            <h4><?php _e('Tecnico', 'dynamic-translator'); ?></h4>
                            <p><?php _e('Termini tecnici e informatici', 'dynamic-translator'); ?></p>
                            <button type="button" class="button import-preset" data-preset="technical">
                                <?php _e('Importa', 'dynamic-translator'); ?>
                            </button>
                        </div>
                        
                        <div class="dpt-preset-item">
                            <h4><?php _e('Brand Protection', 'dynamic-translator'); ?></h4>
                            <p><?php _e('Nomi brand e marchi comuni da non tradurre', 'dynamic-translator'); ?></p>
                            <button type="button" class="button import-preset" data-preset="brands">
                                <?php _e('Importa', 'dynamic-translator'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .dpt-import-export-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 20px; }
        .dpt-ie-section { background: white; padding: 20px; border: 1px solid #ddd; border-radius: 6px; }
        .dpt-ie-section h3 { margin-top: 0; }
        .dpt-export-options label, .dpt-import-options label { display: block; margin: 10px 0; }
        .dpt-export-actions, .dpt-import-actions { margin-top: 20px; }
        .dpt-export-actions .button, .dpt-import-actions .button { margin-right: 10px; }
        .dpt-upload-area { border: 2px dashed #ddd; padding: 40px 20px; text-align: center; cursor: pointer; border-radius: 6px; transition: all 0.2s ease; }
        .dpt-upload-area:hover { border-color: #007cba; background: #f8f9fa; }
        .dpt-upload-icon { font-size: 48px; margin-bottom: 10px; }
        .dpt-upload-text { font-size: 16px; margin-bottom: 5px; }
        .dpt-upload-hint { color: #666; font-size: 13px; }
        .dpt-preset-dictionaries { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; }
        .dpt-preset-item { border: 1px solid #ddd; padding: 15px; border-radius: 6px; }
        .dpt-preset-item h4 { margin: 0 0 10px 0; }
        .dpt-preset-item p { margin: 0 0 15px 0; color: #666; font-size: 13px; }
        .dpt-progress { background: #f1f1f1; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .dpt-progress-bar { background: #007cba; height: 100%; width: 0%; transition: width 0.3s ease; }
        @media (max-width: 1200px) {
            .dpt-import-export-grid { grid-template-columns: 1fr; }
            .dpt-preset-dictionaries { grid-template-columns: 1fr; }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Controlla match dizionario per traduzione real-time
     */
    public function ajax_check_dictionary_match() {
        check_ajax_referer('dpt_frontend_nonce', 'nonce');
        
        $content = sanitize_textarea_field($_POST['content']);
        $target_lang = sanitize_text_field($_POST['target_lang']);
        
        // Controlla traduzioni esatte
        $exact_match = $this->check_exact_translation($content, $target_lang);
        if ($exact_match !== false) {
            wp_send_json_success(array(
                'has_match' => true,
                'translation' => $exact_match,
                'type' => 'exact'
            ));
        }
        
        // Controlla se deve essere escluso
        $should_exclude = $this->should_exclude_from_translation($content, $target_lang);
        if ($should_exclude) {
            wp_send_json_success(array(
                'has_match' => true,
                'translation' => $content,
                'type' => 'exclude'
            ));
        }
        
        wp_send_json_success(array(
            'has_match' => false
        ));
    }
    
    /**
     * Controlla traduzione esatta
     */
    private function check_exact_translation($content, $target_lang) {
        if (!isset($this->dictionary_data[$target_lang]['exact_translations'])) {
            return false;
        }
        
        $exact_translations = $this->dictionary_data[$target_lang]['exact_translations'];
        
        // Cerca match esatto (case-insensitive)
        foreach ($exact_translations as $original => $data) {
            $translation = is_string($data) ? $data : $data['translation'];
            
            if (strcasecmp($original, $content) === 0) {
                return $this->preserve_case($content, $translation);
            }
        }
        
        return false;
    }
    
    /**
     * Controlla se il contenuto deve essere escluso dalla traduzione
     */
    private function should_exclude_from_translation($content, $target_lang) {
        if (!isset($this->exclude_words[$target_lang])) {
            return false;
        }
        
        $excludes = $this->exclude_words[$target_lang];
        
        // Controlla parole esatte
        foreach ($excludes['exact_words'] ?? array() as $word) {
            if (strcasecmp($word, $content) === 0) {
                return true;
            }
        }
        
        // Controlla frasi
        foreach ($excludes['phrases'] ?? array() as $phrase) {
            if (stripos($content, $phrase) !== false) {
                return true;
            }
        }
        
        // Controlla pattern regex
        foreach ($excludes['regex_patterns'] ?? array() as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Utility functions
     */
    private function get_language_name($lang_code) {
        $names = array(
            'en' => 'English', 'it' => 'Italiano', 'es' => 'Español', 'fr' => 'Français',
            'de' => 'Deutsch', 'pt' => 'Português', 'ru' => 'Русский', 'zh' => '中文',
            'ja' => '日本語', 'ar' => 'العربية'
        );
        
        return $names[$lang_code] ?? $lang_code;
    }
    
    private function store_protection_map($protection_map, $language) {
        set_transient('dpt_protection_map_' . $language, $protection_map, HOUR_IN_SECONDS);
    }
    
    /**
     * Ottiene dizionario per lingua
     */
    public function get_dictionary_for_language($language) {
        return $this->dictionary_data[$language] ?? array();
    }
    
    /**
     * Ottiene parole escluse per lingua
     */
    public function get_excludes_for_language($language) {
        return $this->exclude_words[$language] ?? array();
    }
    
    /**
     * Salva voce dizionario
     */
    public function save_dictionary_entry($language, $type, $data) {
        if (!isset($this->dictionary_data[$language])) {
            $this->dictionary_data[$language] = array();
        }
        
        if (!isset($this->dictionary_data[$language][$type])) {
            $this->dictionary_data[$language][$type] = array();
        }
        
        $this->dictionary_data[$language][$type] = array_merge(
            $this->dictionary_data[$language][$type],
            $data
        );
        
        return update_option('dpt_custom_dictionary', $this->dictionary_data);
    }
    
    /**
     * Elimina voce dizionario
     */
    public function delete_dictionary_entry($language, $type, $key) {
        if (isset($this->dictionary_data[$language][$type][$key])) {
            unset($this->dictionary_data[$language][$type][$key]);
            return update_option('dpt_custom_dictionary', $this->dictionary_data);
        }
        
        return false;
    }
}

// Inizializza modulo
new DPT_Custom_Dictionary_Module();