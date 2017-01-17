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

use \providers\apache\httpd\CApacheHTTPServer;
use nabu\data\site\CNabuSiteList;

/**
 * @author Rafael Gutierrez <rgutierrez@wiscot.com>
 * @version 3.0.0 Surface
 * @package name
 */
class CApacheHostedIndex extends CApacheAbstractFile
{
    /**
     * Collection of sites to be listed in the index.
     * @var CNabuSiteList
     */
    private $index_list = null;

    /**
     * Constructor.
     * @param CApacheHTTPServer $http_server
     * @param array $index_list Index List of sites to figure in the index.
     */
    public function __construct(CApacheHTTPServer $http_server, CNabuSiteList $index_list)
    {
        parent::__construct($http_server);

        $this->index_list = $index_list;
    }

    /**
     * Overrides parent method to place the main header of file before the license agreement.
     * @return string Returns the text to be placed at the start of the file.
     */
    protected function getDescriptor()
    {
        return "# ===========================================================================\n"
             . "# Nabu 3 - Apache HTTP Server Host Index\n"
        ;
    }

    protected function getContent($padding = '')
    {
        $http_server = $this->getHTTPServer();
        $nb_server = $http_server->getServer();
        $vhosts_path = $nb_server->getVirtualHostsPath();

        $output = '';

        $this->index_list->iterate(
            function ($site_key, $nb_site) use (&$output, $nb_server, $padding)
            {
                if ($nb_site->isPublished()) {
                    $site_path = $nb_server->getVirtualHostsPath()
                               . $nb_site->getBasePath()
                               . NABU_VHOST_CONFIG_FOLDER
                               . DIRECTORY_SEPARATOR
                               . $nb_server->getKey()
                               . DIRECTORY_SEPARATOR
                               . NABU_VHOST_CONFIG_FILENAME;
                    if (file_exists($site_path)) {
                        $output .= $padding . "# Host: " . $nb_site->getTranslation($nb_site->getDefaultLanguageId())->getName() . "\n";
                        $output .= $padding . "Include \"$site_path\"\n";
                    }
                }

                return true;
            }
        );

        return $output;
    }
}
