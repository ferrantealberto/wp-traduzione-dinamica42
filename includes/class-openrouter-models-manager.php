<?php
/**
 * Gestore Modelli OpenRouter Espanso per Dynamic Page Translator
 * File: includes/class-openrouter-models-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_OpenRouter_Models_Manager {
    
    private $models_list = array();
    private $cached_models = array();
    
    public function __construct() {
        $this->init_models_list();
        $this->init_hooks();
    }
    
    /**
     * Inizializza hook
     */
    private function init_hooks() {
        add_action('wp_ajax_dpt_search_openrouter_models', array($this, 'ajax_search_models'));
        add_action('wp_ajax_dpt_get_model_details', array($this, 'ajax_get_model_details'));
        add_action('wp_ajax_dpt_test_model_translation', array($this, 'ajax_test_model_translation'));
        add_action('wp_ajax_dpt_refresh_models_list', array($this, 'ajax_refresh_models_list'));
        
        // Enqueue assets per admin
        add_action('admin_enqueue_scripts', array($this, 'enqueue_models_assets'));
    }
    
    /**
     * Inizializza lista completa modelli
     */
    private function init_models_list() {
        $this->models_list = array(
            
            // === MODELLI GRATUITI ===
            'meta-llama/llama-3.1-8b-instruct:free' => array(
                'name' => 'Llama 3.1 8B Instruct (Free)',
                'provider' => 'Meta',
                'cost' => 0,
                'cost_per_1m' => 0,
                'category' => 'free',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 131072,
                'description' => 'Modello gratuito veloce, buona qualità per traduzioni base',
                'best_for' => 'Traduzioni veloci, contenuti brevi, test',
                'languages_strong' => 'EN, ES, FR, DE, IT, PT',
                'translation_quality' => 7,
                'speed_rating' => 9,
                'free' => true
            ),
            
            'google/gemma-2-9b-it:free' => array(
                'name' => 'Gemma 2 9B IT (Free)',
                'provider' => 'Google',
                'cost' => 0,
                'cost_per_1m' => 0,
                'category' => 'free',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 8192,
                'description' => 'Modello Google gratuito ottimizzato per instruction following',
                'best_for' => 'Traduzioni precise, linguaggio formale',
                'languages_strong' => 'EN, ES, FR, DE, IT, JA, KO',
                'translation_quality' => 7.5,
                'speed_rating' => 8,
                'free' => true
            ),
            
            'microsoft/wizardlm-2-8x22b:free' => array(
                'name' => 'WizardLM 2 8x22B (Free)',
                'provider' => 'Microsoft',
                'cost' => 0,
                'cost_per_1m' => 0,
                'category' => 'free',
                'speed' => 'medium',
                'quality' => 'excellent',
                'context_length' => 65536,
                'description' => 'Modello gratuito di alta qualità, ottimo per traduzioni complesse',
                'best_for' => 'Traduzioni tecniche, contenuti lunghi',
                'languages_strong' => 'EN, ZH, ES, FR, DE, JA, KO, RU',
                'translation_quality' => 8.5,
                'speed_rating' => 6,
                'free' => true
            ),
            
            'mistralai/mistral-7b-instruct:free' => array(
                'name' => 'Mistral 7B Instruct (Free)',
                'provider' => 'Mistral AI',
                'cost' => 0,
                'cost_per_1m' => 0,
                'category' => 'free',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 32768,
                'description' => 'Modello francese gratuito, eccellente per lingue europee',
                'best_for' => 'Lingue europee, contenuti creativi',
                'languages_strong' => 'FR, EN, ES, DE, IT, PT',
                'translation_quality' => 7.5,
                'speed_rating' => 8,
                'free' => true
            ),
            
            'openchat/openchat-7b:free' => array(
                'name' => 'OpenChat 7B (Free)',
                'provider' => 'OpenChat',
                'cost' => 0,
                'cost_per_1m' => 0,
                'category' => 'free',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 8192,
                'description' => 'Modello open source veloce e affidabile',
                'best_for' => 'Traduzioni conversazionali, chat',
                'languages_strong' => 'EN, ZH, JA, KO, ES, FR',
                'translation_quality' => 7,
                'speed_rating' => 9,
                'free' => true
            ),
            
            'gryphe/mythomist-7b:free' => array(
                'name' => 'Mythomist 7B (Free)',
                'provider' => 'Gryphe',
                'cost' => 0,
                'cost_per_1m' => 0,
                'category' => 'free',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 32768,
                'description' => 'Modello specializzato in contenuti creativi',
                'best_for' => 'Traduzioni creative, letteratura, marketing',
                'languages_strong' => 'EN, ES, FR, DE, IT',
                'translation_quality' => 7,
                'speed_rating' => 8,
                'free' => true
            ),
            
            // === MODELLI ECONOMICI (Low-Cost) ===
            'meta-llama/llama-3.1-70b-instruct' => array(
                'name' => 'Llama 3.1 70B Instruct',
                'provider' => 'Meta',
                'cost' => 0.59,
                'cost_per_1m' => 0.59,
                'category' => 'low-cost',
                'speed' => 'medium',
                'quality' => 'excellent',
                'context_length' => 131072,
                'description' => 'Eccellente qualità traduzione, ottimo rapporto qualità/prezzo',
                'best_for' => 'Traduzioni professionali, contenuti complessi',
                'languages_strong' => 'Tutte le lingue principali',
                'translation_quality' => 9,
                'speed_rating' => 7,
                'free' => false
            ),
            
            'anthropic/claude-3-haiku' => array(
                'name' => 'Claude 3 Haiku',
                'provider' => 'Anthropic',
                'cost' => 0.80,
                'cost_per_1m' => 0.80,
                'category' => 'low-cost',
                'speed' => 'fast',
                'quality' => 'excellent',
                'context_length' => 200000,
                'description' => 'Veloce e preciso, ideale per traduzioni live',
                'best_for' => 'Traduzioni real-time, alta precisione',
                'languages_strong' => 'EN, ES, FR, DE, IT, PT, JA, ZH',
                'translation_quality' => 9,
                'speed_rating' => 9,
                'free' => false
            ),
            
            'openai/gpt-4o-mini' => array(
                'name' => 'GPT-4o Mini',
                'provider' => 'OpenAI',
                'cost' => 0.60,
                'cost_per_1m' => 0.60,
                'category' => 'low-cost',
                'speed' => 'fast',
                'quality' => 'excellent',
                'context_length' => 128000,
                'description' => 'Versione ottimizzata di GPT-4, veloce ed economica',
                'best_for' => 'Traduzioni generali, alta qualità',
                'languages_strong' => 'Tutte le lingue principali',
                'translation_quality' => 9,
                'speed_rating' => 8,
                'free' => false
            ),
            
            'google/gemini-flash-1.5' => array(
                'name' => 'Gemini Flash 1.5',
                'provider' => 'Google',
                'cost' => 0.40,
                'cost_per_1m' => 0.40,
                'category' => 'low-cost',
                'speed' => 'very-fast',
                'quality' => 'excellent',
                'context_length' => 1000000,
                'description' => 'Velocissimo e economico, contesto lungo',
                'best_for' => 'Documenti lunghi, traduzioni veloci',
                'languages_strong' => 'EN, ZH, JA, KO, HI, ES, FR, DE, PT, IT',
                'translation_quality' => 8.5,
                'speed_rating' => 10,
                'free' => false
            ),
            
            'mistralai/mistral-small' => array(
                'name' => 'Mistral Small',
                'provider' => 'Mistral AI',
                'cost' => 0.50,
                'cost_per_1m' => 0.50,
                'category' => 'low-cost',
                'speed' => 'fast',
                'quality' => 'very-good',
                'context_length' => 32768,
                'description' => 'Equilibrio perfetto tra velocità e qualità',
                'best_for' => 'Lingue europee, uso generale',
                'languages_strong' => 'FR, EN, ES, DE, IT, PT',
                'translation_quality' => 8,
                'speed_rating' => 8,
                'free' => false
            ),
            
            'cohere/command-light' => array(
                'name' => 'Command Light',
                'provider' => 'Cohere',
                'cost' => 0.30,
                'cost_per_1m' => 0.30,
                'category' => 'low-cost',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 4096,
                'description' => 'Modello economico per traduzioni semplici',
                'best_for' => 'Traduzioni base, contenuti brevi',
                'languages_strong' => 'EN, ES, FR, DE',
                'translation_quality' => 7,
                'speed_rating' => 8,
                'free' => false
            ),
            
            // === MODELLI PREMIUM ===
            'anthropic/claude-3-sonnet' => array(
                'name' => 'Claude 3 Sonnet',
                'provider' => 'Anthropic',
                'cost' => 15.00,
                'cost_per_1m' => 15.00,
                'category' => 'premium',
                'speed' => 'medium',
                'quality' => 'exceptional',
                'context_length' => 200000,
                'description' => 'Qualità eccezionale per traduzioni critiche',
                'best_for' => 'Traduzioni legali, mediche, tecniche',
                'languages_strong' => 'Tutte le lingue con alta precisione',
                'translation_quality' => 10,
                'speed_rating' => 6,
                'free' => false
            ),
            
            'anthropic/claude-3-opus' => array(
                'name' => 'Claude 3 Opus',
                'provider' => 'Anthropic',
                'cost' => 75.00,
                'cost_per_1m' => 75.00,
                'category' => 'premium',
                'speed' => 'slow',
                'quality' => 'exceptional',
                'context_length' => 200000,
                'description' => 'Massima qualità disponibile, per traduzioni critiche',
                'best_for' => 'Traduzioni di altissima qualità, contenuti critici',
                'languages_strong' => 'Tutte le lingue con precisione massima',
                'translation_quality' => 10,
                'speed_rating' => 4,
                'free' => false
            ),
            
            'openai/gpt-4o' => array(
                'name' => 'GPT-4o',
                'provider' => 'OpenAI',
                'cost' => 30.00,
                'cost_per_1m' => 30.00,
                'category' => 'premium',
                'speed' => 'medium',
                'quality' => 'exceptional',
                'context_length' => 128000,
                'description' => 'GPT-4 ottimizzato, eccellente per tutte le lingue',
                'best_for' => 'Traduzioni professionali, contenuti complessi',
                'languages_strong' => 'Tutte le lingue principali',
                'translation_quality' => 10,
                'speed_rating' => 6,
                'free' => false
            ),
            
            'google/gemini-pro-1.5' => array(
                'name' => 'Gemini Pro 1.5',
                'provider' => 'Google',
                'cost' => 20.00,
                'cost_per_1m' => 20.00,
                'category' => 'premium',
                'speed' => 'medium',
                'quality' => 'exceptional',
                'context_length' => 1000000,
                'description' => 'Contesto lunghissimo, ideale per documenti grandi',
                'best_for' => 'Documenti lunghi, mantenimento contesto',
                'languages_strong' => 'EN, ZH, JA, KO, HI, ES, FR, DE, PT, IT',
                'translation_quality' => 9.5,
                'speed_rating' => 6,
                'free' => false
            ),
            
            'meta-llama/llama-3.1-405b-instruct' => array(
                'name' => 'Llama 3.1 405B Instruct',
                'provider' => 'Meta',
                'cost' => 18.00,
                'cost_per_1m' => 18.00,
                'category' => 'premium',
                'speed' => 'slow',
                'quality' => 'exceptional',
                'context_length' => 131072,
                'description' => 'Modello più grande di Meta, qualità eccezionale',
                'best_for' => 'Traduzioni complesse, ragionamento avanzato',
                'languages_strong' => 'Tutte le lingue principali',
                'translation_quality' => 9.5,
                'speed_rating' => 3,
                'free' => false
            ),
            
            // === MODELLI SPECIALIZZATI ===
            'qwen/qwen-2-72b-instruct' => array(
                'name' => 'Qwen 2 72B Instruct',
                'provider' => 'Alibaba',
                'cost' => 0.90,
                'cost_per_1m' => 0.90,
                'category' => 'specialized',
                'speed' => 'medium',
                'quality' => 'excellent',
                'context_length' => 131072,
                'description' => 'Eccellente per lingue asiatiche',
                'best_for' => 'Cinese, giapponese, coreano, lingue asiatiche',
                'languages_strong' => 'ZH, JA, KO, EN, ES, FR',
                'translation_quality' => 9,
                'speed_rating' => 6,
                'free' => false
            ),
            
            'deepseek/deepseek-coder-v2' => array(
                'name' => 'DeepSeek Coder V2',
                'provider' => 'DeepSeek',
                'cost' => 0.27,
                'cost_per_1m' => 0.27,
                'category' => 'specialized',
                'speed' => 'fast',
                'quality' => 'good',
                'context_length' => 163840,
                'description' => 'Specializzato in codice e documentazione tecnica',
                'best_for' => 'Documentazione tecnica, commenti codice',
                'languages_strong' => 'EN, ZH, documentazione tecnica',
                'translation_quality' => 8,
                'speed_rating' => 8,
                'free' => false
            ),
            
            'nousresearch/hermes-3-llama-3.1-405b' => array(
                'name' => 'Hermes 3 Llama 3.1 405B',
                'provider' => 'Nous Research',
                'cost' => 18.00,
                'cost_per_1m' => 18.00,
                'category' => 'specialized',
                'speed' => 'slow',
                'quality' => 'exceptional',
                'context_length' => 131072,
                'description' => 'Modello fine-tuned per istruzioni complesse',
                'best_for' => 'Traduzioni con istruzioni specifiche',
                'languages_strong' => 'Tutte le lingue principali',
                'translation_quality' => 9.5,
                'speed_rating' => 3,
                'free' => false
            )
        );
    }
    
    /**
     * Enqueue assets per gestione modelli
     */
    public function enqueue_models_assets($hook) {
        if (strpos($hook, 'dynamic-translator') === false) {
            return;
        }
        
        wp_enqueue_script(
            'dpt-models-manager',
            DPT_PLUGIN_URL . 'assets/js/models-manager.js',
            array('jquery', 'jquery-ui-autocomplete'),
            DPT_VERSION,
            true
        );
        
        wp_enqueue_style(
            'dpt-models-manager',
            DPT_PLUGIN_URL . 'assets/css/models-manager.css',
            array(),
            DPT_VERSION
        );
        
        wp_localize_script('dpt-models-manager', 'dptModels', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dpt_models_nonce'),
            'strings' => array(
                'searching' => __('Cercando modelli...', 'dynamic-translator'),
                'noResults' => __('Nessun modello trovato', 'dynamic-translator'),
                'testModel' => __('Test Modello', 'dynamic-translator'),
                'testing' => __('Test in corso...', 'dynamic-translator'),
                'testSuccess' => __('Test riuscito!', 'dynamic-translator'),
                'testFailed' => __('Test fallito:', 'dynamic-translator')
            )
        ));
    }
    
    /**
     * AJAX: Cerca modelli
     */
    public function ajax_search_models() {
        check_ajax_referer('dpt_models_nonce', 'nonce');
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'all');
        $provider = sanitize_text_field($_POST['provider'] ?? 'all');
        $min_quality = intval($_POST['min_quality'] ?? 0);
        $max_cost = floatval($_POST['max_cost'] ?? 999);
        $speed = sanitize_text_field($_POST['speed'] ?? 'all');
        $free_only = isset($_POST['free_only']) && $_POST['free_only'] === 'true';
        
        $filtered_models = $this->filter_models($search, $category, $provider, $min_quality, $max_cost, $speed, $free_only);
        
        wp_send_json_success(array(
            'models' => $filtered_models,
            'total' => count($filtered_models)
        ));
    }
    
    /**
     * Filtra modelli in base ai criteri
     */
    private function filter_models($search, $category, $provider, $min_quality, $max_cost, $speed, $free_only) {
        $filtered = array();
        
        foreach ($this->models_list as $model_id => $model) {
            // Filtro free only
            if ($free_only && !$model['free']) {
                continue;
            }
            
            // Filtro categoria
            if ($category !== 'all' && $model['category'] !== $category) {
                continue;
            }
            
            // Filtro provider
            if ($provider !== 'all' && strtolower($model['provider']) !== strtolower($provider)) {
                continue;
            }
            
            // Filtro qualità minima
            if ($model['translation_quality'] < $min_quality) {
                continue;
            }
            
            // Filtro costo massimo
            if ($model['cost'] > $max_cost) {
                continue;
            }
            
            // Filtro velocità
            if ($speed !== 'all' && $model['speed'] !== $speed) {
                continue;
            }
            
            // Filtro ricerca testuale
            if (!empty($search)) {
                $searchable = strtolower($model['name'] . ' ' . $model['description'] . ' ' . $model['best_for'] . ' ' . $model['provider']);
                if (strpos($searchable, strtolower($search)) === false) {
                    continue;
                }
            }
            
            $model['id'] = $model_id;
            $filtered[$model_id] = $model;
        }
        
        // Ordina per qualità e velocità
        uasort($filtered, function($a, $b) {
            $score_a = $a['translation_quality'] + $a['speed_rating'];
            $score_b = $b['translation_quality'] + $b['speed_rating'];
            return $score_b <=> $score_a;
        });
        
        return $filtered;
    }
    
    /**
     * AJAX: Ottieni dettagli modello
     */
    public function ajax_get_model_details() {
        check_ajax_referer('dpt_models_nonce', 'nonce');
        
        $model_id = sanitize_text_field($_POST['model_id']);
        
        if (!isset($this->models_list[$model_id])) {
            wp_send_json_error('Modello non trovato');
        }
        
        $model = $this->models_list[$model_id];
        $model['id'] = $model_id;
        
        // Aggiungi statistiche di utilizzo se disponibili
        $stats = get_option('dpt_model_stats_' . md5($model_id), array(
            'usage_count' => 0,
            'avg_response_time' => 0,
            'success_rate' => 0,
            'last_used' => null
        ));
        
        $model['stats'] = $stats;
        
        wp_send_json_success($model);
    }
    
    /**
     * AJAX: Test traduzione modello
     */
    public function ajax_test_model_translation() {
        check_ajax_referer('dpt_models_nonce', 'nonce');
        
        $model_id = sanitize_text_field($_POST['model_id']);
        $test_text = sanitize_text_field($_POST['test_text'] ?? 'Hello world');
        $target_lang = sanitize_text_field($_POST['target_lang'] ?? 'it');
        
        if (!isset($this->models_list[$model_id])) {
            wp_send_json_error('Modello non trovato');
        }
        
        // Backup modello corrente
        $current_model = dpt_get_option('openrouter_model');
        
        // Cambia temporaneamente modello
        dpt_update_option('openrouter_model', $model_id);
        
        $start_time = microtime(true);
        
        // Esegui test traduzione
        $plugin = DynamicPageTranslator::get_instance();
        $api_handler = $plugin->get_api_handler();
        
        $translation = $api_handler->translate($test_text, 'en', $target_lang);
        
        $end_time = microtime(true);
        $duration = round(($end_time - $start_time) * 1000, 2);
        
        // Ripristina modello originale
        dpt_update_option('openrouter_model', $current_model);
        
        if (is_wp_error($translation)) {
            wp_send_json_error(array(
                'message' => $translation->get_error_message(),
                'duration' => $duration
            ));
        }
        
        // Salva statistiche test
        $this->save_model_test_stats($model_id, $duration, true);
        
        wp_send_json_success(array(
            'original' => $test_text,
            'translation' => $translation,
            'duration' => $duration,
            'model' => $this->models_list[$model_id]['name']
        ));
    }
    
    /**
     * AJAX: Aggiorna lista modelli da API
     */
    public function ajax_refresh_models_list() {
        check_ajax_referer('dpt_models_nonce', 'nonce');
        
        $api_key = dpt_get_option('openrouter_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error('API key OpenRouter non configurata');
        }
        
        // Recupera lista modelli da API OpenRouter
        $response = wp_remote_get('https://openrouter.ai/api/v1/models', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name')
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Errore connessione API: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['data'])) {
            wp_send_json_error('Risposta API non valida');
        }
        
        // Aggiorna cache modelli
        $updated_models = $this->merge_api_models($data['data']);
        
        update_option('dpt_cached_openrouter_models', $updated_models);
        update_option('dpt_models_last_update', current_time('mysql'));
        
        wp_send_json_success(array(
            'total_models' => count($updated_models),
            'updated_at' => current_time('mysql')
        ));
    }
    
    /**
     * Merge modelli da API con lista locale
     */
    private function merge_api_models($api_models) {
        $merged = $this->models_list;
        
        foreach ($api_models as $api_model) {
            $model_id = $api_model['id'];
            
            if (!isset($merged[$model_id])) {
                // Nuovo modello da API
                $merged[$model_id] = array(
                    'name' => $api_model['name'] ?? $model_id,
                    'provider' => $this->extract_provider($model_id),
                    'cost' => $this->extract_cost($api_model),
                    'cost_per_1m' => $this->extract_cost($api_model),
                    'category' => $this->categorize_model($api_model),
                    'speed' => 'medium',
                    'quality' => 'good',
                    'context_length' => $api_model['context_length'] ?? 4096,
                    'description' => 'Modello rilevato automaticamente',
                    'best_for' => 'Uso generale',
                    'languages_strong' => 'EN, ES, FR, DE',
                    'translation_quality' => 7,
                    'speed_rating' => 6,
                    'free' => $this->extract_cost($api_model) == 0
                );
            } else {
                // Aggiorna informazioni esistenti con dati API
                $merged[$model_id]['context_length'] = $api_model['context_length'] ?? $merged[$model_id]['context_length'];
                if (isset($api_model['pricing'])) {
                    $merged[$model_id]['cost'] = $this->extract_cost($api_model);
                    $merged[$model_id]['cost_per_1m'] = $this->extract_cost($api_model);
                }
            }
        }
        
        return $merged;
    }
    
    /**
     * Estrae provider dal model ID
     */
    private function extract_provider($model_id) {
        $providers = array(
            'meta-llama' => 'Meta',
            'anthropic' => 'Anthropic',
            'openai' => 'OpenAI',
            'google' => 'Google',
            'mistralai' => 'Mistral AI',
            'microsoft' => 'Microsoft',
            'cohere' => 'Cohere',
            'qwen' => 'Alibaba',
            'deepseek' => 'DeepSeek',
            'nousresearch' => 'Nous Research'
        );
        
        foreach ($providers as $prefix => $provider) {
            if (strpos($model_id, $prefix) === 0) {
                return $provider;
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Estrae costo dal modello API
     */
    private function extract_cost($api_model) {
        if (strpos($api_model['id'], ':free') !== false) {
            return 0;
        }
        
        if (isset($api_model['pricing']['prompt']) && is_numeric($api_model['pricing']['prompt'])) {
            return floatval($api_model['pricing']['prompt']) * 1000000; // Convert to per 1M tokens
        }
        
        return 1.0; // Default
    }
    
    /**
     * Categorizza modello automaticamente
     */
    private function categorize_model($api_model) {
        $cost = $this->extract_cost($api_model);
        
        if ($cost == 0) {
            return 'free';
        } elseif ($cost < 2.0) {
            return 'low-cost';
        } elseif ($cost < 10.0) {
            return 'premium';
        } else {
            return 'premium';
        }
    }
    
    /**
     * Salva statistiche test modello
     */
    private function save_model_test_stats($model_id, $duration, $success) {
        $stats_key = 'dpt_model_stats_' . md5($model_id);
        $stats = get_option($stats_key, array(
            'usage_count' => 0,
            'avg_response_time' => 0,
            'success_rate' => 0,
            'total_tests' => 0,
            'successful_tests' => 0,
            'last_used' => null
        ));
        
        $stats['total_tests']++;
        if ($success) {
            $stats['successful_tests']++;
        }
        $stats['usage_count']++;
        $stats['last_used'] = current_time('mysql');
        
        // Calcola media response time
        $stats['avg_response_time'] = (($stats['avg_response_time'] * ($stats['total_tests'] - 1)) + $duration) / $stats['total_tests'];
        
        // Calcola success rate
        $stats['success_rate'] = ($stats['successful_tests'] / $stats['total_tests']) * 100;
        
        update_option($stats_key, $stats);
    }
    
    /**
     * Ottiene tutti i modelli disponibili
     */
    public function get_all_models() {
        return $this->models_list;
    }
    
    /**
     * Ottiene modelli per categoria
     */
    public function get_models_by_category($category) {
        return array_filter($this->models_list, function($model) use ($category) {
            return $model['category'] === $category;
        });
    }
    
    /**
     * Ottiene modelli gratuiti
     */
    public function get_free_models() {
        return array_filter($this->models_list, function($model) {
            return $model['free'] === true;
        });
    }
    
    /**
     * Ottiene modelli raccomandati per traduzione
     */
    public function get_recommended_models($use_case = 'general') {
        $recommendations = array();
        
        switch ($use_case) {
            case 'speed':
                $recommendations = array(
                    'google/gemini-flash-1.5',
                    'anthropic/claude-3-haiku',
                    'meta-llama/llama-3.1-8b-instruct:free'
                );
                break;
                
            case 'quality':
                $recommendations = array(
                    'anthropic/claude-3-opus',
                    'anthropic/claude-3-sonnet',
                    'openai/gpt-4o'
                );
                break;
                
            case 'cost':
                $recommendations = array(
                    'meta-llama/llama-3.1-8b-instruct:free',
                    'google/gemma-2-9b-it:free',
                    'cohere/command-light'
                );
                break;
                
            default:
                $recommendations = array(
                    'anthropic/claude-3-haiku',
                    'meta-llama/llama-3.1-70b-instruct',
                    'google/gemini-flash-1.5'
                );
        }
        
        $recommended_models = array();
        foreach ($recommendations as $model_id) {
            if (isset($this->models_list[$model_id])) {
                $recommended_models[$model_id] = $this->models_list[$model_id];
            }
        }
        
        return $recommended_models;
    }
}

// Inizializza manager
new DPT_OpenRouter_Models_Manager();