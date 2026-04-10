<?php
/**
 * Plugin Name: Articles Custom Post Type
 * Plugin URI: 
 * Description: Добавляет тип записей "Статьи" с поддержкой авторов текста и фото, интеграцией с Ultimate Member
 * Version: 2.0.0
 * Author: AnnaTheMagister
 * Text Domain: articles-cpt
 */

// Защита от прямого доступа к файлу
if (!defined('ABSPATH')) {
    exit;
}

class Articles_CPT_With_Authors {
    
    /**
     * Конструктор - инициализация хуков
     */
    public function __construct() {
        // Регистрируем тип записи при инициализации
        add_action('init', array($this, 'register_post_type'));
        
        // Добавляем метабоксы для авторов
        add_action('add_meta_boxes', array($this, 'add_authors_meta_boxes'));
        
        // Сохраняем данные авторов
        add_action('save_post_articles', array($this, 'save_authors_data'));
        
        // Добавляем подпись в конец статьи
        add_filter('the_content', array($this, 'add_authors_signature'));
        
        // Добавляем поддержку кастомных шаблонов для ACF
        add_filter('template_include', array($this, 'include_template'));
        
        // Добавляем колонки в админке
        add_filter('manage_articles_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_articles_posts_custom_column', array($this, 'display_admin_columns'), 10, 2);
        
        // Поддержка сортировки
        add_action('pre_get_posts', array($this, 'admin_order_support'));
        add_filter('manage_edit-articles_sortable_columns', array($this, 'make_columns_sortable'));
        
        // Регистрируем шорткоды
        add_shortcode('article_text_authors', array($this, 'render_text_authors_shortcode'));
        add_shortcode('article_photo_authors', array($this, 'render_photo_authors_shortcode'));
        
        // Обновляем правила перезаписи URL при активации плагина
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // Сбрасываем правила при деактивации
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Регистрация типа записи "Статьи"
     */
    public function register_post_type() {
        $labels = array(
            'name'               => __('Статьи', 'articles-cpt'),
            'singular_name'      => __('Статья', 'articles-cpt'),
            'menu_name'          => __('Статьи', 'articles-cpt'),
            'add_new'            => __('Добавить статью', 'articles-cpt'),
            'add_new_item'       => __('Добавить новую статью', 'articles-cpt'),
            'edit_item'          => __('Редактировать статью', 'articles-cpt'),
            'new_item'           => __('Новая статья', 'articles-cpt'),
            'view_item'          => __('Просмотр статьи', 'articles-cpt'),
            'search_items'       => __('Поиск статей', 'articles-cpt'),
            'not_found'          => __('Статьи не найдены', 'articles-cpt'),
            'not_found_in_trash' => __('В корзине нет статей', 'articles-cpt'),
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array(
                'slug'       => 'articles',
                'with_front' => false,
                'feeds'      => true,
                'pages'      => true
            ),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-media-document',
            'supports'            => array(
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'trackbacks',
                'custom-fields',
                'comments',
                'revisions',
                'page-attributes'
            ),
            'show_in_rest'        => true,
            'taxonomies'          => array('category', 'post_tag'),
        );
        
        register_post_type('articles', $args);
        flush_rewrite_rules();
    }
    
    /**
     * Добавление метабоксов для авторов
     */
    public function add_authors_meta_boxes() {
        add_meta_box(
            'article_text_authors_meta',
            __('Текст', 'articles-cpt'),
            array($this, 'render_text_authors_meta_box'),
            'articles',
            'normal',
            'high'
        );
        
        add_meta_box(
            'article_photo_authors_meta',
            __('Фото', 'articles-cpt'),
            array($this, 'render_photo_authors_meta_box'),
            'articles',
            'normal',
            'high'
        );
    }
    
    /**
     * Отображение метабокса для авторов текста
     */
    public function render_text_authors_meta_box($post) {
        wp_nonce_field('articles_text_authors_nonce', 'articles_text_authors_nonce');
        
        $saved_authors = get_post_meta($post->ID, '_article_text_authors', true);
        $saved_authors = is_array($saved_authors) ? $saved_authors : array();
        
        // Получаем всех пользователей из Ultimate Member
        $users = $this->get_um_users();
        
        ?>
        <div class="articles-text-authors-selector">
            <select name="article_text_authors[]" multiple style="width: 100%; min-height: 200px;">
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" 
                            <?php echo in_array($user->ID, $saved_authors) ? 'selected' : ''; ?>>
                        <?php echo esc_html($user->display_name); ?> 
                        (<?php echo esc_html($user->user_login); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Выберите авторов текста статьи (можно несколько). Зажмите Ctrl для множественного выбора.', 'articles-cpt'); ?></p>
        </div>
        
        <style>
            .articles-text-authors-selector select,
            .articles-photo-authors-selector select {
                max-height: 300px;
            }
        </style>
        <?php
    }
    
    /**
     * Отображение метабокса для авторов фото
     */
    public function render_photo_authors_meta_box($post) {
        wp_nonce_field('articles_photo_authors_nonce', 'articles_photo_authors_nonce');
        
        $saved_photo_authors = get_post_meta($post->ID, '_article_photo_authors', true);
        $saved_photo_authors = is_array($saved_photo_authors) ? $saved_photo_authors : array();
        
        // Получаем всех пользователей из Ultimate Member
        $users = $this->get_um_users();
        
        ?>
        <div class="articles-photo-authors-selector">
            <select name="article_photo_authors[]" multiple style="width: 100%; min-height: 200px;">
                <?php foreach ($users as $user) : ?>
                    <option value="<?php echo esc_attr($user->ID); ?>" 
                            <?php echo in_array($user->ID, $saved_photo_authors) ? 'selected' : ''; ?>>
                        <?php echo esc_html($user->display_name); ?> 
                        (<?php echo esc_html($user->user_login); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description"><?php _e('Выберите авторов фото (можно несколько). Зажмите Ctrl для множественного выбора.', 'articles-cpt'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Получение всех пользователей (с поддержкой Ultimate Member)
     */
    private function get_um_users() {
        $args = array(
            'number' => -1,
            'orderby' => 'display_name',
            'order' => 'ASC'
        );
        
        // Если Ultimate Member активен, используем его функции
        if (function_exists('um_get_users')) {
            $users = um_get_users($args);
            return isset($users['users']) ? $users['users'] : array();
        }
        
        // Стандартный WP запрос
        return get_users($args);
    }
    
    /**
     * Сохранение данных авторов
     */
    public function save_authors_data($post_id) {
        // Проверяем nonce для авторов текста
        if (isset($_POST['articles_text_authors_nonce']) && wp_verify_nonce($_POST['articles_text_authors_nonce'], 'articles_text_authors_nonce')) {
            if (isset($_POST['article_text_authors'])) {
                $authors = array_map('intval', $_POST['article_text_authors']);
                update_post_meta($post_id, '_article_text_authors', $authors);
            } else {
                update_post_meta($post_id, '_article_text_authors', array());
            }
        }
        
        // Проверяем nonce для авторов фото
        if (isset($_POST['articles_photo_authors_nonce']) && wp_verify_nonce($_POST['articles_photo_authors_nonce'], 'articles_photo_authors_nonce')) {
            if (isset($_POST['article_photo_authors'])) {
                $photo_authors = array_map('intval', $_POST['article_photo_authors']);
                update_post_meta($post_id, '_article_photo_authors', $photo_authors);
            } else {
                update_post_meta($post_id, '_article_photo_authors', array());
            }
        }
    }
    
    /**
     * Получение URL профиля пользователя в Ultimate Member
     */
    private function get_user_profile_url($user_id) {
        if (function_exists('um_user_profile_url')) {
            return um_user_profile_url($user_id);
        }
        
        // Стандартный URL профиля WP
        return get_author_posts_url($user_id);
    }
    
    /**
     * Получение HTML подписи с авторами
     */
    public function get_authors_signature_html($post_id) {
        $text_authors = get_post_meta($post_id, '_article_text_authors', true);
        $photo_authors = get_post_meta($post_id, '_article_photo_authors', true);
        
        $signature_parts = array();
        
        // Формируем подпись для авторов текста
        if (!empty($text_authors) && is_array($text_authors)) {
            $text_names = array();
            foreach ($text_authors as $author_id) {
                $user = get_userdata($author_id);
                if ($user) {
                    $profile_url = $this->get_user_profile_url($author_id);
                    $text_names[] = sprintf(
                        '<a href="%s" class="author-link" target="_blank">%s</a>',
                        esc_url($profile_url),
                        esc_html($user->display_name)
                    );
                }
            }
            
            if (!empty($text_names)) {
                $signature_parts[] = sprintf(
                    '<div class="signature-text-authors">
                        <span class="signature-label">Текст:</span>
                        <span class="signature-names">%s</span>
                    </div>',
                    implode(', ', $text_names)
                );
            }
        }
        
        // Формируем подпись для авторов фото
        if (!empty($photo_authors) && is_array($photo_authors)) {
            $photo_names = array();
            foreach ($photo_authors as $author_id) {
                $user = get_userdata($author_id);
                if ($user) {
                    $profile_url = $this->get_user_profile_url($author_id);
                    $photo_names[] = sprintf(
                        '<a href="%s" class="author-link" target="_blank">%s</a>',
                        esc_url($profile_url),
                        esc_html($user->display_name)
                    );
                }
            }
            
            if (!empty($photo_names)) {
                $signature_parts[] = sprintf(
                    '<div class="signature-photo-authors">
                        <span class="signature-label">Фото:</span>
                        <span class="signature-names">%s</span>
                    </div>',
                    implode(', ', $photo_names)
                );
            }
        }
        
        if (empty($signature_parts)) {
            return '';
        }
        
        return sprintf(
            '<div class="article-authors-signature">
                <div class="signature-title">Авторы</div>
                %s
            </div>',
            implode('', $signature_parts)
        );
    }
    
    /**
     * Добавление подписи в конец статьи
     */
    public function add_authors_signature($content) {
        // Добавляем подпись только для одиночных записей типа 'articles'
        if (is_singular('articles') && in_the_loop() && is_main_query()) {
            $signature = $this->get_authors_signature_html(get_the_ID());
            if (!empty($signature)) {
                $content .= $signature;
            }
        }
        
        return $content;
    }
    
    /**
     * Шорткод для вывода авторов текста
     */
    public function render_text_authors_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => get_the_ID(),
        ), $atts);
        
        $text_authors = get_post_meta($atts['id'], '_article_text_authors', true);
        
        if (empty($text_authors) || !is_array($text_authors)) {
            return '';
        }
        
        $names = array();
        foreach ($text_authors as $author_id) {
            $user = get_userdata($author_id);
            if ($user) {
                $profile_url = $this->get_user_profile_url($author_id);
                $names[] = sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($profile_url),
                    esc_html($user->display_name)
                );
            }
        }
        
        return sprintf(
            '<div class="shortcode-text-authors"><strong>Текст:</strong> %s</div>',
            implode(', ', $names)
        );
    }
    
