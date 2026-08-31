<?php
/**
 * TicketTrade — Support\View
 *
 * Tiny template helper. View::render($view, $vars) wraps $view in the
 * layout (head + bottom_nav + toast_container). View::partial($name)
 * includes a partial directly. View::h() is the canonical htmlspecialchars
 * escape wrapper for dynamic output.
 */

declare(strict_types=1);

namespace App\Support;

class View
{
    /**
     * htmlspecialchars wrapper for dynamic values in templates.
     */
    public static function h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render a View wrapped in the layout.
     *
     * @param string $contentView Absolute path to the content view file
     * @param array $vars         Variables exposed to the view via $GLOBALS
     */
    public static function render(string $contentView, array $vars = []): void
    {
        $layout = __DIR__ . '/View/layout.php';
        $GLOBALS['_tt_view_vars'] = $vars;
        $GLOBALS['_tt_content_view'] = $contentView;
        require $layout;
    }

    /**
     * Include a partial directly (no layout wrapping).
     *
     * @param string $name Partial filename under partials/ (no extension)
     * @param array $vars  Variables exposed to the partial
     */
    public static function partial(string $name, array $vars = []): void
    {
        $path = __DIR__ . '/View/partials/' . $name . '.php';
        $GLOBALS['_tt_view_vars'] = $vars;
        require $path;
    }
}
