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

namespace providers\apache\httpd;

use \nabu\cli\app\CNabuCLIApplication;
use \nabu\core\CNabuEngine;
use \nabu\data\cluster\builtin\CNabuBuiltInServer;
use \nabu\data\cluster\CNabuServer;
use \nabu\data\site\builtin\CNabuBuiltInSite;
use nabu\core\exceptions\ENabuCoreException;

/**
 * Class based in CLI Application to manage Apache Web Server from the command line.
 * This class works coordinated with the bin file nabu-apache-manager.sh
 * @author Rafael Gutierrez <rgutierrez@nabu-3.com>
 * @since 0.0.1
 * @version 0.0.9
 * @package \providers\apache\httpd
 */
class CApacheHTTPManagerApp extends CNabuCLIApplication
{
    /** @var string Default value for Hosted Key. */
    const DEFAULT_HOSTED_KEY = 'nabu-hosted';

    /** @var mixed */
    private $mode = false;
    /** @var string */
    private $host_path = false;
    /** @var string */
    private $server_key = false;

    public function prepareEnvironment()
    {
        CNabuEngine::getEngine()->enableLogTrace();

        if (nbCLICheckOption('a', 'alone', '::', false)) {
            $this->prepareStandaloneMode();
        } elseif (nbCLICheckOption('h', 'hosted', '::', false)) {
            $this->prepareHostedMode();
        } elseif (nbCLICheckOption('c', 'clustered', '::', false)) {
            $this->prepareClusteredMode();
        }
    }

    /** Prepare Apache HTTP Server to run as Standalone mode. */
    private function prepareStandaloneMode()
    {
        $param_path = nbCLICheckOption('p', 'path', ':', false);
        if ($param_path && is_dir($param_path)) {
            $this->mode = CNabuEngine::ENGINE_MODE_STANDALONE;
            $this->host_path = $param_path;
        } else {
            echo "Invalid host path provided for Standalone Engine Mode.\n";
            echo "Please revise your params and try again.\n";
        }
    }

    /** Prepare Apache HTTP Server to run as Hosted mode. */
    private function prepareHostedMode()
    {
        $param_server = nbCLICheckOption('s', 'server', ':', false);
        if (strlen($param_server) === 0) {
            $param_server = self::DEFAULT_HOSTED_KEY;
            $this->mode = CNabuEngine::ENGINE_MODE_HOSTED;
            $this->server_key = $param_server;
        } else {
            echo "Invalid server key provided for Hosted Engine Mode.\n";
            echo "Please revise your options and try again.\n";
        }
    }

    /** Prepare Apache HTTP Server to run as Cluster mode. */
    private function prepareClusteredMode()
    {
        $param_server = nbCLICheckOption('s', 'server', ':', false);
        if (strlen($param_server) === 0) {
            echo "Missed --server or -s option.\n";
            echo "Please revise your options and try again.\n";
        } else {
            $this->mode = CNabuEngine::ENGINE_MODE_CLUSTERED;
            $this->server_key = $param_server;
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
            default:
        }

        return $retval;
    }

    /** Runs the Apache HTTP Server as Standalone mode. */
    private function runStandalone()
    {
        $nb_server = new CNabuBuiltInServer();
        $nb_server->setVirtualHostsPath($this->host_path);

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

    /** Runs the Apache HTTP Server as Hosted mode. */
    private function runHosted()
    {
        $nb_server = CNabuServer::findByKey($this->server_key);
        $apache_server = new CApacheHTTPServer($nb_server);

        if ($apache_server->locateApacheServer()) {
            $this->displayServerConfig($apache_server);
            $apache_server->createHostedConfiguration();
        }
    }

    /** Runs the Apache HTTP Server as Clustered mode. */
    private function runClustered()
    {
        $nb_server = CNabuServer::findByKey($this->server_key);
        if (!is_object($nb_server)) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_SERVER_NOT_FOUND, array($this->server_key, '*', '*'));
        }

        $apache_server = new CApacheHTTPServer($nb_server);

        if ($apache_server->locateApacheServer()) {
            $this->displayServerConfig($apache_server);
            $apache_server->createClusteredConfiguration();
        }
    }

    /** Display the Apache HTTP Server configuration.
      * @param CApacheHTTPServer $apache_server The Apache HTTP Server instance to display.
      */
    private function displayServerConfig(CApacheHTTPServer $apache_server)
    {
        echo "Apache HTTP Server detected\n";
        echo "    Version    : " . $apache_server->getServerVersion() . "\n";
        echo "    Binary     : " . $apache_server->getApacheCtl() . "\n";
        echo "    Config Path: " . $apache_server->getApacheInstancesPath() . "\n";
        echo "\n";
    }

    /** Check if Host Folders exists.
      * @param string $path Base path to check.
      * @return bool Returns true if success.
      */
    private function checkHostFolders(string $path) : bool
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

    /** Check if a folder exists inside a path.
      * @param string $path Base path to check folder.
      * @param string $folder Folder to be checked.
      * @param bool $create If true creates the folder if not exists.
      * @return bool Returns true if success. In case of creation of folder, returns false if folder cannot be created.
      */
    private function checkFolder(string $path, string $folder, bool $create = false) : bool
    {
        $retval = false;

        $target = $path . (is_string($folder) && strlen($folder) > 0 ? DIRECTORY_SEPARATOR . $folder : '');
        echo "    ... checking folder $target ...";

        if (is_dir($target)) {
            echo "EXISTS\n";
            $retval = true;
        } elseif ($create) {
            if ($retval = mkdir($target)) {
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
