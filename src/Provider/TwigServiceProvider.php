<?php
namespace Bolt\Provider;

use Bolt\Twig\DumpExtension;
use Bolt\Twig\FilesystemLoader;
use Bolt\Twig\Handler;
use Bolt\Twig\TwigExtension;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Bridge\Twig\Extension\AssetExtension;

class TwigServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        if (!isset($app['twig'])) {
            $app->register(new \Silex\Provider\TwigServiceProvider());
        }

        $app['twig.loader.bolt_filesystem'] = $app->share(
            function ($app) {
                $loader = new FilesystemLoader($app['filesystem']);

                $themePath = 'theme://' . $app['config']->get('theme/template_directory');

                $loader->addPath($themePath, 'theme');
                $loader->addPath('bolt://app/theme_defaults', 'theme');
                $loader->addPath('bolt://app/view/twig', 'bolt');

                /** @deprecated Deprecated since 3.0, to be removed in 4.0. */
                $loader->addPath($themePath);
                $loader->addPath('bolt://app/theme_defaults');
                $loader->addPath('bolt://app/view/twig');

                return $loader;
            }
        );

        // Insert our filesystem loader before native one
        $app['twig.loader'] = $app->share(
            function ($app) {
                return new \Twig_Loader_Chain(
                    [
                        $app['twig.loader.array'],
                        $app['twig.loader.bolt_filesystem'],
                        $app['twig.loader.filesystem'],
                    ]
                );
            }
        );

        // Handlers
        $app['twig.handlers'] = $app->share(
            function (Application $app) {
                return new \Pimple(
                    [
                        // @codingStandardsIgnoreStart
                        'admin'  => $app->share(function () use ($app) { return new Handler\AdminHandler($app); }),
                        'array'  => $app->share(function () use ($app) { return new Handler\ArrayHandler($app); }),
                        'html'   => $app->share(function () use ($app) { return new Handler\HtmlHandler($app); }),
                        'image'  => $app->share(function () use ($app) { return new Handler\ImageHandler($app); }),
                        'record' => $app->share(function () use ($app) { return new Handler\RecordHandler($app); }),
                        'routing' => $app->share(function () use ($app) { return new Handler\RoutingHandler($app); }),
                        'text'   => $app->share(function () use ($app) { return new Handler\TextHandler($app); }),
                        'user'   => $app->share(function () use ($app) { return new Handler\UserHandler($app); }),
                        'utils'  => $app->share(function () use ($app) { return new Handler\UtilsHandler($app); }),
                        'widget' => $app->share(function () use ($app) { return new Handler\WidgetHandler($app); }),
                        // @codingStandardsIgnoreEnd
                    ]
                );
            }
        );

        // Add the Bolt Twig Extension.
        $app['twig'] = $app->share(
            $app->extend(
                'twig',
                function (\Twig_Environment $twig, $app) {
                    $twig->addExtension(new TwigExtension($app, $app['twig.handlers'], false));
                    $twig->addExtension($app['twig.extension.asset']);

                    if (isset($app['dump'])) {
                        $twig->addExtension(new DumpExtension(
                            $app['dumper.cloner'],
                            $app['dumper.html'],
                            $app['users'],
                            $app['config']->get('general/debug_show_loggedoff', false)
                        ));
                    }

                    return $twig;
                }
            )
        );

        $app['twig.extension.asset'] = $app->share(
            function ($app) {
                return new AssetExtension($app['asset.packages']);
            }
        );

        // Twig options
        $app['twig.options'] = function () use ($app) {
            $options = [];

            // Should we cache or not?
            if ($app['config']->get('general/caching/templates')) {
                $key = hash('md5', $app['config']->get('general/theme'));
                $options['cache'] = $app['resources']->getPath('cache/' . $app['environment'] . '/twig/' . $key);
            }

            if (($strict = $app['config']->get('general/strict_variables')) !== null) {
                $options['strict_variables'] = $strict;
            }

            return $options;
        };

        $app['safe_twig.bolt_extension'] = function () use ($app) {
            return new TwigExtension($app, $app['twig.handlers'], true);
        };

        $app['safe_twig'] = $app->share(
            function ($app) {
                $loader = new \Twig_Loader_String();
                $twig = new \Twig_Environment($loader);
                $twig->addExtension($app['safe_twig.bolt_extension']);

                return $twig;
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function boot(Application $app)
    {
    }
}
