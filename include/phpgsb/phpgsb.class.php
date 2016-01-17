<?php
/**
 * phpGSB - PHP Google Safe Browsing Implementation
 * Version 0.2.4
 * Released under New BSD License (see LICENSE)
 * Copyright (c) 2010-2012, Sam Cleaver (Beaver6813, Beaver6813.com)
 * All rights reserved.
 */

class phpGSB {
    public $apikey = "";
    private $version = "0.2";
    private $realversion = "0.2.4";

    //DO NOT CHANGE API VERSION
    var $apiversion = "2.2";

    private $ob = "";
    private $adminemail = "";
    public $usinglists = array(
        'googpub-phish-shaprivate',
        'goog-malware-shaprivate'
    );

    public $serviceScheme = 'https';
    public $serviceResourcePrefix = 'safebrowsing/';
    public $serviceDomain = 'safebrowsing.clients.google.com';

    private $mainlist = array();
    private $verbose = true;
    private $transtarted = false;
    private $transenabled = true;
    private $pingfilepath = "";

    private $debug = false;
    public $debugLog = array();

    private $db;

    public function __construct($database = false, $username = false, $password = false, $host = "localhost", $verbose = false) {
        if ($database && $username) {
            $this->dbConnect($database, $username, $password, $host);
        }
        $this->verbose = $verbose;
    }

    /**
     * Get url to service resource with parameters
     *
     * @param string $resource
     * @return string
     */
    public function getServiceUrl($resource = '') {
        return $this->serviceScheme . '://' . $this->serviceDomain . '/' . $this->serviceResourcePrefix .
                $resource . '?client=api&apikey=' . $this->apikey . '&appver=' . $this->version . '&pver=' . $this->apiversion;
    }

    public function setService($domain, $resource_prefix = '', $scheme = 'https') {
        $this->serviceDomain = $domain;
        $this->serviceScheme = $scheme;
        $this->serviceResourcePrefix = $resource_prefix;
    }

    public function __destruct() {
        $this->close();
    }

    private function close() {
        $this->log("Closing phpGSB. (Peak Memory: " . (round(memory_get_peak_usage() / 1048576, 3)) . "MB)");
    }

    public function silent() {
        $this->verbose = false;
    }

    public function enableDebug() {
        $this->debug = true;
    }

    public function resetDebugLog() {
        $this->debugLog = array();
    }

    public function setApiKey($apikey) {
        $this->apikey = $apikey;
    }

    public function trans_disable() {
        $this->transenabled = false;
    }

    public function trans_enable() {
        $this->transenabled = true;
    }

    private function trans_begin() {
        if ($this->transenabled) {
            $this->transtarted = true;
            $this->log("Begin MySQL Transaction");
            $this->db->query('START TRANSACTION;');
        }
    }

    private function trans_commit() {
        if ($this->transtarted && $this->transenabled) {
            $this->transtarted = false;
            $this->log("Comitting Transaction");
            $this->db->query('COMMIT;');
        }
    }

    private function trans_rollback() {
        if ($this->transtarted && $this->transenabled) {
            $this->transtarted = false;
            $this->log("Rolling Back Transaction");
            $this->db->query('ROLLBACK;');
        }
    }

    /**
     * Function to output messages, used instead of echo,
     * will make it easier to have a verbose switch in later releases
     */
    private function log($msg) {
        if ($this->verbose) {
            echo $msg . "\n";
        }
    }

    /**
     * Function to output errors, used instead of echo,
     * will make it easier to have a verbose switch in later releases
     */
    private function fatalerror($msg) {
        if ($this->verbose) {
            print_r($msg);
            echo "\n";
        }

        $this->trans_rollback();
        throw new Exception($msg);
    }

