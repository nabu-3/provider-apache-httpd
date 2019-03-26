<?php

/** @license
 *  Copyright 2009-2011 Rafael Gutierrez Martinez
 *  Copyright 2012-2013 Welma WEB MKT LABS, S.L.
 *  Copyright 2014-2016 Where Ideas Simply Come True, S.L.
 *  Copyright 2017 nabu-3 Group
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
 * @author Rafael Gutierrez <rgutierrez@nabu-3.com>
 * @since 0.0.1
 * @version 0.0.9
 * @package \providers\apache\httpd\files
 */
class CApacheHostedIndex extends CApacheAbstractFile
{
    /** @var CNabuSiteList $index_list Collection of sites to be listed in the index. */
    private $index_list = null;

    /**
     * Constructor.
     * @param CApacheHTTPServer $http_server
     * @param CNabuSiteList $index_list Index List of sites to figure in the index.
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
    protected function getDescriptor() : string
    {
        return "# ===========================================================================\n"
             . "# nabu-3 - Apache HTTP Server Host Index\n"
        ;
    }

    protected function getContent(string $padding = '') : string
    {
        $output = '';

        $this->index_list->iterate(
            function ($site_key, $nb_site) use (&$output, $padding)
            {
                if ($nb_site->isPublished()) {
                    $site_path = CApacheHTTPServer::NABU_APACHE_ETC_PATH
                               . DIRECTORY_SEPARATOR . $nb_site->getBasePath()
                               . DIRECTORY_SEPARATOR . NABU_VHOST_CONFIG_FILENAME;
                    if (file_exists($site_path)) {
                        $output .= $padding . "# Host: [$site_key] " . $nb_site->getTranslation($nb_site->getDefaultLanguageId())->getName() . "\n";
                        $output .= $padding . "Include \"$site_path\"\n";
                    }
                }

                return true;
            }
        );

        return $output;
    }
}
