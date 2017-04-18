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

/**
 * @author Rafael Gutierrez <rgutierrez@nabu-3.com>
 * @since 0.0.1
 * @version 0.0.7
 * @package \providers\apache\httpd\files
 */
class CApacheClusteredIndex extends CApacheHostedIndex
{
    /**
     * Overrides parent method to place the main header of file before the license agreement.
     * @return string Returns the text to be placed at the start of the file.
     */
    protected function getDescriptor() : string
    {
        return "# ===========================================================================\n"
             . "# nabu-3 - Apache HTTP Server Cluster Index\n"
        ;
    }
}
