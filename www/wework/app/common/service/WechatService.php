<?php

namespace app\common\service;

use think\facade\Log;
use think\facade\Cache;

class WechatService {
// 获取企业微信访问令牌
    public static function getAccessToken($corpId, $secret) {
        $cacheKey = "wechat_access_token_{$corpId}";
        $accessToken = Cache::get($cacheKey);

        if (!$accessToken) {
            $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid={$corpId}&corpsecret={$secret}";
            $result = self::httpGet($url);

            if (isset($result['access_token'])) {
                $accessToken = $result['access_token'];
                Cache::set($cacheKey, $accessToken, 7000); // 提前200秒过期
            } else {
                Log::error("获取企业微信AccessToken失败: " . json_encode($result));
                throw new \Exception("获取企业微信AccessToken失败");
            }
        }

        return $accessToken;
    }

    // 获取会话内容
    public static function getChatRecords($accessToken, $seq, $limit = 100) {
        $url = "https://qyapi.weixin.qq.com/cgi-bin/chat/getchatdata?access_token={$accessToken}";
        $data = [
            'seq' => $seq,
            'limit' => $limit,
            'timeout' => 30,
            'type' => 1 // 消息类型，1表示文本消息
        ];

        $result = self::httpPost($url, json_encode($data));

        if (isset($result['errcode']) && $result['errcode'] == 0) {
            return $result;
        } else {
            Log::error("获取会话内容失败: " . json_encode($result));
            throw new \Exception("获取会话内容失败");
        }
    }

    // 解密会话内容
    public static function decryptChatData($privateKey, $encryptRandomKey, $encryptChatMsg) {
        $decryptRandKey = null;
        $decryptData = openssl_private_decrypt(
            base64_decode($encryptRandomKey),
            $decryptRandKey,
            $privateKey,
            OPENSSL_PKCS1_PADDING
        );

        if (!$decryptData) {
            Log::error("解密随机密钥失败");
            throw new \Exception("解密随机密钥失败");
        }

        // 这里需要使用企业微信提供的解密库
        // 假设存在一个 decryptData 方法
        $decryptedData = decryptData($decryptRandKey, $encryptChatMsg);

        return $decryptedData;
    }

    // HTTP请求方法
    private static function httpGet($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }

    private static function httpPost($url, $data) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }
}