    /**
     * Шорткод для вывода авторов фото
     */
    public function render_photo_authors_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => get_the_ID(),
        ), $atts);
        
        $photo_authors = get_post_meta($atts['id'], '_article_photo_authors', true);
        
        if (empty($photo_authors) || !is_array($photo_authors)) {
            return '';
        }
        
        $names = array();
        foreach ($photo_authors as $author_id) {
            $user = get_userdata($author_id);
            if ($user) {
                $profile_url = $this->get_user_profile_url($author_id);
                $names[] = sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($profile_url),
                    esc_html($user->display_name)
                );
            }
        }
        
        return sprintf(
            '<div class="shortcode-photo-authors"><strong>Фото:</strong> %s</div>',
            implode(', ', $names)
        );
    }
    
    /**
     * Добавление колонок в админке
     */
    public function add_admin_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['text_authors'] = __('Текст', 'articles-cpt');
                $new_columns['photo_authors'] = __('Фото', 'articles-cpt');
            }
        }
        
        $new_columns['menu_order'] = __('Порядок', 'articles-cpt');
        return $new_columns;
    }
    
    /**
     * Отображение данных в колонках админки
     */
    public function display_admin_columns($column, $post_id) {
        switch ($column) {
            case 'text_authors':
                $authors = get_post_meta($post_id, '_article_text_authors', true);
                $this->display_authors_names($authors);
                break;
                
            case 'photo_authors':
                $photo_authors = get_post_meta($post_id, '_article_photo_authors', true);
                $this->display_authors_names($photo_authors);
                break;
        }
    }
    
    /**
     * Отображение имен авторов в админке
     */
    private function display_authors_names($authors_ids) {
        if (empty($authors_ids)) {
            echo '—';
            return;
        }
        
        $names = array();
        foreach ($authors_ids as $author_id) {
            $user = get_userdata($author_id);
            if ($user) {
                $profile_url = $this->get_user_profile_url($author_id);
                $names[] = sprintf(
                    '<a href="%s" target="_blank">%s</a>',
                    esc_url($profile_url),
                    esc_html($user->display_name)
                );
            }
        }
        
        echo implode(', ', $names);
    }
    
    /**
     * Поддержка сортировки в админке
     */
    public function admin_order_support($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        if ($query->get('post_type') !== 'articles') {
            return;
        }
        
        if (!$query->get('orderby')) {
            $query->set('orderby', 'menu_order');
            $query->set('order', 'ASC');
        }
    }
    
    /**
     * Делаем колонки сортируемыми
     */
    public function make_columns_sortable($columns) {
        $columns['menu_order'] = 'menu_order';
        return $columns;
    }
    
    /**
     * Поддержка шаблонов
     */
    public function include_template($template) {
        if (is_singular('articles')) {
            $theme_template = locate_template(array(
                'single-articles.php',
                'single.php',
                'page.php',
                'index.php'
            ));
            
            if (!empty($theme_template)) {
                return $theme_template;
            }
        }
        
        if (is_post_type_archive('articles')) {
            $theme_template = locate_template(array(
                'archive-articles.php',
                'archive.php',
                'index.php'
            ));
            
            if (!empty($theme_template)) {
                return $theme_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Активация плагина
     */
    public function activate() {
        $this->register_post_type();
        flush_rewrite_rules();
    }
    
    /**
     * Деактивация плагина
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
}

// Инициализация плагина
new Articles_CPT_With_Authors();

// Добавляем CSS для отображения подписи
add_action('wp_enqueue_scripts', 'articles_authors_signature_styles');
function articles_authors_signature_styles() {
    if (is_singular('articles')) {
        ?>
        <style>
            .article-authors-signature {
                margin-top: 40px;
                padding: 20px;
                background: #f8f9fa;
                border-left: 4px solid #0073aa;
                border-radius: 4px;
                font-family: inherit;
            }
            
            .signature-title {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 15px;
                color: #333;
                padding-bottom: 10px;
                border-bottom: 2px solid #0073aa;
                display: inline-block;
            }
            
            .signature-text-authors,
            .signature-photo-authors {
                margin-bottom: 12px;
                line-height: 1.6;
            }
            
            .signature-label {
                font-weight: 600;
                color: #555;
                margin-right: 10px;
                font-size: 15px;
            }
            
            .signature-names {
                color: #666;
            }
            
            .author-link {
                color: #0073aa;
                text-decoration: none;
                transition: color 0.2s ease;
            }
            
            .author-link:hover {
                color: #005177;
                text-decoration: underline;
            }
            
            /* Стили для шорткодов */
            .shortcode-text-authors,
            .shortcode-photo-authors {
                margin: 10px 0;
                padding: 8px 12px;
                background: #f5f5f5;
                border-radius: 4px;
            }
            
            /* Адаптивность для мобильных устройств */
            @media (max-width: 768px) {
                .article-authors-signature {
                    padding: 15px;
                    margin-top: 30px;
                }
                
                .signature-title {
                    font-size: 16px;
                }
                
                .signature-label,
                .signature-names {
                    font-size: 14px;
                }
            }
        </style>
        <?php
    }
}

// Добавляем поддержку ACF (если активен)
add_action('acf/include_fields', function() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_articles_authors',
            'title' => 'Авторы',
            'fields' => array(
                array(
                    'key' => 'field_article_text_authors',
                    'label' => 'Текст',
                    'name' => 'article_text_authors',
                    'type' => 'user',
                    'instructions' => 'Выберите авторов текста',
                    'required' => 0,
                    'multiple' => 1,
                    'role' => array('all'),
                ),
                array(
                    'key' => 'field_article_photo_authors',
                    'label' => 'Фото',
                    'name' => 'article_photo_authors',
                    'type' => 'user',
                    'instructions' => 'Выберите авторов фото',
                    'required' => 0,
                    'multiple' => 1,
                    'role' => array('all'),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'articles',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
        ));
    }
});
?>