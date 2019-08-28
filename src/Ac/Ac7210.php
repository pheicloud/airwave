<?php

namespace Pheicloud\Aruba\Ac;

use Illuminate\Support\Facades\Config;

class Ac7210
{
    private $addr;
    private $username;
    private $password;

    private $cookie = false;
    private $sid;

    public function __construct()
    {
        $this->addr = Config::get('aruba.ac_7210.addr');
        $this->username = Config::get('aruba.ac_7210.username');
        $this->password = Config::get('aruba.ac_7210.password');
    }

    public function __destruct()
    {
        $this->logout();
    }

    public function login()
    {
        $url = 'https://' . $this->addr . '/screens/wms/wms.login';
        $params = [];
        $params['opcode'] = 'login';
        $params['url'] = '/';
        $params['needxml'] = '0';
        $params['uid'] = $this->username;
        $params['passwd'] = $this->password;

        $r = $this->http($url, $params, false, ['Expect: ']);
        if (!isset($r['cookie']))
            return false;

        $cookie = $r['cookie'];
        $i = preg_match('/SESSION=(.+?);/i', $cookie, $match);
        if ($i != 1)
            return false;

        $this->cookie = $cookie;
        $this->sid = $match[1];
        return true;
    }

    public function logout()
    {
        if ($this->cookie) {
            $url = 'https://' . $this->addr . '/logout.html';
            $this->http($url, false, $this->cookie);
            $this->cookie = false;
        }
    }

    public function queryVPN()
    {
        $url = 'https://' . $this->addr . '/screens/cmnutil/execCommandReturnResult.xml?show%20ipv4%20user-table%20authentication-method%20%22vpn%22%20rows%201%201000000&UIDARUBA=' . $this->sid;
        $r = $this->http($url, false, $this->cookie);
        $body = html_entity_decode($r['body']);
        $pattern = '/<r><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><c>(.*?)<\/c><\/r>/i';
        preg_match_all($pattern, $body, $matches);
        $data = [];
        foreach ($matches[0] as $k => $v) {
            $ip = $matches[1][$k];
            $apmac = $matches[3][$k];
            $role = $matches[4][$k];
            if (empty(trim($apmac))) {
                continue;
            }
            $d = [];
            $d['ip'] = $ip;
            $d['apmac'] = $apmac;
            $data[] = $d;
        }
        return $data;
    }

    public function apList()
    {
        $pages = $this->apPage();
        $ipMacs = [];

        for ($i = 1; $i <= $pages; $i++) {
            $url = 'https://' . $this->addr . '/screens/cmnutil/execCommandReturnResult.xml?show%20whitelist-db%20rap%20%20page%20' . $i . '@@1542958183684&UIDARUBA=' . $this->sid;
            $r = $this->http($url, false, $this->cookie);
            $body = html_entity_decode($r['body']);
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
            foreach ($xml->t->r as $v) {
                $ip = (string)$v->c[10];
                $mac = (string)$v->c[0];
                $ipMac = [
                    'ip' => $ip,
                    'mac' => $mac
                ];

                array_push($ipMacs, $ipMac);
            }

        }

        return $ipMacs;
    }

    public function apPage()
    {
        $url = 'https://' . $this->addr . '/screens/cmnutil/execCommandReturnResult.xml?show%20whitelist-db%20rap-status@@1548042104623&UIDARUBA=' . $this->sid;
        $r = $this->http($url, false, $this->cookie);
        $body = html_entity_decode($r['body']);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $str = $xml->data[1]->__toString();
        $val = explode('Total entries:                ', $str)[1];
        return ceil($val / 50);
    }

