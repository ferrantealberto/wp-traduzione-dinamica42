<?php
/**
 * Gestore Performance e Traduzione Live per Dynamic Page Translator
 * File: includes/class-performance-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class DPT_Performance_Manager {
    
    private $translation_queue = array();
    private $batch_size = 20;
    private $max_concurrent = 3;
    private $cache_preload = true;
    private $live_translation = true;
    
    public function __construct() {
        $this->init_hooks();
        $this->init_performance_settings();
    }
    
    /**
     * Inizializza hook per performance
     */
    private function init_hooks() {
        // Queue processing
        add_action('wp_ajax_dpt_process_translation_queue', array($this, 'ajax_process_queue'));
        add_action('wp_ajax_nopriv_dpt_process_translation_queue', array($this, 'ajax_process_queue'));
        
        // Live translation
        add_action('wp_ajax_dpt_live_translate', array($this, 'ajax_live_translate'));
        add_action('wp_ajax_nopriv_dpt_live_translate', array($this, 'ajax_live_translate'));
        
        // Batch translation
        add_action('wp_ajax_dpt_batch_translate', array($this, 'ajax_batch_translate'));
        add_action('wp_ajax_nopriv_dpt_batch_translate', array($this, 'ajax_batch_translate'));
        
        // Preload cache
        add_action('wp_head', array($this, 'preload_common_translations'), 1);
        
        // Background processing
        add_action('dpt_process_background_translations', array($this, 'process_background_translations'));
        
        // Performance monitoring
        add_action('dpt_translation_completed', array($this, 'track_translation_performance'), 10, 3);
    }
    
    /**
     * Inizializza impostazioni performance
     */
    private function init_performance_settings() {
        $this->batch_size = dpt_get_option('performance_batch_size', 20);
        $this->max_concurrent = dpt_get_option('performance_max_concurrent', 3);
        $this->cache_preload = dpt_get_option('performance_cache_preload', true);
        $this->live_translation = dpt_get_option('performance_live_translation', true);
    }
    
    /**
     * AJAX Traduzione Live Ultra-Veloce
     */
    public function ajax_live_translate() {
        // Verifiche di sicurezza rapide
        if (!wp_verify_nonce($_POST['nonce'], 'dpt_frontend_nonce')) {
            wp_send_json_error(array('message' => 'Security error', 'code' => 'invalid_nonce'));
        }
        
        $content = sanitize_textarea_field($_POST['content']);
        $source_lang = sanitize_text_field($_POST['source_lang']);
        $target_lang = sanitize_text_field($_POST['target_lang']);
        $priority = sanitize_text_field($_POST['priority'] ?? 'normal'); // high, normal, low
        
        // Validazione ultra-rapida
        if (empty($content) || $source_lang === $target_lang) {
            wp_send_json_success(array('translation' => $content, 'cached' => false, 'time' => 0));
        }
        
        $start_time = microtime(true);
        
        // 1. Cache check ultra-veloce
        $cache_key = $this->generate_fast_cache_key($content, $source_lang, $target_lang);
        $cached = $this->get_fast_cache($cache_key);
        
        if ($cached !== false) {
            wp_send_json_success(array(
                'translation' => $cached,
                'cached' => true,
                'time' => round((microtime(true) - $start_time) * 1000, 2)
            ));
        }
        
        // 2. Dizionario check
        $dictionary_result = $this->check_dictionary_translation($content, $target_lang);
        if ($dictionary_result !== false) {
            $this->set_fast_cache($cache_key, $dictionary_result);
            wp_send_json_success(array(
                'translation' => $dictionary_result,
                'cached' => false,
                'dictionary' => true,
                'time' => round((microtime(true) - $start_time) * 1000, 2)
            ));
        }
        
        // 3. Traduzione API ottimizzata
        $translation = $this->fast_translate($content, $source_lang, $target_lang, $priority);
        
        if (is_wp_error($translation)) {
            wp_send_json_error(array(
                'message' => $translation->get_error_message(),
                'code' => $translation->get_error_code(),
                'time' => round((microtime(true) - $start_time) * 1000, 2)
            ));
        }
        
        // 4. Salva cache veloce
        $this->set_fast_cache($cache_key, $translation);
        
        wp_send_json_success(array(
            'translation' => $translation,
            'cached' => false,
            'time' => round((microtime(true) - $start_time) * 1000, 2)
        ));
    }
    
    /**
     * Traduzione ottimizzata per velocità
     */
    private function fast_translate($content, $source_lang, $target_lang, $priority = 'normal') {
        $plugin = DynamicPageTranslator::get_instance();
        $api_handler = $plugin->get_api_handler();
        
        // Ottimizzazioni per velocità
        $original_timeout = ini_get('max_execution_time');
        ini_set('max_execution_time', 15); // Timeout ridotto
        
        // Usa modello più veloce per priorità alta
        if ($priority === 'high') {
            $this->switch_to_fast_model();
        }
        
        $translation = $api_handler->translate($content, $source_lang, $target_lang);
        
        // Ripristina timeout
        ini_set('max_execution_time', $original_timeout);
        
        return $translation;
    }
    
    /**
     * Switch a modello veloce per priorità alta
     */
    private function switch_to_fast_model() {
        $current_provider = dpt_get_option('translation_provider');
        
        if ($current_provider === 'openrouter') {
            // Modelli ultra-veloci
            $fast_models = array(
                'meta-llama/llama-3.1-8b-instruct:free',
                'google/gemma-2-9b-it:free',
                'microsoft/wizardlm-2-8x22b:free'
            );
            
            $current_model = dpt_get_option('openrouter_model');
            $fast_model = $fast_models[0]; // Usa il più veloce
            
            // Cambia temporaneamente modello
            dpt_update_option('openrouter_model_temp', $fast_model);
        }
    }
    
    /**
     * AJAX Traduzione Batch
     */
    public function ajax_batch_translate() {
        if (!wp_verify_nonce($_POST['nonce'], 'dpt_frontend_nonce')) {
            wp_send_json_error('Security error');
        }
        
        $items = json_decode(stripslashes($_POST['items']), true);
        $source_lang = sanitize_text_field($_POST['source_lang']);
        $target_lang = sanitize_text_field($_POST['target_lang']);
        
        if (!is_array($items) || empty($items)) {
            wp_send_json_error('Invalid items');
        }
        
        $start_time = microtime(true);
        $results = array();
        $batch_items = array();
        
        // 1. Separa elementi cached da quelli da tradurre
        foreach ($items as $index => $item) {
            $cache_key = $this->generate_fast_cache_key($item['content'], $source_lang, $target_lang);
            $cached = $this->get_fast_cache($cache_key);
            
            if ($cached !== false) {
                $results[$index] = array(
                    'translation' => $cached,
                    'cached' => true,
                    'index' => $index
                );
            } else {
                $batch_items[$index] = $item;
            }
        }
        
        // 2. Traduzione batch per elementi non cached
        if (!empty($batch_items)) {
            $batch_results = $this->translate_batch_optimized($batch_items, $source_lang, $target_lang);
            
            foreach ($batch_results as $index => $result) {
                $results[$index] = $result;
                
                // Cache risultato
                if (!is_wp_error($result['translation'])) {
                    $cache_key = $this->generate_fast_cache_key($batch_items[$index]['content'], $source_lang, $target_lang);
                    $this->set_fast_cache($cache_key, $result['translation']);
                }
            }
        }
        
        // 3. Ordina risultati per index
        ksort($results);
        
        wp_send_json_success(array(
            'results' => array_values($results),
            'total_time' => round((microtime(true) - $start_time) * 1000, 2),
            'cached_count' => count($results) - count($batch_items),
            'translated_count' => count($batch_items)
        ));
    }
    
    /**
     * Traduzione batch ottimizzata
     */
    private function translate_batch_optimized($items, $source_lang, $target_lang) {
        $plugin = DynamicPageTranslator::get_instance();
        $api_handler = $plugin->get_api_handler();
        $results = array();
        
        // Raggruppa per dimensione simile per ottimizzare API calls
        $grouped_items = $this->group_items_by_size($items);
        
        foreach ($grouped_items as $group) {
            $group_results = $this->translate_group($group, $source_lang, $target_lang);
            $results = array_merge($results, $group_results);
        }
        
        return $results;
    }
    
    /**
     * Raggruppa elementi per dimensione
     */
    private function group_items_by_size($items) {
        $groups = array('small' => array(), 'medium' => array(), 'large' => array());
        
        foreach ($items as $index => $item) {
            $length = strlen($item['content']);
            
            if ($length < 50) {
                $groups['small'][$index] = $item;
            } elseif ($length < 200) {
                $groups['medium'][$index] = $item;
            } else {
                $groups['large'][$index] = $item;
            }
        }
        
        return array_filter($groups);
    }
    
    /**
     * Traduce gruppo di elementi
     */
    private function translate_group($group, $source_lang, $target_lang) {
        $results = array();
        
        // Per gruppi piccoli, usa traduzione batch API
        if (count($group) <= 5) {
            $contents = array_column($group, 'content');
            $translations = $this->api_batch_translate($contents, $source_lang, $target_lang);
            
            $i = 0;
            foreach ($group as $index => $item) {
                $results[$index] = array(
                    'translation' => $translations[$i] ?? $item['content'],
                    'cached' => false,
                    'index' => $index
                );
                $i++;
            }
        } else {
            // Per gruppi grandi, processa in parallelo simulato
            foreach ($group as $index => $item) {
                $translation = $this->fast_translate($item['content'], $source_lang, $target_lang);
                
                $results[$index] = array(
                    'translation' => is_wp_error($translation) ? $item['content'] : $translation,
                    'cached' => false,
                    'index' => $index
                );
            }
        }
        
        return $results;
    }
    
    /**
     * API batch translate (implementazione provider-specifica)
     */
    private function api_batch_translate($contents, $source_lang, $target_lang) {
        $provider = dpt_get_option('translation_provider');
        
        if ($provider === 'google') {
            return $this->google_batch_translate($contents, $source_lang, $target_lang);
        } elseif ($provider === 'openrouter') {
            return $this->openrouter_batch_translate($contents, $source_lang, $target_lang);
        }
        
        // Fallback: traduzione singola
        $results = array();
        foreach ($contents as $content) {
            $results[] = $this->fast_translate($content, $source_lang, $target_lang);
        }
        
        return $results;
    }
    
    /**
     * Google batch translate
     */
    private function google_batch_translate($contents, $source_lang, $target_lang) {
        $api_key = dpt_get_option('google_api_key');
        
        if (empty($api_key)) {
            return array_fill(0, count($contents), '');
        }
        
        $params = array(
            'key' => $api_key,
            'target' => $target_lang,
            'format' => 'text'
        );
        
        if ($source_lang !== 'auto') {
            $params['source'] = $source_lang;
        }
        
        // Aggiungi tutti i contenuti
        foreach ($contents as $content) {
            $params['q'][] = $content;
        }
        
        $response = wp_remote_post('https://translation.googleapis.com/language/translate/v2', array(
            'timeout' => 20,
            'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
            'body' => http_build_query($params)
        ));
        
        if (is_wp_error($response)) {
            return array_fill(0, count($contents), '');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['data']['translations'])) {
            return array_fill(0, count($contents), '');
        }
        
        $results = array();
        foreach ($data['data']['translations'] as $translation) {
            $results[] = html_entity_decode($translation['translatedText'], ENT_QUOTES, 'UTF-8');
        }
        
        return $results;
    }
    
    /**
     * OpenRouter batch translate
     */
    private function openrouter_batch_translate($contents, $source_lang, $target_lang) {
        $api_key = dpt_get_option('openrouter_api_key');
        $model = dpt_get_option('openrouter_model');
        
        if (empty($api_key)) {
            return array_fill(0, count($contents), '');
        }
        
        $source_name = $this->get_language_name($source_lang);
        $target_name = $this->get_language_name($target_lang);
        
        // Crea prompt batch
        $batch_text = implode("\n---\n", $contents);
        $prompt = "Translate the following texts from {$source_name} to {$target_name}. Maintain the exact order and separate translations with '---'. Only respond with translations:\n\n{$batch_text}";
        
        $request_data = array(
            'model' => $model,
            'messages' => array(
                array('role' => 'system', 'content' => 'You are a professional translator. Respond only with translations.'),
                array('role' => 'user', 'content' => $prompt)
            ),
            'temperature' => 0,
            'max_tokens' => strlen($batch_text) * 2
        );
        
        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($request_data)
        ));
        
        if (is_wp_error($response)) {
            return array_fill(0, count($contents), '');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return array_fill(0, count($contents), '');
        }
        
        $translated_batch = trim($data['choices'][0]['message']['content']);
        $translations = explode('---', $translated_batch);
        
        // Assicura stesso numero di traduzioni
        while (count($translations) < count($contents)) {
            $translations[] = '';
        }
        
        return array_slice($translations, 0, count($contents));
    }
    
    /**
     * Cache veloce in-memory + transients
     */
    private function get_fast_cache($key) {
        static $memory_cache = array();
        
        // 1. Memory cache (più veloce)
        if (isset($memory_cache[$key])) {
            return $memory_cache[$key];
        }
        
        // 2. Transient cache
        $cached = get_transient('dpt_fast_' . $key);
        if ($cached !== false) {
            $memory_cache[$key] = $cached;
            return $cached;
        }
        
        return false;
    }
    
    /**
     * Imposta cache veloce
     */
    private function set_fast_cache($key, $value) {
        static $memory_cache = array();
        
        // Memory cache
        $memory_cache[$key] = $value;
        
        // Transient cache (24 ore)
        set_transient('dpt_fast_' . $key, $value, 24 * HOUR_IN_SECONDS);
    }
    
    /**
     * Genera chiave cache veloce
     */
    private function generate_fast_cache_key($content, $source_lang, $target_lang) {
        return md5($content . $source_lang . $target_lang . dpt_get_option('translation_provider'));
    }
    
    /**
     * Precarica traduzioni comuni
     */
    public function preload_common_translations() {
        if (!$this->cache_preload) {
            return;
        }
        
        $common_phrases = $this->get_common_phrases();
        $current_lang = $this->get_current_language();
        $default_lang = dpt_get_option('default_language', 'en');
        
        if ($current_lang === $default_lang) {
            return;
        }
        
        // Precarica in background
        wp_schedule_single_event(time() + 5, 'dpt_preload_translations', array($common_phrases, $default_lang, $current_lang));
    }
    
    /**
     * Frasi comuni da precaricare
     */
    private function get_common_phrases() {
        return array(
            'Read more', 'Continue reading', 'Previous', 'Next', 'Search', 'Submit',
            'Contact', 'About', 'Home', 'Menu', 'Close', 'Open', 'Back', 'Loading',
            'Add to cart', 'Buy now', 'Price', 'Sale', 'New', 'Featured',
            'Categories', 'Tags', 'Archive', 'Page', 'Post', 'Comments'
        );
    }
    
    /**
     * Check dizionario personalizzato
     */
    private function check_dictionary_translation($content, $target_lang) {
        $dictionary = get_option('dpt_custom_dictionary', array());
        
        if (!isset($dictionary[$target_lang])) {
            return false;
        }
        
        $lang_dict = $dictionary[$target_lang];
        
        // Check traduzioni esatte
        if (isset($lang_dict['exact'][$content])) {
            return $lang_dict['exact'][$content];
        }
        
        // Check sostituzioni parziali
        if (isset($lang_dict['partial'])) {
            $result = $content;
            foreach ($lang_dict['partial'] as $search => $replace) {
                $result = str_replace($search, $replace, $result);
            }
            
            if ($result !== $content) {
                return $result;
            }
        }
        
        return false;
    }
    
    /**
     * Track performance traduzione
     */
    public function track_translation_performance($content, $translation, $time_taken) {
        $stats = get_option('dpt_performance_stats', array(
            'total_translations' => 0,
            'total_time' => 0,
            'average_time' => 0,
            'fastest_time' => 999999,
            'slowest_time' => 0
        ));
        
        $stats['total_translations']++;
        $stats['total_time'] += $time_taken;
        $stats['average_time'] = $stats['total_time'] / $stats['total_translations'];
        $stats['fastest_time'] = min($stats['fastest_time'], $time_taken);
        $stats['slowest_time'] = max($stats['slowest_time'], $time_taken);
        
        update_option('dpt_performance_stats', $stats);
    }
    
    /**
     * Utility functions
     */
    private function get_current_language() {
        return isset($_COOKIE['dpt_current_lang']) ? 
            sanitize_text_field($_COOKIE['dpt_current_lang']) : 
            dpt_get_option('default_language', 'en');
    }
    
    private function get_language_name($lang_code) {
        $names = array(
            'en' => 'English', 'it' => 'Italian', 'es' => 'Spanish', 'fr' => 'French',
            'de' => 'German', 'pt' => 'Portuguese', 'ru' => 'Russian', 'zh' => 'Chinese',
            'ja' => 'Japanese', 'ar' => 'Arabic', 'auto' => 'auto-detect'
        );
        
        return $names[$lang_code] ?? $lang_code;
    }
    
    /**
     * Ottiene statistiche performance
     */
    public function get_performance_stats() {
        return get_option('dpt_performance_stats', array(
            'total_translations' => 0,
            'total_time' => 0,
            'average_time' => 0,
            'fastest_time' => 0,
            'slowest_time' => 0
        ));
    }
}

// Inizializza performance manager
new DPT_Performance_Manager();