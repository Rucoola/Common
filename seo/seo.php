<?php
    // Генератор
    function generator($string = null, $generator = array(), $seed = null, $recursive = false) {
        if($recursive === false) {
            if(!$string) return null;
            $string = "[{$string}]";
            if(!isset($generator['callstack'])) $generator['callstack'] = array();
            return generator($string, $generator, $seed, true);
        }
        if(!$string) return '';
        // @
        $check = $string;
        $string = preg_replace_callback('/@([a-z0-9_]+)/', function($m) use($generator) {
            if(!isset($generator[$m[1]])) return '';
            $generator = nsplit($generator[$m[1]]);
            $generator = '[' . implode('|', $generator) . ']';
            return $generator;
        }, $string);
        if($check === $string) ; else return generator($string, $generator, $seed, true);
        // @@
        $check = $string;
        $string = preg_replace_callback('/@@"([^"]+?)"/', function($m) {
            if(!is_file(LIST_ROOT . '/' . $m[1])) return '';
            return '[' . implode('|', nsplit(file_get_contents(LIST_ROOT . '/' . $m[1]))) . ']';
        }, $string);
        if($check === $string) ; else return generator($string, $generator, $seed, true);
        //
        $depth = 0;
        $alt = array();
        $collector = '';
        for($i = 0; $i < strlen($string); $i++) {
            $char = $string[$i];
            if($char === '[') $depth += 1;
            if($char === ']') $depth -= 1;
            if($char === '|' and $depth === 0) {
                $alt[] = $collector;
                $collector = '';
            } else $collector .= $char;
        }
        $alt[] = $collector;
        $collector = '';
        $alt = array_map(function($elem) {
            $match = array();
            if(preg_match('|(.*)\(([a-z0-9_,]+)\)$|', $elem, $match)) {
                $paren = explode(',', $match[2]);
                $array = array(array(), array());
                foreach($paren as $elem)
                    if(is_numeric($elem)) $array[0][] = $elem;
                    else $array[1][] = $elem;
                if(isset($array[0][0]))
                    return array($match[1], $array[0][0], $array[1]);
                return array($match[1], 1, $array[1]);
            }
            return array($elem, 1, array());
        }, $alt);
        reset($alt);
        $choser = array();
        while(list(, list($elem, $count, $call)) = each($alt))
            for($i = 0; $i < $count; $i++)
                $choser[] = array($elem, $call);
        if(!count($choser)) return '';
        if($seed === null) $SEED = rand_from_string(microtime(true));
        else $SEED = rand_from_string($seed);
        list($elem, $call) = $choser[$SEED % count($choser)];
        unset($choser);
        //
        $depth = 0;
        $alt = array();
        $collector = '';
        for($i = 0; $i < strlen($elem); $i++) {
            $char = $elem[$i];
            if($char === '[') {
                if($depth === 0) {
                    $alt[] = $collector;
                    $collector = '';
                } else $collector .= $char;
                $depth += 1;
            } elseif($char === ']') {
                if($depth === 1) {
                    $alt[] = $collector;
                    $collector = '';
                } else $collector .= $char;
                $depth -= 1;
            } else $collector .= $char;
        }
        $alt[] = $collector;
        $collector = '';
        if($depth) {
            trigger_error("ERROR - {$string}", E_USER_NOTICE);
            return '';
        }
        foreach(array_keys($alt) as $key)
            if(
                strpos($alt[$key], ']') === false and
                strpos($alt[$key], '[') === false and
                strpos($alt[$key], '|') === false and
                !preg_match('|\(([a-z0-9_,]+)\)$|', $alt[$key])
            ) ; else $alt[$key] = generator($alt[$key], $generator, $seed, true);
        $elem = implode('', $alt);
        foreach($call as $c)
            if(is_callable($c))
                $elem = call_user_func($c, $elem);
            elseif(isset($generator['callstack'][$c]) and is_callable($generator['callstack'][$c]))
                $elem = call_user_func($generator['callstack'][$c], $elem);
        return $elem;
    }
    // Закачать файл с помощью wget
    function load_file($url, $dir, $proxy = false) {
        $ext = file_get_ext($url);
        $name = file_get_name($url);
        if(strlen($ext) > 4 or !$name) {
            trigger_error("INVALID EXT OR NAME - {$url}", E_USER_WARNING);
            return null;
        }
        $img = wget($url, $proxy);
        if(!$img) {
            trigger_error("WARNING - {$url} was not loaded!", E_USER_WARNING);
            return null;
        }
        $name = "{$name}.{$ext}";
        $dir = rtrim($dir, '/');
        if(!is_dir($dir)) exec("mkdir -p '{$dir}'");
        file_put_contents("{$dir}/{$name}", $img);
        return $name;
    }
    // Получить страницу через wget
    function wget($url, $proxy = false, $start = 2) {
        while($start--):
            $ua = getUserAgent();
            $host = host($url);
            if(!$host) {
                trigger_error("INVALID URL", E_USER_NOTICE);
                return null;
            }
            $referer = 'http://' . $host . '/';
            $timeout = 20;
            $tries = 5;
            $rand = rand_from_string(microtime(true));
            $LOG = "wget_file.{$rand}.txt";
            exec("> \"{$LOG}\"");
            $H1 = 'Accept-Language: en-us,en;q=0.5';
            $H2 = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
            $H3 = 'Connection: keep-alive';
            $wget = "--tries={$tries} --header=\"{$H1}\" --header=\"{$H2}\" --header=\"{$H3}\" --no-http-keep-alive --timeout={$timeout} -o \"{$LOG}\" -U \"{$ua}\" --referer=\"{$referer}\"";
            $temp = $proxy;
            if($proxy) {
                $proxy = parse_url(getProxy());
                if($proxy['scheme'] === 'proxy' and $proxy['host'] and $proxy['port']) {
                    $user = isset($proxy['user']) ? "--proxy-user=\"{$proxy['user']}\"" : '';
                    $pass = isset($proxy['pass']) ? "--proxy-password=\"{$proxy['pass']}\"" : '';
                    $proxy = "{$proxy['host']}:{$proxy['port']}";
                    $wget = "http_proxy={$proxy} wget {$user} {$pass} {$wget} -O - \"{$url}\"";
                } else return null;
            } else $wget = "wget {$wget} -O - \"{$url}\"";
            $proxy = $temp;
            trigger_error($wget, E_USER_NOTICE);
            $return = shell_exec($wget);
            trigger_error(file_get_contents($LOG), E_USER_NOTICE);
            unlink($LOG);
            if($return) return $return;
        endwhile;
        return null;
    }
    // awmproxy.com getter
    function getProxy() {
        $PROXY_LIST = LIST_ROOT . '/proxy.list';
        $PROXY_LIST_URL = 'http://awmproxy.com/701proxy.txt';
        $PROXY_LIST_TIMEOUT = 3600;
        $PROXY_LOGIN = 'login';
        $PROXY_PASSWD = 'passwd';
        if(filemtime($PROXY_LIST) < time() - $PROXY_LIST_TIMEOUT) {
            trigger_error('UPDATE', E_USER_NOTICE);
            $list = wget($PROXY_LIST_URL);
            if(!$list) trigger_error('INVALID LIST', E_USER_ERROR);
            $list = nsplit($list);
            $list = implode(chr(10), array_map(function($line) use($PROXY_LOGIN, $PROXY_PASSWD) {
                return "proxy://{$PROXY_LOGIN}:{$PROXY_PASSWD}@{line}";
            }, $list));
            file_put_contents($PROXY_LIST, $list);
        }
        $list = file_get_contents($PROXY_LIST);
        $list = nsplit($list);
        $proxy = $list[mt_rand(0, count($list) - 1)];
        if($proxy) return $proxy;
        trigger_error('INVALID PROXY', E_USER_ERROR);
    }
    // ua getter
    function getUserAgent($seed = null) {
        if($seed === null) $seed = rand_from_string(microtime(true));
        else $seed = rand_from_string($seed);
        $UA_LIST = LIST_ROOT . '/ua.list';
        $list = file_get_contents($UA_LIST);
        $list = nsplit($list);
        $ua = $list[$seed % count($list)];
        if($ua) return $ua;
        trigger_error('INVALID UA', E_USER_ERROR);
    }
