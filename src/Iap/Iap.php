<?php

namespace Pheicloud\Aruba\Iap;

use Illuminate\Support\Facades\Config;

class Iap
{
    private $addr;
    private $username;
    private $password;

    private $sid = false;

    public function __construct()
    {
        $this->addr = Config::get('aruba.iap.addr');
        $this->username = Config::get('aruba.iap.username');
        $this->password = Config::get('aruba.iap.password');
    }

    public function __destruct()
    {
        $this->logout();
    }

    public function login()
    {
        $url = 'https://' . $this->addr . '/swarm.cgi';
        $params = [];
        $params['opcode'] = 'login';
        $params['user'] = $this->username;
        $params['passwd'] = $this->password;
        $params['refresh'] = 'false';

        $r = $this->http($url, $params, false, ['Expect: ']);
        if (!isset($r['body']))
            return false;
        $i = preg_match('/<data name="sid" pn="true">(.+?)<\/data>/i', $r['body'], $match);
        if ($i != 1)
            return false;
        $this->sid = $match[1];
        return true;
    }

    public function logout()
    {
        if ($this->sid) {
            $url = 'https://' . $this->addr . '/swarm.cgi';
            $params = [];
            $params['opcode'] = 'logout';
            $params['refresh'] = 'false';
            $params['sid'] = $this->sid;
            $this->http($url, $params);
            $this->sid = false;
        }
    }

    public function queryProfile()
    {
        $url = 'https://' . $this->addr . '/swarm.cgi?opcode=show&ip=127.0.0.1&cmd=%27show%20network%27&refresh=false&sid=' . $this->sid;
        $r = $this->http($url);
        $body = html_entity_decode($r['body']);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        $data = [];

        if (is_null($xml->t->r)) {
            return [];
        }

        foreach ($xml->t->r as $v) {
            $d = [];
            $d['profile'] = trim((string)$v->c[0], '"');
            $d['ssid'] = trim((string)$v->c[1], '"');
            $d['type'] = (string)$v->c[3];
            $d['status'] = (string)$v->c[8];
            $d['active'] = (string)$v->c[11];
            $data[] = $d;
        }
        return $data;
    }

    /**
     * 获取IAP客户端列表
     *
     * @param  void
     * @return array $data
     */
    public function clientList()
    {
        $rand = '0.' . rand(1000000000000000, 9999999999999999);
        $url = 'https://' . $this->addr . '/swarm.cgi?opcode=show&ip=127.0.0.1&cmd=%27show%20summary%27&refresh=false&sid=' . $this->sid . '&nocache=' . $rand;
        $r = $this->http($url);
        $body = html_entity_decode($r['body']);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $data = [];

        if (is_null($xml->t->r)) {
            return [];
        }

        foreach ($xml->t->r as $v) {
            $d = [];
            $d['mac'] = trim((string)$v->c[0], '"');
            $d['client'] = trim((string)$v->c[1], '"');
            $d['ip'] = (string)$v->c[2];
            $d['ssid'] = (string)$v->c[3];
            $d['apmac'] = (string)$v->c[4];
            $data[] = $d;
        }
        return $data;
    }

    /**
     * 获取IAP下的AP列表
     *
     * @param  void
     * @return array $data
     */
    public function apList()
    {
        $rand = '0.' . rand(1000000000000000, 9999999999999999);
        $url = 'https://' . $this->addr . '/swarm.cgi?opcode=show&ip=127.0.0.1&cmd=%27show%20summary%27&refresh=false&sid=' . $this->sid . '&nocache=' . $rand;
        $r = $this->http($url);
        $body = html_entity_decode($r['body']);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $data = [];

        if (is_null($xml->t->r)) {
            return [];
        }

        $d = [];
        foreach ($xml->t[2]->r as $v) {
            $d[] = (string)$v->c[0];
        }
        return $d;
    }

    /**
     * 获取IAP下的AP IP
     *
     * @param  void
     * @return array $data
     */
    public function apIp()
    {
        $rand = '0.' . rand(1000000000000000, 9999999999999999);
        $url = 'https://' . $this->addr . '/swarm.cgi?opcode=show&ip=127.0.0.1&cmd=%27show%20summary%27&refresh=false&sid=' . $this->sid . '&nocache=' . $rand;
        $r = $this->http($url);
        $body = html_entity_decode($r['body']);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $data = [];

        if (is_null($xml->t->r)) {
            return [];
        }

        $d = [];
        foreach ($xml->t[2]->r as $v) {
            $d[(string)$v->c[0]] = (string)$v->c[1];
        }
        return $d;
    }

    public function setSSID($profile, $ssid)
    {
        $url = 'https://' . $this->addr . '/swarm.cgi';
        $params = [];
        $params['opcode'] = 'config';
        $params['ip'] = '127.0.0.1';
        $params['cmd'] = "'wlan ssid-profile \"$profile\"\nessid \"$ssid\"\n";
        $params['refresh'] = 'false';
        $params['sid'] = $this->sid;

        $r = $this->http($url, $params);
	
        if (!isset($r['body']))
            return false;
        return strpos($r['body'], '<re></re>') !== false;
    }

