<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_privacy
 *
 * @copyright   (C) 2018 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Privacy\Administrator\Model;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Privacy\CollectCapabilitiesEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\CMS\Plugin\PluginHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Capabilities model class.
 *
 * @since  3.9.0
 */
class CapabilitiesModel extends BaseModel
{
    /**
     * Retrieve the extension capabilities.
     *
     * @return  array
     *
     * @since   3.9.0
     */
    public function getCapabilities()
    {
        $app = Factory::getApplication();

        /*
         * Capabilities will be collected in two parts:
         *
         * 1) Core capabilities - This will cover the core API, i.e. all library level classes
         * 2) Extension capabilities - This will be collected by a plugin hook to select plugin groups
         *
         * Plugins which report capabilities should return an associative array with a single root level key which is used as the title
         * for the reporting section and an array with each value being a separate capability. All capability messages should be translated
         * by the extension when building the array. An example of the structure expected to be returned from plugins can be found in the
         * $coreCapabilities array below.
         */

        $coreCapabilities = [
            Text::_('COM_PRIVACY_HEADING_CORE_CAPABILITIES') => [
                Text::_('COM_PRIVACY_CORE_CAPABILITY_SESSION_IP_ADDRESS_AND_COOKIE'),
                Text::sprintf('COM_PRIVACY_CORE_CAPABILITY_LOGGING_IP_ADDRESS', $app->get('log_path', JPATH_ADMINISTRATOR . '/logs')),
                Text::_('COM_PRIVACY_CORE_CAPABILITY_COMMUNICATION_WITH_JOOMLA_ORG'),
            ],
        ];

        /*
         * We will search for capabilities from the following plugin groups:
         *
         * - Authentication: These plugins by design process user information and may have capabilities such as creating cookies
         * - Captcha: These plugins may communicate information to third party systems
         * - Installer: These plugins can add additional install capabilities to the Extension Manager, such as the Install from Web service
         * - Privacy: These plugins are the primary integration point into this component
         * - User: These plugins are intended to extend the user management system
         *
         * This is in addition to plugin groups which are imported before this method is triggered, generally this is the system group.
         */

        $dispatcher = $app->getDispatcher();

        PluginHelper::importPlugin('authentication', null, true, $dispatcher);
        PluginHelper::importPlugin('captcha', null, true, $dispatcher);
        PluginHelper::importPlugin('installer', null, true, $dispatcher);
        PluginHelper::importPlugin('privacy', null, true, $dispatcher);
        PluginHelper::importPlugin('user', null, true, $dispatcher);

        $pluginResults = $dispatcher->dispatch(
            'onPrivacyCollectAdminCapabilities',
            new CollectCapabilitiesEvent('onPrivacyCollectAdminCapabilities')
        )->getArgument('result', []);

        // We are going to "cheat" here and include this component's capabilities without using a plugin
        $extensionCapabilities = [
            Text::_('COM_PRIVACY') => [
                Text::_('COM_PRIVACY_EXTENSION_CAPABILITY_PERSONAL_INFO'),
            ],
        ];

        foreach ($pluginResults as $pluginResult) {
            $extensionCapabilities += $pluginResult;
        }

        // Sort the extension list alphabetically
        ksort($extensionCapabilities);

        // Always prepend the core capabilities to the array
        return $coreCapabilities + $extensionCapabilities;
    }

    /**
     * Method to auto-populate the model state.
     *
     * @return  void
     *
     * @since   3.9.0
     */
    protected function populateState()
    {
        // Load the parameters.
        $this->setState('params', ComponentHelper::getParams('com_privacy'));
    }
}
