<?php
/**
 * Plugin Name: Articles Custom Post Type
 * Plugin URI: 
 * Description: Добавляет тип записей "Статьи" с URL /articles/[slug] и поддержкой сортировки
 * Version: 1.0.0
 * Author: AnnaTheMagister
 * Text Domain: articles-cpt
 */

// Защита от прямого доступа к файлу
if (!defined('ABSPATH')) {
    exit;
}

class Articles_CPT_Plugin
{

    /**
     * Конструктор - инициализация хуков
     */
    public function __construct()
    {
        // Регистрируем тип записи при инициализации
        add_action('init', array($this, 'register_post_type'));

        // Добавляем поддержку кастомных шаблонов для ACF
        add_filter('template_include', array($this, 'include_template'));

        // Обновляем правила перезаписи URL при активации плагина
        register_activation_hook(__FILE__, array($this, 'activate'));

        // Сбрасываем правила при деактивации
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Добавляем поддержку сортировки в админке
        add_action('pre_get_posts', array($this, 'admin_order_support'));

        // Добавляем колонку для сортировки в админке
        add_filter('manage_articles_posts_columns', array($this, 'add_sortable_columns'));
        add_filter('manage_edit-articles_sortable_columns', array($this, 'make_columns_sortable'));
    }

    /**
     * Регистрация типа записи "Статьи"
     */
    public function register_post_type()
    {
        $labels = array(
            'name' => __('Статьи', 'articles-cpt'),
            'singular_name' => __('Статья', 'articles-cpt'),
            'menu_name' => __('Статьи', 'articles-cpt'),
            'add_new' => __('Добавить статью', 'articles-cpt'),
            'add_new_item' => __('Добавить новую статью', 'articles-cpt'),
            'edit_item' => __('Редактировать статью', 'articles-cpt'),
            'new_item' => __('Новая статья', 'articles-cpt'),
            'view_item' => __('Просмотр статьи', 'articles-cpt'),
            'search_items' => __('Поиск статей', 'articles-cpt'),
            'not_found' => __('Статьи не найдены', 'articles-cpt'),
            'not_found_in_trash' => __('В корзине нет статей', 'articles-cpt'),
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array(
                'slug' => 'articles',
                'with_front' => false,
                'feeds' => true,
                'pages' => true
            ),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-media-document',
            'supports' => array(
                'title',
                'editor',
                'author',
                'thumbnail',
                'excerpt',
                'trackbacks',
                'custom-fields',
                'comments',
                'revisions',
                'page-attributes' // Добавляет поддержку сортировки (порядок страниц)
            ),
            'show_in_rest' => true, // Включает поддержку Gutenberg и REST API
            'taxonomies' => array('category', 'post_tag'), // Используем стандартные категории и теги
        );

        register_post_type('articles', $args);

        // Сбрасываем правила перезаписи URL
        flush_rewrite_rules();
    }

    /**
     * Поддержка шаблонов для ACF
     */
    public function include_template($template)
    {
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
     * Поддержка сортировки в админке
     */
    public function admin_order_support($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== 'articles') {
            return;
        }

        // Устанавливаем сортировку по умолчанию для админки
        if (!$query->get('orderby')) {
            $query->set('orderby', 'menu_order');
            $query->set('order', 'ASC');
        }
    }

    /**
     * Добавление колонок для сортировки
     */
    public function add_sortable_columns($columns)
    {
        $columns['menu_order'] = __('Порядок', 'articles-cpt');
        return $columns;
    }

    /**
     * Делаем колонки сортируемыми
     */
    public function make_columns_sortable($columns)
    {
        $columns['menu_order'] = 'menu_order';
        return $columns;
    }

    /**
     * Действия при активации плагина
     */
    public function activate()
    {
        // Регистрируем тип записи
        $this->register_post_type();

        // Сбрасываем правила перезаписи
        flush_rewrite_rules();
    }

    /**
     * Действия при деактивации плагина
     */
    public function deactivate()
    {
        // Сбрасываем правила перезаписи
        flush_rewrite_rules();
    }
}

// Инициализация плагина
new Articles_CPT_Plugin();