    /**
     * Wrapper to connect to database.
     */
    private function dbConnect($database, $username, $password, $host = "localhost") {
        $this->db = new PDO('mysql:host=' . $host . ';dbname=' . $database,
            $username,
            $password
        );

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Simple logic function to calculate timeout based on the number of previous errors
     */
    private function calc($errors) {
        // According to Developer Guide Formula
        if ($errors == 1) {
            // According to Developer Guide (1st error, wait a minute)
            return 60;
        } elseif ($errors > 5) {
            // According to Developer Guide (Above 5 errors check every 4 hours)
            return 28800;
        } else {
            // According to Developer Guide we simply double up our timeout each
            // time and use formula:
            // (Adapted to be relative to errors) ( ((2^$errors) * 7.5) *
            // (decimalrand(0,1) + 1)) to produce
            // a result between: 120min-240min for example
            return floor((pow(2, $errors) * 7.5) * ((rand(0, 1000) / 1000) + 1));
        }
    }

    /**
     * Writes backoff timeouts, uses calc() to calculate timeouts and then writes to file
     * for next check
     */
    private function Backoff($errdata = false, $type) {
        $file = ($type == 'data' ? 'nextcheck.dat' : 'nextcheckl.dat');

        $curstatus = explode('||', file_get_contents($this->pingfilepath . $file));
        $curstatus[1] = $curstatus[1] + 1;
        $seconds = $this->calc($curstatus[1]);
        $until = time() + $seconds . '||' . $curstatus[1];
        file_put_contents($this->pingfilepath . $file, $until);
        $this->fatalerror(array(
            "Invalid Response... Backing Off",
            $errdata
        ));
    }

    /**
     * Writes timeout from valid requests to nextcheck file
     */
    private function setTimeout($seconds) {
        if (file_exists($this->pingfilepath . 'nextcheck.dat')) {
            $curstatus = explode('||', @file_get_contents($this->pingfilepath . 'nextcheck.dat'));
            $until = time() + $seconds . '||' . $curstatus[1];
        } else {
            $until = time() + $seconds . '||';
        }

        file_put_contents($this->pingfilepath . 'nextcheck.dat', $until);
    }

    /**
     * Checks timeout in timeout files (usually performed at the
     * start of script)
     */
    private function checkTimeout($type) {
        $file = ($type == 'data' ? 'nextcheck.dat' : 'nextcheckl.dat');

        $curstatus = explode('||', @file_get_contents($this->pingfilepath . $file));
        if (time() < $curstatus[0]) {
            $this->fatalerror("Must wait another " . ($curstatus[0] - time()) . " seconds before another request");
        }

        $this->log("Allowed to request");
    }

    /**
     * Function downloads from URL's, POST data can be
     * passed via $options. $followbackoff indicates
     * whether to follow backoff procedures or not
     */
    private function download($url, $options = NULL, $followbackoff = false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if (is_array($options)) {
            curl_setopt_array($ch, $options);
        }

        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($followbackoff && $info['http_code'] > 299) {
            $this->Backoff($info, $followbackoff);
        }

        return array(
            $info,
            $data
        );
    }

    //UPDATER FUNCTIONS

    /**
     * Resets lists database, only called if GSB issues r:resetdatabase
     */
    private function resetDatabase() {
        // Lord knows why they would EVER issue this request!
        if (!empty($this->adminemail)) {
            mail($this->adminemail, 'Reset Database Request Issued', 'For some crazy unknown reason GSB requested a database reset at ' . time());
        }

        foreach ($this->usinglists as $value) {
            $this->query("TRUNCATE TABLE `$value-s-index`");
            $this->query("TRUNCATE TABLE `$value-s-hosts`");
            $this->query("TRUNCATE TABLE `$value-s-prefixes`");
            $this->query("TRUNCATE TABLE `$value-a-index`");
            $this->query("TRUNCATE TABLE `$value-a-hosts`");
            $this->query("TRUNCATE TABLE `$value-a-prefixes`");
        }
    }

    /**
     * Processes data recieved from a GSB data request into a managable array
     */
    private function processChunks($data, $listname) {
        $len = strlen($data);
        $offset = 0;
        while ($offset < $len) {
            $x = strpos($data, ':', $offset);
            $type = substr($data, $offset, $x-$offset);

            $offset = $x+1;
            $x = strpos($data, ':', $offset);
            $chunknum = substr($data, $offset, $x-$offset);
            $offset = $x+1;
            if (!is_numeric($chunknum)) {
                $this->fatalerror(array(
                    "Decoding Error, chunknum is not numeric!",
                    $chunknum
                ));
            }

            $x = strpos($data, ':', $offset);
            $hashlen = substr($data, $offset, $x-$offset);
            $offset = $x+1;
            if (!is_numeric($hashlen)) {
                $this->fatalerror(array(
                    "Decoding Error, hashlen is not numeric!",
                    $hashlen
                ));
            }
            $x = strpos($data, "\n", $offset);
            $chunklen = substr($data, $offset, $x-$offset);
            $offset = $x+1;
            $chunkdata = NULL;
            if (!is_numeric($chunklen)) {
                $this->fatalerror(array(
                    "Decoding Error, chunklen is not numeric!",
                    $chunklen
                ));
            }
            if ($chunklen > 0) {
                $chunkdata = bin2hex(substr($data, $offset, $chunklen));
                $offset += $chunklen;
            }

            if ($type != 'a' && $type != 's') {
                $this->log("DISCARDED CHUNKNUM: $chunknum (Had no valid label)");
                continue;
            }

            $dataArr = array(
                'chunknum' => $chunknum,
                'hashlen' => $hashlen,
                'chunklen' => $chunklen,
                'real' => array()
            );

            $chunkOffset = 0;
            while ($chunkOffset < $chunklen) {
                $row = array(
                    'hostkey' => substr($chunkdata, $chunkOffset, 8),
                    'count' => hexdec(substr($chunkdata, $chunkOffset+8, 2)),
                    'pairs' => array()
                );

                $chunkOffset += 10;
                if ($row['count'] > 0) {
                    for ($i = 0; $i < $row['count']; $i++) {
                        $pair = array();
                        if ($type == 's') {
                            $pair['addchunknum'] = hexdec(substr($chunkdata, $chunkOffset, 8));
                            $chunkOffset += 8;
                        }
                        $pair['prefix'] = substr($chunkdata, $chunkOffset, ($hashlen * 2));
                        $chunkOffset += ($hashlen * 2);
                        $row['pairs'][] = $pair;
                    }
                } elseif ($row['count'] == 0 && $type == 's') {
                    $row['pairs'][] = array(
                        'addchunknum' => hexdec(substr($chunkdata, $chunkOffset, 8))
                    );
                    $chunkOffset += 8;
                } elseif ($row['count'] < 0) {
                    $this->fatalerror(array(
                        "Decoding Error, Somethings gone wrong!",
                        array($row, $type)
                    ));
                }
                $dataArr['real'][] = $row;
            }
            $this->saveChunkPart($dataArr, ($type == 's' ? 'SUB' : "ADD"), $listname);
            unset($dataArr);
        }
        return true;
    }

    /**
     * Saves processed data to the MySQL database
     */
    private function saveChunkPart($data, $type, $listname) {
        $buildindex = array();
        $buildindexValues = array();
        $buildhost = array();
        $buildhostValues = array();
        $buildpairs = array();
        $buildpairsValues = array();

        //Check what type of data it is...
        if ($type == "SUB") {
            $listtype = 's';
        } elseif ($type == "ADD") {
            $listtype = 'a';
        } else {
            $this->fatalerror(array(
                "Invalid type given!",
                $type
            ));
        }

        if (!isset($this->mainlist[$listtype][$listname][$data['chunknum']])) {
            $this->mainlist[$listtype][$listname][$data['chunknum']] = true;
            $buildindex[] = "(?, ?)";
            $buildindexValues[] = $data['chunknum'];
            $buildindexValues[] = $data['chunklen'];

            foreach ($data['real'] as $newkey => $newvalue) {
                $buildhost[] = "(?, ?, ?, '')";
                $buildhostValues[] = $newvalue['hostkey'];
                $buildhostValues[] = $data['chunknum'];
                $buildhostValues[] = $newvalue['count'];
                foreach ($newvalue['pairs'] as $innerkey => $innervalue) {
                    $buildpairs[] = "(?, " . ($type == 'SUB' ? '?, ' : '') . "?, '')";
                    $buildpairsValues[] = $newvalue['hostkey'];
                    if ($type == 'SUB') {
                       $buildpairsValues[] = $innervalue['addchunknum'];
                    }

                    $buildpairsValues[] = (isset($innervalue['prefix']) ? $innervalue['prefix'] : '');
                }
            }
        }


        if (!empty($buildindex)) {
            //Insert index value
            $this->query('INSERT IGNORE INTO `' . $listname . '-' . $listtype. '-index` (`chunk_num`,`chunk_len`) VALUES ' . implode(',', $buildindex), $buildindexValues);
        }

        if (!empty($buildhost)) {
            //Insert index value
            $this->query('INSERT IGNORE INTO `' . $listname . '-' . $listtype. '-hosts` (`hostkey`,`chunk_num`,`count`,`fullhash`) VALUES ' . implode(',', $buildhost), $buildhostValues);
        }

        if (!empty($buildpairs)) {
            //Insert index value
            $this->query('INSERT IGNORE INTO `' . $listname . '-' . $listtype. '-prefixes` (`hostkey`, ' .
                        ($type == 'SUB' ? '`add_chunk_num`, ' : '') . '`prefix`,`fullhash`) VALUES ' .
                         implode(',', $buildpairs), $buildpairsValues);
        }
    }

    /**
     * Get ranges of existing chunks from a requested list
     * and type (add [a] or sub [s] return them and set
     * mainlist to recieved for that chunk (prevent dupes)
     */
    private function getRanges($listname, $mode) {
        $checktable = $listname . '-' . $mode . '-index';

        $ranges = array();
        $i = 0;
        $start = 0;
        $stm = $this->query('SELECT chunk_num FROM `' . $checktable . '` ORDER BY `chunk_num` ASC');
        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $this->mainlist[$mode][$listname][$row['chunk_num']] = true;
            if ($i == 0) {
                $start = $row['chunk_num'];
                $previous = $row['chunk_num'];
            } else {
                $expected = $previous + 1;
                if ($row['chunk_num'] != $expected) {
                    if ($start == $previous) {
                        $ranges[] = $start;
                    } else {
                        $ranges[] = $start . '-' . $previous;
                    }
                    $start = $row['chunk_num'];
                }
                $previous = $row['chunk_num'];
            }
            $i++;
        }

        if ($start > 0 && $previous > 0) {
            if ($start == $previous) {
                $ranges[] = $start;
            } else {
                $ranges[] = $start . '-' . $previous;
            }
        }
        return $ranges;
    }

