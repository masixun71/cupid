<?php
declare(strict_types=1);

namespace Jue\Cupid\Notification;


use GuzzleHttp\Client;

class PushBear
{

    public static function push($sendKey, $text, $desp) {
        $client = new Client();
        $res = $client->request('GET', 'https://pushbear.ftqq.com/sub', [
            'query' => [
                'sendkey' => $sendKey,
                'text' => $text,
                'desp' => $desp
            ]
        ]);
    }



}