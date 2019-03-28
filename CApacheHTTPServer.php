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

use nabu\cli\CNabuShell;
use nabu\core\CNabuOS;
use nabu\core\exceptions\ENabuCoreException;
use nabu\data\site\CNabuSite;
use nabu\data\site\CNabuSiteList;
use nabu\http\adapters\CNabuHTTPServerAdapter;
use providers\apache\httpd\files\CApacheHostedFile;
use providers\apache\httpd\files\CApacheClusteredIndex;
use providers\apache\httpd\files\CApacheHostedIndex;
use providers\apache\httpd\files\CApacheClusteredFile;
use providers\apache\httpd\files\CApacheStandaloneFile;

/**
 * Main class to manage Apache HTTP Server
 * @author Rafael Gutierrez <rgutierrez@nabu-3.com>
 * @since 0.0.1
 * @version 0.0.9
 * @package \providers\apache\httpd
 * @todo Create new abstract and interface methods
 */
class CApacheHTTPServer extends CNabuHTTPServerAdapter
{
    /** @var string APACHE_CONFIG_FILENAME Apache main config filename for nabu-3 */
    const APACHE_CONFIG_FILENAME = 'nabu-3.conf';
    /** @var string APACHE_ETC_PATH Apache etc folder */
    const NABU_APACHE_ETC_PATH = NABU_ETC_PATH . DIRECTORY_SEPARATOR . 'httpd';
    /** @var string $apachectl Apache main shell script (apachectl) including full path. */
    private $apachectl = false;
    /** @var array $apache_info Array with all information fields about Apache version. */
    private $apache_info = null;
    /** @var array $apache_compiles Array with compiled options of Apache. */
    private $apache_compiles = null;
    /** @var string $apache_config_path Real nabu-3 Apache config path. */
    private $apache_config_path = false;
    /** @var string $php_module Name of PHP Module for Apache detected. */
    private $php_module = false;

    /**
     * Locates Apache HTTP Server instance running in the S.O.
     * @return bool Returns true if success.
     */
    public function locateApacheServer() : bool
    {
        if ($this->getApacheCtl()) {
            $this->getApacheInfo();
            $this->getApacheInstancesPath();
            $this->getPHPModule();
        }

        return $this->apachectl !== false && $this->apache_config_path !== false;
    }

    /**
     * Locates the Apache Control Application full path installed in the S.O.
     * @return string|false Returns the full path if success or false if error.
     */
    public function getApacheCtl()
    {
        if ($this->apachectl === false) {
            $shell = new CNabuShell();
            $response = array();
            if ($shell->exec('whereis apachectl', null, $response) && count($response) === 1) {
                $parts = preg_split('/\\s/', preg_replace('/^apachectl: /', '', $response[0]));
                $this->apachectl = $parts[0];
            }
        }

        return $this->apachectl;
    }

    /**
     * Gets Apache Information about installed Apache HTTP Server.
     */
    public function getApacheInfo()
    {
        if ($this->apachectl !== false) {
            $shell = new CNabuShell();
            $response = array();
            if ($shell->exec($this->apachectl, array('-V' => ''), $response)) {
                $this->parseApacheInfo($response);
            }
        }
    }

