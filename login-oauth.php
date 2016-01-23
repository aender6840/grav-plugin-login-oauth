<?php
namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use RocketTheme\Toolbox\File\File;
use RocketTheme\Toolbox\Event\Event;
use RocketTheme\Toolbox\Session\Session;
use Symfony\Component\Yaml\Yaml as YamlParser;


class LoginOauthPlugin extends Plugin
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }
    /**
     * Enable if not Admin
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            return;
        }

        // Check to ensure login plugin is enabled.
        if (!$this->grav['config']->get('plugins.login.enabled')) {
            throw new \RuntimeException('The Login plugin needs to be installed and enabled');
        }

        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $oauth = !empty($_POST['oauth']) ? $_POST['oauth'] : $uri->param('oauth');
        $oauth = $oauth ?: $this->grav['session']->oauth;
        $post = !empty($_POST) ? $_POST : [];

        /** @var Grav\Common\Session */
        $session = $this->grav['session'];


        $this->enable([
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
            'onLoginPage' => ['onLoginPage', 0],
        ]);

        // Autoload classes
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new \Exception('Login-OAuth Plugin failed to load. Composer dependencies not met.');
        }
        require_once $autoload;

        // Manage OAuth login
        $task = !empty($post['task']) ? $post['task'] : $uri->param('task');
        if (!$task && isset($post['oauth']) || (!empty($_GET) && $session->oauth)) {
            //require_once __DIR__ . '/classes/OAuthLoginController.php';
            $controller = new LoginOAuth\OAuthLoginController($this->grav, $oauth, $post);
            $controller->execute();
            $controller->redirect();
        }

        // Aborted OAuth authentication (invalidate it)
        unset($session->oauth);
    }
    /**
     * Add plugin templates path
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/login/templates';
    }
    /**
     * Add Twig Site Variables
     */
    public function onTwigSiteVariables()
    {
        /** @var Twig $twig */
        $twig = $this->grav['twig'];

        $providers = [];
            foreach ($this->config->get('plugins.login-oauth.providers') as $provider => $options) {
                if ($options['enabled'] && isset($options['credentials'])) {
                    $providers[$provider] = $options['credentials'];
                }
            }

        $twig->twig_vars['oauth'] = [
                'enabled' => $this->config->get('plugins.login-oauth.enabled'),
                'providers' => $providers
            ];
    }
    /**
     * Add navigation item to the admin plugin
     */
    public function onLoginPage()
    {
        $this->grav['twig']->plugins_hooked_loginPage['LoginOauth'] = 'partials/login-oauth.html.twig';
    }
}