    /*
     * 流氓AP客户端
          0 => "mon_sta_mac_address"
          1 => "mon_radio_band"
          2 => "mon_sta_phy_type"
          3 => "mon_bssid"
          4 => "mon_ssid"
          5 => "mon_sta_classification"
          6 => "mon_ap_classification"
          7 => "wms_event_count"
          8 => "mon_ap_current_channel"
          9 => "mon_sta"
     */
    public function rogueApClients()
    {
        $url = 'https://' . $this->addr . '/screens/cmnutil/execUiQuery.xml';
        $form = 'query=<aruba_queries xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="/screens/monxml/monitoring_schema.xsd"><query><qname>comp_mwips_clients_info</qname><type>list</type><list_query><device_type>mon_sta</device_type><requested_columns>mon_sta_mac_address mon_radio_band mon_sta_phy_type mon_bssid mon_ssid mon_sta_classification mon_ap_classification wms_event_count mon_ap_current_channel mon_sta</requested_columns><sort_by_field>mon_sta_mac_address</sort_by_field><sort_order>asc</sort_order><pagination><key_value></key_value><start_row>0</start_row><num_rows>50</num_rows></pagination></list_query><filter><global_operator>and</global_operator><filter_list><filter_item_entry><field_name>mon_ap_classification</field_name><comp_operator>equals</comp_operator><value><![CDATA[0]]></value></filter_item_entry><filter_item_entry><related_device>mon_bssid</related_device><field_name>mon_ap_status</field_name><comp_operator>equals</comp_operator><value><![CDATA[1]]></value></filter_item_entry><filter_item_entry><field_name>mon_sta_is_ap</field_name><comp_operator>equals</comp_operator><value><![CDATA[0]]></value></filter_item_entry><filter_item_entry><field_name>mon_sta_status</field_name><comp_operator>equals</comp_operator><value><![CDATA[2]]></value></filter_item_entry></filter_list></filter></query></aruba_queries>&UIDARUBA=' . $this->sid;
        $r = $this->http($url, $form, $this->cookie);
        $body = html_entity_decode($r['body']);
        $body = explode('<?xml version="1.0" encoding="UTF-8"?>', $body)[1];
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $datas = [];

        if (is_null($xml->response->list_response->rows->row)) {
            return [];
        }

        foreach ($xml->response->list_response->rows->row as $row) {
            $datas[] = [
                'mac' => $row->value[0]->__toString(),
                'radio' => $row->value[1]->__toString(),
                'type' => $row->value[2]->__toString(),
                'bssid' => $row->value[3]->__toString(),
                'ssid' => $row->value[4]->__toString(),
                'sta_classification' => $row->value[5]->__toString(),
                'ap_classification' => $row->value[6]->__toString(),
                'event_count' => $row->value[7]->__toString(),
                'ap_current_channel' => $row->value[8]->__toString(),
                'sta' => $row->value[9]->__toString(),
            ];
        }
        return $datas;
    }


    /*
     * 流氓AP
          0 => "mon_bssid"
          1 => "mon_radio_band"
          2 => "mon_radio_phy_type"
          3 => "mon_ssid"
          4 => "mon_ap_current_channel"
          5 => "mon_sta_count"
          6 => "mon_ap_classification"
          7 => "mon_ap_encr"
          8 => "mon_ap_is_dos"
          9 => "mon_ap"
     *
     */
    public function rogueAp()
    {

        $url = 'https://' . $this->addr . '/screens/cmnutil/execUiQuery.xml';
        $form = 'query=<aruba_queries xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="/screens/monxml/monitoring_schema.xsd"><query><qname>comp_mwips_aps_info</qname><type>list</type><list_query><device_type>mon_bssid</device_type><requested_columns>mon_bssid mon_radio_band mon_radio_phy_type mon_ssid mon_ap_current_channel mon_sta_count mon_ap_classification mon_ap_encr mon_ap_is_dos mon_ap</requested_columns><sort_by_field>mon_bssid</sort_by_field><sort_order>asc</sort_order><pagination><key_value></key_value><start_row>0</start_row><num_rows>50</num_rows></pagination></list_query><filter><global_operator>and</global_operator><filter_list><filter_item_entry><field_name>mon_ap_classification</field_name><comp_operator>equals</comp_operator><value><![CDATA[0]]></value></filter_item_entry><filter_item_entry><field_name>mon_ap_status</field_name><comp_operator>equals</comp_operator><value><![CDATA[1]]></value></filter_item_entry></filter_list></filter></query></aruba_queries>&UIDARUBA=' . $this->sid;
        $r = $this->http($url, $form, $this->cookie);
        $body = html_entity_decode($r['body']);
        $body = explode('<?xml version="1.0" encoding="UTF-8"?>', $body)[1];
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $datas = [];
        foreach ($xml->response->list_response->rows->row as $row) {
            $datas[] = [
                'bssid' => $row->value[0]->__toString(),
                'radio_band' => $row->value[1]->__toString(),
                'radio_phy_type' => $row->value[2]->__toString(),
                'ssid' => $row->value[3]->__toString(),
                'ap_current_channel' => $row->value[4]->__toString(),
                'sta_count' => $row->value[5]->__toString(),
                'ap_classification' => $row->value[6]->__toString(),
                'ap_encr' => $row->value[7]->__toString(),
                'ap_is_dos' => $row->value[8]->__toString(),
                'ap' => is_null($row->value[9]) ? null : $row->value[9]->__toString(),
            ];
        }
        return $datas;
    }

