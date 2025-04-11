<?php

namespace App\Utils;

class HtmlUpdater
{
    private $html;

    public function __construct($html)
    {
        $this->html = $html;
    }

    public function updateHtml()
    {
        $html = $this->html;

        // templates
        $template_files = scandir(BASE_PATH . '/application/views/templates/');
        $template_html = '';

        foreach ($template_files as $template_file) {
            list($filename, $extension) = array_pad(explode('.', $template_file), 2, '');
            if ($extension == 'html') {
                $template_html .= '<script type="text/twig" id="tmpl-' . $filename . '">';
                $template_html .= file_get_contents(BASE_PATH . '/application/views/templates/' . $template_file);
                $template_html .= "</script>\n";
            }
        }

        $html = str_replace('[[templates]]', $template_html, $html);

        // all icons
        $icons_pattern = "/\[\[icons::([a-z-]+)\]\]/";
        $icon_dirs = ['inline' => BASE_PATH .'/src/assets/svg/inline', 'sprite' => BASE_PATH.'./src/assets/svg/sprite', 'nomin' => BASE_PATH . '/src/assets/svg/nomin'];
        while (preg_match($icons_pattern, $html, $m)) {
            $type = $m[1] == 'sprite' ? 'sprite' : 'svg';
            $dir = $icon_dirs[$m[1]];
            $icon_files = scandir($dir);
            $icon_list = '';

            foreach ($icon_files as $icon_file) {
                list($filename, $extension) = array_pad(explode('.', $icon_file), 2, '');
                if ($extension === 'svg' && $filename[0] !== '_') {
                    $icon_list .= '<tr class="ui__row"><td class="ui__icon ui__icon--' . $m[1] . '"><code>' . $filename . '.svg</code></td><td class="ui__icon ui__icon--' . $m[1] . '"><button>[[' . $type . '::' . $filename . ']]</button></td></tr>';
                }
            }

            $html = str_replace($m[0], $icon_list, $html);
        };

        // make svg inline:
        $svg_pattern = "/\[\[svg::([0-9a-z-_]+)\]\]/";
        while (preg_match($svg_pattern, $html, $m)) {
            $svg_file = BASE_PATH . "/application/views/svg/" . $m[1] . ".svg";
            $svg_content = (file_exists($svg_file)) ? file_get_contents($svg_file) : "";
            $svg_content = str_replace('<svg ', '<svg class="svg-' . $m[1] . '" role="img" ', $svg_content);
            $html = str_replace($m[0], $svg_content, $html);
        };

        // place sprite icons:
        $pattern = "/\[\[sprite::([a-z0-9-_]+)\]\]/";
        $replacement = '<svg class="sprite-$1" role="img"><use xlink:href="#sprite-$1"/></svg>';
        $html = preg_replace($pattern, $replacement, $html);

        // include json files:
        $json_pattern = "/\[\[json::([a-z0-9-_]+)\]\]/";
        while (preg_match($json_pattern, $html, $m)) {
            $json_file = BASE_PATH . "/src/json/" . $m[1] . ".json";
            $json_content = (file_exists($json_file)) ? file_get_contents($json_file) : "";
            $json_content = str_replace("'", "&apos;", $json_content);
            $json_array = json_decode($json_content);
            $json_string = json_encode($json_array);
            $html = str_replace($m[0], htmlspecialchars($json_string), $html);
        };

        // put GET params:
        $get_pattern = "/\[\[get::([a-z0-9-_]+)\]\]/";
        while (preg_match($get_pattern, $html, $m)) {
            $param = $m[1];
            $get = isset($_GET[$param]) ? $_GET[$param] : '';
            $html = str_replace($m[0], $get, $html);
        };

        return $html;
    }
}
