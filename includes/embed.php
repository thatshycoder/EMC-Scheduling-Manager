<?php

// Exit if accessed directly
defined('ABSPATH') || exit;

class EMCS_Embed
{
    private $atts;
    private $url;

    public function __construct($atts)
    {
        $this->atts = $atts;
        $this->url = isset($atts['url']) ? esc_url_raw($atts['url']) : '';
        $url_parts = [];

        if (!empty($this->url)) {

            if (!empty($atts['prefill_fields'])) {
                $this->url = $this->prefill_fields($this->url);
            }

            if (!empty($atts['cookie_banner'])) {
                $url_parts[] = 'hide_gdpr_banner=1';
            }

            if (!empty($atts['hide_details'])) {
                $url_parts[] = 'hide_event_type_details=1';
            }
        }

        if (!empty($url_parts)) {
            $this->url = $this->prepare_embed_url($this->url, $url_parts);
        }

        if (!defined('EMCS_BUTTON_EMBED_TYPE')) {
            define('EMCS_BUTTON_EMBED_TYPE', 2);
        }

        if (!defined('EMCS_POPUP_TEXT_EMBED_TYPE')) {
            define('EMCS_POPUP_TEXT_EMBED_TYPE', 3);
        }
    }

    public function embed_calendar()
    {
        if (!empty($this->atts)) {

            do_action('emcp_before_calendar_embed', $this->url);

            $sanitized_atts = $this->clean_shortcode_atts($this->atts);

            if ($sanitized_atts) {

                switch ($sanitized_atts['embed_type']) {
                    case EMCS_BUTTON_EMBED_TYPE:

                        if ($sanitized_atts['button_style'] == 1) {
                            return $this->embed_inline_button_widget($sanitized_atts);
                        } else {
                            return $this->embed_popup_button_widget($sanitized_atts);
                        }

                    case EMCS_POPUP_TEXT_EMBED_TYPE:
                        return $this->embed_popup_text_widget($sanitized_atts);

                    default:
                        return $this->embed_inline_widget($sanitized_atts);
                }
            }
        }
    }

    /**
     * Clean shortcode attributes and properly escape them
     */
    private function clean_shortcode_atts($atts)
    {
        $sanitized_atts = [];

        if ($atts) {
            foreach ($atts as $att_key => $att_value) {

                switch ($att_key) {
                    case 'url':
                        $sanitized_atts[$att_key] = esc_url($att_value);
                        break;

                    case 'text':
                        $sanitized_atts[$att_key] = esc_html($att_value);
                        break;

                    case 'text_color':
                    case 'button_color':
                        $sanitized_atts[$att_key] = sanitize_text_field($att_value);
                        $sanitized_atts[$att_key] = preg_replace('/[^#a-zA-Z0-9]/', '', $sanitized_atts[$att_key]);
                        break;

                    case 'branding':
                    case 'hide_details':
                    case 'cookie_banner':
                    case 'prefill_fields':
                        $sanitized_atts[$att_key] = (int) $att_value;
                        break;

                    case 'form_height':
                    case 'form_width':
                    case 'text_size':
                        $sanitized_atts[$att_key] = intval($att_value) . 'px';
                        break;

                    default:
                        $sanitized_atts[$att_key] = esc_attr($att_value);
                }
            }
        }

        return $sanitized_atts;
    }

    private function prefill_fields($url)
    {
        if ($url) {
            $current_user = wp_get_current_user();

            if ($current_user) {
                $updated_url = $url;
                $name = '';

                if (!empty($current_user->user_email)) {
                    $email = urlencode($current_user->user_email);
                    $updated_url .= (parse_url($updated_url, PHP_URL_QUERY) ? '&' : '?') . "email=$email";
                }

                if (!empty($current_user->first_name)) {
                    $name .= urlencode($current_user->first_name);
                }

                if (!empty($current_user->last_name)) {
                    $name .= '%20' . urlencode($current_user->last_name);
                }

                if (!empty($name)) {
                    $updated_url .= (parse_url($updated_url, PHP_URL_QUERY) ? '&' : '?') . "name=$name";
                }

                return $updated_url;
            }
        }

        return $url;
    }

    private function embed_inline_widget($atts = array())
    {
        return '<div id="calendly-inline-widget" data-url="' . esc_attr($this->url) . '" class="calendly-inline-widget ' . esc_attr($atts['style_class']) . '" data-url="' . esc_url($this->url) . '"
                     style="height:' . esc_attr($atts['form_height']) . '; min-width:' . esc_attr($atts['form_width']) . '"></div>';
    }

    private function embed_popup_text_widget($atts = array())
    {
        return '<a id="calendly-popup-text-widget" data-url="' . esc_attr($this->url) . '" class="' . esc_attr($atts['style_class']) . '" href="" onclick="Calendly.initPopupWidget({url: \'' . esc_js($this->url) . '\'});return false;"
                   style="font-size:' . esc_attr($atts['text_size']) . '; color:' . esc_attr($atts['text_color']) . '">' . esc_html($atts['text']) . '</a>';
    }

    private function embed_inline_button_widget($atts = array())
    {
        $button_padding = '';

        switch ($atts['button_size']) {
            case 1:
                $button_padding = apply_filters('emcs_small_inline_button', '10px');
                break;
            case 2:
                $button_padding = apply_filters('emcs_medium_inline_button', '15px');
                break;
            default:
                $button_padding = apply_filters('emcs_large_inline_button', '20px');
        }

        return '<a id="calendly-inline-button-widget" data-url="' . esc_attr($this->url) . '" class="' . esc_attr($atts['style_class']) . '" href="" onclick="Calendly.initPopupWidget({url: \'' . esc_js($this->url) . '\'});return false;"
                   style="background-color: ' . esc_attr($atts['button_color']) . '; padding: ' . esc_attr($button_padding) . '; font-size:' . esc_attr($atts['text_size']) . '; 
                   color:' . esc_attr($atts['text_color']) . ';">' . esc_html($atts['text']) . '</a>';
    }

    private function embed_popup_button_widget($atts = array())
    {
        return $this->popup_script($atts);
    }

    private function popup_script($atts)
    {
        $url = esc_js($this->url);
        $text = esc_js($atts['text']);
        $color = esc_js($atts['button_color']);
        $textColor = esc_js($atts['text_color']);
        $branding = esc_js($atts['branding']);

        return "<div id='calendly-popup-button-widget' data-url='" . esc_attr($this->url) . "' style='display: none'>
            <script>
                window.onload = function() {
                    Calendly.initBadgeWidget({
                        url: '{$url}',
                        text: '{$text}',
                        color: '{$color}',
                        textColor: '{$textColor}',
                        branding: {$branding}
                    });
                }
            </script>
        </div>";
    }

    private function prepare_embed_url($url, $url_parts = [])
    {
        if (empty($url) || empty($url_parts)) return $url;

        $delimiter = (parse_url($url, PHP_URL_QUERY)) ? '&' : '?';
        $url .= $delimiter . implode('&', $url_parts);

        return $url;
    }
}