    public function showStats($ip)
    {
        $rand = '0.' . rand(1000000000000000, 9999999999999999);
        $url = 'https://' . $this->addr . '/swarm.cgi?opcode=show&ip=127.0.0.1&cmd=%27show%20stats%20global%2043%27&refresh=true&sid=' . $this->sid . '&nocache=' . $rand;
        $r = $this->http($url);
        $body = html_entity_decode($r['body']);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $data = [];
        $index = 0;

        if (is_null($xml->t)) {
            return [];
        }
        foreach ($xml->t as $v) {

            if ($index == 0) {
                $data[$index] = [];
                foreach ($v->r as $vv) {
                    $time = $vv->c[0]->__toString();
                    $num = $vv->c[1]->__toString();
                    $frameOut = $vv->c[2]->__toString();
                    $frameIn = $vv->c[3]->__toString();
                    $ThroughputOut = $vv->c[4]->__toString();
                    $ThroughputIn = $vv->c[5]->__toString();
                    array_push($data[$index], ['ip' => $ip, 'time' => $time, 'num' => $num, 'frameOut' => $frameOut, 'frameIn' => $frameIn, 'ThroughOut' => $ThroughputOut, 'ThroughIn' => $ThroughputIn]);
                }
            } else if ($index == 1) {
                $data[$index] = [];
                foreach ($v->r as $vv) {
                    $mac = $vv->c[0]->__toString();
                    $utilization = $vv->c[1]->__toString();
                    $noise = $vv->c[2]->__toString();
                    $errors = $vv->c[3]->__toString();
                    array_push($data[$index], ['ip' => $ip, 'mac' => $mac, 'utilization' => $utilization, 'noise' => $noise, 'errors' => $errors]);
                }

            } else if ($index == 2) {
                $data[$index] = [];
                foreach ($v->r as $vv) {
                    $clients = $vv->c[0]->__toString();
                    $signal = $vv->c[1]->__toString();
                    $speed = $vv->c[2]->__toString();
                    $ip = $vv->c[3]->__toString();
                    array_push($data[$index], ['ip' => $ip, 'client' => $clients, 'signal' => $signal, 'speed' => $speed, 'ip' => $ip]);
                }
            }

            $index++;
        }


        return $data;

        /*
                $array = [];
                foreach ($xml->t as $v) {
                    $th_index = 0;
                    foreach ($v->th->h as $vv) {
                        $i = $vv->__toString();
                        $value = $v->r->c;
                        $data[$index][$i] = $value[$th_index]->__toString();
                        $th_index++;
                    }

                    $array[] = $data;
                    $index++;
                }
        */

    }

    /**
     * 导出配置
     *
     * @param  void
     * @return array $data
     */
    public function export()
    {
        $rand = '0.' . rand(1000000000000000, 9999999999999999);
        $url = 'https://' . $this->addr . '/swarm.cgi?opcode=config-backup&ip=127.0.0.1&refresh=false&sid=' . $this->sid . '&nocache=' . $rand;
        $r = $this->http($url);
        $body = html_entity_decode($r['body']);
        return $body;
    }


    /**
     * 导入配置
     *
     * @param  void
     * @return array $data
     */
    public function import($filepath)
    {
        $url = 'https://' . $this->addr . '/swarm.cgi';
        $params = [];
        $params['upload_id'] = 'E75358C5-496F-4081-A02E-B7468FF507D9';
        $params['sid'] = $this->sid;
        $params['opcode'] = 'config-restore';
        $params['config'] = new \CURLFile($filepath);
        $r = $this->http_($url, $params, true, false, ['Expect: ']);
        //TODO:Test api
        //$r = $params;
        //return $r;
        if (!$r) return false;
        return $r['body'];
    }

    static public function http($url, $form = false, $cookie = false, $header = false, $refer = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYSTATUS, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true); // Required for HTTP error codes to be reported via our call to curl_error($ch)
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Timeout for response from server
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Timeout for data from server

        if ($form) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
        }

        if ($cookie)
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);

        if ($refer)
            curl_setopt($ch, CURLOPT_REFERER, $refer);

        if ($header)
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $r = curl_exec($ch);

        if (curl_error($ch)) {
            $error_msg = curl_error($ch);
        }

        curl_close($ch);

        if (isset($error_msg)) {
            // TODO - Handle cURL error accordingly
            return false;
        }

        $data = explode("\r\n\r\n", $r, 2);
        $r = [];
        $r['body'] = $data[1];
        $r['header'] = $data[0];
        $cookies = '';
        $i = preg_match_all('/set-cookie:(.+?);/i', $r['header'], $matches);
        foreach ($matches[1] as $cookie) {
            $cookies .= trim($cookie) . '; ';
        }
        $r['cookie'] = $cookies;
        return $r;
    }

    static public function http_($url, $form = false, $isbinary = false, $cookie = false, $header = false, $refer = false)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HEADER, true);

        if ($form) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (!$isbinary)
                $form = http_build_query($form);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
        }

        if ($cookie)
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);

        if ($refer)
            curl_setopt($ch, CURLOPT_REFERER, $refer);

        if ($header)
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        $r = curl_exec($ch);

        curl_close($ch);
        $data = explode("\r\n\r\n", $r, 2);
        if (count($data) != 2)
            return false;
        $r = [];
        $r['body'] = $data[1];
        $r['header'] = $data[0];
        $cookies = '';
        $i = preg_match_all('/set-cookie:(.+?);/i', $r['header'], $matches);
        foreach ($matches[1] as $cookie) {
            $cookies .= trim($cookie) . '; ';
        }
        $r['cookie'] = $cookies;
        return $r;
    }

}
