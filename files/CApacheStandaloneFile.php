<?php

/*  Copyright 2009-2011 Rafael Gutierrez Martinez
 *  Copyright 2012-2013 Welma WEB MKT LABS, S.L.
 *  Copyright 2014-2016 Where Ideas Simply Come True, S.L.
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */

namespace providers\apache\httpd\files;

use \nabu\core\exceptions\ENabuCoreException;
use \nabu\data\cluster\CNabuServer;
use \nabu\data\site\CNabuSite;
use \providers\apache\httpd\CApacheHTTPServer;
use \providers\apache\httpd\files\CApacheAbstractFile;

/**
 * Class to manage Nabu 3 Apache Standalone Config File
 * @author Rafael Gutierrez <rgutierrez@wiscot.com>
 * @version 3.0.0 Surface
 * @package \providers\apache\httpd\files
 */
class CApacheStandaloneFile extends CApacheAbstractFile
{
    private $nb_server;
    private $nb_site;

    /**
     * Constructor.
     * @param CNabuServer $nb_server Server entity to configure file
     * @param CNabuSite $nb_site Site entity to configure file
     * @throws ENabuCoreException Throws this exception if param $nb_server is empty
     * or unexpected type.
     */
    public function __construct(CApacheHTTPServer $apache_server, CNabuServer $nb_server, CNabuSite $nb_site)
    {
        parent::__construct($apache_server);

        $this->nb_server = $nb_server;
        $this->nb_site = $nb_site;
    }

    /**
     * Overrides parent method to place the main header of file before the license agreement.
     * @return string Returns the text to be placed at the start of the file.
     */
    protected function getDescriptor()
    {
        return "# ===========================================================================\n"
             . "# Nabu 3 - Apache HTTP Server Standalone configuration\n"
        ;
    }

    /**
     * Overrides parent method to build the script content from $apache_server,
     * $nb_server and $nb_site descriptors.
     * @param string $padding Padding to be applied.
     * @return string Output string to be placed in memory or file.
     */
    protected function getContent($padding = '')
    {
        $http_server = $this->getHTTPServer();
        $module_name = $http_server->getPHPModule();

        $site_base_path = $this->nb_server->getVirtualHostsPath();
        $site_httpdocs = $site_base_path . NABU_HTTPDOCS_FOLDER;
        $site_commons = $site_base_path . NABU_COMMONDOCS_FOLDER;
        $use_framework = ($this->nb_site->getUseFramework() === 'T');
        $framework_path = $this->nb_server->getFrameworkPath();

        $tmp = '/var/tmp:/tmp';

        $output = $padding . "DocumentRoot \"$site_httpdocs\"\n"
                . $padding . "ServerName builtin.nabu.local\n"
                . $padding . "<Directory \"$site_httpdocs\">\n"
                . $padding . "        <IfModule $module_name>\n"
                . $padding . "                php_admin_flag engine on\n"
                . $padding . "                php_admin_flag safe_mode off\n"
                . $padding . "                php_admin_value open_basedir \"$site_base_path" .
                           /* ":$icontact_path:$mediotecas_path:$emailing_path:$apps_path" .*/
                           ($use_framework ? ':'.$framework_path : '') . ":" . NABU_ETC_PATH . ":$tmp\"\n"
        ;

        if ($use_framework) {
            $output .= $padding . "                php_value include_path \".:$framework_path\"\n";
        }

        $output .= $padding . "        </IfModule>\n"
                 . $padding . "        Options -Includes -ExecCGI\n"
                 . $padding . "        AllowOverride All\n"
                 . $padding . "        Require all granted\n"
                 . $padding . "</Directory>\n"
        ;

        $output .= $this->populateCommonDocs($padding, $site_commons);


        return $output;
    }
}
