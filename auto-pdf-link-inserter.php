<?php
/*
Plugin Name: Auto PDF Link Inserter
Description: Plugin para automatizar a inserção de links para PDFs com texto personalizado e permitir a reorganização por arrastar e soltar.
Version: 1.1
Author: Daniel Oliveira da Paixão
*/

// Função para adicionar a página de upload do PDF ao menu do admin
function apl_add_admin_menu() {
    add_menu_page('Auto PDF Link Inserter', 'PDF Link Inserter', 'manage_options', 'auto-pdf-link-inserter', 'apl_admin_page', 'dashicons-media-document', 20);
}
add_action('admin_menu', 'apl_add_admin_menu');

// Função para renderizar a página de upload do PDF
function apl_admin_page() {
    if (isset($_POST['apl_submit'])) {
        apl_handle_pdf_upload();
    }
    ?>
    <div class="wrap">
        <h1>Auto PDF Link Inserter</h1>
        <form method="post" enctype="multipart/form-data">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Selecione o PDF</th>
                    <td><input type="file" name="apl_pdf" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Texto do Link</th>
                    <td><input type="text" name="apl_link_text" /></td>
                </tr>
            </table>
            <?php submit_button('Upload e Inserir Link', 'primary', 'apl_submit'); ?>
        </form>
    </div>
    <?php
}

// Função para lidar com o upload do PDF e inserção do link
function apl_handle_pdf_upload() {
    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $uploadedfile = $_FILES['apl_pdf'];
    $upload_overrides = array('test_form' => false);

    $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

    if ($movefile && !isset($movefile['error'])) {
        $pdf_url = $movefile['url'];
        $link_text = sanitize_text_field($_POST['apl_link_text']);
        apl_insert_pdf_link($pdf_url, $link_text);
    } else {
        echo "Erro ao fazer o upload do arquivo: " . $movefile['error'];
    }
}

// Função para inserir o link do PDF na página
function apl_insert_pdf_link($pdf_url, $link_text) {
    // ID da página onde o link será inserido
    $page_id = 11340;
    $page = get_post($page_id);
    if ($page) {
        $content = $page->post_content;

        // Adicionando o link do PDF ao conteúdo da página
        $new_link = '<a href="' . esc_url($pdf_url) . '" class="btn">' . esc_html($link_text) . '</a>';
        $content .= "\n" . $new_link;

        // Atualizando o conteúdo da página
        wp_update_post(array(
            'ID' => $page_id,
            'post_content' => $content
        ));

        echo "Link inserido com sucesso!";
    } else {
        echo "Página não encontrada.";
    }
}

// Função para adicionar o CSS ao tema somente na página específica
function apl_enqueue_styles() {
    if (is_page(11340)) {
        echo '<style>
            .container {
                background: #f9f9f9;
                border: 2px solid #ccc;
                padding: 20px;
                border-radius: 10px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
                width: 80%;
                max-width: 600px;
            }
            .btn {
                display: block;
                width: 100%;
                margin: 10px 0;
                padding: 10px;
                text-align: center;
                background: #007bff;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                transition: background-color 0.3s;
            }
            .btn:hover {
                background: #0056b3;
            }
            .sortable .btn {
                cursor: move;
            }
        </style>';
    }
}
add_action('wp_head', 'apl_enqueue_styles');

// Função para adicionar scripts necessários
function apl_enqueue_scripts() {
    if (is_page(11340)) {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('apl-custom-scripts', plugin_dir_url(__FILE__) . 'auto-pdf-link.js', array('jquery', 'jquery-ui-sortable'), null, true);
    }
}
add_action('wp_enqueue_scripts', 'apl_enqueue_scripts');

// Shortcode para exibir os links e permitir a reorganização
function apl_display_links() {
    $page_id = 11340;
    $page = get_post($page_id);
    $content = $page->post_content;

    preg_match_all('/<a href="([^"]+)" class="btn">([^<]+)<\/a>/', $content, $matches, PREG_SET_ORDER);
    if (!empty($matches)) {
        $output = '<div class="container"><div class="sortable">';
        foreach ($matches as $match) {
            $output .= '<a href="' . esc_url($match[1]) . '" class="btn">' . esc_html($match[2]) . '</a>';
        }
        $output .= '</div></div>';
        return $output;
    } else {
        return '<div class="container">Nenhum link encontrado.</div>';
    }
}
add_shortcode('apl_display_links', 'apl_display_links');

// Função Ajax para atualizar a ordem dos links
function apl_update_links_order() {
    if (isset($_POST['sortedLinks']) && isset($_POST['pageId'])) {
        $sortedLinks = $_POST['sortedLinks'];
        $page_id = intval($_POST['pageId']);
        $page = get_post($page_id);
        $content = $page->post_content;

        preg_match_all('/<a href="([^"]+)" class="btn">([^<]+)<\/a>/', $content, $matches, PREG_SET_ORDER);
        $links = array();

        foreach ($matches as $match) {
            $links[$match[1]] = $match[2];
        }

        $new_content = '';

        foreach ($sortedLinks as $link) {
            $new_content .= '<a href="' . esc_url($link) . '" class="btn">' . esc_html($links[$link]) . '</a>' . "\n";
        }

        // Atualizando o conteúdo da página
        wp_update_post(array(
            'ID' => $page_id,
            'post_content' => $new_content
        ));

        wp_send_json_success('Ordem dos links atualizada.');
    } else {
        wp_send_json_error('Dados inválidos.');
    }
}
add_action('wp_ajax_apl_update_links_order', 'apl_update_links_order');

// JavaScript consolidado no PHP
function apl_custom_scripts() {
    if (is_page(11340)) {
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $(".sortable").sortable({
                    update: function(event, ui) {
                        var sortedLinks = [];
                        $(".sortable .btn").each(function() {
                            sortedLinks.push($(this).attr("href"));
                        });

                        var pageId = 11340;

                        $.ajax({
                            url: ajaxurl,
                            method: 'POST',
                            data: {
                                action: 'apl_update_links_order',
                                sortedLinks: sortedLinks,
                                pageId: pageId
                            },
                            success: function(response) {
                                console.log('Ordem dos links atualizada.');
                            },
                            error: function(error) {
                                console.log('Erro ao atualizar a ordem dos links.', error);
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }
}
add_action('wp_footer', 'apl_custom_scripts');
?>
