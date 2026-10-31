<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Manifest;

use Crustum\BlazeCast\BlazeCastPlugin;
use Crustum\PluginManifest\Manifest\ManifestOps;

/**
 * Standalone manifest provider for the BlazeCast plugin.
 *
 * Plain class without interface or trait dependencies, so the plugin loads
 * and runs without `crustum/plugin-manifest` installed. Discovered by the
 * installer through the provider convention when the plugin class itself
 * provides no manifest. All package references stay inside method bodies,
 * which only execute when the installer (and therefore the package) exists.
 */
final class BlazeCastManifest
{
    /**
     * Plugin install assets via crustum/plugin-manifest.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__, 2);
        $plugin = ManifestOps::pluginName(BlazeCastPlugin::class);

        return array_merge(
            ManifestOps::manifestConfig(
                $plugin,
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'blazecast.php',
                CONFIG . 'blazecast.php',
                false,
            ),
            ManifestOps::manifestBootstrapAppend(
                $plugin,
                "if (file_exists(CONFIG . 'blazecast.php')) {\n    Configure::load('blazecast', 'default');\n}",
                '// BlazeCast Plugin Configuration',
            ),
            ManifestOps::manifestEnvVars(
                $plugin,
                [
                    'BLAZECAST_SERVER' => 'blazecast',
                    'BLAZECAST_SERVER_HOST' => '0.0.0.0',
                    'BLAZECAST_SERVER_PORT' => '8080',
                    'BLAZECAST_APP_ID' => 'app-id',
                    'BLAZECAST_APP_KEY' => 'app-key',
                    'BLAZECAST_APP_SECRET' => 'app-secret',
                    'BLAZECAST_APP_NAME' => 'Default BlazeCast App',
                ],
                '# BlazeCast Configuration',
            ),
            ManifestOps::manifestStarRepo($plugin, 'Crustum/blazecast'),
        );
    }
}