    /*
     * useage type count
            <column_name>client_dev_type</column_name>
            <column_name>client_ip_address</column_name>
     */
    public function usageSummaryClientDevType()
    {

        $url = 'https://' . $this->addr . '/screens/cmnutil/execUiQuery.xml';
        $form = 'query=<aruba_queries xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="/screens/monxml/monitoring_schema.xsd"><query><qname>comp_usage_fw_summary_client_dev_type</qname><type>list</type><list_query><device_type>sta</device_type><requested_columns>client_dev_type client_ip_address</requested_columns><sort_by_field>client_ip_address</sort_by_field><sort_order>asc</sort_order><group_by_aggregates>none count</group_by_aggregates><pagination><key_value></key_value><start_row>0</start_row><num_rows>10</num_rows></pagination><filtered_rows_aggregates>none sum</filtered_rows_aggregates></list_query><group_by>client_dev_type</group_by></query></aruba_queries>&UIDARUBA=' . $this->sid;
        $r = $this->http($url, $form, $this->cookie);
        $body = html_entity_decode($r['body']);
        $body = explode('<?xml version="1.0" encoding="UTF-8"?>', $body)[1];
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $datas = [];
        foreach ($xml->response->list_response->rows->row as $row) {
            $datas[] = [
                'type' => is_null($row->value[0]) ? null : $row->value[0]->__toString(),
                'count' => $row->value[1]->__toString(),
            ];
        }
        return $datas;
    }


    /*
     * app by useage
        <column_name>app_id</column_name>
        <column_name>app_display_name</column_name>
        <column_name>fw_total_bytes</column_name>
     */
    public function usageSummaryApplication()
    {

        $url = 'https://' . $this->addr . '/screens/cmnutil/execUiQuery.xml';
        $form = 'query=<aruba_queries xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="/screens/monxml/monitoring_schema.xsd"><query><qname>comp_usage_fw_summary_fw_application</qname><type>list</type><list_query><device_type>fw_visibility_rec</device_type><requested_columns>app_id app_display_name fw_total_bytes</requested_columns><sort_by_field>fw_total_bytes</sort_by_field><sort_order>desc</sort_order><group_by_aggregates>none mapped sum</group_by_aggregates><pagination><key_value></key_value><start_row>0</start_row><num_rows>25</num_rows></pagination><filtered_rows_aggregates>none none sum</filtered_rows_aggregates></list_query><group_by>app_id</group_by></query></aruba_queries>&UIDARUBA=' . $this->sid;
        $r = $this->http($url, $form, $this->cookie);
        $body = html_entity_decode($r['body']);
        $body = explode('<?xml version="1.0" encoding="UTF-8"?>', $body)[1];
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $datas = [];
        foreach ($xml->response->list_response->rows->row as $row) {
            $datas[] = [
                'appid' => is_null($row->value[0]) ? null : $row->value[0]->__toString(),
                'name' => is_null($row->value[1]) ? null : $row->value[1]->__toString(),
                'count' => is_null($row->value[2]) ? null : $row->value[2]->__toString(),
            ];
        }
        return $datas;
    }