    /**
     * Get both add and sub ranges for a requested list
     */
    private function getFullRanges($listname) {
        $subranges = $this->getRanges($listname, 's');
        $addranges = $this->getRanges($listname, 'a');
        return array(
            "Subranges" => $subranges,
            "Addranges" => $addranges
        );
    }

    /**
     * Format a full request body for a desired list including
     * name and full ranges for add and sub
     */
    private function formattedRequest($listname) {
        $fullranges = $this->getFullRanges($listname);
        $buildpart = '';

        if (count($fullranges['Subranges']) > 0) {
            $buildpart .= 's:' . implode(',', $fullranges['Subranges']);
        }

        if (count($fullranges['Subranges']) > 0 && count($fullranges['Addranges']) > 0) {
            $buildpart .= ':';
        }

        if (count($fullranges['Addranges']) > 0) {
            $buildpart .= 'a:' . implode(',', $fullranges['Addranges']);
        }

        return $listname . ';' . $buildpart . "\n";
    }

    /**
     * Called when GSB returns a SUB-DEL or ADD-DEL response
     */
    private function deleteRange($range, $mode, $listname) {
        $params = array();
        $buildtrunk = $listname . '-' . $mode;
        if (strpos($range, '-') !== false) {
            $params = explode('-', trim($range), 2);
            $clause = "`chunk_num` >= ? AND `chunk_num` <= ?";
        } else {
            $params[] = $range;
            $clause = "`chunk_num` = ?";
        }

        // Delete from index
        $this->query('DELETE FROM `' . $buildtrunk . '-index` WHERE ' . $clause, $params);

        // Select all host keys that match chunks (we'll delete them after but we
        // need the hostkeys list!)
        $stm = $this->query('SELECT `hostkey` FROM `' . $buildtrunk . '-hosts` WHERE ' . $clause . " AND hostkey != ''", $params);
        $buildprefixdel = array();
        while ($row = $stm->fetch(\PDO::FETCH_ASSOC)) {
            $buildprefixdel[] = $row['hostkey'];
        }

        if (!empty($buildprefixdel)) {
            $this->query('DELETE FROM `' . $buildtrunk . '-hosts` WHERE hostkey IN (' . substr(str_repeat('?, ', count($buildprefixdel)), 0, -2) . ')', $buildprefixdel);

            //Delete all matching hostkeys
            $this->query('DELETE FROM `' . $buildtrunk . '-hosts` WHERE ' . $clause, $params);
        }
    }

    public function getList() {
        $url = $this->getServiceUrl('list');
        $result = $this->download($url);
        return explode("\n", trim($result[1]));
    }

    /**
     * Main part of updater function, will call all other functions, merely
     * requires the request body, it will then process and save all data as well as checking
     * for ADD-DEL and SUB-DEL, runs silently so won't return anything on success
     */
    private function getData($body, $skipSetTimeout = false) {
        if (empty($body)) {
            return $this->fatalerror("Missing a body for data request");
        }

        $this->trans_begin();
        $buildopts = array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body . "\n"
        );

        $url = $this->getServiceUrl('downloads');
        $result = $this->download($url, $buildopts, "data");

        if (preg_match('/n:(\d+)/', $result[1], $match)) {
            if (!$skipSetTimeout) {
               $this->setTimeout($match[1]);
            }
        } else {
            return $this->fatalerror("Missing timeout");
        }

        if (strpos($result[1], 'r:pleasereset') !== false) {
            $this->resetDatabase();
            return true;
        }

        if (!preg_match_all('/i:(.+?)\n(.+?)(?=i:|$)/s', $result[1], $blocks, PREG_PATTERN_ORDER)) {
            $this->log('No data available in list');
            return true;
        }

        foreach ($blocks[1] as $id => $listname) {
            if (!preg_match_all('/\s*([^:]+):(.+)/', $blocks[2][$id], $elements, PREG_PATTERN_ORDER)) {
                return $this->fatalerror('could not parse response');
            }

            foreach ($elements[1] as $id => $type) {
                $value = trim($elements[2][$id]);
                switch($type) {
                    case 'u':
                        $chunkdata = $this->download('http://' . $value, false, "data");
                        $processed = $this->processChunks($chunkdata[1], $listname);
                        $this->log("Saved a chunk file: " . $value);
                        break;
                    case 'sd':
                    case 'ad':
                        $delType = substr($type, 0, 1);
                        foreach (explode(',', $value) as $keyadd => $valueadd) {
                            $this->deleteRange($valueadd, $delType, $listname);
                        }
                        break;
                }
            }
        }

