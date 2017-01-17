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

/**
 * Class to manage Nabu 3 Apache Hosted Config File
 * @author Rafael Gutierrez <rgutierrez@wiscot.com>
 * @version 3.0.0 Surface
 * @package \providers\apache\httpd\files
 */
class CApacheHostedFile extends CApacheAbstractFile
{
    /**
     * Server instance to build file
     * @var CNabuServer
     */
    private $nb_server;
    /**
     * Site instance to build file
     * @var CNabuSite
     */
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
             . "# Nabu 3 - Apache HTTP Server Host configuration\n"
        ;
    }

    /**
     * Overrides parent method to build the script content from $apache_server,
     * $nb_server and $nb_site descriptors stored in the class.
     * @param string $padding Padding to be applied.
     * @return string Output string to be placed in memory or file.
     */
    protected function getContent($padding = '')
    {
        $output = '';

        $nb_cluster_user = $this->nb_site->getClusterUser();
        if ($nb_cluster_user === null) {
            throw new ENabuCoreException(ENabuCoreException::ERROR_OBJECT_EXPECTED);
        }

        $nb_cluster_user_group = $nb_cluster_user->getGroup();

        $vhosts_list = $this->nb_site->getHostsForUpdate($this->nb_server);

        if (count($vhosts_list) > 0) {
            $module_name = $this->getHTTPServer()->getPHPModule();
            $admin_user = $this->nb_server->getAdminUser();
            $server_key = $this->nb_server->getKey();
            $base_path = $this->nb_server->getBasePath();
            $runtime_path = $base_path . NABU_RUNTIME_FOLDER;
            $icontact_path = ''; //$base_path . '/icontact';
            $mediotecas_path = ''; //$base_path . '/mediotecas';
            $emailing_path = ''; //$base_path . '/emailing';
            $apps_path = ''; //$base_path . '/apps';
            $vhosts_path = $this->nb_server->getVirtualHostsPath();
            $logs_path = $this->nb_server->getLogsPath() . DIRECTORY_SEPARATOR . $server_key;
            $site_base_path = $vhosts_path . $this->nb_site->getBasePath();
            $site_commons = $site_base_path . NABU_COMMONDOCS_FOLDER;
            $conf_path = $site_base_path .NABU_VHOST_CONFIG_FOLDER . DIRECTORY_SEPARATOR . $server_key;
            $site_key = $this->nb_site->getKey();
            $use_framework = $this->nb_site->isValueEqualThan('nb_site_use_framework', 'T');
            $framework_path = $base_path . $this->nb_server->getFrameworkPath();

//            foreach($vhosts_list as $vhost) {
//                $server_name = $vhost['host']['nb_domain_zone_host_name'].'.'.$vhost['host']['nb_domain_zone_name'];
//                $use_ssl = ($vhost['host']['nb_cluster_group_service_use_ssl'] === 'T');
//                $docs = ($use_ssl ? NABU_HTTPDOCS_FOLDER : NABU_HTTPSDOCS_FOLDER);
//                if (array_key_exists('redirections', $vhost) && count($vhost['redirections']) > 0) {
//                    foreach ($vhost['redirections']  as $redirection) {
//                        $target_name = $redirection['nb_domain_zone_host_name'].'.'.$redirection['nb_domain_zone_name'];
//                        $target_ssl = ($redirection['nb_cluster_group_service_use_ssl'] === 'T');
//                        $output .= $padding . "<VirtualHost " . $redirection['nb_ip_ip'] . ':' . $redirection['nb_server_host_port'] . ">\n");
//                        $output .= $padding . "        ServerName $target_name\n");
//                        $output .= $padding . "\n");
//                        if ($target_ssl) {
//                            $output .= $padding . "        <IfModule mod_ssl.c>\n");
//                            $output .= $padding . "                SSLEngine on\n");
//                            $output .= $padding . "                SSLVerifyClient none\n");
//                            if (file_exists("$site_base_path/private/$site_key.crt")) {
//                                $output .= $padding . "                SSLCertificateFile $site_base_path/private/$site_key.crt\n");
//                            }
//                            if (file_exists("$site_base_path/private/$site_key" . "_private.key")) {
//                                $output .= $padding . "                SSLCertificateKeyFile $site_base_path/private/$site_key" . "_private.key\n");
//                            }
//                            if (file_exists("$site_base_path/private/$site_key" . "_intermediate.crt")) {
//                                $output .= $padding . "                SSLCertificateChainFile $site_base_path/private/$site_key" . "_intermediate.crt\n");
//                            }
//                            if (file_exists("$site_base_path/private/$site_key" . "_root.crt")) {
//                                $output .= $padding . "                SSLCACertificateFile $site_base_path/private/$site_key" . "_root.crt\n");
//                            }
//                            $output .= $padding . "                SSLProxyEngine on\n");
//                            $output .= $padding . "        </IfModule>\n");
//                        } else {
//                            $output .= $padding . "        <IfModule mod_ssl.c>\n");
//                            $output .= $padding . "                SSLEngine off\n");
//                            $output .= $padding . "        </IfModule>\n");
//                        }
//                        $output .= $padding . "\n");
//                        $output .= $padding . "        RewriteEngine on\n");
//                        $output .= $padding . "        RewriteRule ^(.*)$ ".($use_ssl ? 'https:://' : 'http://').$server_name."/$1?%{QUERY_STRING} [L]\n");
//
//                        $output .= $padding . "</VirtualHost>\n");
//                    }
//                }
//            }

            foreach($vhosts_list as $vhost) {
                $server_name = $vhost['host']['nb_domain_zone_host_name'].'.'.$vhost['host']['nb_domain_zone_name'];
                $use_ssl = ($vhost['host']['nb_cluster_group_service_use_ssl'] === 'T');
                $docs = ($use_ssl ? NABU_HTTPSDOCS_FOLDER : NABU_HTTPDOCS_FOLDER);
                $output .= $padding . "<VirtualHost " . $vhost['host']['nb_ip_ip'] . ':' . $vhost['host']['nb_server_host_port'] . ">\n";
                $output .= $padding . "        ServerName $server_name\n";
                if (array_key_exists('aliases', $vhost) && count($vhost['aliases']) > 0) {
                    $aux = "";
                    foreach ($vhost['aliases'] as $alias) {
                        $name = "$alias[nb_domain_zone_host_name].$alias[nb_domain_zone_name]";
                        $aux .= (strlen($aux) > 0 ? ' ' : '') . $name;
                    }
                    if (strlen($aux) > 0) {
                        $output .= $padding . "        ServerAlias $aux\n";
                    }
                }

                $output .= $padding . "\n";
                $output .= $padding . "        UseCanonicalName Off\n";
                $output .= $padding
                        . "        SuexecUserGroup "
                        . $nb_cluster_user->getOSNick()
                        . ' '
                        . $nb_cluster_user_group->getOSNick()
                        . "\n";

                if ($admin_user !== null) {
                    $output .= $padding . "        ServerAdmin \"" . $admin_user->getEmail() . "\"\n";
                }

                $output .= $padding . "        DocumentRoot $site_base_path$docs\n";

                $output .= $padding . "        CustomLog \"|/usr/sbin/rotatelogs -l $logs_path/$server_name.%Y%m%d.access_log 86400\" combined\n";
                $output .= $padding . "        ErrorLog \"|/usr/sbin/rotatelogs -l $logs_path/$server_name.%Y%m%d.error_log 86400\"\n";

                $output .= $padding . "\n";
                if ($use_ssl) {
                    $output .= $padding . "        <IfModule mod_ssl.c>\n";
                    $output .= $padding . "                SSLEngine on\n";
                    $output .= $padding . "                SSLVerifyClient none\n";
                    if (file_exists("$site_base_path/private/$site_key.crt")) {
                        $output .= $padding . "                SSLCertificateFile $site_base_path/private/$site_key.crt\n";
                    }
                    if (file_exists("$site_base_path/private/$site_key" . "_private.key")) {
                        $output .= $padding . "                SSLCertificateKeyFile $site_base_path/private/$site_key" . "_private.key\n";
                    }
                    if (file_exists("$site_base_path/private/$site_key" . "_intermediate.crt")) {
                        $output .= $padding . "                SSLCertificateChainFile $site_base_path/private/$site_key" . "_intermediate.crt\n";
                    }
                    if (file_exists("$site_base_path/private/$site_key" . "_root.crt")) {
                        $output .= $padding . "                SSLCACertificateFile $site_base_path/private/$site_key" . "_root.crt\n";
                    }
                    $output .= $padding . "                SSLProxyEngine on\n";
                    $output .= $padding . "        </IfModule>\n";
                } else {
                    $output .= $padding . "        <IfModule mod_ssl.c>\n";
                    $output .= $padding . "                SSLEngine off\n";
                    $output .= $padding . "        </IfModule>\n";
                }
                $output .= $padding . "\n";

                $output .= $padding . "        <Directory $site_base_path$docs>\n";
                $output .= $padding . "                <IfModule $module_name>\n";
                $output .= $padding . "                        php_admin_flag engine on\n";
                $output .= $padding . "                        php_admin_flag safe_mode off\n";
                $output .= $padding . "                        php_admin_value open_basedir \"" . NABU_ETC_PATH . PATH_SEPARATOR . "$site_base_path:$icontact_path:$mediotecas_path:$emailing_path:$apps_path" . ($use_framework ? ':'.$framework_path : '') . ":/tmp\"\n";
                if ($use_framework) {
                    $output .= $padding . "                        php_value include_path \".:$framework_path\"\n";
                }
                $output .= $padding . "                </IfModule>\n";
                $output .= $padding . "                AllowOverride All\n";
                $output .= $padding . "                Options +Includes -ExecCGI +FollowSymLinks\n";
                $output .= $padding . "                Require all granted\n";
                $output .= $padding . "        </Directory>\n";
                $output .= $padding . "\n";

                $output .= $padding . "        Alias /runtime $runtime_path\n";
                $output .= $padding . "        <Location /runtime>\n";
                $output .= $padding . "                Order allow,deny\n";
                $output .= $padding . "                Allow from all\n";
                $output .= $padding . "        </Location>\n";
                $output .= $padding . "        <Directory $runtime_path>\n";
                $output .= $padding . "                AllowOverride All\n";
                $output .= $padding . "                Require all granted\n";
                $output .= $padding . "        </Directory>\n";
                $output .= $padding . "        <Directory $runtime_path/nbfw/3.0>\n";
                $output .= $padding . "                <IfModule $module_name>\n";
                $output .= $padding . "                        php_admin_flag engine on\n";
                $output .= $padding . "                        php_admin_flag safe_mode off\n";
                $output .= $padding . "                        php_admin_value open_basedir \"$site_base_path:$runtime_path/nbfw/3.0:$icontact_path:$mediotecas_path:$emailing_path:$apps_path" . ($use_framework ? ':'.$framework_path : '') . ":/tmp\"\n";
                if ($use_framework) {
                    $output .= $padding . "                        php_value include_path \".:$framework_path\"\n";
                }
                $output .= $padding . "                </IfModule>\n";
                $output .= $padding . "                Options -Includes -ExecCGI\n";
                $output .= $padding . "                Require all granted\n";
                $output .= $padding . "        </Directory>\n";
                $output .= $this->populateCommonDocs($padding . '        ', $site_commons);
                $output .= $padding . "\n";

                if ($this->nb_site->isValueEqualThan('nb_site_enable_vhost_file', 'T') && file_exists("$conf_path/vhost.conf")) {
                    $output .= $padding . "        Include $conf_path/vhost.conf\n";
                }

                if (file_exists("$conf_path/aliases.conf")) {
                    $output .= $padding . "        Include $conf_path/aliases.conf\n";
                }

                if (file_exists("$conf_path/mediotecas.conf")) {
                    $output .= $padding . "        Include $conf_path/mediotecas.conf\n";
                }

                $output .= $padding . "</VirtualHost>\n";
            }
        }

        return $output;
    }
}