    /*
     * performance clients
     */
    public function performanceClients()
    {
        $url = 'https://' . $this->addr . '/screens/cmnutil/execUiQuery.xml';
        $form = 'query=<aruba_queries xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="/screens/monxml/monitoring_schema.xsd"><query><qname>sta_count</qname><type>list</type><list_query><device_type>network</device_type><requested_columns>sta_count</requested_columns></list_query></query><query><qname>sta_count_2_4_ghz</qname><type>list</type><list_query><device_type>network</device_type><requested_columns>sta_count_2_4_ghz</requested_columns></list_query></query><query><qname>comp_radio_phy_type_2_4_ghz</qname><type>distribution</type><distribution_query><device_type>sta</device_type><dist_by_field>client_phy_type</dist_by_field></distribution_query><filter><global_operator>and</global_operator><filter_list><filter_item_entry><field_name>radio_band</field_name><comp_operator>equals</comp_operator><value><![CDATA[0]]></value></filter_item_entry></filter_list></filter></query><query><qname>sta_count_5_ghz</qname><type>list</type><list_query><device_type>network</device_type><requested_columns>sta_count_5_ghz</requested_columns></list_query></query><query><qname>comp_radio_phy_type_5_ghz</qname><type>distribution</type><distribution_query><device_type>sta</device_type><dist_by_field>client_phy_type</dist_by_field></distribution_query><filter><global_operator>and</global_operator><filter_list><filter_item_entry><field_name>radio_band</field_name><comp_operator>equals</comp_operator><value><![CDATA[1]]></value></filter_item_entry></filter_list></filter></query><query><qname>comp_client_health</qname><type>histogram</type><histogram_query><device_type>sta</device_type><field>client_health</field><bucket_spec><bucket_type>equal_width_buckets</bucket_type><equal_width_buckets><start_range>0</start_range><increments>10</increments><num_buckets>10</num_buckets></equal_width_buckets></bucket_spec></histogram_query></query><query><qname>comp_s_n_r</qname><type>histogram</type><histogram_query><device_type>sta</device_type><field>snr</field><bucket_spec><bucket_type>equal_width_buckets</bucket_type><equal_width_buckets><start_range>10</start_range><increments>5</increments><num_buckets>10</num_buckets></equal_width_buckets></bucket_spec></histogram_query></query><query><qname>comp_speed</qname><type>histogram</type><histogram_query><device_type>sta</device_type><field>speed</field><bucket_spec><bucket_type>variable_width_buckets</bucket_type><variable_width_buckets><buckets>0 12000000 54000000 108000000 300000000 450000000 1300000000 1700000000</buckets></variable_width_buckets></bucket_spec></histogram_query></query><query><qname>comp_avg_data_rate_clients</qname><type>histogram</type><histogram_query><device_type>sta</device_type><field>avg_data_rate</field><bucket_spec><bucket_type>variable_width_buckets</bucket_type><variable_width_buckets><buckets>0 12000000 54000000 108000000 300000000 450000000 1300000000 1700000000</buckets></variable_width_buckets></bucket_spec></histogram_query></query></aruba_queries>&UIDARUBA=' . $this->sid;
        $r = $this->http($url, $form, $this->cookie);
        $body = html_entity_decode($r['body']);
        $body = explode('<?xml version="1.0" encoding="UTF-8"?>', $body)[1];
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $count = $xml->response[0]->list_response->rows->row->value->__toString();
        $count_2_4 = $xml->response[1]->list_response->rows->row->value->__toString();
        $count_5 = $xml->response[3]->list_response->rows->row->value->__toString();
        $client_health = $xml->response[5]->histogram_response->values->value;
        $snr = $xml->response[6]->histogram_response->values->value;
        $speed = $xml->response[7]->histogram_response->values->value;
        $goodput = $xml->response[8]->histogram_response->values->value;

        $datas = [
            'count' => $count,
            'count_2_4' => $count_2_4,
            'count_5' => $count_5,
            'client_health' => $client_health,
            'snr' => $snr,
            'speed' => $speed,
            'goodput' => $goodput,
        ];

        return $datas;
    }

