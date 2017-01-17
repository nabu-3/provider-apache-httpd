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

use nabu\cli\CNabuShell;
use nabu\core\CNabuOS;
use nabu\core\CNabuEngine;
use nabu\core\exceptions\ENabuException;
use nabu\core\exceptions\ENabuCoreException;
use nabu\core\utils\CNabuURL;
use nabu\data\cluster\CNabuServer;
use nabu\data\cluster\CNabuServerHost;
use nabu\data\domain\CNabuDomainZone;
use nabu\data\domain\CNabuDomainZoneHost;
use nabu\data\site\CNabuSite;
use nabu\data\site\CNabuSiteList;
use nabu\data\site\CNabuSiteAlias;
use nabu\http\adapters\CNabuHTTPServerAdapter;
use providers\apache\httpd\files\CApacheClusteredIndex;
use providers\apache\httpd\files\CApacheHostedIndex;
use providers\apache\httpd\files\CApacheClusteredFile;
use providers\apache\httpd\files\CApacheStandaloneFile;

/**
 * Main class to manage Apache HTTP Server
 * @author Rafael Gutierrez <rgutierrez@wiscot.com>
 * @version 3.0.0 Surface
 * @package providers\apache\httpd
 */
class CApacheHTTPServer extends CNabuHTTPServerAdapter
{
    const APACHE_CONFIG_FILENAME = 'nabu-3.conf';

    private $apachectl = false;
    private $apache_info = null;
    private $apache_compiles = null;
    private $apache_config_path = false;
    private $php_module = false;

    public function locateApacheServer()
    {
        if ($this->getApacheCtl()) {
            $this->getApacheInfo();
            $this->getApacheInstancesPath();
            $this->getPHPModule();
        }

        return $this->apachectl !== false && $this->apache_config_path !== false;
    }

    public function getApacheCtl()
    {
        if ($this->apachectl === false) {
            $shell = new CNabuShell();
            $response = array();
            if ($shell->exec('whereis apachectl', null, $response)) {
                if (count($response) === 1) {
                    $parts = preg_split('/\\s/', preg_replace('/^apachectl: /', '', $response[0]));
                    $this->apachectl = $parts[0];
                }
            }
        }

        return $this->apachectl;
    }

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

    private function parseApacheInfo($data)
    {
        $this->apache_info = null;

        if (is_array($data) && count($data) > 0) {
            foreach ($data as $line) {
                $this->interpretApacheInfoData($line) || $this->interpretApacheInfoVariable($line);
            }
        }
    }