        $this->trans_commit();
        return true;
    }

    /**
     * Shortcut to run updater
     */
    public function runUpdate($skipCheckTimeout = false, $skipSetTimeout = false) {
        if (!$skipCheckTimeout) {
            $this->checkTimeout('data');
        }
        $require = "";
        foreach ($this->usinglists as $value) {
            $require .= $this->formattedRequest($value);
        }

        $this->log("Using $require");
        $this->getData($require, $skipSetTimeout);
    }

    //LOOKUP FUNCTIONS
    /**
     * Used to check the canonicalize function
     */
    public function validateMethod() {
        //Input => Expected
        $cases = array(
            "http://host/%25%32%35" => "http://host/%25",
            "http://host/%25%32%35%25%32%35" => "http://host/%25%25",
            "http://host/%2525252525252525" => "http://host/%25",
            "http://host/asdf%25%32%35asd" => "http://host/asdf%25asd",
            "http://host/%%%25%32%35asd%%" => "http://host/%25%25%25asd%25%25",
            "http://www.google.com/" => "http://www.google.com/",
            "http://%31%36%38%2e%31%38%38%2e%39%39%2e%32%36/%2E%73%65%63%75%72%65/%77%77%77%2E%65%62%61%79%2E%63%6F%6D/" => "http://168.188.99.26/.secure/www.ebay.com/",
            "http://195.127.0.11/uploads/%20%20%20%20/.verify/.eBaysecure=updateuserdataxplimnbqmn-xplmvalidateinfoswqpcmlx=hgplmcx/" => "http://195.127.0.11/uploads/%20%20%20%20/.verify/.eBaysecure=updateuserdataxplimnbqmn-xplmvalidateinfoswqpcmlx=hgplmcx/",
            "http://host%23.com/%257Ea%2521b%2540c%2523d%2524e%25f%255E00%252611%252A22%252833%252944_55%252B" => 'http://host%23.com/~a!b@c%23d$e%25f^00&11*22(33)44_55+',
            "http://3279880203/blah" => "http://195.127.0.11/blah",
            "http://www.google.com/blah/.." => "http://www.google.com/",
            "www.google.com/" => "http://www.google.com/",
            "www.google.com" => "http://www.google.com/",
            "http://www.evil.com/blah#frag" => "http://www.evil.com/blah",
            "http://www.GOOgle.com/" => "http://www.google.com/",
            "http://www.google.com.../" => "http://www.google.com/",
            "http://www.google.com/foo\tbar\rbaz\n2" => "http://www.google.com/foobarbaz2",
            "http://www.google.com/q?" => "http://www.google.com/q?",
            "http://www.google.com/q?r?" => "http://www.google.com/q?r?",
            "http://www.google.com/q?r?s" => "http://www.google.com/q?r?s",
            "http://evil.com/foo#bar#baz" => "http://evil.com/foo",
            "http://evil.com/foo;" => "http://evil.com/foo;",
            "http://evil.com/foo?bar;" => "http://evil.com/foo?bar;",
            "http://\x01\x80.com/" => "http://%01%80.com/",
            "http://notrailingslash.com" => "http://notrailingslash.com/",
            "http://www.gotaport.com:1234/" => "http://www.gotaport.com:1234/",
            "  http://www.google.com/  " => "http://www.google.com/",
            "http:// leadingspace.com/" => "http://%20leadingspace.com/",
            "http://%20leadingspace.com/" => "http://%20leadingspace.com/",
            "%20leadingspace.com/" => "http://%20leadingspace.com/",
            "https://www.securesite.com/" => "https://www.securesite.com/",
            "http://host.com/ab%23cd" => "http://host.com/ab%23cd",
            "http://host.com//twoslashes?more//slashes" => "http://host.com/twoslashes?more//slashes"
        );

        foreach ($cases as $key => $value) {
            $canit = self::canonicalizeURL($key);
            $canit = $canit['GSBURL'];
            if ($canit == $value) {
                $this->log("<span style='color:green'>PASSED: $key</span>");
            } else {
                $this->log("<span style='color:red'>INVALid: <br>ORIGINAL: $key<br>EXPECTED: $value<br>RECIEVED: $canit<br> </span>");
            }
        }
    }

    /**
     * Special thanks Steven Levithan (stevenlevithan.com) for the ridiculously complicated regex
     * required to parse urls. This is used over parse_url as it robustly provides access to
     * port, userinfo etc and handles mangled urls very well.
     *
     * Expertly integrated into phpGSB by Sam Cleaver ;)
     * Thanks to mikegillis677 for finding the seg. fault issue in the old function.
     * Passed validateMethod() check on 17/01/12
     */
    private static function j_parseUrl($url) {
        $strict = '/^(?:([^:\/?#]+):)?(?:\/\/\/?((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?))?(((?:\/(\w:))?((?:[^?#\/]*\/)*)([^?#]*))(?:\?([^#]*))?(?:#(.*))?)/';
        $loose = '/^(?:(?![^:@]+:[^:@\/]*@)([^:\/?#.]+):)?(?:\/\/\/?)?((?:(([^:@]*):?([^:@]*))?@)?([^:\/?#]*)(?::(\d*))?)(((?:\/(\w:))?(\/(?:[^?#](?![^?#\/]*\.[^?#\/.]+(?:[?#]|$)))*\/?)?([^?#\/]*))(?:\?([^#]*))?(?:#(.*))?)/';
        preg_match($loose, $url, $match);
        if (empty($match)) {
            //As odd as its sounds, we'll fall back to strict (as technically its
            // more correct and so may salvage completely mangled urls)
            unset($match);
            preg_match($strict, $url, $match);
        }
        $parts = array(
            "source" => '',
            "scheme" => '',
            "authority" => '',
            "userinfo" => '',
            "user" => '',
            "password" => '',
            "host" => '',
            "port" => '',
            "relative" => '',
            "path" => '',
            "drive" => '',
            "directory" => '',
            "file" => '',
            "query" => '',
            "fragment" => ''
        );
        switch (count ($match)) {
            case 15 :
                $parts['fragment'] = $match[14];
            case 14 :
                $parts['query'] = $match[13];
            case 13 :
                $parts['file'] = $match[12];
            case 12 :
                $parts['directory'] = $match[11];
            case 11 :
                $parts['drive'] = $match[10];
            case 10 :
                $parts['path'] = $match[9];
            case 9 :
                $parts['relative'] = $match[8];
            case 8 :
                $parts['port'] = $match[7];
            case 7 :
                $parts['host'] = $match[6];
            case 6 :
                $parts['password'] = $match[5];
            case 5 :
                $parts['user'] = $match[4];
            case 4 :
                $parts['userinfo'] = $match[3];
            case 3 :
                $parts['authority'] = $match[2];
            case 2 :
                $parts['scheme'] = $match[1];
            case 1 :
                $parts['source'] = $match[0];
        }
        return $parts;
    }

    /**
     * Regex to check if its a numerical IP address
     */
    private static function is_ip($ip) {
        return preg_match("/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])" . "(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/", $ip);
    }

    /**
     * Checks if input is in hex format
     */
    private static function is_hex($x) {
        // Relys on the fact that hex often includes letters meaning PHP will
        // disregard the string
        if (($x + 3) == 3) {
            return dechex(hexdec($x)) == $x;
        }

        return false;
    }

    /**
     * Checks if input is in octal format
     */
    private static function is_octal($x) {
        //Relys on the fact that in IP addressing octals must begin with a 0 to
        // denote octal
        return substr($x, 0, 1) == 0;
    }

    /**
     * Converts hex or octal input into decimal
     */
    private static function hexoct2dec($value) {
        //As this deals with parts in IP's we can be more exclusive
        if (substr_count(substr($value, 0, 2), '0x') > 0 && self::is_hex($value)) {
            return hexdec($value);
        } elseif (self::is_octal($value)) {
            return octdec($value);
        }

        return false;
    }

    /**
     * Converts IP address part in HEX to decimal
     */
    private static function iphexdec($hex) {
        //Removes any leading 0x (used to denote hex) and then and leading 0's)
        $temp = str_replace('0x', '', $hex);
        $temp = ltrim($temp, "0");
        return hexdec($temp);
    }

    /**
     * Converts full IP address in HEX to decimal
     */
    private static function hexIPtoIP($hex) {
        // Remove hex identifier and leading 0's (not significant)
        $tempip = str_replace('0x', '', $hex);
        $tempip = ltrim($tempip, "0");

        // It might be hex
        if (self::is_hex($tempip)) {
            // There may be a load of junk before the part we need
            if (strlen($tempip) > 8) {
                $tempip = substr($tempip, -8);
            }
            $hexplode = preg_split('//', $tempip, -1, PREG_SPLIT_NO_EMPTY);
            while (count($hexplode) < 8) {
                array_unshift($hexplode, 0);
            }

            // Normalise
            $newip = hexdec($hexplode[0] . $hexplode[1]) . '.' .
                     hexdec($hexplode[2] . $hexplode[3]) . '.' .
                     hexdec($hexplode[4] . $hexplode[5]) . '.' .
                     hexdec($hexplode[6] . $hexplode[7]);
            //Now check if its an IP
            if (self::is_ip($newip)) {
                return $newip;
            }
        }
        return false;
    }

    /**
     * Checks if an IP provided in either hex, octal or decimal is in fact
     * an IP address. Normalises to a four part IP address.
     */
    private static function isValid_IP($ip) {
        // First do a simple check, if it passes this no more needs to be done
        if (self::is_ip($ip)) {
            return $ip;
        }

        // Its a toughy... eerm perhaps its all in hex?
        $checkhex = self::hexIPtoIP($ip);
        if ($checkhex) {
            return $checkhex;
        }

        // If we're still here it wasn't hex... maybe a DWORD format?
        $checkdword = self::hexIPtoIP(dechex($ip));
        if ($checkdword) {
            return $checkdword;
        }

        // Nope... maybe in octal or a combination of standard, octal and hex?!
        $ipcomponents = explode('.', $ip);
        $ipcomponents[0] = self::hexoct2dec($ipcomponents[0]);
        if (count($ipcomponents) == 2) {
            // The writers of the RFC docs certainly didn't think about the
            // clients! This could be a DWORD mixed with an IP part
            if ($ipcomponents[0] <= 255 && is_int($ipcomponents[0]) && is_int($ipcomponents[1])) {
                $threeparts = dechex($ipcomponents[1]);
                $hexplode = preg_split('//', $threeparts, -1, PREG_SPLIT_NO_EMPTY);
                if (count($hexplode) > 4) {
                    $newip = $ipcomponents[0] . '.' .
                             self::iphexdec($hexplode[0] . $hexplode[1]) . '.' .
                             self::iphexdec($hexplode[2] . $hexplode[3]) . '.' .
                             self::iphexdec($hexplode[4] . $hexplode[5]);
                    // Now check if its valid
                    if (self::is_ip($newip)) {
                        return $newip;
                    }
                }
            }
        }

        $ipcomponents[1] = self::hexoct2dec($ipcomponents[1]);
        if (count($ipcomponents) == 3) {
            //Guess what... it could also be a DWORD mixed with two IP parts!
            if (($ipcomponents[0] <= 255 && is_int($ipcomponents[0])) && ($ipcomponents[1] <= 255 && is_int($ipcomponents[1])) && is_int($ipcomponents[2])) {
                $twoparts = dechex($ipcomponents[2]);
                $hexplode = preg_split('//', $twoparts, -1, PREG_SPLIT_NO_EMPTY);
                if (count($hexplode) > 3) {
                    $newip = $ipcomponents[0] . '.' .
                             $ipcomponents[1] . '.' .
                             self::iphexdec($hexplode[0] . $hexplode[1]) . '.' .
                             self::iphexdec($hexplode[2] . $hexplode[3]);
                    // Now check if its valid
                    if ($this->is_ip($newip))
                        return $newip;
                }
            }
        }
        // If not it may be a combination of hex and octal
        if (count($ipcomponents) >= 4) {
            $tmpcomponents = array(
                $ipcomponents[2],
                $ipcomponents[3]
            );

            foreach ($tmpcomponents as $key => $value) {
                if (!$tmpcomponents[$key] = self::hexoct2dec($value)) {
                    return false;
                }
            }

            array_unshift($tmpcomponents, $ipcomponents[0], $ipcomponents[1]);
            // Convert back to IP form
            $newip = implode('.', $tmpcomponents);

            // Now check if its valid
            if (self::is_ip($newip)) {
                return $newip;
            }
        }

        // Well its not an IP that we can recognise... theres only so much we can
        // do!
        return false;
    }

    /**
     * Had to write another layer as built in PHP urlencode() escapes all non
     * alpha-numeric Google states to only urlencode if its below 32 or above
     * or equal to 127 (some of those are non alpha-numeric and so urlencode
     * on its own won't work).
     */
    private static function flexURLEncode($url, $ignorehash = false) {
        // Had to write another layer as built in PHP urlencode() escapes all non
        // alpha-numeric
        // google states to only urlencode if its below 32 or above or equal to
        // 127 (some of those
        // are non alpha-numeric and so urlencode on its own won't work).
        $urlchars = preg_split('//', $url, -1, PREG_SPLIT_NO_EMPTY);
        if (count($urlchars) > 0) {
            foreach ($urlchars as $key => $value) {
                $ascii = ord($value);
                if ($ascii <= 32 || $ascii >= 127 || ($value == '#' && !$ignorehash) || $value == '%') {
                    $urlchars[$key] = rawurlencode($value);
                }
            }

            return implode('', $urlchars);
        }
        return $url;
    }

    /**
     * Canonicalize a full URL according to Google's definition.
     */
    public static function canonicalizeURL($url) {
        // Remove line feeds, return carriages, tabs, vertical tabs
        $finalurl = trim(str_replace(array(
            "\x09",
            "\x0A",
            "\x0D",
            "\x0B"
        ), '', $url));

        // URL Encode for easy extraction
        $finalurl = self::flexURLEncode($finalurl, true);

        // Now extract hostname & path
        $parts = self::j_parseUrl($finalurl);
        $hostname = $parts['host'];
        $path = $parts['path'];
        $query = $parts['query'];
        $lasthost = "";
        $lastpath = "";
        $lastquery = "";

        // Remove all hex coding (loops max of 50 times to stop craziness but
        // should never reach that)
        for ($i = 0; $i < 50; $i++) {
            $hostname = rawurldecode($hostname);
            $path = rawurldecode($path);
            $query = rawurldecode($query);
            if ($hostname == $lasthost && $path == $lastpath && $query == $lastquery)
                break;
            $lasthost = $hostname;
            $lastpath = $path;
            $lastquery = $query;
        }

        // Deal with hostname first
        // Replace all leading and trailing dots
        $hostname = trim($hostname, '.');

        // Replace all consecutive dots with one dot
        $hostname = preg_replace("/\.{2,}/", ".", $hostname);

        // Make it lowercase
        $hostname = strtolower($hostname);

        // See if its a valid IP
        $hostnameip = self::isValid_IP($hostname);
        if ($hostnameip) {
            $usingip = true;
            $usehost = $hostnameip;
        } else {
            $usingip = false;
            $usehost = $hostname;
        }
        // The developer guide has lowercasing and validating IP other way round
        // but its more efficient to
        // have it this way
        // Now we move onto canonicalizing the path
        $pathparts = explode('/', $path);
        foreach ($pathparts as $key => $value) {
            if ($value == "..") {
                if ($key != 0) {
                    unset($pathparts[$key - 1]);
                    unset($pathparts[$key]);
                } else {
                    unset($pathparts[$key]);
                }
            } elseif ($value == "." || empty($value)) {
                unset($pathparts[$key]);
            }
        }

        if (substr($path, -1, 1) == "/") {
            $append = "/";
        } else {
            $append = false;
        }

        $path = "/" . implode("/", $pathparts);

        if ($append && substr($path, -1, 1) != "/") {
            $path .= $append;
        }

        $usehost = self::flexURLEncode($usehost);
        $path = self::flexURLEncode($path);
        $query = self::flexURLEncode($query);

        if (empty($parts['scheme'])) {
            $parts['scheme'] = 'http';
        }

        $canurl = $parts['scheme'] . '://';
        $realurl = $canurl;

        if (!empty($parts['userinfo'])) {
            $realurl .= $parts['userinfo'] . '@';
        }

        $canurl .= $usehost;
        $realurl .= $usehost;

        if (!empty($parts['port'])) {
            $canurl .= ':' . $parts['port'];
            $realurl .= ':' . $parts['port'];
        }

        $canurl .= $path;
        $realurl .= $path;
        if (substr_count($finalurl, "?") > 0) {
            $canurl .= '?' . $parts['query'];
            $realurl .= '?' . $parts['query'];
        }

        if (!empty($parts['fragment'])) {
            $realurl .= '#' . $parts['fragment'];
        }

        return array(
            "GSBURL" => $canurl,
            "CleanURL" => $realurl,
            "Parts" => array(
                "Host" => $usehost,
                "Path" => $path,
                "Query" => $query,
                "IP" => $usingip
            )
        );
    }

    /**
     * SHA-256 input (short method).
     */
    private static function sha256($data) {
        return hash('sha256', $data);
    }

    /**
     * Make hostkeys for use in a lookup
     */
    private static function makeHostKey($host, $usingip) {
        if ($usingip) {
            $hosts = array($host . "/");
        } else {
            $hostparts = explode(".", $host);
            if (count($hostparts) > 2) {
                $backhostparts = array_reverse($hostparts);
                $threeparts = array_slice($backhostparts, 0, 3);
                $twoparts = array_slice($threeparts, 0, 2);
                $hosts = array(
                    implode('.', array_reverse($threeparts)) . "/",
                    implode('.', array_reverse($twoparts)) . "/"
                );
            } else
                $hosts = array($host . "/");
        }

        // Now make key & key prefix
        $returnhosts = array();
        foreach ($hosts as $value) {
            $fullhash = self::sha256($value);
            $returnhosts[$fullhash] = array(
                "Host" => $value,
                "prefix" => substr($fullhash, 0, 8),
                "Hash" => $fullhash
            );
        }

        return $returnhosts;
    }

    /**
     * Hash up a list of values from makeprefixes() (will possibly be combined into that function at a later date
     */
    private static function makeHashes($prefixarray) {
        if (count($prefixarray) > 0) {
            $returnprefixes = array();
            foreach ($prefixarray as $value) {
                $fullhash = self::sha256($value);
                $returnprefixes[$fullhash] = array(
                    "Original" => $value,
                    "prefix" => substr($fullhash, 0, 8),
                    "Hash" => $fullhash
                );
            }
            return $returnprefixes;
        } else
            return false;
    }

    /**
     * Make URL prefixes for use after a hostkey check
     */
    public static function makeprefixes($host, $path, $query, $usingip) {
        $prefixes = array();

        // Exact hostname in the url
        $hostcombos = array();
        $hostcombos[] = $host;
        if (!$usingip) {
            $hostparts = explode('.', $host);
            $backhostparts = array_reverse($hostparts);
            if (count($backhostparts) > 5) {
                $maxslice = 5;
            } else {
                $maxslice = count($backhostparts);
            }

            $topslice = array_slice($backhostparts, 0, $maxslice);
            while ($maxslice > 1) {
                $hostcombos[] = implode('.', array_reverse($topslice));
                $maxslice--;
                $topslice = array_slice($backhostparts, 0, $maxslice);
            }
        } else {
            $hostcombos[] = $host;
        }

        $hostcombos = array_unique($hostcombos);
        $variations = array();
        if (!empty($path)) {
            $pathparts = explode("/", $path);
            if (count($pathparts) > 4) {
                $upperlimit = 4;
            } else {
                $upperlimit = count($pathparts);
            }
        }

        foreach ($hostcombos as $key => $value) {
            if (!empty($query)) {
                $variations[] = $value . $path . '?' . $query;
            }

            $variations[] = $value . $path;
            if (!empty($path)) {
                $i = 0;
                $pathiparts = "";
                while ($i < $upperlimit) {
                    if ($i != count($pathparts) - 1) {
                        $pathiparts = $pathiparts . $pathparts[$i] . "/";
                    } else {
                        $pathiparts = $pathiparts . $pathparts[$i];
                    }
                    $variations[] = $value . $pathiparts;
                    $i++;
                }
            }
        }

        $variations = array_unique($variations);
        return self::makeHashes($variations);
    }

    /**
     * Process data provided from the response of a full-hash GSB
     * request
     */
    private function processFullLookup($data) {
        $extracthash = array();

        $len = strlen($data);
        $offset = 0;
        while ($offset < $len) {
            $x = strpos($data, "\n", $offset);
            $head = substr($data, $offset, $x-$offset);
            $offset = $x+1;
            list($listname, $addchunk, $chunklen) = explode(':', $head, 3);

            if ($chunklen > 0) {
                $extracthash[$listname][$addchunk] = bin2hex(substr($data, $offset, $chunklen));
                $offset += $chunklen;
            }
        }

        return $extracthash;
    }

    /**
     * Add a full-hash key to a prefix or hostkey (the variable is $prefix
     * but it could be either).
     */
    private function addfullhash($prefix, $chunknum, $fullhash, $listname) {
        $buildtrunk = $listname . "-a";

        // First check hosts
        $stm = $this->query("SELECT * FROM `" . $buildtrunk  ."-hosts` WHERE `hostkey` = ? AND `chunk_num` = ? AND fullhash = '' LIMIT 1", array($prefix, $chunknum));
        if ($stm->rowCount() > 0) {
            $row = $stm->fetch(\PDO::FETCH_ASSOC);
            // We've got a live one! Insert the full hash for it
            $this->query("UPDATE `" . $buildtrunk . "-hosts` SET `fullhash` = ? WHERE `id` = ?", array($fullhash, $row['id']));
        } else {
            $this->query("
                UPDATE
                    `" . $buildtrunk  ."-prefixes` p
                    JOIN `" . $buildtrunk . "-hosts` h ON (h.hostkey = p.hostkey)
                SET
                    p.fullhash = ?,
                    h.fullhash = ?
                WHERE
                    p.`prefix` = ? AND
                    p.fullhash = '' AND
                    h.chunk_num = ? AND
                    h.count > 0
                    ", array($fullhash, $fullhash, $prefix, $chunknum));
        }
    }

    /**
     * Check database for any cached full-length hashes for a given prefix.
     */
    private function cacheCheck($prefix) {
        foreach ($this->usinglists as $value) {
            $buildtrunk = $value . "-a";
            $stm = $this->query("SELECT * FROM `" . $buildtrunk . "-hosts` WHERE `hostkey` = ? AND `fullhash` != ''", array($prefix));
            if ($stm->rowCount() > 0) {
                $row = $stm->fetch(\PDO::FETCH_ASSOC);
                return array(
                    $row['fullhash'],
                    $row['chunk_num']
                );
            }

            $stm = $this->query("SELECT p.fullhash, h.chunk_num FROM
                                `" . $buildtrunk . "-prefixes` p
                                JOIN `" . $buildtrunk . "-hosts` h ON (p.hostkey = h.hostkey)
                                WHERE p.`prefix` = ? AND p.`fullhash` != '' AND h.count > 0", array($prefix));
            if ($stm->rowCount() > 0) {
                $row = $stm->fetch(\PDO::FETCH_ASSOC);
                return array(
                    $row['fullhash'],
                    $row['chunk_num']
                );
            }
        }

        return false;
    }

    /**
     * Do a full-hash lookup based on prefixes provided,
     * returns (bool) true on a match and (bool) false on no match.
     */
    private function doFullLookup($prefixes, $originals) {
        // Store copy of original prefixes
        $cloneprefixes = $prefixes;

        // They should really all have the same prefix size.. we'll just check one
        $prefixsize = strlen($prefixes[0][0]) / 2;
        $length = count($prefixes) * $prefixsize;
        foreach ($prefixes as $key => $value) {
            // Check cache on each iteration (we can return true earlier if we get
            // a match!)
            $cachechk = $this->cacheCheck($value[0]);
            if ($cachechk) {
                if (isset($originals[$cachechk[0]])) {
                    //Check from same chunk
                    foreach ($cloneprefixes as $nnewvalue) {
                        if ($nnewvalue[1] == $cachechk[1] && $value[0] == $originals[$cachechk[0]]['prefix']) {
                            //From same chunks
                            return true;
                        }

                    }
                }
            }
            $prefixes[$key] = pack("H*", $value[0]);
        }
        // No cache matches so we continue with request
        $body = $prefixsize . ":" . $length . "\n" . implode("", $prefixes);

        $buildopts = array(
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body
        );

        $url = $this->getServiceUrl('gethash');

        $result = $this->download($url, $buildopts, "lookup");
        if ($result[0]['http_code'] == 200 && !empty($result[1])) {
            // Extract hashes from response
            // Loop over each list
            foreach ($this->processFullLookup($result[1]) as $listname => $chunks) {
                // Loop over each value in each list
                foreach ($chunks as $newkey => $fullhash) {
                    if (isset($originals[$fullhash])) {
                        // Okay it matches a full-hash we have, now to check
                        // they're from the same chunks
                        foreach ($cloneprefixes as $nnewvalue) {
                            if ($nnewvalue[1] == $newkey && $nnewvalue[0] == $originals[$fullhash]['prefix']) {
                                // From same chunks
                                // Add full hash to database (cache)
                                $this->addfullhash($nnewvalue[0], $nnewvalue[1], $fullhash, $listname);
                                return true;
                            }

                        }
                    }
                }
            }
            return false;
        } elseif ($result[0]['http_code'] == 204 && strlen($result[1]) == 0) {
            // 204 Means no match
            return false;
        } else {
            // "No No No! This just doesn't add up at all!"
            $this->fatalerror("ERROR: Invalid response returned from GSB ({$result[0]['http_code']})");
        }
    }

    /**
     * Checks to see if a match for a prefix is found in the sub table, if it is
     * then we won't do a full-hash lookup.
     * Return true on match in sub list, return false on negative.
     */
    private function subCheck($listname, $prefixlist, $mode) {
        $buildtrunk = $listname . '-s';
        foreach ($prefixlist as $value) {
            $stm = $this->query("SELECT id FROM `". $buildtrunk . "-prefixes` WHERE " .
                ($mode == 'prefix' ? '`prefix`' : 'hostkey') . ' = ? AND add_chunk_num = ? LIMIT 1', array($value[0], $value[1]));
            // As interpreted from Developer Guide if theres a match in
            // sub list it cancels out the add listing
            // we'll double check its from the same chunk just to be pedantic
            if ($stm->rowCount() > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * query wrapper
     */
    private function query($sql, $data = array()) {
        $stm = $this->db->prepare($sql);
        $stm->execute($data);
        if ($this->debug) {
            $this->debugLog[] = array($sql, $data, $stm->rowCount());

        }
        return $stm;
    }

    /**
     * create tables
     */
    public function install() {
        foreach ($this->usinglists as $listname) {
            $this->query("CREATE TABLE IF NOT EXISTS `" . $listname . "-a-hosts` (
                          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `hostkey` varchar(8) NOT NULL,
                          `chunk_num` int(11) unsigned NOT NULL,
                          `count` int(11) unsigned NOT NULL DEFAULT '0',
                          `fullhash` char(64) NOT NULL,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `hostkey_2` (`hostkey`,`chunk_num`,`count`,`fullhash`),
                          KEY `hostkey` (`hostkey`)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;");

            $this->query("CREATE TABLE IF NOT EXISTS `" . $listname . "-a-index` (
                          `chunk_num` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `chunk_len` int(11) unsigned NOT NULL DEFAULT '0',
                          PRIMARY KEY (`chunk_num`)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;");

            $this->query("CREATE TABLE IF NOT EXISTS `" . $listname . "-a-prefixes` (
                          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `hostkey` varchar(8) NOT NULL,
                          `prefix` varchar(8) NOT NULL,
                          `fullhash` char(64) NOT NULL,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `hostkey_2` (`hostkey`,`prefix`),
                          KEY `hostkey` (`hostkey`)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;");

            $this->query("CREATE TABLE IF NOT EXISTS `" . $listname . "-s-hosts` (
                          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `hostkey` varchar(8) NOT NULL,
                          `chunk_num` int(11) unsigned NOT NULL,
                          `count` int(11) unsigned NOT NULL DEFAULT '0',
                          `fullhash` char(64) NOT NULL,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `hostkey_2` (`hostkey`,`chunk_num`,`count`,`fullhash`),
                          KEY `hostkey` (`hostkey`)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;");

            $this->query("CREATE TABLE IF NOT EXISTS `" . $listname . "-s-index` (
                          `chunk_num` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `chunk_len` int(11) unsigned NOT NULL DEFAULT '0',
                          PRIMARY KEY (`chunk_num`)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;");

            $this->query("CREATE TABLE IF NOT EXISTS `" . $listname . "-s-prefixes` (
                          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                          `hostkey` varchar(8) NOT NULL,
                          `add_chunk_num` int(11) unsigned NOT NULL,
                          `prefix` varchar(8) NOT NULL,
                          `fullhash` char(64) NOT NULL,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `hostkey_2` (`hostkey`,`add_chunk_num`,`prefix`),
                          KEY `hostkey` (`hostkey`)
                        ) ENGINE=InnoDB  DEFAULT CHARSET=latin1;");
        }
    }

    /**
     * Does a full URL lookup on given lists, will check if its in database, if
     * slight match there then will do a full-hash lookup on GSB,
     * listname on match and (bool) false on negative.
     */
    public function doLookup($url) {
        $lists = $this->usinglists;
        //First canonicalize the URL
        $canurl = self::canonicalizeURL($url);

        //Make hostkeys
        $hostkeys = self::makeHostKey($canurl['Parts']['Host'], $canurl['Parts']['IP']);

        $prefixes = self::makeprefixes($canurl['Parts']['Host'], $canurl['Parts']['Path'], $canurl['Parts']['Query'], $canurl['Parts']['IP']);

        $prefixParams = array();
        $buildprequery = array();
        foreach ($prefixes as $prefix) {
            $buildprequery[] = " `prefix` = ?";
            $prefixParams[] = $prefix['prefix'];
        }
        $buildprequery = implode("OR", $buildprequery);
        if (!empty($buildprequery))  {
            $buildprequery .= ' AND';
        }

        $matches = array();
        foreach ($lists as $key => $listname) {
            $buildtrunk = $listname . '-a';
            $hostsStm = $this->db->prepare('SELECT count, hostkey, chunk_num FROM `' . $buildtrunk . '-hosts` WHERE hostkey = ?');

            //Loop over each list
            foreach ($hostkeys as $keyinner => $valueinner) {

                if ($this->debug) {
                    $this->debugLog[] = array('SELECT count, hostkey, chunk_num FROM `' . $buildtrunk . '-hosts` WHERE hostkey = ?', array($valueinner['prefix']), $hostsStm->rowCount());
                }
                // Within each list loop over each hostkey
                $hostsStm->execute(array($valueinner['prefix']));

                // For each hostkey match
                while ($row = $hostsStm->fetch(\PDO::FETCH_ASSOC)) {
                    if ($row['count'] > 0) {

                        // There was a match and the count is more than one so
                        // there are prefixes!
                        // Hash up a load of prefixes and create the build
                        // query if we haven't done so already
                        $params = $prefixParams;
                        $params[] = $row['hostkey'];

                        if ($this->debug) {
                            $this->debugLog[] = array("SELECT  FROM `" . $buildtrunk . "-prefixes` WHERE  " . $buildprequery . " `hostkey` = ?", $param);
                        }

                        // Check if there are any matching prefixes
                        $stm = $this->query("SELECT prefix FROM `" . $buildtrunk . "-prefixes` WHERE  " . $buildprequery . " `hostkey` = ?", $params);
                        if ($stm->rowCount() > 0) {
                            // We found prefix matches
                            $prematches = array();
                            $prelookup = array();
                            while ($rowPrefix = $stm->fetch(\PDO::FETCH_ASSOC)) {
                                $prematches[] = array(
                                    $rowPrefix['prefix'],
                                    $row['chunk_num']
                                );
                            }

                            // Before we send off any requests first check
                            // whether its in sub table
                            if (!$this->subCheck($listname, $prematches, "prefix") &&
                                 $this->doFullLookup($prematches, $prefixes)) {
                                return $listname;
                            }
                        }

                        // If we didn't find matches then do nothing (keep
                        // looping till end and it'll return negative)
                    } elseif (!$this->subCheck($listname, array(array($row['hostkey'], $row['chunk_num'])), "hostkey") &&
                             $this->doFullLookup(array(array($row['hostkey'], $row['chunk_num'])), $hostkeys)) {
                        return $listname;
                    }
                }
            }
        }
        return false;
    }
}
