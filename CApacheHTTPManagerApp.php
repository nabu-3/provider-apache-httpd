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

namespace providers\apache\httpd;

use \nabu\cli\app\CNabuCLIApplication;
use \nabu\core\CNabuEngine;
use \nabu\data\cluster\builtin\CNabuBuiltInServer;
use \nabu\data\cluster\CNabuServer;
use \nabu\data\site\builtin\CNabuBuiltInSite;

/**
 * Class based in CLI Application to manage Apache Web Server from the command line.
 * This class works coordinated with the bin file nabu-apache-manager.sh
 * @author Rafael Gutierrez <rgutierrez@wiscot.com>
 * @version 3.0.0 Surface
 * @package providers\apache\httpd
 */
class CApacheHTTPManagerApp extends CNabuCLIApplication
{
    const DEFAULT_HOSTED_KEY = 'nabu-hosted';

    private $mode = false;
    private $host_path = false;
    private $server_key = false;

    public function prepareEnvironment()
    {
        CNabuEngine::getEngine()->enableLogTrace();
        //CNabuEngine::getEngine()->getMainDB()->setTrace(true);

        if (($value = nbCLICheckOption('a', 'alone', '::', false))) {
            $this->prepareStandaloneMode();
        } elseif (($value = nbCLICheckOption('h', 'hosted', '::', false))) {
            $this->prepareHostedMode();
        } elseif (($value = nbCLICheckOption('c', 'clustered', '::', false))) {
            $this->prepareClusteredMode();
        }
    }

    private function prepareStandaloneMode()
    {
        $host_path = nbCLICheckOption('p', 'path', ':', false);
        if ($host_path && is_dir($host_path)) {
            $this->mode = CNabuEngine::ENGINE_MODE_STANDALONE;
            $this->host_path = $host_path;
        } else {
            echo "Invalid host path provided for Standalone Engine Mode.\n";
            echo "Please revise your params and try again.\n";
        }
    }

    private function prepareHostedMode()
    {
        $server_key = nbCLICheckOption('s', 'server', ':', false);
        if (strlen($server_key) === 0) {
            $server_key = self::DEFAULT_HOSTED_KEY;
            $this->mode = CNabuEngine::ENGINE_MODE_HOSTED;
            $this->server_key = $server_key;
        } else {
            echo "Invalid server key provided for Hosted Engine Mode.\n";
            echo "Please revise your options and try again.\n";
        }
    }

    private function prepareClusteredMode()
    {
        $server_key = nbCLICheckOption('s', 'server', ':', false);
        if (strlen($server_key) === 0) {
            echo "Missed --server or -s option.\n";
            echo "Please revise your options and try again.\n";
        } else {
            $this->mode = CNabuEngine::ENGINE_MODE_CLUSTERED;
            $this->server_key = $server_key;
        }
    }

    public function run()
    {
        $retval = -1;

        switch ($this->mode) {
            case CNabuEngine::ENGINE_MODE_STANDALONE:
                $retval = $this->runStandalone();
                break;
            case CNabuEngine::ENGINE_MODE_HOSTED:
                $retval = $this->runHosted();
                break;
            case CNabuEngine::ENGINE_MODE_CLUSTERED:
                $retval = $this->runClustered();
                break;
        }

        return $retval;
    }

    private function runStandalone()
    {
        $nb_server = new CNabuBuiltInServer();
        $nb_server->setVirtualHostsPath($this->host_path);
        $nb_server->setFrameworkPath(NABU_PHPUTILS_PATH);

        $nb_site = new CNabuBuiltInSite();
        $nb_site->setBasePath('');
        $nb_site->setUseFramework('T');

        $apache_server = new CApacheHTTPServer($nb_server, $nb_site);

        if ($apache_server->locateApacheServer()) {
            $this->displayServerConfig($apache_server);
            if ($this->checkHostFolders($this->host_path)) {
                $apache_server->createStandaloneConfiguration();
            }
        }
    }

    private function runHosted()
    {
        $nb_server = CNabuServer::findByKey($this->server_key);
        $apache_server = new CApacheHTTPServer($nb_server);

        if ($apache_server->locateApacheServer()) {
            $this->displayServerConfig($apache_server);
            $apache_server->createHostedConfiguration();
        }
    }

    private function runClustered()
    {
        $nb_server = CNabuServer::findByKey($this->server_key);
        $apache_server = new CApacheHTTPServer($nb_server);

        if ($apache_server->locateApacheServer()) {
            $this->displayServerConfig($apache_server);
            $apache_server->createClusteredConfiguration();
        }
    }

    private function displayServerConfig($apache_server)
    {
        echo "Apache HTTP Server detected\n";
        echo "    Version    : " . $apache_server->getServerVersion() . "\n";
        echo "    Binary     : " . $apache_server->getApacheCtl() . "\n";
        echo "    Config Path: " . $apache_server->getApacheInstancesPath() . "\n";
        echo "\n";
    }

    private function checkHostFolders($path)
    {
        echo "Checking folders of host...\n";
        $retval =
            $this->checkFolder($path, 'private') ||
            $this->checkFolder($path, 'httpdocs') ||
            $this->checkFolder($path, 'httpsdocs') ||
            $this->checkFolder($path, 'phputils') ||
            $this->checkFolder($path, 'templates');
        echo "OK\n";

        return $retval;
    }

    private function checkFolder($path, $folder, $create = false)
    {
        $retval = false;

        $target = $path . (is_string($folder) && strlen($folder) > 0 ? DIRECTORY_SEPARATOR . $folder : '');
        echo "    ... checking folder $target ...";

        if (is_dir($target)) {
            echo "EXISTS\n";
            $retval = true;
        } elseif ($create) {
            if (($retval = mkdir($target))) {
                echo "CREATED\n";
            } else {
                echo "ERROR\n";
            }
        } else {
            echo "NOT PRESENT\n";
        }

        return $retval;
    }
}
