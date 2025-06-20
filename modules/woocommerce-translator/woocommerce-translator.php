<?php
/**
 * Modulo WooCommerce per Dynamic Page Translator
 * File: modules/woocommerce-translator/woocommerce-translator.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_WooCommerce_Translator_Module {
    
    private $translatable_fields = array();
    private $translation_settings = array();
    
    public function __construct() {
        // Verifica se WooCommerce è attivo
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->init_translatable_fields();
        $this->init_translation_settings();
        $this->init_hooks();
        $this->register_module();
    }
    
    /**
     * Inizializza campi traducibili
     */
    private function init_translatable_fields() {
        $this->translatable_fields = array(
            'product_title' => array(
                'label' => __('Titolo Prodotto', 'dynamic-translator'),
                'description' => __('Nome principale del prodotto', 'dynamic-translator'),
                'priority' => 'high',
                'default' => true
            ),
            'product_description' => array(
                'label' => __('Descrizione Prodotto', 'dynamic-translator'),
                'description' => __('Descrizione completa del prodotto', 'dynamic-translator'),
                'priority' => 'high',
                'default' => true
            ),
            'product_short_description' => array(
                'label' => __('Descrizione Breve', 'dynamic-translator'),
                'description' => __('Descrizione breve visualizzata in anteprima', 'dynamic-translator'),
                'priority' => 'high',
                'default' => true
            ),
            'product_attributes' => array(
                'label' => __('Attributi Prodotto', 'dynamic-translator'),
                'description' => __('Caratteristiche e specifiche tecniche', 'dynamic-translator'),
                'priority' => 'medium',
                'default' => true
            ),
            'product_categories' => array(
                'label' => __('Categorie Prodotto', 'dynamic-translator'),
                'description' => __('Categorie e sottocategorie', 'dynamic-translator'),
                'priority' => 'medium',
                'default' => true
            ),
            'product_tags' => array(
                'label' => __('Tag Prodotto', 'dynamic-translator'),
                'description' => __('Etichette e parole chiave', 'dynamic-translator'),
                'priority' => 'low',
                'default' => true
            ),
            'product_variations' => array(
                'label' => __('Varianti Prodotto', 'dynamic-translator'),
                'description' => __('Nomi e descrizioni delle varianti', 'dynamic-translator'),
                'priority' => 'medium',
                'default' => false
            ),
            'product_meta' => array(
                'label' => __('Meta Prodotto', 'dynamic-translator'),
                'description' => __('Meta title e description SEO', 'dynamic-translator'),
                'priority' => 'low',
                'default' => false
            ),
            'checkout_fields' => array(
                'label' => __('Campi Checkout', 'dynamic-translator'),
                'description' => __('Etichette e placeholder del checkout', 'dynamic-translator'),
                'priority' => 'medium',
                'default' => true
            ),
            'shop_notices' => array(
                'label' => __('Avvisi Negozio', 'dynamic-translator'),
                'description' => __('Messaggi di stato e notifiche', 'dynamic-translator'),
                'priority' => 'high',
                'default' => true
            ),
            'cart_messages' => array(
                'label' => __('Messaggi Carrello', 'dynamic-translator'),
                'description' => __('Testi del carrello e wishlist', 'dynamic-translator'),
                'priority' => 'medium',
                'default' => true
            ),
            'coupon_messages' => array(
                'label' => __('Messaggi Coupon', 'dynamic-translator'),
                'description' => __('Descrizioni e messaggi dei coupon', 'dynamic-translator'),
                'priority' => 'low',
                'default' => false
            )
        );
    }
    
    /**
     * Inizializza impostazioni traduzione
     */
    private function init_translation_settings() {
        $this->translation_settings = array(
            'enabled_fields' => dpt_get_option('woo_enabled_fields', array_keys(array_filter($this->translatable_fields, function($field) {
                return $field['default'];
            }))),
            'auto_translate_new' => dpt_get_option('woo_auto_translate_new', true),
            'preserve_html' => dpt_get_option('woo_preserve_html', true),
            'translation_priority' => dpt_get_option('woo_translation_priority', 'normal'),
            'batch_size' => dpt_get_option('woo_batch_size', 10),
            'exclude_categories' => dpt_get_option('woo_exclude_categories', array()),
            'exclude_tags' => dpt_get_option('woo_exclude_tags', array()),
            'custom_fields' => dpt_get_option('woo_custom_fields', array())
        );
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        // Hook prodotti
        add_filter('the_title', array($this, 'translate_product_title'), 10, 2);
        add_filter('woocommerce_product_get_description', array($this, 'translate_product_description'));
        add_filter('woocommerce_product_get_short_description', array($this, 'translate_product_short_description'));
        
        // Hook categorie e tag
        add_filter('wp_get_object_terms', array($this, 'translate_product_terms'), 10, 4);
        add_filter('get_term', array($this, 'translate_single_term'), 10, 2);
        
        // Hook attributi
        add_filter('woocommerce_attribute_label', array($this, 'translate_attribute_label'), 10, 3);
        add_filter('woocommerce_variation_option_name', array($this, 'translate_variation_name'));
        
        // Hook checkout e messaggi
        add_filter('woocommerce_checkout_fields', array($this, 'translate_checkout_fields'));
        add_filter('wc_add_notice', array($this, 'translate_notice_message'), 10, 2);
        add_filter('woocommerce_cart_item_name', array($this, 'translate_cart_item_name'));
        
        // Hook admin
        add_action('admin_menu', array($this, 'add_woo_admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_product_translation_metabox'));
        add_action('save_post', array($this, 'save_product_translations'));
        
        // Hook bulk actions
        add_filter('bulk_actions-edit-product', array($this, 'add_bulk_translate_action'));
        add_filter('handle_bulk_actions-edit-product', array($this, 'handle_bulk_translate'), 10, 3);
        
        // Hook automatici per nuovi prodotti
        add_action('woocommerce_new_product', array($this, 'auto_translate_new_product'));
        add_action('woocommerce_update_product', array($this, 'auto_translate_updated_product'));
        
        // AJAX hooks
        add_action('wp_ajax_dpt_woo_translate_product', array($this, 'ajax_translate_product'));
        add_action('wp_ajax_dpt_woo_batch_translate', array($this, 'ajax_batch_translate_products'));
        add_action('wp_ajax_dpt_woo_get_translation_stats', array($this, 'ajax_get_translation_stats'));
        
        // Hook per varianti
        add_filter('woocommerce_variation_get_description', array($this, 'translate_variation_description'));
        
        // Hook per recensioni
        add_filter('comment_text', array($this, 'translate_review_text'), 10, 2);
    }
    
    /**
     * Registra modulo
     */
    private function register_module() {
        add_action('dpt_modules_loaded', function() {
            $plugin = DynamicPageTranslator::get_instance();
            $plugin->register_module('woocommerce_translator', $this);
        }, 5);
    }
    
    /**
     * Traduce titolo prodotto
     */
    public function translate_product_title($title, $post_id = null) {
        if (!$this->should_translate_field('product_title') || !$this->is_product($post_id)) {
            return $title;
        }
        
        return $this->get_translated_content($title, 'product_title', $post_id);
    }
    
    /**
     * Traduce descrizione prodotto
     */
    public function translate_product_description($description) {
        if (!$this->should_translate_field('product_description')) {
            return $description;
        }
        
        return $this->get_translated_content($description, 'product_description');
    }
    
    /**
     * Traduce descrizione breve prodotto
     */
    public function translate_product_short_description($short_description) {
        if (!$this->should_translate_field('product_short_description')) {
            return $short_description;
        }
        
        return $this->get_translated_content($short_description, 'product_short_description');
    }
    
    /**
     * Traduce termini prodotto (categorie/tag)
     */
    public function translate_product_terms($terms, $object_ids, $taxonomies, $args) {
        if (!is_array($terms) || empty($terms)) {
            return $terms;
        }
        
        foreach ($terms as &$term) {
            if (is_object($term)) {
                $should_translate = false;
                
                if ($term->taxonomy === 'product_cat' && $this->should_translate_field('product_categories')) {
                    $should_translate = true;
                } elseif ($term->taxonomy === 'product_tag' && $this->should_translate_field('product_tags')) {
                    $should_translate = true;
                }
                
                if ($should_translate) {
                    $term->name = $this->get_translated_content($term->name, 'term_' . $term->taxonomy, $term->term_id);
                    if (!empty($term->description)) {
                        $term->description = $this->get_translated_content($term->description, 'term_description', $term->term_id);
                    }
                }
            }
        }
        
        return $terms;
    }
    
    /**
     * Traduce singolo termine
     */
    public function translate_single_term($term, $taxonomy) {
        if (!is_object($term)) {
            return $term;
        }
        
        $should_translate = false;
        
        if ($taxonomy === 'product_cat' && $this->should_translate_field('product_categories')) {
            $should_translate = true;
        } elseif ($taxonomy === 'product_tag' && $this->should_translate_field('product_tags')) {
            $should_translate = true;
        }
        
        if ($should_translate) {
            $term->name = $this->get_translated_content($term->name, 'term_' . $taxonomy, $term->term_id);
            if (!empty($term->description)) {
                $term->description = $this->get_translated_content($term->description, 'term_description', $term->term_id);
            }
        }
        
        return $term;
    }
    
    /**
     * Traduce etichette attributi
     */
    public function translate_attribute_label($label, $name, $product = null) {
        if (!$this->should_translate_field('product_attributes')) {
            return $label;
        }
        
        return $this->get_translated_content($label, 'attribute_label');
    }
    
    /**
     * Traduce nomi variazioni
     */
    public function translate_variation_name($name) {
        if (!$this->should_translate_field('product_variations')) {
            return $name;
        }
        
        return $this->get_translated_content($name, 'variation_name');
    }
    
    /**
     * Traduce campi checkout
     */
    public function translate_checkout_fields($fields) {
        if (!$this->should_translate_field('checkout_fields')) {
            return $fields;
        }
        
        foreach ($fields as $fieldset_key => &$fieldset) {
            if (is_array($fieldset)) {
                foreach ($fieldset as $field_key => &$field) {
                    if (isset($field['label'])) {
                        $field['label'] = $this->get_translated_content($field['label'], 'checkout_field_label');
                    }
                    if (isset($field['placeholder'])) {
                        $field['placeholder'] = $this->get_translated_content($field['placeholder'], 'checkout_field_placeholder');
                    }
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Traduce messaggi di notifica
     */
    public function translate_notice_message($message, $notice_type) {
        if (!$this->should_translate_field('shop_notices')) {
            return $message;
        }
        
        return $this->get_translated_content($message, 'shop_notice');
    }
    
    /**
     * Traduce nome elemento carrello
     */
    public function translate_cart_item_name($name) {
        if (!$this->should_translate_field('cart_messages')) {
            return $name;
        }
        
        return $this->get_translated_content(strip_tags($name), 'cart_item_name');
    }
    
    /**
     * Traduce descrizione variante
     */
    public function translate_variation_description($description) {
        if (!$this->should_translate_field('product_variations')) {
            return $description;
        }
        
        return $this->get_translated_content($description, 'variation_description');
    }
    
    /**
     * Traduce testo recensioni
     */
    public function translate_review_text($text, $comment) {
        if (!$this->should_translate_field('product_reviews') || $comment->comment_type !== 'review') {
            return $text;
        }
        
        return $this->get_translated_content($text, 'review_text', $comment->comment_ID);
    }
    
    /**
     * Aggiunge menu admin WooCommerce
     */
    public function add_woo_admin_menu() {
        add_submenu_page(
            'dynamic-translator',
            __('WooCommerce Traduzioni', 'dynamic-translator'),
            __('WooCommerce', 'dynamic-translator'),
            'manage_options',
            'dynamic-translator-woocommerce',
            array($this, 'render_woo_admin_page')
        );
    }
    
    /**
     * Renderizza pagina admin WooCommerce
     */
    public function render_woo_admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_woo_settings();
        }
        
        $stats = $this->get_woo_translation_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce Traduzioni', 'dynamic-translator'); ?></h1>
            
            <!-- Statistiche -->
            <div class="dpt-woo-stats">
                <h2><?php _e('Statistiche Traduzione', 'dynamic-translator'); ?></h2>
                <div class="dpt-stats-cards">
                    <div class="dpt-stats-card">
                        <span class="dpt-stat-number"><?php echo number_format($stats['products_translated']); ?></span>
                        <span class="dpt-stat-label"><?php _e('Prodotti Tradotti', 'dynamic-translator'); ?></span>
                    </div>
                    <div class="dpt-stats-card">
                        <span class="dpt-stat-number"><?php echo number_format($stats['categories_translated']); ?></span>
                        <span class="dpt-stat-label"><?php _e('Categorie Tradotte', 'dynamic-translator'); ?></span>
                    </div>
                    <div class="dpt-stats-card">
                        <span class="dpt-stat-number"><?php echo number_format($stats['pending_translations']); ?></span>
                        <span class="dpt-stat-label"><?php _e('Traduzioni Pendenti', 'dynamic-translator'); ?></span>
                    </div>
                    <div class="dpt-stats-card">
                        <span class="dpt-stat-number"><?php echo $stats['completion_percentage']; ?>%</span>
                        <span class="dpt-stat-label"><?php _e('Completamento', 'dynamic-translator'); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Azioni Rapide -->
            <div class="dpt-woo-quick-actions">
                <h2><?php _e('Azioni Rapide', 'dynamic-translator'); ?></h2>
                <div class="dpt-action-buttons">
                    <button type="button" id="translate-all-products" class="button button-primary">
                        <?php _e('Traduci Tutti i Prodotti', 'dynamic-translator'); ?>
                    </button>
                    <button type="button" id="translate-categories" class="button">
                        <?php _e('Traduci Categorie', 'dynamic-translator'); ?>
                    </button>
                    <button type="button" id="translate-attributes" class="button">
                        <?php _e('Traduci Attributi', 'dynamic-translator'); ?>
                    </button>
                    <button type="button" id="export-translations" class="button">
                        <?php _e('Esporta Traduzioni', 'dynamic-translator'); ?>
                    </button>
                </div>
                <div id="bulk-translation-progress" style="display:none;">
                    <div class="dpt-progress">
                        <div class="dpt-progress-bar" style="width: 0%;"></div>
                    </div>
                    <p class="dpt-progress-text">Preparazione...</p>
                </div>
            </div>
            
            <!-- Impostazioni -->
            <form method="post" action="">
                <?php wp_nonce_field('dpt_woo_settings', 'dpt_woo_nonce'); ?>
                
                <div class="dpt-form-section">
                    <h3><?php _e('Elementi da Tradurre', 'dynamic-translator'); ?></h3>
                    <table class="form-table">
                        <?php foreach ($this->translatable_fields as $field_key => $field): ?>
                        <tr>
                            <th scope="row">
                                <label for="woo_field_<?php echo $field_key; ?>">
                                    <?php echo esc_html($field['label']); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="woo_enabled_fields[]" 
                                           value="<?php echo $field_key; ?>" 
                                           id="woo_field_<?php echo $field_key; ?>"
                                           <?php checked(in_array($field_key, $this->translation_settings['enabled_fields'])); ?>>
                                    <?php echo esc_html($field['description']); ?>
                                </label>
                                <div class="dpt-field-priority">
                                    <span class="priority-<?php echo $field['priority']; ?>">
                                        <?php 
                                        switch($field['priority']) {
                                            case 'high': _e('Priorità Alta', 'dynamic-translator'); break;
                                            case 'medium': _e('Priorità Media', 'dynamic-translator'); break;
                                            case 'low': _e('Priorità Bassa', 'dynamic-translator'); break;
                                        }
                                        ?>
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                
                <div class="dpt-form-section">
                    <h3><?php _e('Opzioni Traduzione', 'dynamic-translator'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Traduzione Automatica', 'dynamic-translator'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="woo_auto_translate_new" value="1" 
                                           <?php checked($this->translation_settings['auto_translate_new']); ?>>
                                    <?php _e('Traduci automaticamente nuovi prodotti', 'dynamic-translator'); ?>
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Priorità Traduzione', 'dynamic-translator'); ?></th>
                            <td>
                                <select name="woo_translation_priority">
                                    <option value="low" <?php selected($this->translation_settings['translation_priority'], 'low'); ?>>
                                        <?php _e('Bassa (più economico)', 'dynamic-translator'); ?>
                                    </option>
                                    <option value="normal" <?php selected($this->translation_settings['translation_priority'], 'normal'); ?>>
                                        <?php _e('Normale', 'dynamic-translator'); ?>
                                    </option>
                                    <option value="high" <?php selected($this->translation_settings['translation_priority'], 'high'); ?>>
                                        <?php _e('Alta (più veloce)', 'dynamic-translator'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Dimensione Batch', 'dynamic-translator'); ?></th>
                            <td>
                                <input type="number" name="woo_batch_size" 
                                       value="<?php echo $this->translation_settings['batch_size']; ?>" 
                                       min="1" max="50" class="small-text">
                                <p class="description"><?php _e('Numero di elementi tradotti contemporaneamente', 'dynamic-translator'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Escludi Categorie', 'dynamic-translator'); ?></th>
                            <td>
                                <?php
                                $categories = get_terms(array('taxonomy' => 'product_cat', 'hide_empty' => false));
                                if ($categories):
                                ?>
                                <div class="dpt-checkbox-list">
                                    <?php foreach ($categories as $category): ?>
                                    <label>
                                        <input type="checkbox" name="woo_exclude_categories[]" 
                                               value="<?php echo $category->term_id; ?>"
                                               <?php checked(in_array($category->term_id, $this->translation_settings['exclude_categories'])); ?>>
                                        <?php echo esc_html($category->name); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <!-- Traduzioni Personalizzate -->
            <div class="dpt-form-section">
                <h3><?php _e('Traduzioni Personalizzate', 'dynamic-translator'); ?></h3>
                <p><?php _e('Aggiungi traduzioni manuali per termini specifici di WooCommerce.', 'dynamic-translator'); ?></p>
                <div id="woo-custom-translations">
                    <button type="button" id="add-woo-translation" class="button">
                        <?php _e('Aggiungi Traduzione', 'dynamic-translator'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Traduzione batch prodotti
            $('#translate-all-products').on('click', function() {
                if (confirm('<?php _e('Sei sicuro di voler tradurre tutti i prodotti?', 'dynamic-translator'); ?>')) {
                    startBatchTranslation('products');
                }
            });
            
            $('#translate-categories').on('click', function() {
                startBatchTranslation('categories');
            });
            
            $('#translate-attributes').on('click', function() {
                startBatchTranslation('attributes');
            });
            
            function startBatchTranslation(type) {
                const $progress = $('#bulk-translation-progress');
                const $progressBar = $('.dpt-progress-bar');
                const $progressText = $('.dpt-progress-text');
                
                $progress.show();
                $progressBar.css('width', '0%');
                $progressText.text('Inizializzazione...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dpt_woo_batch_translate',
                        type: type,
                        nonce: '<?php echo wp_create_nonce('dpt_woo_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            trackTranslationProgress(response.data.batch_id);
                        } else {
                            alert('Errore: ' + response.data);
                            $progress.hide();
                        }
                    }
                });
            }
            
            function trackTranslationProgress(batchId) {
                const checkProgress = setInterval(function() {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'dpt_woo_get_translation_progress',
                            batch_id: batchId,
                            nonce: '<?php echo wp_create_nonce('dpt_woo_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                const progress = response.data.progress;
                                const total = response.data.total;
                                const completed = response.data.completed;
                                
                                $('.dpt-progress-bar').css('width', progress + '%');
                                $('.dpt-progress-text').text(`Tradotte ${completed} di ${total} (${progress}%)`);
                                
                                if (progress >= 100) {
                                    clearInterval(checkProgress);
                                    setTimeout(function() {
                                        $('#bulk-translation-progress').hide();
                                        location.reload();
                                    }, 2000);
                                }
                            }
                        }
                    });
                }, 2000);
            }
        });
        </script>
        
        <style>
        .dpt-woo-stats { margin: 20px 0; }
        .dpt-stats-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 30px; }
        .dpt-stats-card { background: white; padding: 20px; border: 1px solid #ddd; border-radius: 6px; text-align: center; }
        .dpt-stat-number { display: block; font-size: 24px; font-weight: bold; color: #007cba; }
        .dpt-stat-label { color: #666; font-size: 14px; }
        .dpt-action-buttons { margin: 15px 0; }
        .dpt-action-buttons .button { margin-right: 10px; }
        .dpt-progress { background: #f1f1f1; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
        .dpt-progress-bar { background: #007cba; height: 100%; transition: width 0.3s ease; }
        .dpt-checkbox-list { max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; }
        .dpt-checkbox-list label { display: block; margin-bottom: 5px; }
        .dpt-field-priority { margin-top: 5px; }
        .priority-high { color: #d63638; font-weight: bold; }
        .priority-medium { color: #dba617; }
        .priority-low { color: #00a32a; }
        </style>
        <?php
    }
    
    /**
     * Aggiunge metabox traduzione ai prodotti
     */
    public function add_product_translation_metabox() {
        add_meta_box(
            'dpt-product-translations',
            __('Traduzioni Prodotto', 'dynamic-translator'),
            array($this, 'render_product_translation_metabox'),
            'product',
            'normal',
            'high'
        );
    }
    
    /**
     * Renderizza metabox traduzioni prodotto
     */
    public function render_product_translation_metabox($post) {
        wp_nonce_field('dpt_product_translations', 'dpt_product_translations_nonce');
        
        $enabled_languages = dpt_get_option('enabled_languages', array());
        $product_translations = get_post_meta($post->ID, '_dpt_product_translations', true) ?: array();
        
        ?>
        <div class="dpt-product-translations">
            <div class="dpt-translation-actions">
                <button type="button" id="translate-product-auto" class="button button-primary">
                    <?php _e('Traduci Automaticamente', 'dynamic-translator'); ?>
                </button>
                <button type="button" id="clear-product-translations" class="button">
                    <?php _e('Cancella Traduzioni', 'dynamic-translator'); ?>
                </button>
            </div>
            
            <div class="dpt-translation-tabs">
                <?php foreach ($enabled_languages as $lang_code): ?>
                <div class="dpt-translation-tab" data-lang="<?php echo $lang_code; ?>">
                    <h4><?php echo $this->get_language_name($lang_code); ?></h4>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Titolo', 'dynamic-translator'); ?></th>
                            <td>
                                <input type="text" name="dpt_product_translations[<?php echo $lang_code; ?>][title]" 
                                       value="<?php echo esc_attr($product_translations[$lang_code]['title'] ?? ''); ?>" 
                                       style="width: 100%;">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Descrizione Breve', 'dynamic-translator'); ?></th>
                            <td>
                                <textarea name="dpt_product_translations[<?php echo $lang_code; ?>][short_description]" 
                                          rows="3" style="width: 100%;"><?php echo esc_textarea($product_translations[$lang_code]['short_description'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Descrizione', 'dynamic-translator'); ?></th>
                            <td>
                                <textarea name="dpt_product_translations[<?php echo $lang_code; ?>][description]" 
                                          rows="6" style="width: 100%;"><?php echo esc_textarea($product_translations[$lang_code]['description'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#translate-product-auto').on('click', function() {
                const $button = $(this);
                const originalText = $button.text();
                
                $button.prop('disabled', true).text('<?php _e('Traduzione in corso...', 'dynamic-translator'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dpt_woo_translate_product',
                        product_id: <?php echo $post->ID; ?>,
                        nonce: '<?php echo wp_create_nonce('dpt_woo_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Errore: ' + response.data);
                        }
                    },
                    complete: function() {
                        $button.prop('disabled', false).text(originalText);
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Traduci singolo prodotto
     */
    public function ajax_translate_product() {
        check_ajax_referer('dpt_woo_nonce', 'nonce');
        
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error('Prodotto non trovato');
        }
        
        $result = $this->translate_single_product($product);
        
        if ($result) {
            wp_send_json_success('Prodotto tradotto con successo');
        } else {
            wp_send_json_error('Errore durante la traduzione');
        }
    }
    
    /**
     * Traduce singolo prodotto
     */
    private function translate_single_product($product) {
        $enabled_languages = dpt_get_option('enabled_languages', array());
        $default_language = dpt_get_option('default_language', 'en');
        $translations = array();
        
        foreach ($enabled_languages as $lang_code) {
            if ($lang_code === $default_language) {
                continue;
            }
            
            $translations[$lang_code] = array();
            
            // Traduce titolo
            if ($this->should_translate_field('product_title')) {
                $translations[$lang_code]['title'] = $this->translate_text($product->get_name(), $default_language, $lang_code);
            }
            
            // Traduce descrizione breve
            if ($this->should_translate_field('product_short_description')) {
                $translations[$lang_code]['short_description'] = $this->translate_text($product->get_short_description(), $default_language, $lang_code);
            }
            
            // Traduce descrizione
            if ($this->should_translate_field('product_description')) {
                $translations[$lang_code]['description'] = $this->translate_text($product->get_description(), $default_language, $lang_code);
            }
        }
        
        return update_post_meta($product->get_id(), '_dpt_product_translations', $translations);
    }
    
    /**
     * Utility functions
     */
    private function should_translate_field($field) {
        return in_array($field, $this->translation_settings['enabled_fields']);
    }
    
    private function is_product($post_id) {
        return $post_id && get_post_type($post_id) === 'product';
    }
    
    private function get_translated_content($content, $context, $object_id = null) {
        if (empty($content)) {
            return $content;
        }
        
        $current_language = $this->get_current_language();
        $default_language = dpt_get_option('default_language', 'en');
        
        if ($current_language === $default_language) {
            return $content;
        }
        
        // Check cache veloce
        $cache_key = md5($content . $context . $current_language);
        $cached = get_transient('dpt_woo_' . $cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Traduzione
        $translation = $this->translate_text($content, $default_language, $current_language);
        
        if ($translation && $translation !== $content) {
            set_transient('dpt_woo_' . $cache_key, $translation, 24 * HOUR_IN_SECONDS);
            return $translation;
        }
        
        return $content;
    }
    
    private function translate_text($text, $source_lang, $target_lang) {
        $plugin = DynamicPageTranslator::get_instance();
        $api_handler = $plugin->get_api_handler();
        
        $translation = $api_handler->translate($text, $source_lang, $target_lang);
        
        return is_wp_error($translation) ? $text : $translation;
    }
    
    private function get_current_language() {
        return isset($_COOKIE['dpt_current_lang']) ? 
            sanitize_text_field($_COOKIE['dpt_current_lang']) : 
            dpt_get_option('default_language', 'en');
    }
    
    private function get_language_name($lang_code) {
        $names = array(
            'en' => 'English', 'it' => 'Italiano', 'es' => 'Español', 'fr' => 'Français',
            'de' => 'Deutsch', 'pt' => 'Português', 'ru' => 'Русский', 'zh' => '中文',
            'ja' => '日本語', 'ar' => 'العربية'
        );
        
        return $names[$lang_code] ?? $lang_code;
    }
    
    private function get_woo_translation_stats() {
        $products_count = wp_count_posts('product')->publish;
        $categories_count = wp_count_terms('product_cat');
        
        // Calcola traduzioni esistenti
        global $wpdb;
        $translated_products = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_dpt_product_translations'"
        );
        
        return array(
            'products_translated' => $translated_products,
            'categories_translated' => 0, // Da implementare
            'pending_translations' => $products_count - $translated_products,
            'completion_percentage' => $products_count > 0 ? round(($translated_products / $products_count) * 100) : 0
        );
    }
    
    private function save_woo_settings() {
        if (!wp_verify_nonce($_POST['dpt_woo_nonce'], 'dpt_woo_settings')) {
            return;
        }
        
        $enabled_fields = array_map('sanitize_text_field', $_POST['woo_enabled_fields'] ?? array());
        $auto_translate_new = isset($_POST['woo_auto_translate_new']);
        $translation_priority = sanitize_text_field($_POST['woo_translation_priority'] ?? 'normal');
        $batch_size = intval($_POST['woo_batch_size'] ?? 10);
        $exclude_categories = array_map('intval', $_POST['woo_exclude_categories'] ?? array());
        
        dpt_update_option('woo_enabled_fields', $enabled_fields);
        dpt_update_option('woo_auto_translate_new', $auto_translate_new);
        dpt_update_option('woo_translation_priority', $translation_priority);
        dpt_update_option('woo_batch_size', $batch_size);
        dpt_update_option('woo_exclude_categories', $exclude_categories);
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Impostazioni WooCommerce salvate!', 'dynamic-translator') . '</p></div>';
        });
    }
    
    /**
     * Notice se WooCommerce non è attivo
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Il modulo WooCommerce per Dynamic Page Translator richiede WooCommerce attivo.', 'dynamic-translator');
        echo '</p></div>';
    }
}

// Inizializza modulo se WooCommerce è presente
if (class_exists('WooCommerce')) {
    new DPT_WooCommerce_Translator_Module();
}