    /*
     * performance aps
     */
    public function performanceAps()
    {
        $url = 'https://' . $this->addr . '/screens/cmnutil/execUiQuery.xml';
        $form = 'query=<aruba_queries xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="/screens/monxml/monitoring_schema.xsd"><query><qname>comp_overall_traffic_usage</qname><type>list</type><list_query><device_type>network</device_type><requested_columns>avg_data_rate</requested_columns></list_query></query><query><qname>overall_channel_usage</qname><type>list</type><list_query><device_type>network</device_type><requested_columns>tx_avg_data_rate rx_avg_data_rate tx_data_dropped  tx_data_type_dist rx_data_type_dist tx_data_frame_rate_dist rx_data_frame_rate_dist</requested_columns></list_query></query><query><qname>comp_channel_quality</qname><type>histogram</type><histogram_query><device_type>radio</device_type><field>arm_ch_qual</field><bucket_spec><bucket_type>equal_width_buckets</bucket_type><equal_width_buckets><start_range>0</start_range><increments>10</increments><num_buckets>10</num_buckets></equal_width_buckets></bucket_spec></histogram_query><filter><global_operator>and</global_operator><filter_list><filter_item_entry><field_name>radio_mode</field_name><comp_operator>equals</comp_operator><value><![CDATA[0]]></value></filter_item_entry></filter_list></filter></query><query><qname>comp_noise_floor</qname><type>histogram</type><histogram_query><device_type>radio</device_type><field>noise_floor</field><bucket_spec><bucket_type>equal_width_buckets</bucket_type><equal_width_buckets><start_range>-110</start_range><increments>5</increments><num_buckets>9</num_buckets></equal_width_buckets></bucket_spec></histogram_query><filter><global_operator>and</global_operator><filter_list><filter_item_entry><field_name>radio_mode</field_name><comp_operator>equals</comp_operator><value><![CDATA[0]]></value></filter_item_entry></filter_list></filter></query><query><qname>comp_channel_busy</qname><type>histogram</type><histogram_query><device_type>radio</device_type><field>channel_busy</field><bucket_spec><bucket_type>equal_width_buckets</bucket_type><equal_width_buckets><start_range>0</start_range><increments>10</increments><num_buckets>10</num_buckets></equal_width_buckets></bucket_spec></histogram_query><filter><global_operator>and</global_operator><filter_list><filter_item_entry><field_name>radio_mode</field_name><comp_operator>equals</comp_operator><value><![CDATA[0]]></value></filter_item_entry></filter_list></filter></query><query><qname>comp_channel_interference</qname><type>histogram</type><histogram_query><device_type>radio</device_type><field>channel_interference</field><bucket_spec><bucket_type>equal_width_buckets</bucket_type><equal_width_buckets><start_range>0</start_range><increments>10</increments><num_buckets>10</num_buckets></equal_width_buckets></bucket_spec></histogram_query><filter><global_operator>and</global_operator><filter_list><filter_item_entry><field_name>radio_mode</field_name><comp_operator>equals</comp_operator><value><![CDATA[0]]></value></filter_item_entry></filter_list></filter></query></aruba_queries>&UIDARUBA=' . $this->sid;
        $r = $this->http($url, $form, $this->cookie);
        $body = html_entity_decode($r['body']);
        $body = explode('<?xml version="1.0" encoding="UTF-8"?>', $body)[1];
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        $overall_goodput = $xml->response[0]->list_response->rows->row->value->__toString();
        $channel_quality = $xml->response[2]->histogram_response->values->value;
        $noise_floor = $xml->response[3]->histogram_response->values->value;
        $channel_busy = $xml->response[4]->histogram_response->values->value;
        $interference = $xml->response[5]->histogram_response->values->value;
        $datas = [
            "overall_goodput" => $overall_goodput,
            "channel_quality" => $channel_quality,
            "noise_floor" => $noise_floor,
            "channel_busy" => $channel_busy,
            "interference" => $interference,
        ];

        return $datas;
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
            curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
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
}