    private function interpretApacheInfoData($line)
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
            }
        }

        return $retval;
    }

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

    private function interpretApacheInfoVariable($line)
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

    public function getServerVersion()
    {
        return (is_array($this->apache_info) && array_key_exists('server-version', $this->apache_info))
                ? $this->apache_info['server-version']
                : 'Unknown'
        ;
    }

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
            }
        }

        return $this->php_module;
    }

    public function createStandaloneConfiguration()
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

    public function createHostedConfiguration()
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

    public function createClusteredConfiguration()
    {
        $retval = false;

        if ($this->apache_config_path) {
            $index_list = $this->nb_server->getSitesIndex();
            $index_list->iterate(
                function ($site_key, $nb_site) {
                    $this->createClusteredFile($nb_site);

                    return true;
                }
            );
            $this->createClusteredIndex($index_list);
            $retval = true;
        }

        return $retval;
    }

    private function createHostedIndex(CNabuSiteList $index_list)
    {
        $index = new CApacheHostedIndex($this, $index_list);
        $index->create();
        $index->exportToFile($this->apache_config_path . DIRECTORY_SEPARATOR . self::APACHE_CONFIG_FILENAME);
    }

    private function createClusteredIndex(CNabuSiteList $index_list)
    {
        $index = new CApacheClusteredIndex($this, $index_list);
        $index->create();
        $index->exportToFile($this->apache_config_path . DIRECTORY_SEPARATOR . self::APACHE_CONFIG_FILENAME);
    }

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

    private function createClusteredFile(CNabuSite $nb_site)
    {
        $file = new CApacheClusteredFile($this, $this->nb_server, $nb_site);
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

    public function locateRunningConfiguration()
    {
        if ($this->nb_server === null) {
            $this->locateNabuServer();
        }

        if ($this->nb_site === null) {
            $this->locateNabuSite();
        }

        if ($this->nb_domain_zone === null) {
            $this->locateNabuDomainZone();
        }
    }

    private function locateNabuServer()
    {
        $nb_engine = CNabuEngine::getEngine();

        $this->nb_server_host = null;

        if (($addr = $this->getServerAddress()) &&
            ($port = $this->getServerPort()) &&
            ($server_name = $this->getServerName())
        ) {
            ;
            if (($this->nb_server = CNabuServer::findByHostParams($addr, $port, $server_name)) === null) {
                $this->nb_server = CNabuServer::findByDefaultHostParams($addr, $port, $server_name);
                $this->nb_server_invalid = true;
            } else {
                $this->nb_server_invalid = false;
            }
            if ($this->nb_server === null) {
                throw new ENabuCoreException(
                    ENabuCoreException::ERROR_SERVER_NOT_FOUND, array($server_name, $addr, $port));
            }

            $this->nb_server_host = new CNabuServerHost($this->nb_server);
            if ($this->nb_server_host->isNew()) {
                throw new ENabuCoreException(
                    ENabuCoreException::ERROR_SERVER_HOST_NOT_FOUND, array($addr, $port));
            }
            $nb_engine->traceLog(
                "Server",
                "$addr:$port [" . $this->nb_server->getId() . ',' . $this->nb_server_host->getId() . ']'
            );
        } else {
            throw new ENabuCoreException(ENabuCoreException::ERROR_SERVER_HOST_MISCONFIGURED);
        }

        return $this->nb_server;
    }

    private function locateNabuDomainZone()
    {
        $nb_engine = CNabuEngine::getEngine();

        if ($this->nb_server->contains(NABU_DOMAIN_ZONE_FIELD_ID)) {
            $this->nb_domain_zone = new CNabuDomainZone($this->nb_server);
        } elseif ($this->nb_site->contains(NABU_DOMAIN_ZONE_FIELD_ID)) {
            $this->nb_domain_zone = new CNabuDomainZone($this->nb_site);
        } else {
            throw new ENabuCoreException(ENabuCoreException::ERROR_DOMAIN_ZONE_NOT_FOUND);
        }

        if ($this->nb_server->contains(NABU_DOMAIN_ZONE_HOST_FIELD_ID)) {
            $this->nb_domain_zone_host = new CNabuDomainZoneHost($this->nb_server);
        } elseif ($this->nb_site->contains(NABU_DOMAIN_ZONE_HOST_FIELD_ID)) {
            $this->nb_domain_zone_host = new CNabuDomainZoneHost($this->nb_site);
        }

        if ($this->nb_domain_zone_host !== null) {
            if ($this->nb_domain_zone !== null) {
                $this->nb_domain_zone_host->setDomainZone($this->nb_domain_zone);
            }
            if ($this->nb_site_alias !== null) {
                $this->nb_site_alias->setDomainZoneHost($this->nb_domain_zone_host);
            }
        }
    }

    private function locateNabuSite()
    {
        $nb_engine = CNabuEngine::getEngine();

        $this->nb_site_alias_force_default = false;

        if ($this->nb_server->contains(NABU_SITE_FIELD_ID)) {
            $this->nb_site = new CNabuSite($this->nb_server);
        } elseif (($server_name = $this->getServerName())) {
            $this->nb_site = CNabuSite::findByAlias($server_name);
        }

        if ($this->nb_site === null || $this->nb_site->isNew()) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_SITE_NOT_FOUND);
        } elseif (!$this->nb_site->isPublished()) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_SITE_NOT_PUBLISHED, $this->nb_site->getId());
        }

        if ($this->nb_server->contains(NABU_SITE_ALIAS_FIELD_ID)) {
            $this->nb_site_alias = new CNabuSiteAlias($this->nb_server);
        } elseif ($this->nb_site->contains(NABU_SITE_ALIAS_FIELD_ID)) {
            $this->nb_site_alias = new CNabuSiteAlias($this->nb_site);
        }

        if ($this->nb_site_alias === null || $this->nb_site_alias->isNew()) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_SITE_ALIAS_NOT_FOUND);
        }

        $this->nb_site->setAlias($this->nb_site_alias);

        $nb_engine->traceLog("Site", $this->nb_site->getId());
        $nb_engine->traceLog("Site Alias", $this->nb_site_alias->getId());
    }

    public function locateRemoteAddress()
    {
        $remote_ip = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', false);
        if (!$remote_ip) {
            $remote_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR', false);
        }

        return $remote_ip;
    }

    public function getAcceptedMimetypes() {

        if (array_key_exists('HTTP_ACCEPT', $_SERVER)) {
            $mimetypes = preg_split("/(\s*,\s*)/", filter_input(INPUT_SERVER, 'HTTP_ACCEPT'));
            if (count($mimetypes) > 0) {
                $list = array();
                foreach ($mimetypes as $mimetype) {
                    $attrs = $this->parseAcceptedMimetype($mimetype);
                    if (is_array($attrs)) {
                        $list[$attrs['mimetype']] = $attrs;
                    }
                }
            }
        }

        return isset($list) && count($list) > 0 ? $list : null;
    }

    private function parseAcceptedMimetype($mimetype)
    {
        $retval = false;

        $parts = preg_split("/(\s*;\s*)/", $mimetype);
        switch (count($parts)) {
            case 1:
                $retval = array (
                    'mimetype' => $parts[0],
                    'q' => 1
                );
                break;
            case 2:
                $retval = array (
                    'mimetype' => $parts[0],
                    'q' => floatval(substr($parts[1], 2))
                );
        }

        return $retval;
    }

    public function getAcceptedLanguages() {

        if (array_key_exists('HTTP_ACCEPT_LANGUAGE', $_SERVER)) {
            $languages = preg_split("/(\s*,\s*)/", filter_input(INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE'));
            if (count($languages) > 0) {
                $list = array();
                foreach ($languages as $language) {
                    $attrs = $this->parseAcceptedLanguage($language);
                    if (is_array($attrs)) {
                        $list[$attrs['language']] = $attrs;
                    }
                }
            }
        }

        return isset($list) && count($list) > 0 ? $list : null;
    }

    private function parseAcceptedLanguage($language)
    {
        $retval = false;

        $parts = preg_split("/(\s*;\s*)/", $language);
        switch (count($parts)) {
            case 1:
                $retval = array (
                    'language' => $parts[0],
                    'q' => 1
                );
                break;
            case 2:
                $retval = array (
                    'language' => $parts[0],
                    'q' => floatval(substr($parts[1], 2))
                );
        }

        return $retval;
    }

    public function getContentLength()
    {
        return (array_key_exists('CONTENT_LENGTH', $_SERVER) ? (int)$_SERVER['CONTENT_LENGTH'] : 0);
    }

    public function getContentType()
    {
        return (array_key_exists('CONTENT_TYPE', $_SERVER) ? $_SERVER['CONTENT_TYPE'] : null);
    }

    public function getReferer() {

        $referer = null;

        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            $referer = new CNabuURL(filter_input(INPUT_SERVER, 'HTTP_REFERER'));
            if (!$referer->isValid()) {
                $referer = null;
            }
        }

        return $referer;
    }

    public function getOrigin()
    {
        return filter_input(INPUT_SERVER, 'HTTP_ORIGIN');
    }

    public function getContextDocumentRoot()
    {
        return filter_input(INPUT_SERVER, 'CONTEXT_DOCUMENT_ROOT');
    }

    public function getContextPrefix()
    {
        return filter_input(INPUT_SERVER, 'CONTEXT_PREFIX');
    }

    public function getDocumentRoot()
    {
        return filter_input(INPUT_SERVER, 'DOCUMENT_ROOT');
    }

    public function getGatewayInterface()
    {
        return filter_input(INPUT_SERVER, 'GATEWAY_INTERFACE');
    }

    public function getHTTPAccept()
    {
        return filter_input(INPUT_SERVER, 'HTTP_ACCEPT');
    }

    public function getHTTPAcceptEncoding()
    {
        return filter_input(INPUT_SERVER, 'HTTP_ACCEPT_ENCODING');
    }

    public function getHTTPAcceptLanguage()
    {
        return filter_input(INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE');
    }

    public function getHTTPConnection()
    {
        return filter_input(INPUT_SERVER, 'HTTP_CONNECTION');
    }

    public function getHTTPHost()
    {
        return filter_input(INPUT_SERVER, 'HTTP_HOST');
    }

    public function getHTTPS()
    {
        return filter_input(INPUT_SERVER, 'HTTPS');
    }

    public function getHTTPUserAgent()
    {
        return filter_input(INPUT_SERVER, 'HTTP_USER_AGENT');
    }

    public function getPHPSelf()
    {
        return filter_input(INPUT_SERVER, 'PHP_SELF');
    }

    public function getQueryString()
    {
        return filter_input(INPUT_SERVER, 'QUERY_STRING');
    }

    public function getRemoteAddress()
    {
        return filter_input(INPUT_SERVER, 'REMOTE_ADDRESS');
    }

    public function getRemotePort()
    {
        return filter_input(INPUT_SERVER, 'REMOTE_PORT');
    }

    public function getRequestMethod()
    {
        return filter_input(INPUT_SERVER, 'REQUEST_METHOD');
    }

    public function getRequestScheme()
    {
        return filter_input(INPUT_SERVER, 'REQUEST_SCHEME');
    }

    public function getRequestTime($float = false)
    {
        if ($float) {
            $retval = filter_input(INPUT_SERVER, 'REQUEST_TIME_FLOAT');
        } else {
            $retval = filter_input(INPUT_SERVER, 'REQUEST_TIME');
        }

        return $retval;
    }

    public function getRequestURI()
    {
        return filter_input(INPUT_SERVER, 'REQUEST_URI');
    }

    public function getScriptFilename()
    {
        return filter_input(INPUT_SERVER, 'SCRIPT_FILENAME');
    }

    public function getScriptName()
    {
        return filter_input(INPUT_SERVER, 'SCRIPT_NAME');
    }

    public function getServerAddress()
    {
        return filter_input(INPUT_SERVER, 'SERVER_ADDR');
    }

    public function getServerAdmin()
    {
        return filter_input(INPUT_SERVER, 'SERVER_ADMIN');
    }

    public function getServerName()
    {
        return filter_input(INPUT_SERVER, 'SERVER_NAME');
    }

    public function getServerPort()
    {
        return filter_input(INPUT_SERVER, 'SERVER_PORT');
    }

    public function getServerProtocol()
    {
        return filter_input(INPUT_SERVER, 'SERVER_PROTOCOL');
    }

    public function getServerSignature()
    {
        return filter_input(INPUT_SERVER, 'SERVER_SIGNATURE');
    }

    public function isSecureServer()
    {
        $https = $this->getHTTPS();
        return $https !== false && $https !== null;
    }

    /**
     * Checks if a server is valid.
     * Valid servers are Built-in instances or fetched servers.
     * @return boolean Returns true if the server is valid.
     */
    public function isServerValid()
    {
        return ($this->nb_server !== null &&
                ($this->nb_server->isBuiltIn() || $this->nb_server->isFetched()));
    }
}