    /**
     * Parse Apache Information lines to get valid values.
     * @param array|null $data Array of lines to parse.
     */
    private function parseApacheInfo(array $data = null)
    {
        $this->apache_info = null;

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $line) {
                $this->interpretApacheInfoData($line) ||
                $this->interpretApacheInfoVariable($line);
            }
        }
    }

    /**
     * Interprets a line of Apache Information to discover valid data and store it in $apache_info array.
     * @param string $line Line Information to be interpreted.
     * @return bool Return true if success.
     */
    private function interpretApacheInfoData(string $line) : bool
    {
        $retval = false;
        $parts = preg_split('/:\s+/', $line, 2);
        if (count($parts) === 2) {
            switch (trim($parts[0])) {
                case 'Server version':
                    $this->apache_info['server-version'] = trim($parts[1]);
                    $retval = true;
                    break;
                case 'Server built':
                    $this->apache_info['server-built'] = trim($parts[1]);
                    $retval = true;
                    break;
                case 'Server\'s Module Magic Number':
                    $this->apache_info['server-magic-number'] = trim($parts[1]);
                    $retval = true;
                    break;
                case 'Architecture':
                    $this->apache_info['server-architecture'] = trim($parts[1]);
                    $retval = true;
                    break;
                case 'Server MPM':
                    $this->apache_info['server-mpm'] = trim($parts[1]);
                    $retval = true;
                    break;
                case 'threaded':
                    $this->apache_info['server-mpm-threaded'] = trim($parts[1]);
                    $retval = true;
                    break;
                case 'forked':
                    $this->apache_info['server-mpm-forked'] = trim($parts[1]);
                    $retval = true;
                    break;
                default:
            }
        }

        return $retval;
    }

    /**
     * Get the Apache Instances Path.
     * @return string|false Returns the path if exists or false elsewhere.
     */
    public function getApacheInstancesPath()
    {
        if ($this->apache_config_path === false &&
            is_array($this->apache_compiles) &&
            array_key_exists('SERVER_CONFIG_FILE', $this->apache_compiles)
        ) {
            $config_file = $this->apache_compiles['SERVER_CONFIG_FILE'];
            if (is_file($config_file)) {
                $base_path = dirname($config_file);
                if (is_dir($base_path . DIRECTORY_SEPARATOR . 'other')) {
                    $this->apache_config_path = $base_path . DIRECTORY_SEPARATOR . 'other';
                } elseif (is_dir($base_path . DIRECTORY_SEPARATOR . 'conf.d')) {
                    $this->apache_config_path = $base_path . DIRECTORY_SEPARATOR . 'conf.d';
                }
            }  elseif (array_key_exists('HTTPD_ROOT', $this->apache_compiles)) {
                $base_path = preg_replace('/"$/', '', preg_replace('/^"/', '', $this->apache_compiles['HTTPD_ROOT']));
                if (is_dir($base_path . DIRECTORY_SEPARATOR . 'conf.d')) {
                    $this->apache_config_path = $base_path . DIRECTORY_SEPARATOR . 'conf.d';
                } elseif (is_dir($base_path . DIRECTORY_SEPARATOR . 'other')) {
                    $this->apache_config_path = $base_path . DIRECTORY_SEPARATOR . 'other';
                }
            }
        }

        return $this->apache_config_path;
    }

    /**
     * Interpret Apache Infromation Variable line.
     * @param string $line Line of Intormation to be interpreted.
     */
    private function interpretApacheInfoVariable(string $line)
    {
        $content = preg_split('/^\\s+-D\\s+/', $line, 2);
        if (count($content) === 2) {
            $parts = preg_split('/=/', $content[1], 2);
            if (count($parts) === 2) {
                $this->apache_compiles[$parts[0]] = str_replace('"', '', $parts[1]);
            } elseif (count($parts) === 1) {
                $this->apache_compiles[$content[1]] = true;
            }
        }
    }

    /**
     * Gets the Apache HTTP Server Version.
     * @return string Returns the version string or 'Unknown' if no version is available.
     */
    public function getServerVersion() : string
    {
        return (is_array($this->apache_info) && array_key_exists('server-version', $this->apache_info))
                ? $this->apache_info['server-version']
                : 'Unknown'
        ;
    }

    /**
     * Gets the PHP Module Apache name depending of installed version of PHP (5 or 7).
     * @return string|false Returns the string name if exists or false elsewhere.
     */
    public function getPHPModule()
    {
        if (!$this->php_module) {
            $nb_os = CNabuOS::getOS();
            $php_version = $nb_os->getPHPVersion();
            switch ($php_version[0]) {
                case '5':
                    $this->php_module = 'php5_module';
                    break;
                case '7':
                    $this->php_module = 'php7_module';
                    break;
                default:
            }
        }

        return $this->php_module;
    }

    /**
     * Creates the Standalone Configuration files.
     * @return bool Return true if success.
     */
    public function createStandaloneConfiguration() : bool
    {
        $retval = false;

        if ($this->apache_config_path) {
            $file = new CApacheStandaloneFile($this, $this->nb_server, $this->nb_site);
            $file->create();
            $file->exportToFile($this->apache_config_path . DIRECTORY_SEPARATOR . self::APACHE_CONFIG_FILENAME);
            $retval = true;
        }

        return $retval;
    }

    /**
     * Creates the Hosted Configuration files.
     * @return bool Return true if success.
     */
    public function createHostedConfiguration() : bool
    {
        $retval = false;

        if ($this->apache_config_path) {
            $index_list = $this->nb_server->getSitesIndex();
            $index_list->iterate(
                function ($site_key, $nb_site) {
                    $this->createHostedFile($nb_site);

                    return true;
                }
            );
            $this->createHostedIndex($index_list);
            $retval = true;
        }

        return $retval;
    }

    /**
     * Creates the Clustered Configuration files.
     * @return bool Return true if success.
     */
    public function createClusteredConfiguration()
    {
        $retval = false;

        if ($this->apache_config_path) {
            $index_list = $this->nb_server->getSitesIndex();
            $index_list->iterate(
                function ($site_key, $nb_site) {
                    $this->createSiteFolders($nb_site);
                    $this->createClusteredFile($nb_site);

                    return true;
                }
            );
            $this->createClusteredIndex($index_list);
            $retval = true;
        }

        return $retval;
    }

    /**
     * Create Hosted hosts index file.
     * @param CNabuSiteList $index_list List of Sites to be listed.
     */
    private function createHostedIndex(CNabuSiteList $index_list)
    {
        $index = new CApacheHostedIndex($this, $index_list);
        $index->create();
        $index->exportToFile($this->apache_config_path . DIRECTORY_SEPARATOR . self::APACHE_CONFIG_FILENAME);
    }

    /**
     * Create Clustered hosts index file.
     * @param CNabuSiteList $index_list List of Sites to be listed.
     */
    private function createClusteredIndex(CNabuSiteList $index_list)
    {
        $index = new CApacheClusteredIndex($this, $index_list);
        $index->create();
        $index->exportToFile($this->apache_config_path . DIRECTORY_SEPARATOR . self::APACHE_CONFIG_FILENAME);
    }

    /**
     * Create Hosted per Site file configuration.
     * @param CNabuSite $nb_site Site to create configuration.
     */
    private function createHostedFile(CNabuSite $nb_site)
    {
        $file = new CApacheHostedFile($this, $this->nb_server, $nb_site);
        $file->create();
        $path = $this->nb_server->getVirtualHostsPath()
                  . DIRECTORY_SEPARATOR
                  . $nb_site->getBasePath()
                  . NABU_VHOST_CONFIG_FOLDER
                  . DIRECTORY_SEPARATOR
                  . $this->nb_server->getKey()
        ;
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        if (!is_dir($path)) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_FOLDER_NOT_FOUND, array($path));
        }
        $filename = $path . DIRECTORY_SEPARATOR . NABU_VHOST_CONFIG_FILENAME;
        $file->exportToFile($filename);
    }

    /**
     * Create Clustered per Site file configuration.
     * @param CNabuSite $nb_site Site to create configuration.
     */
    private function createClusteredFile(CNabuSite $nb_site)
    {
        $file = new CApacheClusteredFile($this, $this->nb_server, $nb_site);
        $file->create();
        $path = self::NABU_APACHE_ETC_PATH . DIRECTORY_SEPARATOR . $nb_site->getBasePath();

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        if (!is_dir($path)) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_FOLDER_NOT_FOUND, array($path));
        }
        $filename = $path . DIRECTORY_SEPARATOR . NABU_VHOST_CONFIG_FILENAME;
        $file->exportToFile($filename);
    }

    /**
     * Validates a path to ensure that it exists and is available. If not exists tries to create it.
     * @param string $path Path to validate.
     * @return string Returns the path for convenience.
     * @throws ENabuCoreException Raises an exception if the path does not exists.
     */
    private function validatePath(string $path) : string
    {
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_HOST_PATH_NOT_FOUND, array($path));
        }

        return $path;
    }

    /**
     * Create Site Folders for the requested Site.
     * @param CNabuSite $nb_site Site instance to create folders.
     * @return bool Returns true if all required folders exists.
     * @throws ENabuCoreException Raises an exception if a folder cannot be available.
     * @todo Refactor this method to use CNabuHTTPFileSystem
     */
    public function createSiteFolders(CNabuSite $nb_site) : bool
    {
        $vhosts_path = $this->validatePath($this->nb_server->getVirtualHostsPath());
        $vlib_path = $this->validatePath($this->nb_server->getVirtualLibrariesPath());
        $vcache_path = $this->validatePath($this->nb_server->getVirtualCachePath());

        $nb_cluster_user = $nb_site->getClusterUser();
        if ($nb_cluster_user === null) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_OBJECT_EXPECTED);
        }
        $nb_cluster_user_group = $nb_cluster_user->getGroup();
        if ($nb_cluster_user_group === null) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_OBJECT_EXPECTED);
        }
        $owner_name = $nb_cluster_user->getOSNick();
        $owner_group = $nb_cluster_user_group->getOSNick();

        $vhosts_path = $nb_site->getVirtualHostPath($this->nb_server);
        if (!is_dir($vhosts_path)) {
            if (!mkdir($vhosts_path, 0755, true)) {
                throw new ENabuCoreException(ENabuCoreException::ERROR_HOST_PATH_NOT_FOUND, array($vhosts_path));
            } else {
                chown($vhosts_path, $owner_name);
                chgrp($vhosts_path, $owner_group);
            }
        }
        $vlib_path = $nb_site->getVirtualLibrariesPath($this->nb_server);
        if (!is_dir($vlib_path)) {
            if (!mkdir($vlib_path, 0755, true)) {
                throw new ENabuCoreException(ENabuCoreException::ERROR_HOST_PATH_NOT_FOUND, array($vlib_path));
            } else {
                chown($vlib_path, APACHE_HTTPD_SYS_USER);
                chgrp($vlib_path, $owner_group);
            }
        }
        $vcache_path = $nb_site->getVirtualCachePath($this->nb_server);
        if (!is_dir($vcache_path)) {
            if (!mkdir($vcache_path, 0755, true)) {
                throw new ENabuCoreException(ENabuCoreException::ERROR_HOST_PATH_NOT_FOUND, array($vcache_path));
            } else {
                chown($vcache_path, APACHE_HTTPD_SYS_USER);
                chgrp($vcache_path, $owner_group);
            }
        }

        return true;
    